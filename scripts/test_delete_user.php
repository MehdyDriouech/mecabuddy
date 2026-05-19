<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_sqlite.php';
require_once __DIR__ . '/../includes/demo_auth.php';

$pdo = getSQLite();
$uname = 'zap_test_' . time();
$hash = password_hash('test1234', PASSWORD_DEFAULT);
$pdo->prepare(
    'INSERT INTO demo_users (username, password_hash, role, tutorial_daily_quota, buddy_daily_quota, is_active)
     VALUES (?, ?, ?, 5, 5, 1)'
)->execute([$uname, $hash, 'user']);
$id = (int) $pdo->lastInsertId();

$pdo->prepare('DELETE FROM demo_usage_daily WHERE user_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM demo_users WHERE id = ?')->execute([$id]);

$chk = $pdo->prepare('SELECT id FROM demo_users WHERE id = ?');
$chk->execute([$id]);
$gone = $chk->fetchColumn() === false;

// Simule un second getSQLite (sans recréer le compte)
getSQLite();
$chk->execute([$id]);
$stillGone = $chk->fetchColumn() === false;

echo $gone && $stillGone
    ? "OK delete persiste après getSQLite()\n"
    : "FAIL delete non persistant\n";
exit($gone && $stillGone ? 0 : 1);
