<?php
require_once(dirname(__DIR__) . "/db.php");
require_once(__DIR__ . "/generalFuncs.php");

function normalizarTextoFeedback($texto) {
    $texto = trim((string)($texto ?? ''));
    $texto = preg_replace('/\s+/', ' ', $texto);
    return $texto;
}

if (!function_exists('redirigirFeedback')) {
    function redirigirFeedback($url) {
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

// Inicialización automática de la base de datos
function inicializarBaseDeDatos($conn) {
    // 1. Asegurar la columna es_incidencia en la tabla tickets
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM `tickets` LIKE 'es_incidencia'");
    if (mysqli_num_rows($checkColumn) == 0) {
        mysqli_query($conn, "ALTER TABLE `tickets` ADD COLUMN `es_incidencia` TINYINT(1) DEFAULT 0");
        mysqli_query($conn, "UPDATE `tickets` SET `es_incidencia` = 1 WHERE `juego_id` IS NOT NULL AND `driver_id` IS NOT NULL");
    }

    // 1.2. Asegurar la columna respuesta_resolucion en la tabla tickets
    $checkRespuestaCol = mysqli_query($conn, "SHOW COLUMNS FROM `tickets` LIKE 'respuesta_resolucion'");
    if (mysqli_num_rows($checkRespuestaCol) == 0) {
        mysqli_query($conn, "ALTER TABLE `tickets` ADD COLUMN `respuesta_resolucion` TEXT DEFAULT NULL");
    }

    // 1.5. Asegurar llave primaria en juegos si no la tiene
    $checkJuegosPK = mysqli_query($conn, "SHOW KEYS FROM `juegos` WHERE Key_name = 'PRIMARY'");
    if (mysqli_num_rows($checkJuegosPK) == 0) {
        mysqli_query($conn, "ALTER TABLE `juegos` ADD PRIMARY KEY (`id`)");
        mysqli_query($conn, "ALTER TABLE `juegos` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
    }

    // 2. Asegurar la tabla incidencias
    mysqli_query($conn, "ALTER TABLE `incidencias` MODIFY `juego_id` INT(11) NULL DEFAULT NULL, MODIFY `driver_id` INT(11) NULL DEFAULT NULL");
    $checkIncidenciasTable = mysqli_query($conn, "SHOW TABLES LIKE 'incidencias'");
    if (mysqli_num_rows($checkIncidenciasTable) == 0) {
        $createIncidenciasQuery = "
            CREATE TABLE `incidencias` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `ticket_id` int(11) NOT NULL,
              `juego_id` int(11) NOT NULL,
              `driver_id` int(11) NOT NULL,
              `detalle` text NOT NULL,
              `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `ticket_id` (`ticket_id`),
              KEY `juego_id` (`juego_id`),
              KEY `driver_id` (`driver_id`),
              CONSTRAINT `incidencias_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
              CONSTRAINT `incidencias_ibfk_2` FOREIGN KEY (`juego_id`) REFERENCES `juegos` (`id`) ON DELETE CASCADE,
              CONSTRAINT `incidencias_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=5;
        ";
        mysqli_query($conn, $createIncidenciasQuery);
        
        // Copiar relaciones de tickets existentes a la tabla incidencias
        $migrateSeedQuery = "
            INSERT INTO `incidencias` (`id`, `ticket_id`, `juego_id`, `driver_id`, `detalle`)
            SELECT `id`, `id`, `juego_id`, `driver_id`, CONCAT('Incidencia inicial: ', `descripcion`)
            FROM `tickets`
            WHERE `juego_id` IS NOT NULL AND `driver_id` IS NOT NULL
            ON DUPLICATE KEY UPDATE `ticket_id` = VALUES(`ticket_id`)
        ";
        mysqli_query($conn, $migrateSeedQuery);
    }

    // 3. Verificar si la tabla de feedbacks ya existe
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'feedbacks'");
    if (mysqli_num_rows($checkTable) == 0) {
        // Desactivar temporalmente revisión de claves foráneas
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

        // Insertar tickets semilla si no existen (para que las claves foráneas coincidan)
        mysqli_query($conn, "INSERT IGNORE INTO `tickets` (`id`, `titulo`, `descripcion`, `categoria`, `prioridad`, `estado`, `usuario`, `asignado`, `juego_id`, `driver_id`, `es_incidencia`, `fecha_apertura`, `fecha_cierre`) VALUES
        (36, 'pantallazo azul al cargar el anti-cheat', 'El juego experimenta un pantallazo azul de forma aleatoria al cargar el sistema anti-cheat.', 'drivers', 'baja', 'abierto', 'gamer_pro', 'desarrollo', 6, 1, 1, '2026-05-28 21:00:00', NULL),
        (37, 'el launcher detecta el driver como obsoleto por error', 'El lanzador del juego indica de manera errónea que la versión del controlador de video es antigua.', 'drivers', 'alta', 'cerrado', 'gamer_pro', 'desarrollo', 3, 1, 1, '2026-05-28 21:30:00', '2026-05-28 22:30:00'),
        (38, 'bsod nvlddmkm.sys durante la compilación de shaders', 'Pantalla azul con código de error nvlddmkm.sys al iniciar la etapa de precompilación de shaders del motor gráfico.', 'drivers', 'critica', 'cerrado', 'gamer_pro', 'desarrollo', 4, 2, 1, '2026-05-28 22:00:00', '2026-05-28 23:00:00'),
        (39, 'prueba', 'problema en matchmaking, al momento de seleccionar el boton de buscar partida el juego realiza un crasheo al escritorio', 'prueba', 'media', 'cerrado', 'gamer_pro', 'desarrollo', 2, 6, 1, '2026-06-25 23:14:12', '2026-06-25 23:14:53')");

        // Crear la tabla feedbacks
        $createTableQuery = "
            CREATE TABLE `feedbacks` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `ticket_id` int(11) DEFAULT NULL,
              `usuario` varchar(100) NOT NULL,
              `tipo` varchar(50) NOT NULL,
              `tema` varchar(255) NOT NULL,
              `sentimiento` varchar(50) NOT NULL,
              `estado` varchar(50) DEFAULT 'Nuevo',
              `comentario` text DEFAULT NULL,
              `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `ticket_id` (`ticket_id`),
              CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=102;
        ";
        mysqli_query($conn, $createTableQuery);

        // Insertar feedbacks semilla dinámicamente desde los tickets cerrados
        $seedQuery = "
            INSERT INTO `feedbacks` 
            (`ticket_id`, `usuario`, `tipo`, `tema`, `sentimiento`, `estado`, `comentario`, `fecha`)
            SELECT 
                id,
                usuario,
                CASE 
                    WHEN prioridad IN ('alta', 'critica') THEN 'queja'
                    ELSE 'sugerencia'
                END AS tipo,
                'Soporte Técnico',
                CASE 
                    WHEN prioridad IN ('alta', 'critica') THEN 'negativo'
                    ELSE 'positivo' 
                END AS sentimiento,
                'Nuevo',
                CONCAT('Feedback autogenerado para: ', titulo),
                fecha_cierre
            FROM `tickets`
            WHERE estado = 'cerrado'
        ";
        mysqli_query($conn, $seedQuery);

        // Reactivar revisión de claves foráneas
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
    } else {
        // Migrar tipos y sentimientos antiguos en la base de datos de pruebas si ya existían
        mysqli_query($conn, "UPDATE feedbacks SET tipo = 'Queja' WHERE tipo = 'Bug Report'");
        mysqli_query($conn, "UPDATE feedbacks SET tipo = 'Elogio' WHERE tipo = 'CSAT'");
        mysqli_query($conn, "UPDATE feedbacks SET sentimiento = 'Positivo' WHERE sentimiento = 'Neutral'");
    }

    // 3.1 HU-44: Asegurar columnas de ciclo de vida de calidad en tabla feedbacks
    $colsHU44 = [
        'fecha_envio' => "ALTER TABLE `feedbacks` ADD COLUMN `fecha_envio` TIMESTAMP NULL DEFAULT NULL AFTER `fecha`",
        'fecha_respuesta' => "ALTER TABLE `feedbacks` ADD COLUMN `fecha_respuesta` TIMESTAMP NULL DEFAULT NULL AFTER `fecha_envio`",
        'fecha_expiracion' => "ALTER TABLE `feedbacks` ADD COLUMN `fecha_expiracion` TIMESTAMP NULL DEFAULT NULL AFTER `fecha_respuesta`",
        'calificacion' => "ALTER TABLE `feedbacks` ADD COLUMN `calificacion` INT(11) NULL DEFAULT NULL AFTER `sentimiento`",
        'sla_cumplido' => "ALTER TABLE `feedbacks` ADD COLUMN `sla_cumplido` TINYINT(1) NULL DEFAULT NULL AFTER `calificacion`",
        'motivo_cancelacion' => "ALTER TABLE `feedbacks` ADD COLUMN `motivo_cancelacion` VARCHAR(255) NULL DEFAULT NULL AFTER `comentario`"
    ];
    foreach ($colsHU44 as $cName => $sqlAdd) {
        $chkCol = mysqli_query($conn, "SHOW COLUMNS FROM `feedbacks` LIKE '$cName'");
        if ($chkCol && mysqli_num_rows($chkCol) == 0) {
            mysqli_query($conn, $sqlAdd);
        }
    }
    // Asegurar default de estado para HU-44
    mysqli_query($conn, "ALTER TABLE `feedbacks` MODIFY COLUMN `estado` VARCHAR(50) DEFAULT 'PendienteEnvio'");

    // Migración de estados legacy a los 6 estados de HU-44
    mysqli_query($conn, "UPDATE `feedbacks` SET `estado` = 'PendienteEnvio' WHERE `estado` = 'Pendiente de Usuario'");
    mysqli_query($conn, "UPDATE `feedbacks` SET `estado` = 'PendienteEnvio' WHERE `estado` = 'Nuevo' AND (`comentario` IS NULL OR `comentario` = '')");
    mysqli_query($conn, "UPDATE `feedbacks` SET `estado` = 'Completada' WHERE `estado` = 'Nuevo' AND (`comentario` IS NOT NULL AND `comentario` != '')");
    mysqli_query($conn, "UPDATE `feedbacks` SET `estado` = 'Procesada' WHERE `estado` IN ('Revisado', 'Implementado')");
    mysqli_query($conn, "UPDATE `feedbacks` SET `estado` = 'Cancelada' WHERE `estado` = 'inactivo'");


    // 4. Asegurar la tabla qa_auditorias con columnas de rúbrica ponderada y error crítico
    $checkQATable = mysqli_query($conn, "SHOW TABLES LIKE 'qa_auditorias'");
    if (mysqli_num_rows($checkQATable) == 0) {
        $createQATableQuery = "
            CREATE TABLE `qa_auditorias` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `ticket_id` int(11) DEFAULT NULL,
              `auditor` varchar(100) NOT NULL,
              `agente_evaluado` varchar(100) NOT NULL,
              `categoria` varchar(100) NOT NULL,
              `score_diagnostico` int(11) NOT NULL DEFAULT 100,
              `score_procesos` int(11) NOT NULL DEFAULT 100,
              `score_redaccion` int(11) NOT NULL DEFAULT 100,
              `score_eficiencia` int(11) NOT NULL DEFAULT 100,
              `error_critico` tinyint(1) NOT NULL DEFAULT 0,
              `puntaje` int(11) NOT NULL,
              `resultado` varchar(50) NOT NULL,
              `observaciones` text DEFAULT NULL,
              `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `ticket_id` (`ticket_id`),
              CONSTRAINT `qa_auditorias_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=101;
        ";
        mysqli_query($conn, $createQATableQuery);

        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");
            mysqli_query($conn, "INSERT IGNORE INTO `qa_auditorias` (`id`, `ticket_id`, `auditor`, `agente_evaluado`, `categoria`, `score_diagnostico`, `score_procesos`, `score_redaccion`, `score_eficiencia`, `error_critico`, `puntaje`, `resultado`, `observaciones`, `fecha`) VALUES
        (101, 37, 'SLA Lead', 'soporte_t1', 'Evaluación Rúbrica Ponderada', 100, 90, 90, 100, 0, 95, 'Conforme', 'Respuesta clara y diagnóstico preciso del problema de launcher.', '2026-06-25 10:00:00'),
        (102, 38, 'QA Supervisor', 'desarrollo', 'Evaluación Rúbrica Ponderada', 90, 90, 80, 90, 0, 88, 'Conforme', 'Atención dentro de la ventana de SLA crítica para errores de nvlddmkm.sys.', '2026-06-26 14:30:00'),
        (103, 36, 'SLA Lead', 'soporte_t1', 'Evaluación Rúbrica Ponderada', 70, 70, 80, 70, 0, 72, 'Requiere Mejora', 'Faltó adjuntar captura de pantalla descriptiva en el registro del caso.', '2026-06-27 11:15:00'),
        (104, 39, 'QA Supervisor', 'desarrollo', 'Evaluación Rúbrica Ponderada', 95, 90, 85, 90, 0, 90, 'Conforme', 'Solución al crasheo de matchmaking enviada en tiempo récord.', '2026-06-28 16:45:00')");
    } else {
        $checkRubricCol = mysqli_query($conn, "SHOW COLUMNS FROM `qa_auditorias` LIKE 'score_diagnostico'");
        if (mysqli_num_rows($checkRubricCol) == 0) {
            mysqli_query($conn, "ALTER TABLE `qa_auditorias` ADD COLUMN `score_diagnostico` INT(11) NOT NULL DEFAULT 90 AFTER `categoria`");
            mysqli_query($conn, "ALTER TABLE `qa_auditorias` ADD COLUMN `score_procesos` INT(11) NOT NULL DEFAULT 90 AFTER `score_diagnostico`");
            mysqli_query($conn, "ALTER TABLE `qa_auditorias` ADD COLUMN `score_redaccion` INT(11) NOT NULL DEFAULT 90 AFTER `score_procesos`");
            mysqli_query($conn, "ALTER TABLE `qa_auditorias` ADD COLUMN `score_eficiencia` INT(11) NOT NULL DEFAULT 90 AFTER `score_redaccion`");
        }
        $checkErrorCriticoCol = mysqli_query($conn, "SHOW COLUMNS FROM `qa_auditorias` LIKE 'error_critico'");
        if (mysqli_num_rows($checkErrorCriticoCol) == 0) {
            mysqli_query($conn, "ALTER TABLE `qa_auditorias` ADD COLUMN `error_critico` TINYINT(1) NOT NULL DEFAULT 0 AFTER `score_eficiencia`");
        }

        // Si la tabla existía pero no tiene registros, sembrar registros iniciales de calibración
        $checkQARows = mysqli_query($conn, "SELECT COUNT(*) as cant FROM `qa_auditorias`");
        if ($checkQARows && ($rQA = mysqli_fetch_assoc($checkQARows)) && intval($rQA['cant']) === 0) {
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");
            mysqli_query($conn, "INSERT IGNORE INTO `qa_auditorias` (`id`, `ticket_id`, `auditor`, `agente_evaluado`, `categoria`, `score_diagnostico`, `score_procesos`, `score_redaccion`, `score_eficiencia`, `error_critico`, `puntaje`, `resultado`, `observaciones`, `fecha`) VALUES
            (101, 37, 'SLA Lead', 'soporte_t1', 'Evaluación Rúbrica Ponderada', 100, 90, 90, 100, 0, 95, 'Conforme', 'Respuesta clara y diagnóstico preciso del problema de launcher.', '2026-06-25 10:00:00'),
            (102, 38, 'QA Supervisor', 'desarrollo', 'Evaluación Rúbrica Ponderada', 90, 90, 80, 90, 0, 88, 'Conforme', 'Atención dentro de la ventana de SLA crítica para errores de nvlddmkm.sys.', '2026-06-26 14:30:00'),
            (103, 36, 'SLA Lead', 'soporte_t1', 'Evaluación Rúbrica Ponderada', 70, 70, 80, 70, 0, 72, 'Requiere Mejora', 'Faltó adjuntar captura de pantalla descriptiva en el registro del caso.', '2026-06-27 11:15:00'),
            (104, 39, 'QA Supervisor', 'desarrollo', 'Evaluación Rúbrica Ponderada', 95, 90, 85, 90, 0, 90, 'Conforme', 'Solución al crasheo de matchmaking enviada en tiempo récord.', '2026-06-28 16:45:00')");
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");
        }
    }

    // 5. Asegurar la tabla capa_coaching (HU 34 - Registro de Planes de Acción Correctivos)
    $checkCAPATable = mysqli_query($conn, "SHOW TABLES LIKE 'capa_coaching'");
    if (mysqli_num_rows($checkCAPATable) == 0) {
        $createCAPATableQuery = "
            CREATE TABLE `capa_coaching` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `evaluacion_id` int(11) DEFAULT NULL,
              `tipo` varchar(50) NOT NULL DEFAULT 'Acción Correctiva',
              `origen` varchar(100) NOT NULL DEFAULT 'Auditoría Deficiente',
              `agente_responsable` varchar(100) NOT NULL,
              `agente_area` varchar(100) DEFAULT NULL,
              `causa_raiz` text NOT NULL,
              `accion_propuesta` text NOT NULL,
              `hallazgo` text DEFAULT NULL,
              `plan_accion` text DEFAULT NULL,
              `responsable` varchar(100) DEFAULT NULL,
              `estado` varchar(50) NOT NULL DEFAULT 'Pendiente',
              `fecha_limite` date DEFAULT NULL,
              `fecha_compromiso` date DEFAULT NULL,
              `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `evaluacion_id` (`evaluacion_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=201;
        ";
        mysqli_query($conn, $createCAPATableQuery);

        mysqli_query($conn, "INSERT INTO `capa_coaching` (`id`, `evaluacion_id`, `tipo`, `origen`, `agente_responsable`, `agente_area`, `causa_raiz`, `accion_propuesta`, `hallazgo`, `plan_accion`, `responsable`, `estado`, `fecha_limite`, `fecha_compromiso`, `fecha_creacion`) VALUES
        (201, 103, 'Acción Correctiva', 'Auditoría Deficiente', 'soporte_t1', 'soporte_t1', 'Omisión de adjuntos obligatorios y falta de documentación previa al escalamiento.', 'Revisión obligatoria del checklist de escalamiento previa asignación a desarrollo y taller de captura de evidencia.', 'Omisión de adjuntos obligatorios y falta de documentación previa al escalamiento.', 'Revisión obligatoria del checklist de escalamiento previa asignación a desarrollo y taller de captura de evidencia.', 'SLA Lead', 'En Proceso', '2026-10-15', '2026-10-15', '2026-06-25 12:00:00'),
        (202, 102, 'Sesión Coaching', 'Bajo CSAT', 'desarrollo', 'desarrollo', 'Observación de lenguaje excesivamente técnico en notas dirigidas al usuario final.', 'Sesión de coaching 1-a-1 sobre comunicación clara y empática con el cliente final.', 'Observación de lenguaje excesivamente técnico en notas dirigidas al usuario final.', 'Sesión de coaching 1-a-1 sobre comunicación clara y empática con el cliente final.', 'QA Supervisor', 'Pendiente', '2026-10-20', '2026-10-20', '2026-06-26 15:00:00'),
        (203, NULL, 'CAPA Preventiva', 'Desviación SLA', 'DevOps Lead', 'Infraestructura', 'Cuellos de botella potenciales en horarios pico de entrega de parches de drivers.', 'Implementar CDN de respaldo y monitoreo proactivo de latencia en descargas.', 'Cuellos de botella potenciales en horarios pico de entrega de parches de drivers.', 'Implementar CDN de respaldo y monitoreo proactivo de latencia en descargas.', 'DevOps Lead', 'Completado', '2026-09-30', '2026-09-30', '2026-06-27 09:30:00'),
        (204, 101, 'Acción Correctiva', 'Auditoría Deficiente', 'soporte_t1', 'soporte_t1', 'Retraso inicial en la clasificación de severidad de tickets de controladores gráficos.', 'Estandarización de matriz de priorización rápida en mesa de ayuda.', 'Retraso inicial en la clasificación de severidad de tickets de controladores gráficos.', 'Estandarización de matriz de priorización rápida en mesa de ayuda.', 'SLA Lead', 'Vencido', '2026-06-01', '2026-06-01', '2026-05-15 10:00:00')");
    } else {
        // Asegurar columnas HU 34 si la tabla ya existía
        $checkEvalCol = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'evaluacion_id'");
        if (mysqli_num_rows($checkEvalCol) == 0) {
            mysqli_query($conn, "ALTER TABLE `capa_coaching` ADD COLUMN `evaluacion_id` INT(11) DEFAULT NULL AFTER `id`");
            mysqli_query($conn, "ALTER TABLE `capa_coaching` ADD KEY `evaluacion_id` (`evaluacion_id`)");
            mysqli_query($conn, "UPDATE `capa_coaching` SET `evaluacion_id` = 103 WHERE `id` = 201");
        }

        $checkCausaCol = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'causa_raiz'");
        if (mysqli_num_rows($checkCausaCol) == 0) {
            mysqli_query($conn, "ALTER TABLE `capa_coaching` ADD COLUMN `causa_raiz` TEXT DEFAULT NULL AFTER `evaluacion_id`");
            mysqli_query($conn, "UPDATE `capa_coaching` SET `causa_raiz` = `hallazgo` WHERE `causa_raiz` IS NULL OR `causa_raiz` = ''");
        }

        $checkAccionCol = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'accion_propuesta'");
        if (mysqli_num_rows($checkAccionCol) == 0) {
            mysqli_query($conn, "ALTER TABLE `capa_coaching` ADD COLUMN `accion_propuesta` TEXT DEFAULT NULL AFTER `causa_raiz`");
            mysqli_query($conn, "UPDATE `capa_coaching` SET `accion_propuesta` = `plan_accion` WHERE `accion_propuesta` IS NULL OR `accion_propuesta` = ''");
        }

        $checkAgenteRespCol = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'agente_responsable'");
        if (mysqli_num_rows($checkAgenteRespCol) == 0) {
            mysqli_query($conn, "ALTER TABLE `capa_coaching` ADD COLUMN `agente_responsable` VARCHAR(100) DEFAULT NULL AFTER `accion_propuesta`");
            mysqli_query($conn, "UPDATE `capa_coaching` SET `agente_responsable` = COALESCE(`agente_area`, `responsable`, 'soporte_t1') WHERE `agente_responsable` IS NULL OR `agente_responsable` = ''");
        }

        $checkFechaLimiteCol = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'fecha_limite'");
        if (mysqli_num_rows($checkFechaLimiteCol) == 0) {
            mysqli_query($conn, "ALTER TABLE `capa_coaching` ADD COLUMN `fecha_limite` DATE DEFAULT NULL AFTER `agente_responsable`");
            mysqli_query($conn, "UPDATE `capa_coaching` SET `fecha_limite` = `fecha_compromiso` WHERE `fecha_limite` IS NULL");
        }

        // Migración de estados a los 4 estados de HU 34: Pendiente, En Proceso, Completado, Vencido
        mysqli_query($conn, "UPDATE `capa_coaching` SET `estado` = 'Pendiente' WHERE `estado` = 'Abierto'");
        mysqli_query($conn, "UPDATE `capa_coaching` SET `estado` = 'Completado' WHERE `estado` IN ('Verificado', 'Cerrado')");
        mysqli_query($conn, "UPDATE `capa_coaching` SET `tipo` = 'Acción Correctiva' WHERE `tipo` = 'CAPA Correctiva'");

        // Asegurar que si hay tareas con fecha pasada y no completadas, pasen a 'Vencido'
        mysqli_query($conn, "UPDATE `capa_coaching` SET `estado` = 'Vencido' WHERE `estado` IN ('Pendiente', 'En Proceso') AND `fecha_limite` IS NOT NULL AND `fecha_limite` < CURDATE()");
    }

    // 6. Asegurar la tabla qa_muestreo_cola (HU 33 - Muestreo Inteligente de Tickets)
    $checkMuestreoTable = mysqli_query($conn, "SHOW TABLES LIKE 'qa_muestreo_cola'");
    if (mysqli_num_rows($checkMuestreoTable) == 0) {
        $createMuestreoTableQuery = "
            CREATE TABLE `qa_muestreo_cola` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `ticket_id` int(11) NOT NULL,
              `agente_evaluado` varchar(100) NOT NULL,
              `motivo_inclusion` varchar(255) NOT NULL,
              `tipo_trigger` varchar(50) NOT NULL DEFAULT 'aleatorio',
              `prioridad_muestreo` varchar(50) NOT NULL DEFAULT 'Media',
              `estado` varchar(50) NOT NULL DEFAULT 'Pendiente',
              `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `ticket_id` (`ticket_id`),
              KEY `estado` (`estado`),
              CONSTRAINT `qa_muestreo_cola_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=301;
        ";
        mysqli_query($conn, $createMuestreoTableQuery);

        mysqli_query($conn, "INSERT INTO `qa_muestreo_cola` (`id`, `ticket_id`, `agente_evaluado`, `motivo_inclusion`, `tipo_trigger`, `prioridad_muestreo`, `estado`, `fecha_generacion`) VALUES
        (301, 36, 'soporte_t1', 'Calificación Cliente ≤ 2 Estrellas (CSAT)', 'csat_bajo', 'Alta', 'Pendiente', '2026-06-25 11:00:00'),
        (302, 38, 'desarrollo', 'Exceso de SLA Crítico (nvlddmkm.sys)', 'sla_critico', 'Alta', 'Auditado', '2026-06-26 13:00:00'),
        (303, 39, 'desarrollo', 'Muestreo Aleatorio Mensual (10%)', 'aleatorio', 'Media', 'Pendiente', '2026-06-28 17:00:00')");
    }
}

// ========================================================
// HU-44: CICLO DE VIDA DE EVALUACIONES DE CALIDAD
// ========================================================

if (!defined('ESTADOS_CALIDAD')) {
    define('ESTADOS_CALIDAD', [
        'PendienteEnvio',
        'Enviada',
        'Completada',
        'Procesada',
        'Cancelada',
        'Expirada'
    ]);
}

if (!class_exists('TransicionCalidadException')) {
    class TransicionCalidadException extends Exception {}
}

if (!class_exists('TransicionInvalidaException')) {
    class TransicionInvalidaException extends Exception {}
}

function validarTransicionCalidad($estadoOrigen, $estadoDestino) {
    if (!esEstadoCalidadValido($estadoDestino)) {
        throw new TransicionCalidadException("Estado de destino no válido: '$estadoDestino'.");
    }
    if (esEstadoTerminalCalidad($estadoOrigen)) {
        throw new TransicionCalidadException("Transición inválida: el estado '$estadoOrigen' es terminal y no permite transiciones posteriores.");
    }
    $permitidas = [
        'PendienteEnvio' => ['Enviada', 'Cancelada'],
        'Enviada'        => ['Completada', 'Expirada', 'Cancelada'],
        'Completada'     => ['Procesada']
    ];
    if (!isset($permitidas[$estadoOrigen]) || !in_array($estadoDestino, $permitidas[$estadoOrigen], true)) {
        throw new TransicionCalidadException("Transición inválida: no se permite avanzar de '$estadoOrigen' a '$estadoDestino'.");
    }
    return true;
}

function getEstadosOficialesCalidad() {
    return ESTADOS_CALIDAD;
}

function esEstadoCalidadValido($estado) {
    return in_array($estado, ESTADOS_CALIDAD, true);
}

function esEstadoTerminalCalidad($estado) {
    return in_array($estado, ['Cancelada', 'Expirada', 'Procesada'], true);
}

function getEstadoCalidadBadgeClass($estado) {
    switch (trim((string)($estado ?? ''))) {
        case 'PendienteEnvio':
            return 'badge-calidad-pendiente';
        case 'Enviada':
            return 'badge-calidad-enviada';
        case 'Completada':
            return 'badge-calidad-completada';
        case 'Procesada':
            return 'badge-calidad-procesada';
        case 'Cancelada':
            return 'badge-calidad-cancelada';
        case 'Expirada':
            return 'badge-calidad-expirada';
        default:
            return 'badge-default';
    }
}

function getEstadoCalidadNombreLegible($estado) {
    switch (trim((string)($estado ?? ''))) {
        case 'PendienteEnvio':
            return 'Pendiente de Envío';
        case 'Enviada':
            return 'Enviada (En Espera)';
        case 'Completada':
            return 'Completada (Respondida)';
        case 'Procesada':
            return 'Procesada (Métricas/SLA)';
        case 'Cancelada':
            return 'Cancelada (Reapertura)';
        case 'Expirada':
            return 'Expirada (Timeout)';
        default:
            return (string)($estado ?? '');
    }
}

// Criterio 2: Al resolverse o cerrarse un ticket mediante registrarEvaluacionCalidad(idTicket), la evaluación se crea en estado inicial PendienteEnvio
function registrarEvaluacionCalidad($param1, $param2 = null) {
    global $conn;
    $c = ($param1 instanceof mysqli) ? $param1 : (($param2 instanceof mysqli) ? $param2 : $conn);
    $idTicket = ($param1 instanceof mysqli) ? intval($param2) : intval($param1);

    if ($idTicket <= 0 || !$c) {
        return false;
    }

    $qTicket = mysqli_query($c, "SELECT id, titulo, usuario, estado FROM tickets WHERE id = $idTicket");
    if (!$qTicket || mysqli_num_rows($qTicket) === 0) {
        return false;
    }
    $ticket = mysqli_fetch_assoc($qTicket);

    // Verificar si ya existe una evaluación activa para este ticket en PendienteEnvio o Enviada
    $checkQuery = mysqli_query($c, "SELECT id, estado FROM feedbacks WHERE ticket_id = $idTicket AND estado IN ('PendienteEnvio', 'Enviada') ORDER BY id DESC LIMIT 1");
    if ($checkQuery && $row = mysqli_fetch_assoc($checkQuery)) {
        return intval($row['id']);
    }

    // Insertar nueva evaluación en estado inicial PendienteEnvio
    $usuario = mysqli_real_escape_string($c, $ticket['usuario']);
    $titulo = mysqli_real_escape_string($c, $ticket['titulo']);
    $tema = 'Evaluación de Calidad: ' . $titulo;
    $temaEsc = mysqli_real_escape_string($c, $tema);

    $insQuery = "
        INSERT INTO feedbacks (ticket_id, usuario, tipo, tema, sentimiento, estado, comentario, fecha)
        VALUES ($idTicket, '$usuario', 'Sugerencia', '$temaEsc', 'Positivo', 'PendienteEnvio', NULL, CURRENT_TIMESTAMP)
    ";

    if (mysqli_query($c, $insQuery)) {
        $newId = mysqli_insert_id($c);
        return $newId;
    }
    return false;
}

// Criterio 3: La acción enviarEncuestaUsuario() pasa la evaluación al estado Enviada
function enviarEncuestaUsuario($param1, $param2 = null) {
    global $conn;
    $c = ($param1 instanceof mysqli) ? $param1 : (($param2 instanceof mysqli) ? $param2 : $conn);
    $idEvaluacion = ($param1 instanceof mysqli) ? intval($param2) : intval($param1);

    if ($idEvaluacion <= 0 || !$c) {
        throw new InvalidArgumentException("Parámetros inválidos para enviar la encuesta.");
    }

    $q = mysqli_query($c, "SELECT id, ticket_id, usuario, estado FROM feedbacks WHERE id = $idEvaluacion");
    if (!$q || mysqli_num_rows($q) === 0) {
        throw new Exception("Evaluación de calidad no encontrada.");
    }
    $eval = mysqli_fetch_assoc($q);

    if ($eval['estado'] !== 'PendienteEnvio') {
        throw new Exception("Transición inválida: la evaluación se encuentra en '" . $eval['estado'] . "'. Solo puede enviarse desde 'PendienteEnvio'.");
    }

    $upd = "
        UPDATE feedbacks 
        SET estado = 'Enviada',
            fecha_envio = CURRENT_TIMESTAMP,
            fecha_expiracion = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 48 HOUR)
        WHERE id = $idEvaluacion AND estado = 'PendienteEnvio'
    ";

    if (mysqli_query($c, $upd) && mysqli_affected_rows($c) > 0) {
        $tktIdPad = !empty($eval['ticket_id']) ? 'TKT-' . str_pad($eval['ticket_id'], 3, '0', STR_PAD_LEFT) : 'soporte';
        crearNotificacion($c, 'usuario', 'Encuesta de Calidad Enviada', "Se ha generado y enviado su encuesta de satisfacción para $tktIdPad.", "feedback.php");
        return [
            'success' => true,
            'id' => $idEvaluacion,
            'estado' => 'Enviada',
            'ticket_id' => $eval['ticket_id']
        ];
    }
    throw new Exception("No se pudo actualizar el estado a Enviada: " . mysqli_error($c));
}

// Criterio 4: Si el ticket se reabre antes de que el usuario responda (cancelarPorTicketReabierto()), la evaluación pasa inmediatamente al estado final Cancelada
function cancelarPorTicketReabierto($param1, $param2 = null) {
    global $conn;
    $c = ($param1 instanceof mysqli) ? $param1 : (($param2 instanceof mysqli) ? $param2 : $conn);
    $idTicket = ($param1 instanceof mysqli) ? intval($param2) : intval($param1);

    if ($idTicket <= 0 || !$c) {
        return 0;
    }

    // Cancelar exclusivamente evaluaciones donde el cliente AÚN NO haya respondido (PendienteEnvio o Enviada)
    $upd = "
        UPDATE feedbacks 
        SET estado = 'Cancelada',
            motivo_cancelacion = 'Ticket reabierto antes de la respuesta del usuario'
        WHERE ticket_id = $idTicket AND estado IN ('PendienteEnvio', 'Enviada')
    ";

    mysqli_query($c, $upd);
    $canceladas = mysqli_affected_rows($c);
    if ($canceladas > 0) {
        crearNotificacion($c, 'monitoreo', 'Evaluación de Calidad Cancelada', "La encuesta para el ticket TKT-" . str_pad($idTicket, 3, '0', STR_PAD_LEFT) . " fue cancelada debido a la reapertura del caso.", "feedback.php");
    }
    return $canceladas;
}

// Criterio 5: Al responder el cliente con su calificación y comentarios (registrarDetalleOpinion()), pasa a Completada
function registrarDetalleOpinion($param1, $param2 = null, $calificacion = null, $comentario = null, $tipo = null, $tema = null, $sentimiento = null) {
    global $conn;
    if ($param1 instanceof mysqli) {
        $c = $param1;
        $idEvaluacion = intval($param2);
        $calif = $calificacion;
        $coment = $comentario;
        $tip = $tipo;
        $tem = $tema;
        $sent = $sentimiento;
    } else {
        $c = $conn;
        $idEvaluacion = intval($param1);
        $calif = $param2;
        $coment = $calificacion;
        $tip = $comentario;
        $tem = $tipo;
        $sent = $tema;
    }

    if ($idEvaluacion <= 0 || !$c) {
        throw new InvalidArgumentException("Parámetros inválidos para registrar opinión.");
    }

    $q = mysqli_query($c, "SELECT id, ticket_id, usuario, tema, estado FROM feedbacks WHERE id = $idEvaluacion");
    if (!$q || mysqli_num_rows($q) === 0) {
        throw new Exception("Evaluación de calidad no encontrada.");
    }
    $eval = mysqli_fetch_assoc($q);

    // Validar estado previo: la transición es inválida si no se encuentra en 'Enviada'
    if ($eval['estado'] !== 'Enviada') {
        if ($eval['estado'] === 'Expirada') {
            throw new Exception("Transición inválida: la evaluación alcanzó el estado 'Expirada' y no admite respuestas.");
        }
        if ($eval['estado'] === 'Cancelada') {
            throw new Exception("Transición inválida: la evaluación fue cancelada por reapertura del ticket.");
        }
        if ($eval['estado'] === 'PendienteEnvio') {
            throw new Exception("Transición inválida: la encuesta se encuentra en 'PendienteEnvio' y debe ser enviada antes de responder.");
        }
        if ($eval['estado'] === 'Completada' || $eval['estado'] === 'Procesada') {
            throw new Exception("Transición inválida: la evaluación ya ha sido completada previamente (estado actual: '" . $eval['estado'] . "').");
        }
        throw new Exception("Transición inválida: no se puede responder una evaluación en estado '" . $eval['estado'] . "'.");
    }

    // Normalizar calificación (1 a 5)
    $califVal = intval($calif);
    if ($califVal < 1) $califVal = 1;
    if ($califVal > 5) $califVal = 5;

    $comentVal = trim(strval($coment ?? ''));
    if (empty($comentVal)) {
        $comentVal = 'Respuesta del cliente sin comentarios adicionales.';
    }
    $comentEsc = mysqli_real_escape_string($c, $comentVal);

    // Inferencia inteligente de sentimiento y tipo si no se especifican
    if (empty($sent)) {
        $sent = ($califVal >= 4) ? 'Positivo' : (($califVal <= 2) ? 'Negativo' : 'Positivo');
    }
    $sentEsc = mysqli_real_escape_string($c, ucfirst(strtolower(trim($sent))));

    if (empty($tip)) {
        $tip = ($califVal >= 4) ? 'Elogio' : (($califVal <= 2) ? 'Queja' : 'Sugerencia');
    }
    $tipEsc = mysqli_real_escape_string($c, ucfirst(strtolower(trim($tip))));

    if (empty($tem)) {
        $tem = !empty($eval['tema']) ? $eval['tema'] : 'Atención Soporte';
    }
    $temEsc = mysqli_real_escape_string($c, $tem);

    $upd = "
        UPDATE feedbacks 
        SET estado = 'Completada',
            calificacion = $califVal,
            comentario = '$comentEsc',
            tipo = '$tipEsc',
            tema = '$temEsc',
            sentimiento = '$sentEsc',
            fecha_respuesta = CURRENT_TIMESTAMP
        WHERE id = $idEvaluacion AND estado = 'Enviada'
    ";

    if (mysqli_query($c, $upd) && mysqli_affected_rows($c) > 0) {
        crearNotificacion($c, 'monitoreo', 'Encuesta Respondida (CSAT)', "El usuario {$eval['usuario']} respondió la evaluación OP-" . str_pad($idEvaluacion, 3, '0', STR_PAD_LEFT) . " con $califVal/5 estrellas.", "feedback.php");
        return [
            'success' => true,
            'id' => $idEvaluacion,
            'estado' => 'Completada',
            'calificacion' => $califVal
        ];
    }
    return ['success' => false, 'error' => 'Error al guardar la respuesta: ' . mysqli_error($c)];
}

// Criterio 5 (timeout): si vence el plazo límite sin respuesta (timeoutPlazoRespuesta()), pasa al estado final Expirada
function timeoutPlazoRespuesta($param1 = null, $param2 = null) {
    global $conn;
    $c = ($param1 instanceof mysqli) ? $param1 : (($param2 instanceof mysqli) ? $param2 : $conn);
    $idEvaluacion = ($param1 instanceof mysqli) ? intval($param2) : intval($param1);

    if (!$c) {
        return false;
    }

    if ($idEvaluacion > 0) {
        // Expirar una evaluación específica que esté en Enviada
        $upd = "UPDATE feedbacks SET estado = 'Expirada' WHERE id = $idEvaluacion AND estado = 'Enviada'";
        mysqli_query($c, $upd);
        return mysqli_affected_rows($c) > 0;
    } else {
        // Batch: expirar todas las evaluaciones en Enviada cuyo plazo límite haya vencido
        $upd = "
            UPDATE feedbacks 
            SET estado = 'Expirada' 
            WHERE estado = 'Enviada' 
              AND fecha_expiracion IS NOT NULL 
              AND fecha_expiracion <= CURRENT_TIMESTAMP
        ";
        mysqli_query($c, $upd);
        return mysqli_affected_rows($c);
    }
}

// Criterio 6: Desde Completada, la ejecución de métricas (calcularCumplimientoSLA() / generarMetricasGlobales()) actualiza los indicadores del panel y pasa la evaluación a Procesada
function calcularCumplimientoSLA($param1, $param2 = null) {
    global $conn;
    $c = ($param1 instanceof mysqli) ? $param1 : (($param2 instanceof mysqli) ? $param2 : $conn);
    $idEvaluacion = ($param1 instanceof mysqli) ? intval($param2) : intval($param1);

    if ($idEvaluacion <= 0 || !$c) {
        throw new InvalidArgumentException("Parámetros inválidos para calcular cumplimiento SLA.");
    }

    $q = mysqli_query($c, "
        SELECT f.*, t.prioridad, t.fecha_apertura, t.fecha_cierre 
        FROM feedbacks f 
        LEFT JOIN tickets t ON f.ticket_id = t.id 
        WHERE f.id = $idEvaluacion
    ");
    if (!$q || mysqli_num_rows($q) === 0) {
        throw new Exception("Evaluación de calidad no encontrada.");
    }
    $eval = mysqli_fetch_assoc($q);

    if ($eval['estado'] !== 'Completada') {
        throw new Exception("Transición inválida: solo evaluaciones en 'Completada' pueden ser procesadas para métricas de SLA (estado actual: '" . $eval['estado'] . "').");
    }

    // Regla de cumplimiento de SLA:
    // 1. Tiempo de resolución acorde a prioridad
    $cumpleTiempo = true;
    if (!empty($eval['fecha_apertura']) && !empty($eval['fecha_cierre'])) {
        $horas = (strtotime($eval['fecha_cierre']) - strtotime($eval['fecha_apertura'])) / 3600;
        $prioridad = strtolower(trim($eval['prioridad'] ?? 'media'));
        $limiteHoras = 48;
        switch ($prioridad) {
            case 'critica': $limiteHoras = 24; break;
            case 'alta':    $limiteHoras = 48; break;
            case 'media':   $limiteHoras = 72; break;
            case 'baja':    $limiteHoras = 96; break;
        }
        if ($horas > $limiteHoras) {
            $cumpleTiempo = false;
        }
    }

    // 2. Calidad de percepción del usuario (CSAT >= 3 o Positivo)
    $cumpleSatisfaccion = true;
    if (isset($eval['calificacion']) && intval($eval['calificacion']) > 0) {
        if (intval($eval['calificacion']) < 3) {
            $cumpleSatisfaccion = false;
        }
    } else if (strtolower(trim($eval['sentimiento'] ?? '')) === 'negativo') {
        $cumpleSatisfaccion = false;
    }

    $slaCumplido = ($cumpleTiempo && $cumpleSatisfaccion) ? 1 : 0;

    $upd = "
        UPDATE feedbacks 
        SET estado = 'Procesada',
            sla_cumplido = $slaCumplido
        WHERE id = $idEvaluacion AND estado = 'Completada'
    ";

    if (mysqli_query($c, $upd) && mysqli_affected_rows($c) > 0) {
        return [
            'success' => true,
            'id' => $idEvaluacion,
            'estado' => 'Procesada',
            'sla_cumplido' => $slaCumplido,
            'cumple_tiempo' => $cumpleTiempo,
            'cumple_satisfaccion' => $cumpleSatisfaccion
        ];
    }
    return ['success' => false, 'error' => 'Error al procesar SLA: ' . mysqli_error($c)];
}

function generarMetricasGlobales($conn = null) {
    global $conn;
    $c = $conn;

    if (!$c) {
        return false;
    }

    // 1. Procesar todas las evaluaciones que se encuentren en Completada
    $qCompletadas = mysqli_query($c, "SELECT id FROM feedbacks WHERE estado = 'Completada'");
    $procesadasAhora = 0;
    if ($qCompletadas) {
        while ($row = mysqli_fetch_assoc($qCompletadas)) {
            $res = calcularCumplimientoSLA($c, intval($row['id']));
            if (!empty($res['success'])) {
                $procesadasAhora++;
            }
        }
    }

    // 2. Calcular indicadores globales del panel
    $qStats = mysqli_query($c, "
        SELECT 
            COUNT(id) AS total,
            SUM(CASE WHEN estado = 'PendienteEnvio' THEN 1 ELSE 0 END) AS cant_pendiente_envio,
            SUM(CASE WHEN estado = 'Enviada' THEN 1 ELSE 0 END) AS cant_enviada,
            SUM(CASE WHEN estado = 'Completada' THEN 1 ELSE 0 END) AS cant_completada,
            SUM(CASE WHEN estado = 'Procesada' THEN 1 ELSE 0 END) AS cant_procesada,
            SUM(CASE WHEN estado = 'Cancelada' THEN 1 ELSE 0 END) AS cant_cancelada,
            SUM(CASE WHEN estado = 'Expirada' THEN 1 ELSE 0 END) AS cant_expirada,
            SUM(CASE WHEN sla_cumplido = 1 THEN 1 ELSE 0 END) AS cant_sla_cumplido,
            AVG(CASE WHEN calificacion IS NOT NULL AND calificacion > 0 THEN calificacion ELSE NULL END) AS csat_promedio_estrellas,
            SUM(CASE WHEN sentimiento = 'Positivo' THEN 1 ELSE 0 END) AS cant_positivos
        FROM feedbacks
    ");

    $stats = $qStats ? mysqli_fetch_assoc($qStats) : [];
    $total = intval($stats['total'] ?? 0);
    $procesadas = intval($stats['cant_procesada'] ?? 0);
    $slaOk = intval($stats['cant_sla_cumplido'] ?? 0);
    $completadas = intval($stats['cant_completada'] ?? 0);
    $enviadas = intval($stats['cant_enviada'] ?? 0);
    $canceladas = intval($stats['cant_cancelada'] ?? 0);
    $expiradas = intval($stats['cant_expirada'] ?? 0);
    $pendientes = intval($stats['cant_pendiente_envio'] ?? 0);

    $cumplimientoSLAPct = $procesadas > 0 ? round(($slaOk / $procesadas) * 100, 1) : 100.0;
    $totalRespondidas = $completadas + $procesadas;
    $csatPct = $totalRespondidas > 0 ? round((intval($stats['cant_positivos'] ?? 0) / $totalRespondidas) * 100, 1) : 0.0;
    $csatEstrellas = !empty($stats['csat_promedio_estrellas']) ? round(floatval($stats['csat_promedio_estrellas']), 1) : 5.0;

    $totalEmitidas = $enviadas + $totalRespondidas + $expiradas;
    $tasaRespuestaPct = $totalEmitidas > 0 ? round(($totalRespondidas / $totalEmitidas) * 100, 1) : 0.0;

    return [
        'total' => $total,
        'procesadas_en_ejecucion' => $procesadasAhora,
        'por_estado' => [
            'PendienteEnvio' => $pendientes,
            'Enviada'        => $enviadas,
            'Completada'     => $completadas,
            'Procesada'      => $procesadas,
            'Cancelada'      => $canceladas,
            'Expirada'       => $expiradas
        ],
        'cumplimiento_sla_porcentaje' => $cumplimientoSLAPct,
        'sla_cumplido_total'          => $slaOk,
        'csat_promedio_estrellas'     => $csatEstrellas,
        'csat_porcentaje_positivo'    => $csatPct,
        'tasa_respuesta_porcentaje'   => $tasaRespuestaPct
    ];
}

// Obtener feedbacks filtrados y buscados (HU-44: compatible con los 6 estados de calidad)
function obtenerFeedbacks($conn) {
    $sql = "SELECT f.*, t.titulo AS ticket_titulo, t.descripcion AS ticket_descripcion
            FROM feedbacks f
            LEFT JOIN tickets t ON f.ticket_id = t.id
            WHERE 1=1";

    // FILTRO ESTADO (HU-44)
    if (!empty($_POST["estadoBuscar"]) && $_POST["estadoBuscar"] != "todos") {
        $estado = mysqli_real_escape_string($conn, $_POST["estadoBuscar"]);
        $sql .= " AND f.estado = '$estado'";
    }

    // FILTRO TIPO
    if (!empty($_POST["tipoBuscar"]) && $_POST["tipoBuscar"] != "todos") {
        $tipo = mysqli_real_escape_string($conn, $_POST["tipoBuscar"]);
        $sql .= " AND f.tipo = '$tipo'";
    }

    // FILTRO SENTIMIENTO
    if (!empty($_POST["sentimientoBuscar"]) && $_POST["sentimientoBuscar"] != "todos") {
        $sentimiento = mysqli_real_escape_string($conn, $_POST["sentimientoBuscar"]);
        $sql .= " AND f.sentimiento = '$sentimiento'";
    }

    // BUSQUEDA TEXTUAL
    if (!empty($_POST["inputBuscar"])) {
        $buscar = mysqli_real_escape_string($conn, $_POST["inputBuscar"]);
        
        // Permite buscar por formato "OP-XXX" extrayendo el ID numérico
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

// Obtener tickets cerrados para el selector
function obtenerTicketsCerrados($conn) {
    $query = mysqli_query($conn, "SELECT id, titulo, usuario, asignado FROM tickets WHERE estado = 'cerrado' AND estado != 'inactivo' ORDER BY id DESC");
    if (!$query) {
        return [];
    }
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

// Registrar un nuevo feedback
function registrarFeedback($conn) {
    if (!empty($_POST["guardar_feedback"])) {
        $ticket_id = !empty($_POST["ticket_id"]) ? intval($_POST["ticket_id"]) : "NULL";
        $usuario = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["usuario"] ?? ""));
        $tipo = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["tipo"] ?? ""));
        $tema = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["tema"] ?? ""));
        $sentimiento = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["sentimiento"] ?? ""));
        $estado = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["estado"] ?? "Nuevo"));
        $comentario = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["comentario"] ?? ""));

        if (!empty($usuario) && !empty($tipo) && !empty($tema) && !empty($sentimiento)) {
            $query = "INSERT INTO feedbacks (ticket_id, usuario, tipo, tema, sentimiento, estado, comentario)
                      VALUES ($ticket_id, '$usuario', '$tipo', '$tema', '$sentimiento', '$estado', '$comentario')";
            
            if (mysqli_query($conn, $query)) {
                // Disparador de notificación Soporte (Opinión Manual)
                crearNotificacion($conn, 'soporte', 'Nueva Opinión', "Se ha recibido una opinión de $usuario: \"$tema\".", "feedback.php");
                $_SESSION["msg"] = '<div class="toast-success">✅ ¡Feedback registrado exitosamente!</div>';
                unset($_SESSION["persisted_form"]);
                unset($_SESSION["persisted_form_type"]);
            } else {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al registrar el feedback en la base de datos: ' . mysqli_escape_string($conn, mysqli_error($conn)) . '</div>';
                $_SESSION["persisted_form"] = $_POST;
                $_SESSION["persisted_form_type"] = "crear_feedback";
            }
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: Todos los campos obligatorios deben completarse.</div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form_type"] = "crear_feedback";
        }
        redirigirFeedback("feedback.php");
    }
}

