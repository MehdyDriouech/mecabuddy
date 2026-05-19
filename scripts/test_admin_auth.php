<?php
/**
 * Tests CLI rapides auth admin (à lancer : php scripts/test_admin_auth.php)
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_sqlite.php';
require_once __DIR__ . '/../includes/demo_auth.php';

$failures = 0;

function assert_true(bool $cond, string $msg): void
{
    global $failures;
    if ($cond) {
        echo "OK  $msg\n";
    } else {
        echo "FAIL $msg\n";
        $failures++;
    }
}

$pdo = getSQLite();
migrateSQLiteDemoUserRole($pdo);
demo_auth_seed_admin_user($pdo);

$row = $pdo->prepare('SELECT username, role, password_hash FROM demo_users WHERE username = ?');
$row->execute([DEMO_ADMIN_SEED_USERNAME]);
$admin = $row->fetch(PDO::FETCH_ASSOC);
assert_true($admin !== false, 'moonshine existe');
assert_true($admin !== false && $admin['role'] === DEMO_ROLE_ADMIN, 'moonshine role admin');
assert_true($admin !== false && password_verify('Azerty12345!', (string) $admin['password_hash']), 'moonshine password verify');

$demo = $pdo->prepare('SELECT role FROM demo_users WHERE username = ?');
$demo->execute(['demo-demo']);
$demoRow = $demo->fetch(PDO::FETCH_ASSOC);
assert_true($demoRow !== false && ($demoRow['role'] ?? '') === DEMO_ROLE_USER, 'demo-demo role user');

echo $failures === 0 ? "\nTous les tests passés.\n" : "\n$failures échec(s).\n";
exit($failures > 0 ? 1 : 0);
