<?php
/**
 * API de Monitoreo de SLAs y Redistribución Rápida de Tickets
 * Endpoints protegidos con RBAC para consulta de métricas en tiempo real y mutación de asignación.
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// 1. Soporte para Token de Autorización o Sesión Activa
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
if (!empty($authHeader) && empty($_SESSION['currentUser'])) {
    // Si se envía token Bearer, validar token simple o sesión simulada
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
        if ($token === 'monitoreo_token' || $token === 'admin_token') {
            $_SESSION['currentUser'] = 'api_monitor';
            $_SESSION['currentTeam'] = ($token === 'admin_token') ? 'administrador' : 'monitoreo';
        }
    }
}

// 2. Control de Acceso y Barrera de Seguridad (RBAC)
if (empty($_SESSION["currentUser"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "error" => "No autenticado. Inicie sesión para acceder al módulo de Monitoreo SLA."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once(__DIR__ . "/db.php");
require_once(__DIR__ . "/funcs/generalFuncs.php");
require_once(__DIR__ . "/funcs/ticketLifecycle.php");
require_once(__DIR__ . "/funcs/ticketFunc.php");

// Verificación estricta de rol Monitoreo / SLA / Administrador
if (!verificarPermisoMonitoreoOSla()) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "error" => "Acceso denegado: El módulo de SLA y redistribución rápida es exclusivo para usuarios con rol de Monitoreo o Administrador."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Determinar Acción Solicitada
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Leer payload si es JSON
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
if (!is_array($jsonData)) {
    $jsonData = !empty($_POST) ? $_POST : [];
}
if (empty($action) && !empty($jsonData['action'])) {
    $action = $jsonData['action'];
}

// ============================================================================
// ENDPOINT 1: Consulta de Contadores / Resumen Cuantitativo SLA en Tiempo Real
// ============================================================================
if ($action === 'counters' || $action === 'resumen' || ($method === 'GET' && empty($action))) {
    $metricas = obtenerMetricasSla($conn);
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "data" => $metricas,
        "counters" => $metricas,
        "timestamp" => date("Y-m-d H:i:s")
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// ENDPOINT 2: Mutación de Redistribución Rápida reasignarAgente(idAgente)
// ============================================================================
if ($action === 'reasignar' || $action === 'reasignarAgente' || $action === 'reassign') {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Método no permitido. Use POST para reasignación."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ticketId = intval($jsonData['ticket_id'] ?? $jsonData['id'] ?? $_POST['ticket_id'] ?? 0);
    $idAgente = trim($jsonData['idAgente'] ?? $jsonData['asignado'] ?? $_POST['idAgente'] ?? '');
    $motivo = trim($jsonData['motivo'] ?? $_POST['motivo'] ?? 'Redistribución rápida preventiva SLA');

    if ($ticketId <= 0 || empty($idAgente)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Parámetros incompletos: ticket_id y un idAgente válido son obligatorios."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $ticketObj = Ticket::find($ticketId, $conn);
        if (!$ticketObj) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "El ticket #{$ticketId} no existe."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ticketObj->reasignarAgente($idAgente, $motivo);
        $metricasActualizadas = obtenerMetricasSla($conn);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Ticket #TKT-" . str_pad($ticketId, 3, "0", STR_PAD_LEFT) . " reasignado con éxito a " . htmlspecialchars($idAgente) . ".",
            "ticket_id" => $ticketId,
            "nuevo_agente" => $idAgente,
            "nuevo_estado" => $ticketObj->estado,
            "counters" => $metricasActualizadas
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (TransicionTicketException $tte) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => $tte->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Acción desconocida
http_response_code(400);
echo json_encode(["success" => false, "error" => "Acción desconocida solicitada."], JSON_UNESCAPED_UNICODE);
exit;
