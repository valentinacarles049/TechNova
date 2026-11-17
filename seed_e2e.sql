-- =====================================================================
-- SCRIPT DE SEEDING & CLEANUP PARA PRUEBAS E2E - TECHNOVA
-- Entorno: Localhost / Pruebas E2E (technova_test)
-- Objetivo: Limpiar datos residuales y poblar el sistema con datos base
--           realistas para que el Dashboard y las tablas se vean poblados.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. LIMPIEZA DE TABLAS TRANSACCIONALES (Usa DELETE para evitar error #1701 de Foreign Keys)
DELETE FROM `notificaciones`;
DELETE FROM `historial_tickets`;
DELETE FROM `relaciones_incidencias`;
DELETE FROM `capa_coaching`;
DELETE FROM `qa_auditorias`;
DELETE FROM `qa_muestreo_cola`;
DELETE FROM `feedbacks`;
DELETE FROM `incidencias`;
DELETE FROM `tickets`;
DELETE FROM `usuarios`;
DELETE FROM `roles`;
DELETE FROM `juegos`;
DELETE FROM `drivers`;
DELETE FROM `configuraciones`;

-- Reiniciar contadores de ID autoincrementales
ALTER TABLE `tickets` ADD COLUMN IF NOT EXISTS `fecha_vencimiento` timestamp NULL DEFAULT NULL AFTER `fecha_apertura`;
ALTER TABLE `notificaciones` AUTO_INCREMENT = 1;
ALTER TABLE `historial_tickets` AUTO_INCREMENT = 1;
ALTER TABLE `relaciones_incidencias` AUTO_INCREMENT = 1;
ALTER TABLE `capa_coaching` AUTO_INCREMENT = 1;
ALTER TABLE `qa_auditorias` AUTO_INCREMENT = 1;
ALTER TABLE `qa_muestreo_cola` AUTO_INCREMENT = 1;
ALTER TABLE `feedbacks` AUTO_INCREMENT = 1;
ALTER TABLE `incidencias` AUTO_INCREMENT = 1;
ALTER TABLE `tickets` AUTO_INCREMENT = 1;
ALTER TABLE `usuarios` AUTO_INCREMENT = 1;
ALTER TABLE `roles` AUTO_INCREMENT = 1;
ALTER TABLE `juegos` AUTO_INCREMENT = 1;
ALTER TABLE `drivers` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 2. ROLES DEL SISTEMA
-- ---------------------------------------------------------------------
INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'administrador'),
(2, 'soporte'),
(3, 'desarrollo'),
(4, 'monitoreo');

-- ---------------------------------------------------------------------
-- 3. USUARIOS DE PRUEBA (Credenciales conocidas para la E2E)
-- ---------------------------------------------------------------------
INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `contraseña`, `email`, `dni`, `departamento`, `created_at`, `IdRol`) VALUES
(1, 'support', 'Valentina Carles (Soporte)', 'admin123', 'soporte@technova.com', '12345678', 'Mesa de Ayuda Nivel 1', NOW() - INTERVAL 30 DAY, 2),
(2, 'dev_lead', 'Ingeniero Desarrollo Lead', 'admin123', 'dev@technova.com', '23456789', 'Equipo de Desarrollo y Drivers', NOW() - INTERVAL 30 DAY, 3),
(3, 'valen', 'Valentina Carles (Dev)', '123456', 'valen@technova.com', '34567890', 'Equipo de Desarrollo 1', NOW() - INTERVAL 30 DAY, 3),
(4, 'admin', 'Administrador General', 'admin123', 'admin@technova.com', '99999999', 'Administración Central', NOW() - INTERVAL 30 DAY, 1),
(5, 'monitor', 'Analista Monitoreo y Calidad', 'admin123', 'monitoreo@technova.com', '45678901', 'Equipo de Monitoreo y SLA', NOW() - INTERVAL 30 DAY, 4),
(6, 'valeen', 'Valentina Carles (Monitoreo)', '123456', 'valeen@technova.com', '45678902', 'Equipo de Monitoreo 1', NOW() - INTERVAL 30 DAY, 4);