// Actualizar el estado de un feedback (Validando exclusivamente los 6 estados de HU-44)
function actualizarFeedbackEstado($conn) {
    if (!empty($_POST["actualizar_estado_feedback"])) {
        $id = intval($_POST["actualizar_estado_feedback"]);
        $estado = mysqli_real_escape_string($conn, trim($_POST["estado"] ?? ''));

        if (!empty($estado)) {
            if (!esEstadoCalidadValido($estado)) {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error de Integridad (HU-44): El estado "' . htmlspecialchars($estado) . '" no es válido. El módulo de calidad implementa exclusivamente: ' . implode(', ', ESTADOS_CALIDAD) . '.</div>';
                redirigirFeedback("feedback.php");
                return;
            }

            $qCurrent = mysqli_query($conn, "SELECT estado FROM feedbacks WHERE id = $id");
            $curr = $qCurrent ? mysqli_fetch_assoc($qCurrent) : null;
            if ($curr && esEstadoTerminalCalidad($curr['estado']) && $curr['estado'] !== $estado) {
                $_SESSION["msg"] = '<div class="toast-error">❌ Transición inválida: el estado "' . $curr['estado'] . '" es terminal y no permite transiciones posteriores.</div>';
                redirigirFeedback("feedback.php");
                return;
            }

            $query = "UPDATE feedbacks SET estado = '$estado' WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $_SESSION["msg"] = '<div class="toast-success">✅ Estado de la evaluación actualizado a ' . htmlspecialchars($estado) . ' exitosamente.</div>';
            } else {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al actualizar el estado de la evaluación: ' . mysqli_error($conn) . '</div>';
            }
        }
        redirigirFeedback("feedback.php");
    }
}

