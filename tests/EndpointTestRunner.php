<?php
/**
 * Subprocess helper to execute endpoints in an isolated PHP process and print raw JSON
 */
declare(strict_types=1);

$endpoint = $argv[1] ?? '';
$mode = $argv[2] ?? 'GET';
$itemId = $argv[3] ?? '1';

if ($endpoint === 'get') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['item_id'] = $itemId;
    require __DIR__ . '/../api/matches/get-for-item.php';
} elseif ($endpoint === 'recalculate') {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    init_session();
    $_SESSION['user_id'] = 2; // Alex Rivera, owner of item 1
    $_SESSION['role'] = 'student';
    $_SESSION['csrf_token'] = 'cli-test-token';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['item_id'] = $itemId;
    $_POST['csrf_token'] = 'cli-test-token';
    require __DIR__ . '/../api/matches/recalculate.php';
}
