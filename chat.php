<?php
/**
 * TechNova - Endpoint del Asistente Virtual (Chatbot Techy)
 * HU-40: Conexión segura con el asistente virtual
 * HU-41: Memoria de chat y atención según el rol
 * HU-45: Techy: Consulta directa de datos e información completa
 * 
 * Sub-tarea 1: Enviar historial de la conversación (preguntas cortas de seguimiento)
 * Sub-tarea 2: Inyectar contexto dinámico (rol del usuario y pantalla/módulo actual)
 * Sub-tarea 3: Persistencia de estado en el Frontend (sessionStorage)
 * Sub-tarea 4: Configurar el System Prompt (Personalidad de Techy)
 * HU-45 Criterio 1: Consulta directa de base de datos para tickets, incidencias y feedbacks
 * HU-45 Criterio 2: Prohibición estricta de pedir abrir ventanas o tickets si el registro existe
 * HU-45 Criterio 4: Estructuración en markdown limpio y respuestas completas sin truncar
 */

// Iniciar sesión para validar usuario y mantener historial
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar encabezados de respuesta
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Manejo de preflight OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Asegurar que el método sea estrictamente POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "ok" => false,
        "error" => "method_not_allowed",
        "respuesta" => "Método no permitido. Solo se aceptan peticiones POST."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ==============================================================================
// VALIDACIÓN DE SESIÓN (HU-40 Criterio 4)
// ==============================================================================
if (!isset($_SESSION["currentUser"]) || empty(trim((string)$_SESSION["currentUser"]))) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "error" => "unauthorized",
        "respuesta" => "Tu sesión no es válida o ha expirado. Por favor, inicia sesión en el sistema para utilizar el asistente virtual."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Leer payload JSON enviado por el cliente
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "error" => "invalid_payload",
        "respuesta" => "Formato de mensaje inválido."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ==============================================================================
