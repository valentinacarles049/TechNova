<?php
/**
 * TechNova - Componente de Interfaz del Chatbot Techy
 * HU-40: Conexión segura con el asistente virtual
 * HU-41: Memoria de chat y atención según el rol
 * HU-45: Techy: Consulta directa de datos e información completa
 * 
 * HU-45 Criterio 3: Extractor frontend con getBoundingClientRect() y estilos computados sin desbordes
 * HU-45 Criterio 4: Estructuración en markdown limpio
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION["currentUser"] ?? "Usuario";
$currentName = $_SESSION["currentName"] ?? $currentUser;
$currentRole = $_SESSION["currentTeam"] ?? "soporte";
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');

$modulosMap = [
    'dashboard.php' => ['id' => 'dashboard', 'nombre' => 'Dashboard Ejecutivo y Métricas SLA'],
    'ticketManegement.php' => ['id' => 'tickets', 'nombre' => 'Gestión de Tickets de Soporte'],
    'incidentes.php' => ['id' => 'incidencias', 'nombre' => 'Tablero de Incidencias Técnicas'],
    'feedback.php' => ['id' => 'calidad', 'nombre' => 'Tablero de Calidad, Auditorías QA y CAPA'],
    'games.php' => ['id' => 'juegos', 'nombre' => 'Catálogo de Videojuegos'],
    'drivers.php' => ['id' => 'drivers', 'nombre' => 'Gestión de Drivers y Controladores'],
    'profile.php' => ['id' => 'perfil', 'nombre' => 'Perfil del Usuario'],
    'bandejaEntrada.php' => ['id' => 'bandeja', 'nombre' => 'Bandeja de Entrada']
];

$moduloActual = $modulosMap[$currentPage] ?? ['id' => 'general', 'nombre' => 'Módulo General'];

$rolLower = strtolower($currentRole);
if ($rolLower === 'soporte') {
    $mensajeBienvenidaInicial = '¡Buenas, ' . $currentName . '! 👾 Acá Techy, tu sidekick en TechNova. Veo que estás en ' . $moduloActual['nombre'] . '. ¡Listo para darle start a la cola de tickets y subir de nivel la atención! ¿Qué caso revisamos hoy?';
} elseif ($rolLower === 'desarrollo') {
    $mensajeBienvenidaInicial = '¡Buenas, ' . $currentName . '! 💾 Techy al habla. Módulo actual: ' . $moduloActual['nombre'] . '. ¿Listo para aplastar algunos bugs e incidencias? Decime qué error técnico o driver chequeamos.';
} elseif ($rolLower === 'monitoreo') {
    $mensajeBienvenidaInicial = '¡Buenas, ' . $currentName . '! 🔄 Techy en línea. Monitoreando ' . $moduloActual['nombre'] . ' y los SLAs en tiempo real. ¿Qué métrica o indicador querés auditar?';
} else {
    $mensajeBienvenidaInicial = '¡Buenas, ' . $currentName . '! 🎉 Acá Techy, tu sidekick operativo. Módulo: ' . $moduloActual['nombre'] . '. Todos los sistemas listos. ¿En qué te doy una mano hoy?';
}

if (!isset($_SESSION['chatbot_history']) || empty($_SESSION['chatbot_history'])) {
    $_SESSION['chatbot_history'] = [
        [
            'role' => 'bot',
            'message' => $mensajeBienvenidaInicial
        ]
    ];
}
?>
<!-- --- CSS: El Diseño Visual --- -->
<style>
  /* Contenedor principal flotante */
  #technova-chatbot-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
  }

  /* Botón de alternancia flotante */
  .chatbot-toggle-btn {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0891b2 0%, #2563eb 100%);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(8, 145, 178, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    color: white;
    outline: none;
  }

  .chatbot-toggle-btn:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 6px 24px rgba(8, 145, 178, 0.5);
  }

  .chatbot-toggle-btn:active {
    transform: scale(0.95);
  }

  /* Animación del ícono */
  .chatbot-toggle-btn svg {
    transition: transform 0.4s ease, opacity 0.2s ease;
  }

  .chatbot-toggle-btn.active svg.chat-icon {
    transform: rotate(90deg);
    opacity: 0;
    display: none;
  }

  .chatbot-toggle-btn svg.close-icon {
    display: none;
  }

  .chatbot-toggle-btn.active svg.close-icon {
    display: block;
    transform: rotate(90deg);
    animation: rotateIn 0.3s forwards;
  }

  @keyframes rotateIn {
    from { transform: rotate(0deg); opacity: 0; }
    to { transform: rotate(90deg); opacity: 1; }
  }

  /* Tarjeta de la ventana del chatbot */
  #chatbot-wrapper {
    position: fixed;
    bottom: 96px;
    right: 24px;
    width: 390px;
    height: 530px;
    border: 1px solid rgba(51, 65, 85, 0.5);
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    background-color: rgba(15, 23, 42, 0.96);
    backdrop-filter: blur(12px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    opacity: 0;
    transform: translateY(20px) scale(0.95);
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  #chatbot-wrapper.open {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
  }

  /* Encabezado */
  #chat-header {
    background: linear-gradient(90deg, #0f172a 0%, #1e293b 100%);
    border-bottom: 1px solid rgba(51, 65, 85, 0.5);
    color: white;
    padding: 14px 18px;
    border-radius: 16px 16px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0;
  }

  .chat-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-grow: 1;
    min-width: 0;
  }

  .chat-avatar-container {
    position: relative;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: rgba(8, 145, 178, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(8, 145, 178, 0.4);
    flex-shrink: 0;
  }

  .chat-online-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 10px;
    height: 10px;
    background-color: #10b981;
    border: 2px solid #0f172a;
    border-radius: 50%;
    animation: pulse-green 2s infinite;
  }

  @keyframes pulse-green {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
  }

  .chat-title-wrapper {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  .chat-title {
    font-size: 14.5px;
    font-weight: 600;
    margin: 0;
    color: #f8fafc;
    line-height: 1.2;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .chat-subtitle {
    font-size: 11px;
    color: #94a3b8;
    margin: 2px 0 0 0;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .chat-header-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
  }

  .chat-close-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 6px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
  }

  .chat-close-btn:hover {
    color: #f1f5f9;
    background-color: rgba(255, 255, 255, 0.08);
  }

  /* Historial de Mensajes */
  #chat-messages {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background-color: rgba(10, 15, 30, 0.4);
  }

  #chat-messages::-webkit-scrollbar {
    width: 6px;
  }
  #chat-messages::-webkit-scrollbar-track {
    background: transparent;
  }
  #chat-messages::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.15);
    border-radius: 3px;
  }
  #chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.3);
  }

  /* Mensajes individuales y Markdown (HU-45 Criterio 4) */
  .message {
    max-width: 88%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13.5px;
    line-height: 1.5;
    word-break: break-word;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    opacity: 0;
    transform: translateY(8px);
    animation: messageFadeIn 0.25s forwards cubic-bezier(0.4, 0, 0.2, 1);
  }

  @keyframes messageFadeIn {
    to { opacity: 1; transform: translateY(0); }
  }

  .bot-message {
    background-color: rgba(30, 41, 59, 0.9);
    border: 1px solid rgba(71, 85, 105, 0.3);
    color: #f1f5f9;
    align-self: flex-start;
    border-bottom-left-radius: 2px;
  }

  .user-message {
    background: linear-gradient(135deg, #0891b2 0%, #0369a1 100%);
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 2px;
    box-shadow: 0 4px 12px rgba(8, 145, 178, 0.2);
  }

  .error-message {
    background-color: rgba(127, 29, 29, 0.45) !important;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
    color: #fca5a5 !important;
  }

  /* Estilos para Markdown limpio dentro de los globos */
  .message code {
    background-color: rgba(15, 23, 42, 0.85);
    color: #38bdf8;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 12px;
    border: 1px solid rgba(56, 189, 248, 0.2);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  }

  .user-message code {
    background-color: rgba(0, 0, 0, 0.25);
    color: #e0f2fe;
    border-color: rgba(255, 255, 255, 0.2);
  }

  .chat-md-list-item {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin: 2px 0 2px 4px;
  }

  .chat-md-bullet {
    color: #06b6d4;
    font-weight: bold;
    flex-shrink: 0;
  }

  /* Área de Entrada */
  #chat-input-area {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background-color: rgba(15, 23, 42, 0.95);
    border-top: 1px solid rgba(51, 65, 85, 0.5);
    border-radius: 0 0 16px 16px;
    gap: 8px;
  }

  #user-input {
    flex: 1;
    background-color: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(71, 85, 105, 0.4);
    border-radius: 20px;
    padding: 9px 16px;
    font-size: 13.5px;
    color: white;
    outline: none;
    transition: all 0.2s ease;
  }

  #user-input:focus {
    border-color: #0891b2;
    background-color: rgba(30, 41, 59, 1);
    box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.2);
  }

  #user-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  #send-btn {
    background: linear-gradient(135deg, #0891b2 0%, #0284c7 100%);
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    outline: none;
    flex-shrink: 0;
  }

  #send-btn:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(8, 145, 178, 0.4);
  }

  #send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  /* Indicador de escritura animado */
  .typing-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 0;
  }

  .typing-indicator span {
    width: 6px;
    height: 6px;
    background-color: #94a3b8;
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out both;
  }

  .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
  .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

  @keyframes typing {
    0%, 80%, 100% { transform: scale(0); opacity: 0.4; }
    40% { transform: scale(1); opacity: 1; }
  }
