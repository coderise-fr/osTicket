<?php
/*********************************************************************
    class.stripesync.directory.php

    The only place where the plugin touches osTicket's own classes
    (User, UserAccount, Organization). It deliberately goes through the
    same calls as the agent panel (scp/users.php: "Register" and
    "Set organization" actions), so behavior, signals and audit trail
    stay identical to a manual action by an agent.

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once dirname(__FILE__) . '/class.stripesync.service.php';

class StripeSyncOsTicketDirectory implements StripeSyncDirectory {

    private $settings;
    private $org;

    function __construct($settings) {
        $this->settings = $settings;
    }

    // --- users ---------------------------------------------------------------

    function ensureUser($email, $name) {
        $email = trim((string) $email);
        if (!Validator::is_email($email))
            throw new StripeSyncPermanentError('Invalid e-mail address');

        $user = User::lookupByEmail($email);
        if (!$user) {
            // User::fromVars() may also attach an organization mapped to
            // the e-mail domain: remembered later as the "previous" org.
            $user = User::fromVars(array(
                'email' => $email,
                'name' => ($name !== null && trim($name) !== '') ? $name : null,
            ));
        }
        if (!$user)
            throw new StripeSyncRetryableError('Unable to create the user');

        return (int) $user->getId();
    }

    function userExists($userId) {
        return (bool) User::lookup($userId);
    }

    // --- portal access ---------------------------------------------------------

    function ensureAccess($userId, $lastInvite, $minDelay, $now) {
        global $cfg;

        if (!($user = User::lookup($userId)))
            throw new StripeSyncRetryableError('User not found');

        $acct = $user->getAccount();
        if ($acct && $acct->isLocked())
            return 'locked';                    // blocked by an agent: respect it
        if ($acct && $acct->isConfirmed())
            return 'active';
        if ($acct && $lastInvite && ($now - $lastInvite) < $minDelay)
            return 'throttled';

        // The confirmation e-mail is sent from the default system e-mail.
        if (!$cfg || !$cfg->getDefaultEmail())
            throw new StripeSyncRetryableError('No default system e-mail configured');

        if ($acct) {
            // Account exists but was never confirmed (e.g. expired link):
            // new link, as the "Send confirmation" agent action does.
            if (!$acct->sendConfirmEmail())
                throw new StripeSyncRetryableError('Unable to send the confirmation e-mail');
        }
        else {
            // Creates the account without password and sends the
            // confirmation e-mail (registration-client template): the
            // customer chooses his password from the link.
            $errors = array();
            if (!UserAccount::register($user, array('sendemail' => true), $errors))
                throw new StripeSyncPermanentError('Unable to create the portal account');
        }
        return 'invited';
    }

    // --- Priority organization ---------------------------------------------------

    // Flags that would let Priority customers see each other's tickets.
    private static function sharingFlags() {
        return Organization::COLLAB_ALL_MEMBERS
            | Organization::COLLAB_PRIMARY_CONTACT
            | Organization::SHARE_PRIMARY_CONTACT
            | Organization::SHARE_EVERYBODY;
    }

    /**
     * The Priority organization, created on first use. Refuses (the event
     * is redelivered later) to use an organization configured in a way
     * that would mix the tickets or contacts of unrelated customers.
     */
    private function priorityOrg() {
        if ($this->org)
            return $this->org;

        $name = $this->settings->getOrgName();
        $org = Organization::lookup(array('name' => $name));
        if (!$org) {
            $org = Organization::fromVars(array('name' => $name));
            if (!$org)
                throw new StripeSyncRetryableError('Unable to create the Priority organization');
            // Organization::create() enables "primary contacts see the
            // organization's tickets": not wanted for unrelated customers.
            $org->set('status', 0);
            $org->save();
        }

        if ($org->get('status') & self::sharingFlags())
            throw new StripeSyncRetryableError(sprintf(
                'Organization #%d: disable ticket sharing and automatic collaborators'
                .' (customers of the Priority organization must not see each other)',
                $org->getId()));
        if (trim((string) $org->get('domain')) !== '')
            throw new StripeSyncRetryableError(sprintf(
                'Organization #%d: remove its e-mail domain mapping'
                .' (it would auto-enrol every user of that domain)', $org->getId()));

        return $this->org = $org;
    }

    function attachPriority($userId) {
        $org = $this->priorityOrg();
        if (!($user = User::lookup($userId)))
            throw new StripeSyncRetryableError('User not found');

        if ($user->getOrgId() == $org->getId())
            return array(false, null);          // already there

        $previous = $user->getOrgId() ? (int) $user->getOrgId() : null;
        $user->setOrganization($org);
        $type = array('type' => 'edited', 'key' => 'user-org');
        Signal::send('object.edited', $user, $type);
        return array(true, $previous);
    }

    function detachPriority($userId, $restoreOrgId) {
        // No isolation check here: leaving the organization is always safe.
        $org = Organization::lookup(array('name' => $this->settings->getOrgName()));
        if (!$org || !($user = User::lookup($userId)))
            return false;
        // Moved elsewhere by an agent in the meantime: leave it alone.
        if ($user->getOrgId() != $org->getId())
            return false;

        $org->removeUser($user);                // also drops "primary contact"
        if ($restoreOrgId && ($previous = Organization::lookup($restoreOrgId)))
            $user->setOrganization($previous);

        $type = array('type' => 'edited', 'key' => 'user-org');
        Signal::send('object.edited', $user, $type);
        return true;
    }
}
