<?php
/**
 * Script de generación de datos limpios y profesionales para demostración y capturas
 * TechNova - Help Desk & QA Gaming Platform
 */

require_once(__DIR__ . "/db.php");
require_once(__DIR__ . "/funcs/generalFuncs.php");
require_once(__DIR__ . "/funcs/ticketLifecycle.php");
require_once(__DIR__ . "/funcs/ticketFunc.php");

echo "=== INICIANDO LIMPIEZA Y CARGA DE DATOS PROLIJOS ===" . PHP_EOL;

// 1. Desactivar FK y truncar tablas operativas y de usuarios
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
$tablasLimpiar = [
    'historial_tickets',
    'relaciones_incidencias',
    'feedbacks',
    'incidencias',
    'notificaciones',
    'tickets',
    'usuarios'
];
foreach ($tablasLimpiar as $tabla) {
    mysqli_query($conn, "TRUNCATE TABLE `$tabla`");
    echo "✓ Tabla '$tabla' vaciada." . PHP_EOL;
}
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

$now = time();
$passDefault = 'admin123';
$passHash = password_hash($passDefault, PASSWORD_DEFAULT);

// ============================================================================
// 2. INSERCIÓN DE USUARIOS REALISTAS (TECHNOVA STAFF ROSTER)
// ============================================================================
$usuariosData = [
    // ROL 1: ADMINISTRADOR
    [
        'id' => 1,
        'usuario' => 'admin',
        'nombre_completo' => 'Gonzalo Peralta',
        'contraseña' => $passHash,
        'email' => 'admin@technova.com',
        'dni' => '32111222',
        'departamento' => 'Dirección de Operaciones y TI',
        'IdRol' => 1
    ],
    [
        'id' => 2,
        'usuario' => 'clara_ops',
        'nombre_completo' => 'Clara Bianchi',
        'contraseña' => $passHash,
        'email' => 'clara@technova.com',
        'dni' => '33444555',
        'departamento' => 'Administración y Gestión de Servicios',
        'IdRol' => 1
    ],

    // ROL 2: SOPORTE TÉCNICO
    [
        'id' => 3,
        'usuario' => 'support',
        'nombre_completo' => 'Ignacio Silva',
        'contraseña' => $passHash,
        'email' => 'soporte@technova.com',
        'dni' => '35666777',
        'departamento' => 'Mesa de Ayuda Nivel 1',
        'IdRol' => 2
    ],
    [
        'id' => 4,
        'usuario' => 'matias_soporte',
        'nombre_completo' => 'Matías Romero',
        'contraseña' => $passHash,
        'email' => 'matias@technova.com',
        'dni' => '36888999',
        'departamento' => 'Atención al Cliente y Cuentas',
        'IdRol' => 2
    ],
    [
        'id' => 5,
        'usuario' => 'lucia_tech',
        'nombre_completo' => 'Lucía Fernández',
        'contraseña' => $passHash,
        'email' => 'lucia@technova.com',
        'dni' => '38123456',
        'departamento' => 'Help Desk & Onboarding',
        'IdRol' => 2
    ],

    // ROL 3: DESARROLLO & CONTROLADORES
    [
        'id' => 6,
        'usuario' => 'dev_lead',
        'nombre_completo' => 'Esteban Krause',
        'contraseña' => $passHash,
        'email' => 'dev@technova.com',
        'dni' => '34777888',
        'departamento' => 'Equipo de Motores Gráficos y Drivers',
        'IdRol' => 3
    ],
    [
        'id' => 7,
        'usuario' => 'marcos_dev',
        'nombre_completo' => 'Marcos Domínguez',
        'contraseña' => $passHash,
        'email' => 'marcos@technova.com',
        'dni' => '37999000',
        'departamento' => 'Ingeniería de Shaders y Rendimiento',
        'IdRol' => 3
    ],
    [
        'id' => 8,
        'usuario' => 'franco_dev',
        'nombre_completo' => 'Franco Varela',
        'contraseña' => $passHash,
        'email' => 'franco@technova.com',
        'dni' => '39222333',
        'departamento' => 'Compatibilidad de Hardware y SDK',
        'IdRol' => 3
    ],

    // ROL 4: MONITOREO Y CALIDAD
    [
        'id' => 9,
        'usuario' => 'monitor',
        'nombre_completo' => 'Camila Navarro',
        'contraseña' => $passHash,
        'email' => 'monitoreo@technova.com',
        'dni' => '36444555',
        'departamento' => 'Monitoreo de SLAs y Auditoría',
        'IdRol' => 4
    ],
    [
        'id' => 10,
        'usuario' => 'julian_qa',
        'nombre_completo' => 'Julián Soria',
        'contraseña' => $passHash,
        'email' => 'julian@technova.com',
        'dni' => '37555666',
        'departamento' => 'Calidad y Métricas CSAT',
        'IdRol' => 4
    ]
];

