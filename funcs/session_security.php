<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('redirigirSeguridad')) {
    function redirigirSeguridad($url) {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit();
        } else {
            echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
            exit();
        }
    }
}

// 1. Verificar si el usuario está autenticado
if (!isset($_SESSION["currentUser"])) {
    $is_subfolder = (basename(dirname($_SERVER['PHP_SELF'])) === 'funcs');
    $login_path = $is_subfolder ? '../login.php' : 'login.php';
    redirigirSeguridad($login_path);
}

// 2. Verificar si XAMPP/Apache se ha reiniciado
$pid_file = 'C:/xampp/apache/logs/httpd.pid';
if (!file_exists($pid_file) && isset($_SERVER['DOCUMENT_ROOT'])) {
    $pid_file = dirname($_SERVER['DOCUMENT_ROOT']) . '/apache/logs/httpd.pid';
}

if (file_exists($pid_file) && is_readable($pid_file)) {
    $current_pid = trim(file_get_contents($pid_file));
    $current_mtime = filemtime($pid_file);
    
    if (!isset($_SESSION['server_pid']) || !isset($_SESSION['server_mtime'])) {
        $_SESSION['server_pid'] = $current_pid;
        $_SESSION['server_mtime'] = $current_mtime;
    } else {
        if ($_SESSION['server_pid'] !== $current_pid || $_SESSION['server_mtime'] !== $current_mtime) {
            // Apache ha sido reiniciado, forzar destrucción de sesión
            $is_subfolder = (basename(dirname($_SERVER['PHP_SELF'])) === 'funcs');
            $logout_path = $is_subfolder ? '../logout.php' : 'logout.php';
            redirigirSeguridad($logout_path);
        }
    }
}

// 3. Verificar inactividad (15 minutos = 900 segundos)
$inactive_limit = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive_limit)) {
    // Límite de inactividad superado, forzar cierre de sesión
    $is_subfolder = (basename(dirname($_SERVER['PHP_SELF'])) === 'funcs');
    $logout_path = $is_subfolder ? '../logout.php' : 'logout.php';
    redirigirSeguridad($logout_path);
}

// Actualizar marca de tiempo de última actividad
$_SESSION['last_activity'] = time();
?>