-- ---------------------------------------------------------------------
-- 4. CATÁLOGO COMPLETO DE VIDEOJUEGOS
-- ---------------------------------------------------------------------
INSERT INTO `juegos` (`id`, `nombre`, `estado`, `motor_grafico`) VALUES
(1, 'Cyberpunk 2077', 'activo', 'REDengine 4'),
(2, 'Baldur\'s Gate 3', 'activo', 'Divinity Engine 4.0'),
(3, 'Starfield', 'activo', 'Creation Engine 2'),
(4, 'Elden Ring', 'activo', 'Proprietary / FromSoftware'),
(5, 'The Witcher 3: Wild Hunt', 'activo', 'REDengine 3'),
(6, 'Halo Infinite', 'activo', 'Slipspace Engine');

-- ---------------------------------------------------------------------
-- 5. CATÁLOGO COMPLETO DE CONTROLADORES / DRIVERS
-- ---------------------------------------------------------------------
INSERT INTO `drivers` (`id`, `nombre`, `version`, `fecha`, `tamanio`, `categoriaDriver`, `estado`) VALUES
(1, 'NVIDIA GeForce Game Ready Driver', '545.84', '2026-01-15', 720, 'Tarjeta Gráfica', 'activo'),
(2, 'NVIDIA Studio Driver', '545.92', '2026-02-01', 730, 'Tarjeta Gráfica', 'activo'),
(3, 'NVIDIA GeForce Experience', '3.27.0.120', '2026-01-20', 180, 'Utilidad', 'activo'),
(4, 'NVIDIA PhysX System Software', '9.23.1026', '2026-01-05', 42, 'Motor de Física', 'activo'),
(5, 'NVIDIA CUDA Toolkit', '12.3.2', '2026-01-10', 3, 'Desarrollo', 'activo'),
(6, 'NVIDIA RTX Voice', '0.5.12.9', '2026-01-22', 125, 'Audio AI', 'activo');

-- ---------------------------------------------------------------------
-- 6. TICKETS HISTÓRICOS Y ACTIVOS (Para que el Dashboard y tablas tengan vida)
-- ---------------------------------------------------------------------
INSERT INTO `tickets` (`id`, `titulo`, `descripcion`, `categoria`, `prioridad`, `estado`, `usuario`, `asignado`, `juego_id`, `driver_id`, `fecha_apertura`, `fecha_cierre`, `es_incidencia`, `respuesta_resolucion`) VALUES
(10, 'Error de renderizado Vulkan en Linux', 'Se presentan artefactos visuales severos al compilar shaders con la API Vulkan.', 'drivers', 'alta', 'Cerrado', 'gamer_pro', 'dev_lead', 1, 1, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 4 DAY, 1, 'Se aplicó parche de compatibilidad en driver versión 545.92 y se verificaron shaders.'),
(11, 'Solicitud de informe DXDiag por stuttering', 'Usuario reporta micro-tirones constantes en tasa de refresco.', 'drivers', 'media', 'En Espera', 'gamer_pro', 'support', 2, 2, NOW() - INTERVAL 3 DAY, NULL, 0, NULL),
(12, 'Fallo de autenticación en launcher', 'El lanzador no recuerda credenciales guardadas.', 'Cuentas', 'baja', 'Asignado', 'lucas_gamer', 'support', NULL, NULL, NOW() - INTERVAL 2 DAY, NULL, 0, NULL),
(13, 'Pantallazo azul al cargar sistema anti-cheat', 'Cuelgue BSOD aleatorio con código de parada al iniciar partida multijugador.', 'drivers', 'critica', 'Cerrado', 'matias_pc', 'dev_lead', 4, 1, NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 5 DAY, 1, 'Actualizado módulo de interoperabilidad del kernel con el controlador de video.'),
(14, 'Parpadeo de sombras en DirectX 12', 'Blinking en mapas de sombras con oclusión ambiental activa.', 'drivers', 'media', 'Cerrado', 'sofia_rtx', 'dev_lead', 3, 2, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 3 DAY, 1, 'Ajustado perfil de filtrado anisotrópico en el archivo de configuración del juego.'),
(15, 'Optimización de fotogramas DLSS 3 Frame Generation', 'Consulta sobre habilitación de generación de fotogramas en RTX serie 40.', 'drivers', 'baja', 'En Progreso', 'nico_ultra', 'dev_lead', 5, 1, NOW() - INTERVAL 1 DAY, NULL, 1, NULL);

