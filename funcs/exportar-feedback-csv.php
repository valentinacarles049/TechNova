<?php
require_once("../db.php");
session_start();
require_once("session_security.php");

if (empty($_SESSION["currentTeam"]) || ($_SESSION["currentTeam"] !== "monitoreo" && $_SESSION["currentTeam"] !== "administrador")) {
    http_response_code(403);
    die("Acceso denegado: Este módulo es exclusivo para el Equipo de Monitoreo o Administrador.");
}

// Obtener los mismos filtros que se aplican en la tabla de feedbacks
function obtenerFeedbacksFiltrados($conn) {
    $sql = "SELECT f.*, t.titulo AS ticket_titulo, t.descripcion AS ticket_descripcion
            FROM feedbacks f
            LEFT JOIN tickets t ON f.ticket_id = t.id
            WHERE f.estado != 'inactivo'";

    // FILTRO TIPO
    if (!empty($_GET["tipoBuscar"]) && $_GET["tipoBuscar"] != "todos") {
        $tipo = mysqli_real_escape_string($conn, $_GET["tipoBuscar"]);
        $sql .= " AND f.tipo = '$tipo'";
    }

    // FILTRO SENTIMIENTO
    if (!empty($_GET["sentimientoBuscar"]) && $_GET["sentimientoBuscar"] != "todos") {
        $sentimiento = mysqli_real_escape_string($conn, $_GET["sentimientoBuscar"]);
        $sql .= " AND f.sentimiento = '$sentimiento'";
    }

    // BUSQUEDA TEXTUAL
    if (!empty($_GET["inputBuscar"])) {
        $buscar = mysqli_real_escape_string($conn, $_GET["inputBuscar"]);
        
        $idBuscar = "";
        if (preg_match('/^OP-(\d+)$/i', trim($buscar), $matches)) {
            $idBuscar = intval($matches[1]);
        }

        $sql .= " AND (
            f.usuario LIKE '%$buscar%'
            OR f.tema LIKE '%$buscar%'
            OR f.comentario LIKE '%$buscar%'
        ";
        if ($idBuscar !== "") {
            $sql .= " OR f.id = $idBuscar";
        } else {
            $sql .= " OR f.id LIKE '%$buscar%'";
        }
        $sql .= " )";
    }

    $sql .= " ORDER BY f.id DESC";
    $query = mysqli_query($conn, $sql);
    if (!$query) {
        return [];
    }
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

$feedbacks = obtenerFeedbacksFiltrados($conn);

// Calcular Tasa de Feedback al Cierre (Tasa de Respuesta)
$primerDiaMes = date('Y-m-01');
$primerDiaMesAnterior = date('Y-m-01', strtotime('-1 month'));

// Feedbacks creados en el mes en curso (con ticket_id no nulo y no inactivos)
$queryFbMes = mysqli_query($conn, "SELECT COUNT(*) as cant FROM feedbacks WHERE ticket_id IS NOT NULL AND fecha >= '$primerDiaMes' AND estado != 'inactivo'");
$rowFbMes = mysqli_fetch_assoc($queryFbMes);
$feedbackCierreCount = $rowFbMes['cant'] ?? 0;

// Tickets cerrados en el mes en curso
$queryTktMes = mysqli_query($conn, "SELECT COUNT(*) as cant FROM tickets WHERE estado = 'cerrado' AND fecha_cierre >= '$primerDiaMes'");
$rowTktMes = mysqli_fetch_assoc($queryTktMes);
$ticketsCerradosCount = $rowTktMes['cant'] ?? 0;

$tasaFeedbackAlCierre = $ticketsCerradosCount > 0 ? round(($feedbackCierreCount / $ticketsCerradosCount) * 100) : 0;

// Feedbacks creados en el mes anterior (con ticket_id no nulo y no inactivos)
$queryFbMesPrev = mysqli_query($conn, "SELECT COUNT(*) as cant FROM feedbacks WHERE ticket_id IS NOT NULL AND fecha >= '$primerDiaMesAnterior' AND fecha < '$primerDiaMes' AND estado != 'inactivo'");
$rowFbMesPrev = mysqli_fetch_assoc($queryFbMesPrev);
$feedbackCierrePrevCount = $rowFbMesPrev['cant'] ?? 0;

// Tickets cerrados en el mes anterior
$queryTktMesPrev = mysqli_query($conn, "SELECT COUNT(*) as cant FROM tickets WHERE estado = 'cerrado' AND fecha_cierre >= '$primerDiaMesAnterior' AND fecha_cierre < '$primerDiaMes'");
$rowTktMesPrev = mysqli_fetch_assoc($queryTktMesPrev);
$ticketsCerradosPrevCount = $rowTktMesPrev['cant'] ?? 0;

$tasaFeedbackAlCierreMesAnterior = $ticketsCerradosPrevCount > 0 ? round(($feedbackCierrePrevCount / $ticketsCerradosPrevCount) * 100) : 0;

$tasaChange = $tasaFeedbackAlCierre - $tasaFeedbackAlCierreMesAnterior;

// Agrupación para tipo y sentimiento de los datos actuales del listado exportado
$tiposData = ['Sugerencia' => 0, 'Queja' => 0, 'Elogio' => 0];
$sentimientosData = ['Positivo' => 0, 'Negativo' => 0];
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

// Configurar cabeceras para descarga de Excel (.xls)
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_feedback_' . date('Y-m-d_H-i-s') . '.xls"');

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
    <x:Name>Reporte de Tablero de Calidad</x:Name>
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
  .kpi-val { font-weight: bold; color: #059669; text-align: center; }
  .kpi-desc { color: #64748b; font-style: italic; }
  
  /* Grilla Principal */
  .grid-hdr th { background-color: #0f172a; color: #ffffff; font-weight: bold; text-align: center; height: 30px; border: 1px solid #475569; }
  .row-even { background-color: #ffffff; }
  .row-odd { background-color: #f8fafc; }
  
  /* Tipos y Sentimientos */
  .tipo-sugerencia { background-color: #e0f2fe; color: #0369a1; text-align: center; }
  .tipo-queja { background-color: #fee2e2; color: #b91c1c; text-align: center; }
  .tipo-elogio { background-color: #d1fae5; color: #047857; text-align: center; }
  
  .sent-positivo { background-color: #d1fae5; color: #047857; text-align: center; }
  .sent-negativo { background-color: #fee2e2; color: #b91c1c; text-align: center; }
</style>
</head>
<body>
<table>
  <!-- Banner de Título -->
  <tr>
    <td colspan="9" class="title">TECHNOVA</td>
  </tr>
  <tr>
    <td colspan="9" class="subtitle">REPORTE EJECUTIVO DE TABLERO DE CALIDAD Y CALIDAD DE RESOLUCIONES</td>
  </tr>
  <tr><td colspan="9" style="border:none; height:10px;"></td></tr>
  
  <!-- Información del Reporte -->
  <tr>
    <td colspan="9" class="section-title">INFORMACIÓN DEL REPORTE</td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Fecha de Emisión:</td>
    <td colspan="3" class="meta-val"><?= date('d/m/Y H:i:s') ?></td>
    <td colspan="2" class="meta-label">Usuario Emisor:</td>
    <td colspan="2" class="meta-val"><?= htmlspecialchars($_SESSION["currentUser"]) ?></td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Total Tablero de Calidad:</td>
    <td colspan="3" class="meta-val"><?= count($feedbacks) ?></td>
    <td colspan="2" class="meta-label">Filtro Búsqueda:</td>
    <td colspan="2" class="meta-val"><?= !empty($_GET['inputBuscar']) ? htmlspecialchars($_GET['inputBuscar']) : 'Ninguno' ?></td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Filtro Tipo:</td>
    <td colspan="3" class="meta-val"><?= !empty($_GET['tipoBuscar']) && $_GET['tipoBuscar'] != 'todos' ? htmlspecialchars($_GET['tipoBuscar']) : 'Todos' ?></td>
    <td colspan="2" class="meta-label">Filtro Sentimiento:</td>
    <td colspan="2" class="meta-val"><?= !empty($_GET['sentimientoBuscar']) && $_GET['sentimientoBuscar'] != 'todos' ? htmlspecialchars($_GET['sentimientoBuscar']) : 'Todos' ?></td>
  </tr>
  <tr><td colspan="9" style="border:none; height:15px;"></td></tr>
  
  <!-- KPIs -->
  <tr>
    <td colspan="9" class="section-title">INDICADORES CLAVE DE RENDIMIENTO (KPIs)</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="3">Indicador / Métrica</td>
    <td colspan="2">Valor Registrado</td>
    <td colspan="1">Unidad</td>
    <td colspan="3">Descripción de la Métrica</td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">Tasa de Tablero de Calidad al Cierre</td>
    <td colspan="2" class="kpi-val"><?= $tasaFeedbackAlCierre ?>%</td>
    <td colspan="1" style="text-align:center;">Porcentaje (%)</td>
    <td colspan="3" class="kpi-desc">Porcentaje de tickets cerrados que generaron un feedback en el mes en curso (Var. MoM: <?= ($tasaChange >= 0 ? '+' : '') . $tasaChange ?>%)</td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">Distribución de Sentimiento Positivo</td>
    <td colspan="2" class="kpi-val"><?= $totalActivos > 0 ? round(($sentimientosData['Positivo'] / $totalActivos) * 100) : 0 ?>%</td>
    <td colspan="1" style="text-align:center;">Porcentaje (%)</td>
    <td colspan="3" class="kpi-desc">Proporción de comentarios con sentimiento positivo en el set de datos actual</td>
  </tr>
  <tr><td colspan="9" style="border:none; height:15px;"></td></tr>
  
  <!-- Tabla Principal -->
  <tr>
    <td colspan="9" class="section-title">DETALLE DE TABLERO DE CALIDAD</td>
  </tr>
  <tr class="grid-hdr">
    <th>ID Tablero de Calidad</th>
    <th>Ticket Asociado</th>
    <th>Usuario</th>
    <th>Tipo</th>
    <th>Tema</th>
    <th>Sentimiento</th>
    <th>Estado</th>
    <th>Comentario</th>
    <th>Fecha de Registro</th>
  </tr>
  
  <?php
  $idx = 0;
  foreach ($feedbacks as $fb) {
      $row_class = ($idx % 2 == 0) ? 'row-even' : 'row-odd';
      $fecha_formateada = !empty($fb['fecha']) ? date('d/m/Y H:i:s', strtotime($fb['fecha'])) : 'N/A';
      
      $tkt_asociado = 'N/A';
      if (!empty($fb['ticket_id'])) {
          $tkt_asociado = 'TKT-' . str_pad($fb['ticket_id'], 3, '0', STR_PAD_LEFT);
      }
      
      // Mapeo de estilos para Tipo
      $tipo_class = '';
      $tipo_val = ucfirst(strtolower(trim($fb['tipo'] ?? '')));
      if ($tipo_val === 'Sugerencia') $tipo_class = 'tipo-sugerencia';
      else if ($tipo_val === 'Queja') $tipo_class = 'tipo-queja';
      else if ($tipo_val === 'Elogio') $tipo_class = 'tipo-elogio';
      
      // Mapeo de estilos para Sentimiento
      $sent_class = '';
      $sent_val = ucfirst(strtolower(trim($fb['sentimiento'] ?? '')));
      if ($sent_val === 'Positivo') $sent_class = 'sent-positivo';
      else if ($sent_val === 'Negativo') $sent_class = 'sent-negativo';
      
      ?>
      <tr class="<?= $row_class ?>">
        <td style="text-align:center; font-weight:bold;">OP-<?= str_pad($fb['id'], 3, '0', STR_PAD_LEFT) ?></td>
        <td style="text-align:center;"><?= $tkt_asociado ?></td>
        <td><?= htmlspecialchars($fb['usuario'] ?? '') ?></td>
        <td class="<?= $tipo_class ?>"><?= $tipo_val ?></td>
        <td><?= htmlspecialchars($fb['tema'] ?? '') ?></td>
        <td class="<?= $sent_class ?>"><?= $sent_val ?></td>
        <td><?= htmlspecialchars($fb['estado'] ?? '') ?></td>
        <td><?= htmlspecialchars($fb['comentario'] ?? '') ?></td>
        <td style="text-align:center;"><?= $fecha_formateada ?></td>
      </tr>
      <?php
      $idx++;
  }
  ?>
</table>
</body>
</html>
<?php
exit;
