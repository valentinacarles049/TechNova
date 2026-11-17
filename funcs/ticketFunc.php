<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once(__DIR__ . "/../db.php");
require_once(__DIR__ . "/generalFuncs.php");
require_once(__DIR__ . "/ticketLifecycle.php");

// Asegurar columna fecha_vencimiento en tickets si no existe (HU-39)
if (isset($conn) && $conn) {
    $checkColVenc = @mysqli_query($conn, "SHOW COLUMNS FROM `tickets` LIKE 'fecha_vencimiento'");
    if ($checkColVenc && mysqli_num_rows($checkColVenc) == 0) {
        @mysqli_query($conn, "ALTER TABLE `tickets` ADD COLUMN `fecha_vencimiento` timestamp NULL DEFAULT NULL AFTER `fecha_apertura`");
    }
}

if (!function_exists('normalizarTexto')) {
    function normalizarTexto($texto) {
        $texto = trim($texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        $texto = strtolower($texto);
        return $texto;
    }
}

if (!function_exists('esCategoriaTecnica')) {
    function esCategoriaTecnica($categoria): bool {
        $cat = strtolower(trim((string)$categoria));
        if ($cat === '') {
            return false;
        }
        
        $terminosTecnicos = [
            'bug', 'falla', 'fallo', 'error', 'crash', 'crasheo',
            'driver', 'controlador', 'grafic', 'gráfic', 'video', 'gpu',
            'pantalla azul', 'bsod', 'incidencia', 'tecnic', 'técnic',
            'glitch', 'freeze', 'congelamiento', 'rendimiento', 'hardware',
            'shader', 'vulkan', 'directx', 'anti-cheat', 'anticheat',
            'desempeño', 'optimizacion', 'optimización'
        ];
        
        foreach ($terminosTecnicos as $termino) {
            if (strpos($cat, $termino) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

if (!function_exists('redirigirTicket')) {
    function redirigirTicket($url = "ticketManegement.php") {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit();
        } else {
            echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
            exit();
        }
    }
}

if (!function_exists('verificarPermisoMonitoreoOSla')) {
/**
 * Verifica si el usuario autenticado tiene rol de Responsable de Monitoreo,
 * Administrador o Responsable de SLA (RBAC).
 */
function verificarPermisoMonitoreoOSla(): bool {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    if (empty($_SESSION["currentUser"])) {
        return false;
    }
    $rol = strtolower(trim($_SESSION["currentTeam"] ?? $_SESSION["currentUserRol"] ?? ''));
    $rolesPermitidos = ['monitoreo', 'administrador', 'responsable sla', 'responsable de monitoreo', 'sla lead', 'admin'];
    return in_array($rol, $rolesPermitidos, true);
}
}

if (!function_exists('obtenerHorasSlaPorPrioridad')) {
/**
 * Obtiene las horas límites de SLA según la prioridad del ticket.
 * Crítica: 4h, Alta: 24h, Media: 48h, Baja: 96h (unificado con generalFuncs.php)
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

if (!function_exists('formatearHorasMinutos')) {
/**
 * Convierte una cantidad de segundos a formato legible de horas y minutos.
 */
function formatearHorasMinutos(int $segundos): string {
    $segundos = abs($segundos);
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    return "{$horas}h {$minutos}m";
}
}

if (!function_exists('calcularSlaTicket')) {
/**
 * Calcula el estado SLA de un ticket (Vencido, Warning o En Tiempo).
 * HU-43: si el ticket acumula más de una incidencia activa (Reportada, EnInvestigacion, Mitigada),
 * el SLA se extiende un 50% del tiempo base por cada incidencia adicional:
 * Horas SLA Efectivas = Horas Base + ((Incidencias Activas - 1) x (Horas Base x 0.5))
 * Si un ticket está cerrado o cancelado, el conteo de SLA se detiene.
 */
function calcularSlaTicket(array $fila): array {
    global $conn;
    $prioridad = $fila['prioridad'] ?? 'baja';
    $horasBase = obtenerHorasSlaPorPrioridad($prioridad);

    // Incidencias técnicas activas del ticket
    if (isset($fila['incidencias_activas'])) {
        $incidenciasActivas = intval($fila['incidencias_activas']);
    } elseif (!empty($fila['id']) && isset($conn) && ($conn instanceof mysqli)) {
        $incidenciasActivas = contarIncidenciasActivasTicket($conn, intval($fila['id']));
    } elseif (!empty($fila['ticket_id']) && isset($conn) && ($conn instanceof mysqli)) {
        $incidenciasActivas = contarIncidenciasActivasTicket($conn, intval($fila['ticket_id']));
    } else {
        $incidenciasActivas = 0;
    }

    $calculoSla = calcularHorasSlaEfectivas($horasBase, $incidenciasActivas);
    $horasSla = $calculoSla['horas_efectivas'];
    $horasProrroga = $calculoSla['horas_prorroga'];
    $tieneProrroga = $calculoSla['tiene_prorroga'];
    $segundosSlaTotal = intval(round($horasSla * 3600));

    // Sufijo visual de prórroga: (SLA +Xh por N incidencias)
    $prorrogaSuffix = '';
    if ($tieneProrroga) {
        $prorrogaTxt = formatearNumeroHorasSla($horasProrroga);
        $prorrogaSuffix = " (SLA +{$prorrogaTxt}h por {$incidenciasActivas} incidencias)";
    }

    $fechaAperturaStr = !empty($fila['fecha_apertura']) ? $fila['fecha_apertura'] : ($fila['created_at'] ?? null);
    $tsApertura = $fechaAperturaStr ? strtotime($fechaAperturaStr) : time();
    if (!$tsApertura) {
        $tsApertura = time();
    }

    $estado = class_exists('TicketEstado') ? TicketEstado::normalizar($fila['estado'] ?? 'Nuevo') : trim($fila['estado'] ?? 'Nuevo');
    $esCerrado = in_array(strtolower($estado), ['cerrado', 'resuelto', 'cancelado', 'inactivo'], true);

    if ($esCerrado && !empty($fila['fecha_cierre'])) {
        $tsFin = strtotime($fila['fecha_cierre']);
    } elseif ($esCerrado) {
        $tsFin = $tsApertura;
    } else {
        $tsFin = time();
    }

    $segundosTranscurridos = max(0, $tsFin - $tsApertura);
    $porcentajeConsumido = ($segundosSlaTotal > 0) ? ($segundosTranscurridos / $segundosSlaTotal) * 100 : 0;
    $segundosRestantes = max(0, $segundosSlaTotal - $segundosTranscurridos);

    $tsLimite = $tsApertura + $segundosSlaTotal;
    $fechaLimiteFormato = date('d/m/Y H:i', $tsLimite);

    $horasRest = floor($segundosRestantes / 3600);
    $minsRest = floor(($segundosRestantes % 3600) / 60);
    $formatoRestante = sprintf('%02d:%02d', $horasRest, $minsRest);

    if (strtolower($estado) === 'cancelado') {
        $estadoSla = 'cancelado';
        $etiqueta = '🚫 SLA Cancelado';
        $badgeClass = 'badge-sla-ok';
        $detalle = 'Ticket cancelado';
    } elseif ($segundosTranscurridos >= $segundosSlaTotal) {
        $estadoSla = 'vencido';
        $etiqueta = '🚨 SLA Incumplido' . $prorrogaSuffix;
        $badgeClass = 'badge-sla-vencido';
        $segExceso = $segundosTranscurridos - $segundosSlaTotal;
        $horasExc = floor($segExceso / 3600);
        $minsExc = floor(($segExceso % 3600) / 60);
        $formatoExceso = sprintf('%02d:%02d', $horasExc, $minsExc);
        $detalle = $esCerrado ? "Cerrado fuera de plazo" : "Vencido hace {$formatoExceso} h";
    } elseif ($porcentajeConsumido >= 70 && !$esCerrado) {
        $estadoSla = 'warning';
        $etiqueta = '⚠️ SLA Warning' . $prorrogaSuffix;
        $badgeClass = 'badge-sla-warning';
        $detalle = "Quedan {$formatoRestante} h";
    } else {
        $estadoSla = 'en_tiempo';
        $etiqueta = ($esCerrado ? '✅ Cumplido' : '⏱️ En Tiempo') . $prorrogaSuffix;
        $badgeClass = 'badge-sla-ok';
        $detalle = $esCerrado ? "Cerrado en tiempo" : "{$formatoRestante} h restantes";
    }

    return [
        'estado_sla' => $estadoSla,
        'etiqueta' => $etiqueta,
        'badge_class' => $badgeClass,
        'horas_sla' => $horasSla,
        'horas_base' => $calculoSla['horas_base'],
        'horas_prorroga' => $horasProrroga,
        'incidencias_activas' => $incidenciasActivas,
        'tiene_prorroga' => $tieneProrroga,
        'porcentaje' => round($porcentajeConsumido, 1),
        'segundos_restantes' => $segundosRestantes,
        'segundos_transcurridos' => $segundosTranscurridos,
        'fecha_limite' => $fechaLimiteFormato,
        'fecha_vencimiento' => date('Y-m-d H:i:s', $tsLimite),
        'detalle' => $detalle,
        'es_cerrado' => $esCerrado
    ];
}
}

if (!function_exists('sincronizarIncidenciaTecnicaAutomatica')) {
/**
 * Sincroniza e inyecta automáticamente la incidencia técnica en la tabla de incidencias
 * alertando al equipo de desarrollo cuando un ticket es tipificado como técnico.
 */
function sincronizarIncidenciaTecnicaAutomatica($conn, int $ticketId, $juegoId, $driverId, string $descripcion, string $titulo): ?int {
    $ticketId = intval($ticketId);
    if ($ticketId <= 0 || !$conn) {
        return null;
    }

    $juegoVal = (!empty($juegoId) && $juegoId !== "NULL") ? intval($juegoId) : "NULL";
    $driverVal = (!empty($driverId) && $driverId !== "NULL") ? intval($driverId) : "NULL";

    // Comprobar si ya existe una incidencia asociada a este ticket
    $checkQuery = mysqli_query($conn, "SELECT id FROM incidencias WHERE ticket_id = $ticketId LIMIT 1");
    if ($checkQuery && mysqli_num_rows($checkQuery) > 0) {
        $row = mysqli_fetch_assoc($checkQuery);
        $incId = intval($row['id']);
        mysqli_query($conn, "UPDATE incidencias SET juego_id = $juegoVal, driver_id = $driverVal WHERE id = $incId");
        return $incId;
    }

    // Inyectar automáticamente en incidencias
    $detalleEsc = mysqli_real_escape_string($conn, trim($descripcion));
    $insertQuery = "INSERT INTO incidencias (ticket_id, juego_id, driver_id, detalle, estado) 
                    VALUES ($ticketId, $juegoVal, $driverVal, '$detalleEsc', 'Reportada')";
    if (mysqli_query($conn, $insertQuery)) {
        $incId = mysqli_insert_id($conn);
        registrarHistorialTicket($conn, $ticketId, "Nueva incidencia técnica inyectada automáticamente: INC-" . str_pad($incId, 3, '0', STR_PAD_LEFT));
        // HU-43: recalcular SLA del ticket y registrar el log de prórroga si acumula > 1 incidencias activas
        if (function_exists('recalcularSlaTicket')) {
            recalcularSlaTicket($conn, $ticketId, $incId);
        }
        
        // Alertar al equipo de desarrollo
        if (function_exists('crearNotificacion')) {
            crearNotificacion($conn, 'desarrollo', 'Nueva Incidencia Técnica', $titulo, "incidentes.php?ver=$incId");
        }
        return $incId;
    }
    return null;
}
}

// ============================================================================
// DISPATCHER DE ACCIONES DEL CICLO DE VIDA
// ============================================================================
if (!empty($_POST["accion_ticket"])) {
    $accion = $_POST["accion_ticket"];
    $id = intval($_POST["ticket_id"] ?? 0);

    try {
        if ($id <= 0) {
            throw new Exception("ID de ticket no especificado o inválido.");
        }
        $ticketObj = new Ticket($id, $conn);

        switch ($accion) {
            case 'asignar':
                $idAgente = trim($_POST["idAgente"] ?? $_POST["asignado"] ?? "");
                if (empty($idAgente)) {
                    throw new TransicionTicketException("Debe seleccionar o ingresar el identificador del agente para asignar el ticket.");
                }
                $ticketObj->asignarTicket($idAgente);
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ ¡Ticket #TKT-' . str_pad($id, 3, "0", STR_PAD_LEFT) . ' asignado con éxito a: <strong>' . htmlspecialchars($idAgente) . '</strong>! (Estado: Asignado)
                </div>';
                break;

            case 'cancelar':
                $motivo = trim($_POST["motivo_cancelacion"] ?? "");
                $ticketObj->cancelarTicket($motivo);
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ Ticket #TKT-' . str_pad($id, 3, "0", STR_PAD_LEFT) . ' cancelado exitosamente. (Estado: Cancelado)
                </div>';
                break;

            case 'iniciar_atencion':
                $ticketObj->iniciarAtencion();
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ Atención técnica iniciada para el ticket #TKT-' . str_pad($id, 3, "0", STR_PAD_LEFT) . '. (Estado: En Progreso)
                </div>';
                break;

            case 'solicitar_datos':
                $mensaje = trim($_POST["mensaje_solicitud"] ?? "");
                $ticketObj->solicitarDatosUsuario($mensaje);
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ Solicitud de información enviada al usuario. El ticket pasó a estado <strong>En Espera</strong>.
                </div>';
                break;

            case 'respuesta_usuario':
                $respuesta = trim($_POST["mensaje_respuesta"] ?? "");
                $ticketObj->recibirRespuestaUsuario($respuesta);
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ Respuesta del usuario registrada. Ticket reactivado automáticamente a <strong>En Progreso</strong>.
                </div>';
                break;

            case 'resolver':
                $resolucion = trim($_POST["respuesta_resolucion"] ?? "");
                $ticketObj->resolverTicket($resolucion);
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ Ticket #TKT-' . str_pad($id, 3, "0", STR_PAD_LEFT) . ' marcado como <strong>Resuelto</strong> con dictamen técnico registrado.
                </div>';
                break;

            case 'cerrar':
                $ticketObj->cerrarTicket();
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ Ticket #TKT-' . str_pad($id, 3, "0", STR_PAD_LEFT) . ' confirmado y archivado exitosamente. (Estado: Cerrado)
                </div>';
                break;

            case 'reabrir':
                $motivo = trim($_POST["motivo_reapertura"] ?? "");
                $ticketObj->reabrirPorInconformidad($motivo);
                $_SESSION["msg"] = '<div class="toast-success">
                    ✅ Ticket #TKT-' . str_pad($id, 3, "0", STR_PAD_LEFT) . ' reabierto por inconformidad del usuario. Ha retornado a estado <strong>En Progreso</strong>.
                </div>';
                break;

            default:
                throw new Exception("Acción del ciclo de vida '$accion' no reconocida.");
        }
    } catch (TransicionTicketException $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
    } catch (Exception $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    redirigirTicket("ticketManegement.php");
}

// ============================================================================
// CREAR TICKET (Estado inicial estricto: Nuevo)
// ============================================================================
if (!empty($_POST["crear_ticket"])) {
    $titulo = normalizarTexto($_POST["titulo"] ?? "");
    $descripcion = normalizarTexto($_POST["descripcion"] ?? "");
    $categoria = normalizarTexto($_POST["categoria"] ?? "");
    $prioridad = normalizarTexto($_POST["prioridad"] ?? "");
    $usuario = normalizarTexto($_POST["usuario"] ?? "");
    $asignadoInput = trim($_POST["asignado"] ?? "");
    
    // Todo ticket se crea en estado 'Nuevo'
    $estadoInicial = TicketEstado::NUEVO;
    
    // Juego y Driver son permanentemente opcionales
    $tiene_juego = (!empty($_POST["juego_id"]) && intval($_POST["juego_id"]) > 0);
    $tiene_driver = (!empty($_POST["driver_id"]) && intval($_POST["driver_id"]) > 0);
    $juego_id = $tiene_juego ? intval($_POST["juego_id"]) : "NULL";
    $driver_id = $tiene_driver ? intval($_POST["driver_id"]) : "NULL";

    // Clasificación automática de incidencia técnica
    $es_incidencia = ($tiene_juego || $tiene_driver || esCategoriaTecnica($categoria)) ? 1 : 0;

    // Validar campos obligatorios de recepción
    $campos_validos = !empty($titulo) && !empty($descripcion) && !empty($categoria) &&
                      !empty($prioridad) && !empty($usuario);

    if ($campos_validos) {
        $tituloEsc = mysqli_real_escape_string($conn, $titulo);
        $descEsc = mysqli_real_escape_string($conn, $descripcion);
        $catEsc = mysqli_real_escape_string($conn, $categoria);
        $prioEsc = mysqli_real_escape_string($conn, $prioridad);
        $usrEsc = mysqli_real_escape_string($conn, $usuario);

        $horasSla = obtenerHorasSlaPorPrioridad($prioridad);

        $query = "
            INSERT INTO tickets
            (titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, es_incidencia, fecha_apertura, fecha_vencimiento, fecha_cierre)
            VALUES
            (
                '$tituloEsc',
                '$descEsc',
                '$catEsc',
                '$prioEsc',
                '$estadoInicial',
                '$usrEsc',
                NULL,
                $juego_id,
                $driver_id,
                $es_incidencia,
                NOW(),
                DATE_ADD(NOW(), INTERVAL $horasSla HOUR),
                NULL
            )
        ";

        if (mysqli_query($conn, $query)) {
            $nuevo_id = mysqli_insert_id($conn);
            if ($nuevo_id > 0) {
                registrarHistorialTicket($conn, $nuevo_id, "Ticket creado en estado '" . TicketEstado::NUEVO . "'");
                
                // Si es incidencia técnica, inyectar automáticamente en la grilla de incidencias y alertar a desarrollo
                if (!empty($es_incidencia)) {
                    sincronizarIncidenciaTecnicaAutomatica($conn, $nuevo_id, $juego_id, $driver_id, $descripcion, $titulo);
                }

                // Si el usuario especificó un agente al crear, ejecutar la acción oficial asignarTicket
                if (!empty($asignadoInput)) {
                    try {
                        $ticketObj = new Ticket($nuevo_id, $conn);
                        $ticketObj->asignarTicket($asignadoInput);
                    } catch (Exception $e) {
                        // Si falla la asignación, queda registrado en Nuevo
                    }
                }
            }

            $_SESSION["msg"] = '<div class="toast-success">
                ✅ ¡Ticket registrado exitosamente en estado Nuevo!
            </div>';
            unset($_SESSION["persisted_form"]);
            unset($_SESSION["persisted_form_type"]);
            redirigirTicket("ticketManegement.php");
        } else {
            $_SESSION["msg"] = '<div class="toast-error">
                ❌ Error al registrar el ticket: ' . mysqli_escape_string($conn, mysqli_error($conn)) . '
            </div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form_type"] = "crear_ticket";
            redirigirTicket("ticketManegement.php");
        }
    }
    $_SESSION["msg"]= '<div class="toast-error">
                        ❌ Error: No se pudo crear el ticket - Todos los campos requeridos son obligatorios.
                      </div>';
    $_SESSION["persisted_form"] = $_POST;
    $_SESSION["persisted_form_type"] = "crear_ticket";
    redirigirTicket("ticketManegement.php");
}

// ============================================================================
// EDITAR TICKET (Con validación de transiciones de estados)
// ============================================================================
if (!empty($_POST["editar_ticket"])) {
    $id = intval($_POST["editar_ticket"]);
    $estado_nuevo_input = trim($_POST["estado"] ?? "");

    if (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "monitoreo") {
        $_SESSION["msg"] = '<div class="toast-error">
            ❌ Error: Tu rol de Monitoreo no tiene permisos para editar tickets.
        </div>';
        redirigirTicket("ticketManegement.php");
    }

    try {
        if ($id <= 0) {
            throw new Exception("ID de ticket inválido.");
        }
        $ticketObj = new Ticket($id, $conn);
        $old_estado = $ticketObj->estado;
        $nuevo_estado = !empty($estado_nuevo_input) ? TicketEstado::normalizar($estado_nuevo_input) : $old_estado;

        // Validar transición entre estados mediante la FSM
        if ($old_estado !== $nuevo_estado) {
            TicketStateMachine::validarTransicion($old_estado, $nuevo_estado, $_POST);
        }

        // Si es desarrollo, solo actualiza estado o dictamen técnico
        if (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "desarrollo") {
            if ($old_estado !== $nuevo_estado) {
                _ejecutarTransicionPorNombre($ticketObj, $old_estado, $nuevo_estado, $_POST);
            }
            $_SESSION["msg"] = '<div class="toast-success">
                ✅ Estado del ticket actualizado exitosamente.
            </div>';
        } else {
            // Soporte o Administrador: edita datos generales y/o transiciona
            $titulo = normalizarTexto($_POST["titulo"] ?? "");
            $descripcion = normalizarTexto($_POST["descripcion"] ?? "");
            $categoria = normalizarTexto($_POST["categoria"] ?? "");
            $prioridad = normalizarTexto($_POST["prioridad"] ?? "");
            $usuario = normalizarTexto($_POST["usuario"] ?? "");
            $asignado = normalizarTexto($_POST["asignado"] ?? "");
            $respuesta_resolucion = trim($_POST["respuesta_resolucion"] ?? "");
            
            // Juego y Driver son permanentemente opcionales
            $tiene_juego = (!empty($_POST["juego_id"]) && intval($_POST["juego_id"]) > 0);
            $tiene_driver = (!empty($_POST["driver_id"]) && intval($_POST["driver_id"]) > 0);
            $juego_id = $tiene_juego ? intval($_POST["juego_id"]) : "NULL";
            $driver_id = $tiene_driver ? intval($_POST["driver_id"]) : "NULL";

            // Clasificación automática de incidencia técnica
            $es_incidencia = ($tiene_juego || $tiene_driver || esCategoriaTecnica($categoria)) ? 1 : 0;

            $campos_validos = !empty($titulo) && !empty($descripcion) && !empty($categoria) &&
                              !empty($prioridad) && !empty($usuario);

            if (!$campos_validos) {
                throw new Exception("No se pudo editar el ticket - Todos los campos requeridos son obligatorios.");
            }

            // Actualizar información base del ticket
            $tituloEsc = mysqli_real_escape_string($conn, $titulo);
            $descEsc = mysqli_real_escape_string($conn, $descripcion);
            $catEsc = mysqli_real_escape_string($conn, $categoria);
            $prioEsc = mysqli_real_escape_string($conn, $prioridad);
            $usrEsc = mysqli_real_escape_string($conn, $usuario);
            $asigEsc = mysqli_real_escape_string($conn, $asignado);
            $resEsc = mysqli_real_escape_string($conn, $respuesta_resolucion);

            $updateQuery = "
                UPDATE tickets SET
                    titulo = '$tituloEsc',
                    descripcion = '$descEsc',
                    categoria = '$catEsc',
                    prioridad = '$prioEsc',
                    usuario = '$usrEsc',
                    asignado = '$asigEsc',
                    juego_id = $juego_id,
                    driver_id = $driver_id,
                    es_incidencia = $es_incidencia,
                    fecha_vencimiento = DATE_ADD(fecha_apertura, INTERVAL " . obtenerHorasSlaPorPrioridad($prioridad) . " HOUR)
                    " . (!empty($respuesta_resolucion) ? ", respuesta_resolucion = '$resEsc'" : "") . "
                WHERE id = $id
            ";
            if (!mysqli_query($conn, $updateQuery)) {
                throw new Exception("Error al actualizar información base: " . mysqli_error($conn));
            }

            // Sincronizar o inyectar automáticamente en incidencias si el ticket está tipificado como técnico
            if (!empty($es_incidencia)) {
                sincronizarIncidenciaTecnicaAutomatica($conn, $id, $juego_id, $driver_id, $descripcion, $titulo);
            }

            // Si se solicitó un cambio de estado, ejecutar la transición formal con validaciones y acciones en cascada
            if ($old_estado !== $nuevo_estado) {
                _ejecutarTransicionPorNombre($ticketObj, $old_estado, $nuevo_estado, $_POST);
            } else {
                registrarHistorialTicket($conn, $id, "Ticket modificado (Prioridad: '" . ucfirst($prioridad) . "', Asignado: '" . $asignado . "')");
            }

            $_SESSION["msg"] = '<div class="toast-success">
                ✅ Ticket editado exitosamente.
            </div>';
        }
        unset($_SESSION["persisted_form"]);
        unset($_SESSION["persisted_form_type"]);
    } catch (TransicionTicketException $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
        $_SESSION["persisted_form"] = $_POST;
        $_SESSION["persisted_form"]["id"] = $id;
        $_SESSION["persisted_form_type"] = "editar_ticket";
    } catch (Exception $e) {
        $_SESSION["msg"] = '<div class="toast-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
        $_SESSION["persisted_form"] = $_POST;
        $_SESSION["persisted_form"]["id"] = $id;
        $_SESSION["persisted_form_type"] = "editar_ticket";
    }

    redirigirTicket("ticketManegement.php");
}

if (!function_exists('_ejecutarTransicionPorNombre')) {
    function _ejecutarTransicionPorNombre(Ticket $ticketObj, string $old_estado, string $nuevo_estado, array $datos = []) {
    switch ($nuevo_estado) {
        case TicketEstado::ASIGNADO:
            $agente = trim($datos["asignado"] ?? $datos["idAgente"] ?? "");
            $ticketObj->asignarTicket($agente);
            break;
        case TicketEstado::CANCELADO:
            $motivo = trim($datos["motivo_cancelacion"] ?? $datos["motivo"] ?? "");
            $ticketObj->cancelarTicket($motivo);
            break;
        case TicketEstado::EN_PROGRESO:
            if ($old_estado === TicketEstado::ASIGNADO) {
                $ticketObj->iniciarAtencion();
            } else if ($old_estado === TicketEstado::EN_ESPERA) {
                $ticketObj->recibirRespuestaUsuario(trim($datos["mensaje_respuesta"] ?? $datos["respuesta"] ?? ""));
            } else if ($old_estado === TicketEstado::RESUELTO) {
                $ticketObj->reabrirPorInconformidad(trim($datos["motivo_reapertura"] ?? $datos["motivo"] ?? ""));
            }
            break;
        case TicketEstado::EN_ESPERA:
            $ticketObj->solicitarDatosUsuario(trim($datos["mensaje_solicitud"] ?? $datos["mensaje"] ?? ""));
            break;
        case TicketEstado::RESUELTO:
            $ticketObj->resolverTicket(trim($datos["respuesta_resolucion"] ?? ""));
            break;
        case TicketEstado::CERRADO:
            $ticketObj->cerrarTicket();
            break;
    }
}
}

if (!function_exists('obtenerTickets')) {
function obtenerTickets($conn){
     $sql = "SELECT 
                 t.id, 
                 t.titulo, 
                 t.descripcion,
                 t.usuario,
                 t.categoria, 
                 t.prioridad, 
                 t.estado, 
                 t.asignado,
                 t.juego_id,
                 t.driver_id,
                 t.es_incidencia,
                 t.respuesta_resolucion,
                 t.fecha_apertura,
                 t.fecha_vencimiento,
                 t.fecha_cierre,
                 j.nombre AS juego_nombre,
                 d.nombre AS driver_nombre
             FROM tickets t
             LEFT JOIN juegos j ON t.juego_id = j.id
             LEFT JOIN drivers d ON t.driver_id = d.id
             WHERE 1=1 ";

    // FILTRO ESTADO
    if (!empty($_POST["estadoBuscar"]) && $_POST["estadoBuscar"] != "todas") {
        $estado = mysqli_real_escape_string($conn, trim($_POST["estadoBuscar"]));
        $sql .= " AND (t.estado = '$estado' OR LOWER(t.estado) = LOWER('$estado'))";
    }

    // FILTRO PRIORIDAD
    if (!empty($_POST["prioridadBuscar"]) && $_POST["prioridadBuscar"] != "todas") {
        $prioridad = mysqli_real_escape_string($conn, trim($_POST["prioridadBuscar"]));
        $sql .= " AND (t.prioridad = '$prioridad' OR LOWER(t.prioridad) = LOWER('$prioridad'))";
    }

    // BUSQUEDA
    if (!empty($_POST["inputBuscar"])) {
        $buscar = mysqli_real_escape_string($conn, trim($_POST["inputBuscar"]));
        $sql .= "
            AND (
                t.titulo LIKE '%$buscar%'
                OR t.descripcion LIKE '%$buscar%'
                OR t.id LIKE '%$buscar%'
                OR t.usuario LIKE '%$buscar%'
                OR t.asignado LIKE '%$buscar%'
            )
        ";
    }

    // FILTRO SLA
    $slaFiltro = $_POST["slaBuscar"] ?? $_GET["slaBuscar"] ?? "todas";
    if (!empty($slaFiltro) && $slaFiltro !== "todas") {
        $segundosSlaExpr = "CASE LOWER(t.prioridad)
            WHEN 'critica' THEN 14400
            WHEN 'alta' THEN 86400
            WHEN 'media' THEN 172800
            WHEN 'baja' THEN 345600
            ELSE 345600
        END";

        $segundosTranscurridosExpr = "TIMESTAMPDIFF(SECOND, t.fecha_apertura, 
            CASE 
                WHEN LOWER(t.estado) IN ('resuelto', 'cerrado') AND t.fecha_cierre IS NOT NULL THEN t.fecha_cierre
                WHEN LOWER(t.estado) IN ('resuelto', 'cerrado', 'cancelado') THEN t.fecha_apertura
                ELSE NOW()
            END
        )";

        if ($slaFiltro === "vencido") {
            $sql .= " AND LOWER(t.estado) NOT IN ('cancelado') AND ($segundosTranscurridosExpr >= $segundosSlaExpr)";
        } elseif ($slaFiltro === "warning") {
            $sql .= " AND ($segundosTranscurridosExpr >= ($segundosSlaExpr * 0.70) AND $segundosTranscurridosExpr < $segundosSlaExpr AND LOWER(t.estado) NOT IN ('cerrado', 'cancelado', 'resuelto'))";
        } elseif ($slaFiltro === "en_tiempo") {
            $sql .= " AND (LOWER(t.estado) NOT IN ('cancelado') AND $segundosTranscurridosExpr < ($segundosSlaExpr * 0.70))";
        }
    }

    $sql .= " ORDER BY id DESC";

    $query = mysqli_query($conn, $sql);
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}
}

if (!function_exists('eliminarTicket')) {
function eliminarTicket($conn){
    $idEliminar = intval($_POST["eliminar"]);
    
    // Si el ticket está en estado Nuevo, cancelarlo formalmente
    try {
        $ticketObj = new Ticket($idEliminar, $conn);
        if ($ticketObj->estado === TicketEstado::NUEVO) {
            $ticketObj->cancelarTicket("Cancelado mediante acción eliminar");
            $_SESSION["msg"] = '<div class="toast-success">✅ Ticket cancelado exitosamente.</div>';
            redirigirTicket("ticketManegement.php");
            return;
        }
    } catch (Exception $e) {
        // Proceder con cancelación de respaldo
    }

    $queryDelete = "
        UPDATE tickets 
        SET estado = '" . TicketEstado::CANCELADO . "'
        WHERE id = $idEliminar
    ";

    mysqli_query($conn, $queryDelete);
    registrarHistorialTicket($conn, $idEliminar, "Ticket cancelado");
    $_SESSION["msg"] = '<div class="toast-success">✅ Ticket cancelado exitosamente.</div>';

    redirigirTicket("ticketManegement.php");
}
}

if (!function_exists('queryStats')) {
function queryStats($conn){
    $queryStats = mysqli_query($conn, "
    SELECT 
        COUNT(id) AS total,
        SUM(CASE WHEN LOWER(estado) IN ('nuevo', 'abierto') THEN 1 ELSE 0 END) AS nuevos,
        SUM(CASE WHEN LOWER(estado) = 'asignado' THEN 1 ELSE 0 END) AS asignados,
        SUM(CASE WHEN LOWER(estado) = 'en progreso' THEN 1 ELSE 0 END) AS en_progreso,
        SUM(CASE WHEN LOWER(estado) = 'en espera' THEN 1 ELSE 0 END) AS en_espera,
        SUM(CASE WHEN LOWER(estado) = 'resuelto' THEN 1 ELSE 0 END) AS resueltos,
        SUM(CASE WHEN LOWER(estado) = 'cerrado' THEN 1 ELSE 0 END) AS cerrados,
        SUM(CASE WHEN LOWER(estado) IN ('cancelado', 'inactivo') THEN 1 ELSE 0 END) AS cancelados
    FROM tickets
");
 return mysqli_fetch_assoc($queryStats);
}
}

if (!function_exists('getPrioridadBadgeClass')) {
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
}

if (!function_exists('getEstadoBadgeClass')) {
function getEstadoBadgeClass($estado) {
    switch (strtolower(trim($estado))) {
        case 'nuevo':
        case 'abierto':
            return 'badge-nuevo';
        case 'asignado':
            return 'badge-asignado';
        case 'en progreso':
            return 'badge-en-progreso';
        case 'en espera':
            return 'badge-en-espera';
        case 'resuelto':
            return 'badge-resuelto';
        case 'cerrado':
            return 'badge-cerrado';
        case 'cancelado':
        case 'inactivo':
            return 'badge-cancelado';
        default:
            return '';
    }
}
}

if (!function_exists('createTicket')) {
function createTicket($values = null){
  global $conn;
  require_once("db.php");

  $is_desarrollo = (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "desarrollo");
  $is_monitoreo = (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] === "monitoreo");
  $disabled_attr = ($is_desarrollo || $is_monitoreo) ? 'disabled style="background-color: #1e293b !important; color: #94a3b8 !important; border: 1px solid #334155 !important; opacity: 0.6; cursor: not-allowed;"' : 'style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"';
  $disabled_state_attr = $is_monitoreo ? 'disabled style="background-color: #1e293b !important; color: #94a3b8 !important; border: 1px solid #334155 !important; opacity: 0.6; cursor: not-allowed;"' : 'style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"';
  
  $is_edit = (!is_null($values) && !empty($values["id"]));
  $is_creacion = !$is_edit;
  $ticket_id_formatted = $is_edit ? " #TKT-" . str_pad($values["id"], 3, "0", STR_PAD_LEFT) : "";
  $modal_title = "Editar Ticket" . $ticket_id_formatted;
  if ($is_creacion) {
      $modal_title = "Crear Nuevo Ticket";
  } else if ($is_monitoreo) {
      $modal_title = "Detalles del Ticket" . $ticket_id_formatted;
  }

  $cancel_btn_text = $is_monitoreo ? "Cerrar" : "Cancelar";

  // Catálogo de Juegos
  $juegos_options = [];
  $juegos_result = mysqli_query($conn, "SELECT id, nombre FROM juegos ORDER BY nombre ASC");
  if ($juegos_result) {
      $juegos_options = mysqli_fetch_all($juegos_result, MYSQLI_ASSOC);
  }

  // Catálogo de Drivers
  $drivers_options = [];
  $drivers_result = mysqli_query($conn, "SELECT id, nombre, version FROM drivers ORDER BY version DESC");
  if ($drivers_result) {
      $drivers_options = mysqli_fetch_all($drivers_result, MYSQLI_ASSOC);
  }

  // Lista de Agentes / Usuarios disponibles para asignación con métricas de carga en tiempo real (Least-Busy first)
  $usuarios_options = obtenerAgentesParaReasignacion($conn);

  // Build options for juegos
  $juegos_html = '<option value="">Selecciona un juego (Opcional)</option>';
  foreach ($juegos_options as $juego) {
      $selected = (isset($values["juego_id"]) && $values["juego_id"] == $juego["id"]) ? 'selected' : '';
      $juegos_html .= '<option value="' . htmlspecialchars($juego["id"]) . '" ' . $selected . '>' . htmlspecialchars($juego["nombre"]) . '</option>';
  }

  // Build options for drivers
  $drivers_html = '<option value="">Selecciona un driver (Opcional)</option>';
  foreach ($drivers_options as $driver) {
      $selected = (isset($values["driver_id"]) && $values["driver_id"] == $driver["id"]) ? 'selected' : '';
      $drivers_html .= '<option value="' . htmlspecialchars($driver["id"]) . '" ' . $selected . '>' . htmlspecialchars($driver["nombre"] . ' - ' . $driver["version"]) . '</option>';
  }

  // Build options for assigned agents (filtro por rol y ordenado por menor carga activa)
  $assigned_options_html = '<option value="">-- Sin asignar --</option>';
  $asignadoActual = $values["asignado"] ?? "";
  $usuarioEncontrado = false;
  foreach ($usuarios_options as $u) {
      $rolCanonico = strtolower(trim($u['rol'] ?? ''));
      if (empty($rolCanonico)) {
          if (intval($u['IdRol'] ?? 0) === 2) $rolCanonico = 'soporte';
          elseif (intval($u['IdRol'] ?? 0) === 3) $rolCanonico = 'desarrollo';
          elseif (intval($u['IdRol'] ?? 0) === 4) $rolCanonico = 'monitoreo';
          elseif (intval($u['IdRol'] ?? 0) === 1) $rolCanonico = 'administrador';
      }
      $activos = intval($u['tickets_activos'] ?? 0);
      $badgeCarga = ($activos === 0) ? '🟢 0 act. (Libre)' : (($activos <= 2) ? "🟡 {$activos} act." : "🔴 {$activos} act.");
      $selected = ($asignadoActual !== "" && ($asignadoActual === $u['usuario'] || $asignadoActual === $u['nombre_completo'])) ? 'selected' : '';
      if ($selected) {
          $usuarioEncontrado = true;
      }
      $nombreMostrar = (!empty($u['nombre_completo']) ? $u['nombre_completo'] : $u['usuario']) . " (" . ucfirst($rolCanonico) . ") — {$badgeCarga}";
      $assigned_options_html .= '<option value="' . htmlspecialchars($u['usuario']) . '" data-rol="' . htmlspecialchars($rolCanonico) . '" data-activos="' . $activos . '" ' . $selected . ' style="background-color: #0f172a; color: #ffffff;">' . htmlspecialchars($nombreMostrar) . '</option>';
  }
  if (!empty($asignadoActual) && !$usuarioEncontrado) {
      $assigned_options_html .= '<option value="' . htmlspecialchars($asignadoActual) . '" selected style="background-color: #0f172a; color: #ffffff;">' . htmlspecialchars($asignadoActual) . ' (Actual)</option>';
  }

  // Opciones de Prioridad
  $p_baja = (isset($values["prioridad"]) && $values["prioridad"] === "baja") ? "selected" : "";
  $p_media = (!isset($values["prioridad"]) || $values["prioridad"] === "media") ? "selected" : "";
  $p_alta = (isset($values["prioridad"]) && $values["prioridad"] === "alta") ? "selected" : "";
  $p_critica = (isset($values["prioridad"]) && $values["prioridad"] === "critica") ? "selected" : "";

  // Estado Actual del Ticket
  $currEstado = isset($values["estado"]) ? TicketEstado::normalizar($values["estado"]) : TicketEstado::NUEVO;
  $transicionesPermitidas = TicketStateMachine::obtenerTransicionesPermitidas($currEstado);

  // Construcción de opciones de estado para el select
  $estados_html = '';
  if (is_null($values)) {
      // Al crear, forzado a 'Nuevo'
      $estados_html = '<option value="' . TicketEstado::NUEVO . '" selected>Nuevo (Inicial)</option>';
  } else {
      // Estado actual
      $estados_html .= '<option value="' . htmlspecialchars($currEstado) . '" selected>' . htmlspecialchars($currEstado) . ' (Estado Actual)</option>';
      // Solo transiciones permitidas formalmente
      foreach ($transicionesPermitidas as $nextState => $accionNombre) {
          if ($nextState !== $currEstado) {
              $estados_html .= '<option value="' . htmlspecialchars($nextState) . '">➔ Pasar a ' . htmlspecialchars($nextState) . ' (' . htmlspecialchars($accionNombre) . ')</option>';
          }
      }
      if (empty($transicionesPermitidas)) {
          $estados_html .= '<option value="" disabled>Estado terminal (sin transiciones operativas)</option>';
      }
  }

  $button = "";
  if (!$is_monitoreo) {
      if (is_null($values)) {
          $button = '<button
              type="submit"
              class="modal-btn-guardar"
              name="crear_ticket"
              value="1"
          >
              <span>+</span> Crear Ticket
          </button>';
      } else { 
          $button = '<button
              type="submit"
              name="editar_ticket"
              value="' . htmlspecialchars($values["id"]) . '"
              class="modal-btn-guardar"
          >
              <span>💾</span> Guardar Cambios
          </button>'; 
      }
  }

  // Stepper Visual del Ciclo de Vida (solo en edición/detalles)
  $stepper_html = '';
  $panel_acciones_html = '';

  if (!is_null($values) && isset($values["id"])) {
      $tktId = intval($values["id"]);

      // Panel de Acciones de Transición con alto contraste y diseño nítido
      if (!$is_monitoreo) {
          $panel_acciones_html = '<div style="background-color: #1e293b !important; border: 1px solid #334155 !important; border-radius: 12px !important; padding: 14px 16px !important; margin-bottom: 20px !important; box-shadow: 0 4px 10px rgba(0,0,0,0.3) !important;">';
          $panel_acciones_html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #334155 !important;">
              <h4 style="color: #38bdf8 !important; font-size: 13px !important; font-weight: 700 !important; margin: 0; display: flex; align-items: center; gap: 6px; letter-spacing: 0.02em;">
                  <span>⚡</span> Acciones Rápidas del Estado
              </h4>
              <span class="badge-custom ' . getEstadoBadgeClass($currEstado) . '" style="font-size: 11px;">Estado: ' . htmlspecialchars($currEstado) . '</span>
          </div>';

          switch ($currEstado) {
              case TicketEstado::NUEVO:
                  $panel_acciones_html .= '
                  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
                      <!-- Acción asignarTicket -->
                      <form method="POST" style="background-color: #0f172a !important; border: 1px solid #0284c7 !important; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; margin: 0;">
                          <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                          <input type="hidden" name="accion_ticket" value="asignar" />
                          <div>
                              <label style="color: #7dd3fc !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 6px;">
                                  <span>👤</span> Asignar a Agente Técnico:
                              </label>
                              <p style="color: #cbd5e1 !important; font-size: 12px; margin: 4px 0 0 0;">Asigna el caso para iniciar la atención técnica.</p>
                          </div>
                          <div style="display: flex; gap: 8px; margin-top: 4px;">
                              <select name="idAgente" required style="flex: 1; background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important; border-radius: 6px; padding: 8px 10px; font-size: 12px; outline: none;">
                                  <option value="">Selecciona Agente...</option>';
                  $esTecnicoQuick = (!empty($values["juego_id"]) && !empty($values["driver_id"]));
                  $rolEsperadoQuick = $esTecnicoQuick ? 'desarrollo' : 'soporte';
                  foreach ($usuarios_options as $u) {
                      $rolU = strtolower(trim($u['rol'] ?? ''));
                      if (empty($rolU)) {
                          if (intval($u['IdRol'] ?? 0) === 2) $rolU = 'soporte';
                          elseif (intval($u['IdRol'] ?? 0) === 3) $rolU = 'desarrollo';
                          elseif (intval($u['IdRol'] ?? 0) === 1) $rolU = 'administrador';
                      }
                      if ($rolU !== $rolEsperadoQuick && $rolU !== 'administrador') {
                          continue;
                      }
                      $act = intval($u['tickets_activos'] ?? 0);
                      $badgeTxt = ($act === 0) ? '🟢 0 act. (Libre)' : (($act <= 2) ? "🟡 {$act} act." : "🔴 {$act} act.");
                      $panel_acciones_html .= '<option value="' . htmlspecialchars($u['usuario']) . '">' . htmlspecialchars($u['nombre_completo']) . ' — ' . $badgeTxt . '</option>';
                  }
                  $panel_acciones_html .= '
                              </select>
                              <button type="submit" class="modal-btn-action-info">
                                  <span>👤</span> Asignar
                              </button>
                          </div>
                      </form>

                      <!-- Acción cancelarTicket -->
                      <form method="POST" onsubmit="return confirm(\'¿Estás seguro de cancelar este ticket?\');" style="background-color: #0f172a !important; border: 1px solid #be123c !important; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; margin: 0;">
                          <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                          <input type="hidden" name="accion_ticket" value="cancelar" />
                          <div>
                              <label style="color: #fda4af !important; font-size: 13px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 6px;">
                                  <span>⛔</span> Cancelar Ticket:
                              </label>
                              <p style="color: #cbd5e1 !important; font-size: 12px; margin: 4px 0 0 0;">Cierra e invalida el incidente si ya no aplica.</p>
                          </div>
                          <div style="display: flex; gap: 8px; margin-top: 4px;">
                              <input type="text" name="motivo_cancelacion" placeholder="Motivo de cancelación (opcional)..." style="flex: 1; background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important; border-radius: 6px; padding: 8px 10px; font-size: 12px; outline: none;" />
                              <button type="submit" class="modal-btn-action-danger">
                                  <span>⛔</span> Cancelar
                              </button>
                          </div>
                      </form>
                  </div>';
                  break;

              case TicketEstado::ASIGNADO:
                  $asignadoText = htmlspecialchars($values["asignado"] ?? 'Sin asignar');
                  $panel_acciones_html .= '
                  <div style="background-color: #0f172a !important; border: 1px solid #0284c7 !important; border-radius: 8px; padding: 14px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap;">
                      <div style="font-size: 13px;">
                          <span style="color: #94a3b8 !important;">Ticket asignado a: </span><strong style="color: #38bdf8 !important; font-size: 14px;">' . $asignadoText . '</strong>
                          <p style="color: #cbd5e1 !important; font-size: 12px; margin: 4px 0 0 0;">Inicia la atención técnica para pasar el caso a estado <strong>En Progreso</strong>.</p>
                      </div>
                      <form method="POST" style="margin: 0;">
                          <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                          <input type="hidden" name="accion_ticket" value="iniciar_atencion" />
                          <button type="submit" class="modal-btn-action-primary">
                              <span>▶</span> Iniciar Atención Técnica
                          </button>
                      </form>
                  </div>';
                  break;

              case TicketEstado::EN_PROGRESO:
                  $panel_acciones_html .= '
                  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
                      <!-- Acción solicitarDatosUsuario (pasa a En Espera) -->
                      <form method="POST" style="background-color: #0f172a !important; border: 1px solid #d97706 !important; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; margin: 0;">
                          <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                          <input type="hidden" name="accion_ticket" value="solicitar_datos" />
                          <div>
                              <strong style="color: #fde68a !important; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                  <span>💬</span> Solicitar Datos al Usuario
                              </strong>
                              <p style="color: #cbd5e1 !important; font-size: 12px; margin: 0; line-height: 1.4;">Pasa el ticket a estado <strong>En Espera</strong> mientras responde el cliente.</p>
                          </div>
                          <div style="display: flex; gap: 8px; margin-top: 4px;">
                              <input type="text" name="mensaje_solicitud" placeholder="Información requerida (ej. DXDiag)..." style="flex: 1; background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #64748b !important; border-radius: 6px; padding: 8px 12px; font-size: 12px; outline: none;" />
                              <button type="submit" class="modal-btn-action-warning">
                                  <span>💬</span> Solicitar
                              </button>
                          </div>
                      </form>

                      <!-- Acción resolverTicket (pasa a Resuelto) -->
                      <form method="POST" style="background-color: #0f172a !important; border: 1px solid #0d9488 !important; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; margin: 0;">
                          <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                          <input type="hidden" name="accion_ticket" value="resolver" />
                          <div>
                              <strong style="color: #5eead4 !important; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                  <span>✔</span> Resolver Ticket
                              </strong>
                              <p style="color: #cbd5e1 !important; font-size: 12px; margin: 0; line-height: 1.4;">Requiere dictamen técnico obligatorio para pasar a <strong>Resuelto</strong>.</p>
                          </div>
                          <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px;">
                              <input type="text" name="respuesta_resolucion" required placeholder="Dictamen técnico de solución..." style="width: 100%; box-sizing: border-box; background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #64748b !important; border-radius: 6px; padding: 8px 12px; font-size: 12px; outline: none;" />
                              <button type="submit" class="modal-btn-action-teal" style="width: 100%;">
                                  <span>✔</span> Marcar como Resuelto
                              </button>
                          </div>
                      </form>
                  </div>';
                  break;

              case TicketEstado::EN_ESPERA:
                  $panel_acciones_html .= '
                  <div style="background-color: #0f172a !important; border: 1px solid #d97706 !important; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; gap: 10px;">
                   <div>
                       <strong style="color: #fde68a !important; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                           <span>⏳</span> Ticket En Espera de Datos del Usuario
                       </strong>
                       <p style="color: #cbd5e1 !important; font-size: 12px; margin: 4px 0 0 0;">Al ingresar la respuesta del cliente, el ticket se reactivará automáticamente a <strong>En Progreso</strong>.</p>
                   </div>
                   <form method="POST" style="display: flex; gap: 8px; margin: 0; width: 100%;">
                       <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                       <input type="hidden" name="accion_ticket" value="respuesta_usuario" />
                       <input type="text" name="mensaje_respuesta" placeholder="Mensaje o datos recibidos del cliente..." style="flex: 1; background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #64748b !important; border-radius: 6px; padding: 8px 12px; font-size: 12px; outline: none;" />
                       <button type="submit" class="modal-btn-action-info">
                           <span>↩</span> Registrar Respuesta
                       </button>
                   </form>
               </div>';
               break;

           case TicketEstado::RESUELTO:
               $resNotes = htmlspecialchars($values["respuesta_resolucion"] ?? 'Sin dictamen registrado');
               $panel_acciones_html .= '
               <div style="display: flex; flex-direction: column; gap: 12px;">
                   <div style="background-color: #0f172a !important; border: 1px solid #0d9488 !important; border-radius: 8px; padding: 12px 14px; font-size: 12px;">
                       <strong style="color: #5eead4 !important; display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-size: 13px;">
                           <span>📋</span> Dictamen Técnico Registrado:
                       </strong>
                       <p style="color: #ffffff !important; margin: 0; font-style: italic;">"' . $resNotes . '"</p>
                   </div>
                   <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
                       <!-- Acción cerrarTicket -->
                       <form method="POST" style="background-color: #0f172a !important; border: 1px solid #059669 !important; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; margin: 0;">
                           <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                           <input type="hidden" name="accion_ticket" value="cerrar" />
                           <div>
                               <strong style="color: #6ee7b7 !important; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                   <span>🔒</span> Confirmar y Cerrar
                               </strong>
                               <p style="color: #cbd5e1 !important; font-size: 12px; margin: 0;">Archiva y finaliza formalmente el ticket resuelto.</p>
                           </div>
                           <button type="submit" class="modal-btn-action-primary" style="width: 100%;">
                               <span>🔒</span> Cerrar Ticket Definitivamente
                           </button>
                       </form>

                       <!-- Acción reabrirPorInconformidad -->
                       <form method="POST" style="background-color: #0f172a !important; border: 1px solid #d97706 !important; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 10px; margin: 0;">
                           <input type="hidden" name="ticket_id" value="' . $tktId . '" />
                           <input type="hidden" name="accion_ticket" value="reabrir" />
                           <div>
                               <strong style="color: #fde68a !important; font-size: 13px; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                                   <span>🔁</span> Reabrir por Inconformidad
                               </strong>
                               <p style="color: #cbd5e1 !important; font-size: 12px; margin: 0;">Regresa a En Progreso si el cliente no está conforme.</p>
                           </div>
                           <div style="display: flex; gap: 8px; margin-top: 4px;">
                               <input type="text" name="motivo_reapertura" placeholder="Motivo de inconformidad..." style="flex: 1; background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #64748b !important; border-radius: 6px; padding: 8px 10px; font-size: 12px; outline: none;" />
                               <button type="submit" class="modal-btn-action-warning">
                                   <span>🔁</span> Reabrir
                               </button>
                           </div>
                       </form>
                   </div>
               </div>';
               break;

           case TicketEstado::CERRADO:
               $resNotes = htmlspecialchars($values["respuesta_resolucion"] ?? 'Sin dictamen');
               $panel_acciones_html .= '
               <div style="background-color: #0f172a !important; border: 1px solid #059669 !important; border-radius: 8px; padding: 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                   <div>
                       <strong style="color: #6ee7b7 !important; font-size: 13px;">✔ TICKET FINALIZADO Y CERRADO</strong>
                       <p style="color: #e2e8f0 !important; font-size: 12px; margin: 4px 0 0 0;">Resolución: "' . $resNotes . '"</p>
                   </div>
                   <span style="background-color: rgba(5, 150, 105, 0.25); color: #6ee7b7; border: 1px solid #059669; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px;">Archivado</span>
               </div>';
               break;

           case TicketEstado::CANCELADO:
               $panel_acciones_html .= '
               <div style="background-color: #0f172a !important; border: 1px solid #be123c !important; border-radius: 8px; padding: 14px; color: #fda4af !important; font-size: 13px;">
                   <strong>⛔ TICKET CANCELADO</strong> — No admite nuevas operaciones técnicas.
               </div>';
               break;
       }
       $panel_acciones_html .= '</div>';
   }
}

  echo '
    <div role="dialog" id="create-ticket-modal" aria-labelledby="create-ticket-title" aria-modal="true" data-ticket-id="' . ($values["id"] ?? "") . '" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 overflow-y-auto">
  <style>
    .modal-btn-close {
        background-color: #1e293b !important;
        color: #94a3b8 !important;
        border: 1px solid #475569 !important;
        border-radius: 8px !important;
        width: 36px !important;
        height: 36px !important;
        font-size: 18px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.2s ease !important;
    }
    .modal-btn-close:hover {
        background-color: #ef4444 !important;
        color: #ffffff !important;
        border-color: #ef4444 !important;
        transform: scale(1.08) !important;
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.4) !important;
    }
    .modal-btn-cancelar {
        background-color: #334155 !important;
        color: #ffffff !important;
        border: 1px solid #64748b !important;
        padding: 10px 24px !important;
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
    .modal-btn-cancelar:hover {
        background-color: #475569 !important;
        border-color: #94a3b8 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.4) !important;
    }
    .modal-btn-guardar {
        background-color: #059669 !important;
        color: #ffffff !important;
        border: 1px solid #10b981 !important;
        padding: 10px 24px !important;
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
    .modal-btn-guardar:hover {
        background-color: #047857 !important;
        border-color: #34d399 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 12px -2px rgba(5, 150, 105, 0.5) !important;
    }
    .modal-btn-action-primary {
        background-color: #059669 !important;
        color: #ffffff !important;
        border: 1px solid #10b981 !important;
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
        white-space: nowrap !important;
    }
    .modal-btn-action-primary:hover {
        background-color: #047857 !important;
        border-color: #34d399 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(5, 150, 105, 0.5) !important;
    }
    .modal-btn-action-warning {
        background-color: #d97706 !important;
        color: #ffffff !important;
        border: 1px solid #f59e0b !important;
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
        white-space: nowrap !important;
    }
    .modal-btn-action-warning:hover {
        background-color: #b45309 !important;
        border-color: #fbbf24 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(217, 119, 6, 0.5) !important;
    }
    .modal-btn-action-info {
        background-color: #0284c7 !important;
        color: #ffffff !important;
        border: 1px solid #38bdf8 !important;
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
        white-space: nowrap !important;
    }
    .modal-btn-action-info:hover {
        background-color: #0369a1 !important;
        border-color: #7dd3fc !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(2, 132, 199, 0.5) !important;
    }
    .modal-btn-action-danger {
        background-color: #be123c !important;
        color: #ffffff !important;
        border: 1px solid #f43f5e !important;
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
        white-space: nowrap !important;
    }
    .modal-btn-action-danger:hover {
        background-color: #9f1239 !important;
        border-color: #fda4af !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(190, 18, 60, 0.5) !important;
    }
    .modal-btn-action-teal {
        background-color: #0d9488 !important;
        color: #ffffff !important;
        border: 1px solid #14b8a6 !important;
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
        white-space: nowrap !important;
    }
    .modal-btn-action-teal:hover {
        background-color: #0f766e !important;
        border-color: #2dd4bf !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 10px rgba(13, 148, 136, 0.5) !important;
    }
  </style>
  <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl my-8 max-h-[92vh] overflow-y-auto" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">

    <!-- Header con botón cerrar visible -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #334155 !important;">
      <div>
        <h2
          id="create-ticket-title"
          style="color: #ffffff !important; font-size: 20px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;"
        >
          ' . $modal_title . '
        </h2>

        <p style="color: #94a3b8 !important; font-size: 13px; margin: 6px 0 0 0;">
          ' . (is_null($values) ? 'Completa la información requerida para registrar el ticket.' : 'Actualiza la información técnica y parámetros del ticket.') . '
        </p>
      </div>

      ' . (!$is_creacion ? '
      <!-- Botón X visible en la esquina superior derecha solo en edición -->
      <a
        href="ticketManegement.php"
        title="Cerrar modal"
        class="modal-btn-close"
      >
        ✕
      </a>' : '') . '
    </div>

    ' . $stepper_html . '
    ' . $panel_acciones_html . '

    <!-- Formulario Principal -->
<form class="space-y-4" method="POST">
  ' . (isset($values["id"]) && $values["id"] ? '<input type="hidden" name="ticket_id" value="' . htmlspecialchars($values["id"]) . '" />' : '') . '

  <!-- Título -->
  <div>
    <label for="title" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
      Título del Ticket
    </label>

    <input
      id="title"
      name="titulo"
      type="text"
      required
      placeholder="Asunto o resumen del incidente"
      value="' . htmlspecialchars($values["titulo"] ?? "") . '"
      class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
      style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
      ' . $disabled_attr . '
    />
  </div>

  <!-- Descripción -->
  <div>
    <label for="description" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
      Descripción Detallada
    </label>

    <textarea
      id="description"
      name="descripcion"
      rows="3"
      required
      placeholder="Detalles del problema o consulta reportada..."
      class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
      style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
      ' . $disabled_attr . '
    >' . htmlspecialchars($values["descripcion"] ?? "") . '</textarea>
  </div>

  <!-- Categoría + Prioridad -->
  <div class="grid gap-4 md:grid-cols-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">

    <div>
      <label for="category" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
        Categoría
      </label>

      <input
        id="category"
        value="' . htmlspecialchars($values["categoria"] ?? "") . '"
        name="categoria"
        type="text"
        required
        placeholder="Ej: Drivers, Gráficos, Cuentas"
        class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
        style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
        ' . $disabled_attr . '
      />
    </div>

    <div>
      <label for="priority" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
        Prioridad
      </label>

      <select
        id="priority"
        name="prioridad"
        class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
        style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
        ' . $disabled_attr . '
      >
        <option value="baja" ' . $p_baja . ' style="background-color: #0f172a; color: #ffffff;">Baja (96 h)</option>
        <option value="media" ' . $p_media . ' style="background-color: #0f172a; color: #ffffff;">Media (48 h)</option>
        <option value="alta" ' . $p_alta . ' style="background-color: #0f172a; color: #ffffff;">Alta (24 h)</option>
        <option value="critica" ' . $p_critica . ' style="background-color: #0f172a; color: #ffffff;">Crítica (4 h)</option>
      </select>
    </div>
  </div>

  <!-- Juego + Driver (Permanentemente habilitados y opcionales) -->
  <div class="grid gap-4 md:grid-cols-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
    <div>
      <label for="juego" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
        Juego Afectado
      </label>

      <select
        id="juego"
        name="juego_id"
        class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
        style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
        ' . $disabled_attr . '
      >
        ' . $juegos_html . '
      </select>
    </div>

    <div>
      <label for="driver" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
        Driver Asociado
      </label>

      <select
        id="driver"
        name="driver_id"
        class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
        style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
        ' . $disabled_attr . '
      >
        ' . $drivers_html . '
      </select>
    </div>
  </div>

  <!-- Usuario + Asignado -->
  <div class="grid gap-4 md:grid-cols-2" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">

    <div>
      <label for="user" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
        Usuario / Cliente
      </label>

      <input
        id="user"
        name="usuario"
        required
        value="' . htmlspecialchars($values["usuario"] ?? "") . '"
        type="text"
        placeholder="Nombre de usuario o reportero"
        class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
        style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
        ' . $disabled_attr . '
      />
    </div>

    <div>
      <label for="assigned" class="mb-1.5 block text-sm font-medium text-white flex items-center justify-between" style="color: #ffffff !important;">
        <span>Asignado a</span>
        <span id="badge-rol-filtro" class="text-[11px] font-semibold px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">Filtro Inteligente</span>
      </label>

      <select
        id="assigned"
        name="asignado"
        class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
        style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
        ' . $disabled_attr . '
      >
        ' . $assigned_options_html . '
      </select>
      <div id="assigned-helper-msg" class="text-xs mt-1 text-slate-400">
        ℹ️ Selecciona Juego y Driver para canalizar a Desarrollo, o déjalos vacíos para Soporte.
      </div>
    </div>
  </div>

  <!-- Estado del Ticket -->
  <div>
    <label for="estado_ticket_select" class="mb-1.5 block text-sm font-medium text-white" style="color: #ffffff !important;">
      Estado del Ticket
    </label>

    <select
      id="estado_ticket_select"
      name="estado"
      class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
      style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
      ' . $disabled_state_attr . '
    >
      ' . $estados_html . '
    </select>
  </div>

  ' . (!$is_creacion ? '
  <!-- Dictamen Técnico de Resolución (solo en edición/resolución) -->
  <div id="campo_resolucion_contenedor">
    <label for="respuesta_resolucion_input" class="mb-1.5 block text-sm font-medium text-white flex items-center justify-between" style="color: #ffffff !important;">
      <span>Dictamen Técnico de Resolución</span>
      <span class="text-xs text-amber-400 font-normal">* Obligatorio para estado Resuelto</span>
    </label>

    <textarea
      id="respuesta_resolucion_input"
      name="respuesta_resolucion"
      rows="2"
      placeholder="Describe los pasos y verificación técnica de la solución..."
      class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20"
      style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
      ' . $disabled_attr . '
    >' . htmlspecialchars($values["respuesta_resolucion"] ?? "") . '</textarea>
  </div>' : '') . '

  <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #334155 !important;">
    <!-- Botón Cerrar: enlace de navegación directa sin activar validación HTML5 de campos requeridos -->
    <a
      href="ticketManegement.php"
      id="btn-cerrar-modal"
      title="Cerrar ventana sin guardar cambios"
      class="modal-btn-cancelar"
    >
      <span>✕</span> Cerrar
    </a>

    ' . $button . '
  </div>
</form>

  <script>
  (function() {
    const selectJuego = document.getElementById("juego");
    const selectDriver = document.getElementById("driver");
    const selectAssigned = document.getElementById("assigned");
    const badgeFiltro = document.getElementById("badge-rol-filtro");
    const helperMsg = document.getElementById("assigned-helper-msg");

    if (!selectJuego || !selectDriver || !selectAssigned) return;

    function actualizarFiltroAgentes() {
      const tieneJuego = selectJuego.value && selectJuego.value.trim() !== "";
      const tieneDriver = selectDriver.value && selectDriver.value.trim() !== "";
      const esIncidenciaTecnica = Boolean(tieneJuego && tieneDriver);
      const rolDeseado = esIncidenciaTecnica ? "desarrollo" : "soporte";

      const valorActual = selectAssigned.value;
      let opcionPreviaValida = false;
      let primeraOpcionValida = null;

      if (badgeFiltro) {
        if (esIncidenciaTecnica) {
          badgeFiltro.textContent = "🛠️ Rol: Desarrollo (Técnico)";
          badgeFiltro.className = "text-[11px] font-semibold px-2 py-0.5 rounded bg-indigo-900/60 text-indigo-300 border border-indigo-700/50";
        } else {
          badgeFiltro.textContent = "🎧 Rol: Soporte (Atención)";
          badgeFiltro.className = "text-[11px] font-semibold px-2 py-0.5 rounded bg-emerald-900/60 text-emerald-300 border border-emerald-700/50";
        }
      }

      if (helperMsg) {
        if (esIncidenciaTecnica) {
          helperMsg.textContent = "✓ Juego y Driver seleccionados: Canalizado a Desarrollo (ordenados por menor carga activa).";
          helperMsg.className = "text-xs mt-1 text-cyan-300 font-medium";
        } else {
          helperMsg.textContent = "ℹ️ Sin Juego o Driver: Canalizado a Soporte (ordenados por menor carga activa).";
          helperMsg.className = "text-xs mt-1 text-slate-400";
        }
      }

      const options = Array.from(selectAssigned.querySelectorAll("option"));
      options.forEach(opt => {
        const rolOpt = opt.getAttribute("data-rol");
        if (!rolOpt || opt.value === "") {
          opt.hidden = false;
          opt.disabled = false;
          return;
        }
        const coincide = (rolOpt === rolDeseado || rolOpt === "administrador");
        if (coincide) {
          opt.hidden = false;
          opt.disabled = false;
          if (!primeraOpcionValida) {
            primeraOpcionValida = opt.value;
          }
          if (opt.value === valorActual) {
            opcionPreviaValida = true;
          }
        } else {
          opt.hidden = true;
          opt.disabled = true;
        }
      });

      if (!opcionPreviaValida && valorActual !== "" && selectAssigned.getAttribute("data-manual") !== "1") {
        if (primeraOpcionValida) {
          selectAssigned.value = primeraOpcionValida;
        }
      }
    }

    selectJuego.addEventListener("change", actualizarFiltroAgentes);
    selectDriver.addEventListener("change", actualizarFiltroAgentes);
    selectAssigned.addEventListener("change", function() {
      selectAssigned.setAttribute("data-manual", "1");
    });

    actualizarFiltroAgentes();
  })();
  </script>
  </div>
</div> ';
}
}

if (!function_exists('calcularTiempo')) {
function calcularTiempo($conn){
    $sql = "
        SELECT
            ROUND(AVG(TIMESTAMPDIFF(HOUR, fecha_apertura, fecha_cierre)), 2) AS promedio_horas,
            MAX(TIMESTAMPDIFF(HOUR, fecha_apertura, fecha_cierre)) AS maximo_horas
        FROM tickets
        WHERE fecha_cierre IS NOT NULL
    ";

    $resultado = mysqli_query($conn, $sql);
    $metricas = mysqli_fetch_assoc($resultado);

    return [$metricas, $resultado];
}
}

// ============================================================================
// FUNCIONES DE MONITOREO SLA Y REDISTRIBUCIÓN RÁPIDA DE TICKETS
// ============================================================================



if (!function_exists('obtenerMetricasSla')) {
/**
 * Retorna el resumen cuantitativo en tiempo real de SLAs (Vencidos, Warning, En Tiempo).
 */
function obtenerMetricasSla($conn): array {
    $metricas = [
        'vencidos' => 0,
        'warning' => 0,
        'en_tiempo' => 0,
        'total' => 0
    ];
    if (!$conn) {
        return $metricas;
    }
    $sql = "SELECT id, prioridad, estado, fecha_apertura, fecha_cierre, fecha_vencimiento FROM tickets WHERE LOWER(estado) NOT IN ('cancelado', 'inactivo')";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $sla = calcularSlaTicket($row);
            $estadoSla = $sla['estado_sla'];
            if (isset($metricas[$estadoSla])) {
                $metricas[$estadoSla]++;
            }
            $metricas['total']++;
        }
    }
    return $metricas;
}
}

if (!function_exists('obtenerAgentesParaReasignacion')) {
/**
 * Retorna la lista de usuarios y agentes técnicos disponibles para reasignación rápida
 * junto con su rol formal y contador de carga de tickets activos (Least-Busy first).
 */
function obtenerAgentesParaReasignacion($conn): array {
    $agentes = [];
    if (!$conn) {
        return $agentes;
    }
    $sql = "SELECT u.id, u.usuario, u.nombre_completo, u.departamento, r.nombre AS rol, u.IdRol,
            (SELECT COUNT(*) FROM tickets t 
             WHERE (t.asignado COLLATE utf8mb4_general_ci = u.usuario COLLATE utf8mb4_general_ci 
                 OR t.asignado COLLATE utf8mb4_general_ci = u.nombre_completo COLLATE utf8mb4_general_ci) 
               AND LOWER(t.estado) NOT IN ('cerrado', 'cancelado', 'inactivo')) AS tickets_activos
            FROM usuarios u
            LEFT JOIN roles r ON u.IdRol = r.id
            WHERE r.nombre IN ('soporte', 'desarrollo', 'monitoreo', 'administrador') OR u.IdRol IN (1, 2, 3, 4)
            ORDER BY tickets_activos ASC, u.nombre_completo ASC, u.usuario ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $agentes[] = $row;
        }
    }
    return $agentes;
}
}

if (!function_exists('reasignarAgente')) {
/**
 * Wrapper procedural para reasignar el agente de un ticket.
 */
function reasignarAgente($ticketIdOrConn, $idAgenteOrTicketId = null, $posibleIdAgente = null, $motivo = null): bool {
    if (class_exists('Ticket')) {
        if ($ticketIdOrConn instanceof mysqli) {
            $ticket = new Ticket(intval($idAgenteOrTicketId), $ticketIdOrConn);
            return $ticket->reasignarAgente(strval($posibleIdAgente), $motivo !== null ? strval($motivo) : null);
        } else {
            global $conn;
            $ticket = new Ticket(intval($ticketIdOrConn), $conn);
            return $ticket->reasignarAgente(strval($idAgenteOrTicketId), $posibleIdAgente !== null ? strval($posibleIdAgente) : null);
        }
    }
    return false;
}
}



?>