foreach ($usuariosData as $u) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO usuarios (id, usuario, nombre_completo, contraseña, email, dni, departamento, IdRol)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param(
        $stmt,
        "issssssi",
        $u['id'],
        $u['usuario'],
        $u['nombre_completo'],
        $u['contraseña'],
        $u['email'],
        $u['dni'],
        $u['departamento'],
        $u['IdRol']
    );
    mysqli_stmt_execute($stmt);
}
echo "✓ " . count($usuariosData) . " usuarios de TechNova creados exitosamente." . PHP_EOL;


// ============================================================================
// 3. INSERCIÓN DE TICKETS CON VARIEDAD DE ESTADOS, ROLES Y SLAs
// ============================================================================
// Crítica = 4h | Alta = 24h | Media = 48h | Baja = 96h
$ticketsData = [
    [
        'id' => 101,
        'titulo' => 'Micro-congelamientos con DirectX 12 al compilar shaders',
        'descripcion' => 'El usuario experimenta caídas abruptas de cuadros por segundo y micro-tirones durante la carga asíncrona de escenarios con la API DirectX 12.',
        'categoria' => 'Rendimiento',
        'prioridad' => 'alta',
        'estado' => TicketEstado::ASIGNADO,
        'usuario' => 'alex_gamer99',
        'asignado' => 'dev_lead', // Esteban Krause
        'juego_id' => 3, // Baldur's Gate 3
        'driver_id' => 1, // GeForce Game Ready 545.84
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 7200), // Hace 2 horas
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 7200 + (24 * 3600)), // 24h SLA
        'fecha_cierre' => null,
        'es_incidencia' => 1,
        'respuesta_resolucion' => null
    ],
    [
        'id' => 102,
        'titulo' => 'Caída severa de FPS al habilitar Ray Tracing Overdrive',
        'descripcion' => 'Al activar el trazado de trayectorias completas (Path Tracing), el motor gráfico sufre fuga de memoria en buffers de texturas, reduciendo los cuadros a menos de 15 FPS.',
        'categoria' => 'Drivers',
        'prioridad' => 'alta',
        'estado' => TicketEstado::EN_PROGRESO,
        'usuario' => 'matias_hardware',
        'asignado' => 'marcos_dev', // Marcos Domínguez
        'juego_id' => 2, // Cyberpunk 2077
        'driver_id' => 1, // GeForce Game Ready 545.84
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 64800), // Hace 18 horas (75% consumido de 24h -> SLA Warning!)
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 64800 + (24 * 3600)), // Le quedan ~6h
        'fecha_cierre' => null,
        'es_incidencia' => 1,
        'respuesta_resolucion' => null
    ],
    [
        'id' => 103,
        'titulo' => 'Problema de acceso y sincronización de partidas en la nube',
        'descripcion' => 'El cliente no puede recuperar su archivo de guardado local tras reinstalar el sistema operativo. Solicita sincronización manual forzada desde el servidor central.',
        'categoria' => 'Cuentas',
        'prioridad' => 'media',
        'estado' => TicketEstado::EN_PROGRESO,
        'usuario' => 'lucas_castillo',
        'asignado' => 'support', // Ignacio Silva
        'juego_id' => null,
        'driver_id' => null,
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 14400), // Hace 4 horas
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 14400 + (48 * 3600)), // 48h SLA
        'fecha_cierre' => null,
        'es_incidencia' => 0,
        'respuesta_resolucion' => null
    ],
    [
        'id' => 104,
        'titulo' => 'Solicitud de informe DXDiag y registros de instalación',
        'descripcion' => 'Se requiere el reporte de diagnóstico de DirectX y logs del instalador para evaluar incompatibilidad con librerías Visual C++ Redistributable.',
        'categoria' => 'Soporte Técnico',
        'prioridad' => 'baja',
        'estado' => TicketEstado::EN_ESPERA,
        'usuario' => 'sofia_montes',
        'asignado' => 'matias_soporte', // Matías Romero
        'juego_id' => null,
        'driver_id' => null,
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 86400), // Hace 24 horas
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 86400 + (96 * 3600)), // 96h SLA
        'fecha_cierre' => null,
        'es_incidencia' => 0,
        'respuesta_resolucion' => null
    ],
    [
        'id' => 105,
        'titulo' => 'Fallo de detección de GPU dedicada en equipos portátiles',
        'descripcion' => 'El lanzador iniciaba por defecto en el chip de gráficos integrado Intel en lugar de la tarjeta gráfica NVIDIA RTX.',
        'categoria' => 'Hardware',
        'prioridad' => 'media',
        'estado' => TicketEstado::RESUELTO,
        'usuario' => 'esteban_dev',
        'asignado' => 'dev_lead', // Esteban Krause
        'juego_id' => 1, // Starfield
        'driver_id' => 2, // Studio Driver 545.92
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 36000), // Hace 10 horas
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 36000 + (48 * 3600)),
        'fecha_cierre' => null,
        'es_incidencia' => 1,
        'respuesta_resolucion' => 'Se actualizó el perfil de optimización en el archivo de configuración para forzar la inicialización sobre el adaptador de vídeo NVIDIA discreto y se validó en modo ventana sin bordes.'
    ],
    [
        'id' => 106,
        'titulo' => 'Parpadeo de texturas y sombras en mapas multijugador',
        'descripcion' => 'Artefactos visuales y parpadeo intermitente en las sombras de oclusión ambiental durante combates de alto ritmo.',
        'categoria' => 'Gráficos',
        'prioridad' => 'critica',
        'estado' => TicketEstado::CERRADO,
        'usuario' => 'pro_sniper_ar',
        'asignado' => 'franco_dev', // Franco Varela
        'juego_id' => 7, // Call of Duty MW III
        'driver_id' => 1, // GeForce Game Ready 545.84
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 12000), // Hace ~3.3 horas
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 12000 + (4 * 3600)), // 4h SLA Crítico
        'fecha_cierre' => date('Y-m-d H:i:s', $now - 1800), // Cerrado hace 30 min (cumplió SLA)
        'es_incidencia' => 1,
        'respuesta_resolucion' => 'Corregido buffer de texturas y sincronización vertical de shaders en hotfix v1.04. Verificado con prueba de estrés de 45 minutos.'
    ],
    [
        'id' => 107,
        'titulo' => 'Error 0x887A0006 DXGI_ERROR_DEVICE_HUNG al iniciar cinemática',
        'descripcion' => 'Crash fatal del controlador gráfico al intentar reproducir escenas prerenderizadas en resolución 4K con HDR activo.',
        'categoria' => 'Drivers',
        'prioridad' => 'critica',
        'estado' => TicketEstado::NUEVO,
        'usuario' => 'martin_cordoba',
        'asignado' => null, // Sin asignar para probar el selector inteligente
        'juego_id' => 8, // Red Dead Redemption 2
        'driver_id' => 1, // GeForce Game Ready 545.84
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 1800), // Hace 30 minutos
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 1800 + (4 * 3600)),
        'fecha_cierre' => null,
        'es_incidencia' => 1,
        'respuesta_resolucion' => null
    ],
    [
        'id' => 108,
        'titulo' => 'Asistencia en configuración de cancelación de ruido con RTX Voice',
        'descripcion' => 'Duda sobre la selección correcta de dispositivos de entrada y umbrales de compresión para transmisión en vivo.',
        'categoria' => 'Audio / Utilidades',
        'prioridad' => 'baja',
        'estado' => TicketEstado::CERRADO,
        'usuario' => 'streamer_cordoba',
        'asignado' => 'lucia_tech', // Lucía Fernández
        'juego_id' => null,
        'driver_id' => 6, // NVIDIA RTX Voice
        'fecha_apertura' => date('Y-m-d H:i:s', $now - 172800), // Hace 2 días
        'fecha_vencimiento' => date('Y-m-d H:i:s', $now - 172800 + (96 * 3600)),
        'fecha_cierre' => date('Y-m-d H:i:s', $now - 86400),
        'es_incidencia' => 0,
        'respuesta_resolucion' => 'Se brindó guía técnica paso a paso para configurar el dispositivo virtual de entrada en OBS Studio y Discord.'
    ]
];

