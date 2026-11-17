<?php
require_once("../db.php");
session_start();
require_once("session_security.php");

// Obtener los mismos filtros que se aplican en la tabla
function obtenerTicketsFiltrados($conn) {
    
    $sql = "SELECT 
                t.*, 
                (
                    SELECT GROUP_CONCAT(DISTINCT j.nombre ORDER BY j.nombre ASC SEPARATOR ', ')
                    FROM incidencias inc
                    JOIN juegos j ON inc.juego_id = j.id
                    WHERE inc.ticket_id = t.id
                ) AS juego_nombre,
                (
                    SELECT GROUP_CONCAT(DISTINCT d.nombre ORDER BY d.nombre ASC SEPARATOR ', ')
                    FROM incidencias inc
                    JOIN drivers d ON inc.driver_id = d.id
                    WHERE inc.ticket_id = t.id
                ) AS driver_nombre
            FROM tickets t
            WHERE t.estado != 'inactivo'";

    // FILTRO ESTADO
    if (!empty($_GET["estadoBuscar"]) && $_GET["estadoBuscar"] != "todas") {
        $estado = mysqli_real_escape_string($conn, $_GET["estadoBuscar"]);
        $sql .= " AND t.estado = '$estado'";
    }

    // FILTRO PRIORIDAD
    if (!empty($_GET["prioridadBuscar"]) && $_GET["prioridadBuscar"] != "todas") {
        $prioridad = mysqli_real_escape_string($conn, $_GET["prioridadBuscar"]);
        $sql .= " AND t.prioridad = '$prioridad'";
    }

    // BUSQUEDA
    if (!empty($_GET["inputBuscar"])) {
        $buscar = mysqli_real_escape_string($conn, $_GET["inputBuscar"]);
        $sql .= " AND (t.titulo LIKE '%$buscar%' OR t.descripcion LIKE '%$buscar%' OR t.id LIKE '%$buscar%')";
    }

    // FILTRO SLA: Plazos Base Crítica (4h), Alta (24h), Media (48h), Baja (96h) + prórroga por incidencias activas y alerta < 12h
    if (!empty($_GET["slaBuscar"]) && $_GET["slaBuscar"] != "todas") {
        $slaFiltro = mysqli_real_escape_string($conn, $_GET["slaBuscar"]);
        $segundosSlaExpr = "CASE LOWER(t.prioridad)
            WHEN 'critica' THEN 14400
            WHEN 'alta' THEN 86400
            WHEN 'media' THEN 172800
            WHEN 'baja' THEN 345600
            ELSE 345600
        END";

        if ($slaFiltro === "vencido") {
            $sql .= " AND LOWER(t.estado) NOT IN ('cancelado', 'inactivo')
                      AND (
                          (LOWER(t.estado) IN ('cerrado', 'resuelto') AND t.fecha_cierre IS NOT NULL AND TIMESTAMPDIFF(SECOND, t.fecha_apertura, t.fecha_cierre) >= $segundosSlaExpr)
                          OR
                          (LOWER(t.estado) NOT IN ('cerrado', 'resuelto') AND TIMESTAMPDIFF(SECOND, t.fecha_apertura, NOW()) >= $segundosSlaExpr)
                      )";
        } elseif ($slaFiltro === "warning") {
            $sql .= " AND LOWER(t.estado) IN ('nuevo', 'asignado', 'en progreso', 'en_progreso')
                      AND TIMESTAMPDIFF(SECOND, t.fecha_apertura, NOW()) >= ($segundosSlaExpr - 43200)
                      AND TIMESTAMPDIFF(SECOND, t.fecha_apertura, NOW()) < $segundosSlaExpr";
        } elseif ($slaFiltro === "en_tiempo") {
            $sql .= " AND LOWER(t.estado) NOT IN ('cancelado', 'inactivo')
                      AND (
                          (LOWER(t.estado) IN ('cerrado', 'resuelto') AND (t.fecha_cierre IS NULL OR TIMESTAMPDIFF(SECOND, t.fecha_apertura, t.fecha_cierre) < $segundosSlaExpr))
                          OR
                          (LOWER(t.estado) NOT IN ('cerrado', 'resuelto') AND TIMESTAMPDIFF(SECOND, t.fecha_apertura, NOW()) < ($segundosSlaExpr - 43200))
                      )";
        }
    }

    $sql .= " ORDER BY t.id DESC";
    
    $query = mysqli_query($conn, $sql);
    if (!$query) return [];
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

$tickets = obtenerTicketsFiltrados($conn);

// Calcular métricas generales basándonos en el conjunto de datos filtrado
$total_cerrados = 0;
$suma_horas = 0;
$maximo_horas = 0;

foreach ($tickets as $ticket) {
    if (!empty($ticket['fecha_cierre'])) {
        $apertura = new DateTime($ticket['fecha_apertura']);
        $cierre = new DateTime($ticket['fecha_cierre']);
        $diferencia = $apertura->diff($cierre);
        $horas = ($diferencia->days * 24) + $diferencia->h + ($diferencia->i / 60);
        
        $total_cerrados++;
        $suma_horas += $horas;
        if ($horas > $maximo_horas) {
            $maximo_horas = $horas;
        }
    }
}

$promedio = $total_cerrados > 0 ? round($suma_horas / $total_cerrados, 2) : 0.00;
$maximo = round($maximo_horas, 2);

// Configurar cabeceras para descarga de Excel (.xls)
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_tickets_' . date('Y-m-d_H-i-s') . '.xls"');

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
    <x:Name>Reporte de Tablero de Incidencia</x:Name>
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
  
  /* Estados */
  .status-nuevo, .status-abierto { background-color: #ede9fe; color: #6d28d9; font-weight: bold; text-align: center; border: 1px solid #c4b5fd; }
  .status-asignado { background-color: #e0f2fe; color: #0369a1; font-weight: bold; text-align: center; border: 1px solid #7dd3fc; }
  .status-progreso { background-color: #dbeafe; color: #1d4ed8; font-weight: bold; text-align: center; border: 1px solid #93c5fd; }
  .status-espera { background-color: #fef3c7; color: #b45309; font-weight: bold; text-align: center; border: 1px solid #fcd34d; }
  .status-resuelto { background-color: #ccfbf1; color: #0f766e; font-weight: bold; text-align: center; border: 1px solid #5eead4; }
  .status-cerrado { background-color: #d1fae5; color: #047857; font-weight: bold; text-align: center; border: 1px solid #6ee7b7; }
  .status-cancelado { background-color: #ffe4e6; color: #be123c; font-weight: bold; text-align: center; border: 1px solid #fda4af; }
  
  /* Prioridades */
  .prio-critica { background-color: #fef2f2; color: #dc2626; font-weight: bold; text-align: center; }
  .prio-alta { background-color: #fffbeb; color: #d97706; font-weight: bold; text-align: center; }
  .prio-media { background-color: #f0fdf4; color: #16a34a; text-align: center; }
  .prio-baja { background-color: #f8fafc; color: #64748b; text-align: center; }
</style>
</head>
<body>
<table>
  <!-- Banner de Título -->
  <tr>
    <td colspan="14" class="title">TECHNOVA</td>
  </tr>
  <tr>
    <td colspan="14" class="subtitle">REPORTE EJECUTIVO DE TABLERO DE INCIDENCIA Y AUDITORÍA DE TICKETS</td>
  </tr>
  <tr><td colspan="14" style="border:none; height:10px;"></td></tr>
  
  <!-- Información del Reporte -->
  <tr>
    <td colspan="14" class="section-title">INFORMACIÓN DEL REPORTE</td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Fecha de Emisión:</td>
    <td colspan="4" class="meta-val"><?= date('d/m/Y H:i:s') ?></td>
    <td colspan="2" class="meta-label">Usuario Emisor:</td>
    <td colspan="6" class="meta-val"><?= htmlspecialchars($_SESSION["currentUser"]) ?></td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Total Tablero de Incidencia:</td>
    <td colspan="4" class="meta-val"><?= count($tickets) ?></td>
    <td colspan="2" class="meta-label">Filtro Búsqueda:</td>
    <td colspan="6" class="meta-val"><?= !empty($_GET['inputBuscar']) ? htmlspecialchars($_GET['inputBuscar']) : 'Ninguno' ?></td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Filtro Estado:</td>
    <td colspan="4" class="meta-val"><?= !empty($_GET['estadoBuscar']) && $_GET['estadoBuscar'] != 'todas' ? ucfirst(htmlspecialchars($_GET['estadoBuscar'])) : 'Todos' ?></td>
    <td colspan="2" class="meta-label">Filtro Prioridad:</td>
    <td colspan="6" class="meta-val"><?= !empty($_GET['prioridadBuscar']) && $_GET['prioridadBuscar'] != 'todas' ? ucfirst(htmlspecialchars($_GET['prioridadBuscar'])) : 'Todas' ?></td>
  </tr>
  <tr><td colspan="14" style="border:none; height:15px;"></td></tr>
  
  <!-- KPIs -->
  <tr>
    <td colspan="14" class="section-title">INDICADORES CLAVE DE RENDIMIENTO (KPIs)</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="4">Indicador / Métrica</td>
    <td colspan="2">Valor Registrado</td>
    <td colspan="2">Unidad</td>
    <td colspan="6">Descripción de la Métrica</td>
  </tr>
  <tr>
    <td colspan="4" class="kpi-label">Tiempo Promedio de Resolución</td>
    <td colspan="2" class="kpi-val"><?= $promedio ?></td>
    <td colspan="2" style="text-align:center;">Horas</td>
    <td colspan="6" class="kpi-desc">Tiempo medio transcurrido entre la apertura y el cierre de incidencias</td>
  </tr>
  <tr>
    <td colspan="4" class="kpi-label">Tiempo Máximo de Resolución</td>
    <td colspan="2" class="kpi-val"><?= $maximo ?></td>
    <td colspan="2" style="text-align:center;">Horas</td>
    <td colspan="6" class="kpi-desc">Mayor tiempo registrado para resolver una incidencia en este conjunto</td>
  </tr>
  <tr><td colspan="14" style="border:none; height:15px;"></td></tr>
  
  <!-- Tabla Principal -->
  <tr>
    <td colspan="14" class="section-title">DETALLE DE TABLERO DE INCIDENCIA</td>
  </tr>
  <tr class="grid-hdr">
    <th>ID Tablero de Incidencia</th>
    <th>Título</th>
    <th>Descripción</th>
    <th>Categoría</th>
    <th>Prioridad</th>
    <th>Juego</th>
    <th>Driver</th>
    <th>Estado</th>
    <th>Usuario Creador</th>
    <th>Asignado a</th>
    <th>Fecha de Apertura</th>
    <th>Fecha de Cierre</th>
    <th>Tiempo de Resolución (hs Decimal)</th>
    <th>Tiempo de Resolución (Detallado)</th>
  </tr>
  
  <?php
  $idx = 0;
  foreach ($tickets as $ticket) {
      $row_class = ($idx % 2 == 0) ? 'row-even' : 'row-odd';
      
      $fecha_apertura_formateada = !empty($ticket['fecha_apertura']) ? date('d/m/Y H:i:s', strtotime($ticket['fecha_apertura'])) : 'N/A';
      $fecha_cierre_formateada = !empty($ticket['fecha_cierre']) ? date('d/m/Y H:i:s', strtotime($ticket['fecha_cierre'])) : 'N/A';
      
      $horas_decimal = '';
      $detalle_tiempo = 'N/A';
      
      if (!empty($ticket['fecha_cierre'])) {
          $apertura = new DateTime($ticket['fecha_apertura']);
          $cierre = new DateTime($ticket['fecha_cierre']);
          $diferencia = $apertura->diff($cierre);
          $horas = ($diferencia->days * 24) + $diferencia->h + ($diferencia->i / 60);
          $horas_decimal = round($horas, 2);
          
          $dias = $diferencia->days;
          $hs = $diferencia->h;
          $mins = $diferencia->i;
          
          $partes = [];
          if ($dias > 0) $partes[] = "$dias " . ($dias == 1 ? "día" : "días");
          if ($hs > 0) $partes[] = "$hs " . ($hs == 1 ? "hora" : "horas");
          if ($mins > 0) $partes[] = "$mins " . ($mins == 1 ? "minuto" : "minutos");
          if (empty($partes)) $partes[] = "Menos de 1 minuto";
          $detalle_tiempo = implode(', ', $partes);
          
      } else if ($ticket['estado'] !== 'cerrado') {
          $apertura = new DateTime($ticket['fecha_apertura']);
          $ahora = new DateTime();
          $diferencia = $apertura->diff($ahora);
          
          $dias = $diferencia->days;
          $hs = $diferencia->h;
          $mins = $diferencia->i;
          
          $partes = [];
          if ($dias > 0) $partes[] = "$dias " . ($dias == 1 ? "día" : "días");
          if ($hs > 0) $partes[] = "$hs " . ($hs == 1 ? "hora" : "horas");
          if ($mins > 0) $partes[] = "$mins " . ($mins == 1 ? "minuto" : "minutos");
          if (empty($partes)) $partes[] = "Recién creado";
          $detalle_tiempo = "Pendiente (Activo hace " . implode(', ', $partes) . ")";
      }
      
      // Mapeo de estilos para Estado
      $estado_class = '';
      $estado_texto = ucfirst(htmlspecialchars($ticket['estado']));
      $est_lower = strtolower(trim($ticket['estado']));
      if (in_array($est_lower, ['nuevo', 'abierto'])) $estado_class = 'status-nuevo';
      else if ($est_lower === 'asignado') $estado_class = 'status-asignado';
      else if ($est_lower === 'en progreso') $estado_class = 'status-progreso';
      else if ($est_lower === 'en espera') $estado_class = 'status-espera';
      else if ($est_lower === 'resuelto') $estado_class = 'status-resuelto';
      else if ($est_lower === 'cerrado') $estado_class = 'status-cerrado';
      else if (in_array($est_lower, ['cancelado', 'inactivo'])) $estado_class = 'status-cancelado';
      
      // Mapeo de estilos para Prioridad
      $prio_class = '';
      $prio_texto = ucfirst(htmlspecialchars($ticket['prioridad']));
      if ($ticket['prioridad'] === 'critica') $prio_class = 'prio-critica';
      else if ($ticket['prioridad'] === 'alta') $prio_class = 'prio-alta';
      else if ($ticket['prioridad'] === 'media') $prio_class = 'prio-media';
      else if ($ticket['prioridad'] === 'baja') $prio_class = 'prio-baja';
      
      ?>
      <tr class="<?= $row_class ?>">
        <td style="text-align:center; font-weight:bold;">TKT-<?= str_pad($ticket['id'], 3, '0', STR_PAD_LEFT) ?></td>
        <td><?= htmlspecialchars($ticket['titulo']) ?></td>
        <td><?= htmlspecialchars($ticket['descripcion']) ?></td>
        <td><?= htmlspecialchars($ticket['categoria']) ?></td>
        <td class="<?= $prio_class ?>"><?= $prio_texto ?></td>
        <td><?= htmlspecialchars($ticket['juego_nombre'] ?? 'N/A') ?></td>
        <td><?= htmlspecialchars($ticket['driver_nombre'] ?? 'N/A') ?></td>
        <td class="<?= $estado_class ?>"><?= $estado_texto ?></td>
        <td><?= htmlspecialchars($ticket['usuario']) ?></td>
        <td><?= htmlspecialchars($ticket['asignado']) ?></td>
        <td style="text-align:center;"><?= $fecha_apertura_formateada ?></td>
        <td style="text-align:center;"><?= $fecha_cierre_formateada ?></td>
        <td style="text-align:right; font-weight:bold;"><?= $horas_decimal ?></td>
        <td><?= htmlspecialchars($detalle_tiempo) ?></td>
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