-- ---------------------------------------------------------------------------
-- Première réponse d'un agent AU CLIENT, par ticket Priority.
--
-- Mesure = date de la première réponse d'un AGENT adressée au CLIENT
-- (thread_entry de type 'R', staff_id > 0, client destinataire) moins la date
-- de création du ticket (ticket.created).
--
-- DÉFINITION UNIQUE de « première réponse » (reprise telle quelle dans
-- depassements-par-client-periode.sql, et par le plugin : méthode
-- isClientReply() de plugin/sla_first_response/sla_first_response.php) :
--
--   type 'R'                              réponse (pas message 'M', pas note 'N')
--   staff_id > 0                          auteur = agent
--   (flags & (FLAG_SYSTEM|FLAG_HIDDEN))=0 ni système (0x80), ni masquée (0x04)
--   poster NOT LIKE 'SYSTEM%'             réponse « canned » de filtre
--   recipients LIKE '%"to":%'             le CLIENT est destinataire
--
-- Le dernier critère distingue « réponse envoyée au client » de « réponse
-- enregistrée sans e-mail » : Ticket->postReply() calcule la liste des
-- destinataires et la stocke en JSON dans thread_entry.recipients
-- ({"to":{"<id>":"Nom <adresse>"},"cc":{...}}). Seul le propriétaire du
-- ticket est placé dans "to" (choix « Propriétaire » ou « Tous les
-- destinataires actifs ») ; les collaborateurs sont dans "cc" ; « Ne pas
-- envoyer d'e-mail » ne laisse aucune liste (NULL). Les indicateurs
-- FLAG_REPLY_USER/ALL (0x200/0x100) ne suffisent pas : ils sont aussi posés
-- pour des collaborateurs seuls. thread_entry_email ne convient pas : il ne
-- contient que les e-mails entrants.
--
-- Ce qui ne compte PAS comme première réponse :
--   * les réponses automatiques par e-mail (accusé de réception, réponse
--     automatique à un nouveau message) : osTicket ne les enregistre pas dans
--     le fil, elles n'apparaissent donc jamais dans thread_entry ;
--   * la réponse « canned » d'un filtre : staff_id = 0 et FLAG_SYSTEM, ou
--     auteur SYSTEM…, et de toute façon sans destinataire ;
--   * les réponses « Ne pas envoyer d'e-mail » (« à personne ») ;
--   * les réponses adressées aux seuls collaborateurs (choix non proposé par
--     l'interface agent, possible par script) : le client n'est pas prévenu ;
--   * les notes internes (type 'N'), les messages du client (type 'M') ;
--   * les entrées masquées (FLAG_HIDDEN).
--
-- Ticket ouvert par un agent pour le client (téléphone) : il compte. La
-- réponse jointe à la création est une entrée 'R' comme les autres : si elle
-- est envoyée au client, elle donne 0 minute. Attention : le formulaire agent
-- ne propose le choix « Alerter » que si Admin > Paramètres > Alertes et
-- avis > « Nouveau ticket par un agent : propriétaire du ticket »
-- (ticket_notice_active) est activé ; sinon la réponse jointe est enregistrée
-- sans destinataire et ne compte pas.
--
-- Adapter avant usage :
--   1. le préfixe de tables `ost_` ;
--   2. les noms des deux plans de SLA dans la clause WHERE (plan posé par le
--      filtre d'expéditeur, et plan posé par le plugin une fois la première
--      réponse faite) ;
--   3. @delai_heures (4 pour Priority).
--
-- Remarques de lecture :
--   * ticket.created = date à laquelle osTicket a CRÉÉ le ticket, pas la date
--     d'envoi du client. Pour une boîte relevée en POP/IMAP, le décalage peut
--     aller jusqu'à la fréquence de relève.
--   * Dépassement = STRICTEMENT plus de @delai_heures heures (4 h 00 min 00 s
--     exactement : respecté). Les dates sont des DATETIME à la seconde.
--   * Les dates sont dans le fuseau de la base ; NOW() aussi (même serveur).
--   * La liste des destinataires est celle ENREGISTRÉE par osTicket au moment
--     de la réponse, pas une preuve de remise par le serveur de messagerie.
--   * Un ticket « fusionné » ou « lié » peut avoir des réponses issues du
--     ticket enfant : à vérifier à la main si vous utilisez la fusion.
-- ---------------------------------------------------------------------------

SET @delai_heures = 4;

SELECT
    t.ticket_id,
    t.number                                              AS numero,
    t.user_id                                             AS client_id,
    u.name                                                AS client,
    ue.address                                            AS client_email,
    COALESCE(o.name, '(sans organisation)')               AS organisation,
    s.name                                                AS plan_sla,
    t.created                                             AS cree_le,
    r.premiere_reponse_le,
    TIMESTAMPDIFF(MINUTE, t.created, r.premiere_reponse_le) AS minutes_avant_reponse,
    CASE
        WHEN r.premiere_reponse_le IS NULL
             AND NOW() > t.created + INTERVAL @delai_heures HOUR
            THEN 'sans reponse - delai depasse'
        WHEN r.premiere_reponse_le IS NULL
            THEN 'sans reponse - dans le delai'
        WHEN r.premiere_reponse_le > t.created + INTERVAL @delai_heures HOUR
            THEN 'depasse'
        ELSE 'respecte'
    END                                                   AS statut
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
LEFT JOIN ost_user u
    ON u.id = t.user_id
LEFT JOIN ost_user_email ue
    ON ue.id = u.default_email_id
LEFT JOIN ost_organization o
    ON o.id = u.org_id
WHERE s.name IN ('Priority 4h', 'Priority 4h - 1re reponse faite')
ORDER BY t.created;
