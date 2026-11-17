<?php 
if (date_default_timezone_get() !== 'America/Argentina/Buenos_Aires') {
    @date_default_timezone_set('America/Argentina/Buenos_Aires');
}

function renderizarFilasTabla(array $campos, array $datos): string {
    $html = '';
    
    // Manejo de estado vacío
    if (empty($datos)) {
        $cantidadColumnas = count($campos);
        return '<tr><td colspan="' . $cantidadColumnas . '" class="p-4 text-center text-slate-400">No se encontraron registros.</td></tr>';
    }

    // Iterar sobre cada fila de datos
    foreach ($datos as $fila) {
        // Validación inteligente del ID de la fila para JS/Ajax
        if (isset($fila["id"])) {
            $html .= '<tr class="border-b border-slate-700 hover:bg-slate-800/40 transition-colors" id="row-fb-' . $fila["id"] . '">';
        } else {
            $html .= '<tr class="border-b border-slate-700 hover:bg-slate-800/40 transition-colors">';
        }
        
        // Iterar sobre la configuración de los campos para generar los <td>
        foreach ($campos as $renderCelda) {
            $html .= $renderCelda($fila);
        }
        
        $html .= '</tr>';
    }
    
    return $html;
}

function registrarHistorialTicket($conn, $ticket_id, $tipo_cambio) {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    $usuario = isset($_SESSION["currentUser"]) ? mysqli_real_escape_string($conn, $_SESSION["currentUser"]) : 'sistema';
    $tipo_cambio_escaped = mysqli_real_escape_string($conn, $tipo_cambio);
    $ticket_id = intval($ticket_id);
    
    $query = "INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio) VALUES ($ticket_id, '$usuario', '$tipo_cambio_escaped')";
    mysqli_query($conn, $query);
}

function mostrarModalHistorial($conn, $ticket_id) {
    $ticket_id = intval($ticket_id);
    
    // Consultar información básica del ticket
    $ticketQuery = mysqli_query($conn, "SELECT titulo, estado FROM tickets WHERE id = $ticket_id");
    $ticketInfo = $ticketQuery ? mysqli_fetch_assoc($ticketQuery) : null;
    $ticketTitulo = $ticketInfo ? $ticketInfo['titulo'] : 'Desconocido';
    $ticketEstado = $ticketInfo ? $ticketInfo['estado'] : 'abierto';
    
    // Consultar historial
    $historialQuery = mysqli_query($conn, "
        SELECT id, usuario, tipo_cambio, fecha 
        FROM historial_tickets 
        WHERE ticket_id = $ticket_id 
        ORDER BY fecha DESC, id DESC
    ");
    $logs = [];
    if ($historialQuery) {
        $logs = mysqli_fetch_all($historialQuery, MYSQLI_ASSOC);
    }
    
    // Renderizar listado de la línea de tiempo
    $timelineHTML = '';
    if (empty($logs)) {
        $timelineHTML .= '
        <div class="text-center py-8">
          <p class="text-sm text-slate-500 italic">No hay registros de cambios automáticos para este ticket todavía.</p>
        </div>';
    } else {
        $timelineHTML .= '<div class="relative pl-6 border-l border-slate-700/60 ml-2.5 space-y-4 py-2">';
        foreach ($logs as $log) {
            $fecha_formateada = date('d/m/Y H:i:s', strtotime($log['fecha']));
            $timelineHTML .= '
            <div class="relative">
              <!-- Timeline Dot -->
              <div class="absolute -left-[32px] top-1.5 w-3.5 h-3.5 rounded-full bg-emerald-500 border-4 border-[#0f172a]" style="background-color: #10b981 !important; border-color: #0f172a !important;"></div>
              
              <!-- Timeline Item Box -->
              <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-xs text-slate-300" style="border: 1px solid #1e293b !important; background-color: rgba(2, 6, 23, 0.4) !important;">
                <div class="flex items-center justify-between gap-4 mb-2">
                  <span class="font-bold text-emerald-400" style="color: #34d399 !important;">@' . htmlspecialchars($log['usuario']) . '</span>
                  <span class="text-slate-500 text-[10px]">' . $fecha_formateada . '</span>
                </div>
                <p class="leading-relaxed font-mono bg-slate-950/30 p-2 rounded text-slate-300 border border-slate-900" style="background-color: rgba(2, 6, 23, 0.3) !important; border: 1px solid #0f172a !important;">' . htmlspecialchars($log['tipo_cambio']) . '</p>
              </div>
            </div>';
        }
        $timelineHTML .= '</div>';
    }
    // Calculate close URL preserving filters (relative link changing query string)
    $cleanGet = $_GET;
    unset($cleanGet['historial']);
    unset($cleanGet['ver_historial']);
    $queryStr = http_build_query($cleanGet);
    $closeUrl = '?' . $queryStr;
    
    echo '
    <div role="dialog" id="historial-modal" aria-labelledby="historial-modal-title" aria-modal="true" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.75); padding: 1rem;">
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl animate-fade-in" style="position: relative; width: 100%; max-width: 42rem; border-radius: 1rem; border: 1px solid #475569; background-color: #0f172a !important; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        
        <!-- Header -->
        <div class="mb-4 flex items-start justify-between" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-start;">
          <div>
            <h2 id="historial-modal-title" class="text-2xl font-semibold text-white flex items-center gap-2" style="font-size: 1.5rem; font-weight: 600; color: #ffffff !important; margin: 0;">
              Historial de Cambios: TKT-' . str_pad($ticket_id, 3, '0', STR_PAD_LEFT) . '
            </h2>
            <p class="mt-1 text-sm text-slate-400" style="margin-top: 0.25rem; font-size: 0.875rem; color: #94a3b8 !important;">
              Línea de tiempo de auditoría automática para: "' . htmlspecialchars($ticketTitulo) . '"
            </p>
          </div>
        </div>

        <div class="space-y-4" style="margin-top: 1rem;">
          <!-- Scrollable Timeline Container -->
          <div class="pr-2" style="max-height: 50vh; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #475569 #0f172a; padding-right: 0.5rem;">
            ' . $timelineHTML . '
          </div>

          <!-- Footer Actions (Single Close Button) -->
          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800" style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #1e293b; margin-top: 1rem;">
            <a
              href="' . htmlspecialchars($closeUrl) . '"
              onclick="document.getElementById(\'historial-modal\').remove();"
              style="display: inline-block; padding: 10px 20px; border-radius: 12px; border: 1px solid #475569; background-color: #1e293b; color: #cbd5e1; font-weight: 500; font-size: 14px; text-decoration: none; cursor: pointer; transition: background-color 0.2s; text-align: center;"
              onmouseover="this.style.backgroundColor=\'#334155\'"
              onmouseout="this.style.backgroundColor=\'#1e293b\'"
            >
              Cerrar
            </a>
          </div>
        </div>

      </div>
    </div>';
}

