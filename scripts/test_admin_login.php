<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/demo_auth.php';

session_name(SESSION_NAME);
session_start();

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK  ' : 'FAIL ') . $m . "\n";
    if (!$c) {
        $failures++;
    }
}

logoutDemoUser();
ok(!loginDemoUser('demo-demo', 'demo-demo') === false || !isDemoAdmin(), 'demo-demo login sans admin');

logoutDemoUser();
ok(loginDemoUser('moonshine', 'Azerty12345!'), 'moonshine login');
ok(isDemoAdmin(), 'moonshine is admin');

logoutDemoUser();
ok(loginDemoUser('demo', 'demo'), 'demo legacy login');
ok(!isDemoAdmin(), 'demo pas admin');

exit($failures > 0 ? 1 : 0);
