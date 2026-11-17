<?php
ob_start();
// 1. Simulación de datos desde la base de datos (Backend PHP)

// Variables para el Badge del Sidebar
session_start();
require_once("funcs/session_security.php");
require_once("db.php");
require_once("funcs/feedbackFunc.php");
inicializarBaseDeDatos($conn);

if (empty($_SESSION["currentTeam"]) || ($_SESSION["currentTeam"] !== "monitoreo" && $_SESSION["currentTeam"] !== "administrador")) {
    http_response_code(403);
    die("Acceso denegado: Este módulo es exclusivo para el Equipo de Monitoreo.");
}

function setChange($num1, $num2)
{
    if ($num2 == 0) {
        return $num1 > 0 ? 100 : 0;
    }

    $VariableParaReturn = round((($num1 - $num2) / $num2) * 100, 0);
    return $VariableParaReturn;


}
;

function setPorcentajes($num1, $num2)
{
    if ($num2 == 0) {
        return 0;
    }
    $VariableParaReturn = round(($num1 / $num2) * 100, 0);
    return $VariableParaReturn;
}


$primerDiaMes = date('Y-m-01');
$primerDiaMesAnterior = date('Y-m-01', strtotime('-1 month'));

$feedbackQuery = mysqli_query($conn, "
    SELECT
    SUM(CASE WHEN fecha >= '$primerDiaMes' THEN 1 ELSE 0 END) AS feedback,
    SUM(CASE WHEN fecha >= '$primerDiaMesAnterior' AND fecha < '$primerDiaMes' THEN 1 ELSE 0 END) AS feedbackMesAnterior
    FROM feedbacks
    WHERE estado != 'inactivo'
");

$datosFB = mysqli_fetch_assoc($feedbackQuery);
$feedbackRecibido = $datosFB["feedback"] ?? 0;
$feedbackMesAnterior = $datosFB["feedbackMesAnterior"] ?? 0;

$query = mysqli_query($conn, "
    SELECT
        -- Cerrados este mes vs mes anterior (Resueltos o Cerrados)
        SUM(CASE WHEN LOWER(estado) IN ('cerrado', 'resuelto') AND fecha_cierre >= '$primerDiaMes' THEN 1 ELSE 0 END) AS cerrados,
        SUM(CASE WHEN LOWER(estado) IN ('cerrado', 'resuelto') AND fecha_cierre >= '$primerDiaMesAnterior' AND fecha_cierre < '$primerDiaMes' THEN 1 ELSE 0 END) AS cerradosMesAnterior,

        -- Abiertos / activos en atención (Nuevo, Asignado, En Progreso, En Espera)
        SUM(CASE WHEN LOWER(estado) IN ('nuevo', 'asignado', 'en progreso', 'en espera', 'abierto') THEN 1 ELSE 0 END) AS abiertos,
        SUM(CASE WHEN LOWER(estado) IN ('nuevo', 'asignado', 'en progreso', 'en espera', 'abierto') AND fecha_apertura < '$primerDiaMes' THEN 1 ELSE 0 END) AS abiertosMesAnterior,

        COUNT(IF(fecha_apertura >= '$primerDiaMes', id, NULL)) AS totalMes,
        SUM(CASE WHEN prioridad = 'baja' AND LOWER(estado) IN ('nuevo', 'asignado', 'en progreso', 'en espera', 'abierto') THEN 1 ELSE 0 END) AS prioBaja,
        SUM(CASE WHEN prioridad = 'media' AND LOWER(estado) IN ('nuevo', 'asignado', 'en progreso', 'en espera', 'abierto') THEN 1 ELSE 0 END) AS prioMedia,
        SUM(CASE WHEN prioridad = 'alta' AND LOWER(estado) IN ('nuevo', 'asignado', 'en progreso', 'en espera', 'abierto') THEN 1 ELSE 0 END) AS prioAlta,
        SUM(CASE WHEN prioridad = 'critica' AND LOWER(estado) IN ('nuevo', 'asignado', 'en progreso', 'en espera', 'abierto') THEN 1 ELSE 0 END) AS prioCritica
    FROM tickets
    WHERE LOWER(estado) NOT IN ('inactivo', 'cancelado')
");

$datosDB = mysqli_fetch_assoc($query);
$totalMes = $datosDB["totalMes"];

$prioridad_data = [
    'Baja' => $datosDB["prioBaja"] ?? 0,
    'Media' => $datosDB["prioMedia"] ?? 0,
    'Alta' => $datosDB["prioAlta"] ?? 0,
    'Critica' => $datosDB["prioCritica"] ?? 0,
];

$prioridadColors = [
    'Baja' => '#10b981',
    'Media' => '#facc15',
    'Alta' => '#fb923c',
    'Critica' => '#ef4444'
];

$ticketsAbiertos = $datosDB['abiertos'] ?? 0;
$ticketsAbiertosMesAnterior = $datosDB['abiertosMesAnterior'] ?? 0;

$ticketsCerrados = $datosDB['cerrados'] ?? 0;
$ticketsCerradosMesAnterior = $datosDB['cerradosMesAnterior'] ?? 0;

$graficoQuery = mysqli_query($conn, "
    SELECT
        -- Semana 1
        SUM(CASE WHEN DAYOFMONTH(fecha) BETWEEN 1 AND 7 AND sentimiento = 'positivo' THEN 1 ELSE 0 END) AS pos_S1,
        SUM(CASE WHEN DAYOFMONTH(fecha) BETWEEN 1 AND 7 AND sentimiento = 'negativo' THEN 1 ELSE 0 END) AS neg_S1,

        -- Semana 2
        SUM(CASE WHEN DAYOFMONTH(fecha) BETWEEN 8 AND 14 AND sentimiento = 'positivo' THEN 1 ELSE 0 END) AS pos_S2,
        SUM(CASE WHEN DAYOFMONTH(fecha) BETWEEN 8 AND 14 AND sentimiento = 'negativo' THEN 1 ELSE 0 END) AS neg_S2,

        -- Semana 3
        SUM(CASE WHEN DAYOFMONTH(fecha) BETWEEN 15 AND 21 AND sentimiento = 'positivo' THEN 1 ELSE 0 END) AS pos_S3,
        SUM(CASE WHEN DAYOFMONTH(fecha) BETWEEN 15 AND 21 AND sentimiento = 'negativo' THEN 1 ELSE 0 END) AS neg_S3,

        -- Semana 4
        SUM(CASE WHEN DAYOFMONTH(fecha) >= 22 AND sentimiento = 'positivo' THEN 1 ELSE 0 END) AS pos_S4,
        SUM(CASE WHEN DAYOFMONTH(fecha) >= 22 AND sentimiento = 'negativo' THEN 1 ELSE 0 END) AS neg_S4

    FROM feedbacks
    WHERE fecha >= '$primerDiaMes'
      AND estado != 'inactivo'
");

$row = mysqli_fetch_assoc($graficoQuery);

// Consulta para Actividad Reciente (HU 22)
$actividadQuery = mysqli_query($conn, "
    SELECT 
        'Incidencia' AS tipo,
        titulo,
        fecha_apertura AS fecha,
        estado
    FROM tickets
    WHERE estado != 'inactivo'
    
    UNION ALL
    
    SELECT 
        'Ticket' AS tipo,
        titulo,
        fecha_cierre AS fecha,
        estado
    FROM tickets
    WHERE fecha_cierre IS NOT NULL AND estado != 'inactivo'
    
    UNION ALL
    
    SELECT 
        'Opinión' AS tipo,
        tema AS titulo,
        fecha,
        estado
    FROM feedbacks
    WHERE estado != 'inactivo'
    
    ORDER BY fecha DESC
    LIMIT 6
");
$actividades = [];
if ($actividadQuery) {
    $actividades = mysqli_fetch_all($actividadQuery, MYSQLI_ASSOC);
}

function tiempoTranscurrido($fecha)
{
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;

    if ($diferencia < 60) {
        return "Hace " . max(1, $diferencia) . " seg";
    } elseif ($diferencia < 3600) {
        return "Hace " . floor($diferencia / 60) . " min";
    } elseif ($diferencia < 86400) {
        return "Hace " . floor($diferencia / 3600) . " horas";
    } else {
        return "Hace " . floor($diferencia / 86400) . " días";
    }
}

function getEstadoBadgeClass($estado)
{
    $estadoStr = strtolower(trim($estado));
    if (in_array($estadoStr, ['nuevo', 'abierto', 'nueva']))
        return 'badge-nuevo';
    if (in_array($estadoStr, ['asignado']))
        return 'badge-asignado';
    if (in_array($estadoStr, ['en progreso']))
        return 'badge-en-progreso';
    if (in_array($estadoStr, ['en espera']))
        return 'badge-en-espera';
    if (in_array($estadoStr, ['resuelto']))
        return 'badge-resuelto';
    if (in_array($estadoStr, ['cerrado']))
        return 'badge-cerrado';
    if (in_array($estadoStr, ['cancelado', 'inactivo']))
        return 'badge-cancelado';
    if (in_array($estadoStr, ['revisado']))
        return 'badge-revisado';
    if (in_array($estadoStr, ['implementado', 'implementada']))
        return 'badge-implementado';
    return 'badge-nuevo';
}


$activasQuery = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN fecha_creacion >= '$primerDiaMes' AND estado != 'cerrado' THEN 1 ELSE 0 END) AS abiertas,
        SUM(CASE WHEN fecha_creacion >= '$primerDiaMesAnterior' AND fecha_creacion < '$primerDiaMes' AND estado != 'cerrado' THEN 1 ELSE 0 END) AS abiertasMesAnterior,
        SUM(CASE WHEN (fecha_cierre >= '$primerDiaMes' OR (fecha_cierre IS NULL AND fecha_creacion >= '$primerDiaMes')) AND estado = 'cerrado' THEN 1 ELSE 0 END) AS cerradas,
        SUM(CASE WHEN ((fecha_cierre >= '$primerDiaMesAnterior' AND fecha_cierre < '$primerDiaMes') OR (fecha_cierre IS NULL AND fecha_creacion >= '$primerDiaMesAnterior' AND fecha_creacion < '$primerDiaMes')) AND estado = 'cerrado' THEN 1 ELSE 0 END) AS cerradasMesAnterior
    FROM incidencias
");
$datosActivas = mysqli_fetch_assoc($activasQuery);
$incidenciasAbiertas = $datosActivas["abiertas"] ?? 0;
$incidenciasAbiertasMesAnterior = $datosActivas["abiertasMesAnterior"] ?? 0;
$incidenciasCerradas = $datosActivas["cerradas"] ?? 0;
$incidenciasCerradasMesAnterior = $datosActivas["cerradasMesAnterior"] ?? 0;


// KPIs
$kpis = [
    [
        'title' => 'Incidencias Abiertas',
        'value' => $incidenciasAbiertas,
        'change' => setChange($incidenciasAbiertas, $incidenciasAbiertasMesAnterior),
        'period' => 'vs mes anterior',
        'is_positive' => null,
        'icon' => '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
    ],
    [
        'title' => 'Incidencias Cerradas',
        'value' => $incidenciasCerradas,
        'change' => setChange($incidenciasCerradas, $incidenciasCerradasMesAnterior),
        'period' => 'vs mes anterior',
        'is_positive' => null,
        'icon' => '<svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
    ],
    [
        'title' => 'Tickets Abiertos',
        'value' => $ticketsAbiertos,
        'change' => setChange($ticketsAbiertos, $ticketsAbiertosMesAnterior),
        'period' => 'vs mes anterior',
        'is_positive' => null,
        'icon' => '<svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
    ],
    [
        'title' => 'Tickets Cerrados',
        'value' => $ticketsCerrados,
        'change' => setChange($ticketsCerrados, $ticketsCerradosMesAnterior),
        'period' => 'Este mes',
        'is_positive' => null,
        'icon' => '<svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
    ],
    [
        'title' => 'Tablero de Calidad',
        'value' => $feedbackRecibido,
        'change' => setChange($feedbackRecibido, $feedbackMesAnterior),
        'period' => 'vs mes anterior',
        'is_positive' => null,
        'icon' => '<svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>'
    ]
];

// Datos para los gráficos (dinámicos basándose en la tabla incidencias y su fecha_cierre)
$mesesNombres = [
    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
];

$meses = [];
$cerradas_data = [];
$abiertas_data = [];

for ($i = 4; $i >= 0; $i--) {
    $time = strtotime("-$i month");
    $mesNum = (int)date('n', $time);
    $anio = date('Y', $time);
    $meses[] = $mesesNombres[$mesNum];

    // Consultar incidencias abiertas para este año y mes (creadas en el mes y aún abiertas)
    $queryAbiertas = mysqli_query($conn, "
        SELECT COUNT(id) AS total 
        FROM incidencias 
        WHERE YEAR(fecha_creacion) = '$anio' 
          AND MONTH(fecha_creacion) = '$mesNum'
          AND estado != 'cerrado'
    ");
    $rowAbiertas = mysqli_fetch_assoc($queryAbiertas);
    $abiertas_data[] = (int)($rowAbiertas['total'] ?? 0);

    // Consultar incidencias cerradas para este año y mes basándose en la fecha_cierre de incidencias
    $queryCerradas = mysqli_query($conn, "
        SELECT COUNT(id) AS total 
        FROM incidencias 
        WHERE estado = 'cerrado'
          AND YEAR(COALESCE(fecha_cierre, fecha_creacion)) = '$anio' 
          AND MONTH(COALESCE(fecha_cierre, fecha_creacion)) = '$mesNum'
    ");
    $rowCerradas = mysqli_fetch_assoc($queryCerradas);
    $cerradas_data[] = (int)($rowCerradas['total'] ?? 0);
}

$trend_positivo = [
    $row['pos_S1'] ?? 0,
    $row['pos_S2'] ?? 0,
    $row['pos_S3'] ?? 0,
    $row['pos_S4'] ?? 0
];

$trend_negativo = [
    $row['neg_S1'] ?? 0,
    $row['neg_S2'] ?? 0,
    $row['neg_S3'] ?? 0,
    $row['neg_S4'] ?? 0
];

$feedback_labels = ['S1', 'S2', 'S3', 'S4'];

// ==========================================
// HU 35: MATRIZ DE CALIDAD CRUZADA (CSAT VS QA)
// ==========================================
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

// Helper para graficos SVG circulares (de torta solidos)
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
        
        // Slices with hover effect and inline JS interaction
        $slicesHtml .= '<circle cx="100" cy="100" r="60" class="pie-slice" ' .
                       'style="stroke: ' . $color . '; stroke-width: 36px; fill: none; stroke-dasharray: ' . $dashSize . ' ' . $circumference . '; stroke-dashoffset: -' . $offset . '; transition: stroke-dasharray 0.3s ease, stroke-width 0.2s ease, opacity 0.2s ease; opacity: 0.85;" ' .
                       'onmouseover="updateChartCenter(\'' . $chartId . '\', \'' . htmlspecialchars($key) . '\', \'' . $roundedPercentage . '%\', \'' . $color . '\')" ' .
                       'onmouseout="resetChartCenter(\'' . $chartId . '\', \'Total\', \'' . $total . '\')" />';
        
        $offset += $dashSize;
    }
    
    $slicesHtml .= '</g>';
    
    // Add central labels
    $labelsHtml = '<text id="lbl-title-' . $chartId . '" x="100" y="92" fill="#94a3b8" text-anchor="middle" style="font-family: system-ui, -apple-system, sans-serif; font-size: 13px; font-weight: 500; pointer-events: none; transition: fill 0.2s ease;">Total</text>';
    $labelsHtml .= '<text id="lbl-value-' . $chartId . '" x="100" y="118" fill="#ffffff" text-anchor="middle" style="font-family: system-ui, -apple-system, sans-serif; font-size: 20px; font-weight: 700; pointer-events: none;">' . $total . '</text>';
    
    $html = '<svg width="240" height="240" viewBox="0 0 200 200" style="max-width: 240px; width: 100%; height: 240px; display: block; margin: 0 auto;">';
    $html .= $slicesHtml;
    $html .= $labelsHtml;
    $html .= '</svg>';
    
    return $html;
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Panel de Control</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        html {
            color-scheme: dark;
            scrollbar-width: thin;
            scrollbar-color: #334155 #0b1121;
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background-color: #0b1121;
        }
        ::-webkit-scrollbar-thumb {
            background-color: #334155;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background-color: #475569;
        }

        .contenedor-acciones-top {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .btn-exportar {
            background-color: transparent !important;
            border: 1px solid #334155 !important;
            color: #e2e8f0 !important;
            font-weight: 500 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            border-radius: 6px !important;
            padding: 8px 16px !important;
            cursor: pointer !important;
            transition: all 0.2s ease-in-out !important;
            text-decoration: none !important;
        }
        .btn-exportar:hover {
            background-color: rgba(51, 65, 85, 0.3) !important;
            border-color: #475569 !important;
        }

        .contenedor-acciones-tabla {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }

        .btn-accion-icono {
            background: transparent !important;
            border: none !important;
            padding: 4px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: transform 0.2s ease, opacity 0.2s ease !important;
            font-size: 1.1rem !important;
            text-decoration: none !important;
        }
        .btn-accion-icono:hover {
            transform: scale(1.15) !important;
            opacity: 0.8 !important;
        }

        .icono-ver {
            color: #06b6d4 !important;
        }

        .icono-eliminar {
            color: #f87171 !important;
        }

        body {
            color: #e2e8f0;
        }

        .pie-slice {
            cursor: pointer;
        }
        .pie-slice:hover {
            stroke-width: 44px !important;
            opacity: 1 !important;
        }

        .card-bg {
            background-color: #1e293b;
            border-color: #334155;
        }

        /* Badges de Estado */
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
            background-color: rgb(23, 37, 84) !important;
            color: #60a5fa !important;
            border-color: #1e40af !important;
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

        .badge-cancelado {
            background-color: rgba(225, 29, 72, 0.22) !important;
            color: #fda4af !important;
            border-color: #be123c !important;
        }

        .badge-revisado {
            background-color: rgb(66, 32, 6) !important;
            color: #fef08a !important;
            border-color: #ca8a04 !important;
        }

        .badge-implementado {
            background-color: rgb(8, 47, 73) !important;
            color: #38bdf8 !important;
            border-color: #0369a1 !important;
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
        .badge-estado-revisado {
            background-color: rgb(66, 32, 6) !important;
            color: #facc15 !important;
            border-color: #854d0e !important;
        }

        /* Custom scrollbar for Actividad Reciente container */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #334155 transparent;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #475569;
        }

        /* ============================================================== */
        /* HU 35: ESTILOS DEDICADOS PARA MATRIZ DE CALIDAD CRUZADA         */
        /* ============================================================== */
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

<body class="bg-slate-950 text-white overflow-hidden">
    <div id="root">
        <div class="h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 overflow-hidden">
            <!-- Background -->
            <div class="absolute inset-0 opacity-5">
                <div class="absolute inset-0" style="
            background-image: radial-gradient(
              circle at 2px 2px,
              rgb(16, 185, 129) 1px,
              transparent 0px
            );
            background-size: 40px 40px;
          "></div>
            </div>

            <div class="relative h-full flex">
                <!-- Sidebar -->
                <aside
                    class="w-64 bg-slate-900/90 backdrop-blur-sm border-r border-emerald-900/30 transition-all duration-300 flex flex-col">
                    <!-- Logo -->
                    <?php require_once("templates\logo.php") ?>

            </div>
        </div>

        <!-- Badge -->
        <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-3">
            <span
                class="flex w-full items-center justify-center rounded-md <?php echo $sidebarBadgeClass; ?> px-2 py-1 text-xs font-medium text-white">
                <?php echo $sidebarBadgeText; ?>
            </span>
        </div>

        <!-- Navigation -->
        <?php require_once("templates\sidebar.php") ?>

        <!-- Footer -->
        <div class="p-4 border-t border-slate-800">
            <button
                class="w-full flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 rounded-md py-2 transition-all">
                ✕
            </button>
        </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <?php require_once("templates\header.php") ?>

            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-7xl mx-auto space-y-6">

                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-white">Panel de Control</h1>
                            <p class="text-sm text-slate-400 mt-1">Vista general del sistema de gestión</p>
                        </div>
                        <div class="contenedor-acciones-top">
                            <a 
                                href="funcs/exportar-dashboard.php"
                                class="btn-exportar"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                                    <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a.5.5 0 0 1-.5-.5zM5.884 6.68 8 9.5l2.116-2.82a.25.25 0 0 1 .416.148l.49 1.956.452-1.8c.045-.18.2-.314.385-.314h.342a.25.25 0 0 1 .23.344l-.8 2a.25.25 0 0 1-.456-.01L10.3 8.3l-.364 1.455a.25.25 0 0 1-.482.01L7.26 6.81a.25.25 0 0 0-.46.024l-.534 2.136a.25.25 0 0 1-.485-.012L5.3 7.8l-.3 1.2a.25.25 0 0 1-.485-.012l-.8-3a.25.25 0 0 1 .37-.267h.342c.18 0 .338.125.388.3l.423 1.692.42-1.68a.25.25 0 0 1 .425-.136z"/>
                                </svg>
                                <span>Exportar Excel</span>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                        <?php foreach ($kpis as $kpi): ?>
                            <div class="card-bg border rounded-xl p-5 flex flex-col justify-between shadow-lg">
                                <div class="flex justify-between items-start">
                                    <h3 class="text-sm font-medium text-slate-300"><?= htmlspecialchars($kpi['title']) ?>
                                    </h3>
                                    <?= $kpi['icon'] ?>
                                </div>
                                <div class="mt-4">
                                    <p class="text-3xl font-bold text-white"><?= htmlspecialchars($kpi['value']) ?></p>
                                    <div class="flex items-center mt-2 text-xs">
                                        <?php if ($kpi['change']): ?>
                                            <?php
                                            $colorClass = $kpi['change'] > 0 ? 'text-emerald-400' : 'text-red-400';
                                            $arrow = $kpi['change'] < 0 ? '↘' : '↗';
                                            ?>
                                            <span class="<?= $colorClass ?> font-medium flex items-center gap-1">
                                                <?= $arrow ?>         <?= htmlspecialchars($kpi['change']) ?>%
                                            </span>
                                        <?php else: ?>
                                            <svg class="w-3 h-3 text-slate-400 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                                </path>
                                            </svg>
                                        <?php endif; ?>
                                        <span class="text-slate-400 ml-1"><?= htmlspecialchars($kpi['period']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ============================================================== -->
                    <!-- HU 35: MATRIZ DE CALIDAD CRUZADA & INDICADORES ESTRATÉGICOS SLA -->
                    <!-- ============================================================== -->
                    <div class="space-y-4 pt-2">
                        <!-- Header de la Sección Estratégica -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 p-4 sm:p-5 rounded-xl border border-slate-700/80 shadow-md">
                            <div class="flex items-center gap-3.5">
                                <div class="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shrink-0">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base sm:text-lg font-bold text-white tracking-wide">Matriz de Calidad Cruzada</h2>
                                    <p class="text-xs text-slate-400 mt-1 leading-normal">
                                        Monitoreo global centralizado: Satisfacción del Cliente (CSAT) vs. Calidad Interna Auditada (QA Score)
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="feedback.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-medium text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700/80 border border-slate-600 rounded-lg transition-all shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                    <span>Ir al Tablero de Calidad</span>
                                </a>
                            </div>
                        </div>

                        <!-- Criterio 2: Tarjetas con los KPIs Estratégicos -->
                        <div class="cruzada-kpi-grid">
                            <!-- 1. % Promedio de QA Score -->
                            <div class="cruzada-card cruzada-card-qa">
                                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/5 rounded-full blur-xl pointer-events-none"></div>
                                <div>
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Calidad Interna Auditada</span>
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
                                        <span>Muestra evaluada: <strong class="text-slate-200 font-semibold"><?= $kpisCruzada['total_qa'] ?> auditorías</strong></span>
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
                                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block">Percepción del Usuario</span>
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
                                        <span>Respuestas analizadas: <strong class="text-slate-200 font-semibold"><?= $kpisCruzada['total_feedback'] ?> feedbacks</strong></span>
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
                                        <canvas id="crossQualityChart"></canvas>
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
                                    <a href="feedback.php" class="text-emerald-400 hover:text-emerald-300 font-medium self-end sm:self-auto transition-colors inline-flex items-center gap-1">
                                        <span>Ver planes CAPA</span>
                                        <span>&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="card-bg border rounded-xl p-5 shadow-lg flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-slate-300 mb-4">Tablero de Incidencia por Mes</h3>
                                <div class="relative h-56 w-full">
                                    <canvas id="barChart"></canvas>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: center; align-items: center; gap: 24px; margin-top: 15px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="width: 12px; height: 12px; border-radius: 2px; background-color: #ef4444; display: inline-block;"></span>
                                    <span style="font-size: 14px; color: #e2e8f0; font-weight: normal;">Abiertas</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="width: 12px; height: 12px; border-radius: 2px; background-color: #10b981; display: inline-block;"></span>
                                    <span style="font-size: 14px; color: #e2e8f0; font-weight: normal;">Cerradas</span>
                                </div>
                            </div>
                        </div>

                        <div class="card-bg border rounded-xl p-5 shadow-lg">
                            <h3 class="text-sm font-medium text-slate-300 mb-4">Tickets por Prioridad (%)</h3>
                            <div class="relative w-full flex flex-col justify-center items-center">
                                <div class="relative h-56 w-full flex justify-center items-center">
                                    <?php echo generateSVGPieChart($prioridad_data, $prioridadColors, 'prioridad'); ?>
                                </div>
                                <div class="mt-4 flex flex-wrap justify-center gap-x-8 gap-y-2 text-xs">
                                    <?php 
                                    $totalPrioridad = array_sum($prioridad_data);
                                    foreach ($prioridad_data as $key => $count): 
                                        $color = $prioridadColors[$key] ?? '#64748b';
                                        $percentage = $totalPrioridad > 0 ? round(($count / $totalPrioridad) * 100) : 0;
                                    ?>
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-block w-3 h-3 rounded-sm" style="background-color: <?php echo $color; ?>;"></span>
                                            <span class="text-slate-300 font-bold"><?php echo htmlspecialchars($key); ?>:</span>
                                            <span class="text-white font-normal"><?php echo $percentage; ?>%</span>
                                            <span class="text-slate-500">(<?php echo $count; ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 card-bg border rounded-xl p-5 shadow-lg flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-slate-300 mb-4">Tendencia de Tablero de Calidad (Último Mes)</h3>
                                <div class="relative h-44 w-full">
                                    <canvas id="lineChart"></canvas>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: center; align-items: center; gap: 30px; margin-top: 15px;">
                                <!-- Positivo -->
                                <div style="display: flex; align-items: center; gap: 8px; font-weight: 500; color: #10b981;">
                                    <div style="position: relative; width: 24px; height: 12px; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 100%; height: 2px; background-color: currentColor;"></div>
                                        <div style="position: absolute; width: 8px; height: 8px; border-radius: 50%; border: 2px solid currentColor; background-color: #1e293b;"></div>
                                    </div>
                                    <span>Positivo</span>
                                </div>
                                <!-- Negativo -->
                                <div style="display: flex; align-items: center; gap: 8px; font-weight: 500; color: #ef4444;">
                                    <div style="position: relative; width: 24px; height: 12px; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 100%; height: 2px; background-color: currentColor;"></div>
                                        <div style="position: absolute; width: 8px; height: 8px; border-radius: 50%; border: 2px solid currentColor; background-color: #1e293b;"></div>
                                    </div>
                                    <span>Negativo</span>
                                </div>
                            </div>
                        </div>

                        <!-- Módulo de Actividad Reciente -->
                        <div class="card-bg border rounded-xl p-5 shadow-lg flex flex-col h-full max-h-[300px]">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-slate-300">Actividad Reciente</h3>
                                <span class="flex h-2 w-2 relative">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                            </div>
                            <div class="flex-1 overflow-y-auto pr-2 space-y-4 custom-scrollbar">
                                <?php if (!empty($actividades)): ?>
                                    <?php foreach ($actividades as $actividad): ?>
                                        <div
                                            class="flex items-start gap-3 border-b border-slate-700/50 pb-3 last:border-0 last:pb-0">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <?php
                                                    $tipoColor = 'text-emerald-400 bg-emerald-400/10';
                                                    if ($actividad['tipo'] == 'Ticket')
                                                        $tipoColor = 'text-blue-400 bg-blue-400/10';
                                                    if ($actividad['tipo'] == 'Opinión')
                                                        $tipoColor = 'text-purple-400 bg-purple-400/10';
                                                    ?>
                                                    <span
                                                        class="text-[10px] uppercase tracking-wider font-semibold px-1.5 py-0.5 rounded <?= $tipoColor ?>">
                                                        <?= htmlspecialchars($actividad['tipo']) ?>
                                                    </span>
                                                    <span class="text-xs text-slate-500 whitespace-nowrap">
                                                        <?= tiempoTranscurrido($actividad['fecha']) ?>
                                                    </span>
                                                </div>
                                                <p class="text-sm text-slate-200 truncate"
                                                    title="<?= htmlspecialchars($actividad['titulo']) ?>">
                                                    <?= htmlspecialchars($actividad['titulo']) ?>
                                                </p>
                                            </div>
                                            <span
                                                class="badge-custom <?= getEstadoBadgeClass($actividad['estado']) ?> whitespace-nowrap mt-1">
                                                <?= ucfirst(htmlspecialchars($actividad['estado'])) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-sm text-slate-500 text-center py-4">No hay actividad reciente.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Background Glow -->
    <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="absolute bottom-0 left-0 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
</div>
</div>

    <script>
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = '#334155';

        // 1. Gráfico de Barras
        new Chart(document.getElementById('barChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($meses) ?>,
                datasets: [
                    {
                        label: 'Abiertas',
                        data: <?= json_encode($abiertas_data) ?>,
                        backgroundColor: '#ef4444',
                        barThickness: 16
                    },
                    {
                        label: 'Cerradas',
                        data: <?= json_encode($cerradas_data) ?>,
                        backgroundColor: '#10b981',
                        barThickness: 16
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // 2. Funciones para Gráfico Circular SVG
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

        // 3. Gráfico de Líneas
        new Chart(document.getElementById('lineChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($feedback_labels) ?>,
                datasets: [
                    {
                        label: 'Positivo',
                        data: <?= json_encode($trend_positivo) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#10b981',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Negativo',
                        data: <?= json_encode($trend_negativo) ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#ef4444',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { display: true }, y: { beginAtZero: true } }
            }
        });

        // 4. HU 35: Gráfico Comparativo de Calidad Cruzada (CSAT vs QA Score por Agente/Equipo)
        const crossCtx = document.getElementById('crossQualityChart');
        if (crossCtx) {
            new Chart(crossCtx.getContext('2d'), {
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
                        legend: {
                            display: false
                        },
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
    </script>
</body>

</html>