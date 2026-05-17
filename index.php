<?php
/**
 * Point d'entrée racine — redirection vers l'application (dossier public/).
 */
$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($base === '/' || $base === '.') {
    $base = '';
}
header('Location: ' . $base . '/public/index.php', true, 302);
exit;