</style>

<!-- --- HTML: Estructura de la interfaz --- -->
<div id="technova-chatbot-container"
     data-user="<?php echo htmlspecialchars($currentUser); ?>"
     data-user-name="<?php echo htmlspecialchars($currentName); ?>"
     data-role="<?php echo htmlspecialchars($currentRole); ?>"
     data-module-id="<?php echo htmlspecialchars($moduloActual['id']); ?>"
     data-module-name="<?php echo htmlspecialchars($moduloActual['nombre']); ?>"
     data-welcome-msg="<?php echo htmlspecialchars($mensajeBienvenidaInicial); ?>">

  <!-- Botón Circular Flotante -->
  <button id="chatbot-toggle" class="chatbot-toggle-btn" onclick="toggleChatbot()" title="Asistente Techy">
    <!-- Icono de Mensaje -->
    <svg class="chat-icon" xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
    <!-- Icono de Cierre (X) -->
    <svg class="close-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="18" y1="6" x2="6" y2="18"></line>
      <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
  </button>

  <!-- Ventana de Chat -->
  <div id="chatbot-wrapper">
    <div id="chat-header">
      <div class="chat-header-info">
        <div class="chat-avatar-container">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="10" rx="2"></rect>
            <circle cx="12" cy="5" r="2"></circle>
            <path d="M12 7v4"></path>
            <line x1="8" y1="16" x2="8.01" y2="16"></line>
            <line x1="16" y1="16" x2="16.01" y2="16"></line>
          </svg>
          <span class="chat-online-dot"></span>
        </div>
        <div class="chat-title-wrapper">
          <h3 class="chat-title">Techy • Asistente Virtual</h3>
          <p class="chat-subtitle" title="<?php echo htmlspecialchars($moduloActual['nombre'] . ' • ' . ucfirst($currentRole)); ?>">
            <?php echo htmlspecialchars($moduloActual['nombre']); ?>
          </p>
        </div>
      </div>
      <div class="chat-header-actions">
        <button class="chat-close-btn" onclick="reiniciarChatbot()" title="Reiniciar conversación con Techy">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
            <path d="M21 3v5h-5"></path>
            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
            <path d="M3 21v-5h5"></path>
          </svg>
        </button>
        <button class="chat-close-btn" onclick="toggleChatbot()" title="Cerrar ventana">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
    
    <div id="chat-messages">
      <!-- Los mensajes se restauran dinámicamente desde sessionStorage -->
    </div>

    <div id="chat-input-area">
      <input type="text" id="user-input" placeholder="Pregunta sobre tickets, incidencias, OP..." autocomplete="off">
      <button id="send-btn" onclick="enviarMensajeChatbot()" title="Enviar mensaje">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="22" y1="2" x2="11" y2="13"></line>
          <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
        </svg>
      </button>
    </div>
  </div>
