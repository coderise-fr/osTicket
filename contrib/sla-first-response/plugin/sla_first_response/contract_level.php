<?php
/*********************************************************************
    contract_level.php

    Plugin « SLA : première réponse » : niveau de contrat d'un client
    (« Priority » / « Standard »), API publique pour les thèmes du portail.

    osTicket n'a pas de notion de niveau de contrat. Chez Coderise, le
    niveau est porté par l'appartenance à l'organisation « Priority »
    (créée et alimentée par le plugin contrib/stripe-sync). Ce fichier
    expose cette règle, en un seul endroit, à tous les thèmes :

        coderise_contract_level($subject = null)

    $subject : null (client connecté du portail, $thisclient), User,
    Ticket (son PROPRIÉTAIRE, jamais un collaborateur), ClientSession ou
    EndUser. Retour :

        array(
            'level'    => 'priority' | 'standard',
            'label'    => libellé affichable (configurable, passé par __()),
            'org_id'   => int|null     organisation du propriétaire,
            'org_name' => string|null  nom de cette organisation,
            'source'   => 'organisation' | 'default',
        )

    Jamais d'exception ; résultats mémorisés pour la durée de la requête.
    Voir contrib/sla-first-response/README.md, section « Niveau de
    contrat : API pour les thèmes ».

    Ce fichier est chargé par SlaFirstResponsePlugin::bootstrap() : il ne
    fait que déclarer une classe et une fonction (aucun effet de bord).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
if (!defined('INCLUDE_DIR')) die('Access denied');

class SlaContractLevel {

    const PRIORITY = 'priority';
    const STANDARD = 'standard';

    // Le niveau a été déterminé par la règle d'organisation (organisation
    // Priority configurée et existante, propriétaire connu).
    const SOURCE_ORG = 'organisation';
    // Valeur par défaut (standard) : pas de client connecté, invité,
    // organisation Priority non configurée ou supprimée, sujet inconnu.
    const SOURCE_DEFAULT = 'default';

    const DEFAULT_LABEL_PRIORITY = 'Priority';
    const DEFAULT_LABEL_STANDARD = 'Standard';

    private static $orgId = 0;
    private static $labels = array(
        self::PRIORITY => self::DEFAULT_LABEL_PRIORITY,
        self::STANDARD => self::DEFAULT_LABEL_STANDARD,
    );
    // Organisation Priority résolue (false = pas encore cherchée).
    private static $org = false;
    // Mémo par utilisateur : user_id => array(org_id, org_name)
    private static $users = array();

    /**
     * Appelé au bootstrap du plugin avec la configuration de l'instance.
     * $orgId : identifiant de l'organisation Priority (0 = non configurée).
     */
    static function configure($orgId, $labelPriority=null, $labelStandard=null) {
        self::$orgId = max(0, (int) $orgId);
        self::$labels = array(
            self::PRIORITY => self::cleanLabel($labelPriority, self::DEFAULT_LABEL_PRIORITY),
            self::STANDARD => self::cleanLabel($labelStandard, self::DEFAULT_LABEL_STANDARD),
        );
        self::reset();
    }

    /** Vide le cache de la requête (tests, ou après un changement d'organisation). */
    static function reset() {
        self::$org = false;
        self::$users = array();
    }

    static function getConfiguredOrgId() {
        return self::$orgId;
    }

    private static function cleanLabel($label, $default) {
        $label = trim((string) $label);
        return $label !== '' ? $label : $default;
    }

    /** Libellé affichable d'un niveau, dans la langue de l'utilisateur. */
    static function getLabel($level) {
        $label = self::$labels[$level == self::PRIORITY ? self::PRIORITY : self::STANDARD];
        return function_exists('__') ? __($label) : $label;
    }

    /**
     * Point d'entrée de coderise_contract_level(). Ne lève jamais
     * d'exception : en cas de problème, niveau standard, source « default ».
     */
    static function forSubject($subject=null) {
        try {
            $uid = self::userIdOf($subject);
            if (!$uid)
                return self::result(self::STANDARD, null, null, self::SOURCE_DEFAULT);

            list($orgId, $orgName) = self::userOrg($uid);
            $priority = self::priorityOrg();
            if (!$priority)
                // Organisation Priority non configurée ou supprimée
                return self::result(self::STANDARD, $orgId, $orgName, self::SOURCE_DEFAULT);

            return self::result(
                ($orgId && $orgId == $priority->getId()) ? self::PRIORITY : self::STANDARD,
                $orgId, $orgName, self::SOURCE_ORG);
        }
        catch (Throwable $t) {
            return self::result(self::STANDARD, null, null, self::SOURCE_DEFAULT);
        }
    }

    private static function result($level, $orgId, $orgName, $source) {
        return array(
            'level' => $level,
            'label' => self::getLabel($level),
            'org_id' => $orgId ? (int) $orgId : null,
            'org_name' => ($orgId && $orgName !== null) ? (string) $orgName : null,
            'source' => $source,
        );
    }

    /**
     * Identifiant de l'utilisateur osTicket dont on cherche le niveau, ou
     * 0 si aucun (non connecté, invité, sujet non pris en charge).
     */
    private static function userIdOf($subject) {
        if ($subject === null) {
            // Client connecté du portail (client.inc.php : $thisclient,
            // déjà validé et remis à null si la session est invalide).
            global $thisclient;
            $subject = $thisclient;
            if (!$subject)
                return 0;
        }

        if ($subject instanceof User)
            return (int) $subject->getId();

        if ($subject instanceof Ticket)
            // Le propriétaire du ticket, jamais un collaborateur.
            return (int) $subject->getOwnerId();

        if ($subject instanceof EndUser) {
            // ClientSession hérite d'EndUser. Un invité (accès par numéro
            // de ticket et e-mail, ou lien d'un collaborateur) n'a pas de
            // compte : niveau inconnu, donc standard.
            if ($subject->isGuest())
                return 0;
            return (int) $subject->getId();
        }

        return 0;
    }

    /** array(org_id|null, org_name|null) de l'utilisateur, mémorisé. */
    private static function userOrg($uid) {
        if (!isset(self::$users[$uid])) {
            $orgId = null;
            $orgName = null;
            // Recherche par clé primaire : cache d'objets de l'ORM.
            if (($user = User::lookup($uid)) && ($orgId = (int) $user->getOrgId())) {
                $org = $user->getOrganization();
                if ($org && $org->getId() == $orgId)
                    $orgName = $org->getName();
                else
                    // Organisation supprimée sans mise à jour de l'utilisateur
                    $orgId = null;
            }
            self::$users[$uid] = array($orgId ?: null, $orgName);
        }
        return self::$users[$uid];
    }

    /** L'organisation Priority configurée si elle existe, sinon null. */
    static function priorityOrg() {
        if (self::$org === false) {
            self::$org = null;
            if (self::$orgId)
                self::$org = Organization::lookup(self::$orgId) ?: null;
        }
        return self::$org;
    }
}

if (!function_exists('coderise_contract_level')) {
    /**
     * Niveau de contrat (« priority » / « standard ») d'un client.
     * Voir la classe SlaContractLevel ci-dessus et le README du plugin.
     */
    function coderise_contract_level($subject=null) {
        return SlaContractLevel::forSubject($subject);
    }
}
