-- ---------------------------------------------------------------------------
-- Dépassements du délai de première réponse, par organisation et par année.
-- Sert à appliquer la clause « délai dépassé plus de 3 fois par an ».
--
-- Définition d'un dépassement (la même que premiere-reponse.sql) :
--   * première réponse d'agent arrivée plus de @delai_heures heures après la
--     création du ticket, OU
--   * aucune réponse d'agent et le délai est déjà écoulé.
--
-- On compte des TICKETS (un par ticket au plus), jamais des événements
-- `overdue` : voir la requête 3 pour la comparaison et README.md pour les
-- raisons.
--
-- « Année » = année civile de création du ticket (YEAR(t.created)). Si
-- l'abonnement court d'une date anniversaire à l'autre, utiliser la requête 2.
--
-- Adapter : préfixe `ost_`, noms des plans de SLA, @delai_heures.
-- ---------------------------------------------------------------------------

SET @delai_heures = 4;

-- === Requête 1 : par organisation et par année civile ======================
SELECT
    COALESCE(x.org_id, 0)                        AS org_id,
    COALESCE(x.organisation, '(sans organisation)') AS organisation,
    x.annee,
    COUNT(*)                                     AS tickets_priority,
    SUM(x.depasse)                               AS depassements,
    IF(SUM(x.depasse) > 3, 'OUI', 'non')         AS clause_prolongation
FROM (
    SELECT
        o.id   AS org_id,
        o.name AS organisation,
        YEAR(t.created) AS annee,
        CASE
            WHEN r.premiere_reponse_le IS NULL
                THEN (NOW() > t.created + INTERVAL @delai_heures HOUR)
            ELSE (r.premiere_reponse_le > t.created + INTERVAL @delai_heures HOUR)
        END AS depasse
    FROM ost_ticket t
    JOIN ost_sla s
        ON s.id = t.sla_id
    JOIN ost_thread th
        ON th.object_type = 'T'
       AND th.object_id = t.ticket_id
    LEFT JOIN (
        SELECT te.thread_id, MIN(te.created) AS premiere_reponse_le
          FROM ost_thread_entry te
         WHERE te.type = 'R'
           AND te.staff_id > 0
           AND (te.flags & (128 | 4)) = 0
           AND te.poster NOT LIKE 'SYSTEM%'
         GROUP BY te.thread_id
    ) r ON r.thread_id = th.id
    LEFT JOIN ost_user u ON u.id = t.user_id
    LEFT JOIN ost_organization o ON o.id = u.org_id
    WHERE s.name IN ('Priority 4h', 'Priority 4h - 1re reponse faite')
) x
GROUP BY x.org_id, x.organisation, x.annee
ORDER BY x.organisation, x.annee;


-- === Requête 2 : une organisation, période d'abonnement explicite ==========
-- (date anniversaire plutôt qu'année civile ; adapter les trois valeurs)
-- Les tickets sont rattachés à la période par leur date de création.
SET @org_id = 1;
SET @debut  = '2026-03-01 00:00:00';
SET @fin    = '2027-03-01 00:00:00';   -- exclue

SELECT
    COUNT(*)                                     AS tickets_priority,
    SUM(CASE
            WHEN r.premiere_reponse_le IS NULL
                THEN (NOW() > t.created + INTERVAL @delai_heures HOUR)
            ELSE (r.premiere_reponse_le > t.created + INTERVAL @delai_heures HOUR)
        END)                                     AS depassements
FROM ost_ticket t
JOIN ost_sla s
    ON s.id = t.sla_id
JOIN ost_thread th
    ON th.object_type = 'T'
   AND th.object_id = t.ticket_id
LEFT JOIN (
    SELECT te.thread_id, MIN(te.created) AS premiere_reponse_le
      FROM ost_thread_entry te
     WHERE te.type = 'R'
       AND te.staff_id > 0
       AND (te.flags & (128 | 4)) = 0
       AND te.poster NOT LIKE 'SYSTEM%'
     GROUP BY te.thread_id
) r ON r.thread_id = th.id
JOIN ost_user u ON u.id = t.user_id
WHERE s.name IN ('Priority 4h', 'Priority 4h - 1re reponse faite')
  AND u.org_id = @org_id
  AND t.created >= @debut
  AND t.created <  @fin;


-- === Requête 3 : recoupement avec les événements `overdue` =================
-- Ne PAS utiliser comme compteur de la clause : l'événement est écrit par la
-- tâche cron (retard possible, un cron manquant ou tardif ne l'écrit pas si la
-- réponse arrive avant), peut être écrit plusieurs fois pour un même ticket
-- (réouverture, modification du ticket), et peut venir d'une échéance
-- manuelle (« Due Date ») sans rapport avec la première réponse.
SELECT
    COALESCE(o.name, '(sans organisation)') AS organisation,
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
LEFT JOIN ost_organization o ON o.id = u.org_id
WHERE s.name IN ('Priority 4h', 'Priority 4h - 1re reponse faite')
GROUP BY o.id, o.name, YEAR(e.timestamp)
ORDER BY organisation, annee;
