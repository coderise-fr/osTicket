<?php
/*********************************************************************
    event.php

    Plugin « SLA : première réponse » : événement d'historique écrit lors
    de la bascule du plan de SLA à la première réponse.

    Jusqu'à la version 0.2, le plugin écrivait un événement « edited »
    (« SLA modifié de … à … »). Le gabarit d'origine du portail client
    (include/client/templates/thread-entries.tmpl.php) affiche au client
    les événements created, closed, reopened, edited, collab et merged :
    le client voyait donc « Mis à jour par … : SLA Priority 4h → … (Disabled) ».

    Depuis la version 0.3, la bascule est tracée par un type d'événement
    propre au plugin, « sla_first_response » (une ligne ajoutée à la table
    `event`, créée à la première utilisation). Le portail client ne
    l'affiche pas (il filtre par liste blanche de types) ; le panneau
    agent, qui affiche tous les événements, le montre avec le texte de
    getDescription(). Double garde : en mode client, getDescription()
    renvoie une chaîne vide, que les gabarits client n'affichent pas.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access denied');

class SlaFirstResponseEvent extends ThreadEvent {
    static $icon = 'time';
    static $state = 'sla_first_response';

    // Colonne event.description : 60 caractères au plus.
    const DESCRIPTION = 'SLA first response (plugin sla_first_response)';

    function getIcon() {
        return static::$icon;
    }

    function getDescription($mode=self::MODE_STAFF) {
        // Jamais affiché au client : les noms de plans sont internes.
        if ($mode == self::MODE_CLIENT)
            return '';

        $data = $this->getData();
        $sla = is_array($data) ? @$data['sla_id'] : null;
        if (!is_array($sla) || count($sla) != 2)
            return $this->template(
                __('<b>{somebody}</b> : première réponse adressée au client {timestamp}'), $mode);

        list($old, $new) = $sla;
        return $this->template(sprintf(
            __('<b>{somebody}</b> : première réponse adressée au client {timestamp} — plan de SLA <strong>%1$s</strong> remplacé par <strong>%2$s</strong>'),
            self::slaName($old), self::slaName($new)), $mode);
    }

    private static function slaName($id) {
        $name = SLA::getSLAName($id) ?: ('#' . (int) $id);
        // Les accolades seraient prises pour des jetons par template().
        return str_replace(array('{', '}'), array('&#123;', '&#125;'),
            Format::htmlchars($name));
    }

    /**
     * Identifiant du type d'événement dans la table `event`, créé s'il
     * n'existe pas encore (0 en cas d'échec).
     */
    static function getEventId() {
        static $id = 0;
        if ($id)
            return $id;
        if ($id = self::findEventId())
            return $id;
        try {
            Event::__create(array(
                'name' => static::$state,
                'description' => self::DESCRIPTION,
            ));
        }
        catch (Throwable $t) {
            // Création concurrente (clé unique sur le nom) : relu ci-dessous.
        }
        return $id = self::findEventId();
    }

    private static function findEventId() {
        $row = Event::objects()
            ->filter(array('name' => static::$state))
            ->values_flat('id')
            ->first();
        return $row ? (int) $row[0] : 0;
    }

    /**
     * Journalise la bascule $watch -> $done sur le fil du ticket.
     * Équivaut à $ticket->logEvent('edited', array('sla_id' => …)) de la
     * version 0.2, avec le type « sla_first_response » au lieu de « edited ».
     */
    static function log($ticket, $watch, $done) {
        global $thisstaff;

        $data = array('sla_id' => array((int) $watch, (int) $done));

        // Même signal que Ticket::logEvent('edited') : un éventuel plugin
        // d'audit continue de voir la modification du plan.
        $type = array('type' => 'edited', 'fields' => $data);
        Signal::send('object.created', $ticket, $type);

        if (!($thread = $ticket->getThread()) || !($eid = self::getEventId()))
            return false;

        // Comme ThreadEvents::log(), avec l'identifiant de type explicite
        // (Event::getIdByName() garde en cache la liste lue en début de
        // requête, sans le type créé entre-temps).
        $event = ThreadEvent::forTicket($ticket, static::$state);
        $event->username = ($thisstaff instanceof Staff)
            ? $thisstaff->getUserName() : 'SYSTEM';
        $event->event_id = $eid;
        $event->data = JsonDataEncoder::encode($data);
        $thread->getEvents()->add($event);
        return $event->save();
    }
}
