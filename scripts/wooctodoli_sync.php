<?php
// CLI entry to run wooctodoli sync
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI\n";
    exit(1);
}

require_once __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/custom/wooctodoli/core/class/woocsync.class.php';

$sync = new WoocSync($db);
$res = $sync->doScheduledJob();

if ($res > 0) echo "Sync completed\n";
else echo "Sync failed\n";
