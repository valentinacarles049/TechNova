<?php 
ob_start();
session_start();
require_once("funcs/session_security.php");
require_once("db.php");
require_once("funcs/generalFuncs.php");
require_once("funcs/ticketLifecycle.php");
require_once("funcs/ticketFunc.php");

$canDelete = isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "soporte" || $_SESSION["currentTeam"] === "administrador");
$isMonitoreoSla = verificarPermisoMonitoreoOSla();
$canViewSla = $isMonitoreoSla;
$metricasSla = $isMonitoreoSla ? obtenerMetricasSla($conn) : ['vencidos' => 0, 'warning' => 0, 'en_tiempo' => 0, 'total' => 0];
$agentesParaReasignar = $isMonitoreoSla ? obtenerAgentesParaReasignacion($conn) : [];

// Procesar reasignación rápida POST
if (!empty($_POST["accion_reasignar_inline"])) {
    if ($isMonitoreoSla) {
        $tId = intval($_POST["ticket_id"] ?? 0);
        $ag = trim($_POST["idAgente"] ?? "");
        if ($tId > 0 && !empty($ag)) {
            try {
                reasignarAgente($conn, $tId, $ag, "Redistribución rápida preventiva SLA");
                $_SESSION["msg"] = '<div class="toast-success">✅ Ticket #TKT-' . str_pad($tId, 3, "0", STR_PAD_LEFT) . ' reasignado con éxito a <strong>' . htmlspecialchars($ag) . '</strong>.</div>';
            } catch (Exception $e) {
                $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
    redirigirTicket("ticketManegement.php");
    exit;
}

$mostrarTicketModal = false;
$ticketModalData = null;
if (!empty($_POST["FormTicket"]) || (isset($_SESSION["persisted_form_type"]) && $_SESSION["persisted_form_type"] === "crear_ticket")) {
    $mostrarTicketModal = true;
    $ticketModalData = $_SESSION["persisted_form"] ?? null;
    unset($_SESSION["persisted_form"]);
    unset($_SESSION["persisted_form_type"]);
} else if (!empty($_POST["editar"])) {
    $idEditar = intval($_POST["editar"]);
    $q = mysqli_query($conn, "SELECT * FROM tickets WHERE id = $idEditar");
    if ($q && mysqli_num_rows($q) > 0) {
        $mostrarTicketModal = true;
        $ticketModalData = mysqli_fetch_assoc($q);
    }
} else if (isset($_SESSION["persisted_form_type"]) && $_SESSION["persisted_form_type"] === "editar_ticket") {
    $mostrarTicketModal = true;
    $ticketModalData = $_SESSION["persisted_form"] ?? null;
    unset($_SESSION["persisted_form"]);
    unset($_SESSION["persisted_form_type"]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Ciclo de Vida y Gestión de Tickets</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/index-V-K5O4c4.css">
    <style>
    .badge-custom {
        padding: 0.25rem 0.6rem;
        font-size: 0.75rem;
        line-height: 1rem;
        border-radius: 0.375rem;
        border-width: 1px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border-style: solid;
        letter-spacing: 0.02em;
    }
    .badge-baja {
        background-color: rgba(6, 78, 59, 0.4) !important;
        color: #34d399 !important;
        border-color: #065f46 !important;
    }
    .badge-media {
        background-color: rgba(66, 32, 6, 0.4) !important;
        color: #fef08a !important;
        border-color: #ca8a04 !important;
    }
    .badge-alta {
        background-color: rgba(67, 20, 7, 0.4) !important;
        color: #fb923c !important;
        border-color: #9a3412 !important;
    }
    .badge-critica {
        background-color: rgba(69, 10, 10, 0.4) !important;
        color: #f87171 !important;
        border-color: #991b1b !important;
    }

    /* Estados de Tickets */
    .badge-nuevo, .badge-abierto {
        background-color: rgba(139, 92, 246, 0.22) !important;
        color: #c4b5fd !important;
        border-color: #7c3aed !important;
    }
    .badge-asignado {
        background-color: rgba(14, 165, 233, 0.22) !important;
        color: #7dd3fc !important;
        border-color: #0284c7 !important;
    }
    .badge-en-progreso {
        background-color: rgba(37, 99, 235, 0.22) !important;
        color: #93c5fd !important;
        border-color: #1d4ed8 !important;
    }
    .badge-en-espera {
        background-color: rgba(217, 119, 6, 0.25) !important;
        color: #fcd34d !important;
        border-color: #d97706 !important;
    }
    .badge-resuelto {
        background-color: rgba(13, 148, 136, 0.25) !important;
        color: #5eead4 !important;
        border-color: #0d9488 !important;
    }
    .badge-cerrado {
        background-color: rgba(16, 185, 129, 0.22) !important;
        color: #6ee7b7 !important;
        border-color: #059669 !important;
    }
    .badge-cancelado, .badge-inactivo {
        background-color: rgba(225, 29, 72, 0.22) !important;
        color: #fda4af !important;
        border-color: #be123c !important;
    }

    /* Panel y Métricas de Monitoreo SLA */
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
    .sla-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 0.75rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    @media (max-width: 768px) {
        .sla-grid {
            grid-template-columns: 1fr !important;
        }
    }
    .btn-filtrar-sla {
        padding: 3px 10px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        border-radius: 6px !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        line-height: 1.2 !important;
    }
    .btn-filtrar-vencido {
        background-color: rgba(225, 29, 72, 0.15) !important;
        color: #fca5a5 !important;
        border: 1px solid rgba(225, 29, 72, 0.4) !important;
    }
    .btn-filtrar-vencido:hover {
        background-color: #e11d48 !important;
        color: #ffffff !important;
    }
    .btn-filtrar-warning {
        background-color: rgba(217, 119, 6, 0.15) !important;
        color: #fde68a !important;
        border: 1px solid rgba(217, 119, 6, 0.4) !important;
    }
    .btn-filtrar-warning:hover {
        background-color: #d97706 !important;
        color: #ffffff !important;
    }
    .btn-filtrar-tiempo {
        background-color: rgba(16, 185, 129, 0.15) !important;
        color: #a7f3d0 !important;
        border: 1px solid rgba(16, 185, 129, 0.4) !important;
    }
    .btn-filtrar-tiempo:hover {
        background-color: #059669 !important;
        color: #ffffff !important;
    }

    /* Badges de SLA y Animación de Feedback Inline */
    .badge-sla-vencido {
        background-color: rgba(225, 29, 72, 0.22) !important;
        color: #f87171 !important;
        border-color: #be123c !important;
    }
    .badge-sla-warning {
        background-color: rgba(217, 119, 6, 0.25) !important;
        color: #fcd34d !important;
        border-color: #d97706 !important;
    }
    .badge-sla-ok {
        background-color: rgba(16, 185, 129, 0.22) !important;
        color: #6ee7b7 !important;
        border-color: #059669 !important;
    }
    .row-highlight-reassigned {
        background-color: rgba(8, 145, 178, 0.25) !important;
        transition: background-color 0.8s ease-in-out;
    }

    /* Grilla de Métricas de Tickets - En una sola línea horizontal */
    .stat-grid {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 0.75rem !important;
        width: 100% !important;
        margin-top: 0.5rem !important;
        margin-bottom: 1rem !important;
    }

    /* Burbujas / Tarjetas de Métricas Compactas */
    .stat-card {
        background-color: #1e293b !important;
        border: 1px solid #334155 !important;
        border-radius: 0.5rem !important;
        padding: 0.75rem 1rem !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -2px rgba(0, 0, 0, 0.3) !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        box-sizing: border-box !important;
    }
    .stat-card:hover {
        border-color: #475569 !important;
    }

    /* Títulos Superiores de las Burbujas */
    .stat-card-title {
        color: #ffffff !important;
        font-size: 0.85rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.015em !important;
        margin: 0 !important;
        line-height: 1.2 !important;
    }

    /* Píldoras / Badges superiores */
    .stat-badge {
        font-size: 0.7rem !important;
        font-weight: 600 !important;
        padding: 0.15rem 0.5rem !important;
        border-radius: 9999px !important;
        border: 1px solid transparent !important;
        line-height: 1 !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    .stat-badge-global {
        background-color: #334155 !important;
        color: #f8fafc !important;
        border-color: #475569 !important;
    }
    .stat-badge-asignacion {
        background-color: rgba(139, 92, 246, 0.25) !important;
        color: #e9d5ff !important;
        border-color: rgba(139, 92, 246, 0.5) !important;
    }
    .stat-badge-atencion {
        background-color: rgba(59, 130, 246, 0.25) !important;
        color: #dbeafe !important;
        border-color: rgba(59, 130, 246, 0.5) !important;
    }
    .stat-badge-completados {
        background-color: rgba(16, 185, 129, 0.25) !important;
        color: #d1fae5 !important;
        border-color: rgba(16, 185, 129, 0.5) !important;
    }

    /* Cifra Numérica Principal */
    .stat-card-number {
        font-size: 1.5rem !important;
        line-height: 1 !important;
        font-weight: 800 !important;
        color: #ffffff !important;
        margin: 0.4rem 0 !important;
    }

    /* Pie / Línea de detalle inferior */
    .stat-card-footer {
        padding-top: 0.5rem !important;
        border-top: 1px solid #334155 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        font-size: 0.75rem !important;
        color: #cbd5e1 !important;
        margin-top: auto !important;
    }

    /* Barra de Búsqueda y Filtros */
    .filtros-container {
        display: flex !important;
        flex-direction: row !important;
        gap: 1rem !important;
        align-items: center !important;
        width: 100% !important;
    }
    @media (max-width: 768px) {
        .filtros-container {
            flex-direction: column !important;
            align-items: stretch !important;
        }
    }
        /* Estandarización de Botones de Gestión de Tickets */
    .btn-registrar {
        background-color: #059669 !important;
        background-image: none !important;
        color: #ffffff !important;
        border: 1px solid #10b981 !important;
        padding: 9px 20px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.4) !important;
        transition: all 0.2s ease !important;
        text-decoration: none !important;
    }
    .btn-registrar:hover {
        background-color: #047857 !important;
        border-color: #34d399 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 12px -2px rgba(5, 150, 105, 0.5) !important;
        filter: none !important;
    }
    .btn-exportar {
        background-color: #1e293b !important;
        color: #ffffff !important;
        border: 1px solid #475569 !important;
        padding: 9px 18px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
        transition: all 0.2s ease !important;
        text-decoration: none !important;
    }
    .btn-exportar:hover {
        background-color: #334155 !important;
        border-color: #94a3b8 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.4) !important;
    }
    .btn-filtrar-submit {
        background-color: #059669 !important;
        color: #ffffff !important;
        border: 1px solid #10b981 !important;
        padding: 9px 20px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
        transition: all 0.2s ease !important;
    }
    .btn-filtrar-submit:hover {
        background-color: #047857 !important;
        border-color: #34d399 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(5, 150, 105, 0.4) !important;
    }
    .btn-limpiar-filtros {
        background-color: #334155 !important;
        color: #ffffff !important;
        border: 1px solid #64748b !important;
        padding: 9px 18px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
        transition: all 0.2s ease !important;
        text-decoration: none !important;
    }
    .btn-limpiar-filtros:hover {
        background-color: #475569 !important;
        border-color: #94a3b8 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.4) !important;
    }
    .btn-actualizar-sla {
        background-color: #1e293b !important;
        color: #e2e8f0 !important;
        border: 1px solid #475569 !important;
        padding: 6px 14px !important;
        border-radius: 8px !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
        transition: all 0.2s ease !important;
    }
    .btn-actualizar-sla:hover {
        background-color: #334155 !important;
        border-color: #94a3b8 !important;
        transform: translateY(-1px) !important;
        color: #ffffff !important;
    }
    </style>
</head>
<body>
<?php

$stats = queryStats($conn);
$totalTickets = $stats['total'] ?? 0;
$totalNuevos = $stats['nuevos'] ?? 0;
$totalAsignados = $stats['asignados'] ?? 0;
$totalEnProgreso = $stats['en_progreso'] ?? 0;
$totalEnEspera = $stats['en_espera'] ?? 0;
$totalResueltos = $stats['resueltos'] ?? 0;
$totalCerrados = $stats['cerrados'] ?? 0;
$totalCancelados = $stats['cancelados'] ?? 0;

$totalEnCola = $totalNuevos + $totalAsignados;
$totalEnAtencion = $totalEnProgreso + $totalEnEspera;
$totalFinalizados = $totalResueltos + $totalCerrados;

if (!empty($_POST["eliminar"])){
     eliminarTicket($conn);
};

$tickets = obtenerTickets($conn);     

$camposTickets = [];

// 1. ID del Ticket (TKT-001)
$camposTickets[] = function($fila) {
    $idFormateado = 'TKT-' . str_pad($fila["id"], 3, "0", STR_PAD_LEFT);
    return '<td class="p-2 text-cyan-400 font-mono font-medium">' . $idFormateado . '</td>';
};

// 2. Título
$camposTickets[] = function($fila) {
    return '<td class="p-2 font-medium text-slate-100">' . htmlspecialchars($fila["titulo"]) . '</td>';
};

// 3. Categoría
$camposTickets[] = function($fila) {
    return '<td class="p-2 text-slate-300">' . htmlspecialchars($fila["categoria"]) . '</td>';
};

// 4. Prioridad (Con Badge)
$camposTickets[] = function($fila) {
    $clase = getPrioridadBadgeClass($fila["prioridad"]);
    return '<td class="p-2">
                <span class="badge-custom ' . $clase . '">
                    ' . htmlspecialchars($fila["prioridad"]) . '
                </span>
            </td>';
};

// 5. Juego (Manejo de nulos)
$camposTickets[] = function($fila) {
    $juego = $fila["juego_nombre"] ?? "N/A";
    return '<td class="p-2 text-slate-300">' . htmlspecialchars($juego) . '</td>';
};

// 6. Driver (Manejo de nulos)
$camposTickets[] = function($fila) {
    $driver = $fila["driver_nombre"] ?? "N/A";
    return '<td class="p-2 text-slate-300">' . htmlspecialchars($driver) . '</td>';
};

// 7. Estado Oficial (Con Badge de 7 estados)
$camposTickets[] = function($fila) {
    $clase = getEstadoBadgeClass($fila["estado"]);
    $estadoTexto = htmlspecialchars(TicketEstado::normalizar($fila["estado"]));
    return '<td class="p-2">
                <span class="badge-custom ' . $clase . '">
                    ' . $estadoTexto . '
                </span>
            </td>';
};

// SLA: Tiempo remanente y alertas visuales por colores (Soporte, Monitoreo y Administrador)
if ($canViewSla) {
    $camposTickets[] = function($fila) {
        $sla = calcularSlaTicket($fila);
        $limiteStr = !empty($sla['fecha_vencimiento']) ? date('d/m/Y H:i', strtotime($sla['fecha_vencimiento'])) : $sla['fecha_limite'];
        $detalleHtml = !empty($sla['detalle']) ? '<span class="block text-[10px] text-slate-400 mt-0.5">' . htmlspecialchars($sla['detalle']) . '</span>' : '';
        return '<td class="p-2 whitespace-nowrap">
                    <span class="badge-custom ' . $sla['badge_class'] . '" title="Límite: ' . htmlspecialchars($limiteStr) . ' (SLA: ' . $sla['horas_sla'] . 'h)">
                        ' . $sla['etiqueta'] . '
                    </span>
                    ' . $detalleHtml . '
                </td>';
    };
}

// 9. Asignado a (Con Selector Inline y Mutación rápida para roles autorizados)
$camposTickets[] = function($fila) use ($isMonitoreoSla, $agentesParaReasignar) {
    $tId = $fila['id'];
    $asig = !empty($fila["asignado"]) ? htmlspecialchars($fila["asignado"]) : '<span class="text-slate-500 italic">Sin asignar</span>';
    $estadoActual = TicketEstado::normalizar($fila['estado'] ?? TicketEstado::NUEVO);
    $esActivo = !in_array($estadoActual, [TicketEstado::CERRADO, TicketEstado::CANCELADO], true);

    $html = '<td class="p-2 text-slate-300" id="td-asig-' . $tId . '">';
    $html .= '<div id="agente-display-' . $tId . '" class="font-medium text-slate-200">' . $asig . '</div>';

    // Inyección de Selector Inline para invocar reasignarAgente(idAgente) sin navegar fuera de la vista
    if ($isMonitoreoSla && $esActivo) {
        $tieneJuegoYDriver = (!empty($fila['juego_id']) && !empty($fila['driver_id']));
        $rolEsperado = $tieneJuegoYDriver ? 'desarrollo' : 'soporte';
        $etiquetaCanal = $tieneJuegoYDriver ? '🛠️ Dev' : '🎧 Soporte';

        $html .= '
        <div class="flex items-center gap-1.5 mt-1.5" id="box-reasignar-' . $tId . '">
            <select id="select-agente-' . $tId . '" class="rounded border border-slate-700 bg-slate-800 text-[11px] text-white px-1.5 py-1 outline-none focus:border-cyan-500 max-w-[145px]" title="Reasignar (' . $etiquetaCanal . ') - Ordenado por menor carga">
                <option value="">Reasignar (' . $etiquetaCanal . ')...</option>';
        foreach ($agentesParaReasignar as $ag) {
            $rolAg = strtolower(trim($ag['rol'] ?? ''));
            if (empty($rolAg)) {
                if (intval($ag['IdRol'] ?? 0) === 2) $rolAg = 'soporte';
                elseif (intval($ag['IdRol'] ?? 0) === 3) $rolAg = 'desarrollo';
                elseif (intval($ag['IdRol'] ?? 0) === 1) $rolAg = 'administrador';
            }
            if ($rolAg !== $rolEsperado && $rolAg !== 'administrador') {
                continue;
            }
            $sel = ($fila['asignado'] === $ag['usuario'] || $fila['asignado'] === $ag['nombre_completo']) ? 'selected' : '';
            $act = intval($ag['tickets_activos'] ?? 0);
            $badgeCarga = ($act === 0) ? '🟢 Libre' : (($act <= 2) ? "🟡 {$act} act." : "🔴 {$act} act.");
            $nombreMostrar = (!empty($ag['nombre_completo']) ? $ag['nombre_completo'] : $ag['usuario']) . ' (' . $badgeCarga . ')';
            $html .= '<option value="' . htmlspecialchars($ag['usuario']) . '" ' . $sel . '>' . htmlspecialchars($nombreMostrar) . '</option>';
        }
        $html .= '
            </select>
            <button type="button" onclick="invocarReasignarAgente(' . $tId . ')" class="btn-reasignar-inline px-2 py-1 rounded bg-cyan-600 hover:bg-cyan-500 text-white text-[11px] font-semibold transition flex items-center gap-1 shadow-sm" title="Reasignar inmediatamente">
                <span>🔄</span>
            </button>
        </div>
        <div id="feedback-reasignar-' . $tId . '" class="text-[10px] mt-0.5 hidden font-semibold"></div>';
    }

    $html .= '</td>';
    return $html;
};

// 10. Acciones (Ver detalles / Ciclo de vida y Cancelar condicional)
$camposTickets[] = function($fila) use ($canDelete) {
    $indexEditar = $fila['index'] ?? $fila['id'];
    $estadoActual = TicketEstado::normalizar($fila['estado'] ?? TicketEstado::NUEVO);

    $deleteBtn = '';
    if ($canDelete && $estadoActual !== TicketEstado::CANCELADO && $estadoActual !== TicketEstado::CERRADO) {
        $deleteBtn = '
            <form method="POST" class="inline-block m-0 p-0" onsubmit="return confirm(\'¿Estás seguro de cancelar este ticket?\');">
                <button type="submit" name="eliminar" value="' . $fila["id"] . '" class="btn-accion-icono icono-eliminar" title="Cancelar Ticket">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                </button>
            </form>';
    }

    $historyBtn = '';
    if (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador")) {
        $historyBtn = '
            <form method="POST" class="inline-block m-0 p-0">
                <button type="submit" name="ver_historial" value="' . $fila["id"] . '" class="btn-accion-icono" style="color: #60a5fa !important;" title="Historial del Ticket">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-text" viewBox="0 0 16 16"><path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/></svg>
                </button>
            </form>';
    }

    return '<td class="p-2 text-center">
                <div class="contenedor-acciones-tabla flex items-center justify-center gap-1.5">
                    <form method="POST" class="inline-block m-0 p-0">
                        <button class="btn-accion-icono icono-ver" name="editar" type="submit" value="' . $indexEditar . '" title="Gestionar Ciclo de Vida y Ver Detalles">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                        </button>
                    </form>
                    ' . $historyBtn . '
                    ' . $deleteBtn . '
                </div>
            </td>';
};
?>

<body>
  <div id="root">
    <div
      class="h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 overflow-hidden"
    >
      <!-- Background -->
      <div class="absolute inset-0 opacity-5">
        <div
          class="absolute inset-0"
          style="
            background-image: radial-gradient(
              circle at 2px 2px,
              rgb(16, 185, 129) 1px,
              transparent 0px
            );
            background-size: 40px 40px;
          "
        ></div>
      </div>

      <div class="relative h-full flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900/90 backdrop-blur-sm border-r border-emerald-900/30 transition-all duration-300 flex flex-col">
          <!-- Logo -->
          <?php require_once("templates/logo.php") ?>

          </div>
        </div>

          <!-- Badge -->
        <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-3">
          <span
            class="flex w-full items-center justify-center rounded-md <?php echo $sidebarBadgeClass; ?> px-2 py-1 text-xs font-medium text-white"
          >
            <?php echo $sidebarBadgeText; ?>
          </span>
        </div>

          <!-- Navigation -->
          <?php require_once("templates/sidebar.php") ?>

          <!-- Footer -->
          <div class="p-4 border-t border-slate-800">
            <button
              class="w-full flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 rounded-md py-2 transition-all"
            >
              ✕
            </button>
          </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
          <!-- Header -->
          <?php require_once("templates/header.php") ?>

          <!-- Main -->
          <main class="flex-1 overflow-auto p-8">
            <div class="space-y-6">
              <!-- Title -->
              <div class="flex items-center justify-between">
                <div>
                  <h1 class="text-white text-2xl font-semibold">Gestión de Tickets</h1>
                  <p class="text-slate-400">Administra solicitudes y consultas de usuarios</p>
                </div>

                <div class="contenedor-acciones-top">
                  <?php if ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador"): ?>
                    <a 
                        href="funcs/exportar-csv.php?<?php 
                            echo http_build_query([
                                'inputBuscar' => $_POST['inputBuscar'] ?? '',
                                'estadoBuscar' => $_POST['estadoBuscar'] ?? '',
                                'prioridadBuscar' => $_POST['prioridadBuscar'] ?? '',
                                'slaBuscar' => $_POST['slaBuscar'] ?? $_GET['slaBuscar'] ?? ''
                            ]); ?>"
                        class="btn-exportar"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                            <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a.5.5 0 0 1-.5-.5zM5.884 6.68 8 9.5l2.116-2.82a.25.25 0 0 1 .416.148l.49 1.956.452-1.8c.045-.18.2-.314.385-.314h.342a.25.25 0 0 1 .23.344l-.8 2a.25.25 0 0 1-.456-.01L10.3 8.3l-.364 1.455a.25.25 0 0 1-.482.01L7.26 6.81a.25.25 0 0 0-.46.024l-.534 2.136a.25.25 0 0 1-.485-.012L5.3 7.8l-.3 1.2a.25.25 0 0 1-.485-.012l-.8-3a.25.25 0 0 1 .37-.267h.342c.18 0 .338.125.388.3l.423 1.692.42-1.68a.25.25 0 0 1 .425-.136z"/>
                        </svg>
                        <span>Exportar Excel</span>
                    </a>
                  <?php endif; ?>

                  <?php if ($_SESSION["currentTeam"] == "soporte" || $_SESSION["currentTeam"] == "administrador"): ?>
                    <form method="POST" style="margin: 0;">
                        <button
                            type="submit"
                            name="FormTicket"
                            class="btn-registrar"
                            value="1"
                        >
                            <span>+</span> Registrar Ticket
                        </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Stats Cards -->
              <div class="stat-grid" style="display: grid !important; grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 0.75rem !important; width: 100% !important;">
                <!-- Card 1: Total Casos -->
                <div class="stat-card" style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-radius: 8px; padding: 10px 14px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4);">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="stat-card-title" style="color: #ffffff !important; font-size: 13px; font-weight: 700; margin: 0;">Total de Casos</h3>
                    <span class="stat-badge stat-badge-global" style="background-color: #334155 !important; color: #f8fafc !important; border: 1px solid #475569 !important; font-size: 11px; padding: 2px 7px; border-radius: 9999px;">Global</span>
                  </div>
                  <div class="stat-card-number" style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                    <?php echo $totalTickets; ?>
                  </div>
                  <div class="stat-card-footer" style="padding-top: 6px; border-top: 1px solid #334155 !important; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                    <span style="color: #cbd5e1 !important;">Activos e históricos</span>
                    <span style="color: #34d399 !important; font-weight: 700;"><?php echo $totalTickets; ?> reg.</span>
                  </div>
                </div>

                <!-- Card 2: En Cola -->
                <div class="stat-card" style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-radius: 8px; padding: 10px 14px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4);">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="stat-card-title" style="color: #ffffff !important; font-size: 13px; font-weight: 700; margin: 0;">En Cola</h3>
                    <span class="stat-badge stat-badge-asignacion" style="background-color: rgba(139, 92, 246, 0.25) !important; color: #e9d5ff !important; border: 1px solid rgba(139, 92, 246, 0.5) !important; font-size: 11px; padding: 2px 7px; border-radius: 9999px;">Asignación</span>
                  </div>
                  <div class="stat-card-number" style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                    <?php echo $totalEnCola; ?>
                  </div>
                  <div class="stat-card-footer" style="padding-top: 6px; border-top: 1px solid #334155 !important; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                    <span style="color: #cbd5e1 !important;"><strong style="color: #c4b5fd !important; font-weight: 700;"><?php echo $totalNuevos; ?></strong> Nuevo</span>
                    <span style="color: #cbd5e1 !important;"><strong style="color: #7dd3fc !important; font-weight: 700;"><?php echo $totalAsignados; ?></strong> Asignado</span>
                  </div>
                </div>

                <!-- Card 3: En Atención Activa -->
                <div class="stat-card" style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-radius: 8px; padding: 10px 14px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4);">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="stat-card-title" style="color: #ffffff !important; font-size: 13px; font-weight: 700; margin: 0;">En Atención Activa</h3>
                    <span class="stat-badge stat-badge-atencion" style="background-color: rgba(59, 130, 246, 0.25) !important; color: #dbeafe !important; border: 1px solid rgba(59, 130, 246, 0.5) !important; font-size: 11px; padding: 2px 7px; border-radius: 9999px;">En Curso</span>
                  </div>
                  <div class="stat-card-number" style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                    <?php echo $totalEnAtencion; ?>
                  </div>
                  <div class="stat-card-footer" style="padding-top: 6px; border-top: 1px solid #334155 !important; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                    <span style="color: #cbd5e1 !important;"><strong style="color: #93c5fd !important; font-weight: 700;"><?php echo $totalEnProgreso; ?></strong> En Progreso</span>
                    <span style="color: #cbd5e1 !important;"><strong style="color: #fcd34d !important; font-weight: 700;"><?php echo $totalEnEspera; ?></strong> En Espera</span>
                  </div>
                </div>

                <!-- Card 4: Resolución -->
                <div class="stat-card" style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-radius: 8px; padding: 10px 14px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4);">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="stat-card-title" style="color: #ffffff !important; font-size: 13px; font-weight: 700; margin: 0;">Resolución</h3>
                    <span class="stat-badge stat-badge-completados" style="background-color: rgba(16, 185, 129, 0.25) !important; color: #d1fae5 !important; border: 1px solid rgba(16, 185, 129, 0.5) !important; font-size: 11px; padding: 2px 7px; border-radius: 9999px;">Finalizados</span>
                  </div>
                  <div class="stat-card-number" style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                    <?php echo $totalFinalizados; ?>
                  </div>
                  <div class="stat-card-footer" style="padding-top: 6px; border-top: 1px solid #334155 !important; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                    <span style="color: #cbd5e1 !important;"><strong style="color: #5eead4 !important; font-weight: 700;"><?php echo $totalResueltos; ?></strong> Resuelto</span>
                    <span style="color: #cbd5e1 !important;"><strong style="color: #6ee7b7 !important; font-weight: 700;"><?php echo $totalCerrados; ?></strong> Cerrado</span>
                  </div>
                </div>
              </div>

                            <?php if ($isMonitoreoSla): ?>
              <!-- Monitoreo de SLAs -->
              <div id="sla-summary-dashboard" class="sla-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #334155 !important;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 15px;">⏱️</span>
                    <h2 style="color: #ffffff !important; font-size: 14px; font-weight: 700; margin: 0; letter-spacing: 0.015em;">Monitoreo de SLAs</h2>
                  </div>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span id="sla-live-indicator" style="display: inline-flex; align-items: center; gap: 6px; background-color: rgba(16, 185, 129, 0.15) !important; color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; padding: 2px 8px !important; border-radius: 9999px !important; font-size: 11px !important; font-weight: 600 !important;">
                      <span style="display: inline-block; width: 6px; height: 6px; border-radius: 9999px; background-color: #34d399; box-shadow: 0 0 6px #34d399;"></span> Tiempo Real
                    </span>
                    <button type="button" onclick="actualizarResumenSLA()" style="display: inline-flex; align-items: center; gap: 4px; background-color: #1e293b !important; color: #cbd5e1 !important; border: 1px solid #475569 !important; padding: 3px 9px !important; border-radius: 6px !important; font-size: 11px !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#334155'; this.style.color='#ffffff';" onmouseout="this.style.backgroundColor='#1e293b'; this.style.color='#cbd5e1';" title="Actualizar métricas">
                      <span>🔄</span> Actualizar
                    </button>
                  </div>
                </div>

                <div class="sla-grid">
                  <!-- 1. Vencidos -->
                  <div class="stat-card" style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #ef4444 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                      <h3 style="color: #f87171 !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                        <span>🚨</span> Vencidos
                      </h3>
                      <span style="background-color: rgba(225, 29, 72, 0.2) !important; color: #fca5a5 !important; border: 1px solid rgba(225, 29, 72, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">SLA Incumplido</span>
                    </div>
                    <div id="cnt-sla-vencidos" style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                      <?= $metricasSla['vencidos'] ?>
                    </div>
                    <div style="padding-top: 6px; border-top: 1px solid #334155 !important; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                      <span style="color: #94a3b8 !important;">Fuera de término</span>
                      <button type="button" onclick="filtrarPorSla('vencido')" class="btn-filtrar-sla btn-filtrar-vencido">Filtrar</button>
                    </div>
                  </div>

                  <!-- 2. SLA Warning -->
                  <div class="stat-card" style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #f59e0b !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                      <h3 style="color: #fcd34d !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                        <span>⚠️</span> SLA Warning
                      </h3>
                      <span style="background-color: rgba(217, 119, 6, 0.2) !important; color: #fde68a !important; border: 1px solid rgba(217, 119, 6, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Próximos</span>
                    </div>
                    <div id="cnt-sla-warning" style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                      <?= $metricasSla['warning'] ?>
                    </div>
                    <div style="padding-top: 6px; border-top: 1px solid #334155 !important; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                      <span style="color: #94a3b8 !important;">Riesgo inminente</span>
                      <button type="button" onclick="filtrarPorSla('warning')" class="btn-filtrar-sla btn-filtrar-warning">Filtrar</button>
                    </div>
                  </div>

                  <!-- 3. En Tiempo -->
                  <div class="stat-card" style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #10b981 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                      <h3 style="color: #6ee7b7 !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;">
                        <span>⏱️</span> En Tiempo
                      </h3>
                      <span style="background-color: rgba(16, 185, 129, 0.2) !important; color: #a7f3d0 !important; border: 1px solid rgba(16, 185, 129, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Bajo Control</span>
                    </div>
                    <div id="cnt-sla-tiempo" style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;">
                      <?= $metricasSla['en_tiempo'] ?>
                    </div>
                    <div style="padding-top: 6px; border-top: 1px solid #334155 !important; display: flex; justify-content: space-between; align-items: center; font-size: 11px;">
                      <span style="color: #94a3b8 !important;">Dentro de SLA</span>
                      <button type="button" onclick="filtrarPorSla('en_tiempo')" class="btn-filtrar-sla btn-filtrar-tiempo">Filtrar</button>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>

        <!-- FORMULARIO DE FILTROS Y BÚSQUEDA -->
        <form method="post">
             <div class="w-full p-6 border border-slate-700 rounded-lg bg-slate-900/40 mt-6 mb-6" style="background-color: rgba(15, 23, 42, 0.6) !important; border: 1px solid #334155 !important;">
            <div class="filtros-container">
            
                <!-- SEARCH -->
                <div style="flex: 1 1 0%; min-width: 260px; position: relative;">
                <input
                    type="text"
                    name="inputBuscar"
                    value="<?php echo htmlspecialchars($_POST["inputBuscar"] ?? ""); ?>"
                    placeholder="Buscar tickets por ID, título, descripción, usuario o asignado..."
                    class="w-full rounded-md border border-slate-700 bg-slate-900 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    style="width: 100% !important; height: 38px !important; background-color: #0f172a !important; border: 1px solid #334155 !important; color: #ffffff !important; padding: 0 14px !important; border-radius: 6px !important; box-sizing: border-box !important;"
                />
                </div>

                <!-- FILTRO ESTADO -->
                <select
                class="rounded-md border border-slate-700 bg-slate-900 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                style="height: 38px !important; width: 200px !important; background-color: #0f172a !important; border: 1px solid #334155 !important; color: #ffffff !important; padding: 0 12px !important; border-radius: 6px !important; flex-shrink: 0 !important;"
                name="estadoBuscar"
                onchange="this.form.submit()"
                >
                <option value="todas" <?php echo (($_POST["estadoBuscar"] ?? "todas") === "todas") ? "selected" : ""; ?>>Todos los Estados</option>
                <option value="Nuevo" <?php echo (($_POST["estadoBuscar"] ?? "") === "Nuevo") ? "selected" : ""; ?>>Nuevo</option>
                <option value="Asignado" <?php echo (($_POST["estadoBuscar"] ?? "") === "Asignado") ? "selected" : ""; ?>>Asignado</option>
                <option value="En Progreso" <?php echo (($_POST["estadoBuscar"] ?? "") === "En Progreso") ? "selected" : ""; ?>>En Progreso</option>
                <option value="En Espera" <?php echo (($_POST["estadoBuscar"] ?? "") === "En Espera") ? "selected" : ""; ?>>En Espera</option>
                <option value="Resuelto" <?php echo (($_POST["estadoBuscar"] ?? "") === "Resuelto") ? "selected" : ""; ?>>Resuelto</option>
                <option value="Cerrado" <?php echo (($_POST["estadoBuscar"] ?? "") === "Cerrado") ? "selected" : ""; ?>>Cerrado</option>
                <option value="Cancelado" <?php echo (($_POST["estadoBuscar"] ?? "") === "Cancelado") ? "selected" : ""; ?>>Cancelado</option>
                </select>

                <!-- FILTRO 2: PRIORIDAD -->
                <select
                class="rounded-md border border-slate-700 bg-slate-900 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                style="height: 38px !important; width: 190px !important; background-color: #0f172a !important; border: 1px solid #334155 !important; color: #ffffff !important; padding: 0 12px !important; border-radius: 6px !important; flex-shrink: 0 !important;"
                name="prioridadBuscar"
                onchange="this.form.submit()"
                >
                <option value="todas" <?php echo (($_POST["prioridadBuscar"] ?? "todas") === "todas") ? "selected" : ""; ?>>Todas las Prioridades</option>
                <option value="critica" <?php echo (($_POST["prioridadBuscar"] ?? "") === "critica") ? "selected" : ""; ?>>Crítica</option>
                <option value="alta" <?php echo (($_POST["prioridadBuscar"] ?? "") === "alta") ? "selected" : ""; ?>>Alta</option>
                <option value="media" <?php echo (($_POST["prioridadBuscar"] ?? "") === "media") ? "selected" : ""; ?>>Media</option>
                <option value="baja" <?php echo (($_POST["prioridadBuscar"] ?? "") === "baja") ? "selected" : ""; ?>>Baja</option>
                </select>

                <?php if ($isMonitoreoSla): ?>
                <!-- FILTRO 3: ESTADO SLA (Solo Monitoreo y Administrador) -->
                <select
                class="rounded-md border border-amber-700 bg-slate-900 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                style="height: 38px !important; width: 235px !important; background-color: #0f172a !important; border: 1px solid #ca8a04 !important; color: #ffffff !important; padding: 0 12px !important; border-radius: 6px !important; flex-shrink: 0 !important;"
                name="slaBuscar"
                id="slaBuscar"
                onchange="this.form.submit()"
                >
                <option value="todas" <?php echo (($_POST["slaBuscar"] ?? $_GET["slaBuscar"] ?? "todas") === "todas") ? "selected" : ""; ?>>Todos los SLAs</option>
                <option value="warning" <?php echo (($_POST["slaBuscar"] ?? $_GET["slaBuscar"] ?? "") === "warning") ? "selected" : ""; ?>>⚠️ SLA Warning</option>
                <option value="vencido" <?php echo (($_POST["slaBuscar"] ?? $_GET["slaBuscar"] ?? "") === "vencido") ? "selected" : ""; ?>>🚨 Vencidos</option>
                <option value="en_tiempo" <?php echo (($_POST["slaBuscar"] ?? $_GET["slaBuscar"] ?? "") === "en_tiempo") ? "selected" : ""; ?>>⏱️ En Tiempo</option>
                </select>
                <?php endif; ?>
            </div>
            
            <div class="flex justify-between items-center mt-4">
              <!-- Grupo izquierdo: Buscar y Limpiar -->
              <div class="flex gap-2">
                  <button
                      type="submit"
                      name="buscar"
                      value="buscar"
                      class="btn-filtrar-submit"
                  >
                      <span>🔍</span> Filtrar Resultados
                  </button>

                  <?php 
                  $filtroActivo = !empty($_POST["inputBuscar"]) || 
                                  (isset($_POST["estadoBuscar"]) && $_POST["estadoBuscar"] !== "todas") || 
                                  (isset($_POST["prioridadBuscar"]) && $_POST["prioridadBuscar"] !== "todas") ||
                                  (isset($_POST["slaBuscar"]) && $_POST["slaBuscar"] !== "todas") ||
                                  (isset($_GET["slaBuscar"]) && $_GET["slaBuscar"] !== "todas");
                  if ($filtroActivo): 
                  ?>
                  <a
                      href="ticketManegement.php"
                      class="btn-limpiar-filtros"
                  >
                      <span>✕</span> Limpiar Filtros
                  </a>
                  <?php endif; ?>
              </div>
            </div>
          </div>
        </form>

            <!-- TABLA DE TICKETS -->
            <div class="w-full mt-6 overflow-x-auto rounded-lg border border-slate-700 shadow-xl bg-slate-900/30">
            <table class="w-full min-w-[850px] text-sm">
                <thead>
                <tr class="border-b border-slate-700 text-slate-300 bg-slate-900/80">
                    <th class="text-left p-3 whitespace-nowrap">ID</th>
                    <th class="text-left p-3 whitespace-nowrap">Título</th>
                    <th class="text-left p-3 whitespace-nowrap">Categoría</th>
                    <th class="text-left p-3 whitespace-nowrap">Prioridad</th>
                    <th class="text-left p-3 whitespace-nowrap">Juego</th>
                    <th class="text-left p-3 whitespace-nowrap">Driver</th>
                    <th class="text-left p-3 whitespace-nowrap">Estado</th>
                    <?php if ($canViewSla): ?>
                    <th class="text-left p-3 whitespace-nowrap">SLA</th>
                    <?php endif; ?>
                    <th class="text-left p-3 whitespace-nowrap">Asignado a</th>
                    <th class="text-center p-3 whitespace-nowrap">Acciones</th>
                </tr>
                </thead>

                <tbody class="text-white divide-y divide-slate-800">
               <?php
                  if (!empty($tickets)) {
                      echo renderizarFilasTabla($camposTickets, $tickets);
                  } else {
                      $cols = $canViewSla ? 10 : 9;
                      echo '<tr><td colspan="' . $cols . '" class="p-8 text-center text-slate-400">No se encontraron tickets con los criterios de búsqueda especificados.</td></tr>';
                  }
               ?>
                </tbody>
            </table>
            </div>
            
          </main>
        </div>
      </div>

      <!-- Background Glow -->
      <div
        class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"
      ></div>

      <div
        class="absolute bottom-0 left-0 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"
      ></div>
    </div>
  </div>

  <!-- Toast Container para Alertas Elegantes -->
  <style>
    #toast-container {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-width: 480px;
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

  <!-- Modales del Sistema -->
  <?php
  if ($mostrarTicketModal) {
      createTicket($ticketModalData);
  }

  if (isset($_POST["ver_historial"])) {
      mostrarModalHistorial($conn, intval($_POST["ver_historial"]));
  } else if (isset($_GET["ver_historial"])) {
      mostrarModalHistorial($conn, intval($_GET["ver_historial"]));
  }
  ?>

  <script>
  function filtrarPorSla(valor) {
      const select = document.getElementById("slaBuscar");
      if (select) {
          select.value = valor;
          select.form.submit();
      } else {
          window.location.href = "ticketManegement.php?slaBuscar=" + encodeURIComponent(valor);
      }
  }

  function actualizarResumenSLA() {
      const ind = document.getElementById("sla-live-indicator");
      if (ind) ind.style.opacity = "0.5";
      fetch("api_sla.php?action=counters")
          .then(res => res.json())
          .then(data => {
              const c = data ? (data.counters || data.data) : null;
              if (c) {
                  const v = document.getElementById("cnt-sla-vencidos");
                  const w = document.getElementById("cnt-sla-warning");
                  const t = document.getElementById("cnt-sla-tiempo");
                  if (v && c.vencidos !== undefined) v.textContent = c.vencidos;
                  if (w && c.warning !== undefined) w.textContent = c.warning;
                  if (t && c.en_tiempo !== undefined) t.textContent = c.en_tiempo;
              }
          })
          .catch(err => console.error("Error al actualizar métricas SLA:", err))
          .finally(() => {
              if (ind) ind.style.opacity = "1";
          });
  }

  function invocarReasignarAgente(ticketId) {
      const select = document.getElementById("select-agente-" + ticketId);
      const feedback = document.getElementById("feedback-reasignar-" + ticketId);
      const display = document.getElementById("agente-display-" + ticketId);
      const cell = document.getElementById("td-asig-" + ticketId);

      if (!select || !select.value) {
          alert("Por favor selecciona un agente para reasignar.");
          return;
      }

      const idAgente = select.value;
      if (feedback) {
          feedback.className = "text-[10px] mt-0.5 text-cyan-400 font-semibold";
          feedback.textContent = "Reasignando...";
          feedback.classList.remove("hidden");
      }

      fetch("api_sla.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "reassign", ticket_id: ticketId, idAgente: idAgente })
      })
      .then(res => res.json())
      .then(data => {
          if (data && data.success) {
              if (display) display.textContent = idAgente;
              if (cell) {
                  cell.classList.add("row-highlight-reassigned");
                  setTimeout(() => cell.classList.remove("row-highlight-reassigned"), 2000);
              }
              if (feedback) {
                  feedback.className = "text-[10px] mt-0.5 text-emerald-400 font-semibold";
                  feedback.textContent = "✓ Reasignado con éxito";
                  setTimeout(() => feedback.classList.add("hidden"), 3000);
              }
              actualizarResumenSLA();
          } else {
              if (feedback) {
                  feedback.className = "text-[10px] mt-0.5 text-rose-400 font-semibold";
                  feedback.textContent = "✕ " + (data.error || "Error al reasignar");
              }
          }
      })
      .catch(err => {
          if (feedback) {
              feedback.className = "text-[10px] mt-0.5 text-rose-400 font-semibold";
              feedback.textContent = "✕ Error de conexión";
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
  });
  </script>
</body>
</html>
