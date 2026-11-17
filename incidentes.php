<?php 
ob_start();
session_start();
require_once("funcs/session_security.php");

if (empty($_SESSION["currentTeam"]) || ($_SESSION["currentTeam"] !== "desarrollo" && $_SESSION["currentTeam"] !== "administrador" && $_SESSION["currentTeam"] !== "monitoreo")) {
    http_response_code(403);
    die("Acceso denegado: No tiene permisos para acceder a este módulo.");
}

require_once("db.php");
require_once("funcs/generalFuncs.php");
// HU-48: asegurar columnas de plazos y disparar alertas de incidencias vencidas sin mitigar
asegurarTablasEstructura($conn);
verificarVencimientosIncidencias($conn);
$is_monitoreo = (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "monitoreo");

if (!function_exists('redirigirIncidente')) {
    function redirigirIncidente($url = "incidentes.php") {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit;
        } else {
            echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
            exit;
        }
    }
}

// 2. Procesamiento de acciones POST (crear, editar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "monitoreo") {
        $_SESSION["msg"] = '<div class="toast-error">❌ Error: El equipo de Monitoreo no tiene permisos para realizar esta acción.</div>';
        redirigirIncidente("incidentes.php");
    }
    if (isset($_POST["crear_incidencia"])) {
        $ticket_id = intval($_POST["ticket_id"]);
        $juego_post = $_POST["juego_id"] ?? 0;
        $driver_post = $_POST["driver_id"] ?? 0;
        $detalle = trim($_POST["detalle"] ?? '');
        
        if ($ticket_id > 0 && !empty($detalle)) {
            try {
                // HU-42 / HU-43: registrarIncidencia() inicia en 'Reportada', recalcula el SLA del ticket padre
                // y registra el log de prórroga en historial_tickets si acumula más de una incidencia activa.
                $inc_id = registrarIncidencia($conn, $ticket_id, $juego_post, $driver_post, $detalle);
                
                // Disparador de notificación Desarrollo
                $q_t = mysqli_query($conn, "SELECT titulo FROM tickets WHERE id = $ticket_id");
                $t_row = mysqli_fetch_assoc($q_t);
                $ticket_titulo = $t_row['titulo'] ?? 'Sin título';
                crearNotificacion($conn, 'desarrollo', 'Nueva Incidencia', $ticket_titulo, "incidentes.php?ver=$inc_id");

                $_SESSION["msg"] = '<div class="toast-success">✅ ¡Incidencia registrada exitosamente!</div>';
                unset($_SESSION["persisted_form"]);
                unset($_SESSION["persisted_form_type"]);
            } catch (Exception $e) {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al registrar incidencia: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $_SESSION["persisted_form"] = $_POST;
                $_SESSION["persisted_form_type"] = "crear_incidencia";
            }
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: El ticket y el detalle técnico son obligatorios.</div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form_type"] = "crear_incidencia";
        }
        redirigirIncidente("incidentes.php");
    }
    
    if (isset($_POST["editar_incidencia"])) {
        $incidencia_id = intval($_POST["editar_incidencia"]);
        $ticket_id = intval($_POST["ticket_id"]);
        $juego_id = (!empty($_POST["juego_id"]) && intval($_POST["juego_id"]) > 0) ? intval($_POST["juego_id"]) : "NULL";
        $driver_id = (!empty($_POST["driver_id"]) && intval($_POST["driver_id"]) > 0) ? intval($_POST["driver_id"]) : "NULL";
        $detalle = mysqli_real_escape_string($conn, trim($_POST["detalle"]));
        
        if ($incidencia_id > 0 && $ticket_id > 0 && !empty($detalle)) {
            $updateQuery = "UPDATE incidencias SET ticket_id = $ticket_id, juego_id = $juego_id, driver_id = $driver_id, detalle = '$detalle' WHERE id = $incidencia_id";
            if (mysqli_query($conn, $updateQuery)) {
                registrarHistorialTicket($conn, $ticket_id, "Incidencia técnica INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " modificada");
                $_SESSION["msg"] = '<div class="toast-success">✅ ¡Incidencia actualizada exitosamente!</div>';
                unset($_SESSION["persisted_form"]);
                unset($_SESSION["persisted_form_type"]);
            } else {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al actualizar incidencia: ' . mysqli_escape_string($conn, mysqli_error($conn)) . '</div>';
                $_SESSION["persisted_form"] = $_POST;
                $_SESSION["persisted_form"]["id"] = $incidencia_id;
                $_SESSION["persisted_form_type"] = "editar_incidencia";
            }
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: Todos los campos son obligatorios.</div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form"]["id"] = $incidencia_id;
            $_SESSION["persisted_form_type"] = "editar_incidencia";
        }
        redirigirIncidente("incidentes.php");
    }

    if (isset($_POST["vincular_incidencia"])) {
        $origen_id = intval($_POST["origen_id"]);
        $destino_id = intval($_POST["destino_id"]);
        $tipo = trim($_POST["tipo_relacion"] ?? 'relacionado');
        
        if ($origen_id > 0 && $destino_id > 0 && !empty($tipo)) {
            try {
                // HU-47: Prevención de dependencias circulares y trazabilidad en historial_tickets de ambos tickets
                asociarCorrelacion($conn, $origen_id, $destino_id, $tipo);
                $_SESSION["msg"] = '<div class="toast-success">✅ ¡Incidencias vinculadas exitosamente!</div>';
            } catch (Exception $e) {
                $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: Todos los campos son obligatorios para la vinculación.</div>';
        }
        redirigirIncidente("incidentes.php?ver=" . $origen_id);
    }
    
    if (isset($_POST["eliminar_relacion"])) {
        $relacion_id = intval($_POST["eliminar_relacion_id"]);
        $origen_id = intval($_POST["origen_id"]);
        if ($relacion_id > 0) {
            // HU-47: Desvinculación con registro de trazabilidad en historial_tickets de ambos tickets
            if (desvincularCorrelacion($conn, $relacion_id)) {
                $_SESSION["msg"] = '<div class="toast-success">✅ ¡Vínculo eliminado exitosamente!</div>';
            } else {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al eliminar el vínculo.</div>';
            }
        }
        redirigirIncidente("incidentes.php?ver=" . $origen_id);
    }

    if (isset($_POST["eliminar_incidencia"])) {
        $incidencia_id = intval($_POST["eliminar_incidencia"]);
        if ($incidencia_id > 0) {
            $q_tkt = mysqli_query($conn, "SELECT ticket_id FROM incidencias WHERE id = $incidencia_id");
            if ($q_tkt && $row_tkt = mysqli_fetch_assoc($q_tkt)) {
                $ticket_id = $row_tkt['ticket_id'];
                $deleteQuery = "DELETE FROM incidencias WHERE id = $incidencia_id";
                if (mysqli_query($conn, $deleteQuery)) {
                    registrarHistorialTicket($conn, $ticket_id, "Incidencia técnica INC-" . str_pad($incidencia_id, 3, '0', STR_PAD_LEFT) . " eliminada");
                    $_SESSION["msg"] = '<div class="toast-success">✅ ¡Incidencia eliminada exitosamente!</div>';
                } else {
                    $_SESSION["msg"] = '<div class="toast-error">❌ Error al eliminar la incidencia.</div>';
                }
            }
        }
        redirigirIncidente("incidentes.php");
    }
    
    // HU-42: Handler de transición de estado de incidencia con máquina de estados estricta
    if (isset($_POST["actualizar_estado_ticket"])) {
        $ticket_id    = intval($_POST["ticket_id"]);
        $incidencia_id = intval($_POST["incidencia_id"] ?? 0);
        $accion       = trim($_POST["accion_incidencia"] ?? '');
        $dictamen     = trim($_POST["dictamen_tecnico"] ?? $_POST["respuesta_resolucion"] ?? '');

        if ($incidencia_id > 0) {
            try {
                require_once("funcs/generalFuncs.php");

                // Despachar a la función HU-42 correspondiente según la acción
                switch ($accion) {
                    case 'analizarLogs':
                        // Reportada -> EnInvestigacion
                        analizarLogs($conn, $incidencia_id);
                        $_SESSION["msg"] = '<div class="toast-success">✅ Incidencia enviada a En Investigación.</div>';
                        break;

                    case 'descartarFallo':
                        // Reportada -> Desestimada
                        $motivo = trim($_POST["motivo_descarte"] ?? $dictamen);
                        descartarFallo($conn, $incidencia_id, $motivo);
                        $_SESSION["msg"] = '<div class="toast-success">✅ Incidencia desestimada.</div>';
                        break;

                    case 'aplicarSolucionTemporal':
                        // EnInvestigacion -> Mitigada
                        $sol_temp = trim($_POST["solucion_temporal"] ?? $dictamen);
                        aplicarSolucionTemporal($conn, $incidencia_id, $sol_temp);
                        $_SESSION["msg"] = '<div class="toast-success">✅ Solución temporal aplicada. Incidencia en Mitigada.</div>';
                        break;

                    case 'desplegarParche':
                        // EnInvestigacion -> Corregida
                        if (empty($dictamen)) {
                            throw new Exception("El dictamen técnico es obligatorio para desplegar un parche.");
                        }
                        desplegarParche($conn, $incidencia_id, $dictamen);
                        $_SESSION["msg"] = '<div class="toast-success">✅ Parche desplegado. Incidencia Corregida.</div>';
                        break;

                    case 'solucionDefinitivaConfirmada':
                        // Mitigada -> Corregida
                        solucionDefinitivaConfirmada($conn, $incidencia_id, $dictamen);
                        $_SESSION["msg"] = '<div class="toast-success">✅ Solución definitiva confirmada. Incidencia Corregida.</div>';
                        break;

                    case 'asociarCorrelacion':
                        // EnInvestigacion -> auto-transición (caso abierto agrupado)
                        $destino_id = intval($_POST["correlacion_destino_id"] ?? 0);
                        if ($destino_id > 0 && $destino_id !== $incidencia_id) {
                            asociarCorrelacion($conn, $incidencia_id, $destino_id);
                            $_SESSION["msg"] = '<div class="toast-success">✅ Correlación asociada. Caso agrupado.</div>';
                        } else {
                            throw new Exception("Debe seleccionar una incidencia destino válida para asociar la correlación.");
                        }
                        break;

                    default:
                        throw new Exception("Acción de transición desconocida: '$accion'.");
                }
            } catch (Exception $e) {
                $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ ID de incidencia inválido.</div>';
        }
        redirigirIncidente("incidentes.php" . ($incidencia_id > 0 ? "?ver=$incidencia_id" : ""));
    }
}

// 3. Sincronización automática garantizada e inyección en grilla
@mysqli_query($conn, "
    INSERT INTO incidencias (ticket_id, juego_id, driver_id, detalle, estado)
    SELECT t.id, t.juego_id, t.driver_id, t.descripcion, 'Reportada'
    FROM tickets t
    LEFT JOIN incidencias inc ON t.id = inc.ticket_id
    WHERE t.es_incidencia = 1 AND inc.id IS NULL AND t.estado != 'inactivo'
");

// 3.1 Consulta de incidencias con filtros de búsqueda y estado del ticket padre
$buscar = "";
$filtro = "todos";

$sql = "SELECT 
            inc.id AS incidencia_id,
            inc.ticket_id,
            inc.juego_id,
            inc.driver_id,
            inc.detalle,
            inc.fecha_creacion,
            inc.fecha_cierre AS incidencia_fecha_cierre,
            inc.estado AS incidencia_estado,
            inc.severidad,
            inc.plazo_mitigacion_horas,
            inc.plazo_correccion_horas,
            inc.fecha_limite_mitigacion,
            inc.fecha_limite_correccion,
            inc.fecha_mitigacion,
            inc.tiempo_efectivo_minutos,
            inc.cumplio_sla_desarrollo,
            t.titulo AS ticket_titulo,
            t.estado AS ticket_estado,
            t.prioridad AS ticket_prioridad,
            t.usuario AS usuario_creador,
            t.asignado AS usuario_asignado,
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
    
    // Búsqueda por formato INC-XXX
    if (preg_match('/^INC-(\d+)$/i', trim($buscar), $matches)) {
        $idBuscar = intval($matches[1]);
        $sql .= " AND inc.id = $idBuscar";
    } else {
        $sql .= " AND (t.titulo LIKE '%$buscarEscaped%' OR inc.detalle LIKE '%$buscarEscaped%' OR j.nombre LIKE '%$buscarEscaped%' OR d.nombre LIKE '%$buscarEscaped%')";
    }
}

if (isset($_GET["filtro"]) && in_array($_GET["filtro"], ["resueltos", "no_resueltos"])) {
    $filtro = $_GET["filtro"];
    if ($filtro === "resueltos") {
        // HU-42: estados terminales son Corregida y Desestimada
        $sql .= " AND inc.estado IN ('Corregida', 'Desestimada')";
    } else if ($filtro === "no_resueltos") {
        $sql .= " AND inc.estado IN ('Reportada', 'EnInvestigacion', 'Mitigada')";
    }
}

$prioridad = "todos";
if (isset($_GET["prioridad"]) && in_array($_GET["prioridad"], ["critica", "alta", "media", "baja"])) {
    $prioridad = $_GET["prioridad"];
    $sql .= " AND t.prioridad = '$prioridad'";
}

$sql .= " ORDER BY inc.id DESC";
$query = mysqli_query($conn, $sql);
$incidencias = $query ? mysqli_fetch_all($query, MYSQLI_ASSOC) : [];

// HU-48: métricas de cumplimiento del equipo de desarrollo (meta global >= 85%)
$metricasDev = obtenerMetricasCumplimientoDesarrollo($conn);

// 4. Cargar incidencia para el visualizador (Ver Detalles)
$showIncidencia = null;
if (isset($_GET["ver"]) && !empty($_GET["ver"])) {
    $idVer = intval($_GET["ver"]);
    $queryVer = mysqli_query($conn, "
        SELECT 
            inc.id AS incidencia_id,
            inc.ticket_id,
            inc.juego_id,
            inc.driver_id,
            inc.detalle,
            inc.fecha_creacion,
            inc.fecha_cierre AS incidencia_fecha_cierre,
            inc.estado AS incidencia_estado,
            inc.severidad,
            inc.plazo_mitigacion_horas,
            inc.plazo_correccion_horas,
            inc.fecha_limite_mitigacion,
            inc.fecha_limite_correccion,
            inc.fecha_mitigacion,
            inc.tiempo_efectivo_minutos,
            inc.cumplio_sla_desarrollo,
            t.titulo AS ticket_titulo,
            t.descripcion AS ticket_descripcion,
            t.categoria AS ticket_categoria,
            t.respuesta_resolucion AS ticket_respuesta,
            t.estado AS ticket_estado,
            t.prioridad AS ticket_prioridad,
            t.usuario AS usuario_creador,
            t.asignado AS usuario_asignado,
            j.nombre AS juego_nombre,
            d.nombre AS driver_nombre
        FROM incidencias inc
        JOIN tickets t ON inc.ticket_id = t.id
        LEFT JOIN juegos j ON inc.juego_id = j.id
        LEFT JOIN drivers d ON inc.driver_id = d.id
        WHERE inc.id = $idVer
    ");
    if ($queryVer && mysqli_num_rows($queryVer) > 0) {
        $showIncidencia = mysqli_fetch_assoc($queryVer);
    }
}

// 5. Cargar incidencia para la edición
$editIncidencia = null;
if (isset($_GET["editar"]) && !empty($_GET["editar"])) {
    $idEdit = intval($_GET["editar"]);
    $queryEdit = mysqli_query($conn, "
        SELECT id, ticket_id, juego_id, driver_id, detalle
        FROM incidencias
        WHERE id = $idEdit
    ");
    if ($queryEdit && mysqli_num_rows($queryEdit) > 0) {
        $editIncidencia = mysqli_fetch_assoc($queryEdit);
    }
}

function getPrioridadBadgeClass($prioridad) {
    switch (strtolower(trim($prioridad))) {
        case 'critica':
            return 'badge-critica';
        case 'alta':
            return 'badge-alta';
        case 'media':
            return 'badge-media';
        case 'baja':
            return 'badge-baja';
        default:
            return '';
    }
}

// Funciones para Render de Modales y Utilidades
function renderIncidenciaModal($conn, $values = null) {
    $tickets_padre_query = mysqli_query($conn, "SELECT id, titulo FROM tickets WHERE es_incidencia = 1 AND estado != 'inactivo' ORDER BY id DESC");
    $tickets_padre = $tickets_padre_query ? mysqli_fetch_all($tickets_padre_query, MYSQLI_ASSOC) : [];

    $juegos_query = mysqli_query($conn, "SELECT id, nombre FROM juegos ORDER BY nombre ASC");
    $juegos = $juegos_query ? mysqli_fetch_all($juegos_query, MYSQLI_ASSOC) : [];

    $drivers_query = mysqli_query($conn, "SELECT id, nombre FROM drivers ORDER BY nombre ASC");
    $drivers = $drivers_query ? mysqli_fetch_all($drivers_query, MYSQLI_ASSOC) : [];

    $is_edit = !is_null($values);
    $modal_title = $is_edit ? "Editar Incidencia" : "Registrar Nueva Incidencia";
    $submit_name = $is_edit ? "editar_incidencia" : "crear_incidencia";
    $submit_value = $is_edit ? $values["id"] : "1";
    
    echo '
    <div role="dialog" id="incidencia-modal" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl animate-fade-in" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between">
          <div>
            <h2 class="text-2xl font-semibold text-white" style="color: #ffffff !important;">
              ' . $modal_title . '
            </h2>
            <p class="mt-1 text-sm text-slate-400" style="color: #94a3b8 !important;">
              Asocia la incidencia a un ticket principal y asigna el juego y driver correspondientes.
            </p>
          </div>
        </div>

        <!-- Form -->
        <form class="space-y-5" method="POST" action="incidentes.php">
          ' . ($is_edit ? '<input type="hidden" name="editar_incidencia" value="' . $values["id"] . '" />' : '') . '

          <!-- Ticket Padre -->
          <div>
            <label for="ticket_id" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
              Ticket Padre (Clasificados como Incidencia)
            </label>
            <select
              id="ticket_id"
              name="ticket_id"
              required
              class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
            >';
              if (empty($tickets_padre)) {
                  echo '<option value="" style="background-color: #0f172a; color: #94a3b8;">No hay tickets de incidencia disponibles</option>';
              } else {
                  echo '<option value="" style="background-color: #0f172a; color: #94a3b8;">Selecciona un ticket...</option>';
                  foreach ($tickets_padre as $t) {
                      $selected = ($is_edit && $values["ticket_id"] == $t["id"]) ? "selected" : "";
                      echo '<option value="' . $t["id"] . '" ' . $selected . ' style="background-color: #0f172a; color: #ffffff;">TKT-' . str_pad($t["id"], 3, "0", STR_PAD_LEFT) . ' - ' . htmlspecialchars($t["titulo"]) . '</option>';
                  }
              }
            echo '
            </select>
          </div>

          <!-- Juego + Driver -->
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label for="juego_id" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Juego
              </label>
              <select
                id="juego_id"
                name="juego_id"
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              >
                <option value="" style="background-color: #0f172a; color: #94a3b8;">Selecciona un juego...</option>';
                foreach ($juegos as $j) {
                    $selected = ($is_edit && $values["juego_id"] == $j["id"]) ? "selected" : "";
                    echo '<option value="' . $j["id"] . '" ' . $selected . ' style="background-color: #0f172a; color: #ffffff;">' . htmlspecialchars($j["nombre"]) . '</option>';
                }
              echo '
              </select>
            </div>

            <div>
              <label for="driver_id" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Driver / Controlador
              </label>
              <select
                id="driver_id"
                name="driver_id"
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              >
                <option value="" style="background-color: #0f172a; color: #94a3b8;">Selecciona un driver...</option>';
                foreach ($drivers as $d) {
                    $selected = ($is_edit && $values["driver_id"] == $d["id"]) ? "selected" : "";
                    echo '<option value="' . $d["id"] . '" ' . $selected . ' style="background-color: #0f172a; color: #ffffff;">' . htmlspecialchars($d["nombre"]) . '</option>';
                }
              echo '
              </select>
            </div>
          </div>

          <!-- Detalle -->
          <div>
            <label for="detalle" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
              Detalle Técnico / Reporte
            </label>
            <textarea
              id="detalle"
              name="detalle"
              rows="4"
              required
              placeholder="Detalla el problema técnico, logs, o comportamiento observado..."
              class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
            >' . htmlspecialchars($values["detalle"] ?? "") . '</textarea>
          </div>

          <!-- Acciones -->
          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
            <a
              href="incidentes.php"
              class="rounded-xl border border-slate-600 px-5 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
              style="color: #cbd5e1 !important; border-color: #475569 !important; background-color: #0f172a !important;"
            >
              Cancelar
            </a>
            <button
              type="submit"
              name="' . $submit_name . '"
              value="' . $submit_value . '"
              class="btn-registrar"
            >
              ' . ($is_edit ? 'Actualizar Incidencia' : 'Registrar Incidencia') . '
            </button>
          </div>
        </form>
        <script>
        </script>
      </div>
    </div>';
}

function showIncidenciaDetailModal($conn, $incidencia) {
    $disabled_attr = 'disabled style="background-color: #1e293b !important; color: #94a3b8 !important; border: 1px solid #334155 !important; opacity: 0.6; cursor: not-allowed;"';
    $is_monitoreo = (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "monitoreo");
    
    $incidencia_estado = $incidencia["incidencia_estado"] ?? 'abierto';
    $incidencia_id = intval($incidencia["incidencia_id"]);

    // Query relationships
    $relacionesQuery = mysqli_query($conn, "
        SELECT r.id AS rel_id, r.tipo_relacion,
               i.id AS linked_id, i.detalle AS linked_detalle, t.id AS linked_ticket_id
        FROM relaciones_incidencias r
        JOIN incidencias i ON (r.incidencia_destino_id = i.id AND r.incidencia_origen_id = $incidencia_id)
                           OR (r.incidencia_origen_id = i.id AND r.incidencia_destino_id = $incidencia_id)
        JOIN tickets t ON i.ticket_id = t.id
        WHERE i.id != $incidencia_id
        ORDER BY r.id DESC
    ");
    $relaciones = [];
    if ($relacionesQuery) {
        $relaciones = mysqli_fetch_all($relacionesQuery, MYSQLI_ASSOC);
    }

    // Query other incidents to link
    $incidenciasDisponibles = [];
    if (!$is_monitoreo) {
        $availQuery = mysqli_query($conn, "
            SELECT inc.id, inc.ticket_id, t.titulo 
            FROM incidencias inc
            JOIN tickets t ON inc.ticket_id = t.id
            WHERE inc.id != $incidencia_id
            ORDER BY inc.id DESC
        ");
        if ($availQuery) {
            $incidenciasDisponibles = mysqli_fetch_all($availQuery, MYSQLI_ASSOC);
        }
    }

    $select_attr = $is_monitoreo 
        ? 'disabled class="w-full rounded-xl border border-slate-700 bg-[#1e293b]/50 px-4 py-2.5 text-sm text-slate-400 outline-none cursor-not-allowed" style="background-color: rgb(30 41 59 / 0.5) !important; color: #94a3b8 !important; border: 1px solid #334155 !important;"' 
        : 'id="nuevo_estado" name="nuevo_estado" onchange="toggleIncidenciaResolutionTextarea(this)" class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white outline-none transition focus:border-emerald-500" style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"';

    $textarea_attr = $is_monitoreo 
        ? 'disabled class="w-full resize-none rounded-xl border border-slate-700 bg-[#1e293b]/50 px-4 py-2.5 text-sm text-slate-400 outline-none cursor-not-allowed" style="background-color: rgb(30 41 59 / 0.5) !important; color: #94a3b8 !important; border: 1px solid #334155 !important;" rows="3"' 
        : 'id="respuesta_resolucion" name="respuesta_resolucion" rows="3" placeholder="Describe cómo se solucionó este error..." class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500" style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;" ' . ($incidencia_estado === 'cerrado' ? 'required' : '');

    $ticketDisplay = '<span class="inline-flex items-center gap-1.5 rounded-full bg-blue-950/40 border border-blue-800/50 px-2 py-0.5 text-xs text-blue-400 font-medium">
        TKT-' . str_pad($incidencia["ticket_id"], 3, '0', STR_PAD_LEFT) . '
    </span>';

    // Build relations list
    $relacionesHTML = '';
    if (empty($relaciones)) {
        $relacionesHTML .= '<p class="text-xs text-slate-500 italic">No hay incidencias vinculadas.</p>';
    } else {
        $relacionesHTML .= '<div class="space-y-1.5">';
        foreach ($relaciones as $rel) {
            $tipoBadge = '';
            if ($rel['tipo_relacion'] === 'duplicado') {
                $tipoBadge = '<span class="inline-flex items-center gap-1 rounded bg-amber-950/40 border border-amber-800/40 px-1.5 py-0.5 text-[10px] text-amber-400 font-medium">Duplicada de</span>';
            } elseif ($rel['tipo_relacion'] === 'bloqueante') {
                $tipoBadge = '<span class="inline-flex items-center gap-1 rounded bg-red-950/40 border border-red-800/40 px-1.5 py-0.5 text-[10px] text-red-400 font-medium">Bloqueante</span>';
            } else {
                $tipoBadge = '<span class="inline-flex items-center gap-1 rounded bg-blue-950/40 border border-blue-800/40 px-1.5 py-0.5 text-[10px] text-blue-400 font-medium">Relacionada</span>';
            }
            
            $deleteBtn = '';
            if (!$is_monitoreo) {
                $deleteBtn = '
                <form method="POST" action="incidentes.php" class="inline" onsubmit="return confirm(\'¿Estás seguro de que deseas eliminar este vínculo?\');">
                  <input type="hidden" name="eliminar_relacion" value="1" />
                  <input type="hidden" name="eliminar_relacion_id" value="' . $rel['rel_id'] . '" />
                  <input type="hidden" name="origen_id" value="' . $incidencia_id . '" />
                  <button type="submit" class="text-red-400 hover:text-red-300 text-xs transition">
                    Eliminar
                  </button>
                </form>';
            }

            $relacionesHTML .= '
            <div class="flex items-center justify-between gap-4 rounded-lg bg-slate-950/40 border border-slate-800 p-2 text-xs text-slate-300">
              <div class="flex items-center gap-2 overflow-hidden">
                ' . $tipoBadge . '
                <a href="incidentes.php?ver=' . $rel['linked_id'] . '" class="font-semibold text-emerald-400 hover:underline shrink-0">INC-' . str_pad($rel['linked_id'], 3, '0', STR_PAD_LEFT) . '</a>
                <span class="truncate text-slate-400">("' . htmlspecialchars(mb_strimwidth($rel['linked_detalle'], 0, 45, '...')) . '")</span>
              </div>
              ' . $deleteBtn . '
            </div>';
        }
        $relacionesHTML .= '</div>';
    }

    // Build form to link a new incident
    $formVincular = '';
    if (!$is_monitoreo && !empty($incidenciasDisponibles)) {
        $options = '';
        foreach ($incidenciasDisponibles as $avail) {
            $options .= '<option value="' . $avail['id'] . '" style="background-color: #0f172a; color: #ffffff;">INC-' . str_pad($avail['id'], 3, '0', STR_PAD_LEFT) . ' (TKT-' . $avail['ticket_id'] . ') - ' . htmlspecialchars(mb_strimwidth($avail['titulo'], 0, 30, "...")) . '</option>';
        }
        $formVincular = '
        <form method="POST" action="incidentes.php" class="pt-2 border-t border-slate-800/60 space-y-2">
          <input type="hidden" name="vincular_incidencia" value="1" />
          <input type="hidden" name="origen_id" value="' . $incidencia_id . '" />
          <div class="text-[11px] font-semibold text-slate-400">VINCULAR CON OTRA INCIDENCIA</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <select name="destino_id" required class="col-span-1 sm:col-span-1 rounded-lg border border-slate-600 bg-[#1e293b] px-2 py-1.5 text-xs text-white outline-none transition focus:border-emerald-500" style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;">
              <option value="" style="background-color: #0f172a; color: #64748b;">Seleccionar INC...</option>
              ' . $options . '
            </select>
            <select name="tipo_relacion" required class="rounded-lg border border-slate-600 bg-[#1e293b] px-2 py-1.5 text-xs text-white outline-none transition focus:border-emerald-500" style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;">
              <option value="relacionado" style="background-color: #0f172a; color: #ffffff;">Relacionada</option>
              <option value="duplicado" style="background-color: #0f172a; color: #ffffff;">Duplicada de</option>
              <option value="bloqueante" style="background-color: #0f172a; color: #ffffff;">Bloqueante</option>
            </select>
            <button type="submit" class="rounded-lg bg-emerald-600 text-white font-medium hover:bg-emerald-500 transition px-3 py-1.5 text-xs">
              Vincular
            </button>
          </div>
        </form>';
    }

    $slaDev = calcularEstadoSLADesarrollo($incidencia);
    $semaforo = obtenerEstadoSemaforoBloqueo($conn, $incidencia_id);
    echo '
    <div role="dialog" id="detail-incidencia-modal" aria-labelledby="detail-incidencia-title" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl animate-fade-in" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <!-- Header -->
        <div class="mb-4 flex items-start justify-between">
          <div>
            <h2 id="detail-incidencia-title" class="text-2xl font-semibold text-white flex items-center gap-2" style="color: #ffffff !important;">
              Detalles de INC-' . str_pad($incidencia["incidencia_id"], 3, '0', STR_PAD_LEFT) . '
              ' . $ticketDisplay . '
            </h2>
            <p class="mt-1 text-sm text-slate-400" style="color: #94a3b8 !important;">
              Consulta los detalles técnicos y estado de resolución de la incidencia.
            </p>
          </div>
        </div>

        <div class="space-y-4 pr-2" style="max-height: 60vh; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #475569 #0f172a;">
          
          <!-- Datos Principales -->
          <div class="grid grid-cols-2 gap-4 rounded-xl border border-slate-700 bg-slate-900/60 p-4">
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Juego</span>
              <span class="text-sm font-medium text-white">' . htmlspecialchars($incidencia["juego_nombre"] ?? "No especificado") . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Driver / Controlador</span>
              <span class="text-sm font-medium text-white">' . htmlspecialchars($incidencia["driver_nombre"] ?? "No especificado") . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Usuario Creador del Ticket</span>
              <span class="text-sm font-medium text-white">' . htmlspecialchars($incidencia["usuario_creador"] ?? "N/A") . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Asignado a</span>
              <span class="text-sm font-medium text-white">' . htmlspecialchars($incidencia["usuario_asignado"] ?: "Sin asignar") . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Prioridad</span>
              <span class="mt-1 inline-block text-xs px-2.5 py-0.5 rounded-full font-medium ' . getPrioridadBadgeClass($incidencia["ticket_prioridad"]) . '">' . ucfirst(strtolower(trim($incidencia["ticket_prioridad"]))) . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">SLA Desarrollo</span>
              <span class="mt-1 inline-block badge-custom ' . $slaDev['badge_class'] . '">' . $slaDev['badge_label'] . '</span>
              <span class="block text-[11px] text-slate-400 mt-1">' . htmlspecialchars($slaDev['tiempo_texto']) . ' · ' . htmlspecialchars($slaDev['hito_activo']) . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Estado</span>
              <span class="mt-1 inline-block text-xs px-2.5 py-0.5 rounded-full font-medium ' . getEstadoBadgeClass($incidencia_estado) . '">' . htmlspecialchars(getEstadoNombreLegible($incidencia_estado)) . '</span>
            </div>
          </div>

          <!-- Detalle Técnico / Reporte -->
          <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Detalle Técnico / Reporte</span>
            <p class="text-sm text-slate-300 leading-relaxed bg-slate-950/40 p-3 rounded-lg border border-slate-800">
              "' . htmlspecialchars($incidencia["detalle"] ?? "Sin detalle descriptivo.") . '"
            </p>
          </div>

          <!-- Detalle del Ticket Vinculado -->
          <div class="rounded-xl border border-blue-900/30 bg-blue-950/10 p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-blue-400 mb-2">Ticket de Soporte Relacionado</span>
            <div class="space-y-2 text-sm text-slate-300">
              <div class="flex justify-between">
                <span class="font-semibold text-slate-400">Título del Ticket:</span>
                <span>' . htmlspecialchars($incidencia["ticket_titulo"]) . '</span>
              </div>
              <div class="pt-2 border-t border-slate-800">
                <span class="block font-semibold text-slate-400 mb-1">Descripción de la incidencia original:</span>
                <p class="text-xs bg-slate-950/30 p-2 rounded text-slate-400 leading-relaxed">' . htmlspecialchars($incidencia["ticket_descripcion"]) . '</p>
              </div>
            </div>
          </div>

          <!-- Incidencias Vinculadas -->
          <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4 space-y-3">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Incidencias Vinculadas</span>
            ' . $relacionesHTML . '
            ' . $formVincular . '
          </div>

          <!-- Formulario Actualización Estado (HU-42: Máquina de 5 Estados Estricta) -->
          <form class="pt-2 border-t border-slate-800 space-y-4" method="POST" action="incidentes.php">
            <input type="hidden" name="actualizar_estado_ticket" value="1" />
            <input type="hidden" name="ticket_id" value="' . $incidencia["ticket_id"] . '" />
            <input type="hidden" name="incidencia_id" value="' . $incidencia["incidencia_id"] . '" />

            ' . ($semaforo['bloqueada'] ? '
            <div class="rounded-xl border border-rose-800 bg-rose-950/40 p-4">
              <div class="flex items-start gap-3">
                <span class="text-xl">🛑</span>
                <div>
                  <h4 class="text-xs font-bold uppercase tracking-wider text-rose-400">Semáforo de Bloqueo Activo</h4>
                  <p class="text-xs text-rose-200 mt-1 leading-relaxed">
                    ❌ ' . htmlspecialchars($semaforo['mensaje_bloqueo']) . '. No es posible avanzar a Mitigada ni Corregida hasta que sus bloqueantes sean corregidas.
                  </p>
                </div>
              </div>
            </div>
            ' : '') . '

            ' . (in_array($incidencia_estado, ['Corregida', 'Desestimada', 'cerrado']) ? '
            <div class="rounded-xl border border-slate-700 bg-slate-900/40 p-4 text-center">
              <span class="inline-flex items-center gap-2 text-sm text-slate-300 font-medium">
                🔒 Esta incidencia se encuentra en estado terminal (<strong>' . htmlspecialchars(ucfirst($incidencia_estado)) . '</strong>) y no admite transiciones posteriores.
              </span>
            </div>
            ' : '
            <div>
              <label for="accion_incidencia" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Acción de Transición de Estado (HU-42)
              </label>
              <select
                id="accion_incidencia"
                name="accion_incidencia"
                onchange="toggleAccionesIncidencia(this)"
                required
                ' . ($is_monitoreo ? 'disabled' : '') . '
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white outline-none transition focus:border-emerald-500"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              >
                <option value="" style="background-color: #0f172a; color: #64748b;">Selecciona una acción...</option>
                ' . ($incidencia_estado === 'Reportada' || $incidencia_estado === 'abierto' ? '
                <option value="analizarLogs" style="background-color: #0f172a; color: #ffffff;">🔍 Analizar Logs (Pasa a En Investigación)</option>
                <option value="descartarFallo" style="background-color: #0f172a; color: #ffffff;">❌ Descartar Fallo (Pasa a Desestimada)</option>
                ' : '') . '
                ' . ($incidencia_estado === 'EnInvestigacion' || $incidencia_estado === 'en progreso' ? '
                <option value="aplicarSolucionTemporal" style="background-color: #0f172a; color: #ffffff;">⏳ Aplicar Solución Temporal (Pasa a Mitigada)</option>
                <option value="desplegarParche" style="background-color: #0f172a; color: #ffffff;">🚀 Desplegar Parche (Pasa a Corregida - Requiere Dictamen)</option>
                <option value="asociarCorrelacion" style="background-color: #0f172a; color: #ffffff;">🔗 Asociar Correlación (Mantiene En Investigación agrupado)</option>
                ' : '') . '
                ' . ($incidencia_estado === 'Mitigada' ? '
                <option value="solucionDefinitivaConfirmada" style="background-color: #0f172a; color: #ffffff;">✅ Solución Definitiva Confirmada (Pasa a Corregida)</option>
                ' : '') . '
              </select>
            </div>

            <!-- Campo dinámico: Dictamen Técnico / Parche (Obligatorio para desplegarParche y opcional para solución definitiva) -->
            <div id="contenedor-dictamen-tecnico" style="display: none;" class="space-y-2">
              <label for="dictamen_tecnico" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Dictamen Técnico Obligatorio (Resolución del Error)
              </label>
              <textarea
                id="dictamen_tecnico"
                name="dictamen_tecnico"
                rows="3"
                placeholder="Describe el dictamen técnico o los cambios del parche aplicado..."
                class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              ></textarea>
            </div>

            <!-- Campo dinámico: Motivo de Descarte (para descartarFallo) -->
            <div id="contenedor-motivo-descarte" style="display: none;" class="space-y-2">
              <label for="motivo_descarte" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Motivo de Descarte
              </label>
              <textarea
                id="motivo_descarte"
                name="motivo_descarte"
                rows="2"
                placeholder="Explica por qué se descarta esta incidencia (no reproducible, error de configuración, etc.)..."
                class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              ></textarea>
            </div>

            <!-- Campo dinámico: Solución Temporal (para aplicarSolucionTemporal) -->
            <div id="contenedor-solucion-temporal" style="display: none;" class="space-y-2">
              <label for="solucion_temporal" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Detalle de Solución Temporal (Workaround)
              </label>
              <textarea
                id="solucion_temporal"
                name="solucion_temporal"
                rows="2"
                placeholder="Detalla el workaround o mitigación provisional aplicada..."
                class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              ></textarea>
            </div>

            <!-- Campo dinámico: Selector de correlación para asociarCorrelacion -->
            <div id="contenedor-correlacion" style="display: none;" class="space-y-2">
              <label for="correlacion_destino_id" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Incidencia Destino para Correlacionar
              </label>
              <select
                id="correlacion_destino_id"
                name="correlacion_destino_id"
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white outline-none transition focus:border-emerald-500"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              >
                <option value="">Selecciona incidencia semejante...</option>
                ' . (!empty($incidenciasDisponibles) ? implode("", array_map(function($a) {
                    return "<option value=\"{$a['id']}\" style=\"background-color: #0f172a; color: #ffffff;\">INC-" . str_pad($a['id'], 3, '0', STR_PAD_LEFT) . " - " . htmlspecialchars(mb_strimwidth($a['titulo'], 0, 40, '...')) . "</option>";
                }, $incidenciasDisponibles)) : '') . '
              </select>
            </div>

            <!-- Acciones de Pie -->
            <div class="flex items-center justify-end gap-3 pt-2">
              <a
                href="incidentes.php"
                class="rounded-xl border border-slate-600 px-5 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                style="color: #cbd5e1 !important; border-color: #475569 !important; background-color: #0f172a !important;"
              >
                Cancelar
              </a>
              ' . (!$is_monitoreo ? '
              <button
                type="submit"
                class="btn-registrar"
              >
                Ejecutar Transición
              </button>
              ' : '') . '
            </div>
            ') . '
          </form>
        </div>

        <script>
          function toggleAccionesIncidencia(selectEl) {
              const val = selectEl.value;
              const boxDictamen = document.getElementById("contenedor-dictamen-tecnico");
              const boxDescarte = document.getElementById("contenedor-motivo-descarte");
              const boxSolTemp  = document.getElementById("contenedor-solucion-temporal");
              const boxCorrel   = document.getElementById("contenedor-correlacion");
              const txtDictamen = document.getElementById("dictamen_tecnico");

              if (boxDictamen) boxDictamen.style.display = (val === "desplegarParche" || val === "solucionDefinitivaConfirmada") ? "block" : "none";
              if (boxDescarte) boxDescarte.style.display = (val === "descartarFallo") ? "block" : "none";
              if (boxSolTemp)  boxSolTemp.style.display  = (val === "aplicarSolucionTemporal") ? "block" : "none";
              if (boxCorrel)   boxCorrel.style.display   = (val === "asociarCorrelacion") ? "block" : "none";

              if (txtDictamen) {
                  if (val === "desplegarParche") {
                      txtDictamen.setAttribute("required", "required");
                  } else {
                      txtDictamen.removeAttribute("required");
                  }
              }
          }
        </script>
      </div>
    </div>';
}


$camposIncidencias = [
    // 1. ID Incidencia (INC-001) y Ticket Padre
    function($fila) {
        $incIdFormatted = 'INC-' . str_pad($fila["incidencia_id"], 3, "0", STR_PAD_LEFT);
        $ticketBadge = "";
        if (!empty($fila["ticket_id"])) {
            $ticketPad = str_pad($fila["ticket_id"], 3, "0", STR_PAD_LEFT);
            $ticketBadge = '
                <span class="ml-2 inline-flex items-center gap-1 rounded bg-blue-950/40 border border-blue-900/50 px-1.5 py-0.5 text-[10px] text-blue-400 font-medium cursor-help" title="Viene del ticket TKT-' . $ticketPad . '">
                    🔗 TKT-' . $ticketPad . '
                </span>';
        }
        return '<td class="p-3 text-cyan-400 font-semibold whitespace-nowrap">' . $incIdFormatted . $ticketBadge . '</td>';
    },

    // 2. Título del Ticket Padre
    function($fila) {
        $titulo = htmlspecialchars($fila["ticket_titulo"]);
        return '<td class="p-3 text-slate-200">
                    <div class="max-w-[200px] truncate" title="' . $titulo . '">
                        ' . $titulo . '
                    </div>
                </td>';
    },

    // 3. Juego
    function($fila) {
        $juego = !empty($fila["juego_nombre"]) ? htmlspecialchars($fila["juego_nombre"]) : '<span class="text-slate-500 italic">No especificado</span>';
        $title = !empty($fila["juego_nombre"]) ? htmlspecialchars($fila["juego_nombre"]) : 'No especificado';
        return '<td class="p-3 text-slate-300">
                    <div class="max-w-[120px] truncate" title="' . $title . '">
                        ' . $juego . '
                    </div>
                </td>';
    },

    // 4. Driver
    function($fila) {
        $driver = !empty($fila["driver_nombre"]) ? htmlspecialchars($fila["driver_nombre"]) : '<span class="text-slate-500 italic">No especificado</span>';
        $title = !empty($fila["driver_nombre"]) ? htmlspecialchars($fila["driver_nombre"]) : 'No especificado';
        return '<td class="p-3 text-slate-300">
                    <div class="max-w-[150px] truncate" title="' . $title . '">
                        ' . $driver . '
                    </div>
                </td>';
    },

    // 5. Prioridad (Con Badge)
    function($fila) {
        $clase = getPrioridadBadgeClass($fila["ticket_prioridad"]);
        $prioridad = htmlspecialchars($fila["ticket_prioridad"]);
        return '<td class="p-3 text-slate-300 whitespace-nowrap">
                    <span class="badge-custom ' . $clase . '">
                        ' . $prioridad . '
                    </span>
                </td>';
    },

    // 6. Estado Dinámico (Cálculo interno del estado real de la incidencia)
    function($fila) {
        $estadoReal = $fila["incidencia_estado"] ?? 'abierto';
            
        $clase = getEstadoBadgeClass($estadoReal);
        $textoEstado = htmlspecialchars(getEstadoNombreLegible($estadoReal));
        
        return '<td class="p-3 text-slate-300 whitespace-nowrap">
                    <span class="badge-custom ' . $clase . '">
                        ' . $textoEstado . '
                    </span>
                </td>';
    },

    // 6.b HU-48: SLA de Desarrollo (cuenta regresiva, congelada en Mitigada/Corregida)
    function($fila) {
        $sla = calcularEstadoSLADesarrollo($fila);
        $attrs = '';
        if (empty($sla['es_congelado']) && !empty($sla['deadline_timestamp'])) {
            $attrs = ' data-sla-deadline="' . intval($sla['deadline_timestamp']) . '" data-sla-total="' . (intval($sla['meta_horas']) * 3600) . '"';
        }
        $limite = !empty($sla['deadline_timestamp']) ? date('d/m/Y H:i', $sla['deadline_timestamp']) : '';
        $titulo = 'Hito: ' . $sla['hito_activo'] . ($limite ? ' | Límite: ' . $limite : '');
        return '<td class="p-3 whitespace-nowrap"' . $attrs . '>
                    <span class="badge-custom sla-badge ' . $sla['badge_class'] . '" title="' . htmlspecialchars($titulo) . '">' . $sla['badge_label'] . '</span>
                    <span class="sla-text block text-[10px] text-slate-400 mt-0.5">' . htmlspecialchars($sla['tiempo_texto']) . '</span>
                </td>';
    },

    // 7. Acciones (Ver con URL dinámica y Eliminar condicional)
    function($fila) use ($is_monitoreo, $buscar, $filtro, $prioridad) {
        // Construimos los parámetros para la URL de manera segura
        $params = http_build_query([
            'ver' => $fila["incidencia_id"],
            'buscar' => $buscar,
            'filtro' => $filtro,
            'prioridad' => $prioridad
        ]);

        $deleteBtn = '';
        if (!$is_monitoreo) {
            $deleteBtn = '
                <form method="POST" action="incidentes.php" onsubmit="return confirm(\'¿Estás seguro de que deseas eliminar esta incidencia permanentemente?\');" class="inline-block m-0 p-0">
                    <input type="hidden" name="eliminar_incidencia" value="' . $fila["incidencia_id"] . '" />
                    <button type="submit" class="btn-accion-icono icono-eliminar" title="Eliminar Incidencia">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                    </button>
                </form>';
        }

        $historyBtn = '';
        if (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador")) {
            $historyParams = http_build_query([
                'historial' => $fila["ticket_id"],
                'buscar' => $buscar,
                'filtro' => $filtro,
                'prioridad' => $prioridad
            ]);
            $historyBtn = '
                <a href="incidentes.php?' . $historyParams . '" class="btn-accion-icono" style="color: #60a5fa !important;" title="Historial del Ticket">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-file-text" viewBox="0 0 16 16"><path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/></svg>
                </a>';
        }

        return '<td class="p-3 text-center">
                    <div class="contenedor-acciones-tabla">
                        <a href="incidentes.php?' . $params . '" class="btn-accion-icono icono-ver" title="Ver Detalles">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                        </a>
                        ' . $historyBtn . '
                        ' . $deleteBtn . '
                    </div>
                </td>';
    }];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Tablero de Incidencias</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/index-V-K5O4c4.css">
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
    .badge-baja, .badge-abierto {
        background-color: rgb(6, 78, 59) !important;
        color: #34d399 !important;
        border-color: #065f46 !important;
    }
    .badge-media {
        background-color: rgb(66, 32, 6) !important;
        color: #facc15 !important;
        border-color: #854d0e !important;
    }
    .badge-alta {
        background-color: rgb(67, 20, 7) !important;
        color: #fb923c !important;
        border-color: #9a3412 !important;
    }
    .badge-critica, .badge-cerrado {
        background-color: rgb(69, 10, 10) !important;
        color: #f87171 !important;
        border-color: #991b1b !important;
    }
    .badge-en-progreso {
        background-color: rgb(23, 37, 84) !important;
        color: #60a5fa !important;
        border-color: #1e40af !important;
    }

    /* HU-42: estados de incidencia */
    .badge-reportada {
        background-color: rgba(139, 92, 246, 0.22) !important;
        color: #c4b5fd !important;
        border-color: #7c3aed !important;
    }
    .badge-investigacion {
        background-color: rgba(37, 99, 235, 0.22) !important;
        color: #93c5fd !important;
        border-color: #1d4ed8 !important;
    }
    .badge-mitigada {
        background-color: rgba(217, 119, 6, 0.25) !important;
        color: #fcd34d !important;
        border-color: #d97706 !important;
    }
    .badge-corregida {
        background-color: rgba(16, 185, 129, 0.22) !important;
        color: #6ee7b7 !important;
        border-color: #059669 !important;
    }
    .badge-desestimada {
        background-color: rgba(100, 116, 139, 0.25) !important;
        color: #cbd5e1 !important;
        border-color: #475569 !important;
    }

    /* HU-48: SLA de desarrollo */
    .badge-sla-enplazo {
        background-color: rgba(16, 185, 129, 0.22) !important;
        color: #6ee7b7 !important;
        border-color: #059669 !important;
    }
    .badge-sla-proximo {
        background-color: rgba(217, 119, 6, 0.25) !important;
        color: #fcd34d !important;
        border-color: #d97706 !important;
    }
    .badge-sla-vencida {
        background-color: rgba(225, 29, 72, 0.22) !important;
        color: #f87171 !important;
        border-color: #be123c !important;
    }
    .badge-sla-desestimada {
        background-color: rgba(100, 116, 139, 0.25) !important;
        color: #cbd5e1 !important;
        border-color: #475569 !important;
    }
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
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 0.75rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    @media (max-width: 900px) {
        .sla-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    }
    </style>
</head>
<body class="bg-slate-950 text-white overflow-hidden">
  <div id="root">
    <div class="relative flex h-screen overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950">

      <!-- Fondo -->
      <div class="absolute inset-0 opacity-5 pointer-events-none">
        <div
          class="absolute inset-0"
          style="
            background-image: radial-gradient(circle at 2px 2px, rgb(16, 185, 129) 1px, transparent 0);
            background-size: 40px 40px;
          "
        ></div>
      </div>
      <!-- Brillo Cyan/Esmeralda de fondo -->
      <div class="absolute top-[-20%] left-[-20%] w-[600px] h-[600px] rounded-full bg-cyan-500/10 blur-[120px] pointer-events-none z-0"></div>
      <div class="absolute bottom-[-20%] right-[-20%] w-[600px] h-[600px] rounded-full bg-emerald-500/10 blur-[120px] pointer-events-none z-0"></div>

      <!-- Sidebar -->
      <aside class="w-64 bg-slate-900/90 backdrop-blur-sm border-r border-emerald-900/30 transition-all duration-300 flex flex-col">
        <!-- Logo -->
        <?php require_once("templates\logo.php") ?>
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
        <?php require_once("templates\sidebar.php") ?>

        <!-- Footer -->
        <div class="border-t border-slate-800 p-4">
          <button
            class="flex w-full items-center justify-center rounded-md px-4 py-2 text-slate-400 transition hover:bg-slate-800 hover:text-white"
          >
            ✕
          </button>
        </div>
      </aside>

      <!-- Contenido -->
      <div class="flex flex-1 flex-col overflow-hidden">
        <!-- Header -->
        <?php require_once('templates\header.php') ?>

        <!-- Main -->
        <main class="flex-1 overflow-auto p-8">
          <div class="space-y-6">
            <!-- Title -->
            <div class="flex items-center justify-between">
              <div>
                <h1 class="text-white text-2xl font-semibold">
                  Tablero de Incidencia
                </h1>

                <p class="text-slate-400">
                  Registra, edita y asocia incidencias técnicas a tickets de soporte principales.
                </p>
              </div>
              
              <div class="flex items-center gap-3">
                <?php if (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador")): ?>
                <a 
                    href="funcs/exportar-incidencias-csv.php?<?php 
                        echo http_build_query([
                            'buscar' => $_GET['buscar'] ?? '',
                            'filtro' => $_GET['filtro'] ?? '',
                            'prioridad' => $_GET['prioridad'] ?? ''
                        ]); ?>"
                    class="btn-exportar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-excel-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0zM9.5 3.5v-2l3 3h-2a.5.5 0 0 1-.5-.5zM5.884 6.68 8 9.5l2.116-2.82a.25.25 0 0 1 .416.148l.49 1.956.452-1.8c.045-.18.2-.314.385-.314h.342a.25.25 0 0 1 .23.344l-.8 2a.25.25 0 0 1-.456-.01L10.3 8.3l-.364 1.455a.25.25 0 0 1-.482.01L7.26 6.81a.25.25 0 0 0-.46.024l-.534 2.136a.25.25 0 0 1-.485-.012L5.3 7.8l-.3 1.2a.25.25 0 0 1-.485-.012l-.8-3a.25.25 0 0 1 .37-.267h.342c.18 0 .338.125.388.3l.423 1.692.42-1.68a.25.25 0 0 1 .425-.136z"/>
                    </svg>
                    Exportar a Excel
                </a>
                <?php endif; ?>
                <?php if (!$is_monitoreo) { ?>
                <a
                  href="incidentes.php?nuevo=1"
                  class="btn-registrar"
                >
                  + Registrar Incidencia
                </a>
                <?php } ?>
              </div>
            </div>

            <!-- Cumplimiento SLA de Desarrollo (HU-48) -->
            <?php
              $enPlazoPuro = max(0, intval($metricasDev['en_plazo_total']) - intval($metricasDev['proximas_a_vencer']));
              $colorTasa = $metricasDev['cumple_meta'] ? '#10b981' : '#ef4444';
              $textoTasa = $metricasDev['cumple_meta'] ? '#6ee7b7' : '#f87171';
            ?>
            <div id="sla-dev-dashboard" class="sla-panel">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #334155 !important;">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <span style="font-size: 15px;">⏱️</span>
                  <h2 style="color: #ffffff !important; font-size: 14px; font-weight: 700; margin: 0; letter-spacing: 0.015em;">SLA de Desarrollo · Mitigación y Corrección</h2>
                </div>
                <span style="display: inline-flex; align-items: center; gap: 6px; background-color: rgba(16, 185, 129, 0.15) !important; color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; padding: 2px 8px !important; border-radius: 9999px !important; font-size: 11px !important; font-weight: 600 !important;">
                  <span style="display: inline-block; width: 6px; height: 6px; border-radius: 9999px; background-color: #34d399; box-shadow: 0 0 6px #34d399;"></span> Tiempo Real
                </span>
              </div>

              <div class="sla-grid">
                <!-- 1. Vencidas -->
                <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #ef4444 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: #f87171 !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;"><span>🚨</span> Vencidas</h3>
                    <span style="background-color: rgba(225, 29, 72, 0.2) !important; color: #fca5a5 !important; border: 1px solid rgba(225, 29, 72, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Fuera de plazo</span>
                  </div>
                  <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;"><?php echo intval($metricasDev['vencidas_total']); ?></div>
                  <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">Incidencias vencidas</div>
                </div>

                <!-- 2. Próximas a vencer -->
                <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #f59e0b !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: #fcd34d !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;"><span>⚠️</span> Próximas a Vencer</h3>
                    <span style="background-color: rgba(217, 119, 6, 0.2) !important; color: #fde68a !important; border: 1px solid rgba(217, 119, 6, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">&lt; 25%</span>
                  </div>
                  <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;"><?php echo intval($metricasDev['proximas_a_vencer']); ?></div>
                  <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">Riesgo inminente</div>
                </div>

                <!-- 3. En plazo -->
                <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid #10b981 !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: #6ee7b7 !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;"><span>🟢</span> En Plazo</h3>
                    <span style="background-color: rgba(16, 185, 129, 0.2) !important; color: #a7f3d0 !important; border: 1px solid rgba(16, 185, 129, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Bajo Control</span>
                  </div>
                  <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;"><?php echo $enPlazoPuro; ?></div>
                  <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;">Dentro del plazo operativo</div>
                </div>

                <!-- 4. Cumplimiento -->
                <div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-top: 3px solid <?php echo $colorTasa; ?> !important; border-radius: 8px !important; padding: 10px 14px !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.4) !important;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: <?php echo $textoTasa; ?> !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 4px;"><span>🎯</span> Cumplimiento</h3>
                    <span style="background-color: rgba(100, 116, 139, 0.2) !important; color: #cbd5e1 !important; border: 1px solid rgba(100, 116, 139, 0.4) !important; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 9999px;">Meta ≥ <?php echo number_format($metricasDev['meta_global'], 1); ?>%</span>
                  </div>
                  <div style="font-size: 24px; font-weight: 800; color: #ffffff !important; margin: 4px 0; line-height: 1;"><?php echo number_format($metricasDev['tasa_cumplimiento'], 1); ?>%</div>
                  <div style="padding-top: 6px; border-top: 1px solid #334155 !important; font-size: 11px; color: #94a3b8 !important;"><?php echo intval($metricasDev['total_evaluadas']); ?> evaluadas · <?php echo $metricasDev['cumple_meta'] ? 'Meta cumplida' : 'Bajo la meta'; ?></div>
                </div>
              </div>
            </div>

            <!-- Buscador y Filtro -->
            <form method="GET" action="incidentes.php">
              <div class="w-full p-6 border border-slate-700 rounded-lg bg-slate-900/40">
                <!-- FILTROS -->
                <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center">
                
                  <!-- SEARCH -->
                  <div class="flex-1 relative">
                    <input
                      type="text"
                      name="buscar"
                      value="<?php echo htmlspecialchars($buscar); ?>"
                      placeholder="Buscar incidencias por título, detalle, juego o driver..."
                      class="w-full h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                  </div>

                  <!-- FILTRO RESOLUCION -->
                  <select
                    name="filtro"
                    class="w-full md:w-[180px] h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm"
                    onchange="this.form.submit()"
                  >
                    <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos los Estados</option>
                    <option value="resueltos" <?php echo $filtro === 'resueltos' ? 'selected' : ''; ?>>Resueltos (Cerrados)</option>
                    <option value="no_resueltos" <?php echo $filtro === 'no_resueltos' ? 'selected' : ''; ?>>No Resueltos</option>
                  </select>

                  <!-- FILTRO PRIORIDAD -->
                  <select
                    name="prioridad"
                    class="w-full md:w-[180px] h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm"
                    onchange="this.form.submit()"
                  >
                    <option value="todos" <?php echo $prioridad === 'todos' ? 'selected' : ''; ?>>Todas las Prioridades</option>
                    <option value="critica" <?php echo $prioridad === 'critica' ? 'selected' : ''; ?>>Crítica</option>
                    <option value="alta" <?php echo $prioridad === 'alta' ? 'selected' : ''; ?>>Alta</option>
                    <option value="media" <?php echo $prioridad === 'media' ? 'selected' : ''; ?>>Media</option>
                    <option value="baja" <?php echo $prioridad === 'baja' ? 'selected' : ''; ?>>Baja</option>
                  </select>
                
                </div>
                
                <div class="flex justify-between items-center mt-4">
                  <div class="flex gap-2">
                    <button
                      type="submit"
                      class="rounded-lg px-4 py-2 bg-slate-800 text-slate-200 transition hover:bg-slate-700 text-sm font-medium"
                    >
                      Buscar
                    </button>
                    <?php if (!empty($buscar) || $filtro !== "todos" || $prioridad !== "todos"): ?>
                      <a
                        href="incidentes.php"
                        class="rounded-lg px-4 py-2 border border-slate-700 text-slate-400 hover:text-white hover:bg-slate-800 text-sm flex items-center transition-all"
                      >
                        Limpiar
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </form>

            <!-- Tabla de Resultados -->
            <div class="w-full mt-6 overflow-x-auto rounded-lg border border-slate-700">
              <table class="w-full min-w-[800px] text-sm">
                <thead>
                  <tr class="border-b border-slate-700 text-slate-300 bg-slate-900/60">
                    <th class="text-left p-3 whitespace-nowrap">ID</th>
                    <th class="text-left p-3 whitespace-nowrap">Ticket Padre</th>
                    <th class="text-left p-3 whitespace-nowrap">Juego</th>
                    <th class="text-left p-3 whitespace-nowrap">Driver / Controlador</th>
                    <th class="text-left p-3 whitespace-nowrap">Prioridad</th>
                    <th class="text-left p-3 whitespace-nowrap">Estado</th>
                    <th class="text-left p-3 whitespace-nowrap">SLA Desarrollo</th>
                    <th class="text-center p-3 whitespace-nowrap">Acciones</th>
                  </tr>
                </thead>
                <tbody class="text-white">
                  <?php if (!empty($incidencias)){
                    echo renderizarFilasTabla($camposIncidencias, $incidencias);  
                  } ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>

      <!-- Blur decorativo -->
      <div class="pointer-events-none absolute right-0 top-0 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
      <div class="pointer-events-none absolute bottom-0 left-0 h-96 w-96 rounded-full bg-cyan-500/10 blur-3xl"></div>
    </div>
  </div>

  <script>
    // HU-48: cuenta regresiva en vivo de la grilla (se congela sola en Mitigada/Corregida: sin data-sla-deadline)
    (function () {
      function fmt(s) {
        s = Math.max(0, Math.floor(s));
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
        if (h >= 24) { return Math.floor(h / 24) + 'd ' + (h % 24) + 'h'; }
        return h + 'h ' + m + 'm';
      }
      function tick() {
        var now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('[data-sla-deadline]').forEach(function (td) {
          var deadline = parseInt(td.getAttribute('data-sla-deadline'), 10);
          var total = parseInt(td.getAttribute('data-sla-total'), 10) || 1;
          var diff = deadline - now;
          var badge = td.querySelector('.sla-badge');
          var txt = td.querySelector('.sla-text');
          if (!badge || !txt) return;
          badge.classList.remove('badge-sla-enplazo', 'badge-sla-proximo', 'badge-sla-vencida');
          if (diff <= 0) {
            badge.classList.add('badge-sla-vencida');
            badge.textContent = '🚨 Incidencia Vencida';
            txt.textContent = 'Expiró hace ' + fmt(-diff);
          } else if (diff / total < 0.25) {
            badge.classList.add('badge-sla-proximo');
            badge.textContent = '⚠️ Próximo a Vencer';
            txt.textContent = '⏳ ' + fmt(diff) + ' restantes';
          } else {
            badge.classList.add('badge-sla-enplazo');
            badge.textContent = '🟢 En Plazo';
            txt.textContent = '⏳ ' + fmt(diff) + ' restantes';
          }
        });
      }
      setInterval(tick, 30000);
    })();
  </script>

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
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .animate-fade-in {
      animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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

  <!-- Modales Render -->
  <?php
  if ((isset($_GET["nuevo"]) || (isset($_SESSION["persisted_form_type"]) && $_SESSION["persisted_form_type"] === "crear_incidencia")) && !$is_monitoreo) {
      $values = isset($_SESSION["persisted_form"]) ? $_SESSION["persisted_form"] : null;
      renderIncidenciaModal($conn, $values);
      unset($_SESSION["persisted_form"]);
      unset($_SESSION["persisted_form_type"]);
  } else if (($editIncidencia !== null || (isset($_SESSION["persisted_form_type"]) && $_SESSION["persisted_form_type"] === "editar_incidencia")) && !$is_monitoreo) {
      $values = isset($_SESSION["persisted_form"]) ? $_SESSION["persisted_form"] : $editIncidencia;
      renderIncidenciaModal($conn, $values);
      unset($_SESSION["persisted_form"]);
      unset($_SESSION["persisted_form_type"]);
  } else if ($showIncidencia !== null) {
      showIncidenciaDetailModal($conn, $showIncidencia);
  }
  
  if (isset($_GET["historial"])) {
      mostrarModalHistorial($conn, intval($_GET["historial"]));
  }
  ?>
</body>
</html>
