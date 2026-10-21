-- ---------------------------------------------------------------------------
-- Dépassements du délai de première réponse, PAR CLIENT et PAR PÉRIODE
-- D'ABONNEMENT À DATE ANNIVERSAIRE. Sert à appliquer la clause « délai dépassé
-- 4 fois ou plus sur une période d'abonnement » (prolongation d'un mois).
--
-- Remplace depassements-par-organisation-annee.sql : tous les clients Priority
-- sont dans UNE organisation (« Priority »), compter par organisation les
-- mélangerait. Le compte se fait par utilisateur osTicket (ticket.user_id).
--
-- ENTRÉE : les abonnements, écrits par le plugin contrib/stripe-sync dans
-- ost_config, une ligne par abonnement Priority :
--     namespace = 'stripesync'
--     key       = 'sub:<subscription_id>'
--     value     = '<user_id osTicket>|<start_date>|<ended>|<status>'
-- start_date = epoch UTC (secondes) du tout premier démarrage de
-- l'abonnement ; ended = epoch UTC de la fin, ou chaîne vide si en cours ;
-- status = statut Stripe. Pas de JSON, séparateur '|' : la valeur est
-- découpée ici par SUBSTRING_INDEX. Une ligne qui ne respecte pas ce format
-- n'est pas utilisée et apparaît dans la section 4.
--
-- DÉFINITIONS
--   * Première réponse : la MÊME définition que premiere-reponse.sql (réponse
--     d'un agent, non système, non masquée, ADRESSÉE AU CLIENT : colonne
--     recipients avec "to"). Voir l'en-tête de ce fichier pour le détail.
--   * Dépassement (un ticket compte au plus une fois) : première réponse
--     arrivée STRICTEMENT plus de @delai_heures heures (4 h 00 min 00 s) après
--     la création, OU aucune réponse et ce délai est déjà écoulé. Jamais les
--     événements `overdue` (voir README, et la requête B ci-dessous).
--   * Période d'abonnement : [début + n ans ; début + (n+1) ans[, calculée en
--     UTC à la seconde près à partir du premier démarrage. Elle est coupée à
--     la date de fin si l'abonnement est terminé ; la date de fin est exclue.
--     Un abonnement terminé puis repris crée un NOUVEL abonnement (nouvelle
--     ligne sub:, nouveau début) : l'ancienneté ne se cumule pas.
--   * 29 février : DATE_ADD(début, INTERVAL n YEAR) ramène le 29 février au
--     28 février les années non bissextiles (et le redonne au 29 les années
--     bissextiles). Les bornes sont calculées depuis la date de début (pas
--     d'année en année), donc une période finit exactement où la suivante
--     commence et aucun ticket n'est oublié ni compté deux fois. Exemple :
--     début le 29/02/2024 12:00 UTC => périodes 29/02/2024 12:00 - 28/02/2025
--     12:00, puis 28/02/2025 12:00 - 28/02/2026 12:00, ..., puis le
--     29/02/2028 pour le 4e anniversaire.
--   * Rattachement : un ticket Priority (reconnu par son plan de SLA, comme
--     dans premiere-reponse.sql) est rattaché au client ticket.user_id et à la
--     période de CE client qui contient sa date de création. Si deux
--     abonnements du même client se chevauchent, le plus récent l'emporte
--     (pas de double compte).
--   * Un ticket qu'aucune période ne contient va dans la section 3 « sans
--     abonnement connu » : client sans ligne sub:, ou créé avant le début ou à
--     partir de la fin de ses abonnements. Il n'est jamais perdu.
--
-- SORTIE : un seul résultat, colonne `section` :
--     1 - par client et periode   une ligne par client, abonnement et période
--                                  ayant au moins un ticket Priority
--     2 - prolongation due        les lignes de la section 1 à @seuil
--                                  dépassements ou plus (rappel dédié)
--     3 - sans abonnement connu   une ligne par client et motif
--     4 - lignes sub: illisibles  diagnostic du format
--   `prolongation_due` = 1 quand les dépassements de la période atteignent
--   @seuil (4 : « plus de 3 fois »). Une période EN COURS (periode_en_cours = 1)
--   peut encore évoluer. Un abonnement terminé (fin_abonnement renseignée)
--   peut atteindre le seuil : la décision de prolonger est alors à prendre à
--   la main.
--
-- FUSEAU : les dates d'osTicket sont dans le fuseau de la base (NOW() de
-- l'écriture). Elles sont converties en UTC avec UNIX_TIMESTAMP() DANS LA
-- SESSION OÙ VOUS EXÉCUTEZ CETTE REQUÊTE : utiliser le même fuseau de session
-- que osTicket (par défaut, celui du serveur). Les dates de sortie sont en UTC.
--
-- Adapter : préfixe `ost_`, noms des plans de SLA, @delai_heures, @seuil.
-- Compatible MariaDB 10.11 et MySQL 8.0 (WITH, fonctions de fenêtre).
-- ---------------------------------------------------------------------------

SET @delai_heures = 4;
SET @seuil = 4;

WITH
nombres AS (   -- rangs d'anniversaire possibles (0 à 29 ans)
    SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
    UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14
    UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
    UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
    UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29
),
-- Abonnements : une ligne sub: bien formée = une ligne ici
abo AS (
    SELECT
        c.`key`                                                         AS cle,
        CAST(SUBSTRING_INDEX(c.value, '|', 1) AS UNSIGNED)              AS user_id,
        SUBSTRING_INDEX(c.value, '|', -1)                               AS statut_stripe,
        TIMESTAMPADD(SECOND,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(c.value, '|', 2), '|', -1) AS UNSIGNED),
            CAST('1970-01-01 00:00:00' AS DATETIME))                    AS debut_utc,
        CASE WHEN SUBSTRING_INDEX(SUBSTRING_INDEX(c.value, '|', 3), '|', -1) = ''
             THEN NULL
             ELSE TIMESTAMPADD(SECOND,
                CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(c.value, '|', 3), '|', -1) AS UNSIGNED),
                CAST('1970-01-01 00:00:00' AS DATETIME))
        END                                                             AS fin_utc
    FROM ost_config c
    WHERE c.namespace = 'stripesync'
      AND c.`key` LIKE 'sub:%'
      AND c.value REGEXP '^[0-9]+[|][0-9]+[|][0-9]*[|][^|]*$'
),
-- Périodes anniversaire de chaque abonnement
periode AS (
    SELECT
        a.cle, a.user_id, a.statut_stripe, a.debut_utc, a.fin_utc,
        nb.n                                              AS rang,
        DATE_ADD(a.debut_utc, INTERVAL nb.n YEAR)         AS p_debut,
        CASE WHEN a.fin_utc IS NOT NULL
              AND a.fin_utc < DATE_ADD(a.debut_utc, INTERVAL (nb.n + 1) YEAR)
             THEN a.fin_utc
             ELSE DATE_ADD(a.debut_utc, INTERVAL (nb.n + 1) YEAR)
        END                                               AS p_fin
    FROM abo a
    JOIN nombres nb
      ON a.fin_utc IS NULL
      OR DATE_ADD(a.debut_utc, INTERVAL nb.n YEAR) < a.fin_utc
),
-- Tickets Priority et leur dépassement (définition de premiere-reponse.sql)
ticket_prio AS (
    SELECT
        t.ticket_id,
        t.number                                          AS numero,
        t.user_id,
        t.created,
        TIMESTAMPADD(SECOND, UNIX_TIMESTAMP(t.created),
            CAST('1970-01-01 00:00:00' AS DATETIME))      AS cree_utc,
        CASE
            WHEN r.premiere_reponse_le IS NULL
                THEN (NOW() > t.created + INTERVAL @delai_heures HOUR)
            ELSE (r.premiere_reponse_le > t.created + INTERVAL @delai_heures HOUR)
        END                                               AS depasse
    FROM ost_ticket t
    JOIN ost_sla s
        ON s.id = t.sla_id
    JOIN ost_thread th
        ON th.object_type = 'T'
       AND th.object_id = t.ticket_id
    LEFT JOIN (
        SELECT te.thread_id,
               MIN(te.created) AS premiere_reponse_le
          FROM ost_thread_entry te
         WHERE te.type = 'R'                       -- réponse (pas message 'M', pas note 'N')
           AND te.staff_id > 0                     -- auteur = agent
           AND (te.flags & (128 | 4)) = 0          -- ni FLAG_SYSTEM (0x80), ni FLAG_HIDDEN (0x04)
           AND te.poster NOT LIKE 'SYSTEM%'        -- réponse canned d'un filtre créée depuis le panneau agent
           AND te.recipients LIKE '%"to":%'        -- le client est destinataire (pas « aucun », pas collaborateurs seuls)
         GROUP BY te.thread_id
    ) r
        ON r.thread_id = th.id
    WHERE s.name IN ('Priority 4h', 'Priority 4h - 1re reponse faite')
),
-- Un ticket -> au plus une période (la plus récente si chevauchement)
rattache AS (
    SELECT x.*
    FROM (
        SELECT
            tp.ticket_id, tp.numero, tp.user_id, tp.created, tp.depasse,
            p.cle, p.rang, p.p_debut, p.p_fin,
            ROW_NUMBER() OVER (PARTITION BY tp.ticket_id
                               ORDER BY p.p_debut DESC, p.cle) AS rn
        FROM ticket_prio tp
        JOIN periode p
          ON p.user_id = tp.user_id
         AND tp.cree_utc >= p.p_debut
         AND tp.cree_utc <  p.p_fin
    ) x
    WHERE x.rn = 1
),
par_periode AS (
    SELECT
        r.user_id, r.cle, r.rang, r.p_debut, r.p_fin,
        COUNT(*)                                                       AS tickets,
        CAST(SUM(r.depasse) AS SIGNED)                                 AS depassements,
        GROUP_CONCAT(CASE WHEN r.depasse THEN r.numero END
                     ORDER BY r.created, r.ticket_id SEPARATOR ', ')   AS numeros
    FROM rattache r
    GROUP BY r.user_id, r.cle, r.rang, r.p_debut, r.p_fin
),
sans_abo AS (
    SELECT
        tp.user_id,
        CASE WHEN EXISTS (SELECT 1 FROM abo a WHERE a.user_id = tp.user_id)
             THEN 'ticket hors des periodes d''abonnement connues (avant le debut ou apres la fin)'
             ELSE 'aucune ligne sub: pour ce client'
        END                                                            AS motif,
        COUNT(*)                                                       AS tickets,
        CAST(SUM(tp.depasse) AS SIGNED)                                AS depassements,
        GROUP_CONCAT(CASE WHEN tp.depasse THEN tp.numero END
                     ORDER BY tp.created, tp.ticket_id SEPARATOR ', ') AS numeros
    FROM ticket_prio tp
    WHERE NOT EXISTS (SELECT 1 FROM rattache r WHERE r.ticket_id = tp.ticket_id)
    GROUP BY tp.user_id, 2
)

