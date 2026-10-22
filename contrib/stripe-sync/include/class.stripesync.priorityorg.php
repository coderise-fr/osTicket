<?php
/*********************************************************************
    class.stripesync.priorityorg.php

    Which osTicket organization is "the Priority organization".

    Since version 0.2 the organization is remembered by its IDENTIFIER
    (ost_config, namespace `stripesync`, key `priority_org_id`), not
    looked up by name at every event: an organization renamed in the
    admin panel is still found, and other modules (the SLA plugin, the
    portal themes through coderise_contract_level()) can rely on the
    same identifier.

    Resolution order (resolve()):
      1. the remembered id, if that organization still exists and the
         configured name did not change since it was remembered;
      2. otherwise the configured name (STRIPE_SYNC_PRIORITY_ORG, field
         `org_name`, default "Priority"): first use, installations of
         version 0.1 (migration) or a remembered organization that was
         deleted;
      3. otherwise (and only when $create) a new organization with the
         configured name.
    The id found in 2. or 3. is remembered. A remembered organization
    that disappeared is reported as a WARNING in the system log (ids
    only, no customer data).

    This class knows nothing about osTicket or SQL: the organizations
    and the memory are injected (unit tests: tests/run.php).

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once dirname(__FILE__) . '/class.stripesync.service.php';

/** Organizations, as seen by the resolver (ids only). */
interface StripeSyncOrgBackend {
    /** Whether the organization #$id exists. */
    function orgExists($id);
    /** Id of the organization with this exact name, or null. */
    function findOrgByName($name);
    /**
     * Create the organization (no ticket sharing, no automatic
     * collaborators, no domain) and return its id. Throws
     * StripeSyncRetryableError on failure.
     */
    function createOrg($name);
}

/** Where the id of the Priority organization is remembered. */
interface StripeSyncOrgMemory {
    /** array(int id|null, string configured name|null) */
    function getPriorityOrg();
    function putPriorityOrg($id, $configuredName);
    /** Lock serializing the slow path (lookup by name + creation). */
    function lockPriorityOrg();
    function unlockPriorityOrg();
}

class StripeSyncPriorityOrgResolver {

    private $orgs;
    private $memory;
    private $settings;
    private $log;

    function __construct(StripeSyncOrgBackend $orgs, StripeSyncOrgMemory $memory,
            $settings, StripeSyncLogger $log) {
        $this->orgs = $orgs;
        $this->memory = $memory;
        $this->settings = $settings;
        $this->log = $log;
    }

    /**
     * The remembered organization: array(usable id|null, remembered id|null,
     * true if the configured name changed since it was remembered).
     */
    private function remembered($name) {
        list($id, $cfgName) = $this->memory->getPriorityOrg();
        if (!$id)
            return array(null, null, false);
        // The setting was changed on purpose: follow the new name.
        if ($cfgName !== null && $cfgName !== $name)
            return array(null, $id, true);
        return array($this->orgs->orgExists($id) ? $id : null, $id, false);
    }

    /**
     * Id of the Priority organization. When $create is false (leaving the
     * organization), nothing is created and null is returned if there is
     * no such organization.
     */
    function resolve($create=true) {
        $name = $this->settings->getOrgName();
        list($id) = $this->remembered($name);
        if ($id)
            return $id;

        // Slow path, serialized so that two concurrent events cannot each
        // create (and remember) their own Priority organization.
        if (!$this->memory->lockPriorityOrg())
            throw new StripeSyncRetryableError('Priority organization is locked');
        try {
            list($id, $known, $changed) = $this->remembered($name);    // done meanwhile?
            if ($id)
                return $id;

            $how = 'found by name';
            $id = $this->orgs->findOrgByName($name);
            if (!$id) {
                if (!$create)
                    return null;
                $id = $this->orgs->createOrg($name);
                $how = 'created';
            }
            $id = (int) $id;
            $this->memory->putPriorityOrg($id, $name);

            if ($known && $changed)
                // Not an incident: the administrator changed the setting.
                $this->log->info('Stripe sync: Priority organization changed',
                    sprintf('Configured name changed: organization #%d (%s) replaces #%d',
                        $id, $how, $known));
            elseif ($known && $known != $id)
                $this->log->warning('Stripe sync: Priority organization replaced',
                    sprintf('Organization #%d (remembered) no longer exists: organization #%d %s'
                        .' and remembered instead. Users that were in #%d are not moved;'
                        .' check the ticket filter and the SLA plugin (contract level).',
                        $known, $id, $how, $known));
            else
                $this->log->info('Stripe sync: Priority organization remembered',
                    sprintf('Organization #%d (%s)', $id, $how));
            return $id;
        }
        finally {
            $this->memory->unlockPriorityOrg();
        }
    }
}
