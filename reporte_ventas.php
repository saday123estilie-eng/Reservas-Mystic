<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'ADMIN') {
  header("Location: ../login.php");
  exit;
}

require_once __DIR__ . '/../config/conexion.php';

// Filtros
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$rows    = [];
$resumen = [
  'total'               => 0,
  'reservas'            => 0,
  'promedio'            => 0,
  'total_efectivo'      => 0,
  'total_transferencia' => 0,
  'total_datafono'      => 0,
];
$error = '';

try {
  /** @var PDO $pdo */
  $pdo = conexion::getConexion();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // WHERE dinámico (sin estado)
  $where  = 'WHERE 1=1';
  $params = [];

  if ($desde !== '' && $hasta !== '') {
    $where .= ' AND p.fecha BETWEEN :desde AND :hasta';
    $params[':desde'] = $desde;
    $params[':hasta'] = $hasta;
  } elseif ($desde !== '') {
    $where .= ' AND p.fecha >= :desde';
    $params[':desde'] = $desde;
  } elseif ($hasta !== '') {
    $where .= ' AND p.fecha <= :hasta';
    $params[':hasta'] = $hasta;
  }

  // =======================
  // DETALLE DE VENTAS
  // =======================
  $sqlDetalle = "
    SELECT
      p.fecha        AS fecha,
      r.id_reserva   AS id_reserva,
      c.nombre       AS cliente,
      p.valor        AS monto,
      p.metodo       AS metodo
    FROM pagos p
    INNER JOIN reservas r ON r.id_reserva = p.id_reserva
    INNER JOIN clientes c ON c.id_cliente = r.id_cliente
    $where
    ORDER BY p.fecha DESC
  ";

  $stmt = $pdo->prepare($sqlDetalle);
  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
  }
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // =======================
  // RESUMEN (totales por método)
  // =======================
  $sqlResumen = "
    SELECT
      COALESCE(SUM(p.valor), 0) AS total,
      COUNT(DISTINCT p.id_reserva) AS reservas,
      COALESCE(SUM(CASE WHEN p.metodo = 'Efectivo'      THEN p.valor ELSE 0 END), 0) AS total_efectivo,
      COALESCE(SUM(CASE WHEN p.metodo = 'Transferencia' THEN p.valor ELSE 0 END), 0) AS total_transferencia,
      COALESCE(SUM(CASE WHEN p.metodo = 'Datafono'      THEN p.valor ELSE 0 END), 0) AS total_datafono
    FROM pagos p
    INNER JOIN reservas r ON r.id_reserva = p.id_reserva
    $where
  ";

  $stmt2 = $pdo->prepare($sqlResumen);
  foreach ($params as $k => $v) {
    $stmt2->bindValue($k, $v);
  }
  $stmt2->execute();
  $resumenDb = $stmt2->fetch(PDO::FETCH_ASSOC);

  if (!$resumenDb) {
    $resumenDb = [
      'total'               => 0,
      'reservas'            => 0,
      'total_efectivo'      => 0,
      'total_transferencia' => 0,
      'total_datafono'      => 0,
    ];
  }

  $resumen['total']               = (float)$resumenDb['total'];
  $resumen['reservas']            = (int)$resumenDb['reservas'];
  $resumen['total_efectivo']      = (float)$resumenDb['total_efectivo'];
  $resumen['total_transferencia'] = (float)$resumenDb['total_transferencia'];
  $resumen['total_datafono']      = (float)$resumenDb['total_datafono'];
  $resumen['promedio']            = $resumen['reservas'] > 0
    ? $resumen['total'] / $resumen['reservas']
    : 0;

} catch (PDOException $e) {
  $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de ventas | Mystic Paradise</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

<!-- HEADER SIMPLE CON FLECHA DE REGRESO -->
<!-- HEADER SIMPLE CON BOTÓN ELEGANTE DE REGRESO -->
<nav class="w-full sticky top-0 z-40 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 backdrop-blur border-b border-blue-500/40 shadow-[0_8px_24px_rgba(0,0,0,0.35)]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex h-16 items-center justify-between">

      <!-- IZQUIERDA: BOTÓN VOLVER + TITULO -->
      <div class="flex items-center gap-3">
        <a href="admin_menu.php"
           class="inline-flex items-center gap-2 rounded-full border border-blue-400/40 bg-slate-950/40 px-3 py-1.5 text-[11px] font-medium text-blue-100 hover:bg-blue-500/15 hover:border-blue-300 hover:text-white shadow-sm shadow-blue-900/40 transition">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/20">
            <!-- Ícono flecha más fino -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M11.53 4.47a.75.75 0 010 1.06L8.06 9l3.47 3.47a.75.75 0 11-1.06 1.06l-4-4a.75.75 0 010-1.06l4-4a.75.75 0 011.06 0z" clip-rule="evenodd" />
            </svg>
          </span>
          <span>Volver al panel</span>
        </a>

        <div class="hidden sm:block pl-3 border-l border-blue-400/40">
          <p class="text-[11px] font-semibold tracking-[0.22em] uppercase text-blue-100/95">
            Mystic Paradise
          </p>
          <p class="text-[10px] font-medium tracking-[0.28em] uppercase text-blue-300/90">
            Reporte de ventas
          </p>
        </div>
      </div>

      <!-- DERECHA: USUARIO + SALIR -->
      <div class="flex items-center gap-4">
        <div class="hidden sm:block text-right">
          <p class="text-xs font-semibold leading-tight text-blue-50">
            <?php echo htmlspecialchars($_SESSION['nombre']); ?>
          </p>
          <p class="text-[10px] uppercase tracking-[0.28em] text-blue-200/80">
            Admin
          </p>
        </div>

        <div class="h-9 w-9 rounded-full bg-slate-950/60 border border-white/40 flex items-center justify-center text-xs font-semibold shadow-lg shadow-black/40">
          <?php
            $inicial = mb_substr($_SESSION['nombre'], 0, 1, 'UTF-8');
            echo htmlspecialchars(mb_strtoupper($inicial, 'UTF-8'));
          ?>
        </div>

        <a href="logout.php"
           class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-3 py-1.5 rounded-full bg-red-500 hover:bg-red-600 text-white shadow-md shadow-red-900/40 transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M3 4.5A1.5 1.5 0 014.5 3h5a1.5 1.5 0 110 3h-5A1.5 1.5 0 013 4.5zM11 10a.75.75 0 01.75-.75h4.19l-1.72-1.72a.75.75 0 111.06-1.06l3.25 3.25a.75.75 0 010 1.06l-3.25 3.25a.75.75 0 11-1.06-1.06l1.72-1.72H11.75A.75.75 0 0111 10z" clip-rule="evenodd" />
            <path d="M4.5 9A1.5 1.5 0 003 10.5v5A1.5 1.5 0 004.5 17h5A1.5 1.5 0 0011 15.5V13a1 1 0 10-2 0v2H5v-4.5A1.5 1.5 0 004.5 9H6a1 1 0 100-2H4.5z" />
          </svg>
          <span>Salir</span>
        </a>
      </div>
    </div>
  </div>
</nav>


  <!-- CONTENIDO -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <h1 class="text-2xl font-semibold text-blue-100 mb-4">
      Reporte de ventas
    </h1>

    <!-- FILTROS -->
    <form method="get" class="mb-6 grid gap-4 md:grid-cols-4 items-end bg-slate-900/70 border border-slate-700/80 rounded-2xl p-4">
      <div>
        <label class="block text-xs text-slate-300 mb-1">Desde</label>
        <input type="date" name="desde"
               value="<?php echo htmlspecialchars($desde); ?>"
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
      </div>
      <div>
        <label class="block text-xs text-slate-300 mb-1">Hasta</label>
        <input type="date" name="hasta"
               value="<?php echo htmlspecialchars($hasta); ?>"
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
      </div>
      <div class="md:col-span-2 flex gap-3">
        <button type="submit"
                class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-sm font-medium text-white shadow-md shadow-blue-900/40 transition">
          Aplicar filtros
        </button>
        <a href="reporte_ventas.php"
           class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm font-medium text-slate-100 border border-slate-600/80 transition">
          Limpiar
        </a>
      </div>
    </form>

    <!-- RESUMEN GENERAL -->
    <section class="grid gap-4 md:grid-cols-3 mb-6">
      <div class="rounded-2xl bg-slate-900/80 border border-slate-700/80 px-4 py-3 shadow-lg">
        <p class="text-xs text-slate-400 mb-1">Total vendido</p>
        <p class="text-xl font-semibold text-blue-300">
          $<?php echo number_format($resumen['total'], 0, ',', '.'); ?>
        </p>
      </div>
      <div class="rounded-2xl bg-slate-900/80 border border-slate-700/80 px-4 py-3 shadow-lg">
        <p class="text-xs text-slate-400 mb-1">Número de reservas</p>
        <p class="text-xl font-semibold text-blue-300">
          <?php echo (int)$resumen['reservas']; ?>
        </p>
      </div>
      <div class="rounded-2xl bg-slate-900/80 border border-slate-700/80 px-4 py-3 shadow-lg">
        <p class="text-xs text-slate-400 mb-1">Ticket promedio</p>
        <p class="text-xl font-semibold text-blue-300">
          $<?php echo number_format($resumen['promedio'], 0, ',', '.'); ?>
        </p>
      </div>
    </section>

    <!-- RESUMEN POR MÉTODO -->
    <section class="grid gap-4 md:grid-cols-3 mb-6">
      <div class="rounded-2xl bg-slate-900/80 border border-blue-500/40 px-4 py-3 shadow-lg">
        <p class="text-xs text-blue-300 mb-1">Total en efectivo</p>
        <p class="text-lg font-semibold text-blue-200">
          $<?php echo number_format($resumen['total_efectivo'], 0, ',', '.'); ?>
        </p>
      </div>
      <div class="rounded-2xl bg-slate-900/80 border border-blue-500/40 px-4 py-3 shadow-lg">
        <p class="text-xs text-blue-300 mb-1">Total en transferencia</p>
        <p class="text-lg font-semibold text-blue-200">
          $<?php echo number_format($resumen['total_transferencia'], 0, ',', '.'); ?>
        </p>
      </div>
      <div class="rounded-2xl bg-slate-900/80 border border-blue-500/40 px-4 py-3 shadow-lg">
        <p class="text-xs text-blue-300 mb-1">Total en datáfono</p>
        <p class="text-lg font-semibold text-blue-200">
          $<?php echo number_format($resumen['total_datafono'], 0, ',', '.'); ?>
        </p>
      </div>
    </section>

    <!-- ERRORES -->
    <?php if ($error): ?>
      <div class="mb-4 rounded-xl border border-red-500/60 bg-red-900/40 px-3 py-2 text-xs text-red-50">
        Error: <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <!-- TABLA -->
    <div class="rounded-2xl bg-slate-900/80 border border-slate-700/80 overflow-hidden shadow-xl">
      <div class="max-h-[500px] overflow-auto">
        <table class="min-w-full text-xs">
          <thead class="bg-slate-900 border-b border-slate-700/80 text-slate-300">
            <tr>
              <th class="px-3 py-2 text-left font-semibold">Fecha pago</th>
              <th class="px-3 py-2 text-left font-semibold">Reserva</th>
              <th class="px-3 py-2 text-left font-semibold">Cliente</th>
              <th class="px-3 py-2 text-right font-semibold">Monto</th>
              <th class="px-3 py-2 text-left font-semibold">Método</th>
            </tr>
          </thead>
          <tbody>
          <?php if (count($rows) === 0): ?>
            <tr>
              <td colspan="5" class="px-3 py-4 text-center text-slate-400">
                No hay ventas para el rango seleccionado.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr class="border-t border-slate-800/80 hover:bg-slate-800/60">
                <td class="px-3 py-2">
                  <?php echo htmlspecialchars($row['fecha']); ?>
                </td>
                <td class="px-3 py-2">
                  #<?php echo htmlspecialchars($row['id_reserva']); ?>
                </td>
                <td class="px-3 py-2">
                  <?php echo htmlspecialchars($row['cliente']); ?>
                </td>
                <td class="px-3 py-2 text-right">
                  $<?php echo number_format($row['monto'], 0, ',', '.'); ?>
                </td>
                <td class="px-3 py-2">
                  <?php echo htmlspecialchars($row['metodo']); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

</body>
</html>