foreach ($ticketsData as $t) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO tickets 
        (id, titulo, descripcion, categoria, prioridad, estado, usuario, asignado, juego_id, driver_id, fecha_apertura, fecha_vencimiento, fecha_cierre, es_incidencia, respuesta_resolucion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param(
        $stmt,
        "isssssssiisssis",
        $t['id'],
        $t['titulo'],
        $t['descripcion'],
        $t['categoria'],
        $t['prioridad'],
        $t['estado'],
        $t['usuario'],
        $t['asignado'],
        $t['juego_id'],
        $t['driver_id'],
        $t['fecha_apertura'],
        $t['fecha_vencimiento'],
        $t['fecha_cierre'],
        $t['es_incidencia'],
        $t['respuesta_resolucion']
    );
    mysqli_stmt_execute($stmt);
}
echo "✓ " . count($ticketsData) . " tickets registrados con éxito." . PHP_EOL;

// ============================================================================
// 4. INSERCIÓN DE INCIDENCIAS TÉCNICAS (HU-42 y HU-48)
// ============================================================================
$incidenciasData = [
    [
        'id' => 201,
        'ticket_id' => 101, // Baldur's Gate 3
        'juego_id' => 3,
        'driver_id' => 1,
        'detalle' => 'Stuttering y caída de fotogramas durante la carga asíncrona de texturas y compilación de shaders bajo DirectX 12.',
        'estado' => 'Mitigada',
        'severidad' => 'Media',
        'plazo_mitigacion_horas' => 24,
        'plazo_correccion_horas' => 48,
        'fecha_creacion' => date('Y-m-d H:i:s', $now - 7200),
        'fecha_limite_mitigacion' => date('Y-m-d H:i:s', $now - 7200 + (24 * 3600)),
        'fecha_limite_correccion' => date('Y-m-d H:i:s', $now - 7200 + (48 * 3600)),
        'fecha_mitigacion' => date('Y-m-d H:i:s', $now - 3600),
        'fecha_cierre' => null,
        'solucion_temporal' => 'Comando de inicio (-force-vulkan) agregado temporalmente para omitir la compilación en segundo plano mientras se publica el parche.',
        'dictamen_tecnico' => null,
        'cumplio_sla_desarrollo' => 1,
        'tiempo_efectivo_minutos' => 60
    ],
    [
        'id' => 202,
        'ticket_id' => 102, // Cyberpunk 2077
        'juego_id' => 2,
        'driver_id' => 1,
        'detalle' => 'Fuga progresiva de memoria VRAM en el buffer de trazado de rayos (Path Tracing) al interactuar con reflejos dinámicos.',
        'estado' => 'EnInvestigacion',
        'severidad' => 'Alta',
        'plazo_mitigacion_horas' => 12,
        'plazo_correccion_horas' => 24,
        'fecha_creacion' => date('Y-m-d H:i:s', $now - 39600), // Hace 11 horas (le queda 1h de mitigación -> Alerta Próximo a vencer)
        'fecha_limite_mitigacion' => date('Y-m-d H:i:s', $now - 39600 + (12 * 3600)),
        'fecha_limite_correccion' => date('Y-m-d H:i:s', $now - 39600 + (24 * 3600)),
        'fecha_mitigacion' => null,
        'fecha_cierre' => null,
        'solucion_temporal' => null,
        'dictamen_tecnico' => null,
        'cumplio_sla_desarrollo' => null,
        'tiempo_efectivo_minutos' => null
    ],
    [
        'id' => 203,
        'ticket_id' => 106, // Call of Duty MW III
        'juego_id' => 7,
        'driver_id' => 1,
        'detalle' => 'Corrupción de buffers de texturas y artefactos visuales en oclusión ambiental tras actualización de controladores.',
        'estado' => 'Corregida',
        'severidad' => 'Crítica',
        'plazo_mitigacion_horas' => 2,
        'plazo_correccion_horas' => 4,
        'fecha_creacion' => date('Y-m-d H:i:s', $now - 12000),
        'fecha_limite_mitigacion' => date('Y-m-d H:i:s', $now - 12000 + (2 * 3600)),
        'fecha_limite_correccion' => date('Y-m-d H:i:s', $now - 12000 + (4 * 3600)),
        'fecha_mitigacion' => date('Y-m-d H:i:s', $now - 7200),
        'fecha_cierre' => date('Y-m-d H:i:s', $now - 1800),
        'solucion_temporal' => 'Desactivar oclusión ambiental HBAO+ en menú de opciones avanzadas.',
        'dictamen_tecnico' => 'Se recompiló el shader de oclusión ambiental con la librería actualizada de NVIDIA SDK y se desplegó en el hotfix v1.04.',
        'cumplio_sla_desarrollo' => 1,
        'tiempo_efectivo_minutos' => 170
    ],
    [
        'id' => 204,
        'ticket_id' => 107, // Red Dead Redemption 2
        'juego_id' => 8,
        'driver_id' => 1,
        'detalle' => 'Crash fatal del motor gráfico por timeout de detección y recuperación del controlador (TDR DXGI_ERROR_DEVICE_HUNG).',
        'estado' => 'Reportada',
        'severidad' => 'Crítica',
        'plazo_mitigacion_horas' => 2,
        'plazo_correccion_horas' => 4,
        'fecha_creacion' => date('Y-m-d H:i:s', $now - 1800), // Hace 30 minutos
        'fecha_limite_mitigacion' => date('Y-m-d H:i:s', $now - 1800 + (2 * 3600)),
        'fecha_limite_correccion' => date('Y-m-d H:i:s', $now - 1800 + (4 * 3600)),
        'fecha_mitigacion' => null,
        'fecha_cierre' => null,
        'solucion_temporal' => null,
        'dictamen_tecnico' => null,
        'cumplio_sla_desarrollo' => null,
        'tiempo_efectivo_minutos' => null
    ]
];

