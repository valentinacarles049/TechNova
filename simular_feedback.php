<?php
header('Content-Type: application/json');
session_start();

// 1. Verificar autenticación del usuario
if (!isset($_SESSION["currentUser"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "No autorizado"]);
    exit;
}

// 2. Conectar a la base de datos
require_once("db.php");
require_once("funcs/generalFuncs.php");
require_once("funcs/feedbackFunc.php");
if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error de conexión a la base de datos"]);
    exit;
}

// 3. Obtener el cuerpo de la petición JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos de entrada inválidos"]);
    exit;
}

// 4. Sanitizar variables
$id = isset($data['id']) ? intval($data['id']) : 0;
$tipo = isset($data['tipo']) ? trim($data['tipo']) : 'Sugerencia';
$sentimiento = isset($data['sentimiento']) ? trim($data['sentimiento']) : 'Positivo';
$tema = isset($data['tema']) ? trim($data['tema']) : 'Atención Soporte';
$comentario = isset($data['comentario']) ? trim($data['comentario']) : '';
$calificacion = isset($data['calificacion']) ? intval($data['calificacion']) : (strtolower($sentimiento) === 'positivo' ? 5 : 2);

if ($id <= 0 || empty($comentario)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Faltan campos obligatorios para la simulación"]);
    exit;
}

try {
    // 5. HU-44: Seguir el ciclo de vida oficial
    // Si la evaluación está en PendienteEnvio, emitir envío primero
    $qCheck = mysqli_query($conn, "SELECT estado FROM feedbacks WHERE id = $id");
    $rowCheck = mysqli_fetch_assoc($qCheck);
    if ($rowCheck && $rowCheck['estado'] === 'PendienteEnvio') {
        enviarEncuestaUsuario($conn, $id);
    }

    // Registrar la respuesta del cliente (pasa de Enviada a Completada)
    $resResp = registrarDetalleOpinion($conn, $id, $calificacion, $comentario, $tipo, $tema, $sentimiento);

    if (!empty($resResp['success'])) {
        echo json_encode(["success" => true, "estado" => "Completada"]);
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => $resResp['error'] ?? "No se pudo procesar la respuesta"]);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