SELECT * FROM (
    SELECT
        '1 - par client et periode'                      AS section,
        pp.user_id,
        u.name                                           AS client,
        ue.address                                       AS email,
        pp.cle                                           AS abonnement,
        a.statut_stripe,
        a.debut_utc                                      AS debut_abonnement,
        a.fin_utc                                        AS fin_abonnement,
        pp.rang                                          AS periode_n,
        pp.p_debut                                       AS periode_debut_utc,
        pp.p_fin                                         AS periode_fin_utc,
        (UTC_TIMESTAMP() >= pp.p_debut AND UTC_TIMESTAMP() < pp.p_fin) AS periode_en_cours,
        pp.tickets                                       AS tickets_priority,
        pp.depassements,
        (pp.depassements >= @seuil)                      AS prolongation_due,
        pp.numeros                                       AS tickets_en_depassement,
        CAST(NULL AS CHAR)                               AS motif
    FROM par_periode pp
    JOIN abo a ON a.cle = pp.cle
    LEFT JOIN ost_user u ON u.id = pp.user_id
    LEFT JOIN ost_user_email ue ON ue.id = u.default_email_id

    UNION ALL

    SELECT
        '2 - prolongation due',
        pp.user_id, u.name, ue.address, pp.cle, a.statut_stripe,
        a.debut_utc, a.fin_utc, pp.rang, pp.p_debut, pp.p_fin,
        (UTC_TIMESTAMP() >= pp.p_debut AND UTC_TIMESTAMP() < pp.p_fin),
        pp.tickets, pp.depassements, 1, pp.numeros, CAST(NULL AS CHAR)
    FROM par_periode pp
    JOIN abo a ON a.cle = pp.cle
    LEFT JOIN ost_user u ON u.id = pp.user_id
    LEFT JOIN ost_user_email ue ON ue.id = u.default_email_id
    WHERE pp.depassements >= @seuil

    UNION ALL

    SELECT
        '3 - sans abonnement connu',
        sa.user_id, u.name, ue.address,
        CAST(NULL AS CHAR), CAST(NULL AS CHAR),
        CAST(NULL AS DATETIME), CAST(NULL AS DATETIME), NULL,
        CAST(NULL AS DATETIME), CAST(NULL AS DATETIME), NULL,
        sa.tickets, sa.depassements, NULL, sa.numeros, sa.motif
    FROM sans_abo sa
    LEFT JOIN ost_user u ON u.id = sa.user_id
    LEFT JOIN ost_user_email ue ON ue.id = u.default_email_id

    UNION ALL

    SELECT
        '4 - lignes sub: illisibles',
        NULL, NULL, NULL,
        c.`key`, CAST(NULL AS CHAR),
        CAST(NULL AS DATETIME), CAST(NULL AS DATETIME), NULL,
        CAST(NULL AS DATETIME), CAST(NULL AS DATETIME), NULL,
        NULL, NULL, NULL, CAST(NULL AS CHAR),
        CONCAT('format attendu user_id|debut|fin|statut, valeur lue : ', c.value)
    FROM ost_config c
    WHERE c.namespace = 'stripesync'
      AND c.`key` LIKE 'sub:%'
      AND NOT (c.value REGEXP '^[0-9]+[|][0-9]+[|][0-9]*[|][^|]*$')
) rapport
ORDER BY section, client, abonnement, periode_debut_utc;


