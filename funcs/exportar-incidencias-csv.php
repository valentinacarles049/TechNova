<?php
require_once("../db.php");
session_start();
require_once("session_security.php");
require_once("generalFuncs.php");

if (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "soporte") {
    http_response_code(403);
    die("Acceso denegado: El equipo de Soporte no tiene permisos para acceder a esta función.");
}

// Obtener los mismos filtros que se aplican en la tabla de incidencias
function obtenerIncidenciasFiltradas($conn) {
    $sql = "SELECT 
                inc.id AS incidencia_id,
                inc.ticket_id,
                inc.juego_id,
                inc.driver_id,
                inc.detalle,
                inc.fecha_creacion,
                inc.fecha_cierre AS incidencia_fecha_cierre,
                inc.estado AS incidencia_estado,
                inc.dictamen_tecnico,
                inc.solucion_temporal,
                inc.motivo_descarte,
                t.titulo AS ticket_titulo,
                t.descripcion AS ticket_descripcion,
                t.estado AS ticket_estado,
                t.prioridad AS ticket_prioridad,
                t.categoria AS ticket_categoria,
                t.usuario AS usuario_creador,
                t.asignado AS usuario_assigned,
                j.nombre AS juego_nombre,
                d.nombre AS driver_nombre
            FROM incidencias inc
            JOIN tickets t ON inc.ticket_id = t.id
            LEFT JOIN juegos j ON inc.juego_id = j.id
            LEFT JOIN drivers d ON inc.driver_id = d.id
            WHERE t.estado != 'inactivo'";

    if (isset($_GET["buscar"]) && !empty(trim($_GET["buscar"]))) {
        $buscar = trim($_GET["buscar"]);
        $buscarEscaped = mysqli_real_escape_string($conn, $buscar);
        if (preg_match('/^INC-(\d+)$/i', trim($buscar), $matches)) {
            $idBuscar = intval($matches[1]);
            $sql .= " AND inc.id = $idBuscar";
        } else {
            $sql .= " AND (t.titulo LIKE '%$buscarEscaped%' OR inc.detalle LIKE '%$buscarEscaped%' OR j.nombre LIKE '%$buscarEscaped%' OR d.nombre LIKE '%$buscarEscaped%')";
        }
    }

    if (isset($_GET["filtro"])) {
        $filtro = $_GET["filtro"];
        if ($filtro === "resueltos") {
            $sql .= " AND inc.estado IN ('Corregida', 'Desestimada', 'cerrado')";
        } else if ($filtro === "no_resueltos") {
            $sql .= " AND inc.estado IN ('Reportada', 'EnInvestigacion', 'Mitigada', 'abierto', 'en progreso')";
        } else if (in_array($filtro, ['Reportada', 'EnInvestigacion', 'Mitigada', 'Corregida', 'Desestimada'])) {
            $filtroEscaped = mysqli_real_escape_string($conn, $filtro);
            $sql .= " AND inc.estado = '$filtroEscaped'";
        }
    }

    if (isset($_GET["prioridad"]) && in_array($_GET["prioridad"], ["critica", "alta", "media", "baja"])) {
        $prioridad = $_GET["prioridad"];
        $sql .= " AND t.prioridad = '$prioridad'";
    }

    $sql .= " ORDER BY inc.id DESC";
    $query = mysqli_query($conn, $sql);
    if (!$query) {
        return [];
    }
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

$incidencias = obtenerIncidenciasFiltradas($conn);

// Calcular KPIs e Indicadores
$total_incidencias = count($incidencias);
$total_cerradas = 0;
$total_abiertas = 0;
$suma_horas_resolucion = 0;

$prioridades = ['critica' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0];
$categorias = [];

foreach ($incidencias as &$inc) {
    // Clasificar estado (HU-42)
    $estado_clean = strtolower(trim($inc['incidencia_estado'] ?? 'Reportada'));
    if (in_array($estado_clean, ['corregida', 'desestimada', 'cerrado'])) {
        $total_cerradas++;
    } else {
        $total_abiertas++;
    }

    // Clasificar prioridad
    $prio_clean = strtolower(trim($inc['ticket_prioridad'] ?? 'baja'));
    if (array_key_exists($prio_clean, $prioridades)) {
        $prioridades[$prio_clean]++;
    }

    // Clasificar categorías/tipo
    $cat_clean = ucfirst(strtolower(trim($inc['ticket_categoria'] ?? 'General')));
    if (!empty($cat_clean)) {
        if (!isset($categorias[$cat_clean])) {
            $categorias[$cat_clean] = 0;
        }
        $categorias[$cat_clean]++;
    }

    // Calcular tiempo de resolución
    $inc['tiempo_resolucion_hrs'] = null;
    $inc['tiempo_resolucion_texto'] = 'Pendiente';
    if ($estado_clean === 'cerrado' && !empty($inc['incidencia_fecha_cierre'])) {
        $apertura = new DateTime($inc['fecha_creacion']);
        $cierre = new DateTime($inc['incidencia_fecha_cierre']);
        $diff = $apertura->diff($cierre);
        // Calcular total en horas
        $horas = ($diff->days * 24) + $diff->h + ($diff->i / 60);
        $inc['tiempo_resolucion_hrs'] = $horas;
        $suma_horas_resolucion += $horas;
        
        if ($horas < 1) {
            $inc['tiempo_resolucion_texto'] = round($horas * 60) . ' mins';
        } else if ($horas < 24) {
            $inc['tiempo_resolucion_texto'] = round($horas, 1) . ' hrs';
        } else {
            $dias = floor($horas / 24);
            $resto_hrs = round($horas % 24, 1);
            $inc['tiempo_resolucion_texto'] = $dias . 'd ' . $resto_hrs . 'h';
        }
    }

    // Consultar vinculaciones para cada incidencia
    $incId = $inc['incidencia_id'];
    $relQuery = mysqli_query($conn, "
        SELECT r.tipo_relacion, i.id AS linked_id 
        FROM relaciones_incidencias r
        JOIN incidencias i ON (r.incidencia_destino_id = i.id AND r.incidencia_origen_id = $incId)
                           OR (r.incidencia_origen_id = i.id AND r.incidencia_destino_id = $incId)
        WHERE i.id != $incId
    ");
    $vincs = [];
    if ($relQuery) {
        while ($row = mysqli_fetch_assoc($relQuery)) {
            $vincs[] = "INC-" . str_pad($row['linked_id'], 3, '0', STR_PAD_LEFT) . " (" . ucfirst($row['tipo_relacion']) . ")";
        }
    }
    $inc['vinculaciones'] = empty($vincs) ? 'Ninguna' : implode(', ', $vincs);
}
unset($inc); // Romper referencia

// Tiempo de resolución promedio
$promedio_resolucion = $total_cerradas > 0 ? round($suma_horas_resolucion / $total_cerradas, 2) : 0.00;
$promedio_resolucion_texto = $promedio_resolucion > 0 ? ($promedio_resolucion . ' hrs') : 'N/A';

// Obtener categoría más común
arsort($categorias);
$top_categoria = !empty($categorias) ? key($categorias) . ' (' . current($categorias) . ')' : 'Ninguna';

// Configurar cabeceras para descarga de Excel (.xls)
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_incidencias_' . date('Y-m-d_H-i-s') . '.xls"');

// Enviar BOM para compatibilidad UTF-8
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
    <x:Name>Reporte de Incidencias</x:Name>
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
  
  .prio-critica { background-color: #fee2e2; color: #b91c1c; text-align: center; font-weight: bold; }
  .prio-alta { background-color: #ffedd5; color: #c2410c; text-align: center; }
  .prio-media { background-color: #fef9c3; color: #a16207; text-align: center; }
  .prio-baja { background-color: #d1fae5; color: #047857; text-align: center; }
  
  .est-cerrado { background-color: #d1fae5; color: #047857; text-align: center; }
  .est-abierto { background-color: #fee2e2; color: #b91c1c; text-align: center; }
  .est-en-progreso { background-color: #dbeafe; color: #1d4ed8; text-align: center; }
</style>
</head>
<body>
<table>
  <!-- Banner de Título -->
  <tr>
    <td colspan="11" class="title">TECHNOVA</td>
  </tr>
  <tr>
    <td colspan="11" class="subtitle">REPORTE TÉCNICO DE TABLERO DE INCIDENCIAS Y RESOLUCIONES</td>
  </tr>
  <tr><td colspan="11" style="border:none; height:10px;"></td></tr>
  
  <!-- Información del Reporte -->
  <tr>
    <td colspan="11" class="section-title">INFORMACIÓN DEL REPORTE</td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Fecha de Emisión:</td>
    <td colspan="3" class="meta-val"><?= date('d/m/Y H:i:s') ?></td>
    <td colspan="2" class="meta-label">Usuario Emisor:</td>
    <td colspan="4" class="meta-val"><?= htmlspecialchars($_SESSION["currentUser"]) ?></td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Total Incidencias:</td>
    <td colspan="3" class="meta-val"><?= $total_incidencias ?> (Abiertas: <?= $total_abiertas ?>, Cerradas: <?= $total_cerradas ?>)</td>
    <td colspan="2" class="meta-label">Filtro Búsqueda:</td>
    <td colspan="4" class="meta-val"><?= !empty($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : 'Ninguno' ?></td>
  </tr>
  <tr>
    <td colspan="2" class="meta-label">Filtro Estado:</td>
    <td colspan="3" class="meta-val"><?= !empty($_GET['filtro']) && $_GET['filtro'] != 'todos' ? ($_GET['filtro'] == 'resueltos' ? 'Resueltos (Cerrados)' : 'No Resueltos (Abiertos/En Progreso)') : 'Todos' ?></td>
    <td colspan="2" class="meta-label">Filtro Prioridad:</td>
    <td colspan="4" class="meta-val"><?= !empty($_GET['prioridad']) && $_GET['prioridad'] != 'todos' ? ucfirst(htmlspecialchars($_GET['prioridad'])) : 'Todas' ?></td>
  </tr>
  <tr><td colspan="11" style="border:none; height:15px;"></td></tr>
  
  <!-- KPIs -->
  <tr>
    <td colspan="11" class="section-title">INDICADORES CLAVE DE RENDIMIENTO (KPIs)</td>
  </tr>
  <tr class="kpi-hdr">
    <td colspan="3">Indicador / Métrica</td>
    <td colspan="2">Valor Registrado</td>
    <td colspan="2">Unidad</td>
    <td colspan="4">Descripción de la Métrica</td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">Tiempo de Resolución Promedio</td>
    <td colspan="2" class="kpi-val"><?= $promedio_resolucion_texto ?></td>
    <td colspan="2" style="text-align:center;">Horas (hrs)</td>
    <td colspan="4" class="kpi-desc">Tiempo promedio transcurrido entre la creación y cierre de las incidencias resueltas.</td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">Categoría de Incidencia más Común</td>
    <td colspan="2" class="kpi-val"><?= htmlspecialchars($top_categoria) ?></td>
    <td colspan="2" style="text-align:center;">Tipo (Frecuencia)</td>
    <td colspan="4" class="kpi-desc">Tipo de error más reportado en base a la categoría del ticket de soporte original.</td>
  </tr>
  <tr>
    <td colspan="3" class="kpi-label">Incidencias Críticas Reportadas</td>
    <td colspan="2" class="kpi-val" style="color: #b91c1c;"><?= $prioridades['critica'] ?></td>
    <td colspan="2" style="text-align:center;">Cantidad</td>
    <td colspan="4" class="kpi-desc">Número de fallas críticas activas en el conjunto actual que comprometen la jugabilidad.</td>
  </tr>
  <tr><td colspan="11" style="border:none; height:15px;"></td></tr>
  
  <!-- Tabla Principal -->
  <tr>
    <td colspan="11" class="section-title">DETALLE DEL TABLERO DE INCIDENCIAS</td>
  </tr>
  <tr class="grid-hdr">
    <th>ID Incidencia</th>
    <th>Ticket Asociado</th>
    <th>Título del Ticket</th>
    <th>Juego Afectado</th>
    <th>Driver / Controlador</th>
    <th>Categoría / Tipo</th>
    <th>Criticidad / Prioridad</th>
    <th>Estado (HU-42)</th>
    <th>Tiempo de Resolución</th>
    <th>Vinculaciones (Errores Relacionados)</th>
    <th>Detalle Técnico</th>
    <th>Solución Temporal</th>
    <th>Dictamen Técnico</th>
    <th>Motivo Descarte</th>
  </tr>
  
  <?php
  $idx = 0;
  foreach ($incidencias as $inc) {
      $row_class = ($idx % 2 == 0) ? 'row-even' : 'row-odd';
      
      $tkt_asociado = 'TKT-' . str_pad($inc['ticket_id'], 3, '0', STR_PAD_LEFT);
      
      // Estilos Prioridad
      $prio_class = '';
      $prio_val = strtolower(trim($inc['ticket_prioridad'] ?? 'baja'));
      if ($prio_val === 'critica') $prio_class = 'prio-critica';
      else if ($prio_val === 'alta') $prio_class = 'prio-alta';
      else if ($prio_val === 'media') $prio_class = 'prio-media';
      else $prio_class = 'prio-baja';
      
      // Estilos Estado (HU-42)
      $est_raw = $inc['incidencia_estado'] ?? 'Reportada';
      $est_text = getEstadoNombreLegible($est_raw) . (esEstadoTerminalIncidencia($est_raw) ? ' (Terminal)' : '');
      $est_class = '';
      switch (trim($est_raw)) {
          case 'Corregida':
          case 'cerrado':
              $est_class = 'est-cerrado';
              break;
          case 'Desestimada':
              $est_class = 'est-desestimada';
              break;
          case 'Mitigada':
              $est_class = 'est-mitigada';
              break;
          case 'EnInvestigacion':
          case 'en progreso':
              $est_class = 'est-en-progreso';
              break;
          default:
              $est_class = 'est-abierto';
              break;
      }
      
      ?>
      <tr class="<?= $row_class ?>">
        <td style="text-align:center; font-weight:bold;">INC-<?= str_pad($inc['incidencia_id'], 3, '0', STR_PAD_LEFT) ?></td>
        <td style="text-align:center;"><?= $tkt_asociado ?></td>
        <td><?= htmlspecialchars($inc['ticket_titulo']) ?></td>
        <td><?= htmlspecialchars($inc['juego_nombre'] ?? 'No especificado') ?></td>
        <td><?= htmlspecialchars($inc['driver_nombre'] ?? 'No especificado') ?></td>
        <td><?= htmlspecialchars($inc['ticket_categoria'] ?? 'General') ?></td>
        <td class="<?= $prio_class ?>"><?= ucfirst($prio_val) ?></td>
        <td class="<?= $est_class ?>"><?= $est_text ?></td>
        <td style="text-align:center;"><?= $inc['tiempo_resolucion_texto'] ?></td>
        <td><?= htmlspecialchars($inc['vinculaciones']) ?></td>
        <td><?= htmlspecialchars($inc['detalle']) ?></td>
        <td><?= htmlspecialchars($inc['solucion_temporal'] ?? '-') ?></td>
        <td><?= htmlspecialchars($inc['dictamen_tecnico'] ?? '-') ?></td>
        <td><?= htmlspecialchars($inc['motivo_descarte'] ?? '-') ?></td>
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
?>