function asegurarTablasEstructura($conn) {
    // 1. Crear tabla notificaciones si no existe
    $createNotificaciones = "
        CREATE TABLE IF NOT EXISTS `notificaciones` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `usuario_id` INT NOT NULL,
          `equipo` VARCHAR(50) NOT NULL,
          `titulo` VARCHAR(255) NOT NULL,
          `descripcion` TEXT NOT NULL,
          `enlace` VARCHAR(255) NOT NULL,
          `leido` TINYINT(1) DEFAULT 0,
          `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    mysqli_query($conn, $createNotificaciones);

    // 2. Crear tabla configuraciones si no existe
    $createConfiguraciones = "
        CREATE TABLE IF NOT EXISTS `configuraciones` (
          `clave` VARCHAR(50) PRIMARY KEY,
          `valor` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    mysqli_query($conn, $createConfiguraciones);

    // 3. Asegurar la columna estado en la tabla incidencias con los 5 estados oficiales (HU-42)
    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM `incidencias` LIKE 'estado'");
    if (mysqli_num_rows($checkCol) == 0) {
        mysqli_query($conn, "ALTER TABLE `incidencias` ADD COLUMN `estado` VARCHAR(50) NOT NULL DEFAULT 'Reportada'");
    } else {
        mysqli_query($conn, "ALTER TABLE `incidencias` MODIFY COLUMN `estado` VARCHAR(50) NOT NULL DEFAULT 'Reportada'");
    }

    // 4. Asegurar campos de dictamen técnico, solución temporal y motivo de descarte
    $checkDictamen = mysqli_query($conn, "SHOW COLUMNS FROM `incidencias` LIKE 'dictamen_tecnico'");
    if (mysqli_num_rows($checkDictamen) == 0) {
        mysqli_query($conn, "ALTER TABLE `incidencias` ADD COLUMN `dictamen_tecnico` TEXT NULL");
    }

    $checkSolTemp = mysqli_query($conn, "SHOW COLUMNS FROM `incidencias` LIKE 'solucion_temporal'");
    if (mysqli_num_rows($checkSolTemp) == 0) {
        mysqli_query($conn, "ALTER TABLE `incidencias` ADD COLUMN `solucion_temporal` TEXT NULL");
    }

    $checkMotivo = mysqli_query($conn, "SHOW COLUMNS FROM `incidencias` LIKE 'motivo_descarte'");
    if (mysqli_num_rows($checkMotivo) == 0) {
        mysqli_query($conn, "ALTER TABLE `incidencias` ADD COLUMN `motivo_descarte` TEXT NULL");
    }

    // 4.b HU-48: columnas de severidad, plazos operativos y cumplimiento SLA de desarrollo
    $columnasHu48 = [
        'severidad'                    => "VARCHAR(20) NULL DEFAULT NULL",
        'plazo_mitigacion_horas'       => "INT NULL DEFAULT NULL",
        'plazo_correccion_horas'       => "INT NULL DEFAULT NULL",
        'fecha_limite_mitigacion'      => "DATETIME NULL DEFAULT NULL",
        'fecha_limite_correccion'      => "DATETIME NULL DEFAULT NULL",
        'fecha_mitigacion'             => "DATETIME NULL DEFAULT NULL",
        'tiempo_efectivo_minutos'      => "INT NULL DEFAULT NULL",
        'cumplio_sla_desarrollo'       => "TINYINT(1) NULL DEFAULT NULL",
        'alerta_vencimiento_notificada' => "TINYINT(1) NOT NULL DEFAULT 0",
    ];
    foreach ($columnasHu48 as $nombreCol => $defCol) {
        $checkColHu48 = mysqli_query($conn, "SHOW COLUMNS FROM `incidencias` LIKE '$nombreCol'");
        if ($checkColHu48 && mysqli_num_rows($checkColHu48) == 0) {
            mysqli_query($conn, "ALTER TABLE `incidencias` ADD COLUMN `$nombreCol` $defCol");
        }
    }

    // 4.c HU-48: completar plazos operativos de incidencias existentes según la prioridad del ticket padre
    // (las que ya vencieron se marcan como notificadas para no disparar alertas masivas retroactivas)
    mysqli_query($conn, "
        UPDATE `incidencias` i
        JOIN `tickets` t ON t.id = i.ticket_id
        SET i.severidad = CASE LOWER(t.prioridad) WHEN 'critica' THEN 'Crítica' WHEN 'alta' THEN 'Alta' WHEN 'baja' THEN 'Baja' ELSE 'Media' END,
            i.plazo_mitigacion_horas = CASE LOWER(t.prioridad) WHEN 'critica' THEN 2 WHEN 'alta' THEN 12 WHEN 'baja' THEN NULL ELSE 24 END,
            i.plazo_correccion_horas = CASE LOWER(t.prioridad) WHEN 'critica' THEN 4 WHEN 'alta' THEN 24 WHEN 'baja' THEN 96 ELSE 48 END,
            i.fecha_limite_mitigacion = CASE LOWER(t.prioridad)
                WHEN 'critica' THEN DATE_ADD(i.fecha_creacion, INTERVAL 2 HOUR)
                WHEN 'alta' THEN DATE_ADD(i.fecha_creacion, INTERVAL 12 HOUR)
                WHEN 'baja' THEN NULL
                ELSE DATE_ADD(i.fecha_creacion, INTERVAL 24 HOUR) END,
            i.fecha_limite_correccion = DATE_ADD(i.fecha_creacion, INTERVAL (CASE LOWER(t.prioridad) WHEN 'critica' THEN 4 WHEN 'alta' THEN 24 WHEN 'baja' THEN 96 ELSE 48 END) HOUR),
            i.alerta_vencimiento_notificada = IF(DATE_ADD(i.fecha_creacion, INTERVAL (CASE LOWER(t.prioridad) WHEN 'critica' THEN 2 WHEN 'alta' THEN 12 WHEN 'baja' THEN 96 ELSE 24 END) HOUR) < NOW(), 1, 0)
        WHERE i.severidad IS NULL
    ");

    // 5. Migrar estados anteriores si existieran
    mysqli_query($conn, "UPDATE `incidencias` SET `estado` = 'Reportada' WHERE `estado` = 'abierto' OR `estado` IS NULL OR `estado` = ''");
    mysqli_query($conn, "UPDATE `incidencias` SET `estado` = 'EnInvestigacion' WHERE `estado` = 'en progreso'");
    mysqli_query($conn, "UPDATE `incidencias` SET `estado` = 'Corregida' WHERE `estado` = 'cerrado'");

    // 6. Asegurar columna fecha_vencimiento en tickets (HU-39)
    $checkVenc = mysqli_query($conn, "SHOW COLUMNS FROM `tickets` LIKE 'fecha_vencimiento'");
    if (mysqli_num_rows($checkVenc) == 0) {
        mysqli_query($conn, "ALTER TABLE `tickets` ADD COLUMN `fecha_vencimiento` TIMESTAMP NULL DEFAULT NULL AFTER `fecha_apertura`");
    }

    // 7. Asegurar tabla relaciones_incidencias para vinculación y control de dependencias
    $createRelaciones = "
        CREATE TABLE IF NOT EXISTS `relaciones_incidencias` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `incidencia_origen_id` INT NOT NULL,
          `incidencia_destino_id` INT NOT NULL,
          `tipo_relacion` VARCHAR(50) NOT NULL,
          `fecha_vinculacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY `relacion_unica` (`incidencia_origen_id`, `incidencia_destino_id`),
          KEY `incidencia_destino_id` (`incidencia_destino_id`),
          FOREIGN KEY (`incidencia_origen_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
          FOREIGN KEY (`incidencia_destino_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    mysqli_query($conn, $createRelaciones);
}

// =========================================================================
// HU-42: MÁQUINA DE 5 ESTADOS ESTRICTA PARA INCIDENCIAS TÉCNICAS
// =========================================================================

if (!defined('ESTADOS_OFICIALES_INCIDENCIAS')) {
    define('ESTADOS_OFICIALES_INCIDENCIAS', [
        'Reportada',
        'EnInvestigacion',
        'Mitigada',
        'Corregida',
        'Desestimada'
    ]);
}

if (!function_exists('getEstadosOficialesIncidencias')) {
    function getEstadosOficialesIncidencias() {
        return ESTADOS_OFICIALES_INCIDENCIAS;
    }
}

if (!function_exists('getEstadoNombreLegible')) {
    function getEstadoNombreLegible($estado) {
        switch (trim($estado)) {
            case 'Reportada': return 'Reportada';
            case 'EnInvestigacion': return 'En Investigación';
            case 'Mitigada': return 'Mitigada';
            case 'Corregida': return 'Corregida';
            case 'Desestimada': return 'Desestimada';
            case 'abierto': return 'Reportada';
            case 'en progreso': return 'En Investigación';
            case 'cerrado': return 'Corregida';
            default: return $estado;
        }
    }
}

if (!function_exists('getEstadoBadgeClass')) {
    function getEstadoBadgeClass($estado) {
        switch (strtolower(str_replace(' ', '', trim($estado)))) {
            case 'reportada':
            case 'abierto':
                return 'badge-reportada';
            case 'eninvestigacion':
            case 'enprogreso':
                return 'badge-investigacion';
            case 'mitigada':
                return 'badge-mitigada';
            case 'corregida':
            case 'cerrado':
            case 'resuelto':
                return 'badge-corregida';
            case 'desestimada':
                return 'badge-desestimada';
            default:
                return 'badge-reportada';
        }
    }
}

if (!function_exists('esEstadoTerminalIncidencia')) {
    function esEstadoTerminalIncidencia($estado) {
        $est = strtolower(trim($estado));
        return in_array($est, ['corregida', 'desestimada']);
    }
}

if (!function_exists('obtenerIncidenciasBloqueantesTicket')) {
    function obtenerIncidenciasBloqueantesTicket($conn, $ticket_id) {
        $ticket_id = intval($ticket_id);
        $query = mysqli_query($conn, "
            SELECT id, estado, detalle 
            FROM incidencias 
            WHERE ticket_id = $ticket_id 
              AND estado IN ('Reportada', 'EnInvestigacion', 'Mitigada', 'abierto', 'en progreso')
        ");
        if (!$query) return [];
        return mysqli_fetch_all($query, MYSQLI_ASSOC);
    }
}

if (!function_exists('obtenerHorasSlaPorPrioridad')) {
    /**
     * Obtiene las horas límites base de SLA según la prioridad del ticket.
     * Crítica: 4h, Alta: 24h, Media: 48h, Baja: 96h (alineado a los plazos de corrección de HU-48)
     */
    function obtenerHorasSlaPorPrioridad($prioridad): int {
        switch (strtolower(trim($prioridad ?? ''))) {
            case 'critica':
                return 4;
            case 'alta':
                return 24;
            case 'media':
                return 48;
            case 'baja':
            default:
                return 96;
        }
    }
}

if (!function_exists('contarIncidenciasActivasTicket')) {
    /**
     * Cuenta las incidencias técnicas en estado activo (Reportada, EnInvestigacion, Mitigada) asociadas a un ticket.
     */
    function contarIncidenciasActivasTicket($conn, int $ticket_id): int {
        $ticket_id = intval($ticket_id);
        if (!$conn || $ticket_id <= 0) {
            return 0;
        }
        $q = mysqli_query($conn, "
            SELECT COUNT(*) AS activas
            FROM incidencias
            WHERE ticket_id = $ticket_id
              AND estado IN ('Reportada', 'EnInvestigacion', 'Mitigada', 'abierto', 'en progreso')
        ");
        if ($q && ($row = mysqli_fetch_assoc($q))) {
            return intval($row['activas'] ?? 0);
        }
        return 0;
    }
}

if (!function_exists('formatearNumeroHorasSla')) {
    function formatearNumeroHorasSla($horas): string {
        $val = floatval($horas);
        if (floor($val) == $val) {
            return (string)intval($val);
        }
        return (string)round($val, 1);
    }
}

if (!function_exists('calcularHorasSlaEfectivas')) {
    /**
     * Si un ticket acumula más de una incidencia en estado activo (Reportada, EnInvestigacion, Mitigada),
     * el SLA se recalcula sumando un 50% del tiempo base por cada incidencia abierta adicional:
     * Horas SLA Efectivas = Horas Base + ((Incidencias Activas - 1) x (Horas Base x 0.5))
     */
    function calcularHorasSlaEfectivas($horasBase, int $incidenciasActivas): array {
        $base = floatval($horasBase);
        $activas = max(0, intval($incidenciasActivas));
        $adicionales = max(0, $activas - 1);
        $horasProrroga = $adicionales > 0 ? ($adicionales * ($base * 0.5)) : 0;
        $horasEfectivas = $base + $horasProrroga;

        // Normalizar a int si no tiene decimales para mantener compatibilidad estricta de tipos
        $baseNorm = (floor($base) == $base) ? intval($base) : round($base, 1);
        $prorrogaNorm = (floor($horasProrroga) == $horasProrroga) ? intval($horasProrroga) : round($horasProrroga, 1);
        $efectivasNorm = (floor($horasEfectivas) == $horasEfectivas) ? intval($horasEfectivas) : round($horasEfectivas, 1);

        return [
            'horas_base' => $baseNorm,
            'incidencias_activas' => $activas,
            'incidencias_adicionales' => $adicionales,
            'horas_prorroga' => $prorrogaNorm,
            'horas_efectivas' => $efectivasNorm,
            'tiene_prorroga' => ($activas > 1)
        ];
    }
}

if (!function_exists('recalcularSlaTicket')) {
    /**
     * Recalcula el tiempo límite (fecha_vencimiento y horas efectivas) de un ticket según sus incidencias activas.
     * Si se indica $nuevaIncidenciaId y el ticket acumula > 1 incidencias activas, registra automáticamente
     * el log de ampliación de SLA en historial_tickets.
     */
    function recalcularSlaTicket($conn, int $ticket_id, ?int $nuevaIncidenciaId = null): array {
        $ticket_id = intval($ticket_id);
        if (!$conn || $ticket_id <= 0) {
            return calcularHorasSlaEfectivas(96, 0);
        }

        $qTkt = mysqli_query($conn, "SELECT id, prioridad, fecha_apertura FROM tickets WHERE id = $ticket_id");
        $tkt = $qTkt ? mysqli_fetch_assoc($qTkt) : null;
        $prioridad = $tkt['prioridad'] ?? 'baja';
        $horasBase = obtenerHorasSlaPorPrioridad($prioridad);
        $incidenciasActivas = contarIncidenciasActivasTicket($conn, $ticket_id);

        $calculo = calcularHorasSlaEfectivas($horasBase, $incidenciasActivas);
        $segundosEfectivos = intval(round($calculo['horas_efectivas'] * 3600));

        if ($tkt && !empty($tkt['fecha_apertura'])) {
            mysqli_query($conn, "
                UPDATE tickets 
                SET fecha_vencimiento = DATE_ADD(fecha_apertura, INTERVAL $segundosEfectivos SECOND)
                WHERE id = $ticket_id
            ");
        }

        if ($nuevaIncidenciaId !== null && $nuevaIncidenciaId > 0 && $calculo['tiene_prorroga']) {
            $incCod = 'INC-' . str_pad($nuevaIncidenciaId, 3, '0', STR_PAD_LEFT);
            $baseTxt = formatearNumeroHorasSla($calculo['horas_base']);
            $prorrogaTxt = formatearNumeroHorasSla($calculo['horas_prorroga']);
            $efectivasTxt = formatearNumeroHorasSla($calculo['horas_efectivas']);
            $msgLog = "⏱️ Ampliación automática de SLA por apertura de {$incCod}: El ticket acumula {$incidenciasActivas} incidencias activas. Tiempo límite extendido a {$efectivasTxt}h (Base: {$baseTxt}h | SLA +{$prorrogaTxt}h por {$incidenciasActivas} incidencias).";
            registrarHistorialTicket($conn, $ticket_id, $msgLog);
        }

        return $calculo;
    }
}

if (!function_exists('anexarDictamenATicket')) {
    /**
     * Anexa de forma acumulativa y legible el dictamen técnico de una incidencia corregida
     * en el campo respuesta_resolucion del ticket principal sin pisar las soluciones previas.
     */
    function anexarDictamenATicket($conn, int $ticket_id, int $incidencia_id, string $dictamen_tecnico): bool {
        $ticket_id = intval($ticket_id);
        $incidencia_id = intval($incidencia_id);
        $dictamen_trim = trim($dictamen_tecnico);

        if (!$conn || $ticket_id <= 0 || $incidencia_id <= 0 || $dictamen_trim === '') {
            return false;
        }

        $qTkt = mysqli_query($conn, "SELECT respuesta_resolucion FROM tickets WHERE id = $ticket_id");
        if (!$qTkt || mysqli_num_rows($qTkt) === 0) {
            return false;
        }
        $rowTkt = mysqli_fetch_assoc($qTkt);
        $resolucionActual = trim((string)($rowTkt['respuesta_resolucion'] ?? ''));

        $incCod = 'INC-' . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT);
        $prefijo = "[{$incCod}]:";
        $nuevaLinea = "{$prefijo} {$dictamen_trim}";

        if ($resolucionActual === '') {
            $resolucionAcumulada = $nuevaLinea;
        } else {
            // Si ya existía una entrada para esta misma incidencia, actualizar esa línea sin perder las demás
            $lineas = preg_split("/\r\n|\n|\r/", $resolucionActual);
            $encontrada = false;
            foreach ($lineas as $idx => $linea) {
                if (strpos(trim($linea), $prefijo) === 0) {
                    $lineas[$idx] = $nuevaLinea;
                    $encontrada = true;
                    break;
                }
            }
            if ($encontrada) {
                $resolucionAcumulada = implode("\n", $lineas);
            } else {
                $resolucionAcumulada = $resolucionActual . "\n" . $nuevaLinea;
            }
        }

        $resolucionEsc = mysqli_real_escape_string($conn, $resolucionAcumulada);
        return (bool)mysqli_query($conn, "UPDATE tickets SET respuesta_resolucion = '$resolucionEsc' WHERE id = $ticket_id");
    }
}

// ============================================================================
// HU-48: TIEMPOS LIMITE DE DESARROLLO PARA MITIGACION DE INCIDENCIAS
// ============================================================================

if (!function_exists('obtenerPlazosOperativosPorSeveridad')) {
    /**
     * Fija los plazos operativos alineados al SLA base del ticket padre (HU-48)
     * - Severidad Crítica: Máximo 2h para mitigar y 4h para corregir (tope 4h).
     * - Severidad Alta: Máximo 12h para mitigar y 24h para corregir (tope 24h).
     * - Severidad Media: Máximo 24h para mitigar y 48h (2 días hábiles) para corregir.
     * - Severidad Baja: Plazo único de 96h (4 días hábiles) para corrección definitiva.
     */
    function obtenerPlazosOperativosPorSeveridad($severidad) {
        $sevNorm = strtolower(trim(str_replace(['í', 'Í'], ['i', 'I'], $severidad ?? 'media')));
        switch ($sevNorm) {
            case 'critica':
                return [
                    'severidad' => 'Crítica',
                    'mitigacion_horas' => 2,
                    'correccion_horas' => 4,
                    'limite_ticket_horas' => 4,
                    'descripcion' => 'Máximo 2 horas para mitigar y 4 horas para corregir (tope 4h)'
                ];
            case 'alta':
                return [
                    'severidad' => 'Alta',
                    'mitigacion_horas' => 12,
                    'correccion_horas' => 24,
                    'limite_ticket_horas' => 24,
                    'descripcion' => 'Máximo 12 horas para mitigar y 24 horas para corregir (tope 24h)'
                ];
            case 'baja':
                return [
                    'severidad' => 'Baja',
                    'mitigacion_horas' => null, // Plazo único de 96h para corrección definitiva
                    'correccion_horas' => 96,
                    'limite_ticket_horas' => 96,
                    'descripcion' => 'Plazo único de 96 horas (4 días hábiles) para corrección definitiva'
                ];
            case 'media':
            default:
                return [
                    'severidad' => 'Media',
                    'mitigacion_horas' => 24,
                    'correccion_horas' => 48,
                    'limite_ticket_horas' => 48,
                    'descripcion' => 'Máximo 24 horas para mitigar y 48 horas (2 días hábiles) para corregir'
                ];
        }
    }
}

if (!function_exists('formatearMinutos')) {
    function formatearMinutos($minutos) {
        $minutos = intval($minutos);
        if ($minutos < 60) return $minutos . 'm';
        $horas = floor($minutos / 60);
        $restoMin = $minutos % 60;
        return $horas . 'h' . ($restoMin > 0 ? ' ' . $restoMin . 'm' : '');
    }
}

if (!function_exists('formatearSegundos')) {
    function formatearSegundos($segundos) {
        $segundos = max(0, intval($segundos));
        $horas = floor($segundos / 3600);
        $minutos = floor(($segundos % 3600) / 60);
        if ($horas >= 24) {
            $dias = floor($horas / 24);
            $restoH = $horas % 24;
            return $dias . 'd ' . $restoH . 'h';
        }
        return $horas . 'h ' . $minutos . 'm';
    }
}

if (!function_exists('calcularEstadoSLADesarrollo')) {
    /**
     * Calcula la cuenta regresiva y etiquetas visuales (HU-48 Criterio 2 y 3)
     * - En Plazo (🟢)
     * - Próximo a Vencer (⚠️ menos del 25% del tiempo restante)
     * - Incidencia Vencida (🚨 superó el plazo sin mitigarse/corregirse)
     * - Congela el temporizador si pasa a Mitigada o Corregida.
     */
    function calcularEstadoSLADesarrollo($incidencia) {
        $estado = $incidencia['incidencia_estado'] ?? ($incidencia['estado'] ?? 'Reportada');
        
        // 1. Caso Desestimada
        if ($estado === 'Desestimada') {
            return [
                'estado_sla' => 'Desestimada',
                'badge_label' => '⚪ Desestimada',
                'badge_class' => 'badge-sla-desestimada',
                'es_vencida' => false,
                'es_congelado' => true,
                'tiempo_texto' => 'Desestimada (Sin afectación SLA)',
                'porcentaje_restante' => 100,
                'segundos_restantes' => 0,
                'tiempo_restante_texto' => 'Desestimada',
                'hito_activo' => 'Desestimada',
                'meta_horas' => 0
            ];
        }

        // 2. Criterio 3: Si pasa a Mitigada o Corregida, el temporizador se detiene congelando el tiempo efectivo
        if ($estado === 'Mitigada' || $estado === 'Corregida') {
            $tiempoEfectivo = intval($incidencia['tiempo_efectivo_minutos'] ?? 0);
            if ($tiempoEfectivo <= 0 && !empty($incidencia['fecha_creacion'])) {
                $refTime = !empty($incidencia['fecha_mitigacion']) 
                    ? strtotime($incidencia['fecha_mitigacion']) 
                    : (!empty($incidencia['incidencia_fecha_cierre'] ?? ($incidencia['fecha_cierre'] ?? null)) 
                        ? strtotime($incidencia['incidencia_fecha_cierre'] ?? $incidencia['fecha_cierre']) 
                        : time());
                $tiempoEfectivo = max(1, round(($refTime - strtotime($incidencia['fecha_creacion'])) / 60));
            }
            $tiempoFormateado = formatearMinutos($tiempoEfectivo);

            // Verificar si cumplió el SLA técnico de desarrollo
            $cumplio = ($incidencia['cumplio_sla_desarrollo'] === 1 || $incidencia['cumplio_sla_desarrollo'] === '1');
            if ($incidencia['cumplio_sla_desarrollo'] === null) {
                $limite = !empty($incidencia['fecha_limite_mitigacion']) 
                    ? strtotime($incidencia['fecha_limite_mitigacion']) 
                    : (!empty($incidencia['fecha_limite_correccion']) 
                        ? strtotime($incidencia['fecha_limite_correccion']) 
                        : (strtotime($incidencia['fecha_creacion']) + 86400));
                $cumplio = (strtotime($incidencia['fecha_mitigacion'] ?? ($incidencia['incidencia_fecha_cierre'] ?? ($incidencia['fecha_cierre'] ?? 'now'))) <= $limite);
            }

            if ($cumplio) {
                return [
                    'estado_sla' => 'En Plazo',
                    'badge_label' => '🟢 En Plazo',
                    'badge_class' => 'badge-sla-enplazo',
                    'es_vencida' => false,
                    'es_congelado' => true,
                    'tiempo_texto' => '❄️ ' . $tiempoFormateado . ' (Congelado)',
                    'porcentaje_restante' => 100,
                    'segundos_restantes' => 0,
                    'tiempo_restante_texto' => $estado . ' en ' . $tiempoFormateado,
                    'hito_activo' => ($estado === 'Mitigada') ? 'Mitigada' : 'Corregida',
                    'meta_horas' => intval($incidencia['plazo_mitigacion_horas'] ?? ($incidencia['plazo_correccion_horas'] ?? 24))
                ];
            } else {
                return [
                    'estado_sla' => 'Incidencia Vencida',
                    'badge_label' => '🚨 Incidencia Vencida',
                    'badge_class' => 'badge-sla-vencida',
                    'es_vencida' => true,
                    'es_congelado' => true,
                    'tiempo_texto' => '❄️ ' . $tiempoFormateado . ' (Fuera de SLA)',
                    'porcentaje_restante' => 0,
                    'segundos_restantes' => 0,
                    'tiempo_restante_texto' => 'Vencida (' . $tiempoFormateado . ')',
                    'hito_activo' => ($estado === 'Mitigada') ? 'Mitigada' : 'Corregida',
                    'meta_horas' => intval($incidencia['plazo_mitigacion_horas'] ?? ($incidencia['plazo_correccion_horas'] ?? 24))
                ];
            }
        }

        // 3. Casos Activos (Reportada, EnInvestigacion)
        $plazoMit = !empty($incidencia['plazo_mitigacion_horas']) ? intval($incidencia['plazo_mitigacion_horas']) : null;
        $plazoCorr = !empty($incidencia['plazo_correccion_horas']) ? intval($incidencia['plazo_correccion_horas']) : 48;
        
        if ($plazoMit !== null && !empty($incidencia['fecha_limite_mitigacion'])) {
            $deadline = strtotime($incidencia['fecha_limite_mitigacion']);
            $totalSegundos = $plazoMit * 3600;
            $hito = 'Mitigación (' . $plazoMit . 'h)';
            $metaHoras = $plazoMit;
        } else {
            $deadline = !empty($incidencia['fecha_limite_correccion']) 
                ? strtotime($incidencia['fecha_limite_correccion']) 
                : (strtotime($incidencia['fecha_creacion'] ?? 'now') + ($plazoCorr * 3600));
            $totalSegundos = $plazoCorr * 3600;
            $hito = 'Corrección (' . $plazoCorr . 'h)';
            $metaHoras = $plazoCorr;
        }

        $now = time();
        $diff = $deadline - $now;

        if ($diff <= 0) {
            $segundosVencidos = abs($diff);
            return [
                'estado_sla' => 'Incidencia Vencida',
                'badge_label' => '🚨 Incidencia Vencida',
                'badge_class' => 'badge-sla-vencida',
                'es_vencida' => true,
                'es_congelado' => false,
                'tiempo_texto' => 'Expiró hace ' . formatearSegundos($segundosVencidos),
                'porcentaje_restante' => 0,
                'segundos_restantes' => 0,
                'tiempo_restante_texto' => 'Vencida (-' . formatearSegundos($segundosVencidos) . ')',
                'hito_activo' => $hito,
                'meta_horas' => $metaHoras,
                'deadline_timestamp' => $deadline
            ];
        }

        $pctRestante = ($diff / max(1, $totalSegundos)) * 100;
        $tiempoRestanteTexto = formatearSegundos($diff) . ' restantes';

        // Criterio 2 HU-48: Próximo a Vencer (menos del 25% del tiempo restante)
        if ($pctRestante < 25.0) {
            return [
                'estado_sla' => 'Próximo a Vencer',
                'badge_label' => '⚠️ Próximo a Vencer',
                'badge_class' => 'badge-sla-proximo',
                'es_vencida' => false,
                'es_congelado' => false,
                'tiempo_texto' => '⏳ ' . $tiempoRestanteTexto,
                'porcentaje_restante' => round($pctRestante, 1),
                'segundos_restantes' => $diff,
                'tiempo_restante_texto' => $tiempoRestanteTexto,
                'hito_activo' => $hito,
                'meta_horas' => $metaHoras,
                'deadline_timestamp' => $deadline
            ];
        } else {
            return [
                'estado_sla' => 'En Plazo',
                'badge_label' => '🟢 En Plazo',
                'badge_class' => 'badge-sla-enplazo',
                'es_vencida' => false,
                'es_congelado' => false,
                'tiempo_texto' => '⏳ ' . $tiempoRestanteTexto,
                'porcentaje_restante' => round($pctRestante, 1),
                'segundos_restantes' => $diff,
                'tiempo_restante_texto' => $tiempoRestanteTexto,
                'hito_activo' => $hito,
                'meta_horas' => $metaHoras,
                'deadline_timestamp' => $deadline
            ];
        }
    }
}

if (!function_exists('verificarVencimientosIncidencias')) {
    /**
     * Criterio 4 HU-48: Si una incidencia supera el plazo sin mitigarse, se debe
     * registrar automáticamente una alerta en el historial del ticket y notificar al Responsable de Monitoreo.
     */
    function verificarVencimientosIncidencias($conn) {
        $q = mysqli_query($conn, "
            SELECT inc.id, inc.ticket_id, inc.severidad, inc.plazo_mitigacion_horas, inc.plazo_correccion_horas,
                   inc.fecha_limite_mitigacion, inc.fecha_limite_correccion, t.titulo AS ticket_titulo
            FROM incidencias inc
            JOIN tickets t ON inc.ticket_id = t.id
            WHERE inc.estado IN ('Reportada', 'EnInvestigacion')
              AND inc.alerta_vencimiento_notificada = 0
              AND (
                  (inc.fecha_limite_mitigacion IS NOT NULL AND CURRENT_TIMESTAMP > inc.fecha_limite_mitigacion)
                  OR
                  (inc.fecha_limite_mitigacion IS NULL AND CURRENT_TIMESTAMP > inc.fecha_limite_correccion)
              )
        ");
        
        $alertados = 0;
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $incId = intval($row['id']);
                $ticketId = intval($row['ticket_id']);
                $sev = $row['severidad'] ?? 'Media';
                $plazoHoras = !empty($row['plazo_mitigacion_horas']) 
                    ? ($row['plazo_mitigacion_horas'] . 'h para mitigar') 
                    : ($row['plazo_correccion_horas'] . 'h para corregir');

                // 1. Registrar automáticamente alerta en historial del ticket
                $alertaHistorial = "🚨 ALERTA SLA DESARROLLO: La incidencia técnica INC-" . str_pad($incId, 3, '0', STR_PAD_LEFT) . " (Severidad $sev) ha superado el plazo operativo ($plazoHoras) sin solución temporal aplicada.";
                registrarHistorialTicket($conn, $ticketId, $alertaHistorial);

                // 2. Notificar al Responsable de Monitoreo
                crearNotificacion(
                    $conn,
                    'monitoreo',
                    '🚨 Incidencia Vencida sin Mitigar',
                    "La incidencia INC-" . str_pad($incId, 3, '0', STR_PAD_LEFT) . " del ticket #$ticketId superó el plazo sin mitigación. Severidad: $sev.",
                    "incidentes.php?ver=$incId"
                );

                // Marcar como notificada y marcar SLA incumplido
                mysqli_query($conn, "
                    UPDATE incidencias 
                    SET alerta_vencimiento_notificada = 1,
                        cumplio_sla_desarrollo = 0
                    WHERE id = $incId
                ");
                $alertados++;
            }
        }
        return $alertados;
    }
}

if (!function_exists('obtenerMetricasCumplimientoDesarrollo')) {
    /**
     * Criterio 3 HU-48: Tasa de cumplimiento del equipo de desarrollo (meta global >= 85.0%)
     */
    function obtenerMetricasCumplimientoDesarrollo($conn) {
        $q = mysqli_query($conn, "
            SELECT id, estado, severidad, plazo_mitigacion_horas, plazo_correccion_horas,
                   fecha_creacion, fecha_mitigacion, fecha_cierre, fecha_limite_mitigacion, fecha_limite_correccion,
                   tiempo_efectivo_minutos, cumplio_sla_desarrollo
            FROM incidencias
            WHERE estado != 'Desestimada'
        ");

        $totalEvaluadas = 0;
        $enPlazo = 0;
        $proximoVencer = 0;
        $vencidas = 0;
        $mitigadas = 0;
        $corregidas = 0;

        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $totalEvaluadas++;
                $sla = calcularEstadoSLADesarrollo($row);
                if ($sla['estado_sla'] === 'En Plazo') {
                    $enPlazo++;
                } elseif ($sla['estado_sla'] === 'Próximo a Vencer') {
                    $proximoVencer++;
                    $enPlazo++; // Está en plazo aunque próximo a vencer
                } elseif ($sla['estado_sla'] === 'Incidencia Vencida') {
                    $vencidas++;
                }

                if ($row['estado'] === 'Mitigada') $mitigadas++;
                if ($row['estado'] === 'Corregida') $corregidas++;
            }
        }

        $tasa = ($totalEvaluadas > 0) ? round(($enPlazo / $totalEvaluadas) * 100, 1) : 100.0;
        $metaGlobal = 85.0;
        $cumpleMeta = ($tasa >= $metaGlobal);

        return [
            'total_evaluadas' => $totalEvaluadas,
            'en_plazo_total' => $enPlazo,
            'proximas_a_vencer' => $proximoVencer,
            'vencidas_total' => $vencidas,
            'mitigadas_total' => $mitigadas,
            'corregidas_total' => $corregidas,
            'tasa_cumplimiento' => $tasa,
            'meta_global' => $metaGlobal,
            'cumple_meta' => $cumpleMeta
        ];
    }
}

if (!function_exists('registrarIncidencia')) {
    /**
     * Criterio 1 HU-48: Al darse de alta una incidencia técnica (Reportada), el sistema
     * debe fijar los plazos operativos alineados al SLA base del ticket padre.
     */
    function registrarIncidencia($conn, $ticket_id, $juego_id, $driver_id, $detalle, $severidad = null) {
        // Garantiza que existan las columnas de HU-42/HU-48 antes de insertar (una vez por request)
        static $esquemaVerificado = false;
        if (!$esquemaVerificado) {
            asegurarTablasEstructura($conn);
            $esquemaVerificado = true;
        }
        $ticket_id = intval($ticket_id);
        // Juego y driver son opcionales: se persisten como NULL si no se informan
        $juego_id = (!empty($juego_id) && intval($juego_id) > 0) ? intval($juego_id) : 'NULL';
        $driver_id = (!empty($driver_id) && intval($driver_id) > 0) ? intval($driver_id) : 'NULL';
        $detalle_trim = trim($detalle);

        if ($ticket_id <= 0 || empty($detalle_trim)) {
            throw new InvalidArgumentException("El ticket y el detalle técnico son obligatorios para registrar la incidencia.");
        }

        // HU-48: Obtener severidad y plazos operativos alineados al SLA base del ticket padre
        if (empty($severidad)) {
            $qTkt = mysqli_query($conn, "SELECT prioridad, titulo FROM tickets WHERE id = $ticket_id");
            $tktRow = $qTkt ? mysqli_fetch_assoc($qTkt) : null;
            $prioridadTicket = $tktRow['prioridad'] ?? 'media';
            $plazos = obtenerPlazosOperativosPorSeveridad($prioridadTicket);
        } else {
            $plazos = obtenerPlazosOperativosPorSeveridad($severidad);
        }

        $sevVal = $plazos['severidad'];
        $mitVal = ($plazos['mitigacion_horas'] !== null) ? intval($plazos['mitigacion_horas']) : 'NULL';
        $corrVal = intval($plazos['correccion_horas']);
        $limitMitSql = ($plazos['mitigacion_horas'] !== null) ? "DATE_ADD(CURRENT_TIMESTAMP, INTERVAL {$plazos['mitigacion_horas']} HOUR)" : "NULL";
        $limitCorrSql = "DATE_ADD(CURRENT_TIMESTAMP, INTERVAL {$plazos['correccion_horas']} HOUR)";

        $detalle_esc = mysqli_real_escape_string($conn, $detalle_trim);
        // Toda nueva incidencia creada mediante registrarIncidencia() inicia obligatoriamente en estado Reportada
        $estado_inicial = 'Reportada';

        $insertQuery = "INSERT INTO incidencias (
                            ticket_id, juego_id, driver_id, detalle, estado, fecha_creacion,
                            severidad, plazo_mitigacion_horas, plazo_correccion_horas,
                            fecha_limite_mitigacion, fecha_limite_correccion, alerta_vencimiento_notificada
                        ) VALUES (
                            $ticket_id, $juego_id, $driver_id, '$detalle_esc', '$estado_inicial', CURRENT_TIMESTAMP,
                            '$sevVal', $mitVal, $corrVal,
                            $limitMitSql, $limitCorrSql, 0
                        )";
        if (!mysqli_query($conn, $insertQuery)) {
            throw new Exception("Error al insertar la incidencia: " . mysqli_error($conn));
        }

        $nuevo_id = mysqli_insert_id($conn);
        $plazoDesc = ($plazos['mitigacion_horas'] !== null)
            ? "Límites operativos: {$plazos['mitigacion_horas']}h mitigar / {$plazos['correccion_horas']}h corregir"
            : "Límite operativo: {$plazos['correccion_horas']}h corrección definitiva";

        registrarHistorialTicket($conn, $ticket_id, "Nueva incidencia técnica registrada: INC-" . str_pad($nuevo_id, 3, '0', STR_PAD_LEFT) . " (Severidad: $sevVal | $plazoDesc | Estado inicial: Reportada)");

        // Recalcular SLA del ticket padre y auditar prórroga en historial_tickets si acumula > 1 incidencias activas
        recalcularSlaTicket($conn, $ticket_id, $nuevo_id);

        return $nuevo_id;
    }
}

if (!function_exists('analizarLogs')) {
    function analizarLogs($conn, $incidencia_id) {
        $incidencia_id = intval($incidencia_id);
        $q = mysqli_query($conn, "SELECT id, estado, ticket_id FROM incidencias WHERE id = $incidencia_id");
        $inc = $q ? mysqli_fetch_assoc($q) : null;
        if (!$inc) {
            throw new Exception("Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " no encontrada.");
        }

        if (esEstadoTerminalIncidencia($inc['estado'])) {
            throw new Exception("Los estados Corregida y Desestimada son terminales y no permiten transiciones posteriores.");
        }

        if ($inc['estado'] !== 'Reportada') {
            throw new Exception("Transición inválida: Desde '" . $inc['estado'] . "' no se puede ejecutar analizarLogs(). Solo se permite desde 'Reportada'.");
        }

        $update = "UPDATE incidencias SET estado = 'EnInvestigacion' WHERE id = $incidencia_id";
        if (!mysqli_query($conn, $update)) {
            throw new Exception("Error al actualizar estado: " . mysqli_error($conn));
        }

        $ticket_id = intval($inc['ticket_id']);
        registrarHistorialTicket($conn, $ticket_id, "Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " pasó a 'EnInvestigacion' tras la acción analizarLogs().");
        return true;
    }
}

if (!function_exists('obtenerBloqueantesActivosIncidencia')) {
    /**
     * Obtiene las incidencias bloqueantes de las cuales depende $incidencia_id
     * y que aún continúan activas (Reportada o EnInvestigacion).
     */
    function obtenerBloqueantesActivosIncidencia($conn, int $incidencia_id): array {
        $incidencia_id = intval($incidencia_id);
        if (!$conn || $incidencia_id <= 0) {
            return [];
        }
        $q = mysqli_query($conn, "
            SELECT i.id, i.estado, i.detalle, i.ticket_id
            FROM relaciones_incidencias r
            JOIN incidencias i ON r.incidencia_destino_id = i.id
            WHERE r.incidencia_origen_id = $incidencia_id
              AND r.tipo_relacion = 'bloqueante'
              AND i.estado IN ('Reportada', 'EnInvestigacion', 'abierto', 'en progreso')
            ORDER BY i.id ASC
        ");
        if (!$q) {
            return [];
        }
        return mysqli_fetch_all($q, MYSQLI_ASSOC);
    }
}

if (!function_exists('obtenerEstadoSemaforoBloqueo')) {
    /**
     * Calcula el estado del Semáforo de Bloqueo para una incidencia.
     * Retorna si está bloqueada, cuáles son sus bloqueantes activos, cuáles ya fueron corregidas,
     * y a qué incidencias bloquea esta incidencia.
     */
    function obtenerEstadoSemaforoBloqueo($conn, int $incidencia_id): array {
        $incidencia_id = intval($incidencia_id);
        $bloqueantesActivos = obtenerBloqueantesActivosIncidencia($conn, $incidencia_id);

        $bloqueantesResueltos = [];
        $bloqueaA = [];
        if ($conn && $incidencia_id > 0) {
            $qRes = mysqli_query($conn, "
                SELECT i.id, i.estado, i.detalle, i.ticket_id
                FROM relaciones_incidencias r
                JOIN incidencias i ON r.incidencia_destino_id = i.id
                WHERE r.incidencia_origen_id = $incidencia_id
                  AND r.tipo_relacion = 'bloqueante'
                  AND i.estado NOT IN ('Reportada', 'EnInvestigacion', 'abierto', 'en progreso')
                ORDER BY i.id ASC
            ");
            if ($qRes) {
                $bloqueantesResueltos = mysqli_fetch_all($qRes, MYSQLI_ASSOC);
            }

            $qDep = mysqli_query($conn, "
                SELECT i.id, i.estado, i.detalle, i.ticket_id
                FROM relaciones_incidencias r
                JOIN incidencias i ON r.incidencia_origen_id = i.id
                WHERE r.incidencia_destino_id = $incidencia_id
                  AND r.tipo_relacion = 'bloqueante'
                ORDER BY i.id ASC
            ");
            if ($qDep) {
                $bloqueaA = mysqli_fetch_all($qDep, MYSQLI_ASSOC);
            }
        }

        $estaBloqueada = !empty($bloqueantesActivos);
        $mensajeBloqueo = '';
        if ($estaBloqueada) {
            $primera = $bloqueantesActivos[0];
            $cod = 'INC-' . str_pad($primera['id'], 3, '0', STR_PAD_LEFT);
            $mensajeBloqueo = "No se puede avanzar esta incidencia: está bloqueada por {$cod} (aún en investigación)";
        }

        return [
            'bloqueada' => $estaBloqueada,
            'mensaje_bloqueo' => $mensajeBloqueo,
            'bloqueantes_activos' => $bloqueantesActivos,
            'bloqueantes_resueltos' => $bloqueantesResueltos,
            'bloquea_a' => $bloqueaA
        ];
    }
}

if (!function_exists('verificarBloqueoIncidencia')) {
    /**
     * Criterio 1: Si la Incidencia A está vinculada como dependiente de una Incidencia B de tipo bloqueante,
     * bloquea las transiciones a Mitigada y Corregida en A mientras B continúe activa (Reportada o EnInvestigacion).
     */
    function verificarBloqueoIncidencia($conn, int $incidencia_id): void {
        $semaforo = obtenerEstadoSemaforoBloqueo($conn, $incidencia_id);
        if ($semaforo['bloqueada']) {
            throw new Exception($semaforo['mensaje_bloqueo']);
        }
    }
}

if (!function_exists('obtenerIncidenciaMaestraDuplicada')) {
    /**
     * Si la incidencia está vinculada como 'duplicado' de una [INC-Maestra], devuelve los datos de la maestra.
     */
    function obtenerIncidenciaMaestraDuplicada($conn, int $incidencia_id): ?array {
        $incidencia_id = intval($incidencia_id);
        if (!$conn || $incidencia_id <= 0) {
            return null;
        }
        $q = mysqli_query($conn, "
            SELECT i.id, i.estado, i.detalle, i.ticket_id, i.dictamen_tecnico
            FROM relaciones_incidencias r
            JOIN incidencias i ON r.incidencia_destino_id = i.id
            WHERE r.incidencia_origen_id = $incidencia_id
              AND r.tipo_relacion = 'duplicado'
            ORDER BY r.id DESC
            LIMIT 1
        ");
        if ($q && mysqli_num_rows($q) > 0) {
            return mysqli_fetch_assoc($q);
        }
        return null;
    }
}

if (!function_exists('existeDependenciaCircular')) {
    /**
     * Criterio 3: Prevención de dependencias circulares.
     * Impide relaciones cíclicas inválidas directas (ej. INC-036 bloquea a INC-038 e INC-038 se marca como bloqueante de INC-036)
     * o transitivas (A -> B -> C -> A).
     */
    function existeDependenciaCircular($conn, int $origen_id, int $destino_id, string $tipo_relacion = 'bloqueante'): bool {
        $origen_id = intval($origen_id);
        $destino_id = intval($destino_id);
        if (!$conn || $origen_id <= 0 || $destino_id <= 0) {
            return false;
        }
        if ($origen_id === $destino_id) {
            return true;
        }

        $tipoNorm = strtolower(trim($tipo_relacion));
        if (!in_array($tipoNorm, ['bloqueante', 'duplicado'], true)) {
            return false;
        }

        // 1. Verificar ciclo directo inverso (destino -> origen ya registrado como bloqueante o duplicado)
        $qDirect = mysqli_query($conn, "
            SELECT id FROM relaciones_incidencias
            WHERE incidencia_origen_id = $destino_id
              AND incidencia_destino_id = $origen_id
              AND tipo_relacion IN ('bloqueante', 'duplicado')
            LIMIT 1
        ");
        if ($qDirect && mysqli_num_rows($qDirect) > 0) {
            return true;
        }

        // 2. Cargar grafo dirigido de dependencias (bloqueante / duplicado) para detectar ciclos transitivos
        $qGraph = mysqli_query($conn, "
            SELECT incidencia_origen_id, incidencia_destino_id
            FROM relaciones_incidencias
            WHERE tipo_relacion IN ('bloqueante', 'duplicado')
        ");
        if (!$qGraph) {
            return false;
        }

        $adjForward = [];
        $adjReverse = [];
        while ($row = mysqli_fetch_assoc($qGraph)) {
            $u = intval($row['incidencia_origen_id']);
            $v = intval($row['incidencia_destino_id']);
            $adjForward[$u][] = $v;
            $adjReverse[$v][] = $u;
        }

        // BFS en dirección forward: ¿es $origen_id alcanzable desde $destino_id?
        $queue = [$destino_id];
        $visited = [$destino_id => true];
        while (!empty($queue)) {
            $curr = array_shift($queue);
            if ($curr === $origen_id) {
                return true;
            }
            foreach ($adjForward[$curr] ?? [] as $next) {
                if (empty($visited[$next])) {
                    $visited[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        // BFS en dirección inversa: por si una cadena fue registrada en orientación opuesta
        $queueRev = [$destino_id];
        $visitedRev = [$destino_id => true];
        while (!empty($queueRev)) {
            $curr = array_shift($queueRev);
            if ($curr === $origen_id) {
                return true;
            }
            foreach ($adjReverse[$curr] ?? [] as $next) {
                if (empty($visitedRev[$next])) {
                    $visitedRev[$next] = true;
                    $queueRev[] = $next;
                }
            }
        }

        return false;
    }
}

if (!function_exists('obtenerSugerenciasCorrelacion')) {
    /**
     * Sugiere correlaciones automáticas para una incidencia basándose en coincidencias de juego y driver.
     */
    function obtenerSugerenciasCorrelacion($conn, int $incidencia_id, int $limite = 6): array {
        $incidencia_id = intval($incidencia_id);
        $limite = max(1, intval($limite));
        if (!$conn || $incidencia_id <= 0) {
            return [];
        }

        $qInc = mysqli_query($conn, "SELECT id, juego_id, driver_id FROM incidencias WHERE id = $incidencia_id");
        $incActual = $qInc ? mysqli_fetch_assoc($qInc) : null;
        if (!$incActual) {
            return [];
        }

        $juego_id = intval($incActual['juego_id']);
        $driver_id = intval($incActual['driver_id']);

        $qSug = mysqli_query($conn, "
            SELECT 
                inc.id,
                inc.ticket_id,
                inc.juego_id,
                inc.driver_id,
                inc.estado,
                inc.detalle,
                t.titulo AS ticket_titulo,
                j.nombre AS juego_nombre,
                d.nombre AS driver_nombre,
                ((CASE WHEN inc.juego_id = $juego_id THEN 1 ELSE 0 END) + (CASE WHEN inc.driver_id = $driver_id THEN 1 ELSE 0 END)) AS score_coincidencia
            FROM incidencias inc
            JOIN tickets t ON inc.ticket_id = t.id
            JOIN juegos j ON inc.juego_id = j.id
            JOIN drivers d ON inc.driver_id = d.id
            WHERE inc.id != $incidencia_id
              AND t.estado != 'inactivo'
              AND (inc.juego_id = $juego_id OR inc.driver_id = $driver_id)
              AND inc.id NOT IN (
                  SELECT CASE WHEN incidencia_origen_id = $incidencia_id THEN incidencia_destino_id ELSE incidencia_origen_id END
                  FROM relaciones_incidencias
                  WHERE incidencia_origen_id = $incidencia_id OR incidencia_destino_id = $incidencia_id
              )
            ORDER BY 
                score_coincidencia DESC,
                CASE WHEN inc.estado IN ('EnInvestigacion', 'Reportada', 'Mitigada') THEN 0 ELSE 1 END ASC,
                inc.id DESC
            LIMIT $limite
        ");

        if (!$qSug) {
            return [];
        }

        $sugerencias = [];
        while ($row = mysqli_fetch_assoc($qSug)) {
            $mismoJuego = (intval($row['juego_id']) === $juego_id);
            $mismoDriver = (intval($row['driver_id']) === $driver_id);
            if ($mismoJuego && $mismoDriver) {
                $row['motivo_sugerencia'] = 'Mismo Juego y Driver';
                $row['tipo_sugerido'] = 'duplicado';
            } elseif ($mismoDriver) {
                $row['motivo_sugerencia'] = 'Mismo Driver (' . $row['driver_nombre'] . ')';
                $row['tipo_sugerido'] = 'bloqueante';
            } else {
                $row['motivo_sugerencia'] = 'Mismo Juego (' . $row['juego_nombre'] . ')';
                $row['tipo_sugerido'] = 'relacionado';
            }
            $sugerencias[] = $row;
        }

        return $sugerencias;
    }
}

if (!function_exists('descartarFallo')) {
    function descartarFallo($conn, $incidencia_id, $motivo = '') {
        $incidencia_id = intval($incidencia_id);
        $q = mysqli_query($conn, "SELECT id, estado, ticket_id FROM incidencias WHERE id = $incidencia_id");
        $inc = $q ? mysqli_fetch_assoc($q) : null;
        if (!$inc) {
            throw new Exception("Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " no encontrada.");
        }

        if (esEstadoTerminalIncidencia($inc['estado'])) {
            throw new Exception("Los estados Corregida y Desestimada son terminales y no permiten transiciones posteriores.");
        }

        // Criterio 2: Si está vinculada con el tipo duplicado de [INC-Maestra], permitir pasar a Desestimada
        // incluso desde EnInvestigacion, registrando en el historial técnico: "Desestimada por duplicidad con INC-XXX"
        $maestraDup = obtenerIncidenciaMaestraDuplicada($conn, $incidencia_id);
        $estadosPermitidos = ($maestraDup !== null) ? ['Reportada', 'EnInvestigacion'] : ['Reportada'];

        if (!in_array($inc['estado'], $estadosPermitidos, true)) {
            throw new Exception("Transición inválida: Desde '" . $inc['estado'] . "' no se puede descartar fallo. Solo se permite desde 'Reportada' (o 'EnInvestigacion' cuando está vinculada como duplicada).");
        }

        $motivoTrim = trim((string)$motivo);
        if ($maestraDup !== null) {
            $maestraCod = 'INC-' . str_pad($maestraDup['id'], 3, '0', STR_PAD_LEFT);
            $leyendaDuplicidad = "Desestimada por duplicidad con {$maestraCod}";
            if ($motivoTrim === '' || stripos($motivoTrim, $leyendaDuplicidad) !== false) {
                $motivoFinal = ($motivoTrim === '') ? $leyendaDuplicidad : $motivoTrim;
            } else {
                $motivoFinal = $leyendaDuplicidad . " (" . $motivoTrim . ")";
            }
        } else {
            $motivoFinal = $motivoTrim;
        }

        $motivo_esc = mysqli_real_escape_string($conn, $motivoFinal);
        $update = "UPDATE incidencias SET estado = 'Desestimada', fecha_cierre = CURRENT_TIMESTAMP, motivo_descarte = '$motivo_esc' WHERE id = $incidencia_id";
        if (!mysqli_query($conn, $update)) {
            throw new Exception("Error al desestimar incidencia: " . mysqli_error($conn));
        }

        $ticket_id = intval($inc['ticket_id']);
        recalcularSlaTicket($conn, $ticket_id);

        if ($maestraDup !== null) {
            $msgHist = "Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " fue Desestimada mediante descartarFallo(): " . $motivoFinal;
        } else {
            $msgHist = "Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " fue Desestimada mediante descartarFallo() pasando a estado terminal." . (!empty($motivoFinal) ? " Motivo: \"$motivoFinal\"" : "");
        }
        registrarHistorialTicket($conn, $ticket_id, $msgHist);
        return true;
    }
}

if (!function_exists('asociarCorrelacion')) {
    function asociarCorrelacion($conn, $origen_id, $destino_id, $tipo_relacion = 'relacionado') {
        $origen_id = intval($origen_id);
        $destino_id = intval($destino_id);
        $tipo_relacion = trim((string)$tipo_relacion) !== '' ? trim((string)$tipo_relacion) : 'relacionado';

        if ($origen_id <= 0 || $destino_id <= 0) {
            throw new InvalidArgumentException("Identificadores de incidencias inválidos.");
        }

        if ($origen_id === $destino_id) {
            throw new Exception("No puedes correlacionar una incidencia consigo misma.");
        }

        $q = mysqli_query($conn, "SELECT id, estado, ticket_id FROM incidencias WHERE id = $origen_id");
        $inc = $q ? mysqli_fetch_assoc($q) : null;
        if (!$inc) {
            throw new Exception("Incidencia origen no encontrada.");
        }

        $qDest = mysqli_query($conn, "SELECT id, estado, ticket_id, dictamen_tecnico FROM incidencias WHERE id = $destino_id");
        $incDest = $qDest ? mysqli_fetch_assoc($qDest) : null;
        if (!$incDest) {
            throw new Exception("Incidencia destino no encontrada.");
        }

        if (esEstadoTerminalIncidencia($inc['estado'])) {
            throw new Exception("Los estados Corregida y Desestimada son terminales y no permiten transiciones posteriores.");
        }

        // HU-42 / HU-47: Si está en Reportada, transiciona automáticamente a EnInvestigacion al correlacionar
        if ($inc['estado'] === 'Reportada' || $inc['estado'] === 'abierto') {
            mysqli_query($conn, "UPDATE incidencias SET estado = 'EnInvestigacion' WHERE id = $origen_id");
            $inc['estado'] = 'EnInvestigacion';
        }

        if ($inc['estado'] !== 'EnInvestigacion') {
            throw new Exception("Transición inválida: La auto-transición asociarCorrelacion() solo se permite desde el estado 'EnInvestigacion' o 'Reportada'. Estado actual: '{$inc['estado']}'.");
        }

        // Criterio 3: Prevención de dependencias circulares (evaluar antes que el chequeo general de vínculo)
        if (existeDependenciaCircular($conn, $origen_id, $destino_id, $tipo_relacion)) {
            $origCod = 'INC-' . str_pad($origen_id, 3, '0', STR_PAD_LEFT);
            $destCod = 'INC-' . str_pad($destino_id, 3, '0', STR_PAD_LEFT);
            throw new Exception("Dependencia circular detectada: No se puede vincular {$origCod} como '{$tipo_relacion}' de {$destCod} porque generaría una relación cíclica inválida.");
        }

        // Verificar si ya existe relación entre ambas incidencias
        $check = mysqli_query($conn, "
            SELECT id FROM relaciones_incidencias 
            WHERE (incidencia_origen_id = $origen_id AND incidencia_destino_id = $destino_id)
               OR (incidencia_origen_id = $destino_id AND incidencia_destino_id = $origen_id)
        ");
        if ($check && mysqli_num_rows($check) > 0) {
            throw new Exception("Las incidencias ya se encuentran vinculadas.");
        }

        $tipo_esc = mysqli_real_escape_string($conn, $tipo_relacion);
        $insert = "INSERT INTO relaciones_incidencias (incidencia_origen_id, incidencia_destino_id, tipo_relacion) VALUES ($origen_id, $destino_id, '$tipo_esc')";
        if (!mysqli_query($conn, $insert)) {
            throw new Exception("Error al registrar correlación: " . mysqli_error($conn));
        }

        // Auto-transición: Se mantiene explícitamente en EnInvestigacion
        mysqli_query($conn, "UPDATE incidencias SET estado = 'EnInvestigacion' WHERE id = $origen_id");
        
        // Criterio 4: Trazabilidad en historial_tickets de ambos tickets asociados indicando el tipo de correlación establecida
        $ticket_origen_id = intval($inc['ticket_id']);
        $ticket_destino_id = intval($incDest['ticket_id']);
        $origCod = 'INC-' . str_pad($origen_id, 3, '0', STR_PAD_LEFT);
        $destCod = 'INC-' . str_pad($destino_id, 3, '0', STR_PAD_LEFT);

        registrarHistorialTicket(
            $conn,
            $ticket_origen_id,
            "Auto-transición asociarCorrelacion(): Incidencia {$origCod} vinculada con {$destCod} con tipo de correlación '{$tipo_relacion}' manteniendo el caso abierto en 'EnInvestigacion'."
        );

        if ($ticket_destino_id > 0 && $ticket_destino_id !== $ticket_origen_id) {
            registrarHistorialTicket(
                $conn,
                $ticket_destino_id,
                "Vinculación de incidencia: {$destCod} fue vinculada con {$origCod} bajo el tipo de correlación '{$tipo_relacion}'."
            );
        }

        return true;
    }
}

if (!function_exists('desvincularCorrelacion')) {
    /**
     * Criterio 4: Elimina una vinculación entre incidencias y deja registro en historial_tickets
     * de ambos tickets asociados indicando el tipo de correlación que tenían establecida.
     */
    function desvincularCorrelacion($conn, int $relacion_id): bool {
        $relacion_id = intval($relacion_id);
        if (!$conn || $relacion_id <= 0) {
            return false;
        }

        $qRel = mysqli_query($conn, "SELECT id, incidencia_origen_id, incidencia_destino_id, tipo_relacion FROM relaciones_incidencias WHERE id = $relacion_id");
        $rel = $qRel ? mysqli_fetch_assoc($qRel) : null;
        if (!$rel) {
            return false;
        }

        $orig = intval($rel['incidencia_origen_id']);
        $dest = intval($rel['incidencia_destino_id']);
        $tipo = $rel['tipo_relacion'] ?? 'relacionado';

        $qT1 = mysqli_query($conn, "SELECT ticket_id FROM incidencias WHERE id = $orig");
        $qT2 = mysqli_query($conn, "SELECT ticket_id FROM incidencias WHERE id = $dest");
        $rT1 = $qT1 ? mysqli_fetch_assoc($qT1) : null;
        $rT2 = $qT2 ? mysqli_fetch_assoc($qT2) : null;
        $t1 = $rT1 ? intval($rT1['ticket_id'] ?? 0) : 0;
        $t2 = $rT2 ? intval($rT2['ticket_id'] ?? 0) : 0;

        if (!mysqli_query($conn, "DELETE FROM relaciones_incidencias WHERE id = $relacion_id")) {
            return false;
        }

        $origCod = 'INC-' . str_pad($orig, 3, '0', STR_PAD_LEFT);
        $destCod = 'INC-' . str_pad($dest, 3, '0', STR_PAD_LEFT);

        if ($t1 > 0) {
            registrarHistorialTicket($conn, $t1, "Desvinculada la relación de tipo '{$tipo}' entre {$origCod} y {$destCod}.");
        }
        if ($t2 > 0 && $t2 !== $t1) {
            registrarHistorialTicket($conn, $t2, "Desvinculada la relación de tipo '{$tipo}' entre {$destCod} y {$origCod}.");
        }

        return true;
    }
}

if (!function_exists('procesarEfectosCascadaCorreccion')) {
    /**
     * Criterio 1.3 y Criterio 2.2:
     * - Al pasar una incidencia bloqueante a Corregida, desbloquea automáticamente las dependientes y lo audita.
     * - Al corregirse una incidencia maestra, su dictamen de solución (registrarResolucion()) se replica
     *   automáticamente en las incidencias duplicadas vinculadas y cierra en cascada las activas.
     */
    function procesarEfectosCascadaCorreccion($conn, int $incidencia_maestra_id, string $dictamen_tecnico): void {
        $incidencia_maestra_id = intval($incidencia_maestra_id);
        $dictamen_trim = trim($dictamen_tecnico);
        if (!$conn || $incidencia_maestra_id <= 0) {
            return;
        }

        $maestraCod = 'INC-' . str_pad($incidencia_maestra_id, 3, '0', STR_PAD_LEFT);

        // 1. Criterio 1.3: Auditar desbloqueo automático de incidencias dependientes (tipo 'bloqueante')
        $qDep = mysqli_query($conn, "
            SELECT i.id, i.ticket_id, i.estado
            FROM relaciones_incidencias r
            JOIN incidencias i ON r.incidencia_origen_id = i.id
            WHERE r.incidencia_destino_id = $incidencia_maestra_id
              AND r.tipo_relacion = 'bloqueante'
        ");
        if ($qDep) {
            while ($dep = mysqli_fetch_assoc($qDep)) {
                $depId = intval($dep['id']);
                $depTicketId = intval($dep['ticket_id']);
                $bloqueantesRestantes = obtenerBloqueantesActivosIncidencia($conn, $depId);
                if (empty($bloqueantesRestantes) && $depTicketId > 0) {
                    $depCod = 'INC-' . str_pad($depId, 3, '0', STR_PAD_LEFT);
                    registrarHistorialTicket(
                        $conn,
                        $depTicketId,
                        "🔓 Semáforo de Bloqueo: Incidencia {$depCod} desbloqueada automáticamente al pasar la incidencia bloqueante {$maestraCod} a estado 'Corregida'."
                    );
                }
            }
        }

        // 2. Criterio 2.2: Replicar dictamen y cerrar en cascada incidencias vinculadas como 'duplicado' de esta maestra
        if ($dictamen_trim !== '') {
            $qDup = mysqli_query($conn, "
                SELECT i.id, i.ticket_id, i.estado, i.fecha_creacion, i.fecha_limite_correccion, i.tiempo_efectivo_minutos, i.cumplio_sla_desarrollo
                FROM relaciones_incidencias r
                JOIN incidencias i ON r.incidencia_origen_id = i.id
                WHERE r.incidencia_destino_id = $incidencia_maestra_id
                  AND r.tipo_relacion = 'duplicado'
                  AND i.estado != 'Desestimada'
            ");
            if ($qDup) {
                while ($dup = mysqli_fetch_assoc($qDup)) {
                    $dupId = intval($dup['id']);
                    $dupTicketId = intval($dup['ticket_id']);
                    $dupCod = 'INC-' . str_pad($dupId, 3, '0', STR_PAD_LEFT);

                    // Replicar dictamen mediante registrarResolucion() sin recursión
                    registrarResolucion($conn, $dupId, $dictamen_trim, false);

                    if ($dup['estado'] !== 'Corregida') {
                        $ahora = time();
                        $creacion = !empty($dup['fecha_creacion']) ? strtotime($dup['fecha_creacion']) : $ahora;
                        $tiempoEfectivoMin = !empty($dup['tiempo_efectivo_minutos'])
                            ? intval($dup['tiempo_efectivo_minutos'])
                            : max(1, round(($ahora - $creacion) / 60));
                        $limiteCorreccion = !empty($dup['fecha_limite_correccion'])
                            ? strtotime($dup['fecha_limite_correccion'])
                            : ($creacion + 86400 * 2);
                        $cumplioSla = ($dup['cumplio_sla_desarrollo'] !== null)
                            ? intval($dup['cumplio_sla_desarrollo'])
                            : (($ahora <= $limiteCorreccion) ? 1 : 0);

                        mysqli_query($conn, "
                            UPDATE incidencias
                            SET estado = 'Corregida',
                                fecha_cierre = CURRENT_TIMESTAMP,
                                tiempo_efectivo_minutos = $tiempoEfectivoMin,
                                cumplio_sla_desarrollo = $cumplioSla
                            WHERE id = $dupId
                        ");
                    }

                    if ($dupTicketId > 0) {
                        anexarDictamenATicket($conn, $dupTicketId, $dupId, $dictamen_trim);
                        recalcularSlaTicket($conn, $dupTicketId);
                        registrarHistorialTicket(
                            $conn,
                            $dupTicketId,
                            "🔄 Cierre en cascada por duplicidad: Se replicó automáticamente el dictamen de solución (registrarResolucion) de la incidencia maestra {$maestraCod} en la duplicada {$dupCod}: \"{$dictamen_trim}\"."
                        );
                    }
                }
            }
        }
    }
}

if (!function_exists('aplicarSolucionTemporal')) {
    /**
     * Criterio 3 HU-48: Si la incidencia pasa a Mitigada, el temporizador se detiene congelando
     * el tiempo efectivo para la tasa de cumplimiento del equipo de desarrollo (meta global >= 85.0%).
     */
    function aplicarSolucionTemporal($conn, $incidencia_id, $solucion_temporal = '') {
        $incidencia_id = intval($incidencia_id);
        $q = mysqli_query($conn, "SELECT id, estado, ticket_id, fecha_creacion, fecha_limite_mitigacion, fecha_limite_correccion, plazo_mitigacion_horas, severidad FROM incidencias WHERE id = $incidencia_id");
        $inc = $q ? mysqli_fetch_assoc($q) : null;
        if (!$inc) {
            throw new Exception("Incidencia no encontrada.");
        }

        if (esEstadoTerminalIncidencia($inc['estado'])) {
            throw new Exception("Los estados Corregida y Desestimada son terminales y no permiten transiciones posteriores.");
        }

        if ($inc['estado'] !== 'EnInvestigacion') {
            throw new Exception("Transición inválida: Solo se puede aplicar solución temporal desde el estado 'EnInvestigacion'. Estado actual: '{$inc['estado']}'.");
        }

        // Criterio 1: Semáforo de Bloqueo (bloquear transición a Mitigada si depende de bloqueante activa)
        verificarBloqueoIncidencia($conn, $incidencia_id);

        // HU-48: Congelar tiempo efectivo para la tasa de cumplimiento del equipo de desarrollo
        $ahora = time();
        $creacion = strtotime($inc['fecha_creacion']);
        $tiempoEfectivoMin = max(1, round(($ahora - $creacion) / 60));
        
        // Evaluar cumplimiento SLA de desarrollo
        $limiteMitigacion = !empty($inc['fecha_limite_mitigacion']) 
            ? strtotime($inc['fecha_limite_mitigacion']) 
            : (!empty($inc['fecha_limite_correccion']) ? strtotime($inc['fecha_limite_correccion']) : ($creacion + 86400));
        $cumplioSla = ($ahora <= $limiteMitigacion) ? 1 : 0;

        $sol_esc = mysqli_real_escape_string($conn, trim($solucion_temporal));
        $update = "UPDATE incidencias 
                   SET estado = 'Mitigada', 
                       solucion_temporal = '$sol_esc',
                       fecha_mitigacion = CURRENT_TIMESTAMP,
                       tiempo_efectivo_minutos = $tiempoEfectivoMin,
                       cumplio_sla_desarrollo = $cumplioSla
                   WHERE id = $incidencia_id";
        if (!mysqli_query($conn, $update)) {
            throw new Exception("Error al actualizar a Mitigada: " . mysqli_error($conn));
        }

        $ticket_id = intval($inc['ticket_id']);
        $slaStatusText = ($cumplioSla === 1) ? "dentro de plazo (SLA Cumplido ✓)" : "fuera de plazo (SLA Vencido ✗)";
        $msgHist = "Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " pasó a 'Mitigada' tras aplicarSolucionTemporal(). Tiempo efectivo congelado: " . formatearMinutos($tiempoEfectivoMin) . " ($slaStatusText)." . (!empty($solucion_temporal) ? " Solución temporal: \"$solucion_temporal\"" : "");
        registrarHistorialTicket($conn, $ticket_id, $msgHist);
        return true;
    }
}

if (!function_exists('registrarResolucion')) {
    function registrarResolucion($conn, $incidencia_id, $dictamen_tecnico, $replicarEnDuplicadas = true) {
        $incidencia_id = intval($incidencia_id);
        $dictamen_trim = trim($dictamen_tecnico);

        if (empty($dictamen_trim)) {
            throw new InvalidArgumentException("Es obligatorio registrar el dictamen técnico mediante registrarResolucion().");
        }

        $dictamen_esc = mysqli_real_escape_string($conn, $dictamen_trim);
        $update = "UPDATE incidencias SET dictamen_tecnico = '$dictamen_esc' WHERE id = $incidencia_id";
        if (!mysqli_query($conn, $update)) {
            throw new Exception("Error al registrar dictamen técnico: " . mysqli_error($conn));
        }

        // Si la incidencia ya se encuentra Corregida, sincronizar en respuesta_resolucion del ticket padre y duplicadas
        $qInc = mysqli_query($conn, "SELECT ticket_id, estado FROM incidencias WHERE id = $incidencia_id");
        if ($qInc && ($rInc = mysqli_fetch_assoc($qInc))) {
            if (strtolower(trim($rInc['estado'] ?? '')) === 'corregida') {
                anexarDictamenATicket($conn, intval($rInc['ticket_id']), $incidencia_id, $dictamen_trim);
                if ($replicarEnDuplicadas) {
                    procesarEfectosCascadaCorreccion($conn, $incidencia_id, $dictamen_trim);
                }
            }
        }
        return true;
    }
}

if (!function_exists('desplegarParche')) {
    /**
     * Criterio 3 HU-48: Si la incidencia pasa a Corregida, el temporizador se detiene congelando
     * el tiempo efectivo para la tasa de cumplimiento del equipo de desarrollo.
     * Criterio 4: Al corregirse cada incidencia (Corregida), su dictamen técnico se anexa de forma
     * acumulativa y legible en el campo respuesta_resolucion del ticket principal sin pisar soluciones previas.
     */
    function desplegarParche($conn, $incidencia_id, $dictamen_tecnico) {
        $incidencia_id = intval($incidencia_id);
        $q = mysqli_query($conn, "SELECT id, estado, ticket_id, fecha_creacion, fecha_mitigacion, fecha_limite_correccion, tiempo_efectivo_minutos, cumplio_sla_desarrollo FROM incidencias WHERE id = $incidencia_id");
        $inc = $q ? mysqli_fetch_assoc($q) : null;
        if (!$inc) {
            throw new Exception("Incidencia no encontrada.");
        }

        if (esEstadoTerminalIncidencia($inc['estado'])) {
            throw new Exception("Los estados Corregida y Desestimada son terminales y no permiten transiciones posteriores.");
        }

        if ($inc['estado'] !== 'EnInvestigacion') {
            throw new Exception("Transición inválida: Solo se puede desplegar parche desde el estado 'EnInvestigacion'. Estado actual: '{$inc['estado']}'.");
        }

        // Criterio 1: Semáforo de Bloqueo (bloquear transición a Corregida si depende de bloqueante activa)
        verificarBloqueoIncidencia($conn, $incidencia_id);

        $dictamen_trim = trim($dictamen_tecnico);
        if (empty($dictamen_trim)) {
            throw new InvalidArgumentException("Debe registrar obligatoriamente el dictamen técnico en registrarResolucion() para desplegar el parche.");
        }

        // Exigiendo registrar de forma obligatoria el dictamen técnico en registrarResolucion()
        registrarResolucion($conn, $incidencia_id, $dictamen_trim, false);

        // HU-48: Congelar tiempo efectivo si no se había mitigado previamente
        $ahora = time();
        $creacion = strtotime($inc['fecha_creacion']);
        $tiempoEfectivoMin = !empty($inc['tiempo_efectivo_minutos']) ? intval($inc['tiempo_efectivo_minutos']) : max(1, round(($ahora - $creacion) / 60));
        
        $limiteCorreccion = !empty($inc['fecha_limite_correccion']) ? strtotime($inc['fecha_limite_correccion']) : ($creacion + 86400 * 2);
        $cumplioSla = ($inc['cumplio_sla_desarrollo'] !== null) ? intval($inc['cumplio_sla_desarrollo']) : (($ahora <= $limiteCorreccion) ? 1 : 0);

        // Pasa a Corregida (estado terminal)
        $update = "UPDATE incidencias 
                   SET estado = 'Corregida', 
                       fecha_cierre = CURRENT_TIMESTAMP,
                       tiempo_efectivo_minutos = $tiempoEfectivoMin,
                       cumplio_sla_desarrollo = $cumplioSla
                   WHERE id = $incidencia_id";
        if (!mysqli_query($conn, $update)) {
            throw new Exception("Error al actualizar a Corregida: " . mysqli_error($conn));
        }

        $ticket_id = intval($inc['ticket_id']);

        // Anexar de forma acumulativa y legible el dictamen técnico en respuesta_resolucion del ticket principal
        anexarDictamenATicket($conn, $ticket_id, $incidencia_id, $dictamen_trim);

        // Recalcular el SLA del ticket padre tras corregirse la incidencia
        recalcularSlaTicket($conn, $ticket_id);

        $slaStatusText = ($cumplioSla === 1) ? "dentro de plazo (SLA Cumplido ✓)" : "fuera de plazo (SLA Vencido ✗)";
        registrarHistorialTicket($conn, $ticket_id, "Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " pasó a 'Corregida' (estado terminal) tras desplegarParche(). Tiempo efectivo: " . formatearMinutos($tiempoEfectivoMin) . " ($slaStatusText). Dictamen técnico registrado y anexado al ticket: \"$dictamen_trim\".");

        // Criterio 1.3 y 2.2: Desbloqueo automático de dependientes y replicación en cascada en duplicadas
        procesarEfectosCascadaCorreccion($conn, $incidencia_id, $dictamen_trim);

        return true;
    }
}

if (!function_exists('solucionDefinitivaConfirmada')) {
    function solucionDefinitivaConfirmada($conn, $incidencia_id, $dictamen_tecnico = '') {
        $incidencia_id = intval($incidencia_id);
        $q = mysqli_query($conn, "SELECT id, estado, ticket_id, dictamen_tecnico, solucion_temporal, tiempo_efectivo_minutos, cumplio_sla_desarrollo FROM incidencias WHERE id = $incidencia_id");
        $inc = $q ? mysqli_fetch_assoc($q) : null;
        if (!$inc) {
            throw new Exception("Incidencia no encontrada.");
        }

        if (esEstadoTerminalIncidencia($inc['estado'])) {
            throw new Exception("Los estados Corregida y Desestimada son terminales y no permiten transiciones posteriores.");
        }

        if ($inc['estado'] !== 'Mitigada') {
            throw new Exception("Transición inválida: Desde '{$inc['estado']}' no se puede confirmar solución definitiva. Solo se permite desde 'Mitigada'.");
        }

        // Criterio 1: Semáforo de Bloqueo (bloquear transición a Corregida si depende de bloqueante activa)
        verificarBloqueoIncidencia($conn, $incidencia_id);

        $dictamen_trim = trim($dictamen_tecnico);
        if (!empty($dictamen_trim)) {
            registrarResolucion($conn, $incidencia_id, $dictamen_trim, false);
            $dictamenFinal = $dictamen_trim;
        } elseif (!empty(trim((string)($inc['dictamen_tecnico'] ?? '')))) {
            $dictamenFinal = trim($inc['dictamen_tecnico']);
        } elseif (!empty(trim((string)($inc['solucion_temporal'] ?? '')))) {
            $dictamenFinal = "Solución definitiva confirmada (Mitigación previa: " . trim($inc['solucion_temporal']) . ")";
            registrarResolucion($conn, $incidencia_id, $dictamenFinal, false);
        } else {
            $dictamenFinal = "Solución definitiva confirmada.";
            registrarResolucion($conn, $incidencia_id, $dictamenFinal, false);
        }

        // Pasa a Corregida (estado terminal)
        $update = "UPDATE incidencias SET estado = 'Corregida', fecha_cierre = CURRENT_TIMESTAMP WHERE id = $incidencia_id";
        if (!mysqli_query($conn, $update)) {
            throw new Exception("Error al actualizar a Corregida: " . mysqli_error($conn));
        }

        $ticket_id = intval($inc['ticket_id']);

        // Anexar de forma acumulativa y legible el dictamen técnico en respuesta_resolucion del ticket principal
        anexarDictamenATicket($conn, $ticket_id, $incidencia_id, $dictamenFinal);

        // Recalcular el SLA del ticket padre tras corregirse la incidencia
        recalcularSlaTicket($conn, $ticket_id);

        $msgHist = "Incidencia INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " pasó a 'Corregida' (estado terminal) tras solucionDefinitivaConfirmada(). Dictamen anexado al ticket: \"$dictamenFinal\"";
        registrarHistorialTicket($conn, $ticket_id, $msgHist);

        // Criterio 1.3 y 2.2: Desbloqueo automático de dependientes y replicación en cascada en duplicadas
        procesarEfectosCascadaCorreccion($conn, $incidencia_id, $dictamenFinal);

        return true;
    }
}

function crearNotificacion($conn, $equipo, $titulo, $descripcion, $enlace) {
    $equipoEscaped = mysqli_real_escape_string($conn, $equipo);
    $tituloEscaped = mysqli_real_escape_string($conn, $titulo);
    $descripcionEscaped = mysqli_real_escape_string($conn, $descripcion);
    $enlaceEscaped = mysqli_real_escape_string($conn, $enlace);

    // Obtener los usuarios que pertenecen a ese equipo/rol
    $queryUsers = "SELECT id FROM usuarios WHERE IdRol = (SELECT id FROM roles WHERE nombre = '$equipoEscaped')";
    $resUsers = mysqli_query($conn, $queryUsers);
    if ($resUsers) {
        while ($row = mysqli_fetch_assoc($resUsers)) {
            $userId = intval($row['id']);
            $insertQuery = "INSERT INTO notificaciones (usuario_id, equipo, titulo, descripcion, enlace, leido) 
                            VALUES ($userId, '$equipoEscaped', '$tituloEscaped', '$descripcionEscaped', '$enlaceEscaped', 0)";
            mysqli_query($conn, $insertQuery);
        }
    }
}

?>