-- === Requête B : recoupement avec les événements `overdue` (information) ===
-- Ne PAS utiliser comme compteur de la clause : l'événement est écrit par la
-- tâche cron (retard possible, un cron manquant ou tardif ne l'écrit pas si la
-- réponse arrive avant), peut être écrit plusieurs fois pour un même ticket
-- (réouverture, modification du ticket), et peut venir d'une échéance
-- manuelle (« Due Date ») sans rapport avec la première réponse. Même
-- découpage par client que ci-dessus ; à comparer avec la colonne
-- `depassements` de la section 1 (par année civile de l'événement ici).
SELECT
    t.user_id                               AS client_id,
    u.name                                  AS client,
    YEAR(e.timestamp)                       AS annee,
    COUNT(*)                                AS evenements_overdue,
    COUNT(DISTINCT t.ticket_id)             AS tickets_distincts
FROM ost_thread_event e
JOIN ost_event ev
    ON ev.id = e.event_id
   AND ev.name = 'overdue'
JOIN ost_thread th
    ON th.id = e.thread_id
   AND th.object_type = 'T'
JOIN ost_ticket t
    ON t.ticket_id = th.object_id
JOIN ost_sla s
    ON s.id = t.sla_id
LEFT JOIN ost_user u ON u.id = t.user_id
WHERE s.name IN ('Priority 4h', 'Priority 4h - 1re reponse faite')
GROUP BY t.user_id, u.name, YEAR(e.timestamp)
ORDER BY client, annee;