-- ---------------------------------------------------------------------
-- 7. INCIDENCIAS TÉCNICAS ASOCIADAS
-- ---------------------------------------------------------------------
INSERT INTO `incidencias` (`id`, `ticket_id`, `juego_id`, `driver_id`, `detalle`, `fecha_creacion`, `fecha_cierre`, `estado`) VALUES
(1, 10, 1, 1, 'Incompatibilidad de shaders Vulkan con driver 545.84 en REDengine.', NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 4 DAY, 'cerrado'),
(2, 13, 4, 1, 'Conflicto de hooks de memoria entre Anti-Cheat y driver de video.', NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 5 DAY, 'cerrado'),
(3, 14, 3, 2, 'Cálculo erróneo de sombras en DirectX 12 bajo perfiles Studio.', NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 3 DAY, 'cerrado'),
(4, 15, 5, 1, 'Revisión de compatibilidad de pipeline DLSS 3 en perfiles de controlador.', NOW() - INTERVAL 1 DAY, NULL, 'en progreso');

-- ---------------------------------------------------------------------
-- 8. FEEDBACKS Y EVALUACIONES CSAT (Para métricas de satisfacción)
-- ---------------------------------------------------------------------
INSERT INTO `feedbacks` (`id`, `ticket_id`, `usuario`, `tipo`, `tema`, `sentimiento`, `estado`, `comentario`, `fecha`) VALUES
(1, 10, 'gamer_pro', 'Elogio', 'Soporte Técnico - Driver Vulkan', 'Positivo', 'Revisado', 'Resolvieron el problema de Vulkan rapidísimo, ahora el juego va a 120 FPS sin errores.', NOW() - INTERVAL 4 DAY),
(2, 13, 'matias_pc', 'Elogio', 'Resolución BSOD Anti-Cheat', 'Positivo', 'Implementado', 'Muy buena atención del equipo de ingeniería, no volvió a tirar pantalla azul.', NOW() - INTERVAL 5 DAY),
(3, 14, 'sofia_rtx', 'Sugerencia', 'Ajuste de Sombras DX12', 'Positivo', 'Revisado', 'Quedó solucionado el parpadeo de sombras. Estaría bueno agregar una guía en la web.', NOW() - INTERVAL 3 DAY),
(4, 11, 'gamer_pro', 'Sugerencia', 'Tiempos de respuesta', 'Neutro', 'Nuevo', 'La atención es buena pero tardaron un día en pedirme el archivo dxdiag.', NOW() - INTERVAL 2 DAY);

-- ---------------------------------------------------------------------
-- 9. NOTIFICACIONES DEL SISTEMA
-- ---------------------------------------------------------------------
INSERT INTO `notificaciones` (`id`, `usuario_id`, `equipo`, `titulo`, `descripcion`, `enlace`, `leido`, `fecha`) VALUES
(1, 1, 'soporte', 'Ticket Cerrado', 'El ticket #10 "Error de renderizado Vulkan" ha sido resuelto por Desarrollo.', 'ticketManegement.php?ver_historial=10', 1, NOW() - INTERVAL 4 DAY),
(2, 2, 'desarrollo', 'Nueva Incidencia Técnica', 'Se ha sincronizado la incidencia técnica asociada al ticket #15.', 'incidentes.php?ver=4', 0, NOW() - INTERVAL 1 DAY),
(3, 5, 'monitoreo', 'Resumen Semanal de SLAs', 'El cumplimiento global de SLAs de la última semana se mantiene sobre el 92%.', 'dashboard.php', 0, NOW() - INTERVAL 2 DAY);

-- ---------------------------------------------------------------------
-- 10. HISTORIAL DE CAMBIOS AUDITABLES
-- ---------------------------------------------------------------------
INSERT INTO `historial_tickets` (`id`, `ticket_id`, `usuario`, `tipo_cambio`, `fecha`) VALUES
(1, 10, 'support', 'Ticket creado', NOW() - INTERVAL 5 DAY),
(2, 10, 'dev_lead', 'Incidencia técnica registrada: INC-001', NOW() - INTERVAL 5 DAY),
(3, 10, 'dev_lead', 'Incidencia técnica INC-001 resuelta y ticket cerrado', NOW() - INTERVAL 4 DAY),
(4, 13, 'support', 'Ticket creado con prioridad Crítica', NOW() - INTERVAL 6 DAY),
(5, 13, 'dev_lead', 'Incidencia INC-002 resuelta', NOW() - INTERVAL 5 DAY);

-- ---------------------------------------------------------------------
-- 11. CONFIGURACIÓN BASE
-- ---------------------------------------------------------------------
INSERT INTO `configuraciones` (`clave`, `valor`) VALUES
('ultima_semana_notificada', '2026-43');