foreach ($incidenciasData as $inc) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO incidencias
        (id, ticket_id, juego_id, driver_id, detalle, estado, severidad, plazo_mitigacion_horas, plazo_correccion_horas, fecha_creacion, fecha_limite_mitigacion, fecha_limite_correccion, fecha_mitigacion, fecha_cierre, solucion_temporal, dictamen_tecnico, cumplio_sla_desarrollo, tiempo_efectivo_minutos)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param(
        $stmt,
        "iiiisssiisssssssii",
        $inc['id'],
        $inc['ticket_id'],
        $inc['juego_id'],
        $inc['driver_id'],
        $inc['detalle'],
        $inc['estado'],
        $inc['severidad'],
        $inc['plazo_mitigacion_horas'],
        $inc['plazo_correccion_horas'],
        $inc['fecha_creacion'],
        $inc['fecha_limite_mitigacion'],
        $inc['fecha_limite_correccion'],
        $inc['fecha_mitigacion'],
        $inc['fecha_cierre'],
        $inc['solucion_temporal'],
        $inc['dictamen_tecnico'],
        $inc['cumplio_sla_desarrollo'],
        $inc['tiempo_efectivo_minutos']
    );
    mysqli_stmt_execute($stmt);
}
echo "✓ " . count($incidenciasData) . " incidencias técnicas registradas." . PHP_EOL;

