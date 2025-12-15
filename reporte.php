<?php
// views/reporte_servicios_totales.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'ADMIN') {
  header("Location: ../login.php");
  exit;
}

require_once __DIR__ . '/../config/conexion.php';

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$rows  = [];
$error = '';

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ===============================
    // Construir condiciones de fecha
    // ===============================
    // Para el subquery de planes (alias r)
    $whereRP = "WHERE r.id_plan IS NOT NULL";
    // Para el subquery de extras (alias r2 + rs)
    $whereSE = "WHERE rs.incluido_en_plan = 0";

    $params = [];

    if ($desde !== '' && $hasta !== '') {
        $whereRP .= " AND r.fecha_ingreso BETWEEN :desde AND :hasta";
        $whereSE .= " AND r2.fecha_ingreso BETWEEN :desde AND :hasta";
        $params[':desde'] = $desde;
        $params[':hasta'] = $hasta;
    } elseif ($desde !== '') {
        $whereRP .= " AND r.fecha_ingreso >= :desde";
        $whereSE .= " AND r2.fecha_ingreso >= :desde";
        $params[':desde'] = $desde;
    } elseif ($hasta !== '') {
        $whereRP .= " AND r.fecha_ingreso <= :hasta";
        $whereSE .= " AND r2.fecha_ingreso <= :hasta";
        $params[':hasta'] = $hasta;
    }

    // ===============================
    // Query principal
    // ===============================
    $sql = "
        SELECT
            s.id_servicio,
            s.nombre AS servicio_nombre,
            s.precio_base,
            COALESCE(sp.cantidad_planes, 0) AS cantidad_planes,
            COALESCE(se.cantidad_extra, 0) AS cantidad_extra
        FROM servicios s
        LEFT JOIN (
            -- Servicios incluidos dentro de planes (multiplicando por #reservas del plan)
            SELECT
                ps.id_servicio,
                SUM(ps.cantidad_incluida * rp.cnt_reservas) AS cantidad_planes
            FROM planes_servicios ps
            INNER JOIN (
                SELECT
                    r.id_plan,
                    COUNT(*) AS cnt_reservas
                FROM reservas r
                $whereRP
                GROUP BY r.id_plan
            ) rp ON rp.id_plan = ps.id_plan
            GROUP BY ps.id_servicio
        ) sp ON sp.id_servicio = s.id_servicio
        LEFT JOIN (
            -- Servicios adicionales registrados en reservas_servicios
            SELECT
                rs.id_servicio,
                SUM(rs.cantidad) AS cantidad_extra
            FROM reservas_servicios rs
            INNER JOIN reservas r2 ON r2.id_reserva = rs.id_reserva
            $whereSE
            GROUP BY rs.id_servicio
        ) se ON se.id_servicio = s.id_servicio
        WHERE (COALESCE(sp.cantidad_planes,0) + COALESCE(se.cantidad_extra,0)) > 0
        ORDER BY s.nombre
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

function fmtCOP($n){
    return 'COP ' . number_format((float)$n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de servicios vendidos | Mystic Paradise</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind (CDN) -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

  <!-- NAVBAR AZUL/NEGRO SIMPLIFICADO -->
  <nav class="w-full sticky top-0 z-40 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 backdrop-blur border-b border-blue-500/40 shadow-[0_8px_24px_rgba(0,0,0,0.35)]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">

        <!-- IZQUIERDA: VOLVER + LOGO + NOMBRE -->
        <div class="flex items-center gap-4">
          <!-- Botón Volver -->
          <button
            type="button"
            onclick="history.back()"
            class="inline-flex items-center gap-1.5 text-[11px] px-3 py-1.5 rounded-full border border-blue-400/60 bg-slate-950/70 text-blue-100 hover:bg-blue-500/20 hover:border-blue-300 transition shadow-sm shadow-black/40"
          >
            <span class="text-base leading-none">←</span>
            <span>Volver</span>
          </button>

          <!-- Logo + nombre -->
          <a href="admin_menu.php" class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-2xl bg-black/10 flex items-center justify-center overflow-hidden border border-white/20 shadow-inner">
              <img src="https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png" alt="Logo Mystic" class="h-8 w-auto">
            </div>
            <div class="leading-tight hidden sm:block">
              <p class="text-[11px] font-semibold tracking-[0.22em] uppercase text-blue-100/95">
                Mystic Paradise
              </p>
              <p class="text-[10px] font-medium tracking-[0.28em] uppercase text-blue-300/90">
                Panel administrador
              </p>
            </div>
          </a>
        </div>

        <!-- DERECHA: USUARIO + SALIR -->
        <div class="flex items-center gap-4">
          <!-- Nombre + inicial -->
          <div class="hidden sm:flex items-center gap-3">
            <div class="text-right">
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
          </div>

          <!-- Botón Salir -->
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

    <header class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-wide">Reportes · Mystic Paradise</p>
        <h1 class="text-2xl font-semibold tracking-tight text-blue-100">
          Servicios vendidos (resumen total)
        </h1>
        <p class="text-sm text-slate-400 mt-1 max-w-2xl">
          Resumen de servicios incluidos en planes y vendidos como adicionales en el rango de fechas seleccionado.
        </p>
      </div>

      <!-- Filtro de fechas -->
      <form method="get" class="bg-slate-900/80 border border-slate-700/80 rounded-xl p-4 flex flex-wrap items-end gap-3 shadow-lg">
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1" for="desde">Desde</label>
          <input
            type="date"
            id="desde"
            name="desde"
            value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>"
            class="border border-slate-700 rounded-lg px-2 py-1.5 text-sm bg-slate-950 text-slate-100 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1" for="hasta">Hasta</label>
          <input
            type="date"
            id="hasta"
            name="hasta"
            value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>"
            class="border border-slate-700 rounded-lg px-2 py-1.5 text-sm bg-slate-950 text-slate-100 focus:outline-none focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
          />
        </div>
        <button
          type="submit"
          class="inline-flex items-center gap-1 px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold shadow-md shadow-blue-900/40 transition"
        >
          Filtrar
        </button>
        <a
          href="reporte_servicios_totales.php"
          class="text-xs text-slate-300 hover:text-slate-100 underline"
        >
          Limpiar filtros
        </a>
      </form>
    </header>

    <?php if ($error): ?>
      <div class="mb-4 rounded-xl border border-red-500/60 bg-red-900/40 px-4 py-3 text-sm text-red-50">
        <strong>Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <?php if ($desde !== '' || $hasta !== ''): ?>
      <p class="mb-4 text-xs text-slate-400">
        Mostrando servicios para reservas con fecha de ingreso
        <?php if ($desde !== ''): ?>
          desde <strong class="text-blue-200"><?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php endif; ?>
        <?php if ($desde !== '' && $hasta !== ''): ?>
          y
        <?php endif; ?>
        <?php if ($hasta !== ''): ?>
          hasta <strong class="text-blue-200"><?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php endif; ?>.
      </p>
    <?php endif; ?>

    <!-- Descripción del reporte -->
    <section class="mb-6 rounded-2xl bg-slate-900/80 border border-slate-700/80 px-4 py-4 shadow-lg text-sm text-slate-200">
      <p class="mb-1">
        Este reporte muestra, por cada <strong class="text-blue-200">servicio</strong>:
      </p>
      <ul class="list-disc list-inside space-y-1 text-xs md:text-sm text-slate-300">
        <li><strong>Cantidad desde planes</strong>: lo que viene incluido en los paquetes vendidos (cantidad_incluida × número de reservas del plan).</li>
        <li><strong>Cantidad adicional</strong>: servicios vendidos aparte como extras (<code class="text-blue-300">reservas_servicios.incluido_en_plan = 0</code>), solo por encima de lo ya cubierto por el plan.</li>
        <li><strong>Cantidad total</strong>: suma de ambas.</li>
        <li><strong>Precio unitario</strong>: tomado del <code class="text-blue-300">precio_base</code> de la tabla <code class="text-blue-300">servicios</code>.</li>
        <li><strong>Total ventas</strong> = cantidad total × precio unitario base.</li>
      </ul>
    </section>

    <!-- TABLA -->
    <section class="bg-slate-900/80 border border-slate-700/80 rounded-2xl shadow-xl overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-100">
          Detalle por servicio
        </h2>
        <span class="text-xs text-slate-400">
          Registros: <?php echo count($rows); ?>
        </span>
      </div>

      <div class="max-h-[520px] overflow-auto">
        <table class="min-w-full text-xs md:text-sm">
          <thead class="bg-slate-950 border-b border-slate-800 text-slate-300">
            <tr>
              <th class="px-3 py-2 text-left font-semibold">Servicio</th>
              <th class="px-3 py-2 text-right font-semibold">Desde planes</th>
              <th class="px-3 py-2 text-right font-semibold">Adicionales</th>
              <th class="px-3 py-2 text-right font-semibold">Cantidad total</th>
              <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Precio unitario (base)</th>
              <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">Total ventas</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                  No hay servicios registrados para el rango seleccionado.
                </td>
              </tr>
            <?php else: ?>
              <?php
              $totalGlobal = 0;
              foreach ($rows as $r):
                  $cantPlanes        = (int)($r['cantidad_planes'] ?? 0);
                  $cantExtraOriginal = (int)($r['cantidad_extra'] ?? 0);

                  // Adicionales reales: solo lo que excede lo cubierto por planes
                  $cantExtra = max($cantExtraOriginal - $cantPlanes, 0);

                  // Cantidad total = planes + adicionales reales
                  $cantTotal  = $cantPlanes + $cantExtra;

                  $precioUnit = (float)$r['precio_base'];
                  $totalVentas= $cantTotal * $precioUnit;
                  $totalGlobal += $totalVentas;
              ?>
                <tr class="border-t border-slate-800 hover:bg-slate-800/60">
                  <td class="px-3 py-2 align-top text-slate-100">
                    <?php echo htmlspecialchars($r['servicio_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                  </td>
                  <td class="px-3 py-2 text-right align-top text-slate-200">
                    <?php echo $cantPlanes; ?>
                  </td>
                  <td class="px-3 py-2 text-right align-top text-slate-200">
                    <?php echo $cantExtra; ?>
                  </td>
                  <td class="px-3 py-2 text-right align-top font-semibold text-blue-200">
                    <?php echo $cantTotal; ?>
                  </td>
                  <td class="px-3 py-2 text-right align-top whitespace-nowrap text-slate-200">
                    <?php echo fmtCOP($precioUnit); ?>
                  </td>
                  <td class="px-3 py-2 text-right align-top whitespace-nowrap font-semibold text-blue-300">
                    <?php echo fmtCOP($totalVentas); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($rows)): ?>
          <tfoot class="bg-slate-950 border-t border-slate-800">
            <tr>
              <td colspan="5" class="px-4 py-3 text-right text-xs font-semibold text-slate-300 uppercase">
                Total ventas de todos los servicios
              </td>
              <td class="px-4 py-3 text-right font-bold text-blue-300 whitespace-nowrap">
                <?php echo fmtCOP($totalGlobal); ?>
              </td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </section>
  </main>

</body>
</html>
