<?php
/**
 * Ciclo de Vida y Transiciones de Estados del Ticket
 * 
 * Implementa la máquina de estados finita (FSM) para la gestión de tickets
 * con los 7 estados y las reglas de transición formalmente definidas:
 * 
 * 1. Estados Oficiales:
 *    - Nuevo
 *    - Asignado
 *    - En Progreso
 *    - En Espera
 *    - Resuelto
 *    - Cerrado
 *    - Cancelado
 * 
 * 2. Transiciones Permitidas:
 *    - Desde 'Nuevo':
 *        -> asignarTicket(idAgente) => 'Asignado'
 *        -> cancelarTicket([motivo]) => 'Cancelado'
 *    - Desde 'Asignado':
 *        -> iniciarAtencion() => 'En Progreso'
 *    - Desde 'En Progreso':
 *        -> solicitarDatosUsuario([mensaje]) => 'En Espera'
 *        -> resolverTicket(respuesta_resolucion) => 'Resuelto' (requiere respuesta_resolucion)
 *    - Desde 'En Espera':
 *        -> recibirRespuestaUsuario([mensaje]) => 'En Progreso' (reactivación automática)
 *    - Desde 'Resuelto':
 *        -> cerrarTicket() => 'Cerrado' (confirmado/archivado)
 *        -> reabrirPorInconformidad([motivo]) => 'En Progreso'
 *    - 'Cerrado' y 'Cancelado': Estados terminales.
 */

require_once(__DIR__ . "/../db.php");
require_once(__DIR__ . "/generalFuncs.php");
require_once(__DIR__ . "/feedbackFunc.php");

class TicketEstado {
    const NUEVO       = 'Nuevo';
    const ASIGNADO    = 'Asignado';
    const EN_PROGRESO = 'En Progreso';
    const EN_ESPERA   = 'En Espera';
    const RESUELTO    = 'Resuelto';
    const CERRADO     = 'Cerrado';
    const CANCELADO   = 'Cancelado';

    /**
     * Retorna la lista exhaustiva de los 7 estados oficiales.
     */
    public static function todos(): array {
        return [
            self::NUEVO,
            self::ASIGNADO,
            self::EN_PROGRESO,
            self::EN_ESPERA,
            self::RESUELTO,
            self::CERRADO,
            self::CANCELADO
        ];
    }

    /**
     * Normaliza un texto de estado a su forma canónica oficial.
     */
    public static function normalizar(?string $estado): string {
        if ($estado === null) {
            return self::NUEVO;
        }
        $limpio = trim(strtolower($estado));
        $limpio = preg_replace('/\s+/', ' ', $limpio);

        switch ($limpio) {
            case 'nuevo':
            case 'abierto': // compatibilidad hacia atrás
                return self::NUEVO;
            case 'asignado':
                return self::ASIGNADO;
            case 'en progreso':
            case 'en_progreso':
            case 'en-progreso':
            case 'progreso':
                return self::EN_PROGRESO;
            case 'en espera':
            case 'en_espera':
            case 'en-espera':
            case 'espera':
            case 'pendiente usuario':
                return self::EN_ESPERA;
            case 'resuelto':
                return self::RESUELTO;
            case 'cerrado':
                return self::CERRADO;
            case 'cancelado':
            case 'inactivo': // compatibilidad hacia atrás
                return self::CANCELADO;
            default:
                // Si no coincide con ninguno, retorna el valor limpio capitalizado
                return ucfirst($limpio);
        }
    }

    /**
     * Valida si un estado pertenece a los 7 estados oficiales.
     */
    public static function esValido(string $estado): bool {
        $canonico = self::normalizar($estado);
        return in_array($canonico, self::todos(), true);
    }
}

/**
 * Excepción específica para violaciones en la máquina de estados.
 */
class TransicionTicketException extends Exception {}

/**
 * Gestor de la Máquina de Estados del Ticket
 */
class TicketStateMachine {
    
