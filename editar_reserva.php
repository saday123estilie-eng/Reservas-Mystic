<?php
// editar_reserva.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Error de conexión: " . htmlspecialchars($e->getMessage()));
}

$idReserva = isset($_GET['id_reserva']) ? (int)$_GET['id_reserva'] : 0;
$q         = isset($_GET['q']) ? trim($_GET['q']) : '';

$reservas          = [];
$reserva           = null;
$ultimaAuditoria   = null;
$planes            = [];
$serviciosReserva  = [];
$pagos             = [];
$allServicios      = [];

// ============ 0) CARGAR LISTA DE PLANES (para el select) ============
try {
    $sqlPlanes = "
        SELECT 
            id_plan,
            nombre
        FROM planes
        ORDER BY nombre
    ";
    $stmtPlanes = $pdo->query($sqlPlanes);
    while ($row = $stmtPlanes->fetch(PDO::FETCH_ASSOC)) {
        $planes[] = $row;
    }
} catch (Exception $e) {
    $planes = [];
}

// ============ 0b) CARGAR LISTA DE SERVICIOS DISPONIBLES ============
try {
    $sqlAllServicios = "
        SELECT 
            id_servicio,
            nombre,
            precio_base
        FROM servicios
        ORDER BY nombre
    ";
    $stmtServ = $pdo->query($sqlAllServicios);
    while ($row = $stmtServ->fetch(PDO::FETCH_ASSOC)) {
        $allServicios[] = $row;
    }
} catch (Exception $e) {
    $allServicios = [];
}

// ============ 1) BÚSQUEDA POR Q ============
if ($idReserva === 0 && $q !== '') {
    $sql = "
        SELECT 
            r.id_reserva,
            r.codigo,
            r.fecha_ingreso,
            r.noches,
            r.valor_total,
            r.saldo,
            p.nombre AS plan_nombre,
            c.nombre AS nombre,
            c.cedula AS cedula
        FROM reservas r
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN planes   p ON r.id_plan    = p.id_plan
        WHERE 
            c.cedula LIKE :q1
            OR c.nombre LIKE :q2
            OR r.codigo LIKE :q3
        ORDER BY r.fecha_ingreso DESC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':q1' => "%{$q}%",
        ':q2' => "%{$q}%",
        ':q3' => "%{$q}%",
    ]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============ 2) CARGAR UNA RESERVA PARA EDITAR ============
$totalPagos      = 0;
$puedeNuevoPago  = true;
$totalPagadoCalc = 0;

