<?php
$usuario = $argv[1] ?? '';
$rol = $argv[2] ?? '';
$action = $argv[3] ?? 'counters';
$postJson = $argv[4] ?? '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($usuario) || $usuario === '__NO_AUTH__') {
    $_SESSION = [];
    @session_destroy();
} else {
    $_SESSION['currentUser'] = $usuario;
    $_SESSION['currentTeam'] = $rol;
}

$_SERVER['REQUEST_METHOD'] = (!empty($postJson) && $postJson !== "''") ? 'POST' : 'GET';
$_GET['action'] = $action;

if (!empty($postJson) && $postJson !== "''") {
    $decoded = json_decode($postJson, true);
    if (!is_array($decoded)) {
        $b64 = base64_decode($postJson, true);
        if ($b64 !== false) {
            $decoded = json_decode($b64, true);
        }
    }
    if (is_array($decoded)) {
        $_POST = $decoded;
    }
}

include(__DIR__ . '/../api_sla.php');