    /**
     * Obtiene el mapa de transiciones permitidas según el estado actual.
     */
    public static function obtenerTransicionesPermitidas(string $estadoActual): array {
        $estadoActual = TicketEstado::normalizar($estadoActual);

        switch ($estadoActual) {
            case TicketEstado::NUEVO:
                return [
                    TicketEstado::ASIGNADO  => 'asignarTicket',
                    TicketEstado::CANCELADO => 'cancelarTicket'
                ];

            case TicketEstado::ASIGNADO:
                return [
                    TicketEstado::EN_PROGRESO => 'iniciarAtencion'
                ];

            case TicketEstado::EN_PROGRESO:
                return [
                    TicketEstado::EN_ESPERA => 'solicitarDatosUsuario',
                    TicketEstado::RESUELTO  => 'resolverTicket'
                ];

            case TicketEstado::EN_ESPERA:
                return [
                    TicketEstado::EN_PROGRESO => 'recibirRespuestaUsuario'
                ];

            case TicketEstado::RESUELTO:
                return [
                    TicketEstado::CERRADO     => 'cerrarTicket',
                    TicketEstado::EN_PROGRESO => 'reabrirPorInconformidad'
                ];

            case TicketEstado::CERRADO:
            case TicketEstado::CANCELADO:
            default:
                return [];
        }
    }

    /**
     * Valida una transición entre estados y verifica las reglas de negocio específicas.
     * 
     * @throws TransicionTicketException si la transición no es válida.
     */
    public static function validarTransicion(
        string $estadoActual, 
        string $nuevoEstado, 
        array $datos = []
    ): bool {
        $estadoActual = TicketEstado::normalizar($estadoActual);
        $nuevoEstado = TicketEstado::normalizar($nuevoEstado);

        // 1. Validar que el nuevo estado sea uno de los 7 oficiales
        if (!TicketEstado::esValido($nuevoEstado)) {
            throw new TransicionTicketException(
                "Estado inválido: '$nuevoEstado'. El sistema solo contempla los 7 estados oficiales: Nuevo, Asignado, En Progreso, En Espera, Resuelto, Cerrado y Cancelado."
            );
        }

        // Si el estado no cambia, se considera operación no transicional válida
        if ($estadoActual === $nuevoEstado) {
            return true;
        }

        // 2. Validar que el estado actual no sea terminal
        if ($estadoActual === TicketEstado::CERRADO) {
            throw new TransicionTicketException(
                "Transición no permitida: Un ticket en estado Cerrado ya está finalizado/archivado y no admite nuevas transiciones."
            );
        }

        if ($estadoActual === TicketEstado::CANCELADO) {
            throw new TransicionTicketException(
                "Transición no permitida: Un ticket en estado Cancelado no admite modificaciones de estado."
            );
        }

        $permitidas = self::obtenerTransicionesPermitidas($estadoActual);

        // 3. Validaciones específicas por estado de origen
        if (!array_key_exists($nuevoEstado, $permitidas)) {
            switch ($estadoActual) {
                case TicketEstado::NUEVO:
                    throw new TransicionTicketException(
                        "Transición no permitida: Desde el estado Nuevo, solo se permiten las acciones asignarTicket (pasa a Asignado) o cancelarTicket (pasa a Cancelado)."
                    );
                case TicketEstado::ASIGNADO:
                    throw new TransicionTicketException(
                        "Transición no permitida: Desde el estado Asignado, solo se permite la acción iniciarAtencion (pasa a En Progreso)."
                    );
                case TicketEstado::EN_PROGRESO:
                    throw new TransicionTicketException(
                        "Transición no permitida: Desde el estado En Progreso, solo se permite solicitarDatosUsuario (pasa a En Espera) o resolver el ticket (pasa a Resuelto)."
                    );
                case TicketEstado::EN_ESPERA:
                    throw new TransicionTicketException(
                        "Transición no permitida: Un ticket en En Espera solo puede reactivarse a En Progreso tras recibir la respuesta del usuario."
                    );
                case TicketEstado::RESUELTO:
                    throw new TransicionTicketException(
                        "Transición no permitida: Un ticket en Resuelto solo puede transicionar a Cerrado (archivado/confirmado) o retornar a En Progreso si se ejecuta reabrirPorInconformidad()."
                    );
                default:
                    throw new TransicionTicketException(
                        "Transición no permitida desde '$estadoActual' hacia '$nuevoEstado'."
                    );
            }
        }

        // 4. Regla: Al asignar desde Nuevo, debe indicarse un agente válido
        if ($estadoActual === TicketEstado::NUEVO && $nuevoEstado === TicketEstado::ASIGNADO) {
            $idAgente = trim($datos['idAgente'] ?? $datos['asignado'] ?? '');
            if (empty($idAgente)) {
                throw new TransicionTicketException(
                    "Para transicionar a Asignado es obligatorio especificar el agente asignado (idAgente)."
                );
            }
        }

        // 5. Regla: Para pasar a Resuelto, es obligatorio completar el campo de texto de resolución técnica (respuesta_resolucion)
        if ($nuevoEstado === TicketEstado::RESUELTO) {
            $resolucion = trim($datos['respuesta_resolucion'] ?? '');
            if (empty($resolucion)) {
                throw new TransicionTicketException(
                    "Para pasar a Resuelto, es obligatorio completar el campo de texto de resolución técnica (respuesta_resolucion)."
                );
            }
        }

        return true;
    }
}