// ============================================================================
// 5. RELACIÓN ENTRE INCIDENCIAS (HU-47)
// ============================================================================
mysqli_query($conn, "
    INSERT INTO relaciones_incidencias (incidencia_origen_id, incidencia_destino_id, tipo_relacion)
    VALUES (202, 201, 'relacionado')
");
echo "✓ Relación entre incidencias 202 y 201 establecida." . PHP_EOL;

// ============================================================================
// 6. HISTORIAL DE AUDITORÍA (HISTORIAL_TICKETS)
// ============================================================================
$historiales = [
    [101, 'support', 'Ticket registrado en estado Nuevo', date('Y-m-d H:i:s', $now - 7200)],
    [101, 'admin', 'Ticket asignado formalmente al agente técnico "dev_lead" (Estado: Asignado)', date('Y-m-d H:i:s', $now - 7000)],
    [102, 'support', 'Ticket creado con prioridad Alta', date('Y-m-d H:i:s', $now - 64800)],
    [102, 'marcos_dev', 'Atención técnica iniciada (Estado: En Progreso)', date('Y-m-d H:i:s', $now - 60000)],
    [102, 'marcos_dev', 'Incidencia técnica registrada: INC-202 (En Investigación)', date('Y-m-d H:i:s', $now - 39600)],
    [103, 'support', 'Ticket registrado y puesto En Progreso para verificar backup en la nube', date('Y-m-d H:i:s', $now - 14400)],
    [104, 'matias_soporte', 'Solicitud de información adicional al usuario: DXDiag y logs (Estado: En Espera)', date('Y-m-d H:i:s', $now - 86400)],
    [105, 'dev_lead', 'Ticket marcado como Resuelto con dictamen técnico de configuración', date('Y-m-d H:i:s', $now - 7200)],
    [106, 'support', 'Ticket creado con prioridad Crítica', date('Y-m-d H:i:s', $now - 12000)],
    [106, 'franco_dev', 'Incidencia técnica INC-203 corregida y dictamen aplicado', date('Y-m-d H:i:s', $now - 1800)],
    [106, 'franco_dev', 'Ticket confirmado y archivado permanentemente (Estado: Cerrado)', date('Y-m-d H:i:s', $now - 1800)],
    [107, 'admin', 'Ticket registrado mediante API web de soporte', date('Y-m-d H:i:s', $now - 1800)]
];

foreach ($historiales as $h) {
    $stmt = mysqli_prepare($conn, "INSERT INTO historial_tickets (ticket_id, usuario, tipo_cambio, fecha) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isss", $h[0], $h[1], $h[2], $h[3]);
    mysqli_stmt_execute($stmt);
}
echo "✓ " . count($historiales) . " entradas de auditoría registradas." . PHP_EOL;

// ============================================================================
// 7. TABLERO DE CALIDAD / FEEDBACKS (HU-44)
// ============================================================================
$feedbacksData = [
    [
        'ticket_id' => 106,
        'usuario' => 'pro_sniper_ar',
        'tipo' => 'Elogio',
        'tema' => 'Resolución de sombras Call of Duty',
        'sentimiento' => 'Positivo',
        'calificacion' => 5,
        'sla_cumplido' => 1,
        'estado' => 'Implementado',
        'comentario' => 'Excelente la rapidez con la que corrigieron el parpadeo de sombras en la actualización. Ahora el juego corre fluido y sin glitches.',
        'fecha' => date('Y-m-d H:i:s', $now - 1500)
    ],
    [
        'ticket_id' => 108,
        'usuario' => 'streamer_cordoba',
        'tipo' => 'Sugerencia',
        'tema' => 'Guía de streaming RTX Voice',
        'sentimiento' => 'Positivo',
        'calificacion' => 4,
        'sla_cumplido' => 1,
        'estado' => 'Revisado',
        'comentario' => 'La atención de Lucía fue impecable y rápida. Sería genial que dejen un video tutorial corto en el portal de soporte para los que usamos OBS.',
        'fecha' => date('Y-m-d H:i:s', $now - 86000)
    ],
    [
        'ticket_id' => 105,
        'usuario' => 'esteban_dev',
        'tipo' => 'Queja',
        'tema' => 'Detección automática de GPU',
        'sentimiento' => 'Neutro',
        'calificacion' => 3,
        'sla_cumplido' => 1,
        'estado' => 'Nuevo',
        'comentario' => 'El problema se solucionó con el perfil, pero el launcher debería detectar automáticamente la tarjeta dedicada sin necesidad de abrir un ticket.',
        'fecha' => date('Y-m-d H:i:s', $now - 3600)
    ]
];

foreach ($feedbacksData as $fb) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO feedbacks
        (ticket_id, usuario, tipo, tema, sentimiento, calificacion, sla_cumplido, estado, comentario, fecha)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param(
        $stmt,
        "issssiisss",
        $fb['ticket_id'],
        $fb['usuario'],
        $fb['tipo'],
        $fb['tema'],
        $fb['sentimiento'],
        $fb['calificacion'],
        $fb['sla_cumplido'],
        $fb['estado'],
        $fb['comentario'],
        $fb['fecha']
    );
    mysqli_stmt_execute($stmt);
}
echo "✓ " . count($feedbacksData) . " evaluaciones de calidad (CSAT) registradas." . PHP_EOL;

