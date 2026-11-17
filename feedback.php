<?php 
ob_start();
session_start();
require_once("funcs/session_security.php");
require_once("db.php");
require_once("funcs/feedbackFunc.php");
include_once("funcs/generalFuncs.php");

inicializarBaseDeDatos($conn);

if (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "desarrollo") {
    http_response_code(403);
    die("Acceso denegado: Este módulo no es accesible para el Equipo de Desarrollo.");
}

if (isset($_SESSION["currentTeam"])) {
    registrarFeedback($conn);
    actualizarFeedbackEstado($conn);
    eliminarFeedback($conn);
    registrarQAAuditoria($conn);
    registrarCAPACoaching($conn);
    actualizarEstadoCAPA($conn);
}

// HU-44: Manejo de acciones del ciclo de vida de calidad
if (isset($_POST["accion_enviar_encuesta"])) {
    $evalId = intval($_POST["accion_enviar_encuesta"]);
    try {
        $res = enviarEncuestaUsuario($conn, $evalId);
        if (!empty($res['success'])) {
            $_SESSION["msg"] = '<div class="toast-success">📨 Encuesta de calidad enviada exitosamente al usuario (Estado: Enviada).</div>';
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error al enviar encuesta: ' . htmlspecialchars($res['error'] ?? '') . '</div>';
        }
    } catch (\Throwable $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    redirigirFeedback("feedback.php?tab=csat");
}

if (isset($_POST["accion_responder_encuesta"])) {
    $evalId = intval($_POST["evaluacion_id"] ?? 0);
    $calif = intval($_POST["calificacion"] ?? 5);
    $coment = trim($_POST["comentario"] ?? "");
    $tipo = trim($_POST["tipo"] ?? "Sugerencia");
    $sent = trim($_POST["sentimiento"] ?? "Positivo");
    try {
        $res = registrarDetalleOpinion($conn, $evalId, $calif, $coment, $tipo, null, $sent);
        if (!empty($res['success'])) {
            $_SESSION["msg"] = '<div class="toast-success">⭐ Calificación de ' . $calif . '/5 y comentarios del cliente registrados exitosamente (Estado: Completada).</div>';
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error al registrar respuesta: ' . htmlspecialchars($res['error'] ?? '') . '</div>';
        }
    } catch (\Throwable $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    redirigirFeedback("feedback.php?tab=csat");
}

if (isset($_POST["accion_expirar_encuesta"])) {
    $evalId = intval($_POST["accion_expirar_encuesta"]);
    try {
        $res = timeoutPlazoRespuesta($conn, $evalId);
        if ($res) {
            $_SESSION["msg"] = '<div class="toast-warning">⏰ Evaluación marcada como Expirada por vencimiento del plazo límite sin respuesta.</div>';
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ No se pudo expirar la evaluación seleccionada.</div>';
        }
    } catch (\Throwable $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    redirigirFeedback("feedback.php?tab=csat");
}

if (isset($_POST["accion_calcular_sla"])) {
    $evalId = intval($_POST["accion_calcular_sla"]);
    try {
        $res = calcularCumplimientoSLA($conn, $evalId);
        if (!empty($res['success'])) {
            $msgSLA = $res['sla_cumplido'] == 1 ? 'Cumple SLA ✅' : 'No Cumple SLA ❌';
            $_SESSION["msg"] = '<div class="toast-success">📊 Evaluación procesada exitosamente para métricas de panel (' . $msgSLA . ', Estado: Procesada).</div>';
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error al calcular SLA: ' . htmlspecialchars($res['error'] ?? '') . '</div>';
        }
    } catch (\Throwable $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    redirigirFeedback("feedback.php?tab=csat");
}

if (isset($_POST["ejecutar_metricas_globales"]) || isset($_POST["sincronizar_calidad_sla"])) {
    $numExp = timeoutPlazoRespuesta($conn, null);
    $res = generarMetricasGlobales($conn);
    if ($res) {
        $msgDetalle = "🔄 Sincronización de calidad completada: ";
        if ($numExp > 0) {
            $msgDetalle .= $numExp . " evaluaciones expiradas por timeout y ";
        }
        $msgDetalle .= ($res['procesadas_en_ejecucion'] ?? 0) . " evaluaciones procesadas para métricas de SLA (" . ($res['cumplimiento_sla_porcentaje'] ?? 0) . "% de cumplimiento global).";
        $_SESSION["msg"] = '<div class="toast-success">' . $msgDetalle . '</div>';
    } else {
        $_SESSION["msg"] = '<div class="toast-error">❌ Error al sincronizar métricas.</div>';
    }
    redirigirFeedback("feedback.php?tab=csat");
}

if (isset($_POST["procesar_timeouts_globales"])) {
    $numExp = timeoutPlazoRespuesta($conn, null);
    $_SESSION["msg"] = '<div class="toast-warning">⏰ Verificación de plazos completada: ' . $numExp . ' evaluaciones vencidas pasaron a Expirada.</div>';
    redirigirFeedback("feedback.php?tab=csat");
}

if (isset($_POST["generar_muestreo_qa"])) {
    $numNuevos = generarMuestreoInteligenteQA($conn);
    $_SESSION["msg"] = '<div class="toast-success">⚡ Muestreo inteligente ejecutado exitosamente. Se procesaron/añadieron ' . $numNuevos . ' tickets a la cola de auditoría.</div>';
    redirigirFeedback("feedback.php?tab=qa");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Tablero de Calidad</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/index-V-K5O4c4.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    .badge-custom {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        line-height: 1rem;
        border-radius: 0.25rem;
        border-width: 1px;
        font-weight: 500;
        display: inline-block;
        border-style: solid;
    }
    .badge-sugerencia {
        background-color: rgb(8, 51, 68) !important;
        color: #22d3ee !important;
        border-color: #155e75 !important;
    }
    .badge-queja, .badge-negativo {
        background-color: rgb(69, 10, 10) !important;
        color: #f87171 !important;
        border-color: #991b1b !important;
    }
    .badge-elogio, .badge-positivo {
        background-color: rgb(6, 78, 59) !important;
        color: #34d399 !important;
        border-color: #065f46 !important;
    }
    .badge-default {
        background-color: rgb(15, 23, 42) !important;
        color: #94a3b8 !important;
        border-color: #334155 !important;
    }
    .badge-estado-nuevo {
        background-color: rgb(8, 51, 68) !important;
        color: #22d3ee !important;
        border-color: #155e75 !important;
    }
    .badge-estado-revisado {
        background-color: rgb(66, 32, 6) !important;
        color: #facc15 !important;
        border-color: #854d0e !important;
    }
    .badge-estado-implementado {
        background-color: rgb(6, 78, 59) !important;
        color: #34d399 !important;
        border-color: #065f46 !important;
    }
    /* Badges Ciclo de Vida Calidad HU-44 */
    .badge-calidad-pendiente {
        background-color: rgba(120, 53, 15, 0.45) !important;
        color: #fde047 !important;
        border: 1px solid #ca8a04 !important;
    }
    .badge-calidad-enviada {
        background-color: rgba(8, 51, 68, 0.65) !important;
        color: #38bdf8 !important;
        border: 1px solid #0284c7 !important;
    }
    .badge-calidad-completada {
        background-color: rgba(6, 78, 59, 0.55) !important;
        color: #4ade80 !important;
        border: 1px solid #16a34a !important;
    }
    .badge-calidad-procesada {
        background-color: rgba(67, 56, 202, 0.4) !important;
        color: #a5b4fc !important;
        border: 1px solid #6366f1 !important;
    }
    .badge-calidad-cancelada {
        background-color: rgba(136, 19, 55, 0.65) !important;
        color: #fda4af !important;
        border: 1px solid #e11d48 !important;
    }
    .badge-calidad-expirada {
        background-color: rgba(124, 45, 18, 0.55) !important;
        color: #fdba74 !important;
        border: 1px solid #ea580c !important;
    }
    /* Badges para Grilla de Seguimiento CAPA (HU 34) */
    .badge-capa-pendiente {
        background-color: rgba(120, 53, 15, 0.45) !important;
        color: #fde047 !important;
        border: 1px solid #ca8a04 !important;
    }
    .badge-capa-proceso {
        background-color: rgba(8, 51, 68, 0.65) !important;
        color: #38bdf8 !important;
        border: 1px solid #0284c7 !important;
    }
    .badge-capa-completado {
        background-color: rgba(6, 78, 59, 0.55) !important;
        color: #4ade80 !important;
        border: 1px solid #16a34a !important;
    }
    .badge-capa-vencido {
        background-color: rgba(136, 19, 55, 0.65) !important;
        color: #fda4af !important;
        border: 1px solid #e11d48 !important;
        font-weight: 700 !important;
    }
    /* Badges para Cola de Muestreo de Calidad */
    .badge-muestreo-aleatorio {
        background-color: #083344 !important;
        color: #67e8f9 !important;
        border: 1px solid #155e75 !important;
        padding: 0.25rem 0.6rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-muestreo-csat {
        background-color: #451a03 !important;
        color: #fcd34d !important;
        border: 1px solid #78350f !important;
        padding: 0.25rem 0.6rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-muestreo-sla {
        background-color: #450a0a !important;
        color: #fca5a5 !important;
        border: 1px solid #7f1d1d !important;
        padding: 0.25rem 0.6rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-prioridad-alta {
        background-color: #450a0a !important;
        color: #fca5a5 !important;
        border: 1px solid #991b1b !important;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-prioridad-media {
        background-color: #422006 !important;
        color: #fde047 !important;
        border: 1px solid #854d0e !important;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-prioridad-baja {
        background-color: #0f172a !important;
        color: #94a3b8 !important;
        border: 1px solid #334155 !important;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .pie-slice {
        cursor: pointer;
    }
    .pie-slice:hover {
        stroke-width: 44px !important;
        opacity: 1 !important;
    }

    /* ============================================================== */
    /* HU 44: ESTILOS DEDICADOS PARA PANEL DE CICLO DE VIDA CALIDAD    */
    /* ============================================================== */
    .sla-panel {
        width: 100% !important;
        background-color: #0f172a !important;
        border: 1px solid #334155 !important;
        border-radius: 8px !important;
        padding: 12px 14px !important;
        margin-top: 0.75rem !important;
        margin-bottom: 0.75rem !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4) !important;
        box-sizing: border-box !important;
    }
    .calidad-grid {
        display: grid !important;
        grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
        gap: 0.75rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    @media (max-width: 1200px) {
        .calidad-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
    }
    @media (max-width: 640px) {
        .calidad-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    }

    /* ============================================================== */
    /* HU 35: ESTILOS DEDICADOS PARA MATRIZ DE CALIDAD CRUZADA         */
    /* ============================================================== */
    .cruzada-header-card {
        background-color: #0f172a;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.85));
        border: 1px solid #334155;
        border-radius: 0.75rem;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    }
    .cruzada-kpi-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    @media (min-width: 768px) {
        .cruzada-kpi-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    .cruzada-card {
        background-color: #0f172a;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.85) 100%);
        border: 1px solid #334155;
        border-radius: 0.75rem;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.35), 0 4px 6px -4px rgba(0, 0, 0, 0.35);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: border-color 0.25s ease, box-shadow 0.25s ease;
    }
    .cruzada-card:hover {
        border-color: #475569;
        box-shadow: 0 14px 20px -3px rgba(0, 0, 0, 0.45);
    }
    .cruzada-card-qa:hover {
        border-color: rgba(16, 185, 129, 0.5);
    }
    .cruzada-card-errores:hover {
        border-color: rgba(239, 68, 68, 0.5);
    }
    .cruzada-card-csat:hover {
        border-color: rgba(6, 182, 212, 0.5);
    }
    .cruzada-columns-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    @media (min-width: 1024px) {
        .cruzada-columns-grid {
            grid-template-columns: 7fr 5fr;
        }
    }
    .cruzada-chart-card {
        background-color: #0f172a;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.85) 100%);
        border: 1px solid #334155;
        border-radius: 0.75rem;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.35);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .cruzada-table-card {
        background-color: #0f172a;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.85) 100%);
        border: 1px solid #334155;
        border-radius: 0.75rem;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.35);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .cruzada-progress-track {
        width: 100%;
        background-color: #1e293b;
        border-radius: 9999px;
        height: 0.5rem;
        overflow: hidden;
        box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.4);
    }
    .cruzada-chart-container {
        position: relative;
        height: 280px;
        width: 100%;
    }
    .cruzada-table-container {
        overflow-x: auto;
        border-radius: 0.5rem;
        border: 1px solid #334155;
        margin-top: 0.75rem;
    }
    .cruzada-table {
        width: 100%;
        text-align: left;
        border-collapse: collapse;
        font-size: 0.75rem;
    }
    .cruzada-table th {
        padding: 0.65rem 0.85rem;
        background-color: rgba(30, 41, 59, 0.75);
        border-bottom: 1px solid #334155;
        color: #94a3b8;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 0.05em;
        font-weight: 600;
    }
    .cruzada-table td {
        padding: 0.75rem 0.85rem;
        border-bottom: 1px solid rgba(51, 65, 85, 0.5);
        vertical-align: middle;
    }
    .cruzada-table tbody tr:last-child td {
        border-bottom: none;
    }
    .cruzada-table tbody tr:hover {
        background-color: rgba(30, 41, 59, 0.4);
    }
    </style>
</head>
<body>
<?php

$campos = [
    function($fila) {
        $fbIdFormatted = 'OP-' . str_pad($fila["id"], 3, "0", STR_PAD_LEFT);
        $ticketBadge = "";
        if (!empty($fila["ticket_id"])) {
            $ticketPad = str_pad($fila["ticket_id"], 3, "0", STR_PAD_LEFT);
            $ticketBadge = '
                <span class="ml-2 inline-flex items-center gap-1 rounded bg-blue-950/40 border border-blue-900/50 px-1.5 py-0.5 text-[10px] text-blue-400 font-medium cursor-help" title="Viene del ticket TKT-' . $ticketPad . '">
                    🔗 TKT-' . $ticketPad . '
                </span>';
        }
        
        return '<td class="p-3 text-cyan-400 font-semibold whitespace-nowrap">' . $fbIdFormatted . $ticketBadge . '</td>';
    },

    function($fila) {
        return '<td class="p-3 text-slate-200">' . htmlspecialchars($fila["usuario"] ?? '') . '</td>';
    },

    function($fila) {
        $texto = !empty($fila["tipo"]) ? ucfirst(strtolower(trim($fila["tipo"]))) : '-';
        $clase = getFeedbackTipoBadgeClass($fila["tipo"]); 
        return '<td class="p-3"><span class="badge-custom ' . $clase . '" data-cell="tipo">' . $texto . '</span></td>';
    },

    function($fila) {
        $tema = !empty($fila["tema"]) ? htmlspecialchars($fila["tema"]) : '-';
        return '<td class="p-3 text-slate-300 max-w-[200px] truncate" title="' . $tema . '" data-cell="tema">' . $tema . '</td>';
    },

    function($fila) {
        $texto = !empty($fila["sentimiento"]) ? ucfirst(strtolower(trim($fila["sentimiento"]))) : '-';
        $clase = getFeedbackSentimientoBadgeClass($fila["sentimiento"]);
        $ratingHtml = '';
        if (!empty($fila["calificacion"])) {
            $ratingHtml = '<div class="text-[11px] text-amber-400 mt-1 font-semibold">' . str_repeat('⭐', intval($fila["calificacion"])) . ' ' . $fila["calificacion"] . '/5</div>';
        }
        return '<td class="p-3"><span class="badge-custom ' . $clase . '" data-cell="sentimiento">' . $texto . '</span>' . $ratingHtml . '</td>';
    },

    function($fila) {
        $estado = !empty($fila["estado"]) ? trim($fila["estado"]) : 'PendienteEnvio';
        $clase = getFeedbackEstadoBadgeClass($estado);
        $nombre = getEstadoCalidadNombreLegible($estado);
        $slaTag = '';
        if ($estado === 'Procesada') {
            $slaTag = isset($fila['sla_cumplido']) && $fila['sla_cumplido'] == 1 
                ? '<div class="mt-1 inline-block text-[10px] px-1.5 py-0.5 rounded bg-emerald-950/70 text-emerald-400 border border-emerald-800/80 font-bold">✓ Cumple SLA</div>'
                : '<div class="mt-1 inline-block text-[10px] px-1.5 py-0.5 rounded bg-rose-950/70 text-rose-400 border border-rose-800/80 font-bold">✗ Excede SLA</div>';
        } else if ($estado === 'Completada') {
            $slaTag = '<div class="mt-1 inline-block text-[10px] px-1.5 py-0.5 rounded bg-amber-950/60 text-amber-300 border border-amber-800/70 font-medium">⏳ Pendiente SLA</div>';
        }
        return '<td class="p-3"><span class="badge-custom ' . $clase . '" data-cell="estado">' . htmlspecialchars($nombre) . '</span>' . $slaTag . '</td>';
    },
    function($fila) {
        $id = intval($fila["id"]);
        $estado = $fila["estado"] ?? 'PendienteEnvio';

        // HU-44 Botones de Acción de Ciclo de Vida
        $lifecycleBtn = '';
        if ($estado === 'PendienteEnvio') {
            $lifecycleBtn = '
              <form method="POST" class="inline-block m-0 p-0">
                <input type="hidden" name="active_tab" value="csat" />
                <button type="submit" name="accion_enviar_encuesta" value="' . $id . '" class="btn-accion-icono" style="color: #38bdf8 !important;" title="Enviar Encuesta al Usuario (Enviada)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-send-fill" viewBox="0 0 16 16"><path d="M15.964.686a.5.5 0 0 0-.65-.65L.767 5.855H.766l-.452.18a.5.5 0 0 0-.082.887l.41.26.001.002 4.995 3.178 3.178 4.995.002.002.26.41a.5.5 0 0 0 .886-.083zm-1.833 1.89L6.637 10.07l-.215-.136L2.43 7.42 14.13 2.576z"/></svg>
                </button>
              </form>';
        } else if ($estado === 'Enviada') {
            $temaEsc = htmlspecialchars(addslashes($fila["tema"] ?? ''));
            $userEsc = htmlspecialchars(addslashes($fila["usuario"] ?? ''));
            $lifecycleBtn = '
              <button type="button" onclick="abrirModalResponderCSAT(' . $id . ', \'' . $userEsc . '\', \'' . $temaEsc . '\')" class="btn-accion-icono" style="color: #facc15 !important;" title="Registrar Calificación del Cliente (Completada)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star-fill" viewBox="0 0 16 16"><path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/></svg>
              </button>
              <form method="POST" class="inline-block m-0 p-0" onsubmit="return confirm(\'¿Marcar esta encuesta como Expirada por vencimiento de plazo?\');">
                <input type="hidden" name="active_tab" value="csat" />
                <button type="submit" name="accion_expirar_encuesta" value="' . $id . '" class="btn-accion-icono" style="color: #fb923c !important;" title="Expirar por Plazo Límite (Expirada)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16"><path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.084-.51l.995.106a8 8 0 0 1-.119.764zm-.759 2.075q.08-.25.138-.508l.977.219a8 8 0 0 1-.293.794zm-1.124 1.64q.135-.226.248-.465l.898.44a8 8 0 0 1-.502.738zm-1.42 1.353q.154-.16.294-.332l.783.623a8 8 0 0 1-.61.564zm-1.802.946q.192-.12.373-.252l.602.8a8 8 0 0 1-.74.433zm-2.02.482q.217-.07.424-.152l.37.928a8 8 0 0 1-.806.262zm-2.062.08q.244-.017.48-.046l.123.992a8 8 0 0 1-.926.062zm-2.05-.333q.237.042.476.07l-.117.993a8 8 0 0 1-.913-.124zm-1.956-.732q.214.098.432.181l-.35.936a8 8 0 0 1-.83-.341zm-1.74-1.11q.178.148.361.282l-.578.816a8 8 0 0 1-.689-.533zm-1.442-1.464q.134.185.275.358l-.759.652a8 8 0 0 1-.52-.693zm-1.087-1.758q.082.215.177.422l-.895.446a8 8 0 0 1-.334-.828zm-.666-1.968q.034.238.077.472l-.986.165a8 8 0 0 1-.144-.92zm-.214-2.068q.002.247.014.492l-.999.034a8 8 0 0 1 .012-.976zM8 3.5a.5.5 0 0 0-.5.5v4a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8.5 7.71V4a.5.5 0 0 0-.5-.5"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/></svg>
                </button>
              </form>';
        } else if ($estado === 'Completada') {
            $lifecycleBtn = '
              <form method="POST" class="inline-block m-0 p-0">
                <input type="hidden" name="active_tab" value="csat" />
                <button type="submit" name="accion_calcular_sla" value="' . $id . '" class="btn-accion-icono" style="color: #a5b4fc !important;" title="Calcular Cumplimiento SLA (Procesada)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-patch-check-fill" viewBox="0 0 16 16"><path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.637-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708"/></svg>
                </button>
              </form>';
        }

        $deleteBtn = '';
        if (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] !== "monitoreo") {
            $deleteBtn = '
              <form method="POST" class="inline-block m-0 p-0">
                <button type="submit" name="eliminar" value="' . $fila["id"] . '" class="btn-accion-icono icono-eliminar" title="Eliminar" onclick="return confirm(\'¿Estás seguro de que deseas eliminar este reporte de feedback?\');">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                </button>
              </form>';
        }

        $historyBtn = '';
        if (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador")) {
            if (isset($fila["ticket_id"]) && intval($fila["ticket_id"]) > 0) {
                $historyBtn = '
                  <form method="POST" class="inline-block m-0 p-0">
                    <button type="submit" name="ver_historial" value="' . $fila["ticket_id"] . '" class="btn-accion-icono" style="color: #60a5fa !important;" title="Historial del Ticket">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-text" viewBox="0 0 16 16"><path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/></svg>
                    </button>
                  </form>';
            }
        }

        return '<td class="p-3 text-center">
            <div class="contenedor-acciones-tabla">
              ' . $lifecycleBtn . '
              <form method="POST" class="inline-block m-0 p-0">
                <button type="submit" name="ver_detalle" value="' . $fila["id"] . '" class="btn-accion-icono icono-ver" title="Ver Detalle">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                </button>
              </form>
              ' . $historyBtn . '
              ' . $deleteBtn . '
            </div>
          </td>';
    }
];

// HU-44: Verificación periódica silenciosa de encuestas expiradas por timeout
timeoutPlazoRespuesta($conn);

$feedbacks = obtenerFeedbacks($conn);
$stats = obtenerFeedbackStats($conn);

$qaAuditorias = obtenerQAAuditorias($conn);
$qaStats = obtenerQAStats($conn);

$colaMuestreo = obtenerColaMuestreoQA($conn);
$muestreoStats = obtenerMuestreoStats($conn);

$capaLista = obtenerCAPACoaching($conn);
$capaStats = obtenerCAPAStats($conn);

// HU 35: Matriz de Calidad Cruzada
$kpisCruzada = obtenerKPIsCalidadCruzada($conn);
$matrizCruzada = obtenerMatrizCalidadCruzadaPorAgente($conn);
$cruzadaLabels = [];
$cruzadaCSAT = [];
$cruzadaQA = [];
$cruzadaGap = [];
foreach ($matrizCruzada as $m) {
    $cruzadaLabels[] = $m['nombre_legible'];
    $cruzadaCSAT[] = $m['csat_score'];
    $cruzadaQA[] = $m['qa_score'];
    $cruzadaGap[] = $m['gap'];
}

if (isset($_POST['inputBuscar']) || isset($_POST['tipoBuscar']) || isset($_POST['sentimientoBuscar']) || isset($_POST['buscar'])) {
    $activeTab = 'csat';
} else if (isset($_POST['inputBuscarQA']) || isset($_POST['resultadoBuscarQA']) || isset($_POST['buscarQA']) || isset($_POST['generar_muestreo_qa'])) {
    $activeTab = 'qa';
} else if (isset($_POST['inputBuscarCAPA']) || isset($_POST['tipoBuscarCAPA']) || isset($_POST['estadoBuscarCAPA']) || isset($_POST['cambiar_estado_capa'])) {
    $activeTab = 'capa';
} else {
    $activeTab = $_POST['active_tab'] ?? $_GET['tab'] ?? $_SESSION['active_tab'] ?? 'csat';
}
if (!in_array($activeTab, ['csat', 'qa', 'capa', 'cruzada'])) {
    $activeTab = 'csat';
}
unset($_SESSION['active_tab']);

$primerDiaMes = date('Y-m-01');
$primerDiaMesAnterior = date('Y-m-01', strtotime('-1 month'));

$queryFbMes = mysqli_query($conn, "SELECT COUNT(*) as cant FROM feedbacks WHERE ticket_id IS NOT NULL AND fecha >= '$primerDiaMes' AND estado != 'inactivo'");
$rowFbMes = mysqli_fetch_assoc($queryFbMes);
$feedbackCierreCount = $rowFbMes['cant'] ?? 0;

$queryTktMes = mysqli_query($conn, "SELECT COUNT(*) as cant FROM tickets WHERE estado = 'cerrado' AND fecha_cierre >= '$primerDiaMes'");
$rowTktMes = mysqli_fetch_assoc($queryTktMes);
$ticketsCerradosCount = $rowTktMes['cant'] ?? 0;

$tasaFeedbackAlCierre = $ticketsCerradosCount > 0 ? round(($feedbackCierreCount / $ticketsCerradosCount) * 100) : 0;

$queryFbMesPrev = mysqli_query($conn, "SELECT COUNT(*) as cant FROM feedbacks WHERE ticket_id IS NOT NULL AND fecha >= '$primerDiaMesAnterior' AND fecha < '$primerDiaMes' AND estado != 'inactivo'");
$rowFbMesPrev = mysqli_fetch_assoc($queryFbMesPrev);
$feedbackCierrePrevCount = $rowFbMesPrev['cant'] ?? 0;
$queryTktMesPrev = mysqli_query($conn, "SELECT COUNT(*) as cant FROM tickets WHERE estado = 'cerrado' AND fecha_cierre >= '$primerDiaMesAnterior' AND fecha_cierre < '$primerDiaMes'");
$rowTktMesPrev = mysqli_fetch_assoc($queryTktMesPrev);
$ticketsCerradosPrevCount = $rowTktMesPrev['cant'] ?? 0;

$tasaFeedbackAlCierreMesAnterior = $ticketsCerradosPrevCount > 0 ? round(($feedbackCierrePrevCount / $ticketsCerradosPrevCount) * 100) : 0;

$tasaChange = $tasaFeedbackAlCierre - $tasaFeedbackAlCierreMesAnterior;

$tiposData = [
    'Sugerencia' => 0,
    'Queja' => 0,
    'Elogio' => 0
];
$sentimientosData = [
    'Positivo' => 0,
    'Negativo' => 0
];

$totalActivos = 0;
foreach ($feedbacks as $fb) {
    $totalActivos++;
    $tipo = ucfirst(strtolower(trim($fb['tipo'] ?? '')));
    if (array_key_exists($tipo, $tiposData)) {
        $tiposData[$tipo]++;
    }
    $sentimiento = ucfirst(strtolower(trim($fb['sentimiento'] ?? '')));
    if (array_key_exists($sentimiento, $sentimientosData)) {
        $sentimientosData[$sentimiento]++;
    }
}

$tiposColors = [
    'Sugerencia' => '#06b6d4',  
    'Queja' => '#ef4444',       
    'Elogio' => '#10b981'       
];

$sentimientosColors = [
    'Positivo' => '#10b981',
    'Negativo' => '#ef4444'    
];

if (!function_exists('generateSVGPieChart')) {
function generateSVGPieChart($data, $colors, $chartId) {
    $total = array_sum($data);
    if ($total == 0) {
        return '<svg width="240" height="240" viewBox="0 0 200 200" style="max-width: 240px; width: 100%; height: 240px; display: block; margin: 0 auto;">' .
               '<circle cx="100" cy="100" r="60" fill="none" stroke="#1e293b" stroke-width="36" style="opacity: 0.5;" />' .
               '<text x="100" y="105" fill="#64748b" text-anchor="middle" dy="0.36em" style="font-family: system-ui, -apple-system, sans-serif; font-size: 13px; font-weight: 600;">Sin Datos</text>' .
               '</svg>';
    }
    
    $circumference = 376.99;
    $offset = 0;
    
    $slicesHtml = '<g transform="rotate(-90 100 100)">';
    
    foreach ($data as $key => $count) {
        if ($count == 0) continue;
        $percentage = ($count / $total) * 100;
        $dashSize = ($percentage / 100) * $circumference;
        $color = $colors[$key] ?? '#64748b';
        $roundedPercentage = round($percentage);
        
        $slicesHtml .= '<circle cx="100" cy="100" r="60" class="pie-slice" ' .
                       'style="stroke: ' . $color . '; stroke-width: 36px; fill: none; stroke-dasharray: ' . $dashSize . ' ' . $circumference . '; stroke-dashoffset: -' . $offset . '; transition: stroke-dasharray 0.3s ease, stroke-width 0.2s ease, opacity 0.2s ease; opacity: 0.85;" ' .
                       'onmouseover="updateChartCenter(\'' . $chartId . '\', \'' . htmlspecialchars($key) . '\', \'' . $roundedPercentage . '%\', \'' . $color . '\')" ' .
                       'onmouseout="resetChartCenter(\'' . $chartId . '\', \'Total\', \'' . $total . '\')" />';
        
        $offset += $dashSize;
    }
    
    $slicesHtml .= '</g>';
    
    $labelsHtml = '<text id="lbl-title-' . $chartId . '" x="100" y="92" fill="#94a3b8" text-anchor="middle" style="font-family: system-ui, -apple-system, sans-serif; font-size: 13px; font-weight: 500; pointer-events: none; transition: fill 0.2s ease;">Total</text>';
    $labelsHtml .= '<text id="lbl-value-' . $chartId . '" x="100" y="118" fill="#ffffff" text-anchor="middle" style="font-family: system-ui, -apple-system, sans-serif; font-size: 20px; font-weight: 700; pointer-events: none;">' . $total . '</text>';
    
    $html = '<svg width="240" height="240" viewBox="0 0 200 200" style="max-width: 240px; width: 100%; height: 240px; display: block; margin: 0 auto;">';
    $html .= $slicesHtml;
    $html .= $labelsHtml;
    $html .= '</svg>';
    
    return $html;
}
}

?>

<body class="bg-slate-950 text-white overflow-hidden">
  <div id="root">
    <div class="h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 overflow-hidden">
      <!-- Background Glows -->
      <div class="absolute inset-0 opacity-5 pointer-events-none">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 2px 2px, rgb(16, 185, 129) 1px, transparent 0px); background-size: 40px 40px;"></div>
      </div>

      <div class="relative h-full flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900/90 backdrop-blur-sm border-r border-emerald-900/30 transition-all duration-300 flex flex-col">
          <?php require_once("templates\logo.php") ?>
          </div>
        </div>

        <!-- Badge -->
        <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-3">
          <span class="flex w-full items-center justify-center rounded-md <?php echo $sidebarBadgeClass ?? 'bg-slate-700'; ?> px-2 py-1 text-xs font-medium text-white">
            <?php echo $sidebarBadgeText ?? $_SESSION["currentTeam"]; ?>
          </span>
        </div>

        <!-- Navigation -->
        <?php require_once("templates\sidebar.php") ?>

        <!-- Footer -->
        <div class="p-4 border-t border-slate-800">
          <button class="w-full flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 rounded-md py-2 transition-all">
            ✕
          </button>
        </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
          <!-- Header -->
          <?php require_once("templates\header.php") ?>

          <!-- Main Scrollable -->
          <main class="flex-1 overflow-auto p-8">
            <div class="space-y-6">
              
              <!-- Title Bar -->
              <div class="flex items-center justify-between">
                <div>
                  <h1 class="text-white text-2xl font-bold tracking-tight">
                    Gestión de Evaluaciones de Calidad
                  </h1>
                  <p class="text-slate-400 text-sm mt-1">
                    Gestión integral de la percepción del cliente, auditorías internas y matriz CAPA
                  </p>
                </div>
              </div>

              <!-- Navigation Tabs Bar -->
              <div class="w-full border-b border-slate-800 mt-2">
                <div class="w-full flex items-center gap-2" id="tabs-container">
                  <button
                    type="button"
                    id="tab-btn-csat"
                    onclick="selectTab('csat')"
                    class="flex-1 flex items-center justify-center gap-2.5 py-3.5 px-6 border-b-2 border-emerald-500 font-semibold text-emerald-400 bg-slate-800/60 rounded-t-xl transition-all cursor-pointer text-sm"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span>CSAT & Voz del Cliente</span>
                  </button>

                  <button
                    type="button"
                    id="tab-btn-qa"
                    onclick="selectTab('qa')"
                    class="flex-1 flex items-center justify-center gap-2.5 py-3.5 px-6 border-b-2 border-transparent font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/30 rounded-t-xl transition-all cursor-pointer text-sm"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Auditorías de Calidad</span>
                  </button>

                  <button
                    type="button"
                    id="tab-btn-capa"
                    onclick="selectTab('capa')"
                    class="flex-1 flex items-center justify-center gap-2.5 py-3.5 px-6 border-b-2 border-transparent font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/30 rounded-t-xl transition-all cursor-pointer text-sm"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Matriz CAPA y Coaching</span>
                  </button>

                  <button
                    type="button"
                    id="tab-btn-cruzada"
                    onclick="selectTab('cruzada')"
                    class="flex-1 flex items-center justify-center gap-2.5 py-3.5 px-6 border-b-2 border-transparent font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/30 rounded-t-xl transition-all cursor-pointer text-sm"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Matriz Calidad Cruzada</span>
                  </button>
                </div>
              </div>

              <!-- ========================================== -->
              <!-- PESTAÑA 1: CSAT & VOZ DEL CLIENTE          -->
              <!-- ========================================== -->
              <div id="tab-content-csat" class="tab-content space-y-6">
                
                <!-- CSAT Top Actions -->
                <div class="flex items-center justify-end">
                  <div class="contenedor-acciones-top">
                    <?php if (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador")): ?>
                      <a
                        href="funcs/exportar-feedback-csv.php?<?php 
                          echo http_build_query([
                              'inputBuscar' => $_POST['inputBuscar'] ?? '',
                              'tipoBuscar' => $_POST['tipoBuscar'] ?? '',
                              'sentimientoBuscar' => $_POST['sentimientoBuscar'] ?? ''
                          ]); ?>"
                        class="btn-exportar"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                          <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a.5.5 0 0 1-.5-.5zM5.884 6.68 8 9.5l2.116-2.82a.25.25 0 0 1 .416.148l.49 1.956.452-1.8c.045-.18.2-.314.385-.314h.342a.25.25 0 0 1 .23.344l-.8 2a.25.25 0 0 1-.456-.01L10.3 8.3l-.364 1.455a.25.25 0 0 1-.482.01L7.26 6.81a.25.25 0 0 0-.46.024l-.534 2.136a.25.25 0 0 1-.485-.012L5.3 7.8l-.3 1.2a.25.25 0 0 1-.485-.012l-.8-3a.25.25 0 0 1 .37-.267h.342c.18 0 .338.125.388.3l.423 1.692.42-1.68a.25.25 0 0 1 .425-.136z"/>
                        </svg>
                        <span>Exportar Excel</span>
                      </a>
                    <?php endif; ?>

                    <form method="POST">
                      <input type="hidden" name="active_tab" value="csat" />
                      <button
                        type="submit"
                        name="FormFeedback"
                        class="btn-registrar"
                        value="1"
                      >
                        + Registrar Opinión
                      </button>
                    </form>
                  </div>
                </div>

                <!-- Panel Ciclo de Vida de Calidad (HU-44) -->
                <div class="sla-panel">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #334155 !important;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <span style="font-size: 15px;">📊</span>
                      <h2 style="color: #ffffff !important; font-size: 14px; font-weight: 700; margin: 0; letter-spacing: 0.015em;">Ciclo de Vida de Calidad &amp; Encuestas (CSAT)</h2>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <span style="display: inline-flex; align-items: center; gap: 6px; background-color: rgba(16, 185, 129, 0.15) !important; color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; padding: 2px 8px !important; border-radius: 9999px !important; font-size: 11px !important; font-weight: 600 !important;">
                        <span style="display: inline-block; width: 6px; height: 6px; border-radius: 9999px; background-color: #34d399; box-shadow: 0 0 6px #34d399;"></span> Tiempo Real
                      </span>
                      <form method="POST" class="inline-block m-0 p-0">
                        <input type="hidden" name="active_tab" value="csat" />
                        <button
                          type="submit"
                          name="sincronizar_calidad_sla"
                          style="display: inline-flex; align-items: center; gap: 5px; background-color: #1e293b !important; color: #cbd5e1 !important; border: 1px solid #475569 !important; padding: 3px 10px !important; border-radius: 6px !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.2s;"
                          onmouseover="this.style.backgroundColor='#334155'; this.style.color='#ffffff';"
                          onmouseout="this.style.backgroundColor='#1e293b'; this.style.color='#cbd5e1';"
                          title="Sincronizar vencimientos de plazos de 48h y calcular métricas de SLA para encuestas completadas"
                        >
                          <span>🔄</span> Sincronizar
                        </button>
                      </form>
                    </div>
                  </div>

                  <div class="calidad-grid">
                    <!-- 1. Total Evaluaciones -->
                    <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #3b82f6 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="color: #93c5fd !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                          <span>📋</span> Total
                        </h3>
                        <span style="background-color: rgba(59, 130, 246, 0.2) !important; color: #bfdbfe !important; border: 1px solid rgba(59, 130, 246, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Global</span>
                      </div>
                      <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                        <?php echo intval($stats['total']); ?>
                      </div>
                      <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">
                        Módulo de Calidad
                      </div>
                    </div>

                    <!-- 2. Pendiente Envío -->
                    <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #f59e0b !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="color: #fcd34d !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                          <span>⏳</span> Pendiente Envío
                        </h3>
                        <span style="background-color: rgba(245, 158, 11, 0.2) !important; color: #fde68a !important; border: 1px solid rgba(245, 158, 11, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Por Despachar</span>
                      </div>
                      <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                        <?php echo intval($stats['pendiente_envio']); ?>
                      </div>
                      <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">
                        Esperando despacho
                      </div>
                    </div>

                    <!-- 3. Enviadas -->
                    <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #0ea5e9 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="color: #7dd3fc !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                          <span>📨</span> Enviadas
                        </h3>
                        <span style="background-color: rgba(14, 165, 233, 0.2) !important; color: #bae6fd !important; border: 1px solid rgba(14, 165, 233, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">En Espera</span>
                      </div>
                      <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                        <?php echo intval($stats['enviadas']); ?>
                      </div>
                      <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">
                        En espera del cliente
                      </div>
                    </div>

                    <!-- 4. Completadas -->
                    <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #10b981 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="color: #6ee7b7 !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                          <span>⭐</span> Completadas
                        </h3>
                        <span style="background-color: rgba(16, 185, 129, 0.2) !important; color: #a7f3d0 !important; border: 1px solid rgba(16, 185, 129, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Respondidas</span>
                      </div>
                      <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                        <?php echo intval($stats['completadas']); ?>
                      </div>
                      <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">
                        Respondidas (CSAT)
                      </div>
                    </div>

                    <!-- 5. Procesadas -->
                    <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #8b5cf6 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="color: #c4b5fd !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                          <span>⚡</span> Procesadas
                        </h3>
                        <span style="background-color: rgba(139, 92, 246, 0.2) !important; color: #ddd6fe !important; border: 1px solid rgba(139, 92, 246, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">SLA OK: <?php echo intval($stats['sla_cumplido_total']); ?></span>
                      </div>
                      <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                        <?php echo intval($stats['procesadas']); ?>
                      </div>
                      <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">
                        Métricas calculadas
                      </div>
                    </div>

                    <!-- 6. Canceladas / Expiradas -->
                    <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #ef4444 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                      <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="color: #f87171 !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                          <span>🚫</span> Canceladas / Exp.
                        </h3>
                        <span style="background-color: rgba(239, 68, 68, 0.2) !important; color: #fca5a5 !important; border: 1px solid rgba(239, 68, 68, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Cerradas</span>
                      </div>
                      <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                        <?php echo intval($stats['canceladas'] + $stats['expiradas']); ?>
                      </div>
                      <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">
                        Canc: <?php echo intval($stats['canceladas']); ?> | Exp: <?php echo intval($stats['expiradas']); ?>
                      </div>
                    </div>
                  </div>
                </div>

                <?php if (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador")) { ?>

                <!-- Graphs Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <!-- Card Feedback por Tipo -->
                  <div class="rounded-xl border border-slate-700 bg-slate-900/40 p-6 flex flex-col items-center">
                    <h3 class="text-white text-base font-semibold mb-4 self-start">Opiniones por Tipo</h3>
                    <div class="w-full flex flex-col items-center justify-center py-2">
                      <div class="relative h-56 w-full flex justify-center items-center">
                        <?php echo generateSVGPieChart($tiposData, $tiposColors, 'tipo'); ?>
                      </div>
                      <div class="mt-4 flex flex-row flex-wrap justify-center items-center text-xs" style="gap: 24px;">
                        <?php 
                        $totalTipos = array_sum($tiposData);
                        foreach ($tiposData as $key => $count): 
                            $color = $tiposColors[$key] ?? '#64748b';
                            $percentage = $totalTipos > 0 ? round(($count / $totalTipos) * 100) : 0;
                        ?>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block" style="width: 12px; height: 12px; border-radius: 3px; background-color: <?php echo $color; ?>;"></span>
                                <span class="text-slate-300" style="font-weight: 600;"><?php echo htmlspecialchars($key); ?>:</span>
                                <span class="text-white" style="font-weight: 700;"><?php echo $percentage; ?>%</span>
                                <span style="color: #8a8d93; font-size: 0.9em; font-weight: 400;">(<?php echo $count; ?>)</span>
                            </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>

                  <!-- Card Análisis de Sentimiento -->
                  <div class="rounded-xl border border-slate-700 bg-slate-900/40 p-6 flex flex-col items-center">
                    <h3 class="text-white text-base font-semibold mb-4 self-start">Análisis de Sentimiento</h3>
                    <div class="w-full flex flex-col items-center justify-center py-2">
                      <div class="relative h-56 w-full flex justify-center items-center">
                        <?php echo generateSVGPieChart($sentimientosData, $sentimientosColors, 'sentimiento'); ?>
                      </div>
                      <div class="mt-4 flex flex-row flex-wrap justify-center items-center text-xs" style="gap: 24px;">
                        <?php 
                        $totalSentimientos = array_sum($sentimientosData);
                        foreach ($sentimientosData as $key => $count): 
                            $color = $sentimientosColors[$key] ?? '#64748b';
                            $percentage = $totalSentimientos > 0 ? round(($count / $totalSentimientos) * 100) : 0;
                        ?>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block" style="width: 12px; height: 12px; border-radius: 3px; background-color: <?php echo $color; ?>;"></span>
                                <span class="text-slate-300" style="font-weight: 600;"><?php echo htmlspecialchars($key); ?>:</span>
                                <span class="text-white" style="font-weight: 700;"><?php echo $percentage; ?>%</span>
                                <span style="color: #8a8d93; font-size: 0.9em; font-weight: 400;">(<?php echo $count; ?>)</span>
                            </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>
                <?php } ?>

                <!-- Filter and Search -->
                <form method="POST" action="feedback.php" data-no-persist="true">
                  <input type="hidden" name="active_tab" value="csat" />
                  <div class="w-full p-6 border border-slate-700 rounded-lg bg-slate-900/40">
                    <div style="display: flex; flex-direction: row; flex-wrap: wrap; gap: 1rem; align-items: center; width: 100%;">
                      
                      <!-- Search Input -->
                      <div style="flex: 1 1 200px; min-width: 0;">
                        <input
                          type="text"
                          name="inputBuscar"
                          value="<?php echo htmlspecialchars($_POST["inputBuscar"] ?? ""); ?>"
                          placeholder="Buscar opiniones..."
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          style="width: 100%;"
                        />
                      </div>

                      <!-- Filter 1: Tipo -->
                      <div style="flex: 0 0 180px; width: 180px; max-width: 100%;">
                        <select
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm"
                          name="tipoBuscar"
                          onchange="this.form.submit()"
                          style="width: 100%;"
                        >
                          <option value="todos" <?php echo (($_POST["tipoBuscar"] ?? "todos") === "todos") ? "selected" : ""; ?>>Todos los Tipos</option>
                          <option value="Sugerencia" <?php echo (($_POST["tipoBuscar"] ?? "") === "Sugerencia") ? "selected" : ""; ?>>Sugerencia</option>
                          <option value="Queja" <?php echo (($_POST["tipoBuscar"] ?? "") === "Queja") ? "selected" : ""; ?>>Queja</option>
                          <option value="Elogio" <?php echo (($_POST["tipoBuscar"] ?? "") === "Elogio") ? "selected" : ""; ?>>Elogio</option>
                        </select>
                      </div>

                      <!-- Filter 2: Sentimiento -->
                      <div style="flex: 0 0 180px; width: 180px; max-width: 100%;">
                        <select
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm"
                          name="sentimientoBuscar"
                          onchange="this.form.submit()"
                          style="width: 100%;"
                        >
                          <option value="todos" <?php echo (($_POST["sentimientoBuscar"] ?? "todos") === "todos") ? "selected" : ""; ?>>Todos los Sentimientos</option>
                          <option value="Positivo" <?php echo (($_POST["sentimientoBuscar"] ?? "") === "Positivo") ? "selected" : ""; ?>>Positivo</option>
                          <option value="Negativo" <?php echo (($_POST["sentimientoBuscar"] ?? "") === "Negativo") ? "selected" : ""; ?>>Negativo</option>
                        </select>
                      </div>

                      <!-- Filter 3: Estado (HU-44) -->
                      <div style="flex: 0 0 180px; width: 180px; max-width: 100%;">
                        <select
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm"
                          name="estadoBuscar"
                          onchange="this.form.submit()"
                          style="width: 100%;"
                        >
                          <option value="todos" <?php echo (($_POST["estadoBuscar"] ?? "todos") === "todos") ? "selected" : ""; ?>>Todos los Estados</option>
                          <option value="PendienteEnvio" <?php echo (($_POST["estadoBuscar"] ?? "") === "PendienteEnvio") ? "selected" : ""; ?>>PendienteEnvio</option>
                          <option value="Enviada" <?php echo (($_POST["estadoBuscar"] ?? "") === "Enviada") ? "selected" : ""; ?>>Enviada</option>
                          <option value="Completada" <?php echo (($_POST["estadoBuscar"] ?? "") === "Completada") ? "selected" : ""; ?>>Completada</option>
                          <option value="Procesada" <?php echo (($_POST["estadoBuscar"] ?? "") === "Procesada") ? "selected" : ""; ?>>Procesada</option>
                          <option value="Cancelada" <?php echo (($_POST["estadoBuscar"] ?? "") === "Cancelada") ? "selected" : ""; ?>>Cancelada</option>
                          <option value="Expirada" <?php echo (($_POST["estadoBuscar"] ?? "") === "Expirada") ? "selected" : ""; ?>>Expirada</option>
                        </select>
                      </div>
                    </div>

                    <div class="flex justify-between items-center mt-4">
                      <div class="flex gap-2">
                        <button
                          type="submit"
                          name="buscar"
                          value="buscar"
                          class="rounded-lg px-4 py-2 bg-slate-800 text-slate-200 transition hover:bg-slate-700 text-sm font-medium"
                        >
                          Buscar
                        </button>

                        <?php 
                        $filtroActivo = !empty($_POST["inputBuscar"]) || 
                                        (isset($_POST["tipoBuscar"]) && $_POST["tipoBuscar"] !== "todos") || 
                                        (isset($_POST["sentimientoBuscar"]) && $_POST["sentimientoBuscar"] !== "todos") ||
                                        (isset($_POST["estadoBuscar"]) && $_POST["estadoBuscar"] !== "todos");
                        if ($filtroActivo): 
                        ?>
                          <a
                            href="feedback.php?tab=csat"
                            class="rounded-lg px-4 py-2 border border-slate-700 text-slate-400 hover:text-white hover:bg-slate-800 text-sm flex items-center transition-all"
                          >
                            Limpiar
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </form>

                <!-- Data Grid / Table -->
                <div class="w-full mt-6 overflow-x-auto rounded-lg border border-slate-700">
                  <table class="w-full min-w-[800px] text-sm">
                    <thead>
                      <tr class="border-b border-slate-700 text-slate-300 bg-slate-900/60">
                        <th class="text-left p-3 whitespace-nowrap">ID</th>
                        <th class="text-left p-3 whitespace-nowrap">Usuario</th>
                        <th class="text-left p-3 whitespace-nowrap">Tipo</th>
                        <th class="text-left p-3 whitespace-nowrap">Tema</th>
                        <th class="text-left p-3 whitespace-nowrap">Sentimiento</th>
                        <th class="text-left p-3 whitespace-nowrap">Estado</th>
                        <th class="text-center p-3 whitespace-nowrap">Acciones</th>
                      </tr>
                    </thead>

                    <tbody class="text-white">
                      <?php 
                      if (!empty($feedbacks)) {
                        echo renderizarFilasTabla($campos, $feedbacks);
                      }
                      ?>
                    </tbody>
                  </table>
                </div>

              </div>

              <!-- ========================================== -->
              <!-- PESTAÑA 2: AUDITORÍAS DE CALIDAD (QA)      -->
              <!-- ========================================== -->
              <div id="tab-content-qa" class="tab-content space-y-6 hidden">
                
                <!-- QA Top Actions -->
                <div class="flex items-center justify-between">
                  <div>
                    <h3 class="text-lg font-semibold text-white">Evaluaciones de Auditoría Técnica Interna</h3>
                    <p class="text-xs text-slate-400">Revisión de estándares de soporte y cumplimiento de procedimientos</p>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="active_tab" value="qa" />
                    <button type="submit" name="FormQA" class="btn-registrar" value="1">
                      + Nueva Auditoría QA
                    </button>
                  </form>
                </div>

                <!-- QA Numerical Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">Score Promedio QA</h4>
                      <div class="text-3xl font-bold text-emerald-400 mt-2"><?php echo $qaStats['promedio_score']; ?>%</div>
                    </div>
                    <div class="p-2 bg-emerald-950/20 rounded-lg text-emerald-400">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                  </div>

                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">Auditorías Realizadas</h4>
                      <div class="text-3xl font-bold text-cyan-400 mt-2"><?php echo $qaStats['total']; ?></div>
                    </div>
                    <div class="p-2 bg-cyan-950/20 rounded-lg text-cyan-400">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                      </svg>
                    </div>
                  </div>

                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">Tasa de Conformidad</h4>
                      <div class="text-3xl font-bold text-cyan-400 mt-2"><?php echo $qaStats['tasa_conformidad']; ?>%</div>
                    </div>
                    <div class="p-2 bg-cyan-950/20 rounded-lg text-cyan-400">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                      </svg>
                    </div>
                  </div>

                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">Requieren Mejora</h4>
                      <div class="text-3xl font-bold text-amber-500 mt-2"><?php echo ($qaStats['mejora'] + $qaStats['no_conformes']); ?></div>
                    </div>
                    <div class="p-2 bg-amber-950/20 rounded-lg text-amber-500">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- QA Filter & Search -->
                <form method="POST" action="feedback.php" data-no-persist="true">
                  <input type="hidden" name="active_tab" value="qa" />
                  <div class="w-full p-6 border border-slate-700 rounded-lg bg-slate-900/40">
                    <div style="display: flex; flex-direction: row; flex-wrap: wrap; gap: 1rem; align-items: center; width: 100%;">
                      <div style="flex: 1 1 200px; min-width: 0;">
                        <input
                          type="text"
                          name="inputBuscarQA"
                          value="<?php echo htmlspecialchars($_POST["inputBuscarQA"] ?? ""); ?>"
                          placeholder="Buscar auditorías..."
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          style="width: 100%;"
                        />
                      </div>

                      <div style="flex: 0 0 220px; width: 220px; max-width: 100%;">
                        <select
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          name="resultadoBuscarQA"
                          onchange="this.form.submit()"
                          style="width: 100%;"
                        >
                          <option value="todos" <?php echo (($_POST["resultadoBuscarQA"] ?? "todos") === "todos") ? "selected" : ""; ?>>Todos los Resultados</option>
                          <option value="Conforme" <?php echo (($_POST["resultadoBuscarQA"] ?? "") === "Conforme") ? "selected" : ""; ?>>Conforme</option>
                          <option value="Requiere Mejora" <?php echo (($_POST["resultadoBuscarQA"] ?? "") === "Requiere Mejora") ? "selected" : ""; ?>>Requiere Mejora</option>
                          <option value="No Conforme" <?php echo (($_POST["resultadoBuscarQA"] ?? "") === "No Conforme") ? "selected" : ""; ?>>No Conforme</option>
                          <option value="Auto-Fail" <?php echo (($_POST["resultadoBuscarQA"] ?? "") === "Auto-Fail") ? "selected" : ""; ?>>Auto-Fail (Error Crítico)</option>
                        </select>
                      </div>
                    </div>

                    <div class="flex justify-between items-center mt-4">
                      <div class="flex gap-2">
                        <button
                          type="submit"
                          name="buscarQA"
                          value="buscar"
                          class="rounded-lg px-4 py-2 bg-slate-800 text-slate-200 transition hover:bg-slate-700 text-sm font-medium"
                        >
                          Buscar
                        </button>
                        <?php if (!empty($_POST["inputBuscarQA"]) || (isset($_POST["resultadoBuscarQA"]) && $_POST["resultadoBuscarQA"] !== "todos")): ?>
                          <a
                            href="feedback.php?tab=qa"
                            class="rounded-lg px-4 py-2 border border-slate-700 text-slate-400 hover:text-white hover:bg-slate-800 text-sm flex items-center transition-all"
                          >
                            Limpiar
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </form>

                <!-- QA Data Grid / Table -->
                <div class="w-full mt-6 overflow-x-auto rounded-lg border border-slate-700">
                  <table class="w-full min-w-[850px] text-sm">
                    <thead>
                      <tr class="border-b border-slate-700 text-slate-300 bg-slate-900/60">
                        <th class="text-left p-3 whitespace-nowrap">ID</th>
                        <th class="text-left p-3 whitespace-nowrap">Ticket Evaluado</th>
                        <th class="text-left p-3 whitespace-nowrap">Auditor</th>
                        <th class="text-left p-3 whitespace-nowrap">Evaluado</th>
                        <th class="text-left p-3 whitespace-nowrap">Desglose Rúbrica (Diag 30% | Proc 30% | Red 20% | Efic 20%)</th>
                        <th class="text-left p-3 whitespace-nowrap">Puntaje Total</th>
                        <th class="text-left p-3 whitespace-nowrap">Resultado</th>
                        <th class="text-left p-3 whitespace-nowrap">Fecha</th>
                        <th class="text-center p-3 whitespace-nowrap">Acciones</th>
                      </tr>
                    </thead>
                    <tbody class="text-white">
                      <?php if (!empty($qaAuditorias)): ?>
                        <?php foreach ($qaAuditorias as $qa): ?>
                          <?php 
                          $isAutoFail = (!empty($qa["error_critico"]) || $qa["resultado"] === "Auto-Fail");
                          $resBadgeClass = "badge-custom badge-default";
                          $resultadoText = htmlspecialchars($qa["resultado"]);

                          if ($isAutoFail) {
                              $resBadgeClass = "badge-custom badge-negativo bg-rose-950/90 text-rose-300 border-rose-600 font-bold animate-pulse";
                              $resultadoText = "🚨 Auto-Fail";
                          } else if ($qa["resultado"] === "Conforme") {
                              $resBadgeClass = "badge-custom badge-positivo";
                          } else if ($qa["resultado"] === "Requiere Mejora") {
                              $resBadgeClass = "badge-custom badge-estado-revisado";
                          } else if ($qa["resultado"] === "No Conforme") {
                              $resBadgeClass = "badge-custom badge-negativo";
                          }
                          
                          $tktBadge = $qa["ticket_id"] ? 'TKT-' . str_pad($qa["ticket_id"], 3, '0', STR_PAD_LEFT) : 'General';
                          
                          $diagScore = intval($qa["score_diagnostico"] ?? 90);
                          $procScore = intval($qa["score_procesos"] ?? 90);
                          $redScore = intval($qa["score_redaccion"] ?? 90);
                          $eficScore = intval($qa["score_eficiencia"] ?? 90);

                          $rowStyle = $isAutoFail ? 'border-b border-rose-900/50 bg-rose-950/20 hover:bg-rose-950/30' : 'border-b border-slate-800 hover:bg-slate-800/40';
                          ?>
                          <tr class="<?php echo $rowStyle; ?>">
                            <td class="p-3 text-cyan-400 font-semibold">AUD-<?php echo str_pad($qa["id"], 3, '0', STR_PAD_LEFT); ?></td>
                            <td class="p-3 text-slate-300 font-medium"><?php echo htmlspecialchars($tktBadge); ?></td>
                            <td class="p-3 text-slate-200"><?php echo htmlspecialchars($qa["auditor"]); ?></td>
                            <td class="p-3 text-slate-300 font-medium"><?php echo htmlspecialchars($qa["agente_evaluado"]); ?></td>
                            <td class="p-3 text-xs font-mono text-slate-300">
                              <span class="text-cyan-400" title="Diagnóstico Técnico (30%)">Diag: <?php echo $diagScore; ?>%</span> | 
                              <span class="text-cyan-400" title="Adherencia a Procesos (30%)">Proc: <?php echo $procScore; ?>%</span> | 
                              <span class="text-cyan-400" title="Redacción y Tono (20%)">Red: <?php echo $redScore; ?>%</span> | 
                              <span class="text-cyan-400" title="Eficiencia (20%)">Efic: <?php echo $eficScore; ?>%</span>
                            </td>
                            <td class="p-3 font-bold text-base <?php echo $isAutoFail ? 'text-rose-500' : ($qa["puntaje"] >= 80 ? 'text-emerald-400' : ($qa["puntaje"] >= 60 ? 'text-amber-400' : 'text-rose-400')); ?>"><?php echo intval($qa["puntaje"]); ?>%</td>
                            <td class="p-3"><span class="<?php echo $resBadgeClass; ?>"><?php echo $resultadoText; ?></span></td>
                            <td class="p-3 text-slate-400 text-xs"><?php echo date('d/m/Y H:i', strtotime($qa["fecha"])); ?></td>
                            <td class="p-3 text-center">
                              <form method="POST" class="inline-block m-0 p-0">
                                <input type="hidden" name="active_tab" value="qa" />
                                <button type="submit" name="ver_detalle_qa" value="<?php echo $qa["id"]; ?>" class="btn-accion-icono icono-ver" title="Ver Detalle Rúbrica">
                                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                                </button>
                              </form>
                              <?php if ($qa["resultado"] !== "Conforme" || intval($qa["puntaje"]) < 80 || !empty($qa["error_critico"])): ?>
                                <form method="POST" class="inline-block m-0 p-0 ml-1">
                                  <input type="hidden" name="active_tab" value="capa" />
                                  <input type="hidden" name="FormCAPA" value="1" />
                                  <input type="hidden" name="preselected_evaluacion" value="<?php echo $qa["id"]; ?>" />
                                  <button type="submit" class="btn-accion-icono border border-amber-600/50 bg-amber-950/40 text-amber-400 hover:text-amber-300 hover:bg-amber-900/50" title="Crear Plan de Acción Correctivo (HU 34)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                  </button>
                                </form>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="9" class="p-6 text-center text-slate-500">No se encontraron auditorías registradas.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

              </div>

              <!-- ========================================== -->
              <!-- PESTAÑA 3: MATRIZ CAPA Y COACHING         -->
              <!-- ========================================== -->
              <div id="tab-content-capa" class="tab-content space-y-6 hidden">
                
                <!-- CAPA Top Actions -->
                <div class="flex items-center justify-between">
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">HU 34</span>
                      <h3 class="text-lg font-semibold text-white">Matriz CAPA y Planes de Acción Correctivos</h3>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Registro y seguimiento de planes de acción derivados de auditorías deficientes para la mejora continua</p>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="active_tab" value="capa" />
                    <button type="submit" name="FormCAPA" class="btn-registrar" value="1">
                      + Registrar Acción Correctiva / Plan CAPA
                    </button>
                  </form>
                </div>

                <!-- CAPA Numerical Cards: 4 Estados (Pendiente, En Proceso, Completado, Vencido) -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <!-- 1. Pendientes -->
                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">Acciones Pendientes</h4>
                      <div class="text-3xl font-bold text-amber-400 mt-2"><?php echo $capaStats['pendientes']; ?></div>
                      <span class="text-[11px] text-slate-400 mt-1 block">Esperando inicio de gestión</span>
                    </div>
                    <div class="p-2 bg-amber-950/30 rounded-lg text-amber-400 border border-amber-900/40">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                  </div>

                  <!-- 2. En Proceso -->
                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">En Proceso</h4>
                      <div class="text-3xl font-bold text-cyan-400 mt-2"><?php echo $capaStats['en_proceso']; ?></div>
                      <span class="text-[11px] text-slate-400 mt-1 block">Acciones en ejecución activa</span>
                    </div>
                    <div class="p-2 bg-cyan-950/30 rounded-lg text-cyan-400 border border-cyan-900/40">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                      </svg>
                    </div>
                  </div>

                  <!-- 3. Completados -->
                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">Completados</h4>
                      <div class="text-3xl font-bold text-emerald-400 mt-2"><?php echo $capaStats['completados']; ?></div>
                      <span class="text-[11px] text-emerald-400/80 mt-1 block"><?php echo $capaStats['tasa_cumplimiento']; ?>% de resolución total</span>
                    </div>
                    <div class="p-2 bg-emerald-950/30 rounded-lg text-emerald-400 border border-emerald-900/40">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                  </div>

                  <!-- 4. Vencidos -->
                  <div class="rounded-xl border border-slate-700 bg-slate-800/40 p-6 flex justify-between items-start <?php echo ($capaStats['vencidos'] > 0) ? 'border-rose-900/60 bg-rose-950/20' : ''; ?>">
                    <div>
                      <h4 class="text-sm font-medium text-slate-400">Vencidos</h4>
                      <div class="text-3xl font-bold text-rose-400 mt-2"><?php echo $capaStats['vencidos']; ?></div>
                      <span class="text-[11px] text-rose-400/80 mt-1 block">Acciones fuera de fecha límite</span>
                    </div>
                    <div class="p-2 bg-rose-950/40 rounded-lg text-rose-400 border border-rose-900/50">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- CAPA Filter & Search -->
                <form method="POST" action="feedback.php" data-no-persist="true">
                  <input type="hidden" name="active_tab" value="capa" />
                  <div class="w-full p-6 border border-slate-700 rounded-lg bg-slate-900/40">
                    <div style="display: flex; flex-direction: row; flex-wrap: wrap; gap: 1rem; align-items: center; width: 100%;">
                      <div style="flex: 1 1 200px; min-width: 0;">
                        <input
                          type="text"
                          name="inputBuscarCAPA"
                          value="<?php echo htmlspecialchars($_POST["inputBuscarCAPA"] ?? ""); ?>"
                          placeholder="Buscar por ID, causa raíz, acción propuesta, agente responsable o ID de evaluación..."
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          style="width: 100%;"
                        />
                      </div>

                      <div style="flex: 0 0 200px; width: 200px; max-width: 100%;">
                        <select
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm"
                          name="tipoBuscarCAPA"
                          onchange="this.form.submit()"
                          style="width: 100%;"
                        >
                          <option value="todos" <?php echo (($_POST["tipoBuscarCAPA"] ?? "todos") === "todos") ? "selected" : ""; ?>>Todos los Tipos</option>
                          <option value="Acción Correctiva" <?php echo (($_POST["tipoBuscarCAPA"] ?? "") === "Acción Correctiva") ? "selected" : ""; ?>>Acción Correctiva</option>
                          <option value="CAPA Preventiva" <?php echo (($_POST["tipoBuscarCAPA"] ?? "") === "CAPA Preventiva") ? "selected" : ""; ?>>CAPA Preventiva</option>
                          <option value="Sesión Coaching" <?php echo (($_POST["tipoBuscarCAPA"] ?? "") === "Sesión Coaching") ? "selected" : ""; ?>>Sesión Coaching</option>
                        </select>
                      </div>

                      <div style="flex: 0 0 200px; width: 200px; max-width: 100%;">
                        <select
                          class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm"
                          name="estadoBuscarCAPA"
                          onchange="this.form.submit()"
                          style="width: 100%;"
                        >
                          <option value="todos" <?php echo (($_POST["estadoBuscarCAPA"] ?? "todos") === "todos") ? "selected" : ""; ?>>Todos los Estados</option>
                          <option value="Pendiente" <?php echo (($_POST["estadoBuscarCAPA"] ?? "") === "Pendiente") ? "selected" : ""; ?>>⏳ Pendiente</option>
                          <option value="En Proceso" <?php echo (($_POST["estadoBuscarCAPA"] ?? "") === "En Proceso") ? "selected" : ""; ?>>⚙️ En Proceso</option>
                          <option value="Completado" <?php echo (($_POST["estadoBuscarCAPA"] ?? "") === "Completado") ? "selected" : ""; ?>>✅ Completado</option>
                          <option value="Vencido" <?php echo (($_POST["estadoBuscarCAPA"] ?? "") === "Vencido") ? "selected" : ""; ?>>⚠️ Vencido</option>
                        </select>
                      </div>
                    </div>

                    <div class="flex justify-between items-center mt-4">
                      <div class="flex gap-2">
                        <button type="submit" class="rounded-lg px-4 py-2 bg-slate-800 text-slate-200 transition hover:bg-slate-700 text-sm font-medium">
                          Buscar
                        </button>
                        <?php if (!empty($_POST["inputBuscarCAPA"]) || (isset($_POST["tipoBuscarCAPA"]) && $_POST["tipoBuscarCAPA"] !== "todos") || (isset($_POST["estadoBuscarCAPA"]) && $_POST["estadoBuscarCAPA"] !== "todos")): ?>
                          <a href="feedback.php?tab=capa" class="rounded-lg px-4 py-2 border border-slate-700 text-slate-400 hover:text-white hover:bg-slate-800 text-sm flex items-center transition-all">
                            Limpiar Filtros
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </form>

                <!-- CAPA Data Grid / Table (Criterio 3: Grilla con estados Pendiente, En Proceso, Completado o Vencido) -->
                <div class="w-full mt-6 overflow-x-auto rounded-lg border border-slate-700">
                  <table class="w-full min-w-[950px] text-sm">
                    <thead>
                      <tr class="border-b border-slate-700 text-slate-300 bg-slate-900/60">
                        <th class="text-left p-3 whitespace-nowrap">ID Plan</th>
                        <th class="text-left p-3 whitespace-nowrap">ID Evaluación</th>
                        <th class="text-left p-3 whitespace-nowrap">Tipo</th>
                        <th class="text-left p-3 whitespace-nowrap">Agente Responsable</th>
                        <th class="text-left p-3 whitespace-nowrap">Causa Raíz</th>
                        <th class="text-left p-3 whitespace-nowrap">Acción Propuesta</th>
                        <th class="text-left p-3 whitespace-nowrap">Fecha Límite</th>
                        <th class="text-left p-3 whitespace-nowrap">Estado</th>
                        <th class="text-center p-3 whitespace-nowrap">Acciones</th>
                      </tr>
                    </thead>
                    <tbody class="text-white">
                      <?php if (!empty($capaLista)): ?>
                        <?php foreach ($capaLista as $cp): ?>
                          <?php 
                          $isVencido = ($cp["estado"] === "Vencido" || ($cp["estado"] !== "Completado" && !empty($cp["fecha_limite"]) && strtotime($cp["fecha_limite"]) < strtotime('today')));
                          $effectiveEstado = $isVencido ? "Vencido" : $cp["estado"];

                          $tipoBadge = "badge-custom badge-default";
                          if ($cp["tipo"] === "Acción Correctiva" || $cp["tipo"] === "CAPA Correctiva") $tipoBadge = "badge-custom badge-queja";
                          else if ($cp["tipo"] === "CAPA Preventiva") $tipoBadge = "badge-custom badge-sugerencia";
                          else if ($cp["tipo"] === "Sesión Coaching") $tipoBadge = "badge-custom badge-elogio";

                          $estadoBadge = "badge-capa-pendiente";
                          if ($effectiveEstado === "En Proceso") $estadoBadge = "badge-capa-proceso";
                          else if ($effectiveEstado === "Completado") $estadoBadge = "badge-capa-completado";
                          else if ($effectiveEstado === "Vencido") $estadoBadge = "badge-capa-vencido";

                          $rowClass = ($effectiveEstado === "Vencido") ? "border-b border-rose-900/30 bg-rose-950/10 hover:bg-rose-950/20" : "border-b border-slate-800 hover:bg-slate-800/40";
                          ?>
                          <tr class="<?php echo $rowClass; ?>">
                            <!-- ID Plan -->
                            <td class="p-3 text-cyan-400 font-semibold font-mono">PLAN-<?php echo str_pad($cp["id"], 3, '0', STR_PAD_LEFT); ?></td>

                            <!-- ID Evaluación vinculada (Criterio 1) -->
                            <td class="p-3">
                              <?php if (!empty($cp["evaluacion_id"])): ?>
                                <div class="flex items-center gap-1.5">
                                  <span class="font-mono font-bold text-xs text-amber-300 bg-amber-950/40 px-2 py-0.5 rounded border border-amber-800/40">
                                    AUD-<?php echo str_pad($cp["evaluacion_id"], 3, '0', STR_PAD_LEFT); ?>
                                  </span>
                                  <?php if (isset($cp["eval_puntaje"])): ?>
                                    <span class="text-[11px] text-slate-400">(<?php echo intval($cp["eval_puntaje"]); ?>%)</span>
                                  <?php endif; ?>
                                </div>
                              <?php else: ?>
                                <span class="text-xs text-slate-500 italic">General</span>
                              <?php endif; ?>
                            </td>

                            <!-- Tipo -->
                            <td class="p-3"><span class="<?php echo $tipoBadge; ?>"><?php echo htmlspecialchars($cp["tipo"]); ?></span></td>

                            <!-- Agente Responsable (Criterio 2) -->
                            <td class="p-3 text-slate-200 font-medium whitespace-nowrap">
                              <?php echo htmlspecialchars($cp["agente_responsable"]); ?>
                            </td>

                            <!-- Causa Raíz (Criterio 2) -->
                            <td class="p-3 text-slate-300 max-w-[200px] truncate" title="<?php echo htmlspecialchars($cp["causa_raiz"]); ?>">
                              <?php echo htmlspecialchars($cp["causa_raiz"]); ?>
                            </td>

                            <!-- Acción Propuesta (Criterio 2) -->
                            <td class="p-3 text-slate-300 max-w-[220px] truncate" title="<?php echo htmlspecialchars($cp["accion_propuesta"]); ?>">
                              <?php echo htmlspecialchars($cp["accion_propuesta"]); ?>
                            </td>

                            <!-- Fecha Límite de Resolución (Criterio 2) -->
                            <td class="p-3 whitespace-nowrap">
                              <?php if ($effectiveEstado === "Vencido"): ?>
                                <span class="text-rose-400 font-bold text-xs font-mono flex items-center gap-1">
                                  ⚠️ <?php echo date('d/m/Y', strtotime($cp["fecha_limite"])); ?>
                                </span>
                              <?php else: ?>
                                <span class="text-slate-300 text-xs font-mono">
                                  <?php echo date('d/m/Y', strtotime($cp["fecha_limite"])); ?>
                                </span>
                              <?php endif; ?>
                            </td>

                            <!-- Estado (Criterio 3: Pendiente, En Proceso, Completado, Vencido) -->
                            <td class="p-3 whitespace-nowrap">
                              <span class="badge-custom <?php echo $estadoBadge; ?> mb-1">
                                <?php 
                                if ($effectiveEstado === "Pendiente") echo "⏳ Pendiente";
                                else if ($effectiveEstado === "En Proceso") echo "⚙️ En Proceso";
                                else if ($effectiveEstado === "Completado") echo "✅ Completado";
                                else if ($effectiveEstado === "Vencido") echo "⚠️ Vencido";
                                ?>
                              </span>
                              <!-- Selector rápido de cambio de estado -->
                              <form method="POST" class="block m-0 p-0">
                                <input type="hidden" name="active_tab" value="capa" />
                                <input type="hidden" name="capa_id" value="<?php echo $cp["id"]; ?>" />
                                <input type="hidden" name="cambiar_estado_capa" value="1" />
                                <select name="nuevo_estado" onchange="this.form.submit()" class="text-[11px] rounded bg-slate-900 border border-slate-700 text-slate-300 px-1 py-0.5 focus:ring-1 focus:ring-emerald-500" title="Cambiar estado de seguimiento">
                                  <option value="Pendiente" <?php echo ($effectiveEstado === "Pendiente") ? "selected" : ""; ?>>Pendiente</option>
                                  <option value="En Proceso" <?php echo ($effectiveEstado === "En Proceso") ? "selected" : ""; ?>>En Proceso</option>
                                  <option value="Completado" <?php echo ($effectiveEstado === "Completado") ? "selected" : ""; ?>>Completado</option>
                                  <option value="Vencido" <?php echo ($effectiveEstado === "Vencido") ? "selected" : ""; ?>>Vencido</option>
                                </select>
                              </form>
                            </td>

                            <!-- Acciones -->
                            <td class="p-3 text-center whitespace-nowrap">
                              <form method="POST" class="inline-block m-0 p-0">
                                <input type="hidden" name="active_tab" value="capa" />
                                <button type="submit" name="ver_detalle_capa" value="<?php echo $cp["id"]; ?>" class="btn-accion-icono icono-ver" title="Ver Detalle y Gestionar">
                                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                                </button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="9" class="p-6 text-center text-slate-500">No se encontraron planes de acción correctivos registrados.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

              </div>

              <!-- ========================================== -->
              <!-- PESTAÑA 4: MATRIZ DE CALIDAD CRUZADA (HU 35)-->
              <!-- ========================================== -->
              <div id="tab-content-cruzada" class="tab-content space-y-6 hidden">
                
                <!-- Cross Matrix Top Actions -->
                <div class="cruzada-header-card flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-3.5">
                    <div class="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shrink-0">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                      </svg>
                    </div>
                    <div>
                      <h2 class="text-base sm:text-lg font-bold text-white tracking-tight">Matriz de Calidad Cruzada &amp; Acuerdos de SLA</h2>
                      <p class="text-xs text-slate-400 mt-1 leading-normal">
                        Cruce estratégico: Satisfacción del Cliente (CSAT) vs. Auditoría Interna de Calidad (QA Score)
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap shrink-0">
                    <a
                      href="dashboard.php"
                      class="btn-exportar"
                    >
                      <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                      </svg>
                      <span>Ver en Panel Ejecutivo</span>
                    </a>
                    <a
                      href="funcs/exportar-dashboard.php"
                      class="btn-exportar"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a.5.5 0 0 1-.5-.5zM5.884 6.68 8 9.5l2.116-2.82a.25.25 0 0 1 .416.148l.49 1.956.452-1.8c.045-.18.2-.314.385-.314h.342a.25.25 0 0 1 .23.344l-.8 2a.25.25 0 0 1-.456-.01L10.3 8.3l-.364 1.455a.25.25 0 0 1-.482.01L7.26 6.81a.25.25 0 0 0-.46.024l-.534 2.136a.25.25 0 0 1-.485-.012L5.3 7.8l-.3 1.2a.25.25 0 0 1-.485-.012l-.8-3a.25.25 0 0 1 .37-.267h.342c.18 0 .338.125.388.3l.423 1.692.42-1.68a.25.25 0 0 1 .425-.136z"/>
                      </svg>
                      <span>Exportar Métricas</span>
                    </a>
                  </div>
                </div>

                <!-- Criterio 2: Tarjetas de KPIs Estratégicos -->
                <div class="cruzada-kpi-grid">
                  <!-- 1. % Promedio de QA Score -->
                  <div class="cruzada-card cruzada-card-qa">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/5 rounded-full blur-xl pointer-events-none"></div>
                    <div>
                      <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Calidad Interna</span>
                          <h3 class="text-sm sm:text-base font-bold text-white leading-tight">% Promedio de QA Score</h3>
                        </div>
                        <div class="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shrink-0">
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                          </svg>
                        </div>
                      </div>
                      <div class="mt-4 flex items-baseline justify-between gap-2 flex-wrap">
                        <div class="flex items-baseline gap-2">
                          <span class="text-3xl font-extrabold text-emerald-400 tracking-tight"><?= number_format($kpisCruzada['qa_promedio'], 1) ?>%</span>
                          <span class="text-xs text-slate-400 font-medium">/ 100%</span>
                        </div>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-950/70 text-emerald-300 border border-emerald-800 shrink-0 shadow-sm">
                          Meta SLA: ≥80%
                        </span>
                      </div>
                    </div>
                    <div class="mt-4 pt-3.5 border-t border-slate-800/80">
                      <div class="cruzada-progress-track">
                        <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-2 rounded-full transition-all duration-500" style="width: <?= min(100, max(0, $kpisCruzada['qa_promedio'])) ?>%"></div>
                      </div>
                      <div class="flex justify-between items-center mt-3 text-xs text-slate-400">
                        <span>Evaluaciones: <strong class="text-slate-200 font-semibold"><?= $kpisCruzada['total_qa'] ?> auditorías</strong></span>
                        <span class="text-emerald-400 font-semibold inline-flex items-center gap-1"><?= $kpisCruzada['qa_promedio'] >= 80 ? '✓ Cumple SLA' : '⚠️ Bajo objetivo' ?></span>
                      </div>
                    </div>
                  </div>

                  <!-- 2. % Tasa de Errores Críticos -->
                  <div class="cruzada-card cruzada-card-errores">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-red-500/5 rounded-full blur-xl pointer-events-none"></div>
                    <div>
                      <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Severidad &amp; Auto-Fail</span>
                          <h3 class="text-sm sm:text-base font-bold text-white leading-tight">% Tasa de Errores Críticos</h3>
                        </div>
                        <div class="p-2.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 shrink-0">
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                          </svg>
                        </div>
                      </div>
                      <div class="mt-4 flex items-baseline justify-between gap-2 flex-wrap">
                        <div class="flex items-baseline gap-2">
                          <span class="text-3xl font-extrabold tracking-tight <?= $kpisCruzada['tasa_errores_criticos'] > 0 ? 'text-red-400' : 'text-emerald-400' ?>">
                            <?= number_format($kpisCruzada['tasa_errores_criticos'], 1) ?>%
                          </span>
                          <span class="text-xs text-slate-400 font-medium">tasa global</span>
                        </div>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full shrink-0 shadow-sm <?= $kpisCruzada['tasa_errores_criticos'] == 0 ? 'bg-emerald-950/70 text-emerald-300 border border-emerald-800' : 'bg-red-950/70 text-red-300 border border-red-800' ?>">
                          Meta SLA: &lt;5%
                        </span>
                      </div>
                    </div>
                    <div class="mt-4 pt-3.5 border-t border-slate-800/80">
                      <div class="cruzada-progress-track">
                        <div class="<?= $kpisCruzada['tasa_errores_criticos'] > 0 ? 'bg-red-500' : 'bg-emerald-500' ?> h-2 rounded-full transition-all duration-500" style="width: <?= min(100, max(5, $kpisCruzada['tasa_errores_criticos'])) ?>%"></div>
                      </div>
                      <div class="flex justify-between items-center mt-3 text-xs text-slate-400">
                        <span>Casos críticos: <strong class="text-slate-200 font-semibold"><?= $kpisCruzada['total_errores_criticos'] ?></strong></span>
                        <span class="<?= $kpisCruzada['tasa_errores_criticos'] <= 5 ? 'text-emerald-400' : 'text-red-400' ?> font-semibold inline-flex items-center gap-1">
                          <?= $kpisCruzada['tasa_errores_criticos'] == 0 ? '✓ Cero tolerancia cumplida' : ($kpisCruzada['tasa_errores_criticos'] <= 5 ? '✓ En margen tolerable' : '🚨 Requiere CAPA urgente') ?>
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- 3. CSAT Promedio -->
                  <div class="cruzada-card cruzada-card-csat">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-cyan-500/5 rounded-full blur-xl pointer-events-none"></div>
                    <div>
                      <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Voz del Cliente</span>
                          <h3 class="text-sm sm:text-base font-bold text-white leading-tight">CSAT Promedio</h3>
                        </div>
                        <div class="p-2.5 rounded-lg bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 shrink-0">
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </div>
                      </div>
                      <div class="mt-4 flex items-baseline justify-between gap-2 flex-wrap">
                        <div class="flex items-baseline gap-2">
                          <span class="text-3xl font-extrabold text-cyan-400 tracking-tight"><?= number_format($kpisCruzada['csat_promedio'], 1) ?>%</span>
                          <span class="text-xs text-slate-400 font-medium">satisfacción</span>
                        </div>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-cyan-950/70 text-cyan-300 border border-cyan-800 shrink-0 shadow-sm">
                          Meta SLA: ≥85%
                        </span>
                      </div>
                    </div>
                    <div class="mt-4 pt-3.5 border-t border-slate-800/80">
                      <div class="cruzada-progress-track">
                        <div class="bg-gradient-to-r from-cyan-500 to-blue-500 h-2 rounded-full transition-all duration-500" style="width: <?= min(100, max(0, $kpisCruzada['csat_promedio'])) ?>%"></div>
                      </div>
                      <div class="flex justify-between items-center mt-3 text-xs text-slate-400">
                        <span>Respuestas: <strong class="text-slate-200 font-semibold"><?= $kpisCruzada['total_feedback'] ?> feedbacks</strong></span>
                        <span class="text-cyan-400 font-semibold inline-flex items-center gap-1"><?= $kpisCruzada['csat_promedio'] >= 85 ? '✓ Satisfacción óptima' : '⚠️ Atención a clientes' ?></span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Criterio 1: Gráfico Comparativo CSAT vs QA Score por Agente/Equipo & Desglose -->
                <div class="cruzada-columns-grid">
                  <!-- Gráfico Comparativo (7 cols) -->
                  <div class="cruzada-chart-card">
                    <div>
                      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 mb-4 border-b border-slate-800/80">
                        <div>
                          <h3 class="text-sm sm:text-base font-bold text-white tracking-tight flex items-center gap-2">
                            <span>Comparativa Cruzada: CSAT Cliente vs. QA Score Interno</span>
                          </h3>
                          <p class="text-xs text-slate-400 mt-1 leading-normal">
                            Cruce directo de la percepción externa contra el cumplimiento de la pauta operativa interna.
                          </p>
                        </div>
                        <span class="self-start sm:self-center text-xs text-slate-300 bg-slate-800/90 px-2.5 py-1 rounded-md border border-slate-700 font-medium whitespace-nowrap shadow-sm">
                          Por Agente / Equipo
                        </span>
                      </div>
                      <div class="cruzada-chart-container my-2">
                        <canvas id="crossQualityChartFeedback"></canvas>
                      </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 mt-4 pt-3.5 border-t border-slate-800/80 text-xs text-slate-300">
                      <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-sm bg-cyan-400 inline-block shadow-sm"></span>
                        <span class="font-medium text-slate-200">CSAT del Cliente (%)</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 rounded-sm bg-emerald-400 inline-block shadow-sm"></span>
                        <span class="font-medium text-slate-200">QA Score Interno (%)</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="w-4 h-0.5 border-t-2 border-dashed border-amber-400 inline-block"></span>
                        <span class="text-amber-300 font-medium">Meta SLA (80%)</span>
                      </div>
                    </div>
                  </div>

                  <!-- Matriz & Brecha de Percepción por Agente (5 cols) -->
                  <div class="cruzada-table-card">
                    <div>
                      <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800/80">
                        <div>
                          <h3 class="text-sm sm:text-base font-bold text-white tracking-tight">Desglose de Desempeño &amp; Brecha SLA</h3>
                          <p class="text-xs text-slate-400 mt-1 leading-normal">
                            Diagnóstico del desfase entre calidad técnica y experiencia del jugador.
                          </p>
                        </div>
                        <span class="text-[11px] font-semibold text-emerald-400 bg-emerald-950/50 border border-emerald-800/70 px-2.5 py-0.5 rounded-full whitespace-nowrap shadow-sm">
                          Evaluación Cruzada
                        </span>
                      </div>

                      <div class="cruzada-table-container">
                        <table class="cruzada-table">
                          <thead>
                            <tr>
                              <th class="text-left">Agente / Equipo</th>
                              <th class="text-center whitespace-nowrap">CSAT</th>
                              <th class="text-center whitespace-nowrap">QA Score</th>
                              <th class="text-center whitespace-nowrap" title="Brecha = CSAT - QA Score">Gap</th>
                              <th class="text-right whitespace-nowrap">Diagnóstico</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($matrizCruzada as $rowMatriz): ?>
                              <tr>
                                <td>
                                  <div class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-2">
                                      <span class="w-2 h-2 rounded-full shrink-0 <?= $rowMatriz['errores_criticos'] > 0 ? 'bg-red-400 shadow-[0_0_6px_rgba(248,113,113,0.6)]' : 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.6)]' ?>"></span>
                                      <span class="font-medium text-slate-200 text-xs"><?= htmlspecialchars($rowMatriz['nombre_legible']) ?></span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 pl-4">
                                      <?= $rowMatriz['qa_total'] ?> aud. &bull; <?= $rowMatriz['csat_total'] ?> fb.
                                    </span>
                                  </div>
                                </td>
                                <td class="text-center whitespace-nowrap">
                                  <span class="font-bold text-cyan-400 text-xs sm:text-sm">
                                    <?= number_format($rowMatriz['csat_score'], 1) ?>%
                                  </span>
                                </td>
                                <td class="text-center whitespace-nowrap">
                                  <span class="font-bold text-emerald-400 text-xs sm:text-sm">
                                    <?= number_format($rowMatriz['qa_score'], 1) ?>%
                                  </span>
                                </td>
                                <td class="text-center whitespace-nowrap">
                                  <?php 
                                  $gapVal = $rowMatriz['gap'];
                                  $gapColor = abs($gapVal) <= 5 ? 'text-slate-300' : ($gapVal > 0 ? 'text-cyan-400' : 'text-amber-400');
                                  $gapSign = $gapVal > 0 ? '+' : '';
                                  ?>
                                  <span class="font-semibold text-xs <?= $gapColor ?>" title="Brecha entre satisfacción y auditoría">
                                    <?= $gapSign ?><?= number_format($gapVal, 1) ?>%
                                  </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                  <span class="<?= $rowMatriz['diagnostico_badge'] ?> text-[10px] inline-block font-semibold shadow-sm">
                                    <?= htmlspecialchars($rowMatriz['diagnostico']) ?>
                                  </span>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <!-- SLA Insights footer -->
                    <div class="mt-4 pt-3.5 border-t border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-slate-400">
                      <div class="flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-cyan-400 shrink-0"></span>
                        <span><strong class="text-slate-300 font-medium">Gap &gt; 0:</strong> Percepción cliente supera a auditoría interna</span>
                      </div>
                      <button type="button" onclick="selectTab('capa')" class="text-emerald-400 hover:text-emerald-300 font-medium self-end sm:self-auto transition-colors inline-flex items-center gap-1">
                        <span>Ver planes CAPA</span>
                        <span>&rarr;</span>
                      </button>
                    </div>
                  </div>
                </div>

              </div>

            </div>
          </main>
        </div>
      </div>

      <!-- Foreground Decorative Blurs -->
      <div class="pointer-events-none absolute right-0 top-0 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
      <div class="pointer-events-none absolute bottom-0 left-0 h-96 w-96 rounded-full bg-cyan-500/10 blur-3xl"></div>
    </div>
  </div>

  <!-- Modals insertion -->
  <?php 
  if (!empty($_POST["FormFeedback"]) || (isset($_SESSION["persisted_form_type"]) && $_SESSION["persisted_form_type"] === "crear_feedback")) {
      mostrarModalRegistrarFeedback($conn);
      unset($_SESSION["persisted_form"]);
      unset($_SESSION["persisted_form_type"]);
  }
  if (!empty($_POST["ver_detalle"])) {
      mostrarModalDetalleFeedback($conn, $_POST["ver_detalle"]);
  }
  if (!empty($_POST["FormQA"])) {
      $preTicket = $_POST["preselected_ticket"] ?? null;
      $preAgente = $_POST["preselected_agente"] ?? null;
      mostrarModalRegistrarQA($conn, $preTicket, $preAgente);
  }
  if (!empty($_POST["ver_detalle_qa"])) {
      mostrarModalDetalleQA($conn, $_POST["ver_detalle_qa"]);
  }
  if (!empty($_POST["FormCAPA"])) {
      $preEvaluacion = $_POST["preselected_evaluacion"] ?? null;
      mostrarModalRegistrarCAPA($conn, $preEvaluacion);
  }
  if (!empty($_POST["ver_detalle_capa"])) {
      mostrarModalDetalleCAPA($conn, $_POST["ver_detalle_capa"]);
  }
  ?>

  <!-- Modal HU-44: Responder Encuesta CSAT -->
  <div id="modal-responder-csat" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm overflow-y-auto" style="display: none;">
    <div class="min-h-screen px-4 text-center flex items-center justify-center">
      <div class="relative bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 text-left shadow-2xl transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
          <div class="flex items-center gap-2">
            <span class="p-2 rounded-lg bg-amber-500/10 text-amber-400">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/></svg>
            </span>
            <h3 class="text-lg font-bold text-white">Registrar Respuesta de Calidad (HU-44)</h3>
          </div>
          <button type="button" onclick="cerrarModalResponderCSAT()" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
        </div>
        <form method="POST" action="feedback.php" class="mt-4 space-y-4">
          <input type="hidden" name="active_tab" value="csat" />
          <input type="hidden" name="evaluacion_id" id="csat_modal_id" value="" />
          <input type="hidden" name="accion_responder_encuesta" value="1" />
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Evaluación / Cliente</label>
            <div id="csat_modal_info" class="text-sm text-slate-200 bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 font-mono"></div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Calificación del Cliente (CSAT 1-5 Estrellas)</label>
            <div class="flex items-center gap-4">
              <div class="flex items-center gap-1.5 text-3xl cursor-pointer select-none" id="csat-star-rating">
                <span class="star-item text-amber-400 hover:scale-110 transition-transform" data-val="1">★</span>
                <span class="star-item text-amber-400 hover:scale-110 transition-transform" data-val="2">★</span>
                <span class="star-item text-amber-400 hover:scale-110 transition-transform" data-val="3">★</span>
                <span class="star-item text-amber-400 hover:scale-110 transition-transform" data-val="4">★</span>
                <span class="star-item text-amber-400 hover:scale-110 transition-transform" data-val="5">★</span>
              </div>
              <input type="hidden" name="calificacion" id="csat_modal_calificacion" value="5" />
              <span id="csat_modal_calificacion_label" class="text-sm font-semibold text-amber-300">5 / 5 (Excelente)</span>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Tipo de Feedback</label>
            <select name="tipo" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
              <option value="Elogio">Elogio</option>
              <option value="Sugerencia" selected>Sugerencia</option>
              <option value="Queja">Queja</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Sentimiento Percibido</label>
            <select name="sentimiento" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500">
              <option value="Positivo" selected>Positivo</option>
              <option value="Neutral">Neutral</option>
              <option value="Negativo">Negativo</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Comentarios / Opinión del Cliente *</label>
            <textarea name="comentario" required rows="3" placeholder="Detalle la opinión recibida del cliente..." class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-amber-500"></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-3 border-t border-slate-800">
            <button type="button" onclick="cerrarModalResponderCSAT()" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition-colors">Cancelar</button>
            <button type="submit" class="px-5 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-sm font-semibold shadow-lg shadow-amber-600/30 transition-all flex items-center gap-2">
              <span>Guardar Respuesta (➔ Completada)</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Toast messages container -->
  <style>
    #toast-container {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-width: 450px;
        pointer-events: none;
    }
    #toast-message {
        pointer-events: auto;
        transform: translateX(120%) scale(0.9);
        opacity: 0;
        transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px);
    }
    #toast-message.toast-visible {
        transform: translateX(0) scale(1);
        opacity: 1;
    }
    .toast-success {
        background-color: rgba(6, 78, 59, 0.95) !important;
        border: 1px solid #10b981 !important;
        color: #a7f3d0 !important;
        border-radius: 12px;
        padding: 16px 20px;
        font-family: system-ui, -apple-system, sans-serif;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast-error {
        background-color: rgba(127, 29, 29, 0.95) !important;
        border: 1px solid #ef4444 !important;
        color: #fca5a5 !important;
        border-radius: 12px;
        padding: 16px 20px;
        font-family: system-ui, -apple-system, sans-serif;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
  </style>
  <div id="toast-container">
      <?php if (isset($_SESSION["msg"])): ?>
          <div id="toast-message">
              <?php echo $_SESSION["msg"]; ?>
          </div>
          <?php unset($_SESSION["msg"]); ?>
      <?php endif; ?>
  </div>

  <script>
  function selectTab(tabName) {
      const tabs = ['csat', 'qa', 'capa', 'cruzada'];
      tabs.forEach(t => {
          const btn = document.getElementById('tab-btn-' + t);
          const content = document.getElementById('tab-content-' + t);
          
          if (t === tabName) {
              if (btn) {
                  btn.className = 'flex-1 flex items-center justify-center gap-2.5 py-3.5 px-6 border-b-2 border-emerald-500 font-semibold text-emerald-400 bg-slate-800/60 rounded-t-xl transition-all cursor-pointer text-sm';
              }
              if (content) {
                  content.classList.remove('hidden');
              }
          } else {
              if (btn) {
                  btn.className = 'flex-1 flex items-center justify-center gap-2.5 py-3.5 px-6 border-b-2 border-transparent font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/30 rounded-t-xl transition-all cursor-pointer text-sm';
              }
              if (content) {
                  content.classList.add('hidden');
              }
          }
      });

      if (tabName === 'cruzada') {
          setTimeout(initCrossChartFeedback, 60);
      }

      try {
          localStorage.setItem('active_quality_tab', tabName);
      } catch(e) {}

      if (window.history && window.history.replaceState) {
          window.history.replaceState(null, null, 'feedback.php#' + tabName);
      }
  }

  function updateChartCenter(chartId, name, value, color) {
      const titleEl = document.getElementById('lbl-title-' + chartId);
      const valueEl = document.getElementById('lbl-value-' + chartId);
      if (titleEl && valueEl) {
          titleEl.textContent = name;
          titleEl.setAttribute('fill', color);
          valueEl.textContent = value;
      }
  }

  function resetChartCenter(chartId, defaultName, defaultValue) {
      const titleEl = document.getElementById('lbl-title-' + chartId);
      const valueEl = document.getElementById('lbl-value-' + chartId);
      if (titleEl && valueEl) {
          titleEl.textContent = defaultName;
          titleEl.setAttribute('fill', '#94a3b8');
          valueEl.textContent = defaultValue;
      }
  }

  function simularFeedbackUsuario(idFeedback) {
      const tipos = ["Elogio", "Sugerencia", "Queja"];
      const elogiosComentarios = [
          "Excelente soporte, resolvieron mi incidencia de inmediato.",
          "Muy rápido y efectivo el equipo de soporte. Excelente.",
          "Gracias por la atención, el trato fue insuperable."
      ];
      const sugerenciasComentarios = [
          "Estaría bueno agregar notificaciones en tiempo real al panel.",
          "Sugiero incluir filtros avanzados por categorías de drivers.",
          "Podrían agregar la descarga directa de archivos en formato PDF."
      ];
      const quejasComentarios = [
          "Los drivers recomendados causaron pantallazos azules.",
          "Tardaron bastante en dar respuesta a mi solicitud.",
          "El sistema reporta errores falsos sobre drivers obsoletos."
      ];

      const tipoRandom = tipos[Math.floor(Math.random() * tipos.length)];
      let sentimiento = "";
      let tema = "";
      let comentario = "";

      if (tipoRandom === "Elogio") {
          sentimiento = "Positivo";
          tema = "Satisfacción con soporte";
          comentario = elogiosComentarios[Math.floor(Math.random() * elogiosComentarios.length)];
      } else if (tipoRandom === "Sugerencia") {
          sentimiento = "Positivo";
          tema = "Sugerencia de sistema";
          comentario = sugerenciasComentarios[Math.floor(Math.random() * sugerenciasComentarios.length)];
      } else { // Queja
          sentimiento = "Negativo";
          tema = "Fallo de servicio";
          comentario = quejasComentarios[Math.floor(Math.random() * quejasComentarios.length)];
      }

      fetch('simular_feedback.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json'
          },
          body: JSON.stringify({
              id: idFeedback,
              tipo: tipoRandom,
              sentimiento: sentimiento,
              tema: tema,
              comentario: comentario
          })
      })
      .then(response => {
          if (!response.ok) {
              throw new Error('Respuesta de red no ok');
          }
          return response.json();
      })
      .then(data => {
          if (data.success) {
              const row = document.getElementById('row-fb-' + idFeedback);
              if (row) {
                  const cellTipo = row.querySelector('[data-cell="tipo"]');
                  const cellTema = row.querySelector('[data-cell="tema"]');
                  const cellSentimiento = row.querySelector('[data-cell="sentimiento"]');
                  const cellEstado = row.querySelector('[data-cell="estado"]');

                  if (cellTipo) {
                      cellTipo.textContent = tipoRandom;
                      cellTipo.className = "badge-custom " + getFeedbackTipoClass(tipoRandom);
                  }

                  if (cellTema) {
                      cellTema.textContent = tema;
                      cellTema.setAttribute('title', tema);
                  }

                  if (cellSentimiento) {
                      cellSentimiento.textContent = sentimiento;
                      cellSentimiento.className = "badge-custom " + getFeedbackSentimientoClass(sentimiento);
                  }

                  if (cellEstado) {
                      const estResp = data.estado || "Completada";
                      cellEstado.textContent = estResp;
                      cellEstado.className = "badge-custom " + getFeedbackEstadoClass(estResp);
                  }

                  const btnSimular = row.querySelector('.btn-simular');
                  if (btnSimular) {
                      btnSimular.remove();
                  }
              }
          } else {
              alert('Error al simular feedback: ' + data.error);
          }
      })
      .catch(error => {
          console.error('Error:', error);
          alert('Ocurrió un error al procesar la simulación.');
      });
  }

  function getFeedbackTipoClass(tipo) {
      switch (tipo.toLowerCase().trim()) {
          case 'sugerencia': return 'badge-sugerencia';
          case 'queja': return 'badge-queja';
          case 'elogio': return 'badge-elogio';
          default: return 'badge-default';
      }
  }

  function getFeedbackSentimientoClass(sentimiento) {
      switch (sentimiento.toLowerCase().trim()) {
          case 'positivo': return 'badge-positivo';
          case 'negativo': return 'badge-negativo';
          default: return 'badge-default';
      }
  }

  function getFeedbackEstadoClass(estado) {
      switch (estado.toLowerCase().trim()) {
          case 'pendienteenvio': return 'badge-calidad-pendiente';
          case 'enviada': return 'badge-calidad-enviada';
          case 'completada': return 'badge-calidad-completada';
          case 'procesada': return 'badge-calidad-procesada';
          case 'cancelada': return 'badge-calidad-cancelada';
          case 'expirada': return 'badge-calidad-expirada';
          case 'nuevo': return 'badge-estado-nuevo';
          case 'revisado': return 'badge-estado-revisado';
          case 'implementado': return 'badge-estado-implementado';
          default: return 'badge-default';
      }
  }

  // HU-44: Manejo Modal Responder CSAT
  function abrirModalResponderCSAT(id, usuario, tema) {
      const modal = document.getElementById('modal-responder-csat');
      if (!modal) return;
      document.getElementById('csat_modal_id').value = id;
      document.getElementById('csat_modal_info').textContent = 'Evaluación ID #' + id + ' | Usuario: ' + (usuario || 'Cliente') + (tema ? ' | Tema: ' + tema : '');
      setCSATRating(5);
      modal.classList.remove('hidden');
      modal.style.display = 'block';
  }

  function cerrarModalResponderCSAT() {
      const modal = document.getElementById('modal-responder-csat');
      if (modal) {
          modal.classList.add('hidden');
          modal.style.display = 'none';
      }
  }

  function setCSATRating(val) {
      const input = document.getElementById('csat_modal_calificacion');
      if (input) input.value = val;
      const stars = document.querySelectorAll('#csat-star-rating .star-item');
      stars.forEach(s => {
          const starVal = parseInt(s.getAttribute('data-val') || '0');
          if (starVal <= val) {
              s.classList.remove('text-slate-600');
              s.classList.add('text-amber-400');
          } else {
              s.classList.remove('text-amber-400');
              s.classList.add('text-slate-600');
          }
      });
      const labels = {
          1: '1 / 5 (Muy Insatisfecho)',
          2: '2 / 5 (Insatisfecho)',
          3: '3 / 5 (Neutral)',
          4: '4 / 5 (Satisfecho)',
          5: '5 / 5 (Excelente / Muy Satisfecho)'
      };
      const lbl = document.getElementById('csat_modal_calificacion_label');
      if (lbl) lbl.textContent = labels[val] || (val + ' / 5');
  }

  document.addEventListener('DOMContentLoaded', function() {
      const starItems = document.querySelectorAll('#csat-star-rating .star-item');
      starItems.forEach(s => {
          s.addEventListener('click', function() {
              const val = parseInt(this.getAttribute('data-val') || '5');
              setCSATRating(val);
          });
      });
  });
  </script>

  <!-- Modales Adicionales -->
  <?php
  if (isset($_POST["ver_historial"])) {
      mostrarModalHistorial($conn, intval($_POST["ver_historial"]));
  } else if (isset($_GET["ver_historial"])) {
      mostrarModalHistorial($conn, intval($_GET["ver_historial"]));
  }
  ?>

  <script>
  let crossChartFeedbackInst = null;
  function initCrossChartFeedback() {
      const ctx = document.getElementById('crossQualityChartFeedback');
      if (!ctx || crossChartFeedbackInst) return;
      crossChartFeedbackInst = new Chart(ctx.getContext('2d'), {
          type: 'bar',
          data: {
              labels: <?= json_encode($cruzadaLabels) ?>,
              datasets: [
                  {
                      label: 'CSAT Cliente (%)',
                      data: <?= json_encode($cruzadaCSAT) ?>,
                      backgroundColor: 'rgba(6, 182, 212, 0.85)',
                      borderColor: '#06b6d4',
                      borderWidth: 1.5,
                      borderRadius: 6,
                      barPercentage: 0.65,
                      categoryPercentage: 0.6
                  },
                  {
                      label: 'QA Score Interno (%)',
                      data: <?= json_encode($cruzadaQA) ?>,
                      backgroundColor: 'rgba(16, 185, 129, 0.85)',
                      borderColor: '#10b981',
                      borderWidth: 1.5,
                      borderRadius: 6,
                      barPercentage: 0.65,
                      categoryPercentage: 0.6
                  }
              ]
          },
          options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                  y: {
                      beginAtZero: true,
                      max: 100,
                      ticks: {
                          callback: function(value) { return value + '%'; },
                          stepSize: 20
                      },
                      grid: {
                          color: 'rgba(51, 65, 85, 0.3)'
                      }
                  },
                  x: {
                      grid: { display: false }
                  }
              },
              plugins: {
                  legend: { display: false },
                  tooltip: {
                      callbacks: {
                          label: function(context) {
                              return ' ' + context.dataset.label + ': ' + context.parsed.y + '%';
                          }
                      }
                  }
              }
          }
      });
  }

  document.addEventListener("DOMContentLoaded", function() {
      const toast = document.getElementById("toast-message");
      if (toast) {
          setTimeout(() => {
              toast.classList.add("toast-visible");
          }, 150);
          setTimeout(() => {
              toast.classList.remove("toast-visible");
              setTimeout(() => {
                  toast.remove();
              }, 600);
          }, 4500);
      }

      let initialTab = '<?php echo $activeTab; ?>';
      const hasExplicitTab = <?php echo (isset($_POST['active_tab']) || isset($_GET['tab']) || isset($_POST['inputBuscar']) || isset($_POST['inputBuscarQA']) || isset($_POST['inputBuscarCAPA']) || isset($_POST['tipoBuscar']) || isset($_POST['sentimientoBuscar']) || isset($_POST['resultadoBuscarQA']) || isset($_POST['tipoBuscarCAPA']) || isset($_POST['estadoBuscarCAPA']) || isset($_POST['buscar']) || isset($_POST['buscarQA']) || isset($_POST['cambiar_estado_capa'])) ? 'true' : 'false'; ?>;
      
      if (!hasExplicitTab) {
          const hash = window.location.hash.replace('#', '');
          if (['csat', 'qa', 'capa', 'cruzada'].includes(hash)) {
              initialTab = hash;
          } else {
              const savedTab = localStorage.getItem('active_quality_tab');
              if (savedTab && ['csat', 'qa', 'capa', 'cruzada'].includes(savedTab)) {
                  initialTab = savedTab;
              }
          }
      }
      selectTab(initialTab);
      if (initialTab === 'cruzada') {
          setTimeout(initCrossChartFeedback, 80);
      }
  });
  </script>
</body>
</html>