/**
 * Clase de Entidad y Controlador de Dominio Ticket
 */
class Ticket {
    public int $id;
    public string $titulo;
    public string $descripcion;
    public string $categoria;
    public string $prioridad;
    public string $estado;
    public ?string $usuario;
    public ?string $asignado;
    public ?int $juego_id;
    public ?int $driver_id;
    public ?string $fecha_apertura;
    public ?string $fecha_vencimiento;
    public ?string $fecha_cierre;
    public int $es_incidencia;
    public ?string $respuesta_resolucion;

    protected $conn;

    public function __construct(int $id, $conn = null) {
        global $conn;
        $this->conn = $conn;
        $this->id = $id;
        $this->cargar();
    }

    public static function find(int $id, $conn = null): ?self {
        try {
            $ticket = new self($id, $conn);
            return $ticket;
        } catch (Exception $e) {
            return null;
        }
    }

    public function cargar(): void {
        $id = $this->id;
        $query = mysqli_query($this->conn, "SELECT * FROM tickets WHERE id = $id");
        if (!$query || mysqli_num_rows($query) === 0) {
            throw new Exception("Ticket con ID $id no encontrado.");
        }
        $row = mysqli_fetch_assoc($query);
        $this->titulo = $row['titulo'] ?? '';
        $this->descripcion = $row['descripcion'] ?? '';
        $this->categoria = $row['categoria'] ?? '';
        $this->prioridad = $row['prioridad'] ?? '';
        $this->estado = TicketEstado::normalizar($row['estado'] ?? TicketEstado::NUEVO);
        $this->usuario = $row['usuario'] ?? null;
        $this->asignado = $row['asignado'] ?? null;
        $this->juego_id = isset($row['juego_id']) ? intval($row['juego_id']) : null;
        $this->driver_id = isset($row['driver_id']) ? intval($row['driver_id']) : null;
        $this->fecha_apertura = $row['fecha_apertura'] ?? null;
        $this->fecha_vencimiento = $row['fecha_vencimiento'] ?? null;
        $this->fecha_cierre = $row['fecha_cierre'] ?? null;
        $this->es_incidencia = intval($row['es_incidencia'] ?? 0);
        $this->respuesta_resolucion = $row['respuesta_resolucion'] ?? null;
    }

    /**
     * Desde el estado Nuevo:
     * - asignarTicket(idAgente) (pasa a Asignado)
     */
    public function asignarTicket(string $idAgente): bool {
        $idAgente = trim($idAgente);
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::ASIGNADO, ['idAgente' => $idAgente]);

        $id = $this->id;
        $agenteEsc = mysqli_real_escape_string($this->conn, $idAgente);
        $estadoNuevo = TicketEstado::ASIGNADO;

