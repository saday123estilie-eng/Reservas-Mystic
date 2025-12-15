<?php
// reservas.php - Panel de reservas

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Filtro opcional por estado (RESERVADA, CHECKIN, CHECKOUT, CANCELADA)
    $fEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

    $where  = '';
    $params = [];

    if ($fEstado !== '') {
        $where = "WHERE r.estado = :estado";
        $params[':estado'] = $fEstado;
    }

    $sql = "
        SELECT
            r.id_reserva,
            r.codigo,
            r.fecha_ingreso,
            r.noches,
            r.adultos,
            r.menores,
            r.plan_nombre,
            r.valor_total,
            r.saldo,
            r.estado,
            r.creado_en,
            r.actualizado_en,
            c.nombre   AS nombre_cliente,
            c.cedula   AS cedula_cliente
        FROM reservas r
        INNER JOIN clientes c ON r.id_cliente = c.id_cliente
        $where
        ORDER BY r.fecha_ingreso DESC, r.id_reserva DESC
        LIMIT 300
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error al cargar reservas: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de reservas | Mystic Paradise</title>
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
                Panel reservas
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
                <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
              </p>
              <p class="text-[10px] uppercase tracking-[0.28em] text-blue-200/80">
                <?php echo htmlspecialchars(strtoupper($_SESSION['rol'] ?? 'USUARIO')); ?>
              </p>
            </div>

            <div class="h-9 w-9 rounded-full bg-slate-950/60 border border-white/40 flex items-center justify-center text-xs font-semibold shadow-lg shadow-black/40">
              <?php
                $inicial = mb_substr($_SESSION['nombre'] ?? 'U', 0, 1, 'UTF-8');
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

  <div class="max-w-7xl mx-auto py-8 px-4">

    <!-- HEADER -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-blue-100">Panel de reservas</h1>
        <p class="text-xs text-slate-400 mt-1">
          Visualiza las reservas. <span class="text-amber-300">Naranja = modificada</span>,
          <span class="text-red-300">Roja = cancelada</span>.
        </p>
      </div>

      <!-- Filtros -->
      <form method="get" class="flex items-center gap-2 text-xs">
        <label class="text-slate-400">Estado:</label>
        <select name="estado"
                class="rounded-lg bg-slate-900/70 border border-slate-600 px-2 py-1">
          <option value="">Todos</option>
          <option value="RESERVADA" <?php echo $fEstado==='RESERVADA'?'selected':''; ?>>RESERVADA</option>
          <option value="CHECKIN"   <?php echo $fEstado==='CHECKIN'?'selected':''; ?>>CHECKIN</option>
          <option value="CHECKOUT"  <?php echo $fEstado==='CHECKOUT'?'selected':''; ?>>CHECKOUT</option>
          <option value="CANCELADA" <?php echo $fEstado==='CANCELADA'?'selected':''; ?>>CANCELADA</option>
        </select>
        <button type="submit"
                class="rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-1 font-semibold text-white">
          Filtrar
        </button>
      </form>
    </div>

    <!-- LEYENDA COLORES -->
    <div class="flex flex-wrap items-center gap-4 text-[11px] mb-4">
      <div class="flex items-center gap-2">
        <span class="inline-block w-4 h-4 rounded bg-slate-800 border border-slate-600"></span>
        <span>Normal (sin cambios)</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="inline-block w-4 h-4 rounded bg-amber-900/50 border border-amber-500/60"></span>
        <span>Modificada (actualizado_en &ne; creado_en)</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="inline-block w-4 h-4 rounded bg-red-900/60 border border-red-500/70"></span>
        <span>Cancelada (estado = CANCELADA)</span>
      </div>
    </div>

    <!-- TABLA -->
    <div class="bg-slate-800/70 border border-slate-700 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto text-xs">
        <table class="min-w-full">
          <thead class="bg-slate-900/80 text-slate-300">
            <tr>
              <th class="px-3 py-2 text-left">#</th>
              <th class="px-3 py-2 text-left">Código</th>
              <th class="px-3 py-2 text-left">Cliente</th>
              <th class="px-3 py-2 text-left">Cédula</th>
              <th class="px-3 py-2 text-left">Plan</th>
              <th class="px-3 py-2 text-left">Ingreso</th>
              <th class="px-3 py-2 text-right">Noches</th>
              <th class="px-3 py-2 text-right">Adultos</th>
              <th class="px-3 py-2 text-right">Menores</th>
              <th class="px-3 py-2 text-right">Total</th>
              <th class="px-3 py-2 text-right">Saldo</th>
              <th class="px-3 py-2 text-left">Estado</th>
              <th class="px-3 py-2 text-left">Creado</th>
              <th class="px-3 py-2 text-left">Actualizado</th>
              <th class="px-3 py-2 text-center">Acción</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="15" class="px-3 py-4 text-center text-slate-400">
                No hay reservas para mostrar.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <?php
                // CLASE SEGÚN ESTADO / MODIFICACIÓN
                $rowClass = "border-t border-slate-800";

                if ($r['estado'] === 'CANCELADA') {
                    // ROJO
                    $rowClass .= " bg-red-950/70 text-red-100";
                } elseif ($r['actualizado_en'] !== $r['creado_en']) {
                    // NARANJA (modificada)
                    $rowClass .= " bg-amber-950/40";
                } else {
                    // normal
                    $rowClass .= " bg-slate-900/30";
                }
              ?>
              <tr class="<?php echo $rowClass; ?>">
                <td class="px-3 py-2"><?php echo (int)$r['id_reserva']; ?></td>
                <td class="px-3 py-2 font-mono text-blue-200"><?php echo htmlspecialchars($r['codigo']); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars($r['nombre_cliente']); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars($r['cedula_cliente']); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars($r['plan_nombre']); ?></td>
                <td class="px-3 py-2 text-slate-200">
                  <?php echo htmlspecialchars($r['fecha_ingreso']); ?>
                </td>
                <td class="px-3 py-2 text-right"><?php echo (int)$r['noches']; ?></td>
                <td class="px-3 py-2 text-right"><?php echo (int)$r['adultos']; ?></td>
                <td class="px-3 py-2 text-right"><?php echo (int)$r['menores']; ?></td>
                <td class="px-3 py-2 text-right text-slate-50">
                  <?php echo number_format($r['valor_total'], 0, ',', '.'); ?>
                </td>
                <td class="px-3 py-2 text-right <?php echo $r['saldo'] > 0 ? 'text-amber-200' : 'text-emerald-300'; ?>">
                  <?php echo number_format($r['saldo'], 0, ',', '.'); ?>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px]
                    <?php
                      if ($r['estado'] === 'CANCELADA') {
                          echo 'bg-red-900/80 text-red-100 border border-red-500/60';
                      } elseif ($r['estado'] === 'CHECKIN') {
                          echo 'bg-emerald-900/70 text-emerald-100 border border-emerald-500/60';
                      } elseif ($r['estado'] === 'CHECKOUT') {
                          echo 'bg-sky-900/70 text-sky-100 border border-sky-500/60';
                      } else {
                          echo 'bg-slate-800 text-slate-100 border border-slate-600';
                      }
                    ?>">
                    <?php echo htmlspecialchars($r['estado']); ?>
                  </span>
                </td>
                <td class="px-3 py-2 text-slate-300">
                  <?php echo htmlspecialchars($r['creado_en']); ?>
                </td>
                <td class="px-3 py-2 text-slate-300">
                  <?php echo htmlspecialchars($r['actualizado_en']); ?>
                </td>
                <td class="px-3 py-2 text-center">
                  <a href="editar_reserva.php?id_reserva=<?php echo (int)$r['id_reserva']; ?>"
                     class="inline-flex items-center justify-center rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-1 text-[11px] text-white font-semibold">
                    Editar
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</body>
</html>