if ($idReserva > 0) {
    // Reserva + cliente + plan
    $sql = "
        SELECT
            r.*,
            c.nombre  AS nombre_cliente,
            c.cedula  AS cedula_cliente,
            c.whatsapp,
            p.nombre  AS plan_nombre
        FROM reservas r
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN planes   p ON r.id_plan    = p.id_plan
        WHERE r.id_reserva = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idReserva]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        die("Reserva no encontrada.");
    }

    // Cálculo de total pagado actual
    $valorTotalInicial = isset($reserva['valor_total']) ? (int)$reserva['valor_total'] : 0;
    $saldoInicial      = isset($reserva['saldo']) ? (int)$reserva['saldo'] : 0;
    $totalPagadoCalc   = max(0, $valorTotalInicial - $saldoInicial);

    // Última auditoría
    $sqlAud = "
        SELECT usuario_rol, fecha 
        FROM reserva_auditoria 
        WHERE id_reserva = :id
        ORDER BY fecha DESC
        LIMIT 1
    ";
    $stmtAud = $pdo->prepare($sqlAud);
    $stmtAud->execute([':id' => $idReserva]);
    $ultimaAuditoria = $stmtAud->fetch(PDO::FETCH_ASSOC);

    // Servicios de la reserva
    $sqlServR = "
        SELECT 
            rs.id_servicio,
            rs.nombre_servicio,
            rs.cantidad,
            rs.precio_unitario,
            (rs.cantidad * rs.precio_unitario) AS subtotal
        FROM reserva_servicios rs
        WHERE rs.id_reserva = :id
    ";
    try {
        $stmtSR = $pdo->prepare($sqlServR);
        $stmtSR->execute([':id' => $idReserva]);
        $serviciosReserva = $stmtSR->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $serviciosReserva = [];
    }

    // Pagos de la reserva
    $sqlPagos = "
        SELECT 
            pr.id_pago,
            pr.fecha_pago,
            pr.metodo,
            pr.monto
        FROM pagos_reserva pr
        WHERE pr.id_reserva = :id
        ORDER BY pr.fecha_pago ASC, pr.id_pago ASC
    ";
    try {
        $stmtPag = $pdo->prepare($sqlPagos);
        $stmtPag->execute([':id' => $idReserva]);
        $pagos = $stmtPag->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $pagos = [];
    }

    // límite de abonos: máximo 3 pagos (incluido el inicial)
    $maxAbonos       = 3;
    $totalPagos      = count($pagos);
    $puedeNuevoPago  = $totalPagos < $maxAbonos;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar reserva | Mystic Paradise</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">

  <div class="max-w-6xl mx-auto py-8 px-4">

    <!-- BOTONES SUPERIORES -->
    <div class="mb-6 flex flex-wrap gap-3 items-center justify-between">
      <div class="flex gap-2">
        <a
           href="Ver_reservas.php"
           class="inline-flex items-center gap-2 rounded-full border border-blue-400 bg-slate-800/70 px-3 py-1.5 text-xs font-medium text-blue-100 hover:bg-slate-700 transition">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M11.53 4.47a.75.75 0 010 1.06L8.06 9l3.47 3.47a.75.75 0 11-1.06 1.06l-4-4a.75.75 0 010-1.06l4-4a.75.75 0 011.06 0z" clip-rule="evenodd" />
            </svg>
          </span>
          <span>Volver a reservas</span>
        </a>

        <a
           href="../admin/menu.php"
           class="inline-flex items-center gap-2 rounded-full border border-emerald-400 bg-slate-800/70 px-3 py-1.5 text-xs font-medium text-emerald-100 hover:bg-slate-700 transition">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10 2a1 1 0 01.832.445l6 8.5A1 1 0 0116 12H4a1 1 0 01-.832-1.555l6-8.5A1 1 0 0110 2z" />
              <path d="M4 14a1 1 0 000 2h12a1 1 0 100-2H4z" />
            </svg>
          </span>
          <span>Menú admin</span>
        </a>
      </div>

      <h1 class="text-lg font-bold text-blue-100">Editar reserva</h1>
    </div>

    <!-- ============ BLOQUE DE BÚSQUEDA ============ -->
    <?php if ($idReserva === 0): ?>
      <section class="bg-slate-800/80 border border-blue-500/60 rounded-2xl p-5 mb-6">
        <h2 class="text-blue-200 font-semibold mb-3 text-sm uppercase tracking-wide">Buscar reserva</h2>

        <form method="get" class="flex flex-col sm:flex-row gap-3">
          <input
            type="text"
            name="q"
            value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="Cédula, nombre o código de reserva"
            class="flex-1 rounded-xl bg-slate-900/60 border border-slate-600 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
          <button
            type="submit"
            class="rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow">
            Buscar
          </button>
        </form>

        <?php if ($q !== ''): ?>
          <p class="mt-3 text-xs text-slate-400">
            Resultados para: <span class="font-mono text-blue-200"><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></span>
          </p>
        <?php endif; ?>
      </section>

      <?php if ($q !== '' && empty($reservas)): ?>
        <p class="text-sm text-red-300">No se encontraron reservas que coincidan.</p>
      <?php endif; ?>

      <?php if (!empty($reservas)): ?>
        <section class="bg-slate-800/70 border border-slate-700 rounded-2xl p-5">
          <h3 class="text-sm font-semibold text-slate-200 mb-3">Resultados</h3>
          <div class="overflow-x-auto text-xs">
            <table class="min-w-full border border-slate-700 rounded-xl overflow-hidden">
              <thead class="bg-slate-900/80 text-slate-300">
                <tr>
                  <th class="px-3 py-2 text-left">Código</th>
                  <th class="px-3 py-2 text-left">Cliente</th>
                  <th class="px-3 py-2 text-left">Cédula</th>
                  <th class="px-3 py-2 text-left">Ingreso</th>
                  <th class="px-3 py-2 text-right">Valor</th>
                  <th class="px-3 py-2 text-right">Saldo</th>
                  <th class="px-3 py-2 text-center">Acción</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($reservas as $r): ?>
                <tr class="border-t border-slate-800 hover:bg-slate-900/60">
                  <td class="px-3 py-2 font-mono text-blue-200"><?php echo htmlspecialchars($r['codigo']); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($r['nombre']); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($r['cedula']); ?></td>
                  <td class="px-3 py-2 text-slate-300"><?php echo htmlspecialchars($r['fecha_ingreso']); ?></td>
                  <td class="px-3 py-2 text-right text-slate-200">
                    <?php echo number_format($r['valor_total'], 0, ',', '.'); ?>
                  </td>
                  <td class="px-3 py-2 text-right text-amber-300">
                    <?php echo number_format($r['saldo'], 0, ',', '.'); ?>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <a href="editar_reserva.php?id_reserva=<?php echo (int)$r['id_reserva']; ?>"
                       class="inline-flex items-center justify-center rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-1 text-xs text-white font-semibold">
                      Editar
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>

    <!-- ============ FORMULARIO DE EDICIÓN COMPLETO ============ -->
    <?php if ($idReserva > 0 && $reserva): ?>

      <?php if (!empty($ultimaAuditoria)): ?>
        <div class="mb-4 rounded-xl bg-amber-500/15 border border-amber-400/70 text-amber-100 px-4 py-3 text-sm">
          ⚠️ Esta reserva fue modificada por
          <span class="font-semibold">
            <?php echo htmlspecialchars($ultimaAuditoria['usuario_rol']); ?>
          </span>
          el
          <span class="font-mono">
            <?php echo htmlspecialchars($ultimaAuditoria['fecha']); ?>
          </span>
        </div>
      <?php endif; ?>

      <form action="../Controlador/procesar_edicion_reserva.php" method="post"
            class="bg-slate-800/70 border border-slate-700 rounded-2xl p-6 space-y-8">

        <input type="hidden" name="id_reserva" value="<?php echo (int)$reserva['id_reserva']; ?>">
        <input type="hidden" name="total_pagos_actuales" value="<?php echo (int)$totalPagos; ?>">
        <!-- total pagado calculado -->
        <input type="hidden" id="total_pagado_inicial" name="total_pagado_inicial" value="<?php echo (int)$totalPagadoCalc; ?>">

        <!-- DATOS DEL CLIENTE -->
        <h2 class="text-sm font-semibold text-slate-200 mb-2">Datos del cliente</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div>
            <label class="block text-xs mb-1 text-slate-400">Nombre (no editable)</label>
            <input name="nombre_cliente"
                   value="<?php echo htmlspecialchars($reserva['nombre_cliente']); ?>"
                   readonly
                   class="w-full rounded-lg bg-slate-900/80 border border-slate-700 px-3 py-2 text-sm text-slate-400 cursor-not-allowed">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-400">Cédula (no editable)</label>
            <input name="cedula_cliente"
                   value="<?php echo htmlspecialchars($reserva['cedula_cliente']); ?>"
                   readonly
                   class="w-full rounded-lg bg-slate-900/80 border border-slate-700 px-3 py-2 text-sm text-slate-400 cursor-not-allowed">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-400">WhatsApp</label>
            <input name="whatsapp"
                   value="<?php echo htmlspecialchars($reserva['whatsapp']); ?>"
                   class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
          </div>
        </div>

        <!-- DATOS DE LA RESERVA -->
        <h2 class="text-sm font-semibold text-slate-200 mt-4">Datos de la reserva</h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
          <div>
            <label class="block text-xs mb-1 text-slate-400">Código (no editable)</label>
            <input name="codigo"
                   value="<?php echo htmlspecialchars($reserva['codigo']); ?>"
                   readonly
                   class="w-full rounded-lg bg-slate-900/80 border border-slate-700 px-3 py-2 text-sm text-slate-400 cursor-not-allowed">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-400">Fecha ingreso</label>
            <input type="date" name="fecha_ingreso"
                   value="<?php echo htmlspecialchars(substr($reserva['fecha_ingreso'], 0, 10)); ?>"
                   class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-400">Noches</label>
            <input type="number" name="noches" min="1"
                   value="<?php echo (int)$reserva['noches']; ?>"
                   class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-400">Adultos</label>
            <input type="number" name="adultos" min="1"
                   value="<?php echo (int)$reserva['adultos']; ?>"
                   class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-400">Menores</label>
            <input type="number" name="menores" min="0"
                   value="<?php echo (int)$reserva['menores']; ?>"
                   class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
          </div>

          <!-- SELECT DE PLAN -->
          <div>
            <label class="block text-xs mb-1 text-slate-400">Plan</label>
            <select name="id_plan"
                    class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
              <option value="">Selecciona un plan</option>
              <?php foreach ($planes as $p): ?>
                <option value="<?php echo (int)$p['id_plan']; ?>"
                  <?php echo ((int)$p['id_plan'] === (int)$reserva['id_plan']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($p['nombre']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- TIPO DE ALOJAMIENTO EDITABLE -->
          <div>
            <label class="block text-xs mb-1 text-slate-400">Tipo de alojamiento</label>
            <select name="tipo_alojamiento"
                    class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
              <option value="">Sin asignar</option>
              <option value="glamping" <?php echo ($reserva['tipo_alojamiento'] === 'glamping') ? 'selected' : ''; ?>>
                Glamping
              </option>
              <option value="camping" <?php echo ($reserva['tipo_alojamiento'] === 'camping') ? 'selected' : ''; ?>>
                Camping
              </option>
              <option value="room" <?php echo ($reserva['tipo_alojamiento'] === 'room') ? 'selected' : ''; ?>>
                Habitación
              </option>
            </select>
          </div>

          <!-- NÚMERO DE ALOJAMIENTO -->
          <div>
            <label class="block text-xs mb-1 text-slate-400">Número de alojamiento</label>
            <input type="number" name="numero_alojamiento" min="1"
                   value="<?php echo (int)$reserva['numero_alojamiento']; ?>"
                   class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
          </div>

          <div>
            <label class="block text-xs mb-1 text-slate-400">Valor total (editable)</label>
            <input id="valor_total" name="valor_total"
                   value="<?php echo (int)$reserva['valor_total']; ?>"
                   class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs mb-1 text-slate-400">Saldo (no editable)</label>
            <input id="saldo" name="saldo"
                   value="<?php echo (int)$reserva['saldo']; ?>"
                   readonly
                   class="w-full rounded-lg bg-slate-900/80 border border-slate-700 px-3 py-2 text-sm text-slate-400 cursor-not-allowed">
          </div>
        </div>

        <!-- SERVICIOS DE LA RESERVA -->
        <div class="border-t border-slate-700 pt-4">
          <h2 class="text-sm font-semibold text-slate-200 mb-2">Servicios / extras de la reserva</h2>

          <?php if (!empty($serviciosReserva)): ?>
            <div class="overflow-x-auto text-xs mb-4">
              <table class="min-w-full border border-slate-700 rounded-xl overflow-hidden">
                <thead class="bg-slate-900/80 text-slate-300">
                  <tr>
                    <th class="px-3 py-2 text-left">Servicio</th>
                    <th class="px-3 py-2 text-right">Cantidad</th>
                    <th class="px-3 py-2 text-right">Precio unitario</th>
                    <th class="px-3 py-2 text-right">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($serviciosReserva as $s): ?>
                  <tr class="border-t border-slate-800">
                    <td class="px-3 py-2"><?php echo htmlspecialchars($s['nombre_servicio']); ?></td>
                    <td class="px-3 py-2 text-right"><?php echo (int)$s['cantidad']; ?></td>
                    <td class="px-3 py-2 text-right"><?php echo number_format($s['precio_unitario'], 0, ',', '.'); ?></td>
                    <td class="px-3 py-2 text-right text-emerald-300"><?php echo number_format($s['subtotal'], 0, ',', '.'); ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-xs text-slate-400 mb-2">Esta reserva no tiene servicios adicionales registrados.</p>
          <?php endif; ?>

          <!-- Agregar nuevo servicio -->
          <div class="mt-3 bg-slate-900/60 border border-slate-700 rounded-xl p-4 text-xs space-y-3">
            <p class="text-slate-300 font-semibold mb-1">Agregar nuevo servicio extra</p>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
              <div>
                <label class="block text-[11px] mb-1 text-slate-400">Servicio</label>
                <select id="nuevo_servicio_id" name="nuevo_servicio_id"
                        class="w-full rounded-lg bg-slate-950/70 border border-slate-700 px-2 py-1.5 text-xs">
                  <option value="">Selecciona servicio</option>
                  <?php foreach ($allServicios as $sv): ?>
                    <option value="<?php echo (int)$sv['id_servicio']; ?>"
                            data-precio="<?php echo (float)$sv['precio_base']; ?>">
                      <?php echo htmlspecialchars($sv['nombre']); ?> (<?php echo number_format($sv['precio_base'], 0, ',', '.'); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-[11px] mb-1 text-slate-400">Cantidad</label>
                <input type="number" id="nuevo_servicio_cantidad" name="nuevo_servicio_cantidad"
                       min="1" value="1"
                       class="w-full rounded-lg bg-slate-950/70 border border-slate-700 px-2 py-1.5 text-xs">
              </div>
              <div>
                <label class="block text-[11px] mb-1 text-slate-400">Subtotal estimado</label>
                <input type="text" id="nuevo_servicio_subtotal" readonly
                       class="w-full rounded-lg bg-slate-950/70 border border-slate-700 px-2 py-1.5 text-xs text-emerald-300">
              </div>
              <div class="flex gap-2">
                <button type="button"
                        onclick="recalcularSubtotalServicio()"
                        class="flex-1 rounded-lg border border-slate-600 px-2 py-1.5 text-[11px] hover:bg-slate-800">
                  Calcular
                </button>
              </div>
            </div>

            <p id="nuevo_servicio_msg" class="mt-1 text-[11px] text-slate-400">
              Al guardar, este servicio se registrará como extra y el backend ajustará el total y el saldo.
            </p>
          </div>
        </div>

        <!-- PAGOS DE LA RESERVA -->
        <div class="border-t border-slate-700 pt-4">
          <h2 class="text-sm font-semibold text-slate-200 mb-2">Pagos de la reserva</h2>

          <?php if (!empty($pagos)): ?>
            <div class="overflow-x-auto text-xs mb-4">
              <table class="min-w-full border border-slate-700 rounded-xl overflow-hidden">
                <thead class="bg-slate-900/80 text-slate-300">
                  <tr>
                    <th class="px-3 py-2 text-left">Abono</th>
                    <th class="px-3 py-2 text-left">Fecha</th>
                    <th class="px-3 py-2 text-left">Método</th>
                    <th class="px-3 py-2 text-right">Monto</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $numAbono = 1;
                  foreach ($pagos as $pago):
                ?>
                  <tr class="border-t border-slate-800">
                    <td class="px-3 py-2"><?php echo 'Abono #' . $numAbono; ?></td>
                    <td class="px-3 py-2"><?php echo htmlspecialchars($pago['fecha_pago']); ?></td>
                    <td class="px-3 py-2"><?php echo htmlspecialchars($pago['metodo']); ?></td>
                    <td class="px-3 py-2 text-right text-emerald-300">
                      <?php echo number_format($pago['monto'], 0, ',', '.'); ?>
                    </td>
                  </tr>
                <?php
                  $numAbono++;
                  endforeach;
                ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-xs text-slate-400 mb-2">No hay pagos registrados para esta reserva.</p>
          <?php endif; ?>

          <?php if (!empty($pagos) && !$puedeNuevoPago): ?>
            <p class="text-xs text-red-300 mt-1">
              Esta reserva ya tiene <?php echo (int)$totalPagos; ?> pagos registrados. 
              No se permiten más abonos.
            </p>
          <?php endif; ?>

          <!-- Registrar nuevo pago (solo si no se superó el límite) -->
          <?php if ($puedeNuevoPago): ?>
          <div class="mt-3 bg-slate-900/60 border border-slate-700 rounded-xl p-4 text-xs space-y-3">
            <p class="text-slate-300 font-semibold mb-1">
              Registrar nuevo pago (Abono #<?php echo (int)$totalPagos + 1; ?> de 3)
            </p>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
              <div>
                <label class="block text-[11px] mb-1 text-slate-400">Fecha pago</label>
                <input type="date" name="nuevo_pago_fecha"
                       value="<?php echo date('Y-m-d'); ?>"
                       class="w-full rounded-lg bg-slate-950/70 border border-slate-700 px-2 py-1.5 text-xs">
              </div>
              <div>
                <label class="block text-[11px] mb-1 text-slate-400">Método</label>
                <select name="nuevo_pago_metodo"
                        class="w-full rounded-lg bg-slate-950/70 border border-slate-700 px-2 py-1.5 text-xs">
                  <option value="">Selecciona método</option>
                  <option value="EFECTIVO">EFECTIVO</option>
                  <option value="DATAFONO">DATAFONO</option>
                  <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                  <option value="OTRO">OTRO</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] mb-1 text-slate-400">Monto</label>
                <input type="number" name="nuevo_pago_monto" min="0" step="1000"
                       class="w-full rounded-lg bg-slate-950/70 border border-slate-700 px-2 py-1.5 text-xs">
              </div>
              <div>
                <p class="text-[11px] text-slate-400">
                  El backend sumará este pago al total pagado y recalculará el saldo.
                </p>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- OBSERVACIONES + BOTONES -->
        <div class="border-t border-slate-700 pt-4">
          <label class="block text-xs mb-1 text-slate-400">Observaciones</label>
          <textarea name="observaciones" rows="3"
                    class="w-full rounded-lg bg-slate-900/70 border border-slate-600 px-3 py-2 text-sm"><?php
            echo htmlspecialchars($reserva['observaciones']);
          ?></textarea>
        </div>

        <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div class="flex gap-3">
            <a href="editar_reserva.php"
               class="px-4 py-2 rounded-xl border border-slate-600 text-xs text-slate-200 hover:bg-slate-700">
              Volver a búsqueda
            </a>

            <?php if ($reserva['estado'] !== 'CANCELADA'): ?>
              <button type="button"
                      onclick="confirmarCancelacion(<?php echo (int)$reserva['id_reserva']; ?>)"
                      class="px-4 py-2 rounded-xl border border-red-500 text-xs text-red-200 hover:bg-red-600/20">
                Cancelar reserva
              </button>
            <?php else: ?>
              <span class="px-3 py-1 rounded-xl border border-red-500/60 text-xs text-red-200 bg-red-900/20">
                Reserva cancelada
              </span>
            <?php endif; ?>
          </div>

          <button type="submit"
                  class="px-6 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-semibold text-white">
            Guardar cambios
          </button>
        </div>
      </form>

      <!-- Formulario oculto para cancelar reserva -->
      <form id="form-cancelar-reserva" method="post" action="../Controlador/cancelar_reserva.php" class="hidden">
        <input type="hidden" name="id_reserva" id="cancelar_id_reserva" value="">
      </form>

    <?php endif; ?>

  </div>

  <script>
    function recalcularSubtotalServicio() {
        const select = document.getElementById('nuevo_servicio_id');
        const opt    = select.options[select.selectedIndex];
        const precio = parseFloat(opt.getAttribute('data-precio') || '0');
        const cant   = parseInt(document.getElementById('nuevo_servicio_cantidad').value || '0', 10);

        const subtotal = precio * cant;
        const campoSubtotal = document.getElementById('nuevo_servicio_subtotal');
        const msg = document.getElementById('nuevo_servicio_msg');

        if (!precio || !cant) {
            campoSubtotal.value = '';
            msg.textContent = 'Selecciona un servicio y cantidad para ver el subtotal.';
            return;
        }

        campoSubtotal.value = new Intl.NumberFormat('es-CO').format(subtotal);
        msg.textContent = 'Al guardar, el backend ajustará valor_total, valor_extras y saldo automáticamente.';
    }

    // ===== Recalcular saldo cuando cambie el valor_total =====
    (function () {
        const valorTotalInput = document.getElementById('valor_total');
        const saldoInput      = document.getElementById('saldo');
        const totalPagadoInp  = document.getElementById('total_pagado_inicial');

        if (!valorTotalInput || !saldoInput || !totalPagadoInp) return;

        const totalPagado = parseInt(totalPagadoInp.value || '0', 10);

        function limpiarNumero(str) {
            if (!str) return 0;
            // eliminar puntos, comas, espacios
            str = String(str).replace(/[^\d]/g, '');
            const n = parseInt(str, 10);
            return isNaN(n) ? 0 : n;
        }

        function onValorTotalChange() {
            const vt = limpiarNumero(valorTotalInput.value);
            let nuevoSaldo = vt - totalPagado;
            if (nuevoSaldo < 0) nuevoSaldo = 0;

            saldoInput.value = nuevoSaldo;
        }

        valorTotalInput.addEventListener('input', onValorTotalChange);
        valorTotalInput.addEventListener('change', onValorTotalChange);
    })();

    // ===== Cancelación de reserva =====
    function confirmarCancelacion(id) {
        if (!confirm('¿Seguro que deseas cancelar esta reserva? Esto liberará el glamping/camping/habitación asociado y pondrá el saldo en 0.')) {
            return;
        }
        const hiddenId = document.getElementById('cancelar_id_reserva');
        const form     = document.getElementById('form-cancelar-reserva');
        if (!hiddenId || !form) return;

        hiddenId.value = id;
        form.submit();
    }
  </script>
</body>
</html>
