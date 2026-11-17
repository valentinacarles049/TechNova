-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-09-2026 a las 20:33:06
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `technova`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capa_coaching`
--

CREATE TABLE `capa_coaching` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `origen` varchar(100) NOT NULL,
  `agente_area` varchar(100) NOT NULL,
  `hallazgo` text NOT NULL,
  `plan_accion` text NOT NULL,
  `responsable` varchar(100) NOT NULL,
  `estado` varchar(50) NOT NULL DEFAULT 'Abierto',
  `fecha_compromiso` date DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `capa_coaching`
--

INSERT INTO `capa_coaching` (`id`, `tipo`, `origen`, `agente_area`, `hallazgo`, `plan_accion`, `responsable`, `estado`, `fecha_compromiso`, `fecha_creacion`) VALUES
(201, 'CAPA Correctiva', 'Auditoría QA', 'support', 'Inconsistencias en el completado de documentación previa a escalación.', 'Revisión obligatoria del checklist de escalamiento previa asignación a desarrollo.', 'SLA Lead', 'En Proceso', '2026-11-30', '2026-11-15 15:00:00'),
(202, 'Sesión Coaching', 'Bajo CSAT', 'dev_lead', 'Observación de lenguaje excesivamente técnico en notas dirigidas al usuario final.', 'Sesión de coaching 1-a-1 sobre comunicación clara y empática con el cliente.', 'QA Supervisor', 'Abierto', '2026-12-05', '2026-11-15 18:00:00'),
(203, 'CAPA Preventiva', 'Desviación SLA', 'Infraestructura', 'Cuellos de botella potenciales en horarios pico de entrega de parches de drivers.', 'Implementar CDN de respaldo y monitoreo proactivo de latencia en descargas.', 'DevOps Lead', 'Verificado', '2026-11-28', '2026-11-16 12:30:00'),
(204, 'Sesión Coaching', 'Revisión Periódica', 'support', 'Retroalimentación positiva en manejo de clientes frustrados.', 'Documentación de caso de éxito para compartir como buena práctica de equipo.', 'SLA Lead', 'Cerrado', '2026-11-20', '2026-11-16 13:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones`
--

