<?php
require_once("../db.php");
session_start();
require_once("session_security.php");
require_once("feedbackFunc.php");

inicializarBaseDeDatos($conn);

if (empty($_SESSION["currentTeam"]) || ($_SESSION["currentTeam"] !== "monitoreo" && $_SESSION["currentTeam"] !== "administrador")) {
    http_response_code(403);
    die("Acceso denegado: Este módulo es exclusivo para el Equipo de Monitoreo.");
}

$kpisCruzada = obtenerKPIsCalidadCruzada($conn);
$matrizCruzada = obtenerMatrizCalidadCruzadaPorAgente($conn);

// Funciones de cálculo idénticas a las de dashboard.php
function setChange($num1, $num2)
{
    if ($num2 == 0) {
        return $num1 > 0 ? 100 : 0;
    }
    return round((($num1 - $num2) / $num2) * 100, 0);
}

function setPorcentajes($num1, $num2)
{
    if ($num2 == 0) {
        return 0;
    }
    return round(($num1 / $num2) * 100, 0);
}

$primerDiaMes = date('Y-m-01');
$primerDiaMesAnterior = date('Y-m-01', strtotime('-1 month'));

// 1. Consulta de Feedback
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

// 2. Consulta de Tickets
$query = mysqli_query($conn, "
    SELECT
        -- Cerrados este mes vs mes anterior (basado en la fecha de cierre de cualquier ticket cerrado en el mes)
        SUM(CASE WHEN estado = 'cerrado' AND fecha_cierre >= '$primerDiaMes' THEN 1 ELSE 0 END) AS cerrados,
        SUM(CASE WHEN estado = 'cerrado' AND fecha_cierre >= '$primerDiaMesAnterior' AND fecha_cierre < '$primerDiaMes' THEN 1 ELSE 0 END) AS cerradosMesAnterior,

        -- Abiertos/en progreso en general (tickets actualmente activos/abiertos/en progreso)
        SUM(CASE WHEN (estado = 'abierto' OR estado = 'en progreso') THEN 1 ELSE 0 END) AS abiertos,
        SUM(CASE WHEN (estado = 'abierto' OR estado = 'en progreso') AND fecha_apertura < '$primerDiaMes' THEN 1 ELSE 0 END) AS abiertosMesAnterior,

        COUNT(IF(fecha_apertura >= '$primerDiaMes', id, NULL)) AS totalMes,
        SUM(CASE WHEN prioridad = 'baja' AND (estado = 'abierto' OR estado = 'en progreso') THEN 1 ELSE 0 END) AS prioBaja,
        SUM(CASE WHEN prioridad = 'media' AND (estado = 'abierto' OR estado = 'en progreso') THEN 1 ELSE 0 END) AS prioMedia,
        SUM(CASE WHEN prioridad = 'alta' AND (estado = 'abierto' OR estado = 'en progreso') THEN 1 ELSE 0 END) AS prioAlta,
        SUM(CASE WHEN prioridad = 'critica' AND (estado = 'abierto' OR estado = 'en progreso') THEN 1 ELSE 0 END) AS prioCritica
    FROM tickets    
    WHERE estado != 'inactivo'
");

$datosDB = mysqli_fetch_assoc($query);
$totalMes = $datosDB["totalMes"] ?? 0;

$ticketsAbiertos = $datosDB['abiertos'] ?? 0;
$ticketsAbiertosMesAnterior = $datosDB['abiertosMesAnterior'] ?? 0;

$ticketsCerrados = $datosDB['cerrados'] ?? 0;
$ticketsCerradosMesAnterior = $datosDB['cerradosMesAnterior'] ?? 0;

// 3. Consulta de Incidencias (abiertas vs cerradas del mes en curso y anterior)
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

// KPIs estructurados
$kpis = [
    [
        'title' => 'Incidencias Abiertas',
        'value' => $incidenciasAbiertas,
        'change' => setChange($incidenciasAbiertas, $incidenciasAbiertasMesAnterior),
        'period' => 'vs mes anterior',
        'desc' => 'Incidencias creadas este mes que continúan abiertas'
    ],
    [
        'title' => 'Incidencias Cerradas',
        'value' => $incidenciasCerradas,
        'change' => setChange($incidenciasCerradas, $incidenciasCerradasMesAnterior),
        'period' => 'vs mes anterior',
        'desc' => 'Incidencias creadas este mes que ya fueron cerradas'
    ],
    [
        'title' => 'Tickets Abiertos',
        'value' => $ticketsAbiertos,
        'change' => setChange($ticketsAbiertos, $ticketsAbiertosMesAnterior),
        'period' => 'vs mes anterior',
        'desc' => 'Tickets creados y abiertos durante el mes en curso'
    ],
    [
        'title' => 'Tickets Cerrados',
        'value' => $ticketsCerrados,
        'change' => setChange($ticketsCerrados, $ticketsCerradosMesAnterior),
        'period' => 'Este mes',
        'desc' => 'Tickets que han sido resueltos y cerrados este mes'
    ],
    [
        'title' => 'Tablero de Calidad',
        'value' => $feedbackRecibido,
        'change' => setChange($feedbackRecibido, $feedbackMesAnterior),
        'period' => 'vs mes anterior',
        'desc' => 'Feedbacks de usuarios registrados en el sistema este mes'
    ]
];

// 4. Datos de Prioridades (porcentajes reales)
$prioBaja = $datosDB["prioBaja"] ?? 0;
$prioMedia = $datosDB["prioMedia"] ?? 0;
$prioAlta = $datosDB["prioAlta"] ?? 0;
$prioCritica = $datosDB["prioCritica"] ?? 0;

$prioridades = [
    ['label' => 'Baja', 'count' => $prioBaja, 'pct' => setPorcentajes($prioBaja, $totalMes)],
    ['label' => 'Media', 'count' => $prioMedia, 'pct' => setPorcentajes($prioMedia, $totalMes)],
    ['label' => 'Alta', 'count' => $prioAlta, 'pct' => setPorcentajes($prioAlta, $totalMes)],
    ['label' => 'Crítica', 'count' => $prioCritica, 'pct' => setPorcentajes($prioCritica, $totalMes)]
];

// 5. Consulta para Tendencia de Feedback (Mes en Curso)
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
$rowFeedback = mysqli_fetch_assoc($graficoQuery);

$feedbacksSemanas = [
    ['label' => 'Semana 1', 'pos' => $rowFeedback['pos_S1'] ?? 0, 'neg' => $rowFeedback['neg_S1'] ?? 0],
    ['label' => 'Semana 2', 'pos' => $rowFeedback['pos_S2'] ?? 0, 'neg' => $rowFeedback['neg_S2'] ?? 0],
    ['label' => 'Semana 3', 'pos' => $rowFeedback['pos_S3'] ?? 0, 'neg' => $rowFeedback['neg_S3'] ?? 0],
    ['label' => 'Semana 4', 'pos' => $rowFeedback['pos_S4'] ?? 0, 'neg' => $rowFeedback['neg_S4'] ?? 0]
];

// 5.b. Consulta para Histórico de Incidencias por Mes (últimos 5 meses)
$mesesNombres = [
    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
];

$mesesHistorico = [];

for ($i = 4; $i >= 0; $i--) {
    $time = strtotime("-$i month");
    $mesNum = (int)date('n', $time);
    $anio = date('Y', $time);
    
    // Consultar incidencias abiertas para este año y mes (creadas en el mes y aún abiertas)
    $queryRep = mysqli_query($conn, "
        SELECT COUNT(id) AS total 
        FROM incidencias 
        WHERE YEAR(fecha_creacion) = '$anio' 
          AND MONTH(fecha_creacion) = '$mesNum'
          AND estado != 'cerrado'
    ");
    $rowRep = mysqli_fetch_assoc($queryRep);
    $cantAbiertas = (int)($rowRep['total'] ?? 0);

    // Consultar incidencias cerradas para este año y mes basándose en la fecha_cierre de incidencias
    $queryMes = mysqli_query($conn, "
        SELECT COUNT(id) AS total 
        FROM incidencias 
        WHERE estado = 'cerrado'
          AND YEAR(COALESCE(fecha_cierre, fecha_creacion)) = '$anio' 
          AND MONTH(COALESCE(fecha_cierre, fecha_creacion)) = '$mesNum'
    ");
    $rowMes = mysqli_fetch_assoc($queryMes);
    $cantCerradas = (int)($rowMes['total'] ?? 0);
    
    $mesesHistorico[] = [
        'label' => $mesesNombres[$mesNum] . ' ' . $anio,
        'abiertas' => $cantAbiertas,
        'cerradas' => $cantCerradas
    ];
}

// 6. Consulta para Actividad Reciente
$actividadQuery = mysqli_query($conn, "
    SELECT 'Incidencia' AS tipo, titulo, fecha_apertura AS fecha, estado FROM tickets WHERE estado != 'inactivo'
    UNION ALL
    SELECT 'Ticket' AS tipo, titulo, fecha_cierre AS fecha, estado FROM tickets WHERE fecha_cierre IS NOT NULL AND estado != 'inactivo'
    UNION ALL
    SELECT 'Opinión' AS tipo, tema AS titulo, fecha, estado FROM feedbacks WHERE estado != 'inactivo'
    ORDER BY fecha DESC
    LIMIT 6
");
$actividades = [];
if ($actividadQuery) {
    $actividades = mysqli_fetch_all($actividadQuery, MYSQLI_ASSOC);
}

// Configurar cabeceras para descarga de Excel (.xls)
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_dashboard_' . date('Y-m-d_H-i-s') . '.xls"');

// Enviar BOM para compatibilidad con UTF-8
echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Reporte Dashboard</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  table { border-collapse: collapse; margin-bottom: 20px; }
  td, th { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; border: 1px solid #cbd5e1; padding: 6px 10px; }
  
  .title { font-size: 16pt; font-weight: bold; background-color: #0f172a; color: #ffffff; text-align: center; height: 40px; }
  .subtitle { font-size: 11pt; font-weight: bold; background-color: #1e293b; color: #ffffff; text-align: center; height: 25px; }
  
  .section-title { font-size: 11pt; font-weight: bold; background-color: #f1f5f9; color: #0f172a; height: 30px; border-bottom: 2px solid #cbd5e1; }
  .meta-label { font-weight: bold; background-color: #f8fafc; color: #475569; }
  .meta-val { color: #0f172a; }
  
  .kpi-hdr td { font-weight: bold; background-color: #334155; color: #ffffff; text-align: center; }
  .kpi-label { font-weight: bold; background-color: #f8fafc; }
  .kpi-val { font-weight: bold; color: #0f172a; text-align: center; }
  .kpi-change-pos { font-weight: bold; color: #059669; text-align: center; }
  .kpi-change-neg { font-weight: bold; color: #dc2626; text-align: center; }
  .kpi-desc { color: #64748b; font-style: italic; }
  
  /* Grilla Principal */
  .grid-hdr th { background-color: #0f172a; color: #ffffff; font-weight: bold; text-align: center; height: 30px; border: 1px solid #475569; }
  .row-even { background-color: #ffffff; }
  .row-odd { background-color: #f8fafc; }

  /* Badges de estado de actividad */
  .badge-abierto { background-color: #fee2e2; color: #b91c1c; font-weight: bold; text-align: center; }
  .badge-cerrado { background-color: #d1fae5; color: #047857; font-weight: bold; text-align: center; }
  .badge-en-progreso { background-color: #dbeafe; color: #1d4ed8; font-weight: bold; text-align: center; }
  .badge-revisado { background-color: #fef08a; color: #ca8a04; font-weight: bold; text-align: center; }
  .badge-implementado { background-color: #e0f2fe; color: #0369a1; font-weight: bold; text-align: center; }
</style>
</head>
<body>
<table>
  <!-- Banner de Título -->
  <tr>
    <td colspan="7" class="title">TECHNOVA</td>
  </tr>
  <tr>
    <td colspan="7" class="subtitle">REPORTE RESUMIDO DEL PANEL DE CONTROL (DASHBOARD)</td>
  </tr>
  <tr><td colspan="7" style="border:none; height:10px;"></td></tr>
  
  <!-- Información del Reporte -->
  <tr>
    <td colspan="7" class="section-title">INFORMACIÓN DEL REPORTE</td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Fecha de Emisión:</td>
    <td colspan="2" class="meta-val"><?= date('d/m/Y H:i:s') ?></td>
    <td colspan="2" class="meta-label">Usuario Emisor:</td>
    <td colspan="1" class="meta-val"><?= htmlspecialchars($_SESSION["currentUser"]) ?></td>
  </tr>
  <tr><td colspan="7" style="border:none; height:15px;"></td></tr>
  
  <!-- KPIs -->
  <tr>
    <td colspan="7" class="section-title">INDICADORES CLAVE DE RENDIMIENTO (KPIs)</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="2">Indicador / Métrica</td>
    <td>Valor</td>
    <td>Variación</td>
    <td>Período</td>
    <td colspan="2">Descripción de la Métrica</td>
  </tr>
  <?php foreach ($kpis as $kpi): ?>
  <tr>
    <td colspan="2" class="kpi-label"><?= htmlspecialchars($kpi['title']) ?></td>
    <td class="kpi-val"><?= htmlspecialchars($kpi['value']) ?></td>
    <?php if ($kpi['change'] > 0): ?>
      <td class="kpi-change-pos">+<?= htmlspecialchars($kpi['change']) ?>%</td>
    <?php elseif ($kpi['change'] < 0): ?>
      <td class="kpi-change-neg"><?= htmlspecialchars($kpi['change']) ?>%</td>
    <?php else: ?>
      <td style="text-align:center; color:#64748b;">0%</td>
    <?php endif; ?>
    <td style="text-align:center;"><?= htmlspecialchars($kpi['period']) ?></td>
    <td colspan="2" class="kpi-desc"><?= htmlspecialchars($kpi['desc']) ?></td>
  </tr>
  <?php endforeach; ?>
  <tr><td colspan="7" style="border:none; height:15px;"></td></tr>

  <!-- ============================================================== -->
  <!-- HU 35: MATRIZ DE CALIDAD CRUZADA: KPIS ESTRATÉGICOS SLA         -->
  <!-- ============================================================== -->
  <tr>
    <td colspan="7" class="section-title">MATRIZ DE CALIDAD CRUZADA: KPIS ESTRATÉGICOS SLA (HU 35)</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="3">Indicador Estratégico</td>
    <td>Valor Registrado</td>
    <td>Meta SLA</td>
    <td colspan="2">Diagnóstico / Estado</td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">% Promedio de QA Score (Calidad Interna)</td>
    <td class="kpi-val" style="color:#059669; font-weight:bold;"><?= number_format($kpisCruzada['qa_promedio'], 1) ?>%</td>
    <td style="text-align:center; font-weight:bold;">&ge; 80.0%</td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:<?= $kpisCruzada['qa_promedio'] >= 80 ? '#059669' : '#dc2626' ?>;">
      <?= $kpisCruzada['qa_promedio'] >= 80 ? '✓ Cumple SLA' : '⚠️ Bajo Objetivo' ?> (<?= $kpisCruzada['total_qa'] ?> auditorías)
    </td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">% Tasa de Errores Críticos (Auto-Fail)</td>
    <td class="kpi-val" style="color:<?= $kpisCruzada['tasa_errores_criticos'] > 0 ? '#dc2626' : '#059669' ?>; font-weight:bold;"><?= number_format($kpisCruzada['tasa_errores_criticos'], 1) ?>%</td>
    <td style="text-align:center; font-weight:bold;">&lt; 5.0%</td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:<?= $kpisCruzada['tasa_errores_criticos'] <= 5 ? '#059669' : '#dc2626' ?>;">
      <?= $kpisCruzada['tasa_errores_criticos'] == 0 ? '✓ Cero Tolerancia Cumplida' : ($kpisCruzada['tasa_errores_criticos'] <= 5 ? '✓ Tolerable' : '🚨 Alerta Crítica') ?> (<?= $kpisCruzada['total_errores_criticos'] ?> casos)
    </td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">CSAT Promedio (Satisfacción del Cliente)</td>
    <td class="kpi-val" style="color:#0891b2; font-weight:bold;"><?= number_format($kpisCruzada['csat_promedio'], 1) ?>%</td>
    <td style="text-align:center; font-weight:bold;">&ge; 85.0%</td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:<?= $kpisCruzada['csat_promedio'] >= 85 ? '#059669' : '#dc2626' ?>;">
      <?= $kpisCruzada['csat_promedio'] >= 85 ? '✓ Satisfacción Óptima' : '⚠️ Atención Requerida' ?> (<?= $kpisCruzada['total_feedback'] ?> opiniones)
    </td>
  </tr>
  <tr><td colspan="7" style="border:none; height:15px;"></td></tr>

  <!-- HU 35: Matriz Cruzada por Agente / Equipo -->
  <tr>
    <td colspan="7" class="section-title">MATRIZ DE CALIDAD CRUZADA POR AGENTE / EQUIPO (CSAT VS QA SCORE)</td>
  </tr>
  <tr class="grid-hdr">
    <th colspan="2">Agente / Equipo</th>
    <th>CSAT (%)</th>
    <th>QA Score (%)</th>
    <th>Brecha (Gap)</th>
    <th>Err. Críticos</th>
    <th>Diagnóstico SLA</th>
  </tr>
  <?php foreach ($matrizCruzada as $mRow): ?>
  <tr>
    <td colspan="2" class="kpi-label"><?= htmlspecialchars($mRow['nombre_legible']) ?> (<?= htmlspecialchars($mRow['agente']) ?>)</td>
    <td style="text-align:center; font-weight:bold; color:#0891b2;"><?= number_format($mRow['csat_score'], 1) ?>%</td>
    <td style="text-align:center; font-weight:bold; color:#059669;"><?= number_format($mRow['qa_score'], 1) ?>%</td>
    <td style="text-align:center; font-weight:bold;"><?= ($mRow['gap'] > 0 ? '+' : '') . number_format($mRow['gap'], 1) ?>%</td>
    <td style="text-align:center; font-weight:bold; color:<?= $mRow['errores_criticos'] > 0 ? '#dc2626' : '#059669' ?>;"><?= $mRow['errores_criticos'] ?></td>
    <td style="text-align:center; font-weight:bold;"><?= htmlspecialchars($mRow['diagnostico']) ?></td>
  </tr>
  <?php endforeach; ?>
  <tr><td colspan="7" style="border:none; height:15px;"></td></tr>
  
  <!-- Distribución por Prioridades -->
  <tr>
    <td colspan="7" class="section-title">DISTRIBUCIÓN DE TICKETS DEL MES POR PRIORIDAD</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="3">Prioridad</td>
    <td colspan="2">Cantidad de Tickets</td>
    <td colspan="2">Porcentaje del Total</td>
  </tr>
  <?php foreach ($prioridades as $prio): ?>
  <tr>
    <td colspan="3" class="kpi-label"><?= htmlspecialchars($prio['label']) ?></td>
    <td colspan="2" style="text-align:center; font-weight:bold;"><?= $prio['count'] ?></td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:#059669;"><?= $prio['pct'] ?>%</td>
  </tr>
  <?php endforeach; ?>
  <tr><td colspan="7" style="border:none; height:15px;"></td></tr>

  <!-- Tendencia de Feedback -->
  <tr>
    <td colspan="7" class="section-title">TENDENCIA DE SENTIMIENTO EN TABLERO DE CALIDAD (MES EN CURSO)</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="3">Período semanal</td>
    <td colspan="2">Cantidad Positivo</td>
    <td colspan="2">Cantidad Negativo</td>
  </tr>
  <?php foreach ($feedbacksSemanas as $sem): ?>
  <tr>
    <td colspan="3" class="kpi-label"><?= htmlspecialchars($sem['label']) ?></td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:#059669;"><?= $sem['pos'] ?></td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:#dc2626;"><?= $sem['neg'] ?></td>
  </tr>
  <?php endforeach; ?>
  <tr><td colspan="7" style="border:none; height:15px;"></td></tr>

  <!-- Histórico de Incidencias -->
  <tr>
    <td colspan="7" class="section-title">HISTÓRICO DE TABLERO DE INCIDENCIA (ÚLTIMOS 5 MESES)</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="3">Mes</td>
    <td colspan="2">Cantidad Abiertas</td>
    <td colspan="2">Cantidad Cerradas</td>
  </tr>
  <?php foreach ($mesesHistorico as $hist): ?>
  <tr>
    <td colspan="3" class="kpi-label"><?= htmlspecialchars($hist['label']) ?></td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:#0f172a;"><?= $hist['abiertas'] ?></td>
    <td colspan="2" style="text-align:center; font-weight:bold; color:#059669;"><?= $hist['cerradas'] ?></td>
  </tr>
  <?php endforeach; ?>
  <tr><td colspan="7" style="border:none; height:15px;"></td></tr>

  <!-- Actividad Reciente -->
  <tr>
    <td colspan="7" class="section-title">REGISTRO DE ACTIVIDAD RECIENTE (ÚLTIMOS 6 SUCESOS)</td>
  </tr>
  <tr class="grid-hdr">
    <th>Tipo</th>
    <th colspan="3">Título / Tema</th>
    <th colspan="2">Fecha / Hora</th>
    <th>Estado</th>
  </tr>
  <?php 
  $idx = 0;
  foreach ($actividades as $act): 
      $row_class = ($idx % 2 == 0) ? 'row-even' : 'row-odd';
      
      // Mapeo de estilos para el Estado
      $estado_class = '';
      $estado_str = strtolower(trim($act['estado']));
      if (in_array($estado_str, ['abierto', 'nuevo', 'nueva'])) $estado_class = 'badge-abierto';
      else if (in_array($estado_str, ['cerrado', 'resuelto'])) $estado_class = 'badge-cerrado';
      else if (in_array($estado_str, ['en progreso'])) $estado_class = 'badge-en-progreso';
      else if (in_array($estado_str, ['revisado'])) $estado_class = 'badge-revisado';
      else if (in_array($estado_str, ['implementado', 'implementada'])) $estado_class = 'badge-implementado';
  ?>
  <tr class="<?= $row_class ?>">
    <td style="text-align:center; font-weight:bold;"><?= htmlspecialchars($act['tipo']) ?></td>
    <td colspan="3"><?= htmlspecialchars($act['titulo']) ?></td>
    <td colspan="2" style="text-align:center;"><?= date('d/m/Y H:i:s', strtotime($act['fecha'])) ?></td>
    <td class="<?= $estado_class ?>"><?= ucfirst(htmlspecialchars($act['estado'])) ?></td>
  </tr>
  <?php 
      $idx++;
  endforeach; 
  ?>
</table>
</body>
</html>
<?php
exit;