        $query = "UPDATE tickets SET estado = '$estadoNuevo', asignado = '$agenteEsc' WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->estado = $estadoNuevo;
            $this->asignado = $idAgente;
            registrarHistorialTicket($this->conn, $id, "Ticket asignado al agente '{$idAgente}' (Estado: '" . TicketEstado::ASIGNADO . "')");
            crearNotificacion($this->conn, 'soporte', 'Ticket Asignado', "El ticket \"{$this->titulo}\" fue asignado a {$idAgente}.", "ticketManegement.php?ver_historial={$id}");
            return true;
        }
        throw new Exception("Error al actualizar la asignación del ticket en base de datos: " . mysqli_error($this->conn));
    }

    /**
     * Desde el estado Nuevo:
     * - cancelarTicket() (pasa a Cancelado)
     */
    public function cancelarTicket(?string $motivo = null): bool {
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::CANCELADO);

        $id = $this->id;
        $estadoNuevo = TicketEstado::CANCELADO;

        $query = "UPDATE tickets SET estado = '$estadoNuevo', fecha_cierre = CURRENT_TIMESTAMP WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->estado = $estadoNuevo;
            $this->fecha_cierre = date("Y-m-d H:i:s");
            $detalle = $motivo ? " Motivo: " . trim($motivo) : "";
            registrarHistorialTicket($this->conn, $id, "Ticket cancelado.{$detalle} (Estado: '" . TicketEstado::CANCELADO . "')");
            crearNotificacion($this->conn, 'soporte', 'Ticket Cancelado', "El ticket \"{$this->titulo}\" ha sido cancelado.", "ticketManegement.php?ver_historial={$id}");
            return true;
        }
        throw new Exception("Error al cancelar el ticket en base de datos: " . mysqli_error($this->conn));
    }

    /**
     * Transiciona el ticket de Asignado a En Progreso.
     */
    public function iniciarAtencion(): bool {
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::EN_PROGRESO);

        $id = $this->id;
        $estadoNuevo = TicketEstado::EN_PROGRESO;

        $query = "UPDATE tickets SET estado = '$estadoNuevo' WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->estado = $estadoNuevo;
            registrarHistorialTicket($this->conn, $id, "Atención técnica iniciada por el agente (Estado: '" . TicketEstado::EN_PROGRESO . "')");
            crearNotificacion($this->conn, 'soporte', 'Atención Iniciada', "El ticket \"{$this->titulo}\" ha pasado a En Progreso.", "ticketManegement.php?ver_historial={$id}");
            return true;
        }
        throw new Exception("Error al iniciar atención del ticket: " . mysqli_error($this->conn));
    }

    /**
     * Transiciona el ticket de En Progreso a En Espera.
     */
    public function solicitarDatosUsuario(?string $mensaje = null): bool {
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::EN_ESPERA);

        $id = $this->id;
        $estadoNuevo = TicketEstado::EN_ESPERA;

        $query = "UPDATE tickets SET estado = '$estadoNuevo' WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->estado = $estadoNuevo;
            $detalle = $mensaje ? " Mensaje: " . trim($mensaje) : "";
            registrarHistorialTicket($this->conn, $id, "Solicitud de información adicional al usuario.{$detalle} (Estado: '" . TicketEstado::EN_ESPERA . "')");
            crearNotificacion($this->conn, 'soporte', 'En Espera de Usuario', "El ticket \"{$this->titulo}\" requiere información del usuario para continuar.", "ticketManegement.php?ver_historial={$id}");
            return true;
        }
        throw new Exception("Error al solicitar datos al usuario: " . mysqli_error($this->conn));
    }

    /**
     * La recepción de respuesta por parte del usuario reactiva automáticamente a En Progreso.
     */
    public function recibirRespuestaUsuario(?string $respuesta = null): bool {
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::EN_PROGRESO);

        $id = $this->id;
        $estadoNuevo = TicketEstado::EN_PROGRESO;

        $query = "UPDATE tickets SET estado = '$estadoNuevo' WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->estado = $estadoNuevo;
            $detalle = $respuesta ? " Contenido: \"" . trim($respuesta) . "\"" : "";
            registrarHistorialTicket($this->conn, $id, "Recepción de respuesta del usuario. Ticket reactivado automáticamente.{$detalle} (Estado: '" . TicketEstado::EN_PROGRESO . "')");
            crearNotificacion($this->conn, 'soporte', 'Usuario Respondió', "El usuario respondió al ticket \"{$this->titulo}\". Caso reactivado automáticamente a En Progreso.", "ticketManegement.php?ver_historial={$id}");
            return true;
        }
        throw new Exception("Error al reactivar ticket tras respuesta de usuario: " . mysqli_error($this->conn));
    }

    /**
     * Para pasar a Resuelto, es obligatorio completar el dictamen de resolución técnica.
     */
    public function resolverTicket(string $respuesta_resolucion): bool {
        $resolucionTrim = trim($respuesta_resolucion);
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::RESUELTO, ['respuesta_resolucion' => $resolucionTrim]);

        // Validar regla de integridad de negocio: no resolver si tiene incidencias técnicas hijas abiertas/activas
        $qOpenInc = mysqli_query($this->conn, "SELECT COUNT(*) AS abiertas FROM incidencias WHERE ticket_id = {$this->id} AND estado IN ('Reportada', 'EnInvestigacion', 'Mitigada', 'abierto', 'en progreso')");
        if ($qOpenInc && $rowInc = mysqli_fetch_assoc($qOpenInc)) {
            if (intval($rowInc['abiertas']) > 0) {
                throw new TransicionTicketException("No se puede resolver el ticket porque tiene " . $rowInc['abiertas'] . " incidencia(s) técnica(s) pendiente(s) de resolución.");
            }
        }

        $id = $this->id;
        // Preservar dictámenes técnicos acumulados previamente en respuesta_resolucion sin pisarlos
        $qCurr = mysqli_query($this->conn, "SELECT respuesta_resolucion FROM tickets WHERE id = $id");
        $rowCurr = $qCurr ? mysqli_fetch_assoc($qCurr) : null;
        $resolucionActual = trim((string)($rowCurr['respuesta_resolucion'] ?? $this->respuesta_resolucion ?? ''));

        if ($resolucionActual !== '' && strpos($resolucionTrim, $resolucionActual) === false) {
            $resolucionFinal = $resolucionActual . "\n" . $resolucionTrim;
        } else {
            $resolucionFinal = $resolucionTrim;
        }

        $resEsc = mysqli_real_escape_string($this->conn, $resolucionFinal);
        $estadoNuevo = TicketEstado::RESUELTO;

        $query = "UPDATE tickets SET estado = '$estadoNuevo', respuesta_resolucion = '$resEsc', fecha_cierre = IFNULL(fecha_cierre, CURRENT_TIMESTAMP) WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->estado = $estadoNuevo;
            $this->respuesta_resolucion = $resolucionFinal;
            registrarHistorialTicket($this->conn, $id, "Ticket resuelto con dictamen técnico: \"{$resolucionFinal}\" (Estado: '" . TicketEstado::RESUELTO . "')");
            crearNotificacion($this->conn, 'soporte', 'Ticket Resuelto', "El ticket \"{$this->titulo}\" fue marcado como Resuelto con dictamen técnico.", "ticketManegement.php?ver_historial={$id}");

            // HU-44 Criterio 2: Al resolverse o cerrarse un ticket mediante registrarEvaluacionCalidad(idTicket), la evaluación se crea en estado inicial PendienteEnvio
            if (function_exists('registrarEvaluacionCalidad')) {
                registrarEvaluacionCalidad($this->conn, $id);
            }

            return true;
        }
        throw new Exception("Error al resolver el ticket: " . mysqli_error($this->conn));
    }

    /**
     * Transiciona de Resuelto a Cerrado (confirmado/archivado).
     */
    public function cerrarTicket(): bool {
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::CERRADO);

        // HU-42: Bloquear paso a Resuelto o Cerrado si el ticket tiene incidencias vinculadas en Reportada, EnInvestigacion o Mitigada
        $qOpenInc = mysqli_query($this->conn, "SELECT COUNT(*) AS abiertas FROM incidencias WHERE ticket_id = {$this->id} AND estado IN ('Reportada', 'EnInvestigacion', 'Mitigada', 'abierto', 'en progreso')");
        if ($qOpenInc && $rowInc = mysqli_fetch_assoc($qOpenInc)) {
            if (intval($rowInc['abiertas']) > 0) {
                throw new TransicionTicketException("No se puede cerrar el ticket porque tiene " . $rowInc['abiertas'] . " incidencia(s) técnica(s) pendiente(s) de resolución (en Reportada, EnInvestigacion o Mitigada).");
            }
        }

        $id = $this->id;
        $estadoNuevo = TicketEstado::CERRADO;

        mysqli_begin_transaction($this->conn);
        try {
            $query = "UPDATE tickets SET estado = '$estadoNuevo', fecha_cierre = CURRENT_TIMESTAMP WHERE id = $id";
            if (!mysqli_query($this->conn, $query)) {
                throw new Exception("Error al cerrar ticket: " . mysqli_error($this->conn));
            }

            // Propagar cierre a incidencias hijas si no estaban en estado terminal
            mysqli_query($this->conn, "UPDATE incidencias SET estado = 'Corregida', fecha_cierre = CURRENT_TIMESTAMP WHERE ticket_id = $id AND estado IN ('Reportada', 'EnInvestigacion', 'Mitigada', 'abierto', 'en progreso')");

            // HU-44 Criterio 2: Al resolverse o cerrarse un ticket mediante registrarEvaluacionCalidad(idTicket), la evaluación se crea en estado inicial PendienteEnvio
            if (function_exists('registrarEvaluacionCalidad')) {
                registrarEvaluacionCalidad($this->conn, $id);
            }

            registrarHistorialTicket($this->conn, $id, "Ticket confirmado y archivado permanentemente (Estado: '" . TicketEstado::CERRADO . "')");
            crearNotificacion($this->conn, 'soporte', 'Ticket Cerrado', "El ticket \"{$this->titulo}\" ha sido confirmado y cerrado.", "ticketManegement.php?ver_historial={$id}");

            mysqli_commit($this->conn);
            $this->estado = $estadoNuevo;
            $this->fecha_cierre = date("Y-m-d H:i:s");
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    /**
     * Retorna de Resuelto a En Progreso si el usuario reabre por inconformidad.
     */
    public function reabrirPorInconformidad(?string $motivo = null): bool {
        TicketStateMachine::validarTransicion($this->estado, TicketEstado::EN_PROGRESO);

        $id = $this->id;
        $estadoNuevo = TicketEstado::EN_PROGRESO;

        $query = "UPDATE tickets SET estado = '$estadoNuevo', fecha_cierre = NULL WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->estado = $estadoNuevo;
            $this->fecha_cierre = null;

            // HU-44 Criterio 4: Si el ticket se reabre antes de que el usuario responda (cancelarPorTicketReabierto()), la evaluación pasa inmediatamente al estado final Cancelada
            if (function_exists('cancelarPorTicketReabierto')) {
                cancelarPorTicketReabierto($this->conn, $id);
            }

            $detalle = $motivo ? " Motivo: \"" . trim($motivo) . "\"" : "";
            registrarHistorialTicket($this->conn, $id, "Ticket reabierto por inconformidad del cliente.{$detalle} (Estado: '" . TicketEstado::EN_PROGRESO . "')");
            crearNotificacion($this->conn, 'soporte', 'Ticket Reabierto por Inconformidad', "El ticket \"{$this->titulo}\" fue reabierto por inconformidad y regresa a En Progreso.", "ticketManegement.php?ver_historial={$id}");
            return true;
        }
        throw new Exception("Error al reabrir ticket por inconformidad: " . mysqli_error($this->conn));
    }
    /**
     * Reporte de SLA Warning y Redistribución Rápida de Tickets.
     * Reasigna de forma inmediata un ticket activo a un nuevo agente para evitar infracciones de servicio (SLA).
     * 
     * Reglas operativas:
     * - Si el ticket está en 'Nuevo', transiciona formalmente a 'Asignado' con el agente indicado.
     * - Si está en 'Asignado', 'En Progreso' o 'En Espera', actualiza el agente asignado manteniendo el estado
     *   operativo actual para continuidad del servicio.
     * - Los estados terminales ('Cerrado', 'Cancelado') no admiten redistribución.
     * - Registra la traza de auditoría en historial_tickets y genera la notificación respectiva.
     */
    public function reasignarAgente(string $idAgente, ?string $motivo = null): bool {
        $idAgente = trim($idAgente);
        if (empty($idAgente)) {
            throw new TransicionTicketException("Debe seleccionar un agente válido para la reasignación.");
        }

        if ($this->estado === TicketEstado::CERRADO || $this->estado === TicketEstado::CANCELADO) {
            throw new TransicionTicketException("No se puede redistribuir un ticket en estado terminal ({$this->estado}).");
        }

        $id = $this->id;
        $agenteAnterior = !empty($this->asignado) ? $this->asignado : 'Sin asignar';
        $agenteEsc = mysqli_real_escape_string($this->conn, $idAgente);

        // Si estaba Nuevo, pasa formalmente a Asignado
        $nuevoEstado = ($this->estado === TicketEstado::NUEVO) ? TicketEstado::ASIGNADO : $this->estado;
        $estadoEsc = mysqli_real_escape_string($this->conn, $nuevoEstado);

        $query = "UPDATE tickets SET asignado = '$agenteEsc', estado = '$estadoEsc' WHERE id = $id";
        if (mysqli_query($this->conn, $query)) {
            $this->asignado = $idAgente;
            $this->estado = $nuevoEstado;

            $detalleMotivo = $motivo ? " - Motivo: " . trim($motivo) : " - Prevención de infracción SLA";
            $notaHistorial = "HU-39 Redistribución SLA: Ticket reasignado de '{$agenteAnterior}' a '{$idAgente}'{$detalleMotivo} (Estado: '{$nuevoEstado}')";
            registrarHistorialTicket($this->conn, $id, $notaHistorial);
            crearNotificacion($this->conn, 'soporte', 'Ticket Reasignado (SLA)', "El ticket \"{$this->titulo}\" fue reasignado a {$idAgente} para evitar desvío de SLA.", "ticketManegement.php?ver_historial={$id}");
            return true;
        }

        throw new Exception("Error al reasignar el ticket en base de datos: " . mysqli_error($this->conn));
    }

}

