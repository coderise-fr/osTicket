-- ---------------------------------------------------------------------------
-- Première réponse d'un agent, par ticket Priority.
--
-- Mesure = date de la première réponse d'un AGENT (thread_entry de type 'R',
-- staff_id > 0) moins date de création du ticket (ticket.created).
--
-- Ce qui ne compte PAS comme première réponse :
--   * les réponses automatiques par e-mail (accusé de réception, réponse
--     automatique à un nouveau message) : osTicket ne les enregistre pas dans
--     le fil, elles n'apparaissent donc jamais dans thread_entry ;
--   * la réponse « canned » d'un filtre : enregistrée dans le fil avec
--     staff_id = 0 et l'indicateur FLAG_SYSTEM (0x80), sauf si le ticket est
--     créé depuis le panneau agent (voir le filtre sur le nom de l'auteur) ;
--   * les notes internes (type 'N') et les messages du client (type 'M') ;
--   * les entrées masquées (FLAG_HIDDEN, 0x04).
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
--   * Dépassement = strictement plus de @delai_heures heures (comme osTicket,
--     qui marque « en retard » quand l'échéance est < NOW()).
--   * Les dates sont dans le fuseau de la base ; NOW() aussi (même serveur).
--   * Un ticket « fusionné » ou « lié » peut avoir des réponses issues du
--     ticket enfant : à vérifier à la main si vous utilisez la fusion.
-- ---------------------------------------------------------------------------

SET @delai_heures = 4;

SELECT
    t.ticket_id,
    t.number                                              AS numero,
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
     WHERE te.type = 'R'                 -- réponse (pas message 'M', pas note 'N')
       AND te.staff_id > 0               -- auteur = agent
       AND (te.flags & (128 | 4)) = 0    -- ni FLAG_SYSTEM (0x80), ni FLAG_HIDDEN (0x04)
       AND te.poster NOT LIKE 'SYSTEM%'  -- réponse canned d'un filtre créée depuis le panneau agent
     GROUP BY te.thread_id
) r
    ON r.thread_id = th.id
LEFT JOIN ost_user u
    ON u.id = t.user_id
LEFT JOIN ost_organization o
    ON o.id = u.org_id
WHERE s.name IN ('Priority 4h', 'Priority 4h - 1re reponse faite')
ORDER BY t.created;

-- Variante plus stricte (réponse effectivement adressée au client) :
-- ajouter dans la sous-requête
--     AND (te.flags & (256 | 512)) <> 0   -- FLAG_REPLY_ALL (0x100) ou FLAG_REPLY_USER (0x200)
-- Une réponse postée avec « Répondre à : personne » n'est alors plus comptée
-- (osTicket l'enregistre dans le fil sans envoyer d'e-mail ; le client ne la
-- voit que s'il ouvre le portail).