</div>

<!-- --- JAVASCRIPT: Motor de memoria, contexto y persistencia (HU-41 & HU-45) --- -->
<script>
  const STORAGE_KEY_CHAT = 'technova_chat_history_v2';
  let chatbotEstaEnviando = false;

  function obtenerHistorialStorage() {
    try {
      const datos = sessionStorage.getItem(STORAGE_KEY_CHAT);
      if (datos) {
        const parsed = JSON.parse(datos);
        if (Array.isArray(parsed) && parsed.length > 0) {
          return parsed;
        }
      }
    } catch (e) {
      console.warn("No se pudo leer sessionStorage:", e);
    }
    return null;
  }

  function guardarHistorialStorage(historial) {
    try {
      sessionStorage.setItem(STORAGE_KEY_CHAT, JSON.stringify(historial));
    } catch (e) {
      console.warn("No se pudo guardar en sessionStorage:", e);
    }
  }

  // HU-45 Criterio 4: Formateador de Markdown limpio y seguro
  function formatearTextoMarkdown(texto) {
    if (!texto) return '';

    // 1. Escapar caracteres HTML básicos para prevenir inyecciones
    let html = texto
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");

    // 2. Bloques de código (```código```)
    html = html.replace(/```([a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, function(match, lang, code) {
      return '<pre style="background: rgba(15, 23, 42, 0.9); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(71, 85, 105, 0.4); margin: 6px 0; overflow-x: auto;"><code style="color: #38bdf8; font-family: monospace; font-size: 12px;">' + code.trim() + '</code></pre>';
    });

    // 3. Código inline (`código`)
    html = html.replace(/`([^`]+)`/g, '<code style="background: rgba(15, 23, 42, 0.85); color: #38bdf8; padding: 1px 5px; border-radius: 4px; font-family: monospace; font-size: 12px; border: 1px solid rgba(56, 189, 248, 0.2);">$1</code>');

    // 4. Encabezados (### Titulo y ## Titulo)
    html = html.replace(/^### (.*$)/gim, '<div style="font-weight: 700; color: #f8fafc; font-size: 14px; margin-top: 8px; margin-bottom: 4px;">$1</div>');
    html = html.replace(/^## (.*$)/gim, '<div style="font-weight: 700; color: #38bdf8; font-size: 15px; margin-top: 10px; margin-bottom: 6px;">$1</div>');

    // 5. Negrita (**texto**) y cursiva (*texto*)
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong style="color: #ffffff; font-weight: 600;">$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em style="color: #cbd5e1;">$1</em>');

    // 6. Viñetas de lista (- item, • item, * item)
    html = html.replace(/^[•\-\*]\s+(.*$)/gim, '<div class="chat-md-list-item"><span class="chat-md-bullet">•</span><span>$1</span></div>');

    // 7. Saltos de línea y espaciados
    html = html.replace(/\n\n+/g, '<div style="height: 6px;"></div>');
    html = html.replace(/\n/g, '<br>');

    return html;
  }

  // Renderizar la lista completa de mensajes en la pantalla
  function renderizarHistorialCompleto(historial) {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return;

    chatMessages.innerHTML = '';
    historial.forEach(item => {
      const claseCSS = (item.role === 'user') ? 'user-message' : 'bot-message';
      const div = document.createElement('div');
      div.className = 'message ' + claseCSS;
      div.innerHTML = formatearTextoMarkdown(item.message);
      chatMessages.appendChild(div);
    });

    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  // Alternar apertura y cierre de la ventana
  function toggleChatbot() {
    const wrapper = document.getElementById('chatbot-wrapper');
    const btn = document.getElementById('chatbot-toggle');
    if (!wrapper || !btn) return;

    wrapper.classList.toggle('open');
    btn.classList.toggle('active');

    const isOpen = wrapper.classList.contains('open');
    localStorage.setItem('chatbot_open', isOpen ? 'true' : 'false');

    if (isOpen) {
      const chatMessages = document.getElementById('chat-messages');
      if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
      }
      setTimeout(() => {
        const inputField = document.getElementById('user-input');
        if (inputField) inputField.focus();
      }, 250);
    }
  }

  // Reiniciar la conversación
  async function reiniciarChatbot() {
    if (!confirm("¿Deseas reiniciar la conversación con Techy?")) return;

    const container = document.getElementById('technova-chatbot-container');
    const welcomeMsg = container ? container.dataset.welcomeMsg : "¡Hola! Soy Techy, ¿en qué puedo ayudarte?";
    const nuevoHistorial = [{ role: 'bot', message: welcomeMsg }];

    guardarHistorialStorage(nuevoHistorial);
    renderizarHistorialCompleto(nuevoHistorial);

    try {
      await fetch('chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'reset' })
      });
    } catch (e) {
      // Ignorar fallo de sincronización al resetear
    }
  }

  // ==============================================================================
  // HU-45 CRITERIO 3: EXTRACTOR DE MODALES VISIBLES MEDIANTE ESTILOS COMPUTADOS
  // Y DIMENSIONES REALES (getBoundingClientRect())
  // ==============================================================================
  function esModalAbiertoYVisible(elem) {
    if (!elem) return false;
    // Si tiene clase 'hidden' o style inline display:none
    if (elem.classList.contains('hidden') || elem.style.display === 'none') return false;

    // Verificar estilos computados del elemento
    const estilo = window.getComputedStyle(elem);
    if (estilo.display === 'none' || estilo.visibility === 'hidden' || parseFloat(estilo.opacity) === 0) {
      return false;
    }

    // Verificar dimensiones reales físicas en el viewport con getBoundingClientRect()
    const rect = elem.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) {
      return false;
    }

    // Comprobar que la tarjeta interior del diálogo esté efectivamente visible y no colapsada
    const cajaInterior = elem.querySelector('.relative, [class*="rounded"], form, [class*="max-w"], .modal-content') || elem;
    const rectCaja = cajaInterior.getBoundingClientRect();
    if (rectCaja.width <= 20 || rectCaja.height <= 20) {
      return false;
    }

    return true;
  }

  // HU-45: Detectar información de tickets, incidencias, opiniones y modales en pantalla sin desbordes
  function detectarDatosTicketEnPantalla() {
    // 1. Identificar modales abiertos mediante estilos computados y dimensiones reales (getBoundingClientRect)
    const modales = document.querySelectorAll(
      '#create-ticket-modal, #historial-modal, #incidencia-modal, #detail-incidencia-modal, #detail-feedback-modal, #create-feedback-modal, #create-qa-modal, #detail-qa-modal, #create-capa-modal, #detail-capa-modal, [role="dialog"], [aria-modal="true"], [id*="modal"], [id*="Modal"]'
    );

    for (const modal of modales) {
      // Ignorar el propio contenedor del chatbot si tuviera clase/id modal
      if (modal.closest('#technova-chatbot-container') || modal.id === 'chatbot-wrapper') continue;

      if (esModalAbiertoYVisible(modal)) {
        const partesCapturadas = [];
        
        // A. Capturar Título del Modal sin desbordes
        let tituloModal = '';
        const elemTitulo = modal.querySelector('#detail-incidencia-title, #detail-feedback-title, #create-ticket-title, #historial-modal-title, [id*="title"], [id*="Title"], h2, h3, h1');
        if (elemTitulo) {
          tituloModal = elemTitulo.textContent.replace(/\s+/g, ' ').trim();
          if (tituloModal.length > 120) {
            tituloModal = tituloModal.substring(0, 117) + '...';
          }
        }
        if (!tituloModal) tituloModal = 'Ventana Modal Abierta';
        partesCapturadas.push(`Título: ${tituloModal}`);

        // B. Capturar Códigos específicos (TKT-xxx, INC-xxx, OP-xxx, QA-xxx, CAPA-xxx)
        const codigosDetectados = new Set();
        const idTicketAttr = modal.dataset.ticketId || 
                             modal.querySelector('[name="ticket_id"]')?.value || 
                             modal.querySelector('button[name="editar_ticket"]')?.value;
        if (idTicketAttr) {
          const codTkt = isNaN(idTicketAttr) ? idTicketAttr : 'TKT-' + idTicketAttr.toString().padStart(3, '0');
          codigosDetectados.add(codTkt);
        }

        const idIncAttr = modal.dataset.incidenciaId || 
                          modal.querySelector('[name="editar_incidencia"]')?.value ||
                          modal.querySelector('[name="incidencia_id"]')?.value;
        if (idIncAttr) {
          const codInc = isNaN(idIncAttr) ? idIncAttr : 'INC-' + idIncAttr.toString().padStart(3, '0');
          codigosDetectados.add(codInc);
        }

        // Buscar códigos presentes en el texto del modal
        const textoCompletoModal = modal.textContent || '';
        const regexCodigos = /\b(TKT-\d+|INC-\d+|OP-\d+|QA-\d+|CAPA-\d+)\b/gi;
        let matchCod;
        while ((matchCod = regexCodigos.exec(textoCompletoModal)) !== null) {
          codigosDetectados.add(matchCod[1].toUpperCase());
        }

        if (codigosDetectados.size > 0) {
          partesCapturadas.push(`Códigos Identificados: ` + Array.from(codigosDetectados).join(', '));
        }

        // C. Capturar campos interactivos de formulario (inputs, selects, textareas)
        const camposForm = [];
        const etiquetasMapeadas = {
          'titulo': 'Título',
          'descripcion': 'Descripción',
          'categoria': 'Categoría',
          'prioridad': 'Prioridad',
          'es_incidencia': '¿Es Incidencia?',
          'juego_id': 'Videojuego',
          'driver_id': 'Driver / Controlador',
          'usuario': 'Usuario Solicitante',
          'asignado': 'Asignado a',
          'estado': 'Estado',
          'detalle': 'Detalle Técnico',
          'ticket_padre_id': 'Ticket Padre Relacionado',
          'tema': 'Tema / Asunto',
          'sentimiento': 'Sentimiento',
          'comentario': 'Comentario',
          'tipo': 'Tipo'
        };

        const inputs = modal.querySelectorAll('input, select, textarea');
        inputs.forEach(el => {
          if (el.type === 'hidden' && el.name !== 'ticket_id' && el.name !== 'incidencia_id') return;
          if (el.type === 'submit' || el.type === 'button') return;

          const nombre = el.name || el.id;
          if (!nombre) return;

          let valor = '';
          if (el.tagName.toLowerCase() === 'select') {
            const opt = el.options[el.selectedIndex];
            valor = opt ? opt.text.trim() : el.value;
            if (valor.startsWith('Selecciona')) valor = '';
          } else if (el.type === 'checkbox') {
            valor = el.checked ? 'Sí' : 'No';
          } else {
            valor = el.value ? el.value.replace(/\s+/g, ' ').trim() : '';
          }

          if (valor !== '') {
            // Control de desborde individual de campo
            if (valor.length > 200) {
              valor = valor.substring(0, 197) + '...';
            }
            const etiqueta = etiquetasMapeadas[nombre] || nombre;
            camposForm.push(`${etiqueta}: ${valor}`);
          }
        });

        if (camposForm.length > 0) {
          partesCapturadas.push(`Campos del Formulario: ` + camposForm.join(' | '));
        }

        // D. Capturar campos de vistas de detalle sólo lectura (ej: detail-incidencia-modal, detail-feedback-modal)
        const paresDetalle = [];
        const bloquesDatos = modal.querySelectorAll('.grid > div, [class*="grid-cols"] > div, .space-y-4 > div');
        bloquesDatos.forEach(bloque => {
          const etiquetaElem = bloque.querySelector('span.text-xs, label, dt, .font-semibold');
          const valorElem = bloque.querySelector('span.text-sm, span.font-medium, dd, p');
          if (etiquetaElem && valorElem && etiquetaElem !== valorElem) {
            const k = etiquetaElem.textContent.replace(/\s+/g, ' ').trim();
            let v = valorElem.textContent.replace(/\s+/g, ' ').trim();
            if (k && v && !k.includes('Acciones')) {
              if (v.length > 200) v = v.substring(0, 197) + '...';
              paresDetalle.push(`${k}: ${v}`);
            }
          }
        });

        if (paresDetalle.length > 0) {
          partesCapturadas.push(`Detalles Visualizados: ` + paresDetalle.slice(0, 10).join(' | '));
        }

        // Si es modal de historial de ticket
        if (modal.id === 'historial-modal') {
          const itemsHist = [];
          modal.querySelectorAll('.relative p, .rounded-xl p').forEach(p => {
            const txt = p.textContent.replace(/\s+/g, ' ').trim();
            if (txt) itemsHist.push(txt.length > 100 ? txt.substring(0, 97) + '...' : txt);
          });
          if (itemsHist.length > 0) {
            partesCapturadas.push(`Historial Reciente: ` + itemsHist.slice(0, 5).join(' | '));
          }
        }

        let resultadoModal = `Modal activo en pantalla [${tituloModal}] -> ` + partesCapturadas.join(' // ');
        // Control total de desbordes: no exceder 1500 caracteres
        if (resultadoModal.length > 1500) {
          resultadoModal = resultadoModal.substring(0, 1495) + '...';
        }
        return resultadoModal;
      }
    }

    // 2. Si no hay modal abierto, capturar tabla visible en pantalla (sin desbordes)
    const filasTabla = document.querySelectorAll('table tbody tr');
    if (filasTabla && filasTabla.length > 0) {
      const itemsVisibles = [];
      filasTabla.forEach((fila, index) => {
        if (index < 6) { // Primeras 6 filas para evitar desbordes
          const celdas = fila.querySelectorAll('td');
          if (celdas.length >= 5) {
            const idTxt = celdas[0]?.textContent?.trim() || '';
            if (idTxt.startsWith('OP-')) {
              const usuarioTxt = celdas[1]?.textContent?.trim() || '';
              const tipoTxt = celdas[2]?.textContent?.trim() || '';
              const temaTxt = celdas[3]?.textContent?.trim() || '';
              const sentTxt = celdas[4]?.textContent?.trim() || '';
              const estTxt = celdas[5]?.textContent?.trim() || '';
              itemsVisibles.push(`${idTxt}: "${temaTxt}" [Tipo: ${tipoTxt}, Sentimiento: ${sentTxt}, Estado: ${estTxt}, Usuario: ${usuarioTxt}]`);
            } else {
              const tituloTxt = celdas[1]?.textContent?.trim() || '';
              const prioTxt = celdas[3]?.textContent?.trim() || '';
              const estadoTxt = celdas[6]?.textContent?.trim() || '';
              const asigTxt = celdas[7]?.textContent?.trim() || '';
              if (idTxt) {
                itemsVisibles.push(`${idTxt}: "${tituloTxt}" (${estadoTxt}, Prioridad: ${prioTxt}, Asignado: ${asigTxt || 'N/A'})`);
              }
            }
          }
        }
      });
      if (itemsVisibles.length > 0) {
        const esFeedback = itemsVisibles[0]?.startsWith('OP-');
        const prefijo = esFeedback ? 'Lista de feedbacks visibles en pantalla (Tablero de Calidad): ' : 'Lista de registros visibles en la tabla: ';
        return prefijo + itemsVisibles.join(' ; ');
      }
    }

    // 3. Revisar parámetros URL
    const params = new URLSearchParams(window.location.search);
    const idTicket = params.get('id') || params.get('ticket') || params.get('ver_historial');
    const idVer = params.get('ver');
    if (idTicket) {
      return "Visualizando en pantalla Ticket #" + idTicket;
    }
    if (idVer) {
      return "Visualizando en pantalla Registro #" + idVer;
    }

    return "Ningún registro o modal abierto en pantalla actualmente.";
  }

  // HU-41 SUBTAREA 1 & 2 & HU-45: Enviar historial y contexto dinámico
  async function enviarMensajeChatbot() {
    if (chatbotEstaEnviando) return;

    const inputField = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');
    const container = document.getElementById('technova-chatbot-container');
    const textoUsuario = inputField.value.trim();

    if (textoUsuario === "") return;

    chatbotEstaEnviando = true;
    if (sendBtn) sendBtn.disabled = true;
    if (inputField) inputField.disabled = true;

    // Obtener historial actual
    let historialActual = obtenerHistorialStorage() || [];
    historialActual.push({ role: 'user', message: textoUsuario });
    guardarHistorialStorage(historialActual);

    // Renderizar globo del usuario
    agregarMensajeEnPantallaChatbot(textoUsuario, 'user-message');
    inputField.value = "";

    // Indicador de "Escribiendo..."
    const typingId = agregarMensajeEnPantallaChatbot('<div class="typing-indicator"><span></span><span></span><span></span></div>', 'bot-message', true);

    try {
      const payload = {
        pregunta: textoUsuario,
        historial: historialActual.slice(-8), // Enviar los últimos intercambios
        contexto: {
          modulo_id: container ? container.dataset.moduleId : 'general',
          modulo_nombre: container ? container.dataset.moduleName : 'Sistema TechNova',
          rol: container ? container.dataset.role : 'soporte',
          usuario: container ? container.dataset.user : 'Usuario',
          ticket_data: detectarDatosTicketEnPantalla()
        }
      };

      const response = await fetch('chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const typingElem = document.getElementById(typingId);
      if (typingElem) typingElem.remove();

      let data = null;
      try {
        data = await response.json();
      } catch (e) {
        data = null;
      }

      if (!response.ok) {
        const mensajeError = (data && data.respuesta) 
          ? data.respuesta 
          : (response.status === 401 
              ? "Tu sesión no es válida o ha expirado. Por favor, inicia sesión para continuar." 
              : "No se pudo procesar la solicitud (Error " + response.status + ").");
        
        agregarMensajeEnPantallaChatbot(mensajeError, 'bot-message error-message');
        return;
      }

      const respuestaTexto = (data && data.respuesta) ? data.respuesta : "No se recibió respuesta de Techy.";
      
      // Actualizar memoria en sessionStorage con la respuesta de Techy
      historialActual.push({ role: 'bot', message: respuestaTexto });
      guardarHistorialStorage(historialActual);

      // Renderizar respuesta de Techy con formato markdown limpio (HU-45 Criterio 4)
      agregarMensajeEnPantallaChatbot(respuestaTexto, 'bot-message');

    } catch (error) {
      console.error("Error chatbot:", error);
      const typingElem = document.getElementById(typingId);
      if (typingElem) typingElem.remove();

      agregarMensajeEnPantallaChatbot("No se pudo conectar con el servidor de TechNova. Verifica tu conexión a internet o intenta nuevamente.", 'bot-message error-message');
    } finally {
      chatbotEstaEnviando = false;
      if (sendBtn) sendBtn.disabled = false;
      if (inputField) {
        inputField.disabled = false;
        inputField.focus();
      }
    }
  }

  function agregarMensajeEnPantallaChatbot(texto, claseCSS, isHTML = false) {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return '';

    const nuevoDiv = document.createElement('div');
    nuevoDiv.className = 'message ' + claseCSS;

    if (isHTML) {
      nuevoDiv.innerHTML = texto;
    } else {
      nuevoDiv.innerHTML = formatearTextoMarkdown(texto);
    }

    const idUnico = 'technova-msg-' + Date.now() + Math.random().toString(36).substr(2, 4);
    nuevoDiv.id = idUnico;

    chatMessages.appendChild(nuevoDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return idUnico;
  }

  // Permitir enviar con la tecla Enter en el input del chatbot
  const userInputEl = document.getElementById('user-input');
  if (userInputEl) {
    userInputEl.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        enviarMensajeChatbot();
      }
    });
  }

  // Inicializar y restaurar historial y posición
  document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById('technova-chatbot-container');
    if (container) {
      document.body.appendChild(container);
    }

    // Restaurar o inicializar historial persistente
    let historial = obtenerHistorialStorage();
    if (!historial || historial.length === 0) {
      const welcomeMsg = container ? container.dataset.welcomeMsg : "¡Hola! Soy Techy, ¿en qué puedo ayudarte?";
      historial = [{ role: 'bot', message: welcomeMsg }];
      guardarHistorialStorage(historial);
    }
    renderizarHistorialCompleto(historial);

    // Restaurar si el chat estaba abierto
    const chatbotOpen = localStorage.getItem('chatbot_open') === 'true';
    if (chatbotOpen) {
      const wrapper = document.getElementById('chatbot-wrapper');
      const btn = document.getElementById('chatbot-toggle');
      if (wrapper && btn) {
        wrapper.classList.add('open');
        btn.classList.add('active');
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
          setTimeout(() => {
            chatMessages.scrollTop = chatMessages.scrollHeight;
          }, 60);
        }
      }
    }
  });
</script>