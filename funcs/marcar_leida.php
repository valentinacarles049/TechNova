<?php
session_start();
require_once("../db.php");
header('Content-Type: application/json');

if (isset($_SESSION["currentID"]) && isset($_GET["id"])) {
    $userId = intval($_SESSION["currentID"]);
    $id = intval($_GET["id"]);
    $query = "UPDATE notificaciones SET leido = 1 WHERE id = $id AND usuario_id = $userId";
    if (mysqli_query($conn, $query)) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => mysqli_error($conn)]);
    }
} else {
    echo json_encode(["success" => false, "error" => "No autorizado o parámetros faltantes"]);
}
?>
