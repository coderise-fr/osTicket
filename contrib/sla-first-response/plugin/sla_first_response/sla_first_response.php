<?php
/*********************************************************************
    sla_first_response.php

    Plugin « SLA : première réponse ».

    osTicket traite le plan de SLA comme une échéance de RÉSOLUTION :
    Ticket::checkOverdue() marque « en retard » tout ticket ouvert dont
    l'échéance est dépassée, même si un agent a répondu. Ce plugin
    transforme ce comportement en délai de PREMIÈRE RÉPONSE : à la première
    réponse d'un agent sur un ticket portant le plan « surveillé », le
    ticket est basculé sur le plan « réponse faite », qui doit être
    DÉSACTIVÉ (Active = non) et non transitoire. Un plan désactivé n'a pas
    d'échéance (getSLADueDate() de la classe Ticket renvoie null), donc plus
    de retard.

    Seule une réponse ADRESSÉE AU CLIENT compte (voir isClientReply()) : une
    réponse enregistrée sans e-mail (« Répondre à : aucun ») ou adressée aux
    seuls collaborateurs n'arrête pas le chronomètre. Le critère est le même
    que dans sql/premiere-reponse.sql.

    Le plugin ne touche pas au cœur d'osTicket : il écoute le signal
    'threadentry.created' (include/class.thread.php, ThreadEntry::create()).

    Ce que le plugin NE fait PAS : mesurer ou compter. La mesure de la
    première réponse et le comptage des dépassements se font en SQL
    (contrib/sla-first-response/sql/), indépendamment du plugin.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.signal.php';

class SlaFirstResponseConfig extends PluginConfig {

    function getOptions() {
        return array(
            'sla_watch' => new TextboxField(array(
                'label' => __('Plan de SLA surveillé (identifiant)'),
                'hint' => __('Plan posé par le filtre d\'expéditeur, par exemple « Priority 4h ». L\'identifiant figure dans l\'URL de la page du plan (slas.php?id=…).'),
                'required' => true,
                'configuration' => array(
                    'size' => 8, 'length' => 10, 'validator' => 'number'),
            )),
            'sla_done' => new TextboxField(array(
                'label' => __('Plan de SLA après première réponse (identifiant)'),
                'hint' => __('Plan DÉSACTIVÉ et non transitoire, par exemple « Priority 4h - 1re reponse faite ».'),
                'required' => true,
                'configuration' => array(
                    'size' => 8, 'length' => 10, 'validator' => 'number'),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        $watch = SLA::lookup((int) $config['sla_watch']);
        $done = SLA::lookup((int) $config['sla_done']);

        if (!$watch)
            $errors['sla_watch'] = __('Plan de SLA introuvable');
        if (!$done)
            $errors['sla_done'] = __('Plan de SLA introuvable');
        if ($errors)
            return false;

        if ($watch->getId() == $done->getId())
            $errors['sla_done'] = __('Les deux plans doivent être différents');
        elseif ($done->isActive())
            $errors['sla_done'] = __('Le plan « réponse faite » doit être désactivé (sinon une échéance est recalculée)');
        elseif ($done->isTransient())
            $errors['sla_done'] = __('Le plan « réponse faite » ne doit pas être transitoire (osTicket le remplacerait lors d\'une modification du ticket)');

        return !$errors;
    }
}

class SlaFirstResponsePlugin extends Plugin {
    var $config_class = 'SlaFirstResponseConfig';

    // Une seule instance : le signal est global.
    function isMultiInstance() {
        return false;
    }

    function bootstrap() {
        $config = $this->getConfig();
        $watch = (int) $config->get('sla_watch');
        $done = (int) $config->get('sla_done');
        if (!$watch || !$done || $watch == $done)
            return;

        // Envoyé par ThreadEntry::create(), avant Ticket::onResponse().
        // Voir contrib/sla-first-response/README.md pour le détail.
        Signal::connect('threadentry.created',
            function ($entry) use ($watch, $done) {
                SlaFirstResponsePlugin::onEntry($entry, $watch, $done);
            });
    }

    /**
     * Critère « réponse adressée au client » (une seule définition, reprise
     * telle quelle dans sql/premiere-reponse.sql) :
     *
     * La réponse porte, dans thread_entry.recipients, la liste des
     * destinataires calculée par Ticket->postReply() AVANT l'enregistrement
     * (JSON : {"to": {...}, "cc": {...}}). Dans postReply(), seul le
     * propriétaire du ticket (le client) est placé dans « to » (choix
     * « Propriétaire » ou « Tous les destinataires actifs ») ; les
     * collaborateurs sont toujours dans « cc ». « Ne pas envoyer d'e-mail »
     * ne laisse aucune liste (colonne NULL). Donc :
     *   « to » non vide  <=>  le client est destinataire de la réponse.
     *
     * Les indicateurs FLAG_REPLY_USER / FLAG_REPLY_ALL ne suffisent pas : ils
     * sont aussi posés quand seuls des collaborateurs sont destinataires.
     * thread_entry_email ne sert pas non plus : osTicket n'y enregistre que
     * les e-mails ENTRANTS (ThreadEntry->saveEmailInfo, « TODO: include
     * outgoing emails »).
     *
     * Limite : c'est l'intention enregistrée par osTicket (liste des
     * destinataires), pas la preuve de remise par le serveur de messagerie.
     */
    static function isClientReply($entry) {
        $raw = $entry->recipients;
        if (!is_string($raw) || $raw === '')
            return false;
        $list = json_decode($raw, true);
        return is_array($list) && !empty($list['to']);
    }

    /**
     * Bascule le ticket sur le plan « réponse faite » si $entry est la
     * réponse d'un agent humain sur un ticket au plan « surveillé ».
     */
    static function onEntry($entry, $watch, $done) {
        try {
            // Réponses uniquement (pas les messages du client, pas les notes)
            if (!($entry instanceof ResponseThreadEntry))
                return;

            // Agent humain uniquement : une réponse automatique de filtre
            // (« canned ») n'a pas d'agent, ou porte l'auteur SYSTEM.
            if ($entry->getStaffId() < 1
                    || $entry->isSystem()
                    || $entry->hasFlag(ThreadEntry::FLAG_HIDDEN)
                    || $entry->getPoster() === __('SYSTEM (Canned Reply)'))
                return;

            // Réponse effectivement adressée au client uniquement.
            if (!self::isClientReply($entry))
                return;

            // Tickets uniquement (le fil d'une tâche porte aussi des réponses)
            $thread = $entry->getThread();
            if (!$thread
                    || $thread->getObjectType() != ObjectModel::OBJECT_TYPE_TICKET
                    || !($ticket = $thread->getObject())
                    || !($ticket instanceof Ticket)
                    || $ticket->getSLAId() != $watch)
                return;

            if (!$ticket->setSLAId($done))
                return;

            // Le plan « réponse faite » est inactif : l'échéance devient
            // null et l'éventuel indicateur « en retard » est effacé. Le
            // dépassement éventuel reste mesurable en SQL (première réponse
            // moins création) et par l'événement `overdue` déjà écrit.
            $ticket->updateEstDueDate();

            // Trace dans l'historique du ticket : « SLA modifié de … à … ».
            $ticket->logEvent('edited', array('sla_id' => array($watch, $done)));
        }
        catch (Throwable $t) {
            // Ne jamais empêcher l'envoi d'une réponse à cause du plugin.
            global $ost;
            if ($ost)
                $ost->logWarning('SLA : première réponse', $t->getMessage(), false);
        }
    }
}
?>
