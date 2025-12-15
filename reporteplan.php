<?php
// views/reporte_planes_pasadias.php

require_once __DIR__ . '/../config/conexion.php';

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$rows   = [];
$error  = '';

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $where  = '';
    $params = [];

    if ($desde !== '' && $hasta !== '') {
        $where = "WHERE r.fecha_ingreso BETWEEN :desde AND :hasta";
        $params[':desde'] = $desde;
        $params[':hasta'] = $hasta;
    } elseif ($desde !== '') {
        $where = "WHERE r.fecha_ingreso >= :desde";
        $params[':desde'] = $desde;
    } elseif ($hasta !== '') {
        $where = "WHERE r.fecha_ingreso <= :hasta";
        $params[':hasta'] = $hasta;
    }

    // IMPORTANTE:
    // - Cambia 'Pasadía' si en tu tabla servicios la categoría se llama diferente.
    // - Asumimos tabla reservas = `reservas` y detalle = `reserva_servicios`.
    $sql = "
        SELECT 
            p.id_plan,
            p.nombre AS plan_nombre,
            COUNT(DISTINCT r.id_reserva) AS total_reservas,
            COALESCE(
                SUM(
                    CASE 
                        WHEN s.categoria = 'Pasadía' THEN rs.cantidad 
                        ELSE 0 
                    END
                ), 0
            ) AS total_pasadias
        FROM planes p
        LEFT JOIN reservas r 
            ON r.id_plan = p.id_plan
        LEFT JOIN reservas_servicios rs
            ON rs.id_reserva = r.id_reserva
        LEFT JOIN servicios s
            ON s.id_servicio = rs.id_servicio
        $where
        GROUP BY p.id_plan, p.nombre
        ORDER BY p.nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

function fmt_int($n) {
    return number_format((int)$n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de planes y pasadías | Mystic Paradise</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50:'#eff6ff',
              100:'#dbeafe',
              200:'#bfdbfe',
              300:'#93c5fd',
              400:'#60a5fa',
              500:'#3b82f6',   // azul principal
              600:'#2563eb',
              700:'#1d4ed8',
              800:'#1e40af',
              900:'#1e3a8a',
              950:'#0b1220'
            }
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen bg-[#020617] text-slate-100">

  <!-- FONDO GRADIENTE / GLOW -->
  <div class="fixed inset-0 -z-10 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950"></div>
  <div class="pointer-events-none fixed inset-0 -z-10 opacity-40">
    <div class="absolute -top-24 -left-24 w-72 h-72 rounded-full bg-brand-600 blur-3xl"></div>
    <div class="absolute -bottom-32 right-0 w-80 h-80 rounded-full bg-indigo-600 blur-3xl opacity-70"></div>
  </div>

  <div class="relative max-w-6xl mx-auto px-4 py-8">
    <!-- BOTÓN VOLVER -->
    <div class="mb-4">
      <button type="button"
              onclick="history.back()"
              class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-slate-700 bg-slate-950/70 text-xs text-slate-200 hover:bg-slate-900 hover:border-brand-500/60 transition shadow-sm shadow-black/40">
        <span class="text-base leading-none">←</span>
        <span>Volver</span>
      </button>
    </div>

    <!-- HEADER -->
    <header class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-[0.2em] text-brand-300 mb-1">
          Mystic Paradise · Dashboard
        </p>
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-50">
          Reporte de planes y pasadías
        </h1>
        <p class="text-sm text-slate-400 mt-1 max-w-xl">
          Resumen de cuántos planes se han vendido y cuántas pasadías se han registrado por plan
          en el rango de fechas seleccionado.
        </p>
      </div>

      <!-- FILTROS -->
      <form method="get" class="bg-slate-900/70 border border-slate-700/70 rounded-xl p-4 flex flex-wrap items-end gap-3 shadow-lg shadow-black/40 backdrop-blur">
        <div>
          <label class="block text-xs font-medium text-slate-400 mb-1" for="desde">Desde</label>
          <input type="date" id="desde" name="desde"
                 value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>"
                 class="border border-slate-700 rounded-lg px-2 py-1.5 text-sm bg-slate-950/70 text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500/60 focus:border-brand-400" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-400 mb-1" for="hasta">Hasta</label>
          <input type="date" id="hasta" name="hasta"
                 value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>"
                 class="border border-slate-700 rounded-lg px-2 py-1.5 text-sm bg-slate-950/70 text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500/60 focus:border-brand-400" />
        </div>
        <button type="submit"
                class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold shadow-md shadow-brand-500/30 transition">
          Filtrar
        </button>
        <a href="reporteplan.php"
           class="text-xs text-slate-400 hover:text-slate-200 underline">
          Limpiar filtros
        </a>
      </form>
    </header>

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg border border-red-500/40 bg-red-900/40 px-4 py-3 text-sm text-red-100 shadow shadow-red-900/40">
        <strong>Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <!-- CARD PRINCIPAL -->
    <section class="bg-slate-950/80 border border-slate-800 rounded-2xl shadow-2xl shadow-black/60 overflow-hidden backdrop-blur">
      <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-100 flex items-center gap-2">
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-500/20 text-brand-300 text-xs">
            MP
          </span>
          Detalle por plan
        </h2>
        <span class="text-xs text-slate-400">
          Registros: <?php echo count($rows); ?>
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-900 border-b border-slate-800">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">ID Plan</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Plan</th>
              <th class="px-4 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Planes vendidos</th>
              <th class="px-4 py-2 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Pasadías</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $totalPlanes   = 0;
            $totalPasadias = 0;

            if (!empty($rows)):
              foreach ($rows as $r):
                  $totalPlanes   += (int)$r['total_reservas'];
                  $totalPasadias += (int)$r['total_pasadias'];
            ?>
              <tr class="border-b border-slate-800/80 hover:bg-slate-900/70">
                <td class="px-4 py-2 text-xs text-slate-500">
                  #<?php echo (int)$r['id_plan']; ?>
                </td>
                <td class="px-4 py-2 font-medium text-slate-100">
                  <?php echo htmlspecialchars($r['plan_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td class="px-4 py-2 text-right font-semibold text-slate-100">
                  <?php echo fmt_int($r['total_reservas']); ?>
                </td>
                <td class="px-4 py-2 text-right font-semibold text-brand-300">
                  <?php echo fmt_int($r['total_pasadias']); ?>
                </td>
              </tr>
            <?php
              endforeach;
            else:
            ?>
              <tr>
                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">
                  No se encontraron datos para el rango seleccionado.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
          <tfoot class="bg-slate-900/80 border-t border-slate-800">
            <tr>
              <td colspan="2" class="px-4 py-2 text-right text-xs font-semibold text-slate-400 uppercase">
                Totales
              </td>
              <td class="px-4 py-2 text-right font-bold text-slate-100">
                <?php echo fmt_int($totalPlanes); ?>
              </td>
              <td class="px-4 py-2 text-right font-bold text-brand-300">
                <?php echo fmt_int($totalPasadias); ?>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </div>

</body>
</html>
