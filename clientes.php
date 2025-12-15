<?php
// admin/clientes.php
// Listado de clientes + exportar a CSV

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo ADMIN por ahora (ajusta si quieres incluir otros roles)
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'ADMIN') {
    header("Location: ../login.php");
    exit;
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Admin';
$rolUsuario    = $_SESSION['rol']    ?? 'ADMIN';

require_once __DIR__ . '/../config/conexion.php';

$mensajeErr = '';
$clientes   = [];

// ======================
//  MENSAJE FLASH EXPORT
// ======================
if (isset($_SESSION['msg_err_clientes_export'])) {
    $mensajeErr = $_SESSION['msg_err_clientes_export'];
    unset($_SESSION['msg_err_clientes_export']);
}

// ======================
//  CONEXIÓN BD
// ======================
try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ======================
//  BÚSQUEDA / FILTRO
// ======================
$q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';
$desde  = isset($_GET['desde'])  ? trim($_GET['desde'])  : '';
$hasta  = isset($_GET['hasta'])  ? trim($_GET['hasta'])  : '';

$sqlBase = "
    SELECT 
        id_cliente,
        nombre,
        cedula,
        whatsapp,
        correo,
        ciudad,
        cumple,
        edad,
        estrato,
        profesion,
        medio,
        creado_en
    FROM clientes
";

$whereParts = [];
$params     = [];

// Filtro de texto
if ($q !== '') {
    $whereParts[] = "
        (
            nombre   LIKE :q
         OR cedula   LIKE :q
         OR whatsapp LIKE :q
         OR correo   LIKE :q
         OR ciudad   LIKE :q
        )
    ";
    $params[':q'] = '%' . $q . '%';
}

// Filtro por fecha DESDE (inclusive)
if ($desde !== '') {
    $whereParts[]     = "DATE(creado_en) >= :desde";
    $params[':desde'] = $desde;
}

// Filtro por fecha HASTA (inclusive)
if ($hasta !== '') {
    $whereParts[]     = "DATE(creado_en) <= :hasta";
    $params[':hasta'] = $hasta;
}

$where = '';
if (count($whereParts) > 0) {
    $where = ' WHERE ' . implode(' AND ', $whereParts);
}

$sqlOrder = " ORDER BY creado_en DESC";
$sqlFull  = $sqlBase . $where . $sqlOrder;

// ======================
//  EXPORTAR A CSV
// ======================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $stmt = $pdo->prepare($sqlFull);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ❌ Si no hay filas, no exportamos y volvemos con mensaje
        if (count($rows) === 0) {
            $_SESSION['msg_err_clientes_export'] = "No hay clientes para exportar con los filtros aplicados.";

            // Reconstruir URL sin el parámetro export=csv
            $redirectParams = [];
            if ($q !== '')     $redirectParams['q']     = $q;
            if ($desde !== '') $redirectParams['desde'] = $desde;
            if ($hasta !== '') $redirectParams['hasta'] = $hasta;

            $queryStr = http_build_query($redirectParams);
            header('Location: clientes.php' . ($queryStr ? ('?' . $queryStr) : ''));
            exit;
        }

        // Encabezados HTTP
        $filename = "clientes_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // BOM UTF-8 para que Excel abra bien los acentos
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        // Encabezado CSV
        fputcsv($out, [
            'ID',
            'Nombre',
            'Cédula',
            'WhatsApp',
            'Correo',
            'Ciudad',
            'Cumpleaños',
            'Edad',
            'Estrato',
            'Profesión',
            'Medio',
            'Creado en',
        ], ';');

        // Filas
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id_cliente'],
                $r['nombre'],
                $r['cedula'],
                $r['whatsapp'],
                $r['correo'],
                $r['ciudad'],
                $r['cumple'],
                $r['edad'],
                $r['estrato'],
                $r['profesion'],
                $r['medio'],
                $r['creado_en'],
            ], ';');
        }

        fclose($out);
        exit;

    } catch (PDOException $e) {
        $mensajeErr = "Error al exportar CSV: " . $e->getMessage();
    }
}

