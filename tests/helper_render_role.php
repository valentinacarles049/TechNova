<?php
$usuario = $argv[1] ?? 'valeen';
$rol = $argv[2] ?? 'monitoreo';
$rolId = $argv[3] ?? '4';

session_start();
$_SESSION['currentUser'] = $usuario;
$_SESSION['currentTeam'] = $rol;
$_SESSION['userRole'] = $rolId;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/ticketManegement.php';

ob_start();
include(__DIR__ . '/../ticketManegement.php');
echo ob_get_clean();
