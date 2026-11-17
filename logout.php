<?php
session_start();
session_unset();
session_destroy();
if (!headers_sent()) {
    header("Location: login.php");
    exit;
} else {
    echo '<script>window.location.href="login.php";</script>';
    exit;
}
?>