// Eliminar feedback (lógico) - marca como Cancelada (estado terminal válido HU-44)
function eliminarFeedback($conn) {
    if (!empty($_POST["eliminar"])) {
        $idEliminar = intval($_POST["eliminar"]);
        $queryDelete = "UPDATE feedbacks SET estado = 'Cancelada', motivo_cancelacion = 'Eliminado lógicamente por usuario' WHERE id = $idEliminar";
        
        if (mysqli_query($conn, $queryDelete)) {
            $_SESSION["msg"] = '<div class="toast-success">✅ Evaluación cancelada/removida exitosamente.</div>';
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error al cancelar la evaluación.</div>';
        }
        redirigirFeedback("feedback.php");
    }
}

// Obtener estadísticas de feedback (HU-44: métricas de los 6 estados de calidad y SLA)
function obtenerFeedbackStats($conn) {
    $query = mysqli_query($conn, "
        SELECT 
            COUNT(id) AS total,
            SUM(CASE WHEN estado = 'PendienteEnvio' THEN 1 ELSE 0 END) AS pendiente_envio,
            SUM(CASE WHEN estado = 'Enviada' THEN 1 ELSE 0 END) AS enviadas,
            SUM(CASE WHEN estado = 'Completada' THEN 1 ELSE 0 END) AS completadas,
            SUM(CASE WHEN estado = 'Procesada' THEN 1 ELSE 0 END) AS procesadas,
            SUM(CASE WHEN estado = 'Cancelada' THEN 1 ELSE 0 END) AS canceladas,
            SUM(CASE WHEN estado = 'Expirada' THEN 1 ELSE 0 END) AS expiradas,
            SUM(CASE WHEN sla_cumplido = 1 THEN 1 ELSE 0 END) AS sla_cumplido_total
        FROM feedbacks
    ");
    if (!$query) {
        return [
            'total' => 0, 'pendiente_envio' => 0, 'enviadas' => 0, 
            'completadas' => 0, 'procesadas' => 0, 'canceladas' => 0, 'expiradas' => 0,
            'sla_cumplido_total' => 0, 'nuevos' => 0, 'revisados' => 0, 'implementados' => 0
        ];
    }
    $res = mysqli_fetch_assoc($query);
    // Aliases para compatibilidad hacia atrás
    $res['nuevos'] = intval($res['pendiente_envio'] ?? 0);
    $res['revisados'] = intval($res['completadas'] ?? 0);
    $res['implementados'] = intval($res['procesadas'] ?? 0);
    return $res;
}

// Clases de CSS para badges de Tipo y Sentimiento
function getFeedbackTipoBadgeClass($tipo) {
    switch (strtolower(trim((string)($tipo ?? '')))) {
        case 'sugerencia':
            return 'badge-sugerencia';
        case 'queja':
            return 'badge-queja';
        case 'elogio':
            return 'badge-elogio';
        default:
            return 'badge-default';
    }
}

function getFeedbackSentimientoBadgeClass($sentimiento) {
    switch (strtolower(trim((string)($sentimiento ?? '')))) {
        case 'positivo':
            return 'badge-positivo';
        case 'negativo':
            return 'badge-negativo';
        default:
            return 'badge-default';
    }
}

function getFeedbackEstadoBadgeClass($estado) {
    return getEstadoCalidadBadgeClass($estado);
}
function mostrarModalRegistrarFeedback($conn) {
    $tickets = obtenerTicketsCerrados($conn);
    
    echo '
    <div role="dialog" id="create-feedback-modal" aria-labelledby="create-feedback-title" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between">
          <div>
            <h2 id="create-feedback-title" class="text-2xl font-semibold text-white" style="color: #ffffff !important;">
              Registrar Opinión
            </h2>
            <p class="mt-1 text-sm text-slate-400" style="color: #94a3b8 !important;">
              Registra una nueva opinión vinculada a un ticket cerrado/resuelto.
            </p>
          </div>
        </div>

        <!-- Form -->
        <form class="space-y-5" method="POST">
          <!-- Ticket Vincular -->
          <div>
            <label for="feedback-ticket-select" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
              Ticket de Soporte de Origen (Requerido)
            </label>
            <select
              id="feedback-ticket-select"
              name="ticket_id"
              required
              class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              onchange="autocompletarTicketInfo()"
            >
              <option value="" style="background-color: #0f172a; color: #ffffff;">-- Selecciona un ticket cerrado --</option>';
              foreach ($tickets as $t) {
                  echo '<option value="' . $t['id'] . '" data-usuario="' . htmlspecialchars($t['usuario']) . '" data-titulo="' . htmlspecialchars($t['titulo']) . '" style="background-color: #0f172a; color: #ffffff;">TKT-' . str_pad($t['id'], 3, '0', STR_PAD_LEFT) . ' - ' . htmlspecialchars($t['titulo']) . ' (' . htmlspecialchars($t['usuario']) . ')</option>';
              }
    echo '  </select>
          </div>

          <!-- Usuario -->
          <div>
            <label for="feedback-usuario" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
              Usuario
            </label>
            <input
              id="feedback-usuario"
              name="usuario"
              type="text"
              required
              placeholder="Nombre de usuario"
              class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
            />
          </div>

          <!-- Tema -->
          <div>
            <label for="feedback-tema" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
              Tema
            </label>
            <input
              id="feedback-tema"
              name="tema"
              type="text"
              required
              placeholder="Ej. Satisfacción con soporte o asunto"
              class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
            />
          </div>

          <!-- Tipo + Sentimiento -->
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label for="feedback-tipo" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Tipo de Opinión
              </label>
              <select
                id="feedback-tipo"
                name="tipo"
                required
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
                onchange="actualizarSentimientoAutomatico()"
              >
                <option value="Sugerencia" style="background-color: #0f172a; color: #ffffff;">Sugerencia</option>
                <option value="Queja" style="background-color: #0f172a; color: #ffffff;">Queja</option>
                <option value="Elogio" style="background-color: #0f172a; color: #ffffff;">Elogio</option>
              </select>
            </div>

            <div>
              <label for="feedback-sentimiento" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Análisis de Sentimiento
              </label>
              <select
                id="feedback-sentimiento"
                name="sentimiento"
                required
                class="w-full rounded-xl border border-slate-700 bg-[#1e293b]/50 px-4 py-3 text-sm text-slate-400 outline-none cursor-not-allowed"
                style="background-color: rgb(30 41 59 / 0.5) !important; color: #94a3b8 !important; border: 1px solid #334155 !important; pointer-events: none;"
                tabindex="-1"
              >
                <option value="Positivo" style="background-color: #0f172a; color: #ffffff;">Positivo</option>
                <option value="Negativo" style="background-color: #0f172a; color: #ffffff;">Negativo</option>
              </select>
            </div>
          </div>

          <!-- Estado + Comentario -->
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label for="feedback-estado" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Estado
              </label>
              <select
                id="feedback-estado"
                name="estado"
                required
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              >
                <option value="Nuevo" style="background-color: #0f172a; color: #ffffff;">Nuevo</option>
                <option value="Revisado" style="background-color: #0f172a; color: #ffffff;">Revisado</option>
                <option value="Implementado" style="background-color: #0f172a; color: #ffffff;">Implementado</option>
              </select>
            </div>

            <div>
              <label for="feedback-comentario" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Comentario / Opinión Detallada
              </label>
              <textarea
                id="feedback-comentario"
                name="comentario"
                rows="3"
                placeholder="Escribe el comentario del usuario..."
                class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"
              ></textarea>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4">
            <!-- Botón para cancelar y cerrar el modal vía post -->
            <button
              type="submit"
              name="FormFeedback"
              value=""
              class="rounded-xl border border-slate-600 px-5 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
              style="color: #cbd5e1 !important; border-color: #475569 !important; background-color: #0f172a !important;"
              formnovalidate
            >
              Cancelar
            </button>
            <button
              type="submit"
              name="guardar_feedback"
              value="1"
              class="btn-registrar"
            >
              Guardar Opinión
            </button>
          </div>
        </form>

        <!-- Script de Autocompletado -->
        <script>
          function actualizarSentimientoAutomatico() {
              const tipoSelect = document.getElementById("feedback-tipo");
              const sentimientoSelect = document.getElementById("feedback-sentimiento");
              if (!tipoSelect || !sentimientoSelect) return;
              
              const tipo = tipoSelect.value;
              if (tipo === "Elogio" || tipo === "Sugerencia") {
                  sentimientoSelect.value = "Positivo";
              } else if (tipo === "Queja") {
                  sentimientoSelect.value = "Negativo";
              }
          }

          function autocompletarTicketInfo() {
              const select = document.getElementById("feedback-ticket-select");
              const selectedOption = select.options[select.selectedIndex];
              if (selectedOption && selectedOption.value !== "") {
                  const usuario = selectedOption.getAttribute("data-usuario");
                  const titulo = selectedOption.getAttribute("data-titulo");
                  
                  document.getElementById("feedback-usuario").value = usuario;
                  document.getElementById("feedback-tema").value = "Satisfacción con soporte - TKT-" + selectedOption.value.padStart(3, "0");
                  document.getElementById("feedback-tipo").value = "Elogio";
                  actualizarSentimientoAutomatico();
              }
          }

          // Inicializar sentimiento al cargar/abrir el modal
          actualizarSentimientoAutomatico();
        </script>

      </div>
    </div>';
}

function mostrarModalDetalleFeedback($conn, $id) {
    $id = intval($id);
    $is_monitoreo = false;
    
    $query = mysqli_query($conn, "
        SELECT f.*, t.id AS ticket_id, t.titulo AS ticket_titulo, t.descripcion AS ticket_descripcion, t.categoria AS ticket_categoria, t.prioridad AS ticket_prioridad, t.usuario AS ticket_usuario
        FROM feedbacks f
        LEFT JOIN tickets t ON f.ticket_id = t.id
        WHERE f.id = $id
    ");
    $fb = mysqli_fetch_assoc($query);
    if (!$fb) return;

    $ticketDisplay = "No vinculado";
    if (!empty($fb["ticket_id"])) {
        $ticketDisplay = '<span class="inline-flex items-center gap-1.5 rounded-full bg-blue-950/40 border border-blue-800/50 px-2 py-0.5 text-xs text-blue-400 font-medium">
            TKT-' . str_pad($fb["ticket_id"], 3, '0', STR_PAD_LEFT) . '
        </span>';
    }

    $select_attr = $is_monitoreo 
        ? 'disabled class="w-full rounded-xl border border-slate-700 bg-[#1e293b]/50 px-4 py-2.5 text-sm text-slate-400 outline-none cursor-not-allowed" style="background-color: rgb(30 41 59 / 0.5) !important; color: #94a3b8 !important; border: 1px solid #334155 !important;"' 
        : 'class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white outline-none transition focus:border-emerald-500" style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"';

    echo '
    <div role="dialog" id="detail-feedback-modal" aria-labelledby="detail-feedback-title" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <!-- Header -->
        <div class="mb-4 flex items-start justify-between">
          <div>
            <h2 id="detail-feedback-title" class="text-2xl font-semibold text-white flex items-center gap-2" style="color: #ffffff !important;">
              Detalles de OP-' . str_pad($fb["id"], 3, '0', STR_PAD_LEFT) . '
              ' . $ticketDisplay . '
            </h2>
            <p class="mt-1 text-sm text-slate-400" style="color: #94a3b8 !important;">
              Información de la opinión recibida e historial asociado.
            </p>
          </div>
        </div>

        <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
          
          <!-- Datos Principales y Métricas de Calidad (HU-44) -->
          <div class="grid grid-cols-2 gap-4 rounded-xl border border-slate-700 bg-slate-900/60 p-4">
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Usuario</span>
              <span class="text-sm font-medium text-white">' . htmlspecialchars($fb["usuario"] ?? '') . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Estado de Calidad (HU-44)</span>
              <span class="mt-1 inline-block text-xs px-2.5 py-0.5 rounded font-medium ' . getEstadoCalidadBadgeClass($fb["estado"]) . '">' . htmlspecialchars(getEstadoCalidadNombreLegible($fb["estado"])) . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Calificación del Cliente (CSAT)</span>
              <span class="text-sm font-medium text-amber-400">' . (!empty($fb["calificacion"]) ? str_repeat('⭐', intval($fb["calificacion"])) . ' (' . $fb["calificacion"] . '/5)' : '<span class="text-slate-500 italic text-xs">Sin respuesta aún</span>') . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Cumplimiento de SLA</span>
              <span class="text-sm font-medium">' . (isset($fb["sla_cumplido"]) && $fb["sla_cumplido"] !== null ? ($fb["sla_cumplido"] == 1 ? '<span class="text-emerald-400 font-semibold">✅ Cumple SLA</span>' : '<span class="text-rose-400 font-semibold">❌ No Cumple SLA</span>') : '<span class="text-slate-500 text-xs">Pendiente de cálculo</span>') . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Tipo</span>
              <span class="mt-1 inline-block text-xs px-2.5 py-0.5 rounded-full font-medium ' . getFeedbackTipoBadgeClass($fb["tipo"]) . '">' . ucfirst(strtolower(trim($fb["tipo"] ?? 'Sugerencia'))) . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Sentimiento</span>
              <span class="mt-1 inline-block text-xs px-2.5 py-0.5 rounded-full font-medium ' . getFeedbackSentimientoBadgeClass($fb["sentimiento"]) . '">' . ucfirst(strtolower(trim($fb["sentimiento"] ?? 'Positivo'))) . '</span>
            </div>
            <div class="col-span-2">
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Tema</span>
              <span class="text-sm text-white font-medium">' . htmlspecialchars($fb["tema"] ?? 'Atención') . '</span>
            </div>
            <div class="col-span-2 pt-2 border-t border-slate-800 grid grid-cols-3 gap-2 text-xs">
              <div>
                <span class="text-slate-500 block">Fecha Envío:</span>
                <span class="text-slate-300 font-medium">' . (!empty($fb["fecha_envio"]) ? date('d/m/Y H:i', strtotime($fb["fecha_envio"])) : '<span class="text-slate-600">No enviada</span>') . '</span>
              </div>
              <div>
                <span class="text-slate-500 block">Fecha Límite:</span>
                <span class="text-slate-300 font-medium">' . (!empty($fb["fecha_expiracion"]) ? date('d/m/Y H:i', strtotime($fb["fecha_expiracion"])) : '<span class="text-slate-600">N/A</span>') . '</span>
              </div>
              <div>
                <span class="text-slate-500 block">Fecha Respuesta:</span>
                <span class="text-slate-300 font-medium">' . (!empty($fb["fecha_respuesta"]) ? date('d/m/Y H:i', strtotime($fb["fecha_respuesta"])) : '<span class="text-slate-600">Pendiente</span>') . '</span>
              </div>
            </div>' . (!empty($fb["motivo_cancelacion"]) ? '
            <div class="col-span-2 p-2 bg-rose-950/30 border border-rose-900/50 rounded text-rose-300 text-xs">
              <strong>Motivo de Cancelación:</strong> ' . htmlspecialchars($fb["motivo_cancelacion"]) . '
            </div>' : '') . '
          </div>

          <!-- Comentario del Usuario -->
          <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Comentario original</span>
            <p class="text-sm text-slate-300 leading-relaxed italic bg-slate-950/40 p-3 rounded-lg border border-slate-800">
              "' . htmlspecialchars($fb["comentario"] ?? "Sin comentarios descriptivos.") . '"
            </p>
          </div>';

          if (!empty($fb["ticket_id"])) {
              echo '
              <!-- Detalle del Ticket Vinculado -->
              <div class="rounded-xl border border-blue-900/30 bg-blue-950/10 p-4">
                <span class="block text-xs font-semibold uppercase tracking-wider text-blue-400 mb-2">Ticket de Soporte Relacionado</span>
                <div class="space-y-2 text-sm text-slate-300">
                  <div class="flex justify-between">
                    <span class="font-semibold text-slate-400">Título del Ticket:</span>
                    <span>' . htmlspecialchars($fb["ticket_titulo"] ?? '') . '</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-semibold text-slate-400">Creado por el jugador:</span>
                    <span>' . htmlspecialchars($fb["ticket_usuario"] ?? '') . '</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-semibold text-slate-400">Categoría:</span>
                    <span class="capitalize">' . htmlspecialchars($fb["ticket_categoria"] ?? '') . '</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="font-semibold text-slate-400">Prioridad:</span>
                    <span class="uppercase text-xs font-medium text-amber-400">' . htmlspecialchars($fb["ticket_prioridad"] ?? '') . '</span>
                  </div>
                  <div class="pt-2 border-t border-slate-800">
                    <span class="block font-semibold text-slate-400 mb-1">Descripción de la incidencia:</span>
                    <p class="text-xs bg-slate-950/30 p-2 rounded text-slate-400 leading-relaxed">' . htmlspecialchars($fb["ticket_descripcion"] ?? '') . '</p>
                  </div>
                </div>
              </div>';
          }

          echo '
          <!-- Actualizar Estado del Feedback (Exclusivamente los 6 estados de HU-44) -->
          <form class="pt-2 border-t border-slate-800 space-y-4" method="POST">
            <div>
              <label for="detail-status" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Cambiar Estado de Evaluación (HU-44)
              </label>
              <select
                id="detail-status"
                name="estado"
                ' . $select_attr . '
              >
                <option value="PendienteEnvio" ' . ($fb["estado"] === "PendienteEnvio" ? "selected" : "") . ' style="background-color: #0f172a; color: #ffffff;">PendienteEnvio (Pendiente de Envío)</option>
                <option value="Enviada" ' . ($fb["estado"] === "Enviada" ? "selected" : "") . ' style="background-color: #0f172a; color: #ffffff;">Enviada (En Espera de Respuesta)</option>
                <option value="Completada" ' . ($fb["estado"] === "Completada" ? "selected" : "") . ' style="background-color: #0f172a; color: #ffffff;">Completada (Respondida por Cliente)</option>
                <option value="Procesada" ' . ($fb["estado"] === "Procesada" ? "selected" : "") . ' style="background-color: #0f172a; color: #ffffff;">Procesada (Métricas & SLA Calculados)</option>
                <option value="Cancelada" ' . ($fb["estado"] === "Cancelada" ? "selected" : "") . ' style="background-color: #0f172a; color: #ffffff;">Cancelada (Ticket Reabierto)</option>
                <option value="Expirada" ' . ($fb["estado"] === "Expirada" ? "selected" : "") . ' style="background-color: #0f172a; color: #ffffff;">Expirada (Timeout sin Respuesta)</option>
              </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
              <button
                type="submit"
                name="ver_detalle"
                value=""
                class="rounded-xl border border-slate-600 px-5 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                style="color: #cbd5e1 !important; border-color: #475569 !important; background-color: #0f172a !important;"
                formnovalidate
              >
                Cerrar
              </button>
              ' . (!$is_monitoreo ? '
              <button
                type="submit"
                name="actualizar_estado_feedback"
                value="' . $fb["id"] . '"
                class="btn-registrar"
              >
                Actualizar Estado
              </button>
              ' : '') . '
            </div>
          </form>
        </div>
      </div>
    </div>';
}

// ==========================================
// FUNCIONES DE AUDITORÍAS DE CALIDAD (QA INTERNAL)
// ==========================================

function obtenerQAAuditorias($conn) {
    $sql = "SELECT q.*, t.titulo AS ticket_titulo 
            FROM qa_auditorias q 
            LEFT JOIN tickets t ON q.ticket_id = t.id 
            WHERE 1=1";

    if (!empty($_POST["inputBuscarQA"])) {
        $buscar = mysqli_real_escape_string($conn, $_POST["inputBuscarQA"]);
        $sql .= " AND (q.auditor LIKE '%$buscar%' OR q.agente_evaluado LIKE '%$buscar%' OR q.categoria LIKE '%$buscar%' OR q.observaciones LIKE '%$buscar%')";
    }

    if (!empty($_POST["resultadoBuscarQA"]) && $_POST["resultadoBuscarQA"] !== "todos") {
        $resultado = mysqli_real_escape_string($conn, $_POST["resultadoBuscarQA"]);
        if ($resultado === "Auto-Fail") {
            $sql .= " AND (q.resultado = 'Auto-Fail' OR q.error_critico = 1)";
        } else {
            $sql .= " AND q.resultado = '$resultado'";
        }
    }

    $sql .= " ORDER BY q.id DESC";
    $query = mysqli_query($conn, $sql);
    if (!$query) return [];
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function obtenerQAStats($conn) {
    $query = mysqli_query($conn, "
        SELECT 
            COUNT(id) AS total,
            AVG(puntaje) AS promedio_score,
            SUM(CASE WHEN resultado = 'Conforme' THEN 1 ELSE 0 END) AS conformes,
            SUM(CASE WHEN resultado = 'Requiere Mejora' THEN 1 ELSE 0 END) AS mejora,
            SUM(CASE WHEN resultado = 'No Conforme' OR resultado = 'Auto-Fail' OR error_critico = 1 THEN 1 ELSE 0 END) AS no_conformes
        FROM qa_auditorias
    ");
    if (!$query) {
        return ['total' => 0, 'promedio_score' => 0, 'conformes' => 0, 'mejora' => 0, 'no_conformes' => 0, 'tasa_conformidad' => 0];
    }
    $res = mysqli_fetch_assoc($query);
    $res['total'] = intval($res['total'] ?? 0);
    $res['promedio_score'] = round(floatval($res['promedio_score'] ?? 0), 1);
    $res['conformes'] = intval($res['conformes'] ?? 0);
    $res['mejora'] = intval($res['mejora'] ?? 0);
    $res['no_conformes'] = intval($res['no_conformes'] ?? 0);
    $res['tasa_conformidad'] = $res['total'] > 0 ? round(($res['conformes'] / $res['total']) * 100) : 0;
    return $res;
}

// ==========================================
// MATRIZ DE CALIDAD CRUZADA: CSAT VS QA SCORE (HU 35)
// ==========================================

function obtenerKPIsCalidadCruzada($conn) {
    // 1. Promedio de QA Score y Tasa de Errores Críticos
    $qaQuery = mysqli_query($conn, "
        SELECT 
            COUNT(id) AS total_qa,
            AVG(puntaje) AS avg_qa_score,
            SUM(CASE WHEN error_critico = 1 OR resultado = 'Auto-Fail' THEN 1 ELSE 0 END) AS total_errores_criticos
        FROM qa_auditorias
    ");
    $qaData = $qaQuery ? mysqli_fetch_assoc($qaQuery) : [];
    $totalQA = intval($qaData['total_qa'] ?? 0);
    $qaPromedio = $totalQA > 0 ? round(floatval($qaData['avg_qa_score']), 1) : 0;
    $totalErroresCriticos = intval($qaData['total_errores_criticos'] ?? 0);
    $tasaErroresCriticos = $totalQA > 0 ? round(($totalErroresCriticos / $totalQA) * 100, 1) : 0;

    // 2. CSAT Promedio (Porcentaje de satisfacción positiva del cliente)
    // Se consideran feedbacks con sentimiento explícito para no sesgar el índice
    $csatQuery = mysqli_query($conn, "
        SELECT 
            COUNT(CASE WHEN sentimiento IS NOT NULL AND TRIM(sentimiento) != '' THEN 1 ELSE NULL END) AS total_feedback,
            SUM(CASE WHEN LOWER(sentimiento) = 'positivo' OR LOWER(tipo) = 'elogio' THEN 1 ELSE 0 END) AS positivos
        FROM feedbacks
        WHERE estado != 'inactivo'
    ");
    $csatData = $csatQuery ? mysqli_fetch_assoc($csatQuery) : [];
    $totalFB = intval($csatData['total_feedback'] ?? 0);
    $totalPositivos = intval($csatData['positivos'] ?? 0);
    $csatPromedio = $totalFB > 0 ? round(($totalPositivos / $totalFB) * 100, 1) : 0;

    return [
        'qa_promedio' => $qaPromedio,
        'tasa_errores_criticos' => $tasaErroresCriticos,
        'csat_promedio' => $csatPromedio,
        'total_qa' => $totalQA,
        'total_errores_criticos' => $totalErroresCriticos,
        'total_feedback' => $totalFB
    ];
}

function obtenerMatrizCalidadCruzadaPorAgente($conn) {
    // Definición de equipos operativos conforme a technova.sql (roles, usuarios, tickets y auditorias)
    $equiposDef = [
        'soporte' => [
            'nombre_legible' => 'Equipo de Soporte',
            'qa_agentes' => ['soporte', 'soporte_t1', 'support'],
            'tkt_agentes' => ['soporte', 'soporte_t1', 'support']
        ],
        'desarrollo' => [
            'nombre_legible' => 'Equipo de Desarrollo',
            'qa_agentes' => ['desarrollo', 'equipo de desarrollo', 'valen'],
            'tkt_agentes' => ['desarrollo', 'equipo de desarrollo', 'valen']
        ]
    ];

    // Detectar si existen otros agentes independientes evaluados en QA o asignados en Tickets
    $otrosAgentes = [];
    $qAgents = mysqli_query($conn, "SELECT DISTINCT agente_evaluado FROM qa_auditorias WHERE agente_evaluado IS NOT NULL AND agente_evaluado != ''");
    if ($qAgents) {
        while ($r = mysqli_fetch_assoc($qAgents)) {
            $ag = trim((string)$r['agente_evaluado']);
            if ($ag === '' || $ag === '.' || $ag === '-') continue;
            $agLower = strtolower($ag);
            if (!in_array($agLower, $equiposDef['soporte']['qa_agentes']) && !in_array($agLower, $equiposDef['desarrollo']['qa_agentes']) && strpos($agLower, 'soporte') === false && strpos($agLower, 'desarrollo') === false) {
                $otrosAgentes[$ag] = true;
            }
        }
    }

    $tAgents = mysqli_query($conn, "SELECT DISTINCT asignado FROM tickets WHERE asignado IS NOT NULL AND asignado != ''");
    if ($tAgents) {
        while ($r = mysqli_fetch_assoc($tAgents)) {
            $ag = trim((string)$r['asignado']);
            if ($ag === '' || $ag === '.' || $ag === '-') continue;
            $agLower = strtolower($ag);
            if (!in_array($agLower, $equiposDef['soporte']['tkt_agentes']) && !in_array($agLower, $equiposDef['desarrollo']['tkt_agentes']) && strpos($agLower, 'soporte') === false && strpos($agLower, 'desarrollo') === false) {
                $otrosAgentes[$ag] = true;
            }
        }
    }

    $kpisGlobales = obtenerKPIsCalidadCruzada($conn);
    $globalCSAT = $kpisGlobales['csat_promedio'] > 0 ? $kpisGlobales['csat_promedio'] : 85.0;
    $globalQA = $kpisGlobales['qa_promedio'] > 0 ? $kpisGlobales['qa_promedio'] : 85.0;

    $matriz = [];

    // Procesar los equipos principales de technova.sql
    foreach ($equiposDef as $key => $conf) {
        $qaIn = "'" . implode("', '", array_map(function($a) use ($conn) { return mysqli_real_escape_string($conn, $a); }, $conf['qa_agentes'])) . "'";
        $tktIn = "'" . implode("', '", array_map(function($a) use ($conn) { return mysqli_real_escape_string($conn, $a); }, $conf['tkt_agentes'])) . "'";

        // 1. Estadísticas QA
        $qaQ = mysqli_query($conn, "
            SELECT 
                COUNT(id) AS total_qa,
                AVG(puntaje) AS avg_score,
                SUM(CASE WHEN error_critico = 1 OR resultado = 'Auto-Fail' THEN 1 ELSE 0 END) AS errores_criticos
            FROM qa_auditorias
            WHERE agente_evaluado IN ($qaIn)
        ");
        $qaRes = $qaQ ? mysqli_fetch_assoc($qaQ) : [];
        $totalQA = intval($qaRes['total_qa'] ?? 0);
        $qaScore = $totalQA > 0 ? round(floatval($qaRes['avg_score']), 1) : $globalQA;
        $errCriticos = intval($qaRes['errores_criticos'] ?? 0);

        // 2. Estadísticas CSAT
        if ($key === 'soporte') {
            $csatQ = mysqli_query($conn, "
                SELECT 
                    COUNT(DISTINCT f.id) AS total_fb,
                    SUM(CASE WHEN LOWER(f.sentimiento) = 'positivo' OR LOWER(f.tipo) = 'elogio' THEN 1 ELSE 0 END) AS positivos
                FROM feedbacks f
                LEFT JOIN tickets t ON f.ticket_id = t.id
                LEFT JOIN qa_auditorias q ON f.ticket_id = q.ticket_id
                WHERE f.estado != 'inactivo'
                  AND f.sentimiento IS NOT NULL
                  AND TRIM(f.sentimiento) != ''
                  AND (
                      t.asignado IN ($tktIn)
                      OR q.agente_evaluado IN ($qaIn)
                      OR LOWER(f.tema) LIKE '%soporte%'
                  )
            ");
        } else {
            $csatQ = mysqli_query($conn, "
                SELECT 
                    COUNT(DISTINCT f.id) AS total_fb,
                    SUM(CASE WHEN LOWER(f.sentimiento) = 'positivo' OR LOWER(f.tipo) = 'elogio' THEN 1 ELSE 0 END) AS positivos
                FROM feedbacks f
                LEFT JOIN tickets t ON f.ticket_id = t.id
                LEFT JOIN qa_auditorias q ON f.ticket_id = q.ticket_id
                WHERE f.estado != 'inactivo'
                  AND f.sentimiento IS NOT NULL
                  AND TRIM(f.sentimiento) != ''
                  AND (
                      t.asignado IN ($tktIn)
                      OR q.agente_evaluado IN ($qaIn)
                  )
            ");
        }
        $csatRes = $csatQ ? mysqli_fetch_assoc($csatQ) : [];
        $totalFB = intval($csatRes['total_fb'] ?? 0);
        $csatScore = $totalFB > 0 ? round((intval($csatRes['positivos']) / $totalFB) * 100, 1) : $globalCSAT;

        $gap = round($csatScore - $qaScore, 1);

        // Diagnóstico SLA
        if ($errCriticos > 0) {
            $diagnostico = "🚨 Error Crítico";
            $diagnosticoBadge = "badge-custom badge-queja";
        } else if ($qaScore >= 85 && $csatScore >= 85) {
            $diagnostico = "⭐ Sobresaliente";
            $diagnosticoBadge = "badge-custom badge-positivo";
        } else if ($qaScore >= 80 && $csatScore >= 80) {
            $diagnostico = "✅ Cumple SLA";
            $diagnosticoBadge = "badge-custom badge-sugerencia";
        } else if (abs($gap) >= 15) {
            $diagnostico = "⚠️ Desalineación";
            $diagnosticoBadge = "badge-custom badge-estado-revisado";
        } else {
            $diagnostico = "⚡ Requiere Mejora";
            $diagnosticoBadge = "badge-custom badge-default";
        }

        $matriz[] = [
            'agente' => $key,
            'nombre_legible' => $conf['nombre_legible'],
            'csat_score' => $csatScore,
            'csat_total' => $totalFB,
            'qa_score' => $qaScore,
            'qa_total' => $totalQA,
            'errores_criticos' => $errCriticos,
            'gap' => $gap,
            'diagnostico' => $diagnostico,
            'diagnostico_badge' => $diagnosticoBadge
        ];
    }

    // Si existen otros agentes independientes, procesarlos
    foreach (array_keys($otrosAgentes) as $otro) {
        $otroEscaped = mysqli_real_escape_string($conn, $otro);
        $qaQ = mysqli_query($conn, "
            SELECT 
                COUNT(id) AS total_qa,
                AVG(puntaje) AS avg_score,
                SUM(CASE WHEN error_critico = 1 OR resultado = 'Auto-Fail' THEN 1 ELSE 0 END) AS errores_criticos
            FROM qa_auditorias
            WHERE agente_evaluado = '$otroEscaped'
        ");
        $qaRes = $qaQ ? mysqli_fetch_assoc($qaQ) : [];
        $totalQA = intval($qaRes['total_qa'] ?? 0);
        $qaScore = $totalQA > 0 ? round(floatval($qaRes['avg_score']), 1) : $globalQA;
        $errCriticos = intval($qaRes['errores_criticos'] ?? 0);

        $csatQ = mysqli_query($conn, "
            SELECT 
                COUNT(f.id) AS total_fb,
                SUM(CASE WHEN LOWER(f.sentimiento) = 'positivo' OR LOWER(f.tipo) = 'elogio' THEN 1 ELSE 0 END) AS positivos
            FROM feedbacks f
            LEFT JOIN tickets t ON f.ticket_id = t.id
            WHERE t.asignado = '$otroEscaped' AND f.estado != 'inactivo' AND f.sentimiento IS NOT NULL AND TRIM(f.sentimiento) != ''
        ");
        $csatRes = $csatQ ? mysqli_fetch_assoc($csatQ) : [];
        $totalFB = intval($csatRes['total_fb'] ?? 0);
        $csatScore = $totalFB > 0 ? round((intval($csatRes['positivos']) / $totalFB) * 100, 1) : $globalCSAT;

        $gap = round($csatScore - $qaScore, 1);

        if ($errCriticos > 0) {
            $diagnostico = "🚨 Error Crítico";
            $diagnosticoBadge = "badge-custom badge-queja";
        } else if ($qaScore >= 85 && $csatScore >= 85) {
            $diagnostico = "⭐ Sobresaliente";
            $diagnosticoBadge = "badge-custom badge-positivo";
        } else if ($qaScore >= 80 && $csatScore >= 80) {
            $diagnostico = "✅ Cumple SLA";
            $diagnosticoBadge = "badge-custom badge-sugerencia";
        } else if (abs($gap) >= 15) {
            $diagnostico = "⚠️ Desalineación";
            $diagnosticoBadge = "badge-custom badge-estado-revisado";
        } else {
            $diagnostico = "⚡ Requiere Mejora";
            $diagnosticoBadge = "badge-custom badge-default";
        }

        $uQ = mysqli_query($conn, "SELECT nombre_completo FROM usuarios WHERE usuario = '$otroEscaped' LIMIT 1");
        $nombreLegible = ($uQ && ($u = mysqli_fetch_assoc($uQ)) && !empty($u['nombre_completo'])) ? $u['nombre_completo'] : ucfirst($otro);

        $matriz[] = [
            'agente' => $otro,
            'nombre_legible' => $nombreLegible,
            'csat_score' => $csatScore,
            'csat_total' => $totalFB,
            'qa_score' => $qaScore,
            'qa_total' => $totalQA,
            'errores_criticos' => $errCriticos,
            'gap' => $gap,
            'diagnostico' => $diagnostico,
            'diagnostico_badge' => $diagnosticoBadge
        ];
    }

    return $matriz;
}

function registrarQAAuditoria($conn) {
    if (!empty($_POST["guardar_qa"])) {
        $ticket_id = !empty($_POST["ticket_id"]) ? intval($_POST["ticket_id"]) : "NULL";
        $auditor = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["auditor"] ?? ""));
        $agente_evaluado = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["agente_evaluado"] ?? ""));
        $categoria = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["categoria"] ?? "Evaluación con Rúbrica Ponderada"));
        
        $error_critico = (isset($_POST["error_critico"]) && $_POST["error_critico"] == "1") ? 1 : 0;

        $score_diagnostico = max(0, min(100, intval($_POST["score_diagnostico"] ?? 100)));
        $score_procesos = max(0, min(100, intval($_POST["score_procesos"] ?? 100)));
        $score_redaccion = max(0, min(100, intval($_POST["score_redaccion"] ?? 100)));
        $score_eficiencia = max(0, min(100, intval($_POST["score_eficiencia"] ?? 100)));

        if ($error_critico === 1) {
            // Regla de cero tolerancia: se penaliza automáticamente a 0% independientemente de otros criterios
            $puntaje = 0;
            $resultado = "Auto-Fail";
        } else {
            // Cálculo del puntaje final ponderado (100% total)
            // Diagnóstico Técnico: 30%, Adherencia a Procesos: 30%, Redacción/Tono: 20%, Eficiencia: 20%
            $puntaje = round(($score_diagnostico * 0.30) + ($score_procesos * 0.30) + ($score_redaccion * 0.20) + ($score_eficiencia * 0.20));

            if ($puntaje >= 80) {
                $resultado = "Conforme";
            } else if ($puntaje >= 60) {
                $resultado = "Requiere Mejora";
            } else {
                $resultado = "No Conforme";
            }
        }

        $observaciones = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["observaciones"] ?? ""));

        if (!empty($auditor) && !empty($agente_evaluado)) {
            $query = "INSERT INTO qa_auditorias (ticket_id, auditor, agente_evaluado, categoria, score_diagnostico, score_procesos, score_redaccion, score_eficiencia, error_critico, puntaje, resultado, observaciones)
                      VALUES ($ticket_id, '$auditor', '$agente_evaluado', '$categoria', $score_diagnostico, $score_procesos, $score_redaccion, $score_eficiencia, $error_critico, $puntaje, '$resultado', '$observaciones')";
            
            if (mysqli_query($conn, $query)) {
                if ($ticket_id !== 'NULL' && intval($ticket_id) > 0) {
                    mysqli_query($conn, "UPDATE qa_muestreo_cola SET estado = 'Auditado' WHERE ticket_id = " . intval($ticket_id));
                }

                $mensajeNotif = ($error_critico === 1) 
                    ? "ALERTA SEVERA: Auditoría QA para $agente_evaluado finalizada en AUTO-FAIL por Error Crítico." 
                    : "Evaluación de rúbrica realizada para $agente_evaluado con resultado $resultado ($puntaje%).";
                
                crearNotificacion($conn, 'soporte', 'Nueva Auditoría QA', $mensajeNotif, "feedback.php?tab=qa");
                
                if ($error_critico === 1) {
                    $_SESSION["msg"] = '<div class="toast-error">🚨 Auditoría QA registrada con ERROR CRÍTICO (Regla de Cero Tolerancia). Resultado: Auto-Fail (0%).</div>';
                } else {
                    $_SESSION["msg"] = '<div class="toast-success">✅ Auditoría QA evaluada y registrada exitosamente. Score ponderado final: ' . $puntaje . '% (' . $resultado . ')</div>';
                }
            } else {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al registrar la auditoría QA en la base de datos: ' . mysqli_escape_string($conn, mysqli_error($conn)) . '</div>';
            }
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Completa todos los campos obligatorios de la rúbrica.</div>';
        }
        redirigirFeedback("feedback.php?tab=qa");
    }
}

// =========================================================
// HU 33: MUESTREO INTELIGENTE DE TICKETS PARA AUDITORÍA
// =========================================================
function generarMuestreoInteligenteQA($conn, $porcentaje = 0.10) {
    $insertadosCount = 0;
    
    // 1. Muestreo Aleatorio (10% de tickets cerrados por agente asignado)
    $agentesQuery = mysqli_query($conn, "SELECT DISTINCT COALESCE(NULLIF(NULLIF(asignado, ''), '.'), 'soporte') as agente FROM tickets WHERE estado = 'cerrado'");
    if ($agentesQuery) {
        while ($agRow = mysqli_fetch_assoc($agentesQuery)) {
            $agente = mysqli_real_escape_string($conn, $agRow['agente']);
            $tktsAgenteQuery = mysqli_query($conn, "SELECT id FROM tickets WHERE estado = 'cerrado' AND (asignado = '$agente' OR (asignado = '' AND '$agente' = 'soporte') OR (asignado = '.' AND '$agente' = 'soporte'))");
            $totalAgente = mysqli_num_rows($tktsAgenteQuery);
            if ($totalAgente > 0) {
                $cantMuestreo = max(1, ceil($totalAgente * $porcentaje));
                $sampleQuery = mysqli_query($conn, "SELECT id FROM tickets WHERE estado = 'cerrado' AND (asignado = '$agente' OR (asignado = '' AND '$agente' = 'soporte') OR (asignado = '.' AND '$agente' = 'soporte')) ORDER BY RAND() LIMIT $cantMuestreo");
                if ($sampleQuery) {
                    while ($sample = mysqli_fetch_assoc($sampleQuery)) {
                        $tktId = intval($sample['id']);
                        $checkExists = mysqli_query($conn, "SELECT id FROM qa_muestreo_cola WHERE ticket_id = $tktId");
                        if (mysqli_num_rows($checkExists) == 0) {
                            mysqli_query($conn, "INSERT INTO qa_muestreo_cola (ticket_id, agente_evaluado, motivo_inclusion, tipo_trigger, prioridad_muestreo, estado) VALUES ($tktId, '$agente', 'Muestreo Aleatorio Mensual', 'aleatorio', 'Media', 'Pendiente')");
                            $insertadosCount++;
                        }
                    }
                }
            }
        }
    }

    // 2. Disparador CSAT Bajo / Feedback Negativo
    $csatBajoQuery = mysqli_query($conn, "SELECT f.ticket_id, COALESCE(NULLIF(NULLIF(t.asignado, ''), '.'), 'soporte') as agente FROM feedbacks f JOIN tickets t ON f.ticket_id = t.id WHERE (LOWER(f.sentimiento) = 'negativo' OR LOWER(f.tipo) = 'queja') AND f.ticket_id IS NOT NULL");
    if ($csatBajoQuery) {
        while ($csat = mysqli_fetch_assoc($csatBajoQuery)) {
            $tktId = intval($csat['ticket_id']);
            $agente = mysqli_real_escape_string($conn, $csat['agente']);
            $checkExists = mysqli_query($conn, "SELECT id FROM qa_muestreo_cola WHERE ticket_id = $tktId");
            if (mysqli_num_rows($checkExists) == 0) {
                mysqli_query($conn, "INSERT INTO qa_muestreo_cola (ticket_id, agente_evaluado, motivo_inclusion, tipo_trigger, prioridad_muestreo, estado) VALUES ($tktId, '$agente', 'Calificación Cliente ≤ 2 Estrellas', 'csat_bajo', 'Alta', 'Pendiente')");
                $insertadosCount++;
            }
        }
    }

    // 3. Disparador SLA Crítico (Prioridad Critica)
    $slaCriticoQuery = mysqli_query($conn, "SELECT id, COALESCE(NULLIF(NULLIF(asignado, ''), '.'), 'soporte') as agente FROM tickets WHERE prioridad = 'critica' AND estado = 'cerrado'");
    if ($slaCriticoQuery) {
        while ($sla = mysqli_fetch_assoc($slaCriticoQuery)) {
            $tktId = intval($sla['id']);
            $agente = mysqli_real_escape_string($conn, $sla['agente']);
            $checkExists = mysqli_query($conn, "SELECT id FROM qa_muestreo_cola WHERE ticket_id = $tktId");
            if (mysqli_num_rows($checkExists) == 0) {
                mysqli_query($conn, "INSERT INTO qa_muestreo_cola (ticket_id, agente_evaluado, motivo_inclusion, tipo_trigger, prioridad_muestreo, estado) VALUES ($tktId, '$agente', 'Exceso de SLA Crítico', 'sla_critico', 'Alta', 'Pendiente')");
                $insertadosCount++;
            }
        }
    }

    return $insertadosCount;
}

function obtenerColaMuestreoQA($conn) {
    // Sincronizar agente_evaluado en qa_muestreo_cola con el campo asignado del ticket (evitando caracteres como '.')
    mysqli_query($conn, "UPDATE qa_muestreo_cola m JOIN tickets t ON m.ticket_id = t.id SET m.agente_evaluado = COALESCE(NULLIF(NULLIF(t.asignado, ''), '.'), 'soporte') WHERE m.agente_evaluado != COALESCE(NULLIF(NULLIF(t.asignado, ''), '.'), 'soporte') OR m.agente_evaluado IS NULL OR m.agente_evaluado = ''");

    $sql = "SELECT m.*, t.titulo, t.prioridad as ticket_prioridad, t.fecha_cierre 
            FROM qa_muestreo_cola m 
            JOIN tickets t ON m.ticket_id = t.id 
            WHERE m.estado = 'Pendiente'
            ORDER BY FIELD(m.prioridad_muestreo, 'Alta', 'Media', 'Baja'), m.id DESC";
    $res = mysqli_query($conn, $sql);
    if (!$res) return [];
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function obtenerMuestreoStats($conn) {
    $stats = [
        'pendientes' => 0,
        'aleatorios' => 0,
        'disparadores' => 0
    ];
    $res = mysqli_query($conn, "SELECT 
        SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN tipo_trigger = 'aleatorio' AND estado = 'Pendiente' THEN 1 ELSE 0 END) as aleatorios,
        SUM(CASE WHEN tipo_trigger != 'aleatorio' AND estado = 'Pendiente' THEN 1 ELSE 0 END) as disparadores
        FROM qa_muestreo_cola");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $stats['pendientes'] = intval($row['pendientes'] ?? 0);
        $stats['aleatorios'] = intval($row['aleatorios'] ?? 0);
        $stats['disparadores'] = intval($row['disparadores'] ?? 0);
    }
    return $stats;
}

function obtenerUsuariosSoporte($conn) {
    $sql = "SELECT u.id, u.usuario, u.nombre_completo, u.email 
            FROM usuarios u 
            LEFT JOIN roles r ON u.IdRol = r.id 
            WHERE r.nombre = 'soporte' OR u.IdRol = 2
            ORDER BY u.nombre_completo ASC, u.usuario ASC";
    $query = mysqli_query($conn, $sql);
    if (!$query) return [];
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function mostrarModalRegistrarQA($conn, $preselectedTicketId = null, $preselectedAgente = null) {
    $tickets = obtenerTicketsCerrados($conn);
    $agentesSoporte = obtenerUsuariosSoporte($conn);
    $auditorDefault = !empty($_SESSION["currentName"]) ? $_SESSION["currentName"] : (!empty($_SESSION["currentUser"]) ? $_SESSION["currentUser"] : (!empty($_SESSION["usuario"]) ? $_SESSION["usuario"] : "Responsable SLA"));
    echo '
    <div role="dialog" id="create-qa-modal" aria-labelledby="create-qa-title" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-2 sm:p-4">
      <div class="relative flex flex-col rounded-xl border border-slate-600 bg-[#0f172a] shadow-2xl overflow-hidden" style="max-width: 600px !important; width: 92% !important; max-height: 85vh !important; background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <!-- Header Fijo -->
        <div class="px-4 py-2.5 flex-shrink-0 flex items-center justify-between border-b border-slate-800 bg-slate-900/80">
          <div>
            <h2 id="create-qa-title" class="text-base font-semibold text-white">
              Evaluación de Calidad - Rúbrica QA
            </h2>
            <p class="text-[11px] text-slate-400">
              Evalúa el desempeño técnico y comunicacional sobre el 100%.
            </p>
          </div>
          <form method="POST" class="m-0 p-0">
            <input type="hidden" name="active_tab" value="qa" />
            <button type="submit" name="FormQA" value="" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition cursor-pointer" title="Cerrar modal">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>
            </button>
          </form>
        </div>

        <!-- Formulario (Cuerpo Desplazable) -->
        <form class="flex-1 flex flex-col min-h-0 overflow-hidden" method="POST">
          <input type="hidden" name="active_tab" value="qa" />

          <!-- Cuerpo scrollable -->
          <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <!-- Ticket Vinculado & Auditor -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-200">Ticket Resuelto Evaluado *</label>
                <select id="qa-ticket-select" name="ticket_id" required class="w-full rounded-lg border border-slate-600 bg-[#1e293b] px-2.5 py-1.5 text-xs text-white outline-none" onchange="autocompletarAgenteQA()">
                  <option value="">-- Selecciona ticket --</option>';
                  foreach ($tickets as $t) {
                      $selTicket = ($preselectedTicketId && intval($t['id']) === intval($preselectedTicketId)) ? ' selected' : '';
                      $asignadoVal = !empty($t['asignado']) ? htmlspecialchars($t['asignado']) : 'soporte_t1';
                      echo '<option value="' . $t['id'] . '" data-asignado="' . $asignadoVal . '" data-usuario="' . htmlspecialchars($t['usuario']) . '"' . $selTicket . ' style="background-color: #0f172a; color: #ffffff;">TKT-' . str_pad($t['id'], 3, '0', STR_PAD_LEFT) . ' - ' . htmlspecialchars($t['titulo']) . ' (Atendido: ' . $asignadoVal . ')</option>';
                  }
      echo '    </select>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium text-slate-200">Auditor / Evaluador *</label>
                <input type="text" name="auditor" readonly value="' . htmlspecialchars($auditorDefault) . '" class="w-full rounded-lg border border-slate-700 bg-slate-800/80 px-2.5 py-1.5 text-xs text-slate-300 cursor-not-allowed outline-none font-medium" title="Auditor preseleccionado según la sesión activa" />
              </div>
            </div>

            <!-- Agente & Categoría -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-200">Agente / Equipo Evaluado *</label>
                <select id="qa-agente-select" name="agente_evaluado" required class="w-full rounded-lg border border-slate-600 bg-[#1e293b] px-2.5 py-1.5 text-xs text-white outline-none">
                  <option value="">-- Selecciona agente a evaluar --</option>
                  <optgroup label="Soporte Técnico">
                    <option value="support" ' . (($preselectedAgente === 'support') ? 'selected' : '') . ' style="background-color: #0f172a; color: #ffffff;">Ignacio Silva (@support)</option>
                    <option value="soporte_t1" ' . (($preselectedAgente === 'soporte_t1' || empty($preselectedAgente)) ? 'selected' : '') . ' style="background-color: #0f172a; color: #ffffff;">Soporte Técnico T1 (@soporte_t1)</option>
                    <option value="soporte" ' . (($preselectedAgente === 'soporte') ? 'selected' : '') . ' style="background-color: #0f172a; color: #ffffff;">Mesa de Soporte (@soporte)</option>
                  </optgroup>
                  <optgroup label="Desarrollo">
                    <option value="desarrollo" ' . (($preselectedAgente === 'desarrollo' || $preselectedAgente === 'equipo de desarrollo') ? 'selected' : '') . ' style="background-color: #0f172a; color: #ffffff;">Equipo de Desarrollo (@desarrollo)</option>
                    <option value="dev_lead" ' . (($preselectedAgente === 'dev_lead') ? 'selected' : '') . ' style="background-color: #0f172a; color: #ffffff;">Esteban Krause (@dev_lead)</option>
                  </optgroup>';
                  if (!empty($agentesSoporte)) {
                      foreach ($agentesSoporte as $ag) {
                          if (in_array($ag["usuario"], ['support', 'soporte_t1', 'soporte', 'desarrollo', 'valen'])) continue;
                          $selAgente = ($preselectedAgente && $ag["usuario"] === $preselectedAgente) ? ' selected' : '';
                          $nombreLabel = !empty($ag["nombre_completo"]) ? htmlspecialchars($ag["nombre_completo"]) . ' (@' . htmlspecialchars($ag["usuario"]) . ')' : htmlspecialchars($ag["usuario"]);
                          echo '<option value="' . htmlspecialchars($ag["usuario"]) . '"' . $selAgente . ' style="background-color: #0f172a; color: #ffffff;">' . $nombreLabel . '</option>';
                      }
                  }
      echo '    </select>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium text-slate-200">Categoría</label>
                <input type="text" name="categoria" value="Evaluación con Rúbrica Ponderada" class="w-full rounded-lg border border-slate-600 bg-[#1e293b] px-2.5 py-1.5 text-xs text-white outline-none" />
              </div>
            </div>

            <!-- RÚBRICA PONDERADA (4 CATEGORÍAS COMPACTAS) -->
            <div class="space-y-1.5 rounded-xl border border-slate-700 bg-slate-900/60 p-3">
              <div class="flex justify-between items-center border-b border-slate-800 pb-1">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-emerald-400">Criterios de Rúbrica Ponderada (100%)</h3>
                <span class="text-[10px] text-slate-400">Puntaje (0 - 100%)</span>
              </div>

              <!-- Criterio 1: Diagnóstico Técnico (30%) -->
              <div class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-800/60">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-white">1. Diagnóstico Técnico</span>
                    <span class="text-[10px] font-bold text-emerald-400">(30%)</span>
                  </div>
                  <span class="text-[10px] text-slate-400 block truncate">Análisis de causa raíz y solución.</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <input type="number" min="0" max="100" id="rubric_diagnostico" name="score_diagnostico" value="90" required oninput="calcularRubricaPonderada()" class="w-16 rounded-md border border-slate-600 bg-[#1e293b] px-2 py-1 text-center text-xs text-white font-mono font-bold outline-none focus:border-emerald-500" />
                  <span id="pts_diagnostico" class="text-xs font-bold text-cyan-400 min-w-[70px] text-right">27.0 / 30 pts</span>
                </div>
              </div>

              <!-- Criterio 2: Adherencia a Procesos (30%) -->
              <div class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-800/60">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-white">2. Adherencia a Procesos</span>
                    <span class="text-[10px] font-bold text-emerald-400">(30%)</span>
                  </div>
                  <span class="text-[10px] text-slate-400 block truncate">Cumplimiento SLA y escalamiento.</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <input type="number" min="0" max="100" id="rubric_procesos" name="score_procesos" value="90" required oninput="calcularRubricaPonderada()" class="w-16 rounded-md border border-slate-600 bg-[#1e293b] px-2 py-1 text-center text-xs text-white font-mono font-bold outline-none focus:border-emerald-500" />
                  <span id="pts_procesos" class="text-xs font-bold text-cyan-400 min-w-[70px] text-right">27.0 / 30 pts</span>
                </div>
              </div>

              <!-- Criterio 3: Redacción y Tono (20%) -->
              <div class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-800/60">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-white">3. Redacción / Tono</span>
                    <span class="text-[10px] font-bold text-emerald-400">(20%)</span>
                  </div>
                  <span class="text-[10px] text-slate-400 block truncate">Claridad y trato profesional.</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <input type="number" min="0" max="100" id="rubric_redaccion" name="score_redaccion" value="90" required oninput="calcularRubricaPonderada()" class="w-16 rounded-md border border-slate-600 bg-[#1e293b] px-2 py-1 text-center text-xs text-white font-mono font-bold outline-none focus:border-emerald-500" />
                  <span id="pts_redaccion" class="text-xs font-bold text-cyan-400 min-w-[70px] text-right">18.0 / 20 pts</span>
                </div>
              </div>

              <!-- Criterio 4: Eficiencia (20%) -->
              <div class="flex items-center justify-between gap-2 py-1.5">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-white">4. Eficiencia</span>
                    <span class="text-[10px] font-bold text-emerald-400">(20%)</span>
                  </div>
                  <span class="text-[10px] text-slate-400 block truncate">Velocidad y respuesta efectiva.</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <input type="number" min="0" max="100" id="rubric_eficiencia" name="score_eficiencia" value="90" required oninput="calcularRubricaPonderada()" class="w-16 rounded-md border border-slate-600 bg-[#1e293b] px-2 py-1 text-center text-xs text-white font-mono font-bold outline-none focus:border-emerald-500" />
                  <span id="pts_eficiencia" class="text-xs font-bold text-cyan-400 min-w-[70px] text-right">18.0 / 20 pts</span>
                </div>
              </div>
            </div>

            <!-- REGLA DE CERO TOLERANCIA / ERROR CRÍTICO -->
            <div class="rounded-lg border border-rose-900/60 bg-rose-950/20 p-2 transition-all hover:border-rose-700">
              <div class="flex items-center justify-between gap-2">
                <label for="rubric_error_critico" class="flex items-center gap-2 text-xs font-semibold text-rose-300 cursor-pointer min-w-0 flex-1">
                  <input 
                    type="checkbox" 
                    id="rubric_error_critico" 
                    name="error_critico" 
                    value="1" 
                    onchange="calcularRubricaPonderada()" 
                    class="h-4 w-4 flex-shrink-0 rounded border-slate-600 bg-slate-900 text-rose-600 focus:ring-rose-500 cursor-pointer" 
                  />
                  <span class="truncate">🚨 Error Crítico (Regla Cero Tolerancia)</span>
                </label>
                <span class="text-[10px] text-rose-400/80 flex-shrink-0">Forzar 0% (Auto-Fail)</span>
              </div>
            </div>

            <!-- Puntaje Total Calculado Gauge Bar -->
            <div id="total_score_gauge_box" class="rounded-lg border border-emerald-800/60 bg-emerald-950/20 px-3 py-2 flex items-center justify-between transition-all">
              <div class="flex items-center gap-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Score Total:</span>
                <span id="total_score_val" class="text-xl font-extrabold text-emerald-400">90%</span>
              </div>
              <div>
                <span id="result_badge_preview" class="badge-custom badge-positivo text-xs px-2.5 py-0.5">Conforme</span>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-xs font-medium text-slate-200">Observaciones y Retroalimentación</label>
              <textarea name="observaciones" rows="2" placeholder="Ingresa recomendaciones..." class="w-full resize-none rounded-lg border border-slate-600 bg-[#1e293b] px-2.5 py-1.5 text-xs text-white outline-none"></textarea>
            </div>
          </div>

          <!-- Sticky Footer -->
          <div class="px-4 py-2.5 flex-shrink-0 flex items-center justify-end gap-2 border-t border-slate-800 bg-slate-900/80">
            <button type="submit" name="FormQA" value="" class="rounded-lg border border-slate-600 px-3.5 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-800" formnovalidate>
              Cancelar
            </button>
            <button type="submit" name="guardar_qa" value="1" class="btn-registrar py-1.5 px-3.5 text-xs">
              Finalizar Evaluación
            </button>
          </div>
        </form>

        <script>
        function calcularRubricaPonderada() {
            const isErrorCritico = document.getElementById("rubric_error_critico") ? document.getElementById("rubric_error_critico").checked : false;

            const diag = parseFloat(document.getElementById("rubric_diagnostico").value) || 0;
            const proc = parseFloat(document.getElementById("rubric_procesos").value) || 0;
            const red = parseFloat(document.getElementById("rubric_redaccion").value) || 0;
            const efic = parseFloat(document.getElementById("rubric_eficiencia").value) || 0;

            const ptsDiag = (diag * 0.30);
            const ptsProc = (proc * 0.30);
            const ptsRed = (red * 0.20);
            const ptsEfic = (efic * 0.20);

            document.getElementById("pts_diagnostico").textContent = ptsDiag.toFixed(1) + " / 30 pts";
            document.getElementById("pts_procesos").textContent = ptsProc.toFixed(1) + " / 30 pts";
            document.getElementById("pts_redaccion").textContent = ptsRed.toFixed(1) + " / 20 pts";
            document.getElementById("pts_eficiencia").textContent = ptsEfic.toFixed(1) + " / 20 pts";

            const gaugeBox = document.getElementById("total_score_gauge_box");
            const totalEl = document.getElementById("total_score_val");
            const badgeEl = document.getElementById("result_badge_preview");

            if (isErrorCritico) {
                if (totalEl) {
                    totalEl.textContent = "0%";
                    totalEl.className = "text-3xl font-extrabold text-rose-500 animate-pulse";
                }
                if (badgeEl) {
                    badgeEl.textContent = "🚨 Auto-Fail";
                    badgeEl.className = "badge-custom badge-negativo bg-rose-950/80 text-rose-300 border-rose-600 font-bold text-sm px-3 py-1";
                }
                if (gaugeBox) {
                    gaugeBox.className = "rounded-xl border border-rose-800/80 bg-rose-950/40 p-4 flex items-center justify-between transition-all";
                }
            } else {
                const total = Math.round(ptsDiag + ptsProc + ptsRed + ptsEfic);
                if (totalEl) {
                    totalEl.textContent = total + "%";
                    totalEl.className = "text-3xl font-extrabold text-emerald-400";
                }
                if (gaugeBox) {
                    gaugeBox.className = "rounded-xl border border-emerald-800/60 bg-emerald-950/20 p-4 flex items-center justify-between transition-all";
                }

                if (badgeEl) {
                    if (total >= 80) {
                        badgeEl.textContent = "Conforme";
                        badgeEl.className = "badge-custom badge-positivo text-sm px-3 py-1";
                    } else if (total >= 60) {
                        badgeEl.textContent = "Requiere Mejora";
                        badgeEl.className = "badge-custom badge-estado-revisado text-sm px-3 py-1";
                    } else {
                        badgeEl.textContent = "No Conforme";
                        badgeEl.className = "badge-custom badge-negativo text-sm px-3 py-1";
                    }
                }
            }
        }

        function autocompletarAgenteQA() {
            const select = document.getElementById("qa-ticket-select");
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption && selectedOption.value !== "") {
                const asignado = selectedOption.getAttribute("data-asignado");
                const agenteSelect = document.getElementById("qa-agente-select");
                if (agenteSelect && asignado) {
                    const asgLower = asignado.toLowerCase();
                    let matched = false;
                    for (let i = 0; i < agenteSelect.options.length; i++) {
                        const optVal = agenteSelect.options[i].value.toLowerCase();
                        const optText = agenteSelect.options[i].text.toLowerCase();
                        if (optVal === asgLower || optText.includes(asgLower)) {
                            agenteSelect.selectedIndex = i;
                            matched = true;
                            break;
                        }
                    }
                    if (!matched && (asgLower.includes("soporte") || asgLower.includes("t1") || asgLower.includes("support"))) {
                        for (let i = 0; i < agenteSelect.options.length; i++) {
                            const val = agenteSelect.options[i].value.toLowerCase();
                            if (val === "support" || val === "soporte_t1" || val.includes("soporte")) {
                                agenteSelect.selectedIndex = i;
                                matched = true;
                                break;
                            }
                        }
                    }
                    if (!matched && asgLower.includes("desarrollo")) {
                        for (let i = 0; i < agenteSelect.options.length; i++) {
                            if (agenteSelect.options[i].value.toLowerCase().includes("desarrollo")) {
                                agenteSelect.selectedIndex = i;
                                matched = true;
                                break;
                            }
                        }
                    }
                }
            }
        }

        calcularRubricaPonderada();
        </script>
      </div>
    </div>';
}

function mostrarModalDetalleQA($conn, $id) {
    $id = intval($id);
    $query = mysqli_query($conn, "
        SELECT q.*, t.titulo AS ticket_titulo, t.usuario AS ticket_usuario
        FROM qa_auditorias q
        LEFT JOIN tickets t ON q.ticket_id = t.id
        WHERE q.id = $id
    ");
    $qa = mysqli_fetch_assoc($query);
    if (!$qa) return;

    $tktDisplay = $qa["ticket_id"] ? 'TKT-' . str_pad($qa["ticket_id"], 3, '0', STR_PAD_LEFT) . ' (' . htmlspecialchars($qa["ticket_titulo"] ?? "") . ')' : 'Sin ticket vinculado';

    $scoreDiag = intval($qa["score_diagnostico"] ?? 90);
    $scoreProc = intval($qa["score_procesos"] ?? 90);
    $scoreRed = intval($qa["score_redaccion"] ?? 90);
    $scoreEfic = intval($qa["score_eficiencia"] ?? 90);

    $ptsDiag = round($scoreDiag * 0.30, 1);
    $ptsProc = round($scoreProc * 0.30, 1);
    $ptsRed = round($scoreRed * 0.20, 1);
    $ptsEfic = round($scoreEfic * 0.20, 1);

    $isAutoFail = (!empty($qa["error_critico"]) || $qa["resultado"] === "Auto-Fail");

    $resBadgeClass = "badge-custom badge-default";
    $resultadoText = htmlspecialchars($qa["resultado"]);

    if ($isAutoFail) {
        $resBadgeClass = "badge-custom badge-negativo bg-rose-950/80 text-rose-300 border-rose-600 font-bold text-sm px-3 py-1";
        $resultadoText = "🚨 Auto-Fail";
    } else if ($qa["resultado"] === "Conforme") {
        $resBadgeClass = "badge-custom badge-positivo text-sm px-3 py-1";
    } else if ($qa["resultado"] === "Requiere Mejora") {
        $resBadgeClass = "badge-custom badge-estado-revisado text-sm px-3 py-1";
    } else if ($qa["resultado"] === "No Conforme") {
        $resBadgeClass = "badge-custom badge-negativo text-sm px-3 py-1";
    }

    echo '
    <div role="dialog" id="detail-qa-modal" aria-labelledby="detail-qa-title" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-2 sm:p-4">
      <div class="relative flex flex-col rounded-xl border border-slate-600 bg-[#0f172a] shadow-2xl overflow-hidden" style="max-width: 600px !important; width: 92% !important; max-height: 85vh !important; background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <div class="mb-3 flex-shrink-0 flex items-start justify-between border-b border-slate-800 pb-3">
          <div>
            <h2 id="detail-qa-title" class="text-xl md:text-2xl font-semibold text-white">
              Detalle de Auditoría AUD-' . str_pad($qa["id"], 3, '0', STR_PAD_LEFT) . '
            </h2>
            <p class="mt-0.5 text-xs md:text-sm text-slate-400">
              Desglose completo de la rúbrica ponderada de calidad.
            </p>
          </div>
          <form method="POST" class="m-0 p-0">
            <input type="hidden" name="active_tab" value="qa" />
            <button type="submit" name="ver_detalle_qa" value="" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition cursor-pointer" title="Cerrar modal">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>
            </button>
          </form>
        </div>

        <div class="flex-1 overflow-y-auto space-y-4 pr-2">';

          if ($isAutoFail) {
              echo '
              <div class="rounded-xl border border-rose-800 bg-rose-950/50 p-4 text-rose-200">
                <div class="flex items-center gap-2 font-bold text-rose-400 text-sm">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                  </svg>
                  Regla de Cero Tolerancia Activada (Error Crítico)
                </div>
                <p class="text-xs text-rose-300/90 mt-1">
                  Esta auditoría registró un incumplimiento severo. Por normativa de SLA, el puntaje total de la evaluación se redujo automáticamente a <strong>0% (Auto-Fail)</strong>.
                </p>
              </div>';
          }

    echo '
          <div class="grid grid-cols-2 gap-4 rounded-xl border border-slate-700 bg-slate-900/60 p-4">
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Ticket Evaluado</span>
              <span class="text-sm font-medium text-cyan-400">' . htmlspecialchars($tktDisplay) . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Auditor</span>
              <span class="text-sm font-medium text-white">' . htmlspecialchars($qa["auditor"]) . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Agente Evaluado</span>
              <span class="text-sm font-medium text-white">' . htmlspecialchars($qa["agente_evaluado"]) . '</span>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Fecha de Evaluación</span>
              <span class="text-sm font-medium text-white">' . date('d/m/Y H:i', strtotime($qa["fecha"])) . '</span>
            </div>
          </div>

          <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4 space-y-3">
            <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-400">Puntajes por Criterio Ponderado</span>
            
            <div class="flex justify-between items-center text-sm border-b border-slate-800 pb-2">
              <span class="text-slate-300">1. Diagnóstico Técnico (30%)</span>
              <span class="font-mono text-cyan-400 font-semibold">' . $scoreDiag . '% (' . $ptsDiag . ' / 30 pts)</span>
            </div>

            <div class="flex justify-between items-center text-sm border-b border-slate-800 pb-2">
              <span class="text-slate-300">2. Adherencia a Procesos (30%)</span>
              <span class="font-mono text-cyan-400 font-semibold">' . $scoreProc . '% (' . $ptsProc . ' / 30 pts)</span>
            </div>

            <div class="flex justify-between items-center text-sm border-b border-slate-800 pb-2">
              <span class="text-slate-300">3. Redacción y Tono (20%)</span>
              <span class="font-mono text-cyan-400 font-semibold">' . $scoreRed . '% (' . $ptsRed . ' / 20 pts)</span>
            </div>

            <div class="flex justify-between items-center text-sm">
              <span class="text-slate-300">4. Eficiencia (20%)</span>
              <span class="font-mono text-cyan-400 font-semibold">' . $scoreEfic . '% (' . $ptsEfic . ' / 20 pts)</span>
            </div>
          </div>

          <div class="rounded-xl ' . ($isAutoFail ? 'border border-rose-800/80 bg-rose-950/40' : 'border border-emerald-800/60 bg-emerald-950/20') . ' p-4 flex items-center justify-between">
            <div>
              <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Score Final Total</span>
              <span class="text-3xl font-extrabold ' . ($isAutoFail ? 'text-rose-500' : 'text-emerald-400') . '">' . intval($qa["puntaje"]) . '%</span>
            </div>
            <div>
              <span class="' . $resBadgeClass . '">' . $resultadoText . '</span>
            </div>
          </div>

          <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Observaciones del Auditor</span>
            <p class="text-sm text-slate-300 italic bg-slate-950/40 p-3 rounded-lg border border-slate-800">
              "' . htmlspecialchars($qa["observaciones"] ?? "Sin observaciones escritas.") . '"
            </p>
          </div>
        </div>

        <div class="flex-shrink-0 flex items-center justify-end pt-3 mt-3 border-t border-slate-800">
          <form method="POST" class="m-0 p-0">
            <input type="hidden" name="active_tab" value="qa" />
            <button type="submit" name="ver_detalle_qa" value="" class="btn-registrar">
              Cerrar Detalle
            </button>
          </form>
        </div>
      </div>
    </div>';
}

// ==========================================
// FUNCIONES DE MATRIZ CAPA Y COACHING (HU 34)
// ==========================================

function obtenerCAPACoaching($conn) {
    // Verificar si las columnas extendidas ya existen en capa_coaching
    static $colsChecked = false;
    static $hasExtendedCols = false;
    if (!$colsChecked) {
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'causa_raiz'");
        $hasExtendedCols = ($chk && mysqli_num_rows($chk) > 0);
        $colsChecked = true;
    }

    if ($hasExtendedCols) {
        $selectCols = "c.*, 
                       COALESCE(c.causa_raiz, c.hallazgo) AS causa_raiz,
                       COALESCE(c.accion_propuesta, c.plan_accion) AS accion_propuesta,
                       COALESCE(c.agente_responsable, c.agente_area, c.responsable) AS agente_responsable,
                       COALESCE(c.fecha_limite, c.fecha_compromiso) AS fecha_limite,
                       q.puntaje AS eval_puntaje,
                       q.resultado AS eval_resultado,
                       q.agente_evaluado AS eval_agente,
                       q.ticket_id AS eval_ticket_id";
        $joinQA = "LEFT JOIN qa_auditorias q ON c.evaluacion_id = q.id";
    } else {
        $selectCols = "c.*, 
                       c.hallazgo AS causa_raiz,
                       c.plan_accion AS accion_propuesta,
                       COALESCE(c.agente_area, c.responsable) AS agente_responsable,
                       c.fecha_compromiso AS fecha_limite,
                       NULL AS eval_puntaje,
                       NULL AS eval_resultado,
                       NULL AS eval_agente,
                       NULL AS eval_ticket_id";
        $joinQA = "";
    }

    $sql = "SELECT $selectCols FROM capa_coaching c $joinQA WHERE 1=1";

    if (!empty($_POST["inputBuscarCAPA"])) {
        $buscar = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["inputBuscarCAPA"]));
        
        $idBuscar = "";
        if (preg_match('/^(?:CAPA|PLAN)-(\d+)$/i', trim($buscar), $matches)) {
            $idBuscar = intval($matches[1]);
        }
        $audBuscar = "";
        if (preg_match('/^AUD-(\d+)$/i', trim($buscar), $audMatches)) {
            $audBuscar = intval($audMatches[1]);
        }

        if ($hasExtendedCols) {
            $sql .= " AND (
                c.causa_raiz LIKE '%$buscar%' 
                OR c.hallazgo LIKE '%$buscar%'
                OR c.accion_propuesta LIKE '%$buscar%' 
                OR c.plan_accion LIKE '%$buscar%'
                OR c.agente_responsable LIKE '%$buscar%' 
                OR c.agente_area LIKE '%$buscar%'
                OR c.responsable LIKE '%$buscar%'
                OR q.agente_evaluado LIKE '%$buscar%'
            ";
            if ($idBuscar !== "") {
                $sql .= " OR c.id = $idBuscar";
            }
            if ($audBuscar !== "") {
                $sql .= " OR c.evaluacion_id = $audBuscar";
            }
            $sql .= " )";
        } else {
            $sql .= " AND (
                c.hallazgo LIKE '%$buscar%'
                OR c.plan_accion LIKE '%$buscar%'
                OR c.agente_area LIKE '%$buscar%'
                OR c.responsable LIKE '%$buscar%'
            ";
            if ($idBuscar !== "") {
                $sql .= " OR c.id = $idBuscar";
            }
            $sql .= " )";
        }
    }

    if (!empty($_POST["tipoBuscarCAPA"]) && $_POST["tipoBuscarCAPA"] !== "todos") {
        $tipo = mysqli_real_escape_string($conn, $_POST["tipoBuscarCAPA"]);
        if ($tipo === "Acción Correctiva") {
            $sql .= " AND (c.tipo = 'Acción Correctiva' OR c.tipo = 'CAPA Correctiva')";
        } else {
            $sql .= " AND c.tipo = '$tipo'";
        }
    }

    if (!empty($_POST["estadoBuscarCAPA"]) && $_POST["estadoBuscarCAPA"] !== "todos") {
        $estado = mysqli_real_escape_string($conn, $_POST["estadoBuscarCAPA"]);
        $fechaComp = $hasExtendedCols ? "COALESCE(c.fecha_limite, c.fecha_compromiso)" : "c.fecha_compromiso";
        if ($estado === "Vencido") {
            $sql .= " AND (c.estado = 'Vencido' OR (c.estado NOT IN ('Completado', 'Verificado', 'Cerrado') AND $fechaComp < CURDATE()))";
        } else if ($estado === "Pendiente") {
            $sql .= " AND (c.estado = 'Pendiente' OR c.estado = 'Abierto') AND ($fechaComp >= CURDATE() OR $fechaComp IS NULL)";
        } else if ($estado === "En Proceso") {
            $sql .= " AND c.estado = 'En Proceso' AND ($fechaComp >= CURDATE() OR $fechaComp IS NULL)";
        } else if ($estado === "Completado") {
            $sql .= " AND c.estado IN ('Completado', 'Verificado', 'Cerrado')";
        } else {
            $sql .= " AND c.estado = '$estado'";
        }
    }

    $sql .= " ORDER BY c.id DESC";
    $query = mysqli_query($conn, $sql);
    if (!$query) return [];
    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function obtenerCAPAStats($conn) {
    static $colsCheckedStats = false;
    static $hasExtendedColsStats = false;
    if (!$colsCheckedStats) {
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'fecha_limite'");
        $hasExtendedColsStats = ($chk && mysqli_num_rows($chk) > 0);
        $colsCheckedStats = true;
    }

    $fechaCol = $hasExtendedColsStats ? "COALESCE(fecha_limite, fecha_compromiso)" : "fecha_compromiso";

    $query = mysqli_query($conn, "
        SELECT 
            COUNT(id) AS total,
            SUM(CASE WHEN (estado = 'Pendiente' OR estado = 'Abierto') AND ($fechaCol >= CURDATE() OR $fechaCol IS NULL) THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'En Proceso' AND ($fechaCol >= CURDATE() OR $fechaCol IS NULL) THEN 1 ELSE 0 END) AS en_proceso,
            SUM(CASE WHEN estado IN ('Completado', 'Verificado', 'Cerrado') THEN 1 ELSE 0 END) AS completados,
            SUM(CASE WHEN estado = 'Vencido' OR (estado NOT IN ('Completado', 'Verificado', 'Cerrado') AND $fechaCol < CURDATE()) THEN 1 ELSE 0 END) AS vencidos
        FROM capa_coaching
    ");
    if (!$query) {
        return ['total' => 0, 'pendientes' => 0, 'en_proceso' => 0, 'completados' => 0, 'vencidos' => 0, 'tasa_cumplimiento' => 0];
    }
    $res = mysqli_fetch_assoc($query);
    $res['total'] = intval($res['total'] ?? 0);
    $res['pendientes'] = intval($res['pendientes'] ?? 0);
    $res['en_proceso'] = intval($res['en_proceso'] ?? 0);
    $res['completados'] = intval($res['completados'] ?? 0);
    $res['vencidos'] = intval($res['vencidos'] ?? 0);
    $res['tasa_cumplimiento'] = $res['total'] > 0 ? round(($res['completados'] / $res['total']) * 100) : 0;
    return $res;
}

function registrarCAPACoaching($conn) {
    if (!empty($_POST["guardar_capa"])) {
        $evaluacion_id = !empty($_POST["evaluacion_id"]) ? intval($_POST["evaluacion_id"]) : "NULL";
        $tipo = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["tipo"] ?? "Acción Correctiva"));
        $origen = ($evaluacion_id !== "NULL") ? "Auditoría Deficiente" : mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["origen"] ?? "Auditoría QA"));
        $agente_responsable = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["agente_responsable"] ?? ""));
        $causa_raiz = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["causa_raiz"] ?? ""));
        $accion_propuesta = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["accion_propuesta"] ?? ""));
        $estado = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["estado"] ?? "Pendiente"));
        $fecha_limite = !empty($_POST["fecha_limite"]) ? mysqli_real_escape_string($conn, $_POST["fecha_limite"]) : date('Y-m-d', strtotime('+14 days'));
        
        // Compatibilidad con columnas legadas
        $agente_area = $agente_responsable;
        $hallazgo = $causa_raiz;
        $plan_accion = $accion_propuesta;
        $responsable = !empty($_SESSION["usuario"]) ? $_SESSION["usuario"] : "SLA Lead";
        $fecha_compromiso = $fecha_limite;

        // Auto-detección si la fecha límite ingresada ya venció y el estado no es completado
        if ($estado !== "Completado" && strtotime($fecha_limite) < strtotime('today')) {
            $estado = "Vencido";
        }

        if (!empty($causa_raiz) && !empty($accion_propuesta) && !empty($agente_responsable)) {
            $chkCols = mysqli_query($conn, "SHOW COLUMNS FROM `capa_coaching` LIKE 'causa_raiz'");
            $hasCausa = ($chkCols && mysqli_num_rows($chkCols) > 0);
            if ($hasCausa) {
                $evalVal = ($evaluacion_id !== "NULL") ? $evaluacion_id : "NULL";
                $query = "INSERT INTO capa_coaching (evaluacion_id, tipo, origen, agente_responsable, agente_area, causa_raiz, accion_propuesta, hallazgo, plan_accion, responsable, estado, fecha_limite, fecha_compromiso)
                          VALUES ($evalVal, '$tipo', '$origen', '$agente_responsable', '$agente_area', '$causa_raiz', '$accion_propuesta', '$hallazgo', '$plan_accion', '$responsable', '$estado', '$fecha_limite', '$fecha_compromiso')";
            } else {
                $query = "INSERT INTO capa_coaching (tipo, origen, agente_area, hallazgo, plan_accion, responsable, estado, fecha_compromiso)
                          VALUES ('$tipo', '$origen', '$agente_area', '$hallazgo', '$plan_accion', '$responsable', '$estado', '$fecha_compromiso')";
            }
            
            if (mysqli_query($conn, $query)) {
                $insertedId = mysqli_insert_id($conn);
                $evalText = ($evalVal !== "NULL") ? " vinculada a AUD-" . str_pad($evalVal, 3, '0', STR_PAD_LEFT) : "";
                $_SESSION["msg"] = '<div class="toast-success">✅ Plan de acción correctivo <strong>PLAN-' . str_pad($insertedId, 3, '0', STR_PAD_LEFT) . '</strong> registrado exitosamente' . $evalText . '.</div>';
            } else {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al registrar plan de acción correctivo: ' . mysqli_error($conn) . '</div>';
            }
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Completa todos los campos obligatorios: Causa Raíz, Acción Propuesta, Agente Responsable y Fecha Límite.</div>';
        }
        redirigirFeedback("feedback.php?tab=capa");
    }
}

