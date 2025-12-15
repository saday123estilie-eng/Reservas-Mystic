<?php
// admin/usuarios.php
// Gestión de usuarios: listar, crear, editar y eliminar

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'ADMIN') {
    header("Location: ../login.php"); // ajusta si tu login está en otra ruta
    exit;
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Admin';
$rolUsuario    = $_SESSION['rol']    ?? 'ADMIN';

require_once __DIR__ . '/../config/conexion.php';

$mensajeOk  = '';
$mensajeErr = '';

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Mensajes GET (PRG)
if (isset($_GET['ok'])) {
    switch ($_GET['ok']) {
        case 'creado':
            $mensajeOk = "Usuario creado correctamente.";
            break;
        case 'editado':
            $mensajeOk = "Usuario actualizado correctamente.";
            break;
        case 'eliminado':
            $mensajeOk = "Usuario eliminado correctamente.";
            break;
    }
}

// ============ 1) Procesar POST (crear/editar/eliminar) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Normalizar datos básicos
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email']  ?? '');
    $rolNew = $_POST['rol'] ?? 'ASESOR';

    // Roles permitidos (según la BD)
    $rolesPermitidos = ['ASESOR', 'ADMIN', 'RECEPCION', 'OTRO'];
    if (!in_array($rolNew, $rolesPermitidos, true)) {
        $rolNew = 'ASESOR';
    }

    // ===== CREAR USUARIO (usa el formulario "bonito" de abajo) =====
    if ($accion === 'crear') {
        // nombres desde tu formulario: password y password2
        $pass  = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if ($nombre === '' || $email === '' || $pass === '' || $pass2 === '') {
            $mensajeErr = "Todos los campos son obligatorios para crear un usuario.";
        } elseif ($pass !== $pass2) {
            $mensajeErr = "Las contraseñas no coinciden.";
        } elseif (strlen($pass) < 6) {
            $mensajeErr = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT);

                $sqlIns = "
                    INSERT INTO usuarios (nombre, email, password_hash, rol)
                    VALUES (:nombre, :email, :pass, :rol)
                ";
                $stmtIns = $pdo->prepare($sqlIns);
                $stmtIns->execute([
                    ':nombre' => $nombre,
                    ':email'  => $email,
                    ':pass'   => $hash,
                    ':rol'    => $rolNew,
                ]);

                header("Location: usuarios.php?ok=creado");
                exit;

            } catch (PDOException $e) {
                if ((int)$e->errorInfo[1] === 1062) {
                    $mensajeErr = "Ya existe un usuario con ese correo electrónico.";
                } else {
                    $mensajeErr = "Error al crear usuario: " . $e->getMessage();
                }
            }
        }
    }

    // ===== EDITAR USUARIO =====
    if ($accion === 'editar') {
        $idUsuarioEditar = (int)($_POST['id_usuario'] ?? 0);
        $passNueva       = $_POST['password'] ?? ''; // opcional en edición

        if ($idUsuarioEditar <= 0) {
            $mensajeErr = "ID de usuario inválido para editar.";
        } else {
            try {
                // Traer usuario actual
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id LIMIT 1");
                $stmt->execute([':id' => $idUsuarioEditar]);
                $userOrig = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$userOrig) {
                    $mensajeErr = "Usuario no encontrado.";
                } else {
                    $hash = $userOrig['password_hash'];

                    // Si se envía una nueva contraseña, se actualiza
                    if ($passNueva !== '') {
                        if (strlen($passNueva) < 6) {
                            $mensajeErr = "La nueva contraseña debe tener al menos 6 caracteres.";
                        } else {
                            $hash = password_hash($passNueva, PASSWORD_BCRYPT);
                        }
                    }

                    if ($mensajeErr === '') {
                        $sqlUpd = "
                            UPDATE usuarios
                            SET nombre = :nombre,
                                email  = :email,
                                password_hash = :pass,
                                rol    = :rol
                            WHERE id_usuario = :id
                        ";
                        $stmtUpd = $pdo->prepare($sqlUpd);
                        $stmtUpd->execute([
                            ':nombre' => $nombre !== '' ? $nombre : $userOrig['nombre'],
                            ':email'  => $email  !== '' ? $email  : $userOrig['email'],
                            ':pass'   => $hash,
                            ':rol'    => $rolNew,
                            ':id'     => $idUsuarioEditar,
                        ]);

                        header("Location: usuarios.php?ok=editado");
                        exit;
                    }
                }
            } catch (PDOException $e) {
                if ((int)$e->errorInfo[1] === 1062) {
                    $mensajeErr = "Ya existe un usuario con ese correo electrónico.";
                } else {
                    $mensajeErr = "Error al editar usuario: " . $e->getMessage();
                }
            }
        }
    }

    // ===== ELIMINAR USUARIO =====
    if ($accion === 'eliminar') {
        $idEliminar = (int)($_POST['id_usuario'] ?? 0);

        if ($idEliminar <= 0) {
            $mensajeErr = "ID de usuario inválido para eliminar.";
        } elseif ($idEliminar === (int)$_SESSION['id_usuario']) {
            $mensajeErr = "No puedes eliminar tu propio usuario mientras estás logueado.";
        } else {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
                $stmtDel->execute([':id' => $idEliminar]);

                header("Location: usuarios.php?ok=eliminado");
                exit;
            } catch (PDOException $e) {
                $mensajeErr = "Error al eliminar usuario: " . $e->getMessage();
            }
        }
    }
}

