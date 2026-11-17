<?php
require_once("./db.php");

function normalizarTextoJuego($texto) {
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    $texto = strtolower($texto);
    return $texto;
}

if (!function_exists('redirigirJuegos')) {
    function redirigirJuegos($url = "games.php") {
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

if (!empty($_POST["crear_juego"])) {
    $nombreRaw = trim($_POST["nombre"] ?? "");
    $motorRaw = trim($_POST["motor_grafico"] ?? "");

    if (!empty($nombreRaw) && !empty($motorRaw)) {
        $nombre = mysqli_real_escape_string($conn, $nombreRaw);
        $motor_grafico = mysqli_real_escape_string($conn, $motorRaw);
        $query = "
            INSERT INTO juegos
            (nombre, motor_grafico, estado)
            VALUES
            (
                '$nombre',
                '$motor_grafico',
                'activo'
            )
        ";

        if(mysqli_query($conn, $query)){
            $_SESSION["msg"] = '<div class="toast-success">✅ ¡Juego creado exitosamente!</div>';
            unset($_SESSION["persisted_form"]);
            unset($_SESSION["persisted_form_type"]);
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form_type"] = "crear_juego";
        }
        redirigirJuegos("games.php" . (!empty($_GET['page']) ? '?page=' . $_GET['page'] : '') . (!empty($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''));
    }
    $_SESSION["msg"] = '<div class="toast-error">❌ Error: No se pudo crear el juego - Todos los campos son obligatorios.</div>';
    $_SESSION["persisted_form"] = $_POST;
    $_SESSION["persisted_form_type"] = "crear_juego";
    redirigirJuegos("games.php" . (!empty($_GET['page']) ? '?page=' . $_GET['page'] : '') . (!empty($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''));
}

if (!empty($_POST["editar_juego"])) {
    $id = intval($_POST["editar_juego"]);
    $nombreRaw = trim($_POST["nombre"] ?? "");
    $motorRaw = trim($_POST["motor_grafico"] ?? "");

    if (!empty($nombreRaw) && !empty($motorRaw)) {
        $nombre = mysqli_real_escape_string($conn, $nombreRaw);
        $motor_grafico = mysqli_real_escape_string($conn, $motorRaw);
        $query = "
            UPDATE juegos SET
                nombre = '$nombre',
                motor_grafico = '$motor_grafico'
            WHERE id = $id
        ";
        
        if(mysqli_query($conn, $query)){
            $_SESSION["msg"] = '<div class="toast-success">✅ Juego editado exitosamente.</div>';
            unset($_SESSION["persisted_form"]);
            unset($_SESSION["persisted_form_type"]);
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form"]["id"] = $id;
            $_SESSION["persisted_form_type"] = "editar_juego";
        }
    } else {
        $_SESSION["msg"] = '<div class="toast-error">❌ Error: No se pudo editar el juego - Todos los campos son obligatorios.</div>';
        $_SESSION["persisted_form"] = $_POST;
        $_SESSION["persisted_form"]["id"] = $id;
        $_SESSION["persisted_form_type"] = "editar_juego";
    }

    redirigirJuegos("games.php" . (!empty($_GET['page']) ? '?page=' . $_GET['page'] : '') . (!empty($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''));
}

function contarJuegos($conn) {
    $sql = "SELECT COUNT(*) as total FROM juegos WHERE estado != 'inactivo'";

    if (!empty($_POST["inputBuscar"]) || !empty($_GET["buscar"])) {
        $buscarRaw = $_POST["inputBuscar"] ?? $_GET["buscar"];
        $buscar = mysqli_real_escape_string($conn, trim($buscarRaw));
        $sql .= "
            AND (
                nombre LIKE '%$buscar%'
                OR motor_grafico LIKE '%$buscar%'
            )
        ";
    }

    $query = mysqli_query($conn, $sql);
    if(!$query) return 0;

    $row = mysqli_fetch_assoc($query);
    return (int)$row["total"];
}

function obtenerJuegos($conn, $limite = 10, $offset = 0){
    $sql = "SELECT * FROM juegos WHERE estado != 'inactivo'";

    if (!empty($_POST["inputBuscar"]) || !empty($_GET["buscar"])) {
        $buscarRaw = $_POST["inputBuscar"] ?? $_GET["buscar"];
        $buscar = mysqli_real_escape_string($conn, trim($buscarRaw));
        $sql .= "
            AND (
                nombre LIKE '%$buscar%'
                OR motor_grafico LIKE '%$buscar%'
            )
        ";
    }

    $sql .= " ORDER BY id DESC LIMIT $limite OFFSET $offset";

    $query = mysqli_query($conn, $sql);
    
    if(!$query) return [];

    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function eliminarJuego($conn){
    $idEliminar = intval($_POST["eliminar"]);

    // Soft delete (baja lógica)
    $queryDelete = "
        UPDATE juegos 
        SET estado = 'inactivo'
        WHERE id = $idEliminar
    ";

    if(mysqli_query($conn, $queryDelete)){
        $_SESSION["msg"] = '<div class="toast-success">✅ Juego eliminado exitosamente.</div>';
    } else {
        $_SESSION["msg"] = '<div class="toast-error">❌ Error al eliminar el juego.</div>';
    }

    redirigirJuegos("games.php");
}

//Create or edit modal
function createJuego($values = null){
  global $conn;
  require_once("db.php");

  // Only soporte can create or edit
  $canEdit = (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "soporte" || $_SESSION["currentTeam"] === "administrador"));
  $disabled_attr = (!$canEdit) ? 'disabled style="background-color: #1e293b !important; color: #94a3b8 !important; border: 1px solid #334155 !important; opacity: 0.6; cursor: not-allowed;"' : 'style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"';

  $modal_title = "Editar Juego";
  if (is_null($values)) {
      $modal_title = "Crear Nuevo Juego";
  } else if (!$canEdit) {
      $modal_title = "Detalles del Juego";
  }

  $cancel_btn_text = (!$canEdit) ? "Cerrar" : "Cancelar";

  $button = "";

  if ($canEdit) {
      if (is_null($values)) {
          $button = '<button
              type="submit"
              class="btn-registrar"
              name="crear_juego"
              value="1"
          >
              Crear Juego
          </button>';
      } else { 
          $button = '<button
              type="submit"
              class="btn-registrar"
              name="editar_juego"
              value="'.$values["id"].'">
              Guardar Cambios
          </button>'; 
      }
  }

  echo '
  <div role="dialog" id="create-juego-modal" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
    <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">

      <!-- Header -->
      <div class="mb-6 flex items-start justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white" style="color: #ffffff !important;">
          ' . $modal_title . '
          </h2>
          <p class="mt-1 text-sm text-slate-400" style="color: #94a3b8 !important;">
            ' . ($canEdit ? 'Completa la información del juego.' : 'Visualizando información del juego.') . '
          </p>
        </div>
      </div>

      <!-- Form -->
      <form class="space-y-5" method="POST" action="games.php' . (!empty($_GET['page']) ? '?page=' . $_GET['page'] : '') . (!empty($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '">

        <!-- Nombre -->
        <div>
          <label for="nombre" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
            Nombre del Juego
          </label>
          <input
            id="nombre"
            name="nombre"
            type="text"
            placeholder="Ej: Starfield"
            value="' . htmlspecialchars($values["nombre"] ?? "") . '"
            class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
            ' . $disabled_attr . '
            required
          />
        </div>

        <!-- Motor Gráfico -->
        <div>
          <label for="motor_grafico" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
            Motor Gráfico
          </label>
          <input
            id="motor_grafico"
            name="motor_grafico"
            type="text"
            placeholder="Ej: Creation Engine 2"
            value="' . htmlspecialchars($values["motor_grafico"] ?? "") . '"
            class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
            ' . $disabled_attr . '
            required
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button
            type="submit"
            class="rounded-xl border border-slate-600 px-5 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
            name="FormJuegoCancel"
            value="1"
            style="color: #cbd5e1 !important; border-color: #475569 !important; background-color: #0f172a !important;"
            formnovalidate
          >
            ' . $cancel_btn_text . '
          </button>
          ' . $button . '
        </div>

      </form>
    </div>
  </div>'; 
}
?>