function actualizarEstadoCAPA($conn) {
    if (!empty($_POST["cambiar_estado_capa"])) {
        $id = intval($_POST["capa_id"] ?? 0);
        $nuevoEstado = mysqli_real_escape_string($conn, normalizarTextoFeedback($_POST["nuevo_estado"] ?? ""));
        $estadosPermitidos = ['Pendiente', 'En Proceso', 'Completado', 'Vencido'];
        
        if ($id > 0 && in_array($nuevoEstado, $estadosPermitidos)) {
            $query = "UPDATE capa_coaching SET estado = '$nuevoEstado' WHERE id = $id";
            if (mysqli_query($conn, $query)) {
                $_SESSION["msg"] = '<div class="toast-success">✅ Estado del plan <strong>PLAN-' . str_pad($id, 3, '0', STR_PAD_LEFT) . '</strong> actualizado a: <strong>' . htmlspecialchars($nuevoEstado) . '</strong>.</div>';
            } else {
                $_SESSION["msg"] = '<div class="toast-error">❌ Error al actualizar el estado de la acción correctiva.</div>';
            }
        }
        redirigirFeedback("feedback.php?tab=capa");
    }
}

function mostrarModalRegistrarCAPA($conn, $preEvaluacionId = null) {
    // Obtener evaluaciones de calidad para vincular
    $evalsQuery = mysqli_query($conn, "
        SELECT q.id, q.ticket_id, q.auditor, q.agente_evaluado, q.puntaje, q.resultado, q.observaciones, q.fecha, q.error_critico, t.titulo AS ticket_titulo
        FROM qa_auditorias q
        LEFT JOIN tickets t ON q.ticket_id = t.id
        ORDER BY q.id DESC
    ");
    $evaluaciones = $evalsQuery ? mysqli_fetch_all($evalsQuery, MYSQLI_ASSOC) : [];

    $preEvaluacionId = !empty($preEvaluacionId) ? intval($preEvaluacionId) : 0;
    $preAgente = "";
    $preObservaciones = "";
    $preScore = "";
    $preResultado = "";

    if ($preEvaluacionId > 0) {
        foreach ($evaluaciones as $ev) {
            if ($ev["id"] == $preEvaluacionId) {
                $preAgente = $ev["agente_evaluado"];
                $preObservaciones = $ev["observaciones"];
                $preScore = $ev["puntaje"];
                $preResultado = $ev["resultado"];
                break;
            }
        }
    }

    echo '
    <div role="dialog" id="create-capa-modal" aria-labelledby="create-capa-title" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 overflow-y-auto">
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl my-8" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <div class="mb-5 flex items-start justify-between border-b border-slate-700/60 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded text-xs font-bold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">HU 34</span>
              <h2 id="create-capa-title" class="text-xl font-bold text-white">
                Registro de Planes de Acción Correctivos
              </h2>
            </div>
            <p class="mt-1 text-xs text-slate-400">
              Registra y da seguimiento a acciones correctivas derivadas de auditorías deficientes para gestionar correcciones de raíz.
            </p>
          </div>
          <form method="POST" class="m-0 p-0">
            <input type="hidden" name="active_tab" value="capa" />
            <button type="submit" class="text-slate-400 hover:text-white transition text-lg font-bold p-1">✕</button>
          </form>
        </div>

        <form class="space-y-4" method="POST">
          <input type="hidden" name="active_tab" value="capa" />

          <!-- Criterio 1: Campo ID de Evaluación Vinculada -->
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-cyan-400">
              ID de Evaluación Vinculada (Auditoría Deficiente) *
            </label>
            <select name="evaluacion_id" id="capa_evaluacion_id" class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500" onchange="seleccionarEvaluacionCAPA(this)">
              <option value="">-- Sin Evaluación Vinculada (General / Preventivo) --</option>
              
              <optgroup label="⚠️ Auditorías Deficientes (Prioridad de Mejora Continua)">
                ';
                foreach ($evaluaciones as $ev) {
                    $esDeficiente = ($ev["resultado"] !== "Conforme" || intval($ev["puntaje"]) < 80 || !empty($ev["error_critico"]));
                    if ($esDeficiente) {
                        $selected = ($preEvaluacionId === intval($ev["id"])) ? 'selected' : '';
                        $icono = !empty($ev["error_critico"]) ? '🚨 Auto-Fail' : '⚠️ ' . htmlspecialchars($ev["resultado"]);
                        echo '<option value="' . $ev["id"] . '" ' . $selected . ' 
                              data-agente="' . htmlspecialchars($ev["agente_evaluado"]) . '" 
                              data-observaciones="' . htmlspecialchars($ev["observaciones"] ?? "") . '" 
                              data-score="' . intval($ev["puntaje"]) . '" 
                              data-resultado="' . htmlspecialchars($ev["resultado"]) . '" 
                              data-ticket="' . htmlspecialchars($ev["ticket_id"] ?? "") . '">
                              AUD-' . str_pad($ev["id"], 3, '0', STR_PAD_LEFT) . ' | ' . htmlspecialchars($ev["agente_evaluado"]) . ' — ' . intval($ev["puntaje"]) . '% (' . $icono . ')' . ($ev["ticket_id"] ? ' [TKT-' . str_pad($ev["ticket_id"], 3, '0', STR_PAD_LEFT) . ']' : '') . '
                        </option>';
                    }
                }
                echo '
              </optgroup>

              <optgroup label="Auditorías Conformes">
                ';
                foreach ($evaluaciones as $ev) {
                    $esDeficiente = ($ev["resultado"] !== "Conforme" || intval($ev["puntaje"]) < 80 || !empty($ev["error_critico"]));
                    if (!$esDeficiente) {
                        $selected = ($preEvaluacionId === intval($ev["id"])) ? 'selected' : '';
                        echo '<option value="' . $ev["id"] . '" ' . $selected . ' 
                              data-agente="' . htmlspecialchars($ev["agente_evaluado"]) . '" 
                              data-observaciones="' . htmlspecialchars($ev["observaciones"] ?? "") . '" 
                              data-score="' . intval($ev["puntaje"]) . '" 
                              data-resultado="' . htmlspecialchars($ev["resultado"]) . '" 
                              data-ticket="' . htmlspecialchars($ev["ticket_id"] ?? "") . '">
                              AUD-' . str_pad($ev["id"], 3, '0', STR_PAD_LEFT) . ' | ' . htmlspecialchars($ev["agente_evaluado"]) . ' — ' . intval($ev["puntaje"]) . '% (Conforme)
                        </option>';
                    }
                }
                echo '
              </optgroup>
            </select>
          </div>

          <!-- Banner interactivo de la evaluación seleccionada -->
          <div id="eval_info_banner" class="rounded-xl border border-slate-700 bg-slate-900/60 p-3 text-xs text-slate-300 ' . ($preEvaluacionId > 0 ? '' : 'hidden') . '">
            <div class="flex items-center justify-between mb-1.5">
              <span class="font-semibold text-cyan-300 flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Detalle de Auditoría Vinculada:
              </span>
              <span id="eval_info_badge" class="badge-custom badge-queja text-[11px] font-bold">
                ' . ($preResultado ? htmlspecialchars($preResultado) . ' (' . intval($preScore) . '%)' : '') . '
              </span>
            </div>
            <p id="eval_info_obs" class="italic text-slate-400 bg-slate-950/40 p-2 rounded border border-slate-800">
              "' . htmlspecialchars($preObservaciones ?: 'Selecciona una auditoría para previsualizar observaciones.') . '"
            </p>
            <div class="flex justify-end mt-2">
              <button type="button" onclick="copiarObservacionACausa()" class="text-xs text-amber-400 hover:text-amber-300 flex items-center gap-1 font-medium transition">
                ⚡ Copiar hallazgo del auditor a Causa Raíz
              </button>
            </div>
          </div>

          <!-- Criterio 2: Causa Raíz -->
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-300">
              Causa Raíz *
            </label>
            <textarea name="causa_raiz" id="capa_causa_raiz" required rows="2" placeholder="Identificar la causa fundamental de la deficiencia detectada en la evaluación..." class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">' . htmlspecialchars($preObservaciones ? 'Hallazgo auditoría: ' . $preObservaciones : '') . '</textarea>
          </div>

          <!-- Criterio 2: Acción Propuesta -->
          <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-300">
              Acción Propuesta *
            </label>
            <textarea name="accion_propuesta" id="capa_accion_propuesta" required rows="2" placeholder="Detallar la acción correctiva, capacitación o ajuste de procesos para resolver la causa raíz..." class="w-full resize-none rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
          </div>

          <!-- Criterio 2: Agente Responsable y Fecha Límite de Resolución -->
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-300">
                Agente Responsable *
              </label>
              <input type="text" name="agente_responsable" id="capa_agente_responsable" required value="' . htmlspecialchars($preAgente ?: '') . '" placeholder="Ej. soporte_t1 / desarrollo / analista" class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>

            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-300">
                Fecha Límite de Resolución *
              </label>
              <input type="date" name="fecha_limite" id="capa_fecha_limite" required value="' . date('Y-m-d', strtotime('+14 days')) . '" class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
          </div>

          <!-- Tipo de Plan y Criterio 3: Estado Inicial (Pendiente, En Proceso, Completado, Vencido) -->
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-300">Tipo de Plan *</label>
              <select name="tipo" required class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="Acción Correctiva" selected>Acción Correctiva (CAPA)</option>
                <option value="CAPA Preventiva">CAPA Preventiva</option>
                <option value="Sesión Coaching">Sesión Coaching</option>
              </select>
            </div>

            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-300">Estado Inicial *</label>
              <select name="estado" required class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="Pendiente" selected>⏳ Pendiente</option>
                <option value="En Proceso">⚙️ En Proceso</option>
                <option value="Completado">✅ Completado</option>
                <option value="Vencido">⚠️ Vencido</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-700/60">
            <button type="submit" name="FormCAPA" value="" class="rounded-xl border border-slate-600 px-5 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800" formnovalidate>
              Cancelar
            </button>
            <button type="submit" name="guardar_capa" value="1" class="btn-registrar">
              Guardar Plan Correctivo
            </button>
          </div>
        </form>
      </div>
    </div>

    <script>
    function seleccionarEvaluacionCAPA(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const banner = document.getElementById("eval_info_banner");
        const agenteInput = document.getElementById("capa_agente_responsable");
        const badgeEl = document.getElementById("eval_info_badge");
        const obsEl = document.getElementById("eval_info_obs");

        if (opt && opt.value) {
            const agente = opt.getAttribute("data-agente");
            const obs = opt.getAttribute("data-observaciones") || "Sin observaciones específicas registradas.";
            const score = opt.getAttribute("data-score");
            const res = opt.getAttribute("data-resultado");

            if (agenteInput && agente && !agenteInput.value) {
                agenteInput.value = agente;
            }
            if (badgeEl) {
                badgeEl.innerText = res + " (" + score + "%)";
                badgeEl.className = (parseInt(score) < 80 || res === "Auto-Fail") ? "badge-custom badge-queja text-[11px] font-bold" : "badge-custom badge-positivo text-[11px] font-bold";
            }
            if (obsEl) {
                obsEl.innerText = \'"\' + obs + \'"\';
            }
            if (banner) {
                banner.classList.remove("hidden");
            }
        } else {
            if (banner) {
                banner.classList.add("hidden");
            }
        }
    }

    function copiarObservacionACausa() {
        const obsEl = document.getElementById("eval_info_obs");
        const causaTextarea = document.getElementById("capa_causa_raiz");
        if (obsEl && causaTextarea) {
            let texto = obsEl.innerText.replace(/^"|"$/g, "");
            causaTextarea.value = "Causa raíz identificada tras auditoría: " + texto;
            causaTextarea.focus();
        }
    }
    </script>
    ';
}

function mostrarModalDetalleCAPA($conn, $capaId) {
    $capaId = intval($capaId);
    $query = mysqli_query($conn, "
        SELECT c.*, 
               COALESCE(c.causa_raiz, c.hallazgo) AS causa_raiz,
               COALESCE(c.accion_propuesta, c.plan_accion) AS accion_propuesta,
               COALESCE(c.agente_responsable, c.agente_area, c.responsable) AS agente_responsable,
               COALESCE(c.fecha_limite, c.fecha_compromiso) AS fecha_limite,
               q.id AS eval_id,
               q.puntaje AS eval_puntaje,
               q.resultado AS eval_resultado,
               q.agente_evaluado AS eval_agente,
               q.observaciones AS eval_observaciones,
               q.auditor AS eval_auditor,
               q.error_critico AS eval_error_critico,
               t.id AS eval_ticket_id,
               t.titulo AS eval_ticket_titulo
        FROM capa_coaching c
        LEFT JOIN qa_auditorias q ON c.evaluacion_id = q.id
        LEFT JOIN tickets t ON q.ticket_id = t.id
        WHERE c.id = $capaId
    ");
    if (!$query || mysqli_num_rows($query) === 0) return;
    $plan = mysqli_fetch_assoc($query);

    $isVencido = ($plan["estado"] === "Vencido" || ($plan["estado"] !== "Completado" && !empty($plan["fecha_limite"]) && strtotime($plan["fecha_limite"]) < strtotime('today')));
    $effectiveEstado = $isVencido ? "Vencido" : $plan["estado"];

    $estadoBadge = "badge-capa-pendiente";
    if ($effectiveEstado === "En Proceso") $estadoBadge = "badge-capa-proceso";
    else if ($effectiveEstado === "Completado") $estadoBadge = "badge-capa-completado";
    else if ($effectiveEstado === "Vencido") $estadoBadge = "badge-capa-vencido";

    echo '
    <div role="dialog" id="detail-capa-modal" aria-labelledby="detail-capa-title" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 overflow-y-auto">
      <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl my-8" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">
        
        <div class="mb-5 flex items-start justify-between border-b border-slate-700/60 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-cyan-400 font-bold text-lg font-mono">PLAN-' . str_pad($plan["id"], 3, "0", STR_PAD_LEFT) . '</span>
              <span class="badge-custom ' . $estadoBadge . '">' . htmlspecialchars($effectiveEstado) . '</span>
              <span class="badge-custom badge-default text-xs">' . htmlspecialchars($plan["tipo"]) . '</span>
            </div>
            <h2 id="detail-capa-title" class="text-xl font-bold text-white mt-1">
              Seguimiento de Plan de Acción Correctivo
            </h2>
          </div>
          <form method="POST" class="m-0 p-0">
            <input type="hidden" name="active_tab" value="capa" />
            <button type="submit" class="text-slate-400 hover:text-white transition text-lg font-bold p-1">✕</button>
          </form>
        </div>

        <div class="space-y-4 text-sm">
          <!-- Información de la Evaluación vinculada -->
          ';
          if (!empty($plan["eval_id"])) {
              $scoreClass = intval($plan["eval_puntaje"]) < 80 ? "text-rose-400" : "text-emerald-400";
              echo '
              <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs font-semibold uppercase tracking-wider text-cyan-400">Auditoría Deficiente Vinculada</span>
                  <span class="font-bold ' . $scoreClass . '">AUD-' . str_pad($plan["eval_id"], 3, '0', STR_PAD_LEFT) . ' (' . intval($plan["eval_puntaje"]) . '% - ' . htmlspecialchars($plan["eval_resultado"]) . ')</span>
                </div>
                <div class="text-xs text-slate-300 space-y-1">
                  <div><strong>Auditor:</strong> ' . htmlspecialchars($plan["eval_auditor"] ?? "N/A") . '</div>
                  ' . (!empty($plan["eval_ticket_id"]) ? '<div><strong>Ticket Relacionado:</strong> TKT-' . str_pad($plan["eval_ticket_id"], 3, '0', STR_PAD_LEFT) . ' - ' . htmlspecialchars($plan["eval_ticket_titulo"]) . '</div>' : '') . '
                  <div class="mt-2 text-slate-400 italic bg-slate-950/40 p-2.5 rounded border border-slate-800">
                    "' . htmlspecialchars($plan["eval_observaciones"] ?? "Sin observaciones registradas.") . '"
                  </div>
                </div>
              </div>';
          } else {
              echo '
              <div class="rounded-xl border border-slate-700 bg-slate-900/40 p-3 text-xs text-slate-400">
                <strong>Evaluación Vinculada:</strong> Plan general de aseguramiento de calidad (sin auditoría directa).
              </div>';
          }
          echo '

          <!-- Causa Raíz -->
          <div class="rounded-xl border border-amber-900/40 bg-amber-950/20 p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-amber-400 mb-1.5 flex items-center gap-1.5">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              Causa Raíz Identificada
            </span>
            <p class="text-white text-sm whitespace-pre-line leading-relaxed">
              ' . htmlspecialchars($plan["causa_raiz"]) . '
            </p>
          </div>

          <!-- Acción Propuesta -->
          <div class="rounded-xl border border-emerald-900/40 bg-emerald-950/20 p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-emerald-400 mb-1.5 flex items-center gap-1.5">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              Acción Propuesta / Plan Correctivo
            </span>
            <p class="text-white text-sm whitespace-pre-line leading-relaxed">
              ' . htmlspecialchars($plan["accion_propuesta"]) . '
            </p>
          </div>

          <!-- Metadatos: Agente y Fechas -->
          <div class="grid grid-cols-2 gap-4 text-xs">
            <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-3">
              <span class="text-slate-400 block mb-1">Agente Responsable</span>
              <span class="font-semibold text-white text-sm">' . htmlspecialchars($plan["agente_responsable"]) . '</span>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-3">
              <span class="text-slate-400 block mb-1">Fecha Límite de Resolución</span>
              <span class="font-semibold font-mono text-sm ' . ($isVencido ? 'text-rose-400 font-bold' : 'text-slate-200') . '">
                ' . htmlspecialchars($plan["fecha_limite"]) . ' ' . ($isVencido ? '(⚠️ Vencido)' : '') . '
              </span>
            </div>
          </div>

          <!-- Formulario para Cambiar Estado (Criterio 3: Pendiente, En Proceso, Completado, Vencido) -->
          <div class="rounded-xl border border-slate-700 bg-slate-900/80 p-4">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Gestionar Estado de Seguimiento</span>
            <form method="POST" class="flex flex-wrap items-center gap-3 m-0">
              <input type="hidden" name="active_tab" value="capa" />
              <input type="hidden" name="capa_id" value="' . $plan["id"] . '" />
              <input type="hidden" name="cambiar_estado_capa" value="1" />
              
              <select name="nuevo_estado" class="flex-1 rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="Pendiente" ' . ($effectiveEstado === "Pendiente" ? "selected" : "") . '>⏳ Pendiente</option>
                <option value="En Proceso" ' . ($effectiveEstado === "En Proceso" ? "selected" : "") . '>⚙️ En Proceso</option>
                <option value="Completado" ' . ($effectiveEstado === "Completado" ? "selected" : "") . '>✅ Completado</option>
                <option value="Vencido" ' . ($effectiveEstado === "Vencido" ? "selected" : "") . '>⚠️ Vencido</option>
              </select>

              <button type="submit" class="btn-registrar py-2 px-4 text-xs font-semibold">
                Actualizar Estado
              </button>
            </form>
          </div>

        </div>

        <div class="flex items-center justify-end pt-4 mt-4 border-t border-slate-800">
          <form method="POST" class="m-0 p-0">
            <input type="hidden" name="active_tab" value="capa" />
            <button type="submit" class="rounded-xl border border-slate-600 px-5 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800">
              Cerrar
            </button>
          </form>
        </div>

      </div>
    </div>';
}
?>