// ======================
//  CARGAR LISTADO
// ======================
try {
    $stmt = $pdo->prepare($sqlFull);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensajeErr = "Error al cargar clientes: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de clientes | Mystic Paradise</title>
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
      border-color:#2563eb;
      color:#bfdbfe;
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
      background:linear-gradient(135deg,#38bdf8,#6366f1);
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

  <!-- 🔹 Encabezado Mystic -->
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand">
        <!-- Volver -->
        <a href="javascript:window.history.back();" class="btn-top btn-top-back" title="Volver a la pantalla anterior">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M10 19l-7-7 7-7M3 12h18"/>
          </svg>
          Volver
        </a>

        <!-- Menú admin -->
        <a href="admin_menu.php" class="btn-top" title="Ir al panel de administrador">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/>
          </svg>
          Menú admin
        </a>

        <div class="brand-logo"></div>
        <div class="brand-text">
          <span class="brand-title">MYSTIC PARADISE</span>
          <span class="brand-sub">Listado de clientes</span>
        </div>
      </div>

      <div class="top-actions">
        <!-- Atajo a reservas -->
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
        <h1 class="text-2xl font-semibold text-blue-400">Clientes registrados</h1>
        <p class="text-sm text-slate-300 mt-1">
          Consulta el histórico de clientes que han hecho reservas en Mystic Paradise.
        </p>
      </div>
      <div class="flex flex-col items-end text-xs text-slate-400">
        <span>Usuario: <span class="text-slate-100 font-semibold"><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></span></span>
        <span>Rol: <span class="text-slate-100 font-semibold"><?= htmlspecialchars($rolUsuario, ENT_QUOTES, 'UTF-8') ?></span></span>
      </div>
    </div>

    <!-- Mensajes -->
    <?php if ($mensajeErr): ?>
      <div class="rounded-lg bg-slate-900 border border-red-500 px-4 py-2 text-sm text-red-200">
        <?= htmlspecialchars($mensajeErr, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Bloque búsqueda + export -->
    <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-5 shadow-sm space-y-3">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <h2 class="text-lg font-medium text-slate-50">Buscar clientes</h2>
          <p class="text-xs text-slate-400">
            Filtra por nombre, cédula, WhatsApp, correo o ciudad. También puedes acotar por rango de fechas.
          </p>
        </div>

        <form method="get" class="flex flex-col sm:flex-row gap-3 sm:items-end flex-wrap">
          <div>
            <label class="block text-xs text-slate-400 mb-1">Texto de búsqueda</label>
            <input
              type="text"
              name="q"
              value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
              placeholder="Ej: 123, Laura, @gmail.com, Cali..."
              class="w-full sm:w-64 rounded-md bg-slate-800 border border-slate-600 px-3 py-1.5 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>

          <div>
            <label class="block text-xs text-slate-400 mb-1">Desde</label>
            <input
              type="date"
              name="desde"
              value="<?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?>"
              class="w-full sm:w-40 rounded-md bg-slate-800 border border-slate-600 px-3 py-1.5 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>

          <div>
            <label class="block text-xs text-slate-400 mb-1">Hasta</label>
            <input
              type="date"
              name="hasta"
              value="<?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?>"
              class="w-full sm:w-40 rounded-md bg-slate-800 border border-slate-600 px-3 py-1.5 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>

          <div class="flex gap-2">
            <button
              type="submit"
              class="mt-4 sm:mt-0 inline-flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-medium text-white"
            >
              Buscar
            </button>

            <!-- 🔄 Limpiar filtros -->
            <a
              href="clientes.php"
              class="mt-4 sm:mt-0 inline-flex items-center justify-center rounded-md bg-slate-800 border border-slate-600 hover:bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200"
              title="Quitar filtros y ver todo el listado"
            >
              Limpiar filtros
            </a>

            <?php
              // Construir URL de export respetando filtros
              $exportParams = ['export=csv'];
              if ($q !== '')     $exportParams[] = 'q=' . urlencode($q);
              if ($desde !== '') $exportParams[] = 'desde=' . urlencode($desde);
              if ($hasta !== '') $exportParams[] = 'hasta=' . urlencode($hasta);
              $exportUrl = 'clientes.php?' . implode('&', $exportParams);
            ?>
            <a
              href="<?= $exportUrl ?>"
              class="mt-4 sm:mt-0 inline-flex items-center justify-center rounded-md bg-slate-800 border border-blue-500 hover:bg-slate-900 px-4 py-2 text-sm font-medium text-blue-200"
              title="Exportar listado filtrado a CSV"
            >
              Exportar CSV
            </a>
          </div>
        </form>
      </div>

      <!-- Resumen -->
      <p class="text-xs text-slate-400 mt-1">
        Clientes encontrados: <span class="font-semibold text-slate-100"><?= count($clientes) ?></span>
        <?php if ($q !== ''): ?>
          · Filtro: "<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
        <?php endif; ?>
        <?php if ($desde !== '' || $hasta !== ''): ?>
          · Rango de fechas:
          <?php if ($desde !== ''): ?>
            desde <span class="text-slate-100"><?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <?php if ($hasta !== ''): ?>
            hasta <span class="text-slate-100"><?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </p>

      <!-- Listado con scroll -->
      <?php if (empty($clientes)): ?>
        <p class="mt-3 text-sm text-slate-300">No se encontraron clientes con ese filtro.</p>
      <?php else: ?>
        <div class="mt-3 rounded-lg border border-slate-700 bg-slate-900">
          <div class="max-h-[460px] overflow-y-auto overflow-x-auto text-xs">
            <table class="min-w-full">
              <thead class="bg-slate-800 text-slate-200 sticky top-0 z-10">
                <tr>
                  <th class="px-3 py-2 text-left">ID</th>
                  <th class="px-3 py-2 text-left">Nombre</th>
                  <th class="px-3 py-2 text-left">Cédula</th>
                  <th class="px-3 py-2 text-left">WhatsApp</th>
                  <th class="px-3 py-2 text-left">Correo</th>
                  <th class="px-3 py-2 text-left">Ciudad</th>
                  <th class="px-3 py-2 text-left">Medio</th>
                  <th class="px-3 py-2 text-left">Cumpleaños</th>
                  <th class="px-3 py-2 text-right">Edad</th>
                  <th class="px-3 py-2 text-left">Estrato</th>
                  <th class="px-3 py-2 text-left">Profesión</th>
                  <th class="px-3 py-2 text-left">Creado en</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($clientes as $c): ?>
                  <tr class="border-t border-slate-800 hover:bg-slate-900/70">
                    <td class="px-3 py-2 font-mono text-slate-200">
                      <?= (int)$c['id_cliente'] ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['cedula'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['whatsapp'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['correo'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['ciudad'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['medio'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['cumple'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-right text-slate-100">
                      <?= htmlspecialchars($c['edad'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['estrato'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-100">
                      <?= htmlspecialchars($c['profesion'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="px-3 py-2 text-slate-300">
                      <?= htmlspecialchars($c['creado_en'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </section>

  </div>
</body>
</html>
