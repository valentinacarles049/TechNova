<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (isset($_SESSION["currentUser"])) {
    $_SESSION['last_activity'] = time();
    echo json_encode(["status" => "success", "last_activity" => $_SESSION['last_activity']]);
} else {
    echo json_encode(["status" => "expired"]);
}
exit();
?>
