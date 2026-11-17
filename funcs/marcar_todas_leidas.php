<?php
session_start();
require_once("../db.php");
header('Content-Type: application/json');

if (isset($_SESSION["currentID"])) {
    $userId = intval($_SESSION["currentID"]);
    $query = "UPDATE notificaciones SET leido = 1 WHERE usuario_id = $userId";
    if (mysqli_query($conn, $query)) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => mysqli_error($conn)]);
    }
} else {
    echo json_encode(["success" => false, "error" => "No autorizado"]);
}
?>
