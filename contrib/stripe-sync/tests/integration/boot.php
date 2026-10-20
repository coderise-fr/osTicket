<?php
// Test harness only (not part of the deliverable): boots osTicket in CLI.
chdir('/srv/ost');
define('DISABLE_SESSION', true);
$_SERVER['SCRIPT_NAME'] = '/boot.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require '/srv/ost/main.inc.php';