// ============ 2) Cargar datos para mostrar ============
$usuarios      = [];
$usuarioEditar = null;
$editId        = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

try {
    $stmt = $pdo->query("
        SELECT id_usuario, nombre, email, rol, creado_en
        FROM usuarios
        ORDER BY creado_en DESC
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensajeErr = "Error al cargar usuarios: " . $e->getMessage();
}

// Si hay edit=ID, traemos ese usuario
if ($editId > 0) {
    try {
        $stmtEd = $pdo->prepare("SELECT id_usuario, nombre, email, rol FROM usuarios WHERE id_usuario = :id LIMIT 1");
        $stmtEd->execute([':id' => $editId]);
        $usuarioEditar = $stmtEd->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensajeErr = "Error al cargar usuario para edición: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de usuarios | Mystic Paradise</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

  <style>
    body{
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    }
    :root{
      --ink:#e5e7eb;
      --muted:#9ca3af;

      --brand:#2563eb;
      --brand-2:#38bdf8;
      --brand-3:#6366f1;

      --shadow-1:0 2px 10px rgba(0,0,0,.45);
      --brand-grad: linear-gradient(120deg,#1d4ed8 0%,#2563eb 25%,#38bdf8 60%,#6366f1 100%);
      --brand-logo-url: url("https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png");
    }

    .topbar{
      position:sticky; top:0; z-index:40;
      background:rgba(15,23,42,.96);
      backdrop-filter: blur(16px);
      border-bottom:1px solid #1f2937;
    }
    .topbar::before{
      content:""; position:absolute; inset:0 0 auto 0; height:3px;
      background:var(--brand-grad);
    }
    .topbar-inner{
      max-width:1200px;
      margin:0 auto;
      padding:10px 14px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
    }
    .brand{
      display:flex; align-items:center; gap:10px;
      flex-wrap:wrap;
    }
    .brand-logo{
      width:36px; height:36px;
      border-radius:12px;
      border:1px solid #1f2937;
      background:#020617;
      box-shadow:var(--shadow-1);
      display:grid; place-items:center;
      overflow:hidden;
    }
    .brand-logo::before{
      content:""; width:100%; height:100%;
      background-image:var(--brand-logo-url);
      background-size:contain;
      background-repeat:no-repeat;
      background-position:center;
    }
    .brand-text{
      display:flex; flex-direction:column;
    }
    .brand-title{
      font-weight:900;
      font-size:14px;
      letter-spacing:.2px;
      color:var(--ink);
    }
    .brand-sub{
      font-size:12px;
      color:#64748b;
    }

    .top-actions{
      display:flex; align-items:center; gap:10px;
      flex-wrap:wrap;
    }
    .btn-top{
      border-radius:999px;
      padding:7px 13px;
      font-size:12px;
      font-weight:600;
      letter-spacing:.2px;
      border:1px solid #1f2937;
      background:#020617;
      color:var(--ink);
      box-shadow:var(--shadow-1);
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      gap:6px;
      cursor:pointer;
    }
    .btn-top:hover{
      border-color:#2563eb;
    }
    .btn-top svg{width:14px;height:14px;}
    .btn-top-back{
      border-color:#3b82f6;
      color:#dbeafe;
    }

    .avatar-chip{
      border-radius:999px;
      padding:4px 8px 4px 4px;
      border:1px solid #1f2937;
      background:rgba(15,23,42,.95);
      display:flex;
      align-items:center;
      gap:8px;
    }
    .avatar-circle{
      width:28px;height:28px;
      border-radius:999px;
      background:linear-gradient(135deg,#3b82f6,#6366f1);
      display:grid;place-items:center;
      font-size:13px;
      font-weight:800;
      color:white;
    }
    .avatar-meta{
      display:flex; flex-direction:column;
    }
    .avatar-name{
      font-size:12px;
      font-weight:700;
      color:var(--ink);
    }
    .avatar-role{
      font-size:11px;
      color:#64748b;
    }

    @media (max-width: 768px){
      .topbar-inner{
        flex-direction:column;
        align-items:flex-start;
      }
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-50">

  <!-- 🔹 Encabezado Mystic (topbar) -->
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand">
        <!-- Volver a pantalla anterior -->
        <a href="javascript:window.history.back();" class="btn-top btn-top-back" title="Volver a la pantalla anterior">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M10 19l-7-7 7-7M3 12h18"/>
          </svg>
          Volver
        </a>

        <!-- Menú administrador -->
        <a href="admin_menu.php" class="btn-top" title="Ir al panel de administrador">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/>
          </svg>
          Menú admin
        </a>

        <div class="brand-logo"></div>
        <div class="brand-text">
          <span class="brand-title">MYSTIC PARADISE</span>
          <span class="brand-sub">Gestión de usuarios</span>
        </div>
      </div>

      <div class="top-actions">
        <!-- Atajo a reservas (opcional) -->
        <a href="../Vista/Ver_reservas.php" class="btn-top" title="Ver reservas">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 5h16M4 10h16M4 15h10M4 20h7"/>
          </svg>
          Reservas
        </a>

        <!-- Chip usuario -->
        <div class="avatar-chip">
          <div class="avatar-circle">
            <?php
              $ini = mb_strtoupper(mb_substr($nombreUsuario,0,1,'UTF-8'),'UTF-8');
              echo htmlspecialchars($ini,ENT_QUOTES,'UTF-8');
            ?>
          </div>
          <div class="avatar-meta">
            <span class="avatar-name">
              <?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="avatar-role"><?= htmlspecialchars($rolUsuario, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">

    <!-- Encabezado interno -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-2">
      <div>
        <h1 class="text-2xl font-semibold text-blue-400">Usuarios del sistema</h1>
        <p class="text-sm text-slate-300 mt-1">
          Administra los usuarios que pueden ingresar al backoffice: crea nuevos, edita datos o elimina accesos.
        </p>
      </div>
      <div class="flex flex-col items-end text-xs text-slate-400">
        <span>Usuario: <span class="text-slate-100 font-semibold"><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></span></span>
        <span>Rol: <span class="text-slate-100 font-semibold"><?= htmlspecialchars($rolUsuario, ENT_QUOTES, 'UTF-8') ?></span></span>
      </div>
    </div>

    <!-- Mensajes -->
    <?php if ($mensajeOk): ?>
      <div class="rounded-lg bg-slate-900 border border-blue-500 px-4 py-2 text-sm text-blue-200">
        <?= htmlspecialchars($mensajeOk, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($mensajeErr): ?>
      <div class="rounded-lg bg-slate-900 border border-red-500 px-4 py-2 text-sm text-red-200">
        <?= htmlspecialchars($mensajeErr, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Formulario EDITAR (compacto arriba si hay ?edit=ID) -->
    <?php if ($usuarioEditar): ?>
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-lg font-medium text-slate-50">Editar usuario</h2>
          <a href="usuarios.php" class="text-xs text-slate-300 hover:text-blue-300 underline">
            Cancelar edición
          </a>
        </div>
        <p class="text-xs text-slate-400">
          Solo cambia la contraseña si deseas regenerarla; si la dejas vacía, se mantiene igual.
        </p>
        <form method="post" class="grid gap-3 md:grid-cols-4 md:items-end text-sm">
          <input type="hidden" name="accion" value="editar">
          <input type="hidden" name="id_usuario" value="<?= (int)$usuarioEditar['id_usuario'] ?>">

          <div>
            <label class="block text-xs text-slate-400 mb-1">Nombre</label>
            <input type="text" name="nombre"
                   value="<?= htmlspecialchars($usuarioEditar['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1.5">
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Correo electrónico</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($usuarioEditar['email'], ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1.5">
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Rol</label>
            <select name="rol"
                    class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1.5">
              <?php
                $roles = ['ASESOR','ADMIN','RECEPCION','OTRO'];
                foreach ($roles as $r):
              ?>
                <option value="<?= $r ?>" <?= $usuarioEditar['rol'] === $r ? 'selected' : '' ?>>
                  <?= $r ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Nueva contraseña</label>
            <input type="password" name="password"
                   placeholder="Dejar en blanco para no cambiar"
                   class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1.5">
          </div>

          <div class="md:col-span-4 flex justify-end mt-2">
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 px-5 py-2 text-sm font-medium text-white">
              Guardar cambios
            </button>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <!-- Listado de usuarios -->
    <section id="lista-usuarios" class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-5 shadow-sm space-y-3">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-2">
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-medium text-slate-50">Listado de usuarios</h2>
          <span class="text-xs text-slate-400">
            Total: <span class="font-semibold text-slate-100"><?= count($usuarios) ?></span>
          </span>
        </div>

        <!-- 🔍 Buscador de usuarios -->
        <div class="flex items-center gap-2">
          <label for="buscar-usuario" class="text-xs text-slate-400">Buscar:</label>
          <input
            id="buscar-usuario"
            type="text"
            placeholder="Nombre o correo..."
            class="w-48 rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
        </div>
      </div>

      <?php if (empty($usuarios)): ?>
        <p class="text-sm text-slate-300">No hay usuarios registrados en el sistema.</p>
      <?php else: ?>
        <!-- Contenedor con scroll interno -->
        <div class="rounded-lg border border-slate-700 bg-slate-900 text-xs max-h-96 overflow-y-auto">
          <table class="min-w-full" id="tabla-usuarios">
            <thead class="bg-slate-800 text-slate-200 sticky top-0 z-10">
              <tr>
                <th class="px-3 py-2 text-left">ID</th>
                <th class="px-3 py-2 text-left">Nombre</th>
                <th class="px-3 py-2 text-left">Correo</th>
                <th class="px-3 py-2 text-left">Rol</th>
                <th class="px-3 py-2 text-left">Creado</th>
                <th class="px-3 py-2 text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usuarios as $u): ?>
                <tr class="border-t border-slate-800 hover:bg-slate-900/70 fila-usuario">
                  <td class="px-3 py-2 font-mono text-slate-200">
                    <?= (int)$u['id_usuario'] ?>
                  </td>
                  <td class="px-3 py-2 text-slate-100 celda-nombre">
                    <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="px-3 py-2 text-slate-100 celda-correo">
                    <?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="px-3 py-2">
                    <span class="inline-flex items-center rounded-full bg-slate-800 px-2 py-0.5 text-[11px] text-slate-100 border border-slate-600">
                      <?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                  <td class="px-3 py-2 text-slate-300">
                    <?= htmlspecialchars($u['creado_en'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <!-- Editar -->
                    <a href="usuarios.php?edit=<?= (int)$u['id_usuario'] ?>"
                       class="inline-flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 px-3 py-1 text-[11px] text-white mr-1">
                      Editar
                    </a>

                    <!-- Eliminar -->
                    <form method="post" class="inline-block"
                          onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                      <button type="submit"
                              class="inline-flex items-center justify-center rounded-md bg-red-600 hover:bg-red-700 px-3 py-1 text-[11px] text-white">
                        Eliminar
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- 🔻 Formulario de creación, todo en azul -->
    <section class="mt-4">
      <div class="min-h-[0] flex items-center justify-center px-0">
        <div class="w-full max-w-xl mx-auto">
          <div class="mb-6 text-center">
            <p class="text-xs uppercase tracking-[0.25em] text-blue-300 mb-1">Mystic Paradise</p>
            <h2 class="text-2xl sm:text-3xl font-semibold text-slate-50">
              Registrar nuevo usuario
            </h2>
            <p class="text-sm text-slate-300 mt-1">
              Crea cuentas para administradores, asesores y recepción.
            </p>
          </div>

          <div class="bg-slate-950/70 backdrop-blur-xl border border-white/10 shadow-2xl shadow-blue-500/40 rounded-3xl p-6 sm:p-7">
            <form method="post" action="usuarios.php" class="space-y-5">
              <input type="hidden" name="accion" value="crear">

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-slate-200 mb-1.5">
                    Nombre completo
                  </label>
                  <input
                    type="text"
                    name="nombre"
                    required
                    class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400/70 focus:border-blue-400/70"
                    placeholder="Ej. Laura Gómez - Recepción"
                  >
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-slate-200 mb-1.5">
                    Correo
                  </label>
                  <input
                    type="email"
                    name="email"
                    required
                    class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400/70 focus:border-blue-400/70"
                    placeholder="correo@tudominio.com"
                  >
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-200 mb-1.5">
                    Contraseña
                  </label>
                  <input
                    type="password"
                    name="password"
                    required
                    minlength="6"
                    class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400/70 focus:border-blue-400/70"
                    placeholder="Mínimo 6 caracteres"
                  >
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-200 mb-1.5">
                    Repetir contraseña
                  </label>
                  <input
                    type="password"
                    name="password2"
                    required
                    minlength="6"
                    class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400/70 focus:border-blue-400/70"
                  >
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-slate-200 mb-1.5">
                    Rol del usuario
                  </label>
                  <select
                    name="rol"
                    class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400/70 focus:border-blue-400/70"
                  >
                    <option value="ASESOR">Asesor</option>
                    <option value="RECEPCION">Recepción</option>
                    <option value="ADMIN">Admin</option>
                    <option value="OTRO">Otro</option>
                  </select>
                  <p class="mt-1 text-[11px] text-slate-400">
                    El rol define lo que podrá ver y hacer dentro de Mystic Admin.
                  </p>
                </div>
              </div>

              <div class="flex items-center justify-between pt-4 border-t border-white/5">
                <a href="#lista-usuarios"
                   class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-300 hover:text-blue-300 transition">
                  ↑ Volver al listado
                </a>

                <button
                  type="submit"
                  class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-lg shadow-blue-500/40 hover:bg-blue-500 active:bg-blue-700 transition"
                >
                  Guardar usuario
                </button>
              </div>
            </form>
          </div>

          <p class="mt-4 text-center text-[11px] text-slate-400">
            Panel interno Mystic Paradise · Solo personal autorizado
          </p>
        </div>
      </div>
    </section>

  </div>

  <!-- 🔍 Buscador JS para la tabla de usuarios -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const inputBusqueda = document.getElementById('buscar-usuario');
      if (!inputBusqueda) return;

      const filas = Array.from(document.querySelectorAll('#tabla-usuarios tbody .fila-usuario'));

      function aplicarFiltro() {
        const q = inputBusqueda.value.toLowerCase().trim();

        filas.forEach(fila => {
          const nombre = (fila.querySelector('.celda-nombre')?.textContent || '').toLowerCase();
          const correo = (fila.querySelector('.celda-correo')?.textContent || '').toLowerCase();

          const coincide = q === '' || nombre.includes(q) || correo.includes(q);

          fila.style.display = coincide ? '' : 'none';
        });
      }

      inputBusqueda.addEventListener('input', aplicarFiltro);
    });
  </script>
</body>
</html>