// ============================================================================
// 8. NOTIFICACIONES DEL SISTEMA
// ============================================================================
$notificaciones = [
    [3, 'soporte', 'Ticket Asignado', 'El ticket #101 "Micro-congelamientos con DirectX 12" fue asignado al equipo técnico.', 'ticketManegement.php?ver_historial=101', 0, date('Y-m-d H:i:s', $now - 7000)],
    [6, 'desarrollo', 'Alerta SLA Warning', 'El ticket #102 "Caída severa de FPS al habilitar Ray Tracing" ha superado el 75% del tiempo límite.', 'incidentes.php?ver=202', 0, date('Y-m-d H:i:s', $now - 3600)],
    [9, 'monitoreo', 'Resumen SLA en Peligro', 'Hay 1 ticket técnico en riesgo de incumplimiento SLA en la cola de Desarrollo.', 'dashboard.php', 0, date('Y-m-d H:i:s', $now - 1800)]
];

foreach ($notificaciones as $n) {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO notificaciones (usuario_id, equipo, titulo, descripcion, enlace, leido, fecha)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "issssis", $n[0], $n[1], $n[2], $n[3], $n[4], $n[5], $n[6]);
    mysqli_stmt_execute($stmt);
}
echo "✓ " . count($notificaciones) . " notificaciones registradas." . PHP_EOL;

echo "=== CARGA COMPLETADA CON ÉXITO: TECHNOVA QUEDÓ IMPECABLE ===" . PHP_EOL;