// ACCIÓN: REINICIAR CONVERSACIÓN
// ==============================================================================
if (isset($input["accion"]) && $input["accion"] === "reset") {
    $currentName = $_SESSION["currentName"] ?? $_SESSION["currentUser"] ?? "Usuario";
    $_SESSION['chatbot_history'] = [
        [
            'role' => 'bot',
            'message' => '¡Hola, ' . htmlspecialchars($currentName) . '! He reiniciado nuestra conversación. ¿En qué puedo orientarte hoy sobre TechNova?'
        ]
    ];
    echo json_encode([
        "ok" => true,
        "respuesta" => "Conversación reiniciada con éxito."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ==============================================================================
// SANITIZACIÓN Y VALIDACIÓN DE LA PREGUNTA (HU-40 Criterio 3)
// ==============================================================================
if (!isset($input["pregunta"])) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "error" => "missing_question",
        "respuesta" => "Por favor, escribe una pregunta para que pueda ayudarte."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$textoCrudo = (string)$input["pregunta"];
$textoCrudo = str_replace(chr(0), '', $textoCrudo);
$preguntaUsuario = trim($textoCrudo);
$preguntaUsuario = preg_replace('/[ \t]+/', ' ', $preguntaUsuario);

if (!mb_check_encoding($preguntaUsuario, 'UTF-8')) {
    $preguntaUsuario = mb_convert_encoding($preguntaUsuario, 'UTF-8', 'UTF-8');
}

if (mb_strlen($preguntaUsuario, 'UTF-8') < 1) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "error" => "empty_question",
        "respuesta" => "Por favor, escribe una pregunta válida."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (mb_strlen($preguntaUsuario, 'UTF-8') > 1000) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "error" => "max_length_exceeded",
        "respuesta" => "Tu consulta supera el límite de 1000 caracteres. Por favor, formula una pregunta más concreta."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ==============================================================================
// CARGA DE CREDENCIALES DESDE .ENV (HU-40 Subtarea 1)
// ==============================================================================
require_once __DIR__ . '/funcs/env.php';
cargarEnv(__DIR__);

$apiKey = env('GEMINI_API_KEY');
$modelo = env('GEMINI_MODEL', 'gemini-2.5-flash');

if (empty($apiKey) || $apiKey === 'tu_api_key_aqui') {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "missing_api_key",
        "respuesta" => "La configuración de conexión con la IA no está disponible en el servidor. Contacta al administrador."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ==============================================================================
// HU-41 SUB-TAREA 2 & HU-45: INYECTAR CONTEXTO DINÁMICO Y CONSULTA DIRECTA DE DB
// ==============================================================================
$usuario = $_SESSION["currentUser"] ?? "Usuario";
$nombreCompleto = $_SESSION["currentName"] ?? $usuario;
$userRol = ucfirst(strtolower($_SESSION["currentTeam"] ?? "soporte"));

$inputContexto = (isset($input["contexto"]) && is_array($input["contexto"])) ? $input["contexto"] : [];
$moduloId = htmlspecialchars($inputContexto["modulo_id"] ?? "general");
$moduloNombre = htmlspecialchars($inputContexto["modulo_nombre"] ?? "Sistema TechNova");
$pantallaActual = "{$moduloNombre} ({$moduloId})";
$ticketData = !empty($inputContexto["ticket_data"]) ? trim($inputContexto["ticket_data"]) : "Ningún registro o modal abierto en pantalla actualmente.";

// Telemetría en tiempo real y base de datos (HU-45 Criterio 1)
$metricasContexto = "";
$ticketsContexto = "";
$ticketConsultadoContexto = "";
$incidenciaConsultadaContexto = "";
$incidenciasContexto = "";
$feedbackConsultadoContexto = "";
$feedbacksContexto = "";
$muestreoContexto = "";

if (file_exists(__DIR__ . '/db.php')) {
    include_once __DIR__ . '/db.php';
    if (isset($conn) && $conn) {
        // Métricas numéricas para telemetría
        $qTickets = @mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE estado IN ('abierto', 'en progreso')");
        $ticketsActivos = ($qTickets && $r = mysqli_fetch_assoc($qTickets)) ? intval($r['c']) : 0;

        $qBugs = @mysqli_query($conn, "SELECT COUNT(*) as c FROM incidencias WHERE estado != 'resuelto' AND estado != 'cerrado'");
        if (!$qBugs) {
            $qBugs = @mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE es_incidencia = 1 AND estado != 'cerrado'");
        }
        $bugsActivos = ($qBugs && $r = mysqli_fetch_assoc($qBugs)) ? intval($r['c']) : 0;

        $qSla = @mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE prioridad = 'critica' AND estado != 'cerrado'");
        $slaCriticos = ($qSla && $r = mysqli_fetch_assoc($qSla)) ? intval($r['c']) : 0;

        $metricasContexto = "
[Telemetría en Vivo del Sistema]
- Tickets activos en cola (Soporte): {$ticketsActivos}
- Incidencias y bugs activos (Desarrollo): {$bugsActivos}
- Tickets críticos / SLA en riesgo (Monitoreo): {$slaCriticos}";

        // ----------------------------------------------------------------------
        // HU-45: 1. Detección y consulta directa sobre un TICKET específico
        // Ejemplos: "¿Quién tiene el ticket 38?", "Datos de TKT-036", "#38", "el ticket 38", "TKT38"
        // ----------------------------------------------------------------------
        $idConsultado = null;
        if (preg_match('/\b(?:tkt|ticket|caso)\s*[-#]?\s*0*(\d+)\b/i', $preguntaUsuario, $matchTicket)) {
            $idConsultado = intval($matchTicket[1]);
        } elseif (preg_match('/\b#\s*0*(\d+)\b/i', $preguntaUsuario, $matchTicket)) {
            $idConsultado = intval($matchTicket[1]);
        } elseif (preg_match('/\b(?:el|del|sobre el|ver|datos del|quien tiene el|quién tiene el)\s+(?:ticket\s+)?0*(\d{1,4})\b/i', $preguntaUsuario, $matchTicket)) {
            $idConsultado = intval($matchTicket[1]);
        } elseif (preg_match('/\bTKT-?0*(\d+)\b/i', $ticketData, $matchTktContext)) {
            // Si el usuario pregunta de seguimiento ("¿Quién lo tiene?", "estado", etc.) y hay un ticket en el modal/pantalla
            if (preg_match('/\b(?:qui[eé]n|asignado|estado|prioridad|datos|qu[eé] pasa|detalle|resoluci[oó]n|juego|driver)\b/i', $preguntaUsuario)) {
                $idConsultado = intval($matchTktContext[1]);
            }
        }

        if ($idConsultado) {
            $qTarget = @mysqli_query($conn, "
                SELECT t.*, j.nombre AS juego_nombre, d.nombre AS driver_nombre, d.version AS driver_version
                FROM tickets t
                LEFT JOIN juegos j ON t.juego_id = j.id
                LEFT JOIN drivers d ON t.driver_id = d.id
                WHERE t.id = $idConsultado
            ");
            if ($qTarget && $rowTarget = mysqli_fetch_assoc($qTarget)) {
                $tktCod = "TKT-" . str_pad($rowTarget['id'], 3, '0', STR_PAD_LEFT);
                
                // Historial de cambios del ticket
                $histLogs = [];
                $qHist = @mysqli_query($conn, "
                    SELECT usuario, tipo_cambio, fecha 
                    FROM historial_tickets 
                    WHERE ticket_id = $idConsultado 
                    ORDER BY fecha DESC, id DESC 
                    LIMIT 5
                ");
                if ($qHist) {
                    while ($hl = mysqli_fetch_assoc($qHist)) {
                        $histLogs[] = "  * [{$hl['fecha']}] {$hl['usuario']}: {$hl['tipo_cambio']}";
                    }
                }
                $histTexto = !empty($histLogs) ? "\n- Historial reciente de auditoría:\n" . implode("\n", $histLogs) : "";

                // Incidencias técnicas asociadas a este ticket
                $incidenciasAsoc = [];
                $qIncAsoc = @mysqli_query($conn, "
                    SELECT id, detalle, estado 
                    FROM incidencias 
                    WHERE ticket_id = $idConsultado 
                    ORDER BY id ASC
                ");
                if ($qIncAsoc) {
                    while ($iRow = mysqli_fetch_assoc($qIncAsoc)) {
                        $cInc = "INC-" . str_pad($iRow['id'], 3, '0', STR_PAD_LEFT);
                        $incidenciasAsoc[] = "  * {$cInc} [Estado: {$iRow['estado']}] - " . (!empty($iRow['detalle']) ? mb_strimwidth($iRow['detalle'], 0, 80, '...') : 'Sin detalle');
                    }
                }
                $incAsocTexto = !empty($incidenciasAsoc) ? "\n- Incidencias técnicas asociadas a este ticket:\n" . implode("\n", $incidenciasAsoc) : "\n- Incidencias técnicas asociadas: Ninguna";

                $ticketConsultadoContexto = "
[DATOS EXACTOS DEL TICKET CONSULTADO: {$tktCod} (BASE DE DATOS OFICIAL)]
- Código: {$tktCod} (ID DB: {$rowTarget['id']})
- Título: {$rowTarget['titulo']}
- Descripción: {$rowTarget['descripcion']}
- Estado: {$rowTarget['estado']}
- Prioridad: {$rowTarget['prioridad']}
- Categoría: {$rowTarget['categoria']}
- Asignado a: " . (!empty($rowTarget['asignado']) ? $rowTarget['asignado'] : 'Sin asignar') . "
- Usuario solicitante: " . (!empty($rowTarget['usuario']) ? $rowTarget['usuario'] : 'No especificado') . "
- ¿Es Incidencia Técnica?: " . ($rowTarget['es_incidencia'] ? 'Sí' : 'No') . "
- Videojuego afectado: " . (!empty($rowTarget['juego_nombre']) ? $rowTarget['juego_nombre'] : 'N/A') . "
- Driver relacionado: " . (!empty($rowTarget['driver_nombre']) ? ($rowTarget['driver_nombre'] . ' v' . $rowTarget['driver_version']) : 'N/A') . "
- Fecha Apertura: {$rowTarget['fecha_apertura']}
- Fecha Cierre: " . (!empty($rowTarget['fecha_cierre']) ? $rowTarget['fecha_cierre'] : 'Aún abierto') . "
- Respuesta de Resolución: " . (!empty($rowTarget['respuesta_resolucion']) ? $rowTarget['respuesta_resolucion'] : 'Pendiente') . "{$incAsocTexto}{$histTexto}";
            } else {
                $tktCod = "TKT-" . str_pad($idConsultado, 3, '0', STR_PAD_LEFT);
                $ticketConsultadoContexto = "
[CONSULTA DB: {$tktCod}]
- El ticket {$tktCod} (ID DB: {$idConsultado}) NO existe en la base de datos de TechNova.
- Indicación de respuesta: Informale directamente al usuario que el ticket {$tktCod} no se encuentra registrado en el sistema. NO le pidas abrir ventanas ni supongas datos inexistentes.";
            }
        }

        // ----------------------------------------------------------------------
        // HU-45: 2. Detección y consulta directa sobre una INCIDENCIA específica
        // Ejemplos: "Estado de INC-114", "Datos de INC-114", "INC-114", "incidencia 114", "bug 114"
        // ----------------------------------------------------------------------
        $idIncidenciaConsultada = null;
        if (preg_match('/\b(?:inc\s*[-#]?\s*|incidencia\s*#?\s*|bug\s*#?\s*)0*(\d+)\b/i', $preguntaUsuario, $matchInc)) {
            $idIncidenciaConsultada = intval($matchInc[1]);
        } elseif (preg_match('/\bINC-?0*(\d+)\b/i', $ticketData, $matchIncContext)) {
            if (preg_match('/\b(?:estado|detalle|qu[eé] pasa|prioridad|asignado|datos|info|vinculada|bloqueante)\b/i', $preguntaUsuario)) {
                $idIncidenciaConsultada = intval($matchIncContext[1]);
            }
        }

        if ($idIncidenciaConsultada) {
            $qInc = @mysqli_query($conn, "
                SELECT i.*, 
                       t.titulo AS ticket_titulo, t.descripcion AS ticket_descripcion,
                       t.estado AS ticket_estado, t.prioridad AS ticket_prioridad,
                       t.categoria AS ticket_categoria, t.asignado AS ticket_asignado,
                       t.usuario AS ticket_solicitante,
                       j.nombre AS juego_nombre,
                       d.nombre AS driver_nombre, d.version AS driver_version
                FROM incidencias i
                LEFT JOIN tickets t ON i.ticket_id = t.id
                LEFT JOIN juegos j ON i.juego_id = j.id
                LEFT JOIN drivers d ON i.driver_id = d.id
                WHERE i.id = $idIncidenciaConsultada
            ");
            if ($qInc && $rowInc = mysqli_fetch_assoc($qInc)) {
                $incCod = "INC-" . str_pad($rowInc['id'], 3, '0', STR_PAD_LEFT);
                $padreCod = !empty($rowInc['ticket_id']) ? "TKT-" . str_pad($rowInc['ticket_id'], 3, '0', STR_PAD_LEFT) : null;
                $padreInfo = $padreCod 
                    ? "{$padreCod} (\"{$rowInc['ticket_titulo']}\" | Asignado: " . (!empty($rowInc['ticket_asignado']) ? $rowInc['ticket_asignado'] : 'Sin asignar') . " | Prioridad: {$rowInc['ticket_prioridad']} | Estado: {$rowInc['ticket_estado']})"
                    : "Sin ticket padre asociado";

                // Relaciones de incidencias
                $relList = [];
                $qRel = @mysqli_query($conn, "
                    SELECT tipo_relacion,
                           CASE 
                             WHEN incidencia_origen_id = $idIncidenciaConsultada THEN incidencia_destino_id 
                             ELSE incidencia_origen_id 
                           END AS vinculada_id
                    FROM relaciones_incidencias
                    WHERE incidencia_origen_id = $idIncidenciaConsultada OR incidencia_destino_id = $idIncidenciaConsultada
                ");
                if ($qRel) {
                    while ($rRow = mysqli_fetch_assoc($qRel)) {
                        $cVin = "INC-" . str_pad($rRow['vinculada_id'], 3, '0', STR_PAD_LEFT);
                        $relList[] = "  * Vinculada con {$cVin} (Tipo de Relación: {$rRow['tipo_relacion']})";
                    }
                }
                $relTexto = !empty($relList) ? "\n- Incidencias Técnicas Vinculadas:\n" . implode("\n", $relList) : "\n- Incidencias Técnicas Vinculadas: Ninguna";

                $incidenciaConsultadaContexto = "
[DATOS EXACTOS DE LA INCIDENCIA TÉCNICA CONSULTADA: {$incCod} (BASE DE DATOS OFICIAL)]
- Código de Incidencia: {$incCod} (ID DB: {$rowInc['id']})
- Estado de la Incidencia: {$rowInc['estado']}
- Detalle Técnico: {$rowInc['detalle']}
- Ticket Padre Asociado: {$padreInfo}
- Videojuego afectado: " . (!empty($rowInc['juego_nombre']) ? $rowInc['juego_nombre'] : 'N/A') . "
- Driver / Controlador: " . (!empty($rowInc['driver_nombre']) ? ($rowInc['driver_nombre'] . ' v' . $rowInc['driver_version']) : 'N/A') . "
- Fecha de Creación: {$rowInc['fecha_creacion']}
- Fecha de Cierre: " . (!empty($rowInc['fecha_cierre']) ? $rowInc['fecha_cierre'] : 'Aún abierta') . "{$relTexto}";
            } else {
                $incCod = "INC-" . str_pad($idIncidenciaConsultada, 3, '0', STR_PAD_LEFT);
                $incidenciaConsultadaContexto = "
[CONSULTA DB: {$incCod}]
- La incidencia técnica {$incCod} (ID DB: {$idIncidenciaConsultada}) NO existe en la base de datos de TechNova.
- Indicación de respuesta: Informale directamente al usuario que la incidencia {$incCod} no se encuentra registrada en el sistema. NO le pidas abrir ventanas ni supongas datos inexistentes.";
            }
        }

        // ----------------------------------------------------------------------
        // HU-45: 3. Detección y consulta directa sobre un FEEDBACK / OPINIÓN
        // Ejemplos: "Opinión OP-005", "Datos de OP-005", "OP-005", "feedback 5", "opinión 5"
        // ----------------------------------------------------------------------
        $idFeedbackConsultado = null;
        if (preg_match('/\b(?:opini[oó]n|feedback)\s*(?:op\s*[-#]?\s*|#\s*)?0*(\d+)\b/i', $preguntaUsuario, $matchFb)) {
            $idFeedbackConsultado = intval($matchFb[1]);
        } elseif (preg_match('/\bop\s*[-#]?\s*0*(\d+)\b/i', $preguntaUsuario, $matchFb)) {
            $idFeedbackConsultado = intval($matchFb[1]);
        } elseif (preg_match('/\bOP-?0*(\d+)\b/i', $ticketData, $matchFbContext)) {
            if (preg_match('/\b(?:sentimiento|comentario|usuario|tipo|tema|estado|datos|info)\b/i', $preguntaUsuario)) {
                $idFeedbackConsultado = intval($matchFbContext[1]);
            }
        }

        if ($idFeedbackConsultado) {
            $qFb = @mysqli_query($conn, "
                SELECT f.*, t.titulo AS ticket_titulo, t.estado AS ticket_estado, t.asignado AS ticket_asignado 
                FROM feedbacks f 
                LEFT JOIN tickets t ON f.ticket_id = t.id 
                WHERE f.id = $idFeedbackConsultado
            ");
            if ($qFb && $rowFb = mysqli_fetch_assoc($qFb)) {
                $opCod = "OP-" . str_pad($rowFb['id'], 3, '0', STR_PAD_LEFT);
                $tktRef = !empty($rowFb['ticket_id']) 
                    ? " (Asociado al Ticket TKT-" . str_pad($rowFb['ticket_id'], 3, '0', STR_PAD_LEFT) . ": \"{$rowFb['ticket_titulo']}\")" 
                    : " (Sin ticket asociado)";
                $feedbackConsultadoContexto = "
[DATOS EXACTOS DEL FEEDBACK/OPINIÓN CONSULTADO: {$opCod} (BASE DE DATOS OFICIAL)]
- Código: {$opCod} (ID DB: {$rowFb['id']})
- Usuario/Cliente: {$rowFb['usuario']}
- Tipo de Opinión: {$rowFb['tipo']}
- Tema / Asunto: {$rowFb['tema']}
- Sentimiento: {$rowFb['sentimiento']}
- Estado de Revisión: {$rowFb['estado']}
- Comentario completo del usuario: \"{$rowFb['comentario']}\"{$tktRef}
- Fecha de registro: {$rowFb['fecha']}";
            } else {
                $opCod = "OP-" . str_pad($idFeedbackConsultado, 3, '0', STR_PAD_LEFT);
                $feedbackConsultadoContexto = "
[CONSULTA DB: {$opCod}]
- El feedback u opinión {$opCod} (ID DB: {$idFeedbackConsultado}) NO existe en la base de datos de TechNova.
- Indicación de respuesta: Informale directamente al usuario que la opinión {$opCod} no se encuentra registrada en el sistema. NO le pidas abrir ventanas ni supongas datos inexistentes.";
            }
        }

        // 4. Cola general de tickets activos y recientes
        $qCola = @mysqli_query($conn, "
            SELECT t.id, t.titulo, t.descripcion, t.categoria, t.prioridad, t.estado, t.usuario, t.asignado, t.es_incidencia,
                   j.nombre AS juego_nombre, d.nombre AS driver_nombre
            FROM tickets t
            LEFT JOIN juegos j ON t.juego_id = j.id
            LEFT JOIN drivers d ON t.driver_id = d.id
            WHERE t.estado != 'inactivo'
            ORDER BY t.id DESC
            LIMIT 20
        ");
        if ($qCola) {
            $lineas = [];
            while ($tRow = mysqli_fetch_assoc($qCola)) {
                $cId = "TKT-" . str_pad($tRow['id'], 3, '0', STR_PAD_LEFT);
                $extraInfo = [];
                if (!empty($tRow['asignado'])) $extraInfo[] = "Asignado: " . $tRow['asignado'];
                if (!empty($tRow['juego_nombre'])) $extraInfo[] = "Juego: " . $tRow['juego_nombre'];
                if (!empty($tRow['driver_nombre'])) $extraInfo[] = "Driver: " . $tRow['driver_nombre'];
                $extraStr = !empty($extraInfo) ? " | " . implode(" | ", $extraInfo) : "";
                
                $lineas[] = "- {$cId}: \"{$tRow['titulo']}\" [Estado: {$tRow['estado']} | Prioridad: {$tRow['prioridad']} | Cat: {$tRow['categoria']}{$extraStr}]";
            }
            if (!empty($lineas)) {
                $ticketsContexto = "
[Registro de Tickets en Vivo en TechNova (Base de Datos Oficial)]
" . implode("\n", $lineas);
            }
        }

        // 5. Cola general de incidencias técnicas recientes (Tablero de Incidencias)
        $qIncList = @mysqli_query($conn, "
            SELECT i.id, i.ticket_id, i.estado, i.detalle, i.fecha_creacion,
                   t.titulo AS ticket_titulo, j.nombre AS juego_nombre, d.nombre AS driver_nombre
            FROM incidencias i
            LEFT JOIN tickets t ON i.ticket_id = t.id
            LEFT JOIN juegos j ON i.juego_id = j.id
            LEFT JOIN drivers d ON i.driver_id = d.id
            ORDER BY i.id DESC
            LIMIT 15
        ");
        if ($qIncList) {
            $incLineas = [];
            while ($inc = mysqli_fetch_assoc($qIncList)) {
                $cInc = "INC-" . str_pad($inc['id'], 3, '0', STR_PAD_LEFT);
                $cTkt = !empty($inc['ticket_id']) ? " (Ticket TKT-" . str_pad($inc['ticket_id'], 3, '0', STR_PAD_LEFT) . ")" : "";
                $det = !empty($inc['detalle']) ? mb_strimwidth(trim($inc['detalle']), 0, 70, '...') : 'Sin detalle';
                $extra = [];
                if (!empty($inc['juego_nombre'])) $extra[] = "Juego: " . $inc['juego_nombre'];
                if (!empty($inc['driver_nombre'])) $extra[] = "Driver: " . $inc['driver_nombre'];
                $extraStr = !empty($extra) ? " | " . implode(" | ", $extra) : "";
                $incLineas[] = "- {$cInc}{$cTkt}: \"{$det}\" [Estado: {$inc['estado']}{$extraStr}]";
            }
            if (!empty($incLineas)) {
                $incidenciasContexto = "
[Registro de Incidencias Técnicas en TechNova (Base de Datos Oficial)]
" . implode("\n", $incLineas);
            }
        }

        // 6. Cola de feedbacks y opiniones (Tablero de Calidad)
        $qFbs = @mysqli_query($conn, "
            SELECT f.id, f.usuario, f.tipo, f.tema, f.sentimiento, f.estado, f.ticket_id
            FROM feedbacks f
            ORDER BY f.id DESC
            LIMIT 15
        ");
        if ($qFbs) {
            $fbLineas = [];
            while ($fb = mysqli_fetch_assoc($qFbs)) {
                $cOp = "OP-" . str_pad($fb['id'], 3, '0', STR_PAD_LEFT);
                $tktBadge = !empty($fb['ticket_id']) ? " | Ticket: TKT-" . str_pad($fb['ticket_id'], 3, '0', STR_PAD_LEFT) : "";
                $fbLineas[] = "- {$cOp}: \"{$fb['tema']}\" [Tipo: {$fb['tipo']} | Sentimiento: {$fb['sentimiento']} | Estado: {$fb['estado']}{$tktBadge} | Usuario: {$fb['usuario']}]";
            }
            if (!empty($fbLineas)) {
                $feedbacksContexto = "
[Registro de Feedbacks y Opiniones en TechNova (Tablero de Calidad)]
" . implode("\n", $fbLineas);
            }
        }

        // 7. Cola de Muestreo de Calidad (Auditoría Técnica)
        $qMuestreo = @mysqli_query($conn, "
            SELECT m.ticket_id, m.agente_evaluado, m.motivo_inclusion, m.prioridad_muestreo, t.titulo
            FROM qa_muestreo_cola m
            JOIN tickets t ON m.ticket_id = t.id
            WHERE m.estado = 'Pendiente'
            ORDER BY FIELD(m.prioridad_muestreo, 'Alta', 'Media', 'Baja'), m.id DESC
        ");
        if ($qMuestreo && mysqli_num_rows($qMuestreo) > 0) {
            $mLineas = [];
            while ($mRow = mysqli_fetch_assoc($qMuestreo)) {
                $cTkt = "TKT-" . str_pad($mRow['ticket_id'], 3, '0', STR_PAD_LEFT);
                $mLineas[] = "- {$cTkt}: \"{$mRow['titulo']}\" | Agente: {$mRow['agente_evaluado']} | Motivo: {$mRow['motivo_inclusion']} | Prioridad: {$mRow['prioridad_muestreo']}";
            }
            $muestreoContexto = "
[Cola de Muestreo Inteligente de Auditoría de Calidad (Activa)]
" . implode("\n", $mLineas);
        }
    }
}

// Cargar base de conocimiento (FAQs)
$rutaDocumento = __DIR__ . "/faqs_technova.txt";
$documentoFaqs = "Documento no disponible.";
if (file_exists($rutaDocumento) && is_readable($rutaDocumento)) {
    $documentoFaqs = file_get_contents($rutaDocumento);
}

// ==============================================================================
// GESTIÓN OPERATIVA EXCLUSIVA: MUESTREO INTELIGENTE Y AUDITORÍA DE CALIDAD
// ==============================================================================
$userTeam = strtolower(trim($_SESSION["currentTeam"] ?? $_SESSION["currentUserRol"] ?? 'soporte'));
$esMonitoreoOAdmin = in_array($userTeam, ['monitoreo', 'administrador', 'responsable sla', 'responsable de monitoreo', 'admin'], true);

$preguntaNormQA = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], mb_strtolower($preguntaUsuario, 'UTF-8'));
$esIntencionMuestreoQA = (bool)preg_match(
    '/\b(muestreo\s+(?:inteligente|de\s+calidad|qa|de\s+tickets|aleatorio)|auditoria\s+de\s+calidad|auditar\s+(?:calidad|tickets|el\s+muestreo)|generar\s+muestreo|ejecutar\s+muestreo|cola\s+de\s+muestreo|tickets?\s+(?:a|para)\s+auditar|revisar\s+calidad)\b/i',
    $preguntaNormQA
) || (
    preg_match('/\b(?:hace|hacer|generar|genera|ejecutar|ejecuta|iniciar|inicia|correr|activar|activa|mostrar|mostra|ver|actualizar|actualiza|realizar|realiza|auditar)\b/i', $preguntaNormQA) &&
    preg_match('/\b(?:auditoria|muestreo)\b/i', $preguntaNormQA)
);

if ($esIntencionMuestreoQA) {
    if (!$esMonitoreoOAdmin) {
        $textoRespuesta = "⛔ ¡Hola, " . htmlspecialchars($nombreCompleto) . "! La función de **Muestreo Inteligente y Auditoría de Calidad** está reservada exclusivamente para el equipo de **Monitoreo** (y Administradores).\n\nComo tu rol actual es **" . htmlspecialchars($userRol) . "**, no dispones de los permisos operativos requeridos para ejecutar ni gestionar esta auditoría de tickets. Si detectás algún caso que requiera revisión especial, por favor reportalo al Responsable de Monitoreo o de SLA. 👾";
    } else {
        require_once __DIR__ . '/funcs/feedbackFunc.php';
        
        $quiereGenerar = (bool)preg_match('/\b(?:hace|hacer|generar|genera|ejecutar|ejecuta|activar|activa|correr|iniciar|inicia|crear|actualizar|actualiza|realizar|realiza|procesar|procesa|revisar|revisa|auditar)\b/i', $preguntaNormQA);
        if ($quiereGenerar && isset($conn) && $conn) {
            generarMuestreoInteligenteQA($conn);
        }
        
        $cola = (isset($conn) && $conn) ? obtenerColaMuestreoQA($conn) : [];
        
        if (empty($cola)) {
            $textoRespuesta = "🎯 **Muestreo Inteligente de Auditoría de Calidad ejecutado.**\n\nHe analizado los tickets cerrados en base a los disparadores de calidad (muestreo aleatorio mensual, CSAT bajo/negativo ≤ 2 estrellas y SLA crítico).\n\n✨ Actualmente no hay tickets pendientes en la cola de muestreo para auditar. Todos los casos cerrados ya fueron evaluados o cumplen satisfactoriamente con los estándares. ¡El tablero de calidad está al día! 👾";
        } else {
            $totalPendientes = count($cola);
            $itemsTexto = [];
            foreach ($cola as $item) {
                $cTkt = "TKT-" . str_pad($item['ticket_id'], 3, '0', STR_PAD_LEFT);
                $titulo = htmlspecialchars($item['titulo'] ?? 'Sin título');
                $agente = htmlspecialchars($item['agente_evaluado'] ?? 'soporte');
                $motivo = htmlspecialchars($item['motivo_inclusion'] ?? 'Muestreo');
                $prioridad = strtoupper(htmlspecialchars($item['prioridad_muestreo'] ?? 'MEDIA'));
                
                $iconoMotivo = "🎲";
                if (stripos($motivo, 'estrella') !== false || stripos($motivo, 'csat') !== false) {
                    $iconoMotivo = "⚠️";
                } elseif (stripos($motivo, 'sla') !== false) {
                    $iconoMotivo = "🚨";
                }
                
                $itemsTexto[] = "• **{$cTkt}**: *{$titulo}*\n  - **Agente Evaluado:** `{$agente}`\n  - **Motivo de Selección:** {$iconoMotivo} *{$motivo}*\n  - **Prioridad:** **{$prioridad}**";
            }
            
            $listaFormateada = implode("\n\n", $itemsTexto);
            
            $textoRespuesta = "🎯 **Muestreo Inteligente de Auditoría de Calidad ejecutado.**\n\nHe procesado los tickets cerrados aplicando los disparadores de calidad (muestreo aleatorio mensual, calificaciones CSAT bajas ≤ 2 estrellas y excesos de SLA crítico).\n\n📋 **Tickets pendientes en la cola de auditoría ({$totalPendientes} en total):**\n\n{$listaFormateada}\n\n💡 *Compañero de Monitoreo: Podés pedirme detalles de cualquiera de estos tickets (ej: 'Techy, datos del TKT-037') o realizar la evaluación de calidad.* 👾";
        }
    }

    if (!isset($_SESSION['chatbot_history']) || !is_array($_SESSION['chatbot_history'])) {
        $_SESSION['chatbot_history'] = [];
    }
    $_SESSION['chatbot_history'][] = ['role' => 'user', 'message' => $preguntaUsuario];
    $_SESSION['chatbot_history'][] = ['role' => 'bot', 'message' => $textoRespuesta];

    echo json_encode([
        "ok" => true,
        "respuesta" => $textoRespuesta
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ==============================================================================
// HU-41 SUB-TAREA 4 & HU-45: SYSTEM PROMPT OFICIAL DE TECHY
// ==============================================================================
$systemPrompt = "Nombre del Asistente: Techy
Rol: Asistente operativo interno del equipo de IT de TechNova.
Público: NO hablás con clientes finales. Solo asistís a los miembros del equipo interno: Responsable de Soporte, Responsable de Desarrollo, Responsable de Monitoreo y Administrador.

[Tono y Personalidad]
Sos un 'sidekick' (compañero de equipo) ágil, optimista y resolutivo. Usá un tono informal, claro y directo (tratando de 'vos'). Usá metáforas sutiles del desarrollo de software y los videojuegos (ej: 'bug aplastado', 'subir de nivel', 'darle start', 'checkpoint', 'glitch'), pero sin exagerar ni sonar forzado. Tu objetivo es hacer que la gestión de tickets y SLAs sea dinámica y menos tediosa. Usá emojis de forma moderada pero efectiva (👾, 💾, 🔄, ⚠️, 🎉).

[Contexto Dinámico Obligatorio del Sistema]
En cada interacción, el sistema te envía datos oficiales de la plataforma y la base de datos. Respondé basándote estrictamente en esta información:
- Rol del usuario actual: {$userRol}
- Compañero: {$nombreCompleto} (usuario: {$usuario})
- Pantalla/Módulo que está viendo: {$pantallaActual}
- Datos del ticket o modal en pantalla (captura del frontend): {$ticketData}
{$ticketConsultadoContexto}
{$incidenciaConsultadaContexto}
{$feedbackConsultadoContexto}
{$metricasContexto}
{$ticketsContexto}
{$incidenciasContexto}
{$feedbacksContexto}
{$muestreoContexto}

[Pautas según el Rol del Compañero]
- Si es SOPORTE: Ayudalo a clasificar tickets (Prioridad, Categoría, Estado), asignaciones y atención rápida para darle start a la cola y cumplir los tiempos de respuesta.
- Si es DESARROLLO: Enfocate en bugs aplastados 👾, incidencias técnicas, videojuegos afectados y compatibilidad de drivers.
- Si es MONITOREO: Enfocate en métricas del Dashboard, cumplimiento de SLAs ⏱️, alertas preventivas, quejas de usuarios y la gestión de la Auditoría de Calidad y Muestreo Inteligente de Tickets.
- Si es ADMINISTRADOR: Dale visión global ejecutiva, auditorías QA y planes CAPA.

[Reglas de Comportamiento Oficiales y Mandatarias (HU-45)]
1. CONSULTA DIRECTA DE DATOS Y RESPUESTAS COMPLETAS (HU-45 Criterios 1 y 2):
Tenés acceso directo e irrestricto a la base de datos oficial de TechNova: tickets (TKT-XXX), incidencias técnicas (INC-XXX) y feedbacks u opiniones de usuarios (OP-XXX), además de la información del modal o pantalla activa.
- PROHIBICIÓN ABSOLUTA: NUNCA le pidas al usuario que abra el ticket, que abra la incidencia, que abra la opinión ni que abra ninguna ventana si el registro existe en la base de datos o en pantalla. Tampoco uses frases evasivas como 'ingresa a la pantalla de tickets' o 'haz clic en ver más'.
- Respondé INMEDIATAMENTE con los datos precisos consultados:
  * Si preguntan '¿Quién tiene el ticket 38?', respondé de forma directa quién es el asignado actual ('desarrollo'), su título ('bsod nvlddmkm.sys durante la compilación de shaders'), su prioridad y su estado.
  * Si preguntan 'Datos de TKT-036', entrega su título, estado, prioridad, asignado ('desarrollo'), descripción y detalles técnicos relevantes.
  * Si preguntan 'Estado de INC-114', respondé con el estado exacto de la incidencia ('en progreso'), su detalle técnico, su ticket padre asociado (TKT-038) y cualquier incidencia vinculada (ej: bloqueante con INC-104).
  * Si preguntan 'Opinión OP-005', entregá el usuario ('admin'), tipo ('Sugerencia'), tema ('Sugerencia de sistema'), sentimiento ('Positivo'), estado ('Revisado') y el comentario textual completo.
- INTEGRIDAD DE RESPUESTAS (HU-45 Criterio 4): Tus respuestas deben ser SIEMPRE COMPLETAS y autosuficientes, cerrando todas las ideas, oraciones y párrafos de principio a fin, sin dejar textos cortados o truncados.

2. ESTRUCTURA EN FORMATO MARKDOWN LIMPIO (HU-45 Criterio 4):
- Utilizá siempre markdown limpio y bien formateado:
  * Negritas (**texto**) para resaltar nombres, asignados, estados y métricas clave.
  * Etiquetas de código (`TKT-038`, `INC-114`, `OP-005`) para identificadores y códigos técnicos.
  * Viñetas (• o -) para listas estructuradas de campos.
  * Separá las secciones con saltos de línea claros.
- Mantené respuestas concisas, dinámicas y al grano, evitando rodeos innecesarios para que el usuario resuelva su consulta operativa en segundos.

3. PRIORIDAD DE PANTALLA Y MODALES:
Si el usuario tiene un modal o elemento abierto en pantalla, dale prioridad a ese contexto para resolver sus dudas inmediatas sobre ese caso puntual.

4. SALUDOS CONTEXTUALES:
Si el usuario te saluda, saludalo según su rol usando la telemetría del sistema (tickets activos a Soporte, incidencias a Desarrollo, SLAs a Monitoreo).

5. ROL DE TECHNOVA:
Si te preguntan algo ajeno a TechNova, gestión de tickets o IT, recordá amablemente cuál es tu función operativa como sidekick de TechNova.

Base de Conocimiento oficial (FAQs de TechNova):
{$documentoFaqs}";

// ==============================================================================
// HU-41 SUB-TAREA 1: ENVIAR HISTORIAL DE LA CONVERSACIÓN (Multi-turn para Gemini)
// ==============================================================================
$historialEntrante = (isset($input["historial"]) && is_array($input["historial"])) ? $input["historial"] : [];

// Tomar hasta los últimos 8 mensajes para contexto óptimo de seguimiento
$ultimosMensajes = array_slice($historialEntrante, -8);
$contents = [];

foreach ($ultimosMensajes as $item) {
    if (!is_array($item) || empty($item["message"])) continue;
    $rolMsg = ($item["role"] === "user") ? "user" : "model";
    $textoMsg = trim((string)$item["message"]);
    if ($textoMsg === "") continue;

    // Gemini no debe empezar con un rol 'model' si es el primer turno en contents
    if (empty($contents) && $rolMsg === "model") {
        continue;
    }

    // Evitar dos roles consecutivos idénticos fusionando el contenido
    $ultimoTurno = end($contents);
    if ($ultimoTurno && $ultimoTurno["role"] === $rolMsg) {
        $indice = key($contents);
        $contents[$indice]["parts"][0]["text"] .= "\n" . $textoMsg;
    } else {
        $contents[] = [
            "role" => $rolMsg,
            "parts" => [["text" => $textoMsg]]
        ];
    }
}

// Agregar el mensaje actual del usuario como el último turno
$ultimoTurno = end($contents);
if ($ultimoTurno && $ultimoTurno["role"] === "user") {
    $indice = key($contents);
    $contents[$indice]["parts"][0]["text"] .= "\n" . $preguntaUsuario;
} else {
    $contents[] = [
        "role" => "user",
        "parts" => [["text" => $preguntaUsuario]]
    ];
}

// ==============================================================================
// LLAMADA A LA API DE GOOGLE GEMINI (HU-45 Criterio 4: Tokens y Respuestas Completas)
// ==============================================================================
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($modelo) . ":generateContent?key=" . urlencode($apiKey);

$generationConfig = [
    "temperature" => 0.2,
    "maxOutputTokens" => 4096
];

// Para modelos de la familia Gemini 2.5 (que por defecto activan pensamiento interno y pueden agotar el tiempo/tokens),
// optimizamos thinkingBudget a 0 para respuestas operativas instantáneas sin cortes
if (strpos($modelo, '2.5') !== false || strpos($modelo, 'thinking') !== false) {
    $generationConfig["thinkingConfig"] = [
        "thinkingBudget" => 0
    ];
}

$payload = [
    "systemInstruction" => [
        "parts" => [
            ["text" => $systemPrompt]
        ]
    ],
    "contents" => $contents,
    "generationConfig" => $generationConfig
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_CONNECTTIMEOUT => 7,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Manejo de errores de transporte cURL
if ($curlErrno !== 0) {
    http_response_code(504);

    if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
        $msgError = "Techy tardó demasiado tiempo en responder (tiempo de espera agotado). Por favor, intenta de nuevo.";
    } elseif ($curlErrno === CURLE_COULDNT_CONNECT || $curlErrno === CURLE_COULDNT_RESOLVE_HOST) {
        $msgError = "No se pudo conectar con el servicio de inteligencia artificial. Comprueba la conexión del servidor.";
    } else {
        $msgError = "Error de comunicación con el servicio de IA (" . htmlspecialchars($curlError) . ").";
    }

    echo json_encode([
        "ok" => false,
        "error" => "curl_error",
        "respuesta" => $msgError
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Manejo de respuesta exitosa de Gemini (HU-45 Criterio 4)
if ($httpCode === 200 && !empty($response)) {
    $responseData = json_decode($response, true);
    $candidate = $responseData["candidates"][0] ?? null;
    $textoRespuesta = "";

    if ($candidate && !empty($candidate["content"]["parts"])) {
        foreach ($candidate["content"]["parts"] as $part) {
            // Ignorar pensamiento interno si viniera en una parte separada
            if (!empty($part["thought"])) {
                continue;
            }
            if (isset($part["text"])) {
                $textoRespuesta .= $part["text"];
            }
        }
    }

    $textoRespuesta = trim($textoRespuesta);

    if (empty($textoRespuesta)) {
        http_response_code(502);
        echo json_encode([
            "ok" => false,
            "error" => "empty_gemini_response",
            "respuesta" => "No se pudo interpretar la respuesta generada por Techy. Por favor, intenta reformular tu pregunta."
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // HU-45 Criterio 4: Garantizar que la respuesta no tenga oraciones truncadas en el final
    $ultimosCaracteres = mb_substr($textoRespuesta, -1, 1, 'UTF-8');
    $caracteresValidosFin = ['.', '!', '?', ':', ')', ']', '"', "'", '`', '>', '👾', '💾', '🔄', '✨', '🚨', '⚠️', '🎯', '🎉'];
    if (!in_array($ultimosCaracteres, $caracteresValidosFin, true)) {
        if (in_array($ultimosCaracteres, [',', ';', '-'], true)) {
            $textoRespuesta = mb_substr($textoRespuesta, 0, -1, 'UTF-8') . '.';
        } else {
            $textoRespuesta .= '.';
        }
    }

    // Actualizar historial en la sesión del servidor
    if (!isset($_SESSION['chatbot_history']) || !is_array($_SESSION['chatbot_history'])) {
        $_SESSION['chatbot_history'] = [];
    }
    $_SESSION['chatbot_history'][] = ['role' => 'user', 'message' => $preguntaUsuario];
    $_SESSION['chatbot_history'][] = ['role' => 'bot', 'message' => $textoRespuesta];

    echo json_encode([
        "ok" => true,
        "respuesta" => $textoRespuesta
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Manejo de respuestas no 200 de la API
http_response_code(502);

if ($httpCode === 400 || $httpCode === 403) {
    $msgError = "Error de autenticación o solicitud con el servicio de IA de Gemini. Notifica al administrador.";
} elseif ($httpCode === 429) {
    $msgError = "Techy ha alcanzado el límite momentáneo de consultas. Por favor, aguarda unos segundos e intenta nuevamente.";
} elseif ($httpCode >= 500) {
    $msgError = "El servicio de Google Gemini no se encuentra disponible temporalmente. Intenta más tarde.";
} else {
    $msgError = "El servicio de IA devolvió un código inesperado (HTTP " . intval($httpCode) . ").";
}

echo json_encode([
    "ok" => false,
    "error" => "gemini_api_error",
    "http_code" => $httpCode,
    "respuesta" => $msgError
], JSON_UNESCAPED_UNICODE);
?>