// ============================================================================
// FUNCIONES PROCEDURALES EXPUESTAS
// Permiten llamar a las acciones con sintaxis directa tanto ($id, $args) como ($conn, $id, $args)
// ============================================================================

/**
 * Normaliza los argumentos comunes ($ticketIdOrConn, $arg1, $arg2)
 */
function _resolverTicketInstancia($ticketIdOrConn, $posibleId = null): Ticket {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $conexion = $ticketIdOrConn;
        $id = intval($posibleId);
    } else {
        $conexion = $conn;
        $id = intval($ticketIdOrConn);
    }
    return new Ticket($id, $conexion);
}

/**
 * asignarTicket(idAgente)
 */
function asignarTicket($ticketIdOrConn, $idAgenteOrTicketId = null, $posibleIdAgente = null): bool {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $ticket = new Ticket(intval($idAgenteOrTicketId), $ticketIdOrConn);
        $agente = strval($posibleIdAgente);
    } else {
        $ticket = new Ticket(intval($ticketIdOrConn), $conn);
        $agente = strval($idAgenteOrTicketId);
    }
    return $ticket->asignarTicket($agente);
}

/**
 * cancelarTicket()
 */
function cancelarTicket($ticketIdOrConn, $motivoOrTicketId = null, $posibleMotivo = null): bool {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $ticket = new Ticket(intval($motivoOrTicketId), $ticketIdOrConn);
        $motivo = $posibleMotivo !== null ? strval($posibleMotivo) : null;
    } else {
        $ticket = new Ticket(intval($ticketIdOrConn), $conn);
        $motivo = $motivoOrTicketId !== null ? strval($motivoOrTicketId) : null;
    }
    return $ticket->cancelarTicket($motivo);
}

/**
 * iniciarAtencion()
 */
function iniciarAtencion($ticketIdOrConn, $posibleId = null): bool {
    $ticket = _resolverTicketInstancia($ticketIdOrConn, $posibleId);
    return $ticket->iniciarAtencion();
}

/**
 * solicitarDatosUsuario()
 */
function solicitarDatosUsuario($ticketIdOrConn, $mensajeOrTicketId = null, $posibleMensaje = null): bool {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $ticket = new Ticket(intval($mensajeOrTicketId), $ticketIdOrConn);
        $mensaje = $posibleMensaje !== null ? strval($posibleMensaje) : null;
    } else {
        $ticket = new Ticket(intval($ticketIdOrConn), $conn);
        $mensaje = $mensajeOrTicketId !== null ? strval($mensajeOrTicketId) : null;
    }
    return $ticket->solicitarDatosUsuario($mensaje);
}

/**
 * recibirRespuestaUsuario()
 */
function recibirRespuestaUsuario($ticketIdOrConn, $respuestaOrTicketId = null, $posibleRespuesta = null): bool {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $ticket = new Ticket(intval($respuestaOrTicketId), $ticketIdOrConn);
        $respuesta = $posibleRespuesta !== null ? strval($posibleRespuesta) : null;
    } else {
        $ticket = new Ticket(intval($ticketIdOrConn), $conn);
        $respuesta = $respuestaOrTicketId !== null ? strval($respuestaOrTicketId) : null;
    }
    return $ticket->recibirRespuestaUsuario($respuesta);
}

/**
 * resolverTicket(respuesta_resolucion)
 */
function resolverTicket($ticketIdOrConn, $resolucionOrTicketId = null, $posibleResolucion = null): bool {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $ticket = new Ticket(intval($resolucionOrTicketId), $ticketIdOrConn);
        $resolucion = strval($posibleResolucion);
    } else {
        $ticket = new Ticket(intval($ticketIdOrConn), $conn);
        $resolucion = strval($resolucionOrTicketId);
    }
    return $ticket->resolverTicket($resolucion);
}

/**
 * cerrarTicket()
 */
function cerrarTicket($ticketIdOrConn, $posibleId = null): bool {
    $ticket = _resolverTicketInstancia($ticketIdOrConn, $posibleId);
    return $ticket->cerrarTicket();
}

/**
 * reabrirPorInconformidad()
 */
function reabrirPorInconformidad($ticketIdOrConn, $motivoOrTicketId = null, $posibleMotivo = null): bool {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $ticket = new Ticket(intval($motivoOrTicketId), $ticketIdOrConn);
        $motivo = $posibleMotivo !== null ? strval($posibleMotivo) : null;
    } else {
        $ticket = new Ticket(intval($ticketIdOrConn), $conn);
        $motivo = $motivoOrTicketId !== null ? strval($motivoOrTicketId) : null;
    }
    return $ticket->reabrirPorInconformidad($motivo);
}


/**
 * Procedural wrapper para reasignarAgente
 */
function reasignarAgente($ticketIdOrConn, $idAgenteOrTicketId = null, $posibleIdAgente = null, $motivo = null): bool {
    global $conn;
    if ($ticketIdOrConn instanceof mysqli) {
        $ticket = new Ticket(intval($idAgenteOrTicketId), $ticketIdOrConn);
        $agente = strval($posibleIdAgente);
        $motivoFinal = $motivo !== null ? strval($motivo) : null;
    } else {
        $ticket = new Ticket(intval($ticketIdOrConn), $conn);
        $agente = strval($idAgenteOrTicketId);
        $motivoFinal = $posibleIdAgente !== null ? strval($posibleIdAgente) : ($motivo !== null ? strval($motivo) : null);
    }
    return $ticket->reasignarAgente($agente, $motivoFinal);
}