CREATE TABLE `configuraciones` (
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuraciones`
--

INSERT INTO `configuraciones` (`clave`, `valor`) VALUES
('ultima_semana_notificada', '2026-40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `version` varchar(200) NOT NULL,
  `fecha` date NOT NULL,
  `tamanio` int(11) NOT NULL,
  `categoriaDriver` varchar(50) DEFAULT NULL,
  `estado` varchar(50) NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `drivers`
--

INSERT INTO `drivers` (`id`, `nombre`, `version`, `fecha`, `tamanio`, `categoriaDriver`, `estado`) VALUES
(1, 'NVIDIA GeForce Game Ready Driver', '545.84', '2025-10-15', 720, 'Tarjeta Gráfica', 'activo'),
(2, 'NVIDIA Studio Driver', '545.92', '2025-10-28', 730, 'Tarjeta Gráfica', 'activo'),
(3, 'NVIDIA GeForce Experience', '3.27.0.120', '2025-09-20', 180, 'Utilidad', 'activo'),
(4, 'NVIDIA PhysX System Software', '9.23.1026', '2025-10-05', 42, 'Motor de Física', 'activo'),
(5, 'NVIDIA CUDA Toolkit', '12.3.2', '2025-09-15', 3, 'Desarrollo', 'activo'),
(6, 'NVIDIA RTX Voice', '0.5.12.9', '2025-08-22', 125, 'Audio AI', 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `usuario` varchar(100) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `tema` varchar(255) DEFAULT NULL,
  `sentimiento` varchar(50) DEFAULT NULL,
  `calificacion` int(11) DEFAULT NULL,
  `sla_cumplido` tinyint(1) DEFAULT NULL,
  `estado` varchar(50) DEFAULT 'PendienteEnvio',
  `comentario` text DEFAULT NULL,
  `motivo_cancelacion` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_envio` timestamp NULL DEFAULT NULL,
  `fecha_respuesta` timestamp NULL DEFAULT NULL,
  `fecha_expiracion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `feedbacks`
--

INSERT INTO feedbacks
        (ticket_id, usuario, tipo, tema, sentimiento, calificacion, sla_cumplido, estado, comentario, fecha)
        VALUES (106, 'pro_sniper_ar', 'Elogio', 'Resolución de sombras Call of Duty', 'Positivo', 5, 1, 'Implementado', 'Excelente la rapidez con la que corrigieron el parpadeo de sombras en la actualización. Ahora el juego corre fluido y sin glitches.', '2026-11-17 00:42:50');
INSERT INTO feedbacks
        (ticket_id, usuario, tipo, tema, sentimiento, calificacion, sla_cumplido, estado, comentario, fecha)
        VALUES (108, 'streamer_cordoba', 'Sugerencia', 'Guía de streaming RTX Voice', 'Positivo', 4, 1, 'Revisado', 'La atención de Lucía fue impecable y rápida. Sería genial que dejen un video tutorial corto en el portal de soporte para los que usamos OBS.', '2026-11-16 01:14:30');
INSERT INTO feedbacks
        (ticket_id, usuario, tipo, tema, sentimiento, calificacion, sla_cumplido, estado, comentario, fecha)
        VALUES (105, 'esteban_dev', 'Queja', 'Detección automática de GPU', 'Neutro', 3, 1, 'Nuevo', 'El problema se solucionó con el perfil, pero el launcher debería detectar automáticamente la tarjeta dedicada sin necesidad de abrir un ticket.', '2026-11-17 00:07:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_tickets`
--

CREATE TABLE `historial_tickets` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `tipo_cambio` varchar(255) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_tickets`
--

INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (101, 'support', 'Ticket registrado en estado Nuevo', '2026-11-16 23:07:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (101, 'admin', 'Ticket asignado formalmente al agente técnico \"dev_lead\" (Estado: Asignado)', '2026-11-16 23:11:10');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (102, 'support', 'Ticket creado con prioridad Alta', '2026-11-16 07:07:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (102, 'marcos_dev', 'Atención técnica iniciada (Estado: En Progreso)', '2026-11-16 08:27:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (102, 'marcos_dev', 'Incidencia técnica registrada: INC-202 (En Investigación)', '2026-11-16 14:07:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (103, 'support', 'Ticket registrado y puesto En Progreso para verificar backup en la nube', '2026-11-16 21:07:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (104, 'matias_soporte', 'Solicitud de información adicional al usuario: DXDiag y logs (Estado: En Espera)', '2026-11-16 01:07:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (105, 'dev_lead', 'Ticket marcado como Resuelto con dictamen técnico de configuración', '2026-11-16 23:07:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (106, 'support', 'Ticket creado con prioridad Crítica', '2026-11-16 21:47:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (106, 'franco_dev', 'Incidencia técnica INC-203 corregida y dictamen aplicado', '2026-11-17 00:37:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (106, 'franco_dev', 'Ticket confirmado y archivado permanentemente (Estado: Cerrado)', '2026-11-17 00:37:50');
INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (107, 'admin', 'Ticket registrado mediante API web de soporte', '2026-11-17 00:37:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencias`
--

CREATE TABLE `incidencias` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `juego_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `detalle` text NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `estado` varchar(50) DEFAULT 'Reportada',
  `severidad` varchar(50) DEFAULT NULL,
  `plazo_mitigacion_horas` int(11) DEFAULT NULL,
  `plazo_correccion_horas` int(11) DEFAULT NULL,
  `fecha_limite_mitigacion` timestamp NULL DEFAULT NULL,
  `fecha_limite_correccion` timestamp NULL DEFAULT NULL,
  `fecha_mitigacion` timestamp NULL DEFAULT NULL,
  `tiempo_efectivo_minutos` int(11) DEFAULT NULL,
  `cumplio_sla_desarrollo` tinyint(1) DEFAULT NULL,
  `alerta_vencimiento_notificada` tinyint(1) NOT NULL DEFAULT 0,
  `dictamen_tecnico` text DEFAULT NULL,
  `solucion_temporal` text DEFAULT NULL,
  `motivo_descarte` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `incidencias`
--

INSERT INTO incidencias
        (id, ticket_id, juego_id, driver_id, detalle, estado, severidad, plazo_mitigacion_horas, plazo_correccion_horas, fecha_creacion, fecha_limite_mitigacion, fecha_limite_correccion, fecha_mitigacion, fecha_cierre, solucion_temporal, dictamen_tecnico, cumplio_sla_desarrollo, tiempo_efectivo_minutos)
        VALUES (201, 101, 3, 1, 'Stuttering y caída de fotogramas durante la carga asíncrona de texturas y compilación de shaders bajo DirectX 12.', 'Mitigada', 'Media', 24, 48, '2026-11-16 23:07:50', '2026-11-17 23:07:50', '2026-11-18 23:07:50', '2026-11-17 00:07:50', NULL, 'Comando de inicio (-force-vulkan) agregado temporalmente para omitir la compilación en segundo plano mientras se publica el parche.', NULL, 1, 60);
INSERT INTO incidencias
        (id, ticket_id, juego_id, driver_id, detalle, estado, severidad, plazo_mitigacion_horas, plazo_correccion_horas, fecha_creacion, fecha_limite_mitigacion, fecha_limite_correccion, fecha_mitigacion, fecha_cierre, solucion_temporal, dictamen_tecnico, cumplio_sla_desarrollo, tiempo_efectivo_minutos)
        VALUES (202, 102, 2, 1, 'Fuga progresiva de memoria VRAM en el buffer de trazado de rayos (Path Tracing) al interactuar con reflejos dinámicos.', 'EnInvestigacion', 'Alta', 12, 24, '2026-11-16 14:07:50', '2026-11-17 02:07:50', '2026-11-17 14:07:50', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO incidencias
        (id, ticket_id, juego_id, driver_id, detalle, estado, severidad, plazo_mitigacion_horas, plazo_correccion_horas, fecha_creacion, fecha_limite_mitigacion, fecha_limite_correccion, fecha_mitigacion, fecha_cierre, solucion_temporal, dictamen_tecnico, cumplio_sla_desarrollo, tiempo_efectivo_minutos)
        VALUES (203, 106, 7, 1, 'Corrupción de buffers de texturas y artefactos visuales en oclusión ambiental tras actualización de controladores.', 'Corregida', 'Crítica', 2, 4, '2026-11-16 21:47:50', '2026-11-16 23:47:50', '2026-11-17 01:47:50', '2026-11-16 23:07:50', '2026-11-17 00:37:50', 'Desactivar oclusión ambiental HBAO+ en menú de opciones avanzadas.', 'Se recompiló el shader de oclusión ambiental con la librería actualizada de NVIDIA SDK y se desplegó en el hotfix v1.04.', 1, 170);
INSERT INTO incidencias
        (id, ticket_id, juego_id, driver_id, detalle, estado, severidad, plazo_mitigacion_horas, plazo_correccion_horas, fecha_creacion, fecha_limite_mitigacion, fecha_limite_correccion, fecha_mitigacion, fecha_cierre, solucion_temporal, dictamen_tecnico, cumplio_sla_desarrollo, tiempo_efectivo_minutos)
        VALUES (204, 107, 8, 1, 'Crash fatal del motor gráfico por timeout de detección y recuperación del controlador (TDR DXGI_ERROR_DEVICE_HUNG).', 'Reportada', 'Crítica', 2, 4, '2026-11-17 00:37:50', '2026-11-17 02:37:50', '2026-11-17 04:37:50', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `juegos`
--

CREATE TABLE `juegos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `estado` varchar(11) NOT NULL DEFAULT 'activo',
  `motor_grafico` varchar(100) DEFAULT 'Desconocido'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `juegos`
--

INSERT INTO `juegos` (`id`, `nombre`, `estado`, `motor_grafico`) VALUES
(1, 'Starfield', 'activo', 'Desconocido'),
(2, 'Cyberpunk 2077', 'activo', 'Desconocido'),
(3, 'Baldur\'s Gate 3', 'activo', 'Desconocido'),
(4, 'Halo Infinite', 'activo', 'Desconocido'),
(5, 'The Witcher 3', 'activo', 'Desconocido'),
(6, 'Elden Ring', 'activo', 'Desconocido'),
(7, 'Call of Duty: Modern Warfare III', 'activo', 'Desconocido'),
(8, 'Red Dead Redemption 2', 'activo', 'Desconocido');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `equipo` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `enlace` varchar(255) NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO notificaciones (usuario_id, equipo, titulo, descripcion, enlace, leido, fecha)
        VALUES (3, 'soporte', 'Ticket Asignado', 'El ticket #101 \"Micro-congelamientos con DirectX 12\" fue asignado al equipo técnico.', 'ticketManegement.php?ver_historial=101', 0, '2026-11-16 23:11:10');
INSERT INTO notificaciones (usuario_id, equipo, titulo, descripcion, enlace, leido, fecha)
        VALUES (6, 'desarrollo', 'Alerta SLA Warning', 'El ticket #102 \"Caída severa de FPS al habilitar Ray Tracing\" ha superado el 75% del tiempo límite.', 'incidentes.php?ver=202', 0, '2026-11-17 00:07:50');
INSERT INTO notificaciones (usuario_id, equipo, titulo, descripcion, enlace, leido, fecha)
        VALUES (9, 'monitoreo', 'Resumen SLA en Peligro', 'Hay 1 ticket técnico en riesgo de incumplimiento SLA en la cola de Desarrollo.', 'dashboard.php', 0, '2026-11-17 00:37:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `qa_auditorias`
--

CREATE TABLE `qa_auditorias` (
  `id` int(11) NOT NULL,
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
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `qa_auditorias`
--

INSERT INTO `qa_auditorias` (`id`, `ticket_id`, `auditor`, `agente_evaluado`, `categoria`, `score_diagnostico`, `score_procesos`, `score_redaccion`, `score_eficiencia`, `error_critico`, `puntaje`, `resultado`, `observaciones`, `fecha`) VALUES
(101, 101, 'SLA Lead', 'support', 'Evaluación Rúbrica Ponderada', 100, 90, 90, 100, 0, 95, 'Conforme', 'Respuesta clara y diagnóstico preciso del problema de shaders DirectX 12.', '2026-11-15 13:00:00'),
(102, 102, 'QA Supervisor', 'dev_lead', 'Evaluación Rúbrica Ponderada', 90, 90, 80, 90, 0, 88, 'Conforme', 'Atención dentro de la ventana de SLA crítica para errores de Ray Tracing.', '2026-11-15 17:30:00'),
(103, 103, 'SLA Lead', 'support', 'Evaluación Rúbrica Ponderada', 70, 70, 80, 70, 0, 72, 'Requiere Mejora', 'Faltó adjuntar captura de pantalla descriptiva en el registro del caso.', '2026-11-16 14:15:00'),
(104, 104, 'QA Supervisor', 'support', 'Evaluación Rúbrica Ponderada', 95, 90, 85, 90, 0, 90, 'Conforme', 'Solución a la sincronización en la nube enviada en tiempo récord.', '2026-11-16 19:45:00'),
(105, 105, 'Administrador', 'support', 'Evaluación con Rúbrica Ponderada', 90, 90, 90, 90, 1, 0, 'Auto-Fail', 'Error en asignación previa de driver.', '2026-11-16 19:44:55'),
(106, 106, 'Gonzalo Peralta', 'support', 'Evaluación con Rúbrica Ponderada', 90, 87, 86, 90, 0, 88, 'Conforme', 'Excelente atención y resolución técnica.', '2026-11-16 20:47:16'),
(107, 107, 'Administrador', 'support', 'Evaluación con Rúbrica Ponderada', 90, 90, 90, 90, 0, 92, 'Conforme', 'Ticket cerrado con conformidad de usuario.', '2026-11-16 20:47:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `qa_muestreo_cola`
--

CREATE TABLE `qa_muestreo_cola` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `agente_evaluado` varchar(100) NOT NULL,
  `motivo_inclusion` varchar(255) NOT NULL,
  `tipo_trigger` varchar(50) NOT NULL DEFAULT 'aleatorio',
  `prioridad_muestreo` varchar(50) NOT NULL DEFAULT 'Media',
  `estado` varchar(50) NOT NULL DEFAULT 'Pendiente',
  `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `qa_muestreo_cola`
--

INSERT INTO `qa_muestreo_cola` (`id`, `ticket_id`, `agente_evaluado`, `motivo_inclusion`, `tipo_trigger`, `prioridad_muestreo`, `estado`, `fecha_generacion`) VALUES
(301, 101, 'dev_lead', 'Exceso de SLA Crítico (DirectX 12)', 'sla_critico', 'Alta', 'Pendiente', '2026-11-16 14:00:00'),
(302, 102, 'dev_lead', 'Exceso de SLA Crítico (Ray Tracing)', 'sla_critico', 'Alta', 'Auditado', '2026-11-16 16:00:00'),
(303, 103, 'support', 'Muestreo Aleatorio Mensual (10%)', 'aleatorio', 'Media', 'Pendiente', '2026-11-16 20:00:00'),
(304, 104, 'support', 'Muestreo Aleatorio Mensual (10%)', 'aleatorio', 'Media', 'Auditado', '2026-11-16 20:43:18'),
(305, 105, 'dev_lead', 'Muestreo Aleatorio Mensual (10%)', 'aleatorio', 'Media', 'Auditado', '2026-11-16 20:43:18'),
(306, 106, 'support', 'Calificación Cliente ≤ 2 Estrellas (CSAT)', 'csat_bajo', 'Alta', 'Pendiente', '2026-11-16 20:43:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `relaciones_incidencias`
--

CREATE TABLE `relaciones_incidencias` (
  `id` int(11) NOT NULL,
  `incidencia_origen_id` int(11) NOT NULL,
  `incidencia_destino_id` int(11) NOT NULL,
  `tipo_relacion` varchar(50) NOT NULL,
  `fecha_vinculacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `relaciones_incidencias`
--

INSERT INTO relaciones_incidencias (incidencia_origen_id, incidencia_destino_id, tipo_relacion)
    VALUES (202, 201, 'relacionado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'administrador'),
(3, 'desarrollo'),
(4, 'monitoreo'),
(2, 'soporte');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `prioridad` varchar(50) DEFAULT NULL,
  `estado` varchar(50) NOT NULL DEFAULT 'Nuevo',
  `usuario` varchar(100) DEFAULT NULL,
  `asignado` varchar(100) DEFAULT NULL,
  `juego_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `fecha_apertura` timestamp NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` timestamp NULL DEFAULT NULL,
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `es_incidencia` tinyint(1) DEFAULT 0,
  `respuesta_resolucion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (101, 'Micro-congelamientos con DirectX 12 al compilar shaders', 'El usuario experimenta caídas abruptas de cuadros por segundo y micro-tirones durante la carga asíncrona de escenarios con la API DirectX 12.', 'Rendimiento', 'alta', 'Asignado', 'alex_gamer99', 'dev_lead', 3, 1, '2026-11-16 23:07:50', '2026-11-17 23:07:50', NULL, 1, NULL);
INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (102, 'Caída severa de FPS al habilitar Ray Tracing Overdrive', 'Al activar el trazado de trayectorias completas (Path Tracing), el motor gráfico sufre fuga de memoria en buffers de texturas, reduciendo los cuadros a menos de 15 FPS.', 'Drivers', 'alta', 'En Progreso', 'matias_hardware', 'marcos_dev', 2, 1, '2026-11-16 07:07:50', '2026-11-17 07:07:50', NULL, 1, NULL);
INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (103, 'Problema de acceso y sincronización de partidas en la nube', 'El cliente no puede recuperar su archivo de guardado local tras reinstalar el sistema operativo. Solicita sincronización manual forzada desde el servidor central.', 'Cuentas', 'media', 'En Progreso', 'lucas_castillo', 'support', NULL, NULL, '2026-11-16 21:07:50', '2026-11-18 21:07:50', NULL, 0, NULL);
INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (104, 'Solicitud de informe DXDiag y registros de instalación', 'Se requiere el reporte de diagnóstico de DirectX y logs del instalador para evaluar incompatibilidad con librerías Visual C++ Redistributable.', 'Soporte Técnico', 'baja', 'En Espera', 'sofia_montes', 'matias_soporte', NULL, NULL, '2026-11-16 01:07:50', '2026-11-20 01:07:50', NULL, 0, NULL);
INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (105, 'Fallo de detección de GPU dedicada en equipos portátiles', 'El lanzador iniciaba por defecto en el chip de gráficos integrado Intel en lugar de la tarjeta gráfica NVIDIA RTX.', 'Hardware', 'media', 'Resuelto', 'esteban_dev', 'dev_lead', 1, 2, '2026-11-16 15:07:50', '2026-11-18 15:07:50', NULL, 1, 'Se actualizó el perfil de optimización en el archivo de configuración para forzar la inicialización sobre el adaptador de vídeo NVIDIA discreto y se validó en modo ventana sin bordes.');
INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (106, 'Parpadeo de texturas y sombras en mapas multijugador', 'Artefactos visuales y parpadeo intermitente en las sombras de oclusión ambiental durante combates de alto ritmo.', 'Gráficos', 'critica', 'Cerrado', 'pro_sniper_ar', 'franco_dev', 7, 1, '2026-11-16 21:47:50', '2026-11-17 01:47:50', '2026-11-17 00:37:50', 1, 'Corregido buffer de texturas y sincronización vertical de shaders en hotfix v1.04. Verificado con prueba de estrés de 45 minutos.');
INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (107, 'Error 0x887A0006 DXGI_ERROR_DEVICE_HUNG al iniciar cinemática', 'Crash fatal del controlador gráfico al intentar reproducir escenas prerenderizadas en resolución 4K con HDR activo.', 'Drivers', 'critica', 'Nuevo', 'martin_cordoba', NULL, 8, 1, '2026-11-17 00:37:50', '2026-11-17 04:37:50', NULL, 1, NULL);
INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (108, 'Asistencia en configuración de cancelación de ruido con RTX Voice', 'Duda sobre la selección correcta de dispositivos de entrada y umbrales de compresión para transmisión en vivo.', 'Audio / Utilidades', 'baja', 'Cerrado', 'streamer_cordoba', 'lucia_tech', NULL, 6, '2026-11-15 01:07:50', '2026-11-19 01:07:50', '2026-11-16 01:07:50', 0, 'Se brindó guía técnica paso a paso para configurar el dispositivo virtual de entrada en OBS Studio y Discord.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `nombre_completo` varchar(255) DEFAULT NULL,
  `contraseña` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `departamento` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `IdRol` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (1, 'admin', 'Gonzalo Peralta', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'admin@technova.com', '32111222', 'Dirección de Operaciones y TI', 1);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (2, 'clara_ops', 'Clara Bianchi', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'clara@technova.com', '33444555', 'Administración y Gestión de Servicios', 1);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (3, 'support', 'Ignacio Silva', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'soporte@technova.com', '35666777', 'Mesa de Ayuda Nivel 1', 2);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (4, 'matias_soporte', 'Matías Romero', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'matias@technova.com', '36888999', 'Atención al Cliente y Cuentas', 2);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (5, 'lucia_tech', 'Lucía Fernández', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'lucia@technova.com', '38123456', 'Help Desk & Onboarding', 2);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (6, 'dev_lead', 'Esteban Krause', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'dev@technova.com', '34777888', 'Equipo de Motores Gráficos y Drivers', 3);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (7, 'marcos_dev', 'Marcos Domínguez', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'marcos@technova.com', '37999000', 'Ingeniería de Shaders y Rendimiento', 3);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (8, 'franco_dev', 'Franco Varela', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'franco@technova.com', '39222333', 'Compatibilidad de Hardware y SDK', 3);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (9, 'monitor', 'Camila Navarro', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'monitoreo@technova.com', '36444555', 'Monitoreo de SLAs y Auditoría', 4);
INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (10, 'julian_qa', 'Julián Soria', 'INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles', 'admin123', 'valenC@technova.com', '12345678', 'Equipo de Soporte 1', '2026-05-15 02:12:05', 2),
(2, 'valen', 'Valentina Carles', '123456', 'cvalenn@gmail.com', '1233456789', 'Equipo de Desarrollo 1', '2026-05-26 01:16:10', 3),
(4, 'valeen', 'Valentina Carles', '123456', 'valn@gmail.com', '123456789', 'Equipo de Monitoreo 1', '2026-05-28 03:17:44', 4),
(5, 'test_admin', 'Administrador', '$2y$10$41PzFLddksGchB10nmPrbedisCw9CsUDbDcxwpAVVVD6qpSS/j4tO', 'admin@technova.com', '999999', 'Administración', '2026-06-20 20:30:45', 1);y$a3nwU.4C.a3p3zHCbajKfuYCrKB3KDeuhZzPnVZdZA1acbe1OYQZS', 'julian@technova.com', '37555666', 'Calidad y Métricas CSAT', 4);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `capa_coaching`
--
ALTER TABLE `capa_coaching`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indices de la tabla `historial_tickets`
--
ALTER TABLE `historial_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indices de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `juego_id` (`juego_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indices de la tabla `juegos`
--
ALTER TABLE `juegos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `qa_auditorias`
--
ALTER TABLE `qa_auditorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indices de la tabla `qa_muestreo_cola`
--
ALTER TABLE `qa_muestreo_cola`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `estado` (`estado`);

--
-- Indices de la tabla `relaciones_incidencias`
--
ALTER TABLE `relaciones_incidencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `relacion_unica` (`incidencia_origen_id`,`incidencia_destino_id`),
  ADD KEY `incidencia_destino_id` (`incidencia_destino_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `fk_usuarios_roles` (`IdRol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `capa_coaching`
--
ALTER TABLE `capa_coaching`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT de la tabla `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `historial_tickets`
--
ALTER TABLE `historial_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT de la tabla `juegos`
--
ALTER TABLE `juegos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `qa_auditorias`
--
ALTER TABLE `qa_auditorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT de la tabla `qa_muestreo_cola`
--
ALTER TABLE `qa_muestreo_cola`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=307;

--
-- AUTO_INCREMENT de la tabla `relaciones_incidencias`
--
ALTER TABLE `relaciones_incidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `historial_tickets`
--
ALTER TABLE `historial_tickets`
  ADD CONSTRAINT `historial_tickets_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD CONSTRAINT `incidencias_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incidencias_ibfk_2` FOREIGN KEY (`juego_id`) REFERENCES `juegos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incidencias_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `qa_auditorias`
--
ALTER TABLE `qa_auditorias`
  ADD CONSTRAINT `qa_auditorias_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `qa_muestreo_cola`
--
ALTER TABLE `qa_muestreo_cola`
  ADD CONSTRAINT `qa_muestreo_cola_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `relaciones_incidencias`
--
ALTER TABLE `relaciones_incidencias`
  ADD CONSTRAINT `relaciones_incidencias_ibfk_1` FOREIGN KEY (`incidencia_origen_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `relaciones_incidencias_ibfk_2` FOREIGN KEY (`incidencia_destino_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`IdRol`) REFERENCES `roles` (`id`);
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
