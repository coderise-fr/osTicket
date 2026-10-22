-- ---------------------------------------------------------------------------
-- masquer-evenements-0.2.sql  (FACULTATIF, à exécuter une seule fois)
--
-- Jusqu'à la version 0.2, le plugin sla_first_response traçait la bascule du
-- plan de SLA par un événement « edited » ({"sla_id":[surveillé,fait]}), que
-- le gabarit d'origine du portail client affiche au client
-- (« SLA modifié de Priority 4h à … (Disabled) »). La version 0.3 utilise un
-- type d'événement propre, « sla_first_response », que le portail n'affiche
-- pas (README, section 10).
--
-- Cette requête convertit les anciens événements au nouveau type : le client
-- ne les voit plus, l'agent les voit toujours (avec le texte de la 0.3).
--
-- Prérequis : plugin 0.3 installé et AU MOINS UNE bascule faite depuis (le
-- type « sla_first_response » existe alors dans la table event ; sinon la
-- requête ne modifie rien).
-- À adapter : préfixe `ost_`, et les deux identifiants de plan (mêmes
-- valeurs que la configuration du plugin : 2 = surveillé, 3 = fait ici).
-- Ne convertit que les événements dont les données sont EXACTEMENT
-- {"sla_id":[surveillé,fait]} (une modification manuelle identique faite par
-- un agent serait aussi convertie : même sens).
-- Testé : test/phase4c-portail-client.php (MariaDB 10.11, MySQL 8.0).
-- ---------------------------------------------------------------------------
UPDATE ost_thread_event e
  JOIN ost_event v_old ON v_old.id = e.event_id AND v_old.name = 'edited'
  JOIN ost_event v_new ON v_new.name = 'sla_first_response'
   SET e.event_id = v_new.id
 WHERE e.thread_type = 'T'
   AND e.data = CONCAT('{"sla_id":[', 2, ',', 3, ']}');
