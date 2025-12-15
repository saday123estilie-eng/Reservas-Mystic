<?php
// check_out.php
// Pantalla de Check-out: buscar reserva, ver saldo, agregar extras y marcar salida liberando el alojamiento

require_once __DIR__ . '/../config/conexion.php';

// ===============================
//  SESIÓN Y ROL DEL USUARIO
// ===============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idUsuario = $_SESSION['id_usuario'] ?? null;
$rol       = $_SESSION['rol']        ?? null;

if (!$idUsuario) {
    header("Location: ../login.php");
    exit;
}

// URL del menú según el rol (AJUSTA RUTAS A TU PROYECTO)
$menuUrl = '../index.php';

if ($rol === 'ADMIN') {
    $menuUrl = '../admin/menu.php';
} elseif ($rol === 'ASESOR') {
    $menuUrl = '../asesor/menu.php';
} elseif ($rol === 'RECEPCION') {
    $menuUrl = '../recepcion/menu.php';
}

$mensajeOk      = '';
$mensajeErr     = '';
$reserva        = null;
$pagosReserva   = [];
$servicios      = [];
$extrasReserva  = [];
$categoriasServicios = [];

$busqueda = [
    'codigo' => isset($_GET['codigo']) ? trim($_GET['codigo']) : '',
    'cedula' => isset($_GET['cedula']) ? trim($_GET['cedula']) : '',
    'nombre' => isset($_GET['nombre']) ? trim($_GET['nombre']) : '',
];

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Mensajes PRG
if (isset($_GET['ok'])) {
    if ($_GET['ok'] === 'pago') {
        $mensajeOk = "Pago registrado y saldo actualizado.";
    } elseif ($_GET['ok'] === 'checkout') {
        $mensajeOk = "Check-out realizado. El alojamiento quedó disponible de nuevo para esas fechas.";
    } elseif ($_GET['ok'] === 'extras') {
        $mensajeOk = "Extras guardados y saldo actualizado.";
    }
}

/**
 * 1) Procesar POST (pago final, agregar extras o checkout)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    // 1.a) Registrar pago final (misma lógica que en check_in)
    if ($accion === 'registrar_pago') {
        $id_reserva = isset($_POST['id_reserva']) ? (int)$_POST['id_reserva'] : 0;
        $metodo     = isset($_POST['metodo']) ? trim($_POST['metodo']) : '';
        $valor      = isset($_POST['valor']) ? (int)$_POST['valor'] : 0;
        $nota       = isset($_POST['nota']) ? trim($_POST['nota']) : '';

        $metodosPermitidos = ['DATÁFONO', 'TRANSFERENCIA', 'EFECTIVO', 'OTRO'];

        if ($id_reserva <= 0) {
            $mensajeErr = "Reserva inválida para registrar pago.";
        } elseif (!in_array($metodo, $metodosPermitidos, true)) {
            $mensajeErr = "Método de pago no válido.";
        } elseif ($valor <= 0) {
            $mensajeErr = "El valor del pago debe ser mayor a cero.";
        } else {
            try {
                $stmtRes = $pdo->prepare("
                    SELECT 
                        valor_total,
                        pago_total,
                        pago_efectivo,
                        pago_transferencia,
                        pago_datafono
                    FROM reservas
                    WHERE id_reserva = :id_reserva
                ");
                $stmtRes->execute([':id_reserva' => $id_reserva]);
                $resData = $stmtRes->fetch(PDO::FETCH_ASSOC);

                if (!$resData) {
                    throw new Exception("Reserva no encontrada para actualizar pagos.");
                }

                $valor_total        = (int)$resData['valor_total'];
                $pago_total_actual  = (int)$resData['pago_total'];
                $pago_efectivo_act  = (int)$resData['pago_efectivo'];
                $pago_trans_act     = (int)$resData['pago_transferencia'];
                $pago_datafono_act  = (int)$resData['pago_datafono'];

                $stmtNum = $pdo->prepare("
                    SELECT COALESCE(MAX(num_pago), 0) AS max_num
                    FROM pagos
                    WHERE id_reserva = :id_reserva
                ");
                $stmtNum->execute([':id_reserva' => $id_reserva]);
                $rowNum  = $stmtNum->fetch(PDO::FETCH_ASSOC);
                $numPago = (int)$rowNum['max_num'] + 1;

                $stmtPago = $pdo->prepare("
                    INSERT INTO pagos (id_reserva, num_pago, fecha, metodo, valor, nota)
                    VALUES (:id_reserva, :num_pago, CURDATE(), :metodo, :valor, :nota)
                ");
                $stmtPago->execute([
                    ':id_reserva' => $id_reserva,
                    ':num_pago'   => $numPago,
                    ':metodo'     => $metodo,
                    ':valor'      => $valor,
                    ':nota'       => $nota !== '' ? $nota : null,
                ]);

                $incEfectivo      = ($metodo === 'EFECTIVO')      ? $valor : 0;
                $incTransferencia = ($metodo === 'TRANSFERENCIA') ? $valor : 0;
                $incDatafono      = ($metodo === 'DATÁFONO')      ? $valor : 0;

                $nuevo_pago_total        = $pago_total_actual + $valor;
                $nuevo_pago_efectivo     = $pago_efectivo_act + $incEfectivo;
                $nuevo_pago_transfer     = $pago_trans_act    + $incTransferencia;
                $nuevo_pago_datafono     = $pago_datafono_act + $incDatafono;
                $nuevo_saldo             = max($valor_total - $nuevo_pago_total, 0);

                $stmtUp = $pdo->prepare("
                    UPDATE reservas
                    SET 
                        pago_total         = :pago_total,
                        pago_efectivo      = :pago_efectivo,
                        pago_transferencia = :pago_transferencia,
                        pago_datafono      = :pago_datafono,
                        saldo              = :saldo
                    WHERE id_reserva = :id_reserva
                ");
                $stmtUp->execute([
                    ':pago_total'         => $nuevo_pago_total,
                    ':pago_efectivo'      => $nuevo_pago_efectivo,
                    ':pago_transferencia' => $nuevo_pago_transfer,
                    ':pago_datafono'      => $nuevo_pago_datafono,
                    ':saldo'              => $nuevo_saldo,
                    ':id_reserva'         => $id_reserva,
                ]);

                header("Location: check_out.php?id_reserva=" . $id_reserva . "&ok=pago");
                exit;

            } catch (Exception $e) {
                $mensajeErr = "Error al registrar el pago: " . $e->getMessage();
            }
        }
    }

    // 1.b) Agregar servicios extras en Check-out
    if ($accion === 'agregar_extras') {
        $id_reserva = isset($_POST['id_reserva']) ? (int)$_POST['id_reserva'] : 0;
        $extrasPost = isset($_POST['extras']) ? $_POST['extras'] : [];

        if ($id_reserva <= 0) {
            $mensajeErr = "Reserva inválida para agregar extras.";
        } else {
            try {
                $pdo->beginTransaction();

                $totalExtrasNuevos = 0;

                foreach ($extrasPost as $id_servicio => $info) {
                    if (!isset($info['usar']) || $info['usar'] != '1') {
                        continue;
                    }

                    $id_servicio = (int)$id_servicio;
                    $cantidad    = isset($info['cantidad']) ? (int)$info['cantidad'] : 0;
                    if ($cantidad <= 0) {
                        continue;
                    }

                    // Precio base del servicio
                    $stmt = $pdo->prepare("
                        SELECT precio_base 
                        FROM servicios 
                        WHERE id_servicio = :id AND activo = 1
                    ");
                    $stmt->execute([':id' => $id_servicio]);
                    $serv = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$serv) {
                        continue;
                    }

                    $precio_unit = (int)$serv['precio_base'];
                    $total_linea = $precio_unit * $cantidad;

                    // Insertar como extra (no incluido en plan)
                    $stmtIns = $pdo->prepare("
                        INSERT INTO reservas_servicios
                            (id_reserva, id_servicio, cantidad, incluido_en_plan, 
                             precio_unit_aplicado, total_linea, incluidas)
                        VALUES
                            (:id_reserva, :id_servicio, :cantidad, 0,
                             :precio_unit, :total_linea, 0)
                    ");
                    $stmtIns->execute([
                        ':id_reserva'  => $id_reserva,
                        ':id_servicio' => $id_servicio,
                        ':cantidad'    => $cantidad,
                        ':precio_unit' => $precio_unit,
                        ':total_linea' => $total_linea,
                    ]);

                    $totalExtrasNuevos += $total_linea;
                }

                if ($totalExtrasNuevos > 0) {
                    // Traer reserva para recalcular
                    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE id_reserva = :id");
                    $stmt->execute([':id' => $id_reserva]);
                    $r = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$r) {
                        throw new Exception("Reserva no encontrada para recalcular.");
                    }

                    $valor_base        = (int)$r['valor_base'];
                    $valor_extras_prev = (int)$r['valor_extras'];
                    $descuento_valor   = (int)$r['descuento_valor'];
                    $pago_total        = (int)$r['pago_total'];

                    $nuevo_valor_extras = $valor_extras_prev + $totalExtrasNuevos;
                    $nuevo_valor_total  = $valor_base + $nuevo_valor_extras - $descuento_valor;
                    $nuevo_saldo        = $nuevo_valor_total - $pago_total;

                    $stmtUp = $pdo->prepare("
                        UPDATE reservas
                        SET valor_extras = :ve,
                            valor_total  = :vt,
                            saldo        = :saldo
                        WHERE id_reserva = :id
                    ");
                    $stmtUp->execute([
                        ':ve'    => $nuevo_valor_extras,
                        ':vt'    => $nuevo_valor_total,
                        ':saldo' => $nuevo_saldo,
                        ':id'    => $id_reserva,
                    ]);
                } else {
                    $pdo->commit();
                    header("Location: check_out.php?id_reserva=" . $id_reserva);
                    exit;
                }

                $pdo->commit();

                header("Location: check_out.php?id_reserva=" . $id_reserva . "&ok=extras");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $mensajeErr = "Error al agregar extras: " . $e->getMessage();
            }
        }
    }

    // 1.c) Confirmar Checkout y liberar alojamiento
    if ($accion === 'confirmar_checkout') {
        $id_reserva = isset($_POST['id_reserva']) ? (int)$_POST['id_reserva'] : 0;

        if ($id_reserva <= 0) {
            $mensajeErr = "Reserva inválida para Checkout.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmtRes = $pdo->prepare("
                    SELECT *
                    FROM reservas
                    WHERE id_reserva = :id_reserva
                    FOR UPDATE
                ");
                $stmtRes->execute([':id_reserva' => $id_reserva]);
                $resData = $stmtRes->fetch(PDO::FETCH_ASSOC);

                if (!$resData) {
                    throw new Exception("Reserva no encontrada para Checkout.");
                }

                // Marcar estado CHECKOUT
                $stmtUp = $pdo->prepare("
                    UPDATE reservas
                    SET estado = 'CHECKOUT'
                    WHERE id_reserva = :id_reserva
                ");
                $stmtUp->execute([':id_reserva' => $id_reserva]);

                // Aquí iría la liberación real del alojamiento en tu modelo
                /*
                $stmtLib = $pdo->prepare("
                    UPDATE ocupacion_alojamiento
                    SET estado = 'LIBRE'
                    WHERE id_reserva = :id_reserva
                ");
                $stmtLib->execute([':id_reserva' => $id_reserva]);
                */

                $pdo->commit();

                header("Location: check_out.php?id_reserva=" . $id_reserva . "&ok=checkout");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $mensajeErr = "Error al realizar el Checkout: " . $e->getMessage();
            }
        }
    }
}

/**
 * 2) Buscar la reserva seleccionada
 */
$idReservaSeleccionada = 0;

if (isset($_GET['id_reserva']) && (int)$_GET['id_reserva'] > 0) {
    $idReservaSeleccionada = (int)$_GET['id_reserva'];
} else {
    if ($busqueda['codigo'] !== '' || $busqueda['cedula'] !== '' || $busqueda['nombre'] !== '') {
        $sql = "
            SELECT 
                r.*,
                c.nombre   AS cliente_nombre,
                c.cedula   AS cliente_cedula,
                c.whatsapp AS cliente_whatsapp,
                COALESCE(r.plan_nombre, p.nombre) AS plan_nombre_real
            FROM reservas r
            INNER JOIN clientes c ON c.id_cliente = r.id_cliente
            LEFT JOIN planes   p ON p.id_plan    = r.id_plan
            WHERE 1 = 1
        ";

        $params = [];

        if ($busqueda['codigo'] !== '') {
            $sql .= " AND r.codigo = :codigo";
            $params[':codigo'] = $busqueda['codigo'];
        }

        if ($busqueda['cedula'] !== '') {
            $sql .= " AND c.cedula = :cedula";
            $params[':cedula'] = $busqueda['cedula'];
        }

        if ($busqueda['nombre'] !== '') {
            $sql .= " AND c.nombre LIKE :nombre";
            $params[':nombre'] = '%' . $busqueda['nombre'] . '%';
        }

        $sql .= " ORDER BY r.creado_en DESC LIMIT 1";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reserva) {
                $idReservaSeleccionada = (int)$reserva['id_reserva'];
            } else {
                $mensajeErr = $mensajeErr ?: "No se encontró ninguna reserva con esos datos.";
            }
        } catch (PDOException $e) {
            $mensajeErr = "Error al buscar la reserva: " . $e->getMessage();
        }
    }
}

if ($idReservaSeleccionada > 0 && !$reserva) {
    $sql = "
        SELECT 
            r.*,
            c.nombre   AS cliente_nombre,
            c.cedula   AS cliente_cedula,
            c.whatsapp AS cliente_whatsapp,
            COALESCE(r.plan_nombre, p.nombre) AS plan_nombre_real
        FROM reservas r
        INNER JOIN clientes c ON c.id_cliente = r.id_cliente
        LEFT JOIN planes   p ON p.id_plan    = r.id_plan
        WHERE r.id_reserva = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idReservaSeleccionada]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Cargar catálogo de servicios activos para poder agregar extras
try {
    $stmtServ = $pdo->query("
        SELECT 
            id_servicio,
            categoria,
            nombre,
            precio_base
        FROM servicios
        WHERE activo = 1
        ORDER BY categoria, nombre
    ");
    $servicios = $stmtServ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensajeErr = "Error al cargar servicios: " . $e->getMessage();
}

// Construir listado de categorías
foreach ($servicios as $srv) {
    $cat = trim((string)$srv['categoria']);
    if ($cat !== '' && !in_array($cat, $categoriasServicios, true)) {
        $categoriasServicios[] = $cat;
    }
}
sort($categoriasServicios);

// Cargar pagos y servicios asociados a la reserva
if ($reserva) {
    $stmtPagos = $pdo->prepare("
        SELECT num_pago, fecha, metodo, valor, nota
        FROM pagos
        WHERE id_reserva = :id_reserva
        ORDER BY fecha, num_pago
    ");
    $stmtPagos->execute([':id_reserva' => $reserva['id_reserva']]);
    $pagosReserva = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

    $stmtExtras = $pdo->prepare("
        SELECT 
            rs.*,
            s.nombre    AS servicio_nombre,
            s.categoria AS servicio_categoria
        FROM reservas_servicios rs
        INNER JOIN servicios s ON s.id_servicio = rs.id_servicio
        WHERE rs.id_reserva = :id_reserva
        ORDER BY s.categoria, s.nombre
    ");
    $stmtExtras->execute([':id_reserva' => $reserva['id_reserva']]);
    $extrasReserva = $stmtExtras->fetchAll(PDO::FETCH_ASSOC);
}

function mp_money($valor) {
    return '$' . number_format((int)$valor, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Check-out | Mystic Paradise</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<!-- 🎨 Mismo tema oscuro azul + negro que el check-in -->
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-50">
  <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">

    <!-- Header con botón Volver al menú -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-2">
      <div>
        <h1 class="text-2xl font-semibold text-blue-400">Check-out de reservas</h1>
        <p class="text-sm text-slate-300 mt-1">
          Busca la reserva, revisa el saldo final, registra el pago restante, agrega consumos y marca el alojamiento como disponible de nuevo.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs text-slate-400 uppercase tracking-wide">
          Rol: <span class="text-slate-100 font-semibold"><?= htmlspecialchars((string)$rol) ?></span>
        </span>
        <a href="<?= htmlspecialchars($menuUrl) ?>"
           class="inline-flex items-center rounded-md bg-slate-800 border border-slate-600 px-3 py-2 text-xs font-medium text-slate-50 hover:bg-slate-700 hover:border-blue-500 transition">
          ⬅ Volver al menú
        </a>
      </div>
    </div>

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

    <!-- 1) Buscar reserva -->
    <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-3">
      <h2 class="text-lg font-medium text-slate-50">1. Buscar reserva para Check-out</h2>
      <form method="get" class="grid gap-3 md:grid-cols-4 md:items-end">
        <div>
          <label class="block text-xs text-slate-400 mb-1">Código de reserva</label>
          <input type="text" name="codigo" value="<?= htmlspecialchars($busqueda['codigo']) ?>"
                 class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-slate-400 mb-1">Cédula</label>
          <input type="text" name="cedula" value="<?= htmlspecialchars($busqueda['cedula']) ?>"
                 class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-slate-400 mb-1">Nombre del cliente</label>
          <input type="text" name="nombre" value="<?= htmlspecialchars($busqueda['nombre']) ?>"
                 class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <button type="submit"
                  class="w-full rounded-md bg-blue-600 hover:bg-blue-700 px-3 py-2 text-sm font-medium text-white mt-5 md:mt-0">
            Buscar
          </button>
        </div>
      </form>
      <p class="text-xs text-slate-400">
        Busca por código, cédula o nombre. Si hay varias coincidencias, se mostrará la más reciente.
      </p>
    </section>

    <?php if ($reserva): ?>
      <?php
        $saldo        = (int)$reserva['saldo'];
        $valor_base   = (int)$reserva['valor_base'];
        $valor_extras = (int)$reserva['valor_extras'];
        $valor_total  = (int)$reserva['valor_total'];
        $pago_total   = (int)$reserva['pago_total'];
      ?>

      <!-- 2) Resumen -->
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <h2 class="text-lg font-medium text-slate-50 mb-1">2. Resumen de la reserva</h2>
            <p class="text-sm text-slate-200">
              Cliente: <span class="font-semibold text-slate-50"><?= htmlspecialchars($reserva['cliente_nombre']) ?></span>
              (<?= htmlspecialchars($reserva['cliente_cedula']) ?>)
            </p>
            <p class="text-sm text-slate-200">
              WhatsApp: <span class="font-mono text-slate-100"><?= htmlspecialchars($reserva['cliente_whatsapp']) ?></span>
            </p>
            <p class="text-sm text-slate-200">
              Plan: <span class="font-semibold text-blue-400"><?= htmlspecialchars($reserva['plan_nombre_real']) ?></span>
            </p>
            <p class="text-sm text-slate-200">
              Fechas: <?= htmlspecialchars($reserva['fecha_ingreso']) ?> · Noches: <?= (int)$reserva['noches'] ?>
            </p>
            <p class="text-xs text-slate-400 mt-1">
              Estado actual: <span class="font-semibold text-slate-50"><?= htmlspecialchars($reserva['estado']) ?></span> ·
              Código: <?= htmlspecialchars($reserva['codigo']) ?> · Reserva #<?= (int)$reserva['id_reserva'] ?>
            </p>
          </div>
          <div class="grid grid-cols-2 gap-2 text-sm md:w-80">
            <div class="rounded-lg bg-slate-800 border border-slate-600 p-2">
              <div class="text-xs text-slate-400">Valor base</div>
              <div class="font-semibold text-slate-50"><?= mp_money($valor_base) ?></div>
            </div>
            <div class="rounded-lg bg-slate-800 border border-slate-600 p-2">
              <div class="text-xs text-slate-400">Extras</div>
              <div class="font-semibold text-slate-50"><?= mp_money($valor_extras) ?></div>
            </div>
            <div class="rounded-lg bg-slate-800 border border-slate-600 p-2">
              <div class="text-xs text-slate-400">Pagos realizados</div>
              <div class="font-semibold text-slate-50"><?= mp_money($pago_total) ?></div>
            </div>
            <div class="rounded-lg bg-blue-950 border border-blue-500 p-2">
              <div class="text-xs text-blue-300">Saldo pendiente</div>
              <div class="font-semibold text-blue-200"><?= mp_money($saldo) ?></div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2.b) Servicios ya registrados -->
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-3">
        <h3 class="text-sm font-medium text-slate-50 mb-1">Servicios ya registrados en esta reserva</h3>
        <?php if (empty($extrasReserva)): ?>
          <p class="text-xs text-slate-400">Aún no hay servicios (incluidos ni extras) asociados a esta reserva.</p>
        <?php else: ?>
          <div class="overflow-x-auto rounded-lg border border-slate-700 bg-slate-900">
            <table class="min-w-full text-xs">
              <thead class="bg-slate-800 text-slate-200">
                <tr>
                  <th class="px-2 py-1 text-left">Categoría</th>
                  <th class="px-2 py-1 text-left">Servicio</th>
                  <th class="px-2 py-1 text-right">Cantidad</th>
                  <th class="px-2 py-1 text-right">Precio unitario</th>
                  <th class="px-2 py-1 text-right">Total</th>
                  <th class="px-2 py-1 text-center">Incluido en plan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($extrasReserva as $er): ?>
                  <tr class="border-t border-slate-700">
                    <td class="px-2 py-1 text-slate-100"><?= htmlspecialchars($er['servicio_categoria']) ?></td>
                    <td class="px-2 py-1 text-slate-100"><?= htmlspecialchars($er['servicio_nombre']) ?></td>
                    <td class="px-2 py-1 text-right text-slate-100"><?= (int)$er['cantidad'] ?></td>
                    <td class="px-2 py-1 text-right text-slate-100"><?= mp_money($er['precio_unit_aplicado']) ?></td>
                    <td class="px-2 py-1 text-right text-slate-100"><?= mp_money($er['total_linea']) ?></td>
                    <td class="px-2 py-1 text-center">
                      <?php if ((int)$er['incluido_en_plan'] === 1): ?>
                        <span class="inline-flex items-center rounded-full bg-blue-950 px-2 py-0.5 text-[10px] text-blue-200 border border-blue-600">
                          Sí
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center rounded-full bg-slate-800 px-2 py-0.5 text-[10px] text-slate-200 border border-slate-600">
                          Extra
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <!-- 3) Agregar servicios adicionales en Check-out -->
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h2 class="text-lg font-medium text-slate-50">3. Agregar servicios adicionales (consumos al salir)</h2>
            <p class="text-xs text-slate-400">
              Si el huésped consumió algo extra (kayak, fogata, bebidas, etc.) durante la estadía, agrégalo aquí para que se sume al saldo final.
            </p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            <div>
              <label class="block text-[11px] text-slate-400 mb-0.5">Buscar servicio</label>
              <input id="buscar-servicio"
                     type="text"
                     placeholder="Nombre del servicio..."
                     class="w-full sm:w-48 rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-[11px] text-slate-400 mb-0.5">Filtrar categoría</label>
              <select id="filtro-categoria"
                      class="w-full sm:w-40 rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Todas</option>
                <?php foreach ($categoriasServicios as $cat): ?>
                  <option value="<?= htmlspecialchars($cat) ?>">
                    <?= htmlspecialchars($cat) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <?php if (empty($servicios)): ?>
          <p class="text-xs text-slate-400">No hay servicios activos configurados.</p>
        <?php else: ?>
          <form method="post" class="space-y-3">
            <input type="hidden" name="accion" value="agregar_extras">
            <input type="hidden" name="id_reserva" value="<?= (int)$reserva['id_reserva'] ?>">

            <div class="overflow-x-auto rounded-lg border border-slate-700 bg-slate-900">
              <table class="min-w-full text-xs">
                <thead class="bg-slate-800 text-slate-200">
                  <tr>
                    <th class="px-2 py-1 text-center">Agregar</th>
                    <th class="px-2 py-1 text-left">Categoría</th>
                    <th class="px-2 py-1 text-left">Servicio</th>
                    <th class="px-2 py-1 text-right">Precio base</th>
                    <th class="px-2 py-1 text-center">Cantidad</th>
                  </tr>
                </thead>
                <tbody id="tbody-servicios">
                  <?php foreach ($servicios as $s): ?>
                    <tr class="border-t border-slate-700"
                        data-categoria="<?= htmlspecialchars($s['categoria']) ?>">
                      <td class="px-2 py-1 text-center">
                        <input type="checkbox"
                               name="extras[<?= (int)$s['id_servicio'] ?>][usar]"
                               value="1"
                               class="rounded border-slate-500 bg-slate-800 text-blue-500 focus:ring-blue-500">
                      </td>
                      <td class="px-2 py-1 col-categoria text-slate-100"><?= htmlspecialchars($s['categoria']) ?></td>
                      <td class="px-2 py-1 col-nombre text-slate-100"><?= htmlspecialchars($s['nombre']) ?></td>
                      <td class="px-2 py-1 text-right text-slate-100"><?= mp_money($s['precio_base']) ?></td>
                      <td class="px-2 py-1 text-center">
                        <input type="number"
                               name="extras[<?= (int)$s['id_servicio'] ?>][cantidad]"
                               value="1"
                               min="1"
                               class="w-16 rounded-md bg-slate-900 border border-slate-600 px-2 py-1 text-xs text-center text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <p class="text-xs text-slate-400">
                Al guardar, estos consumos se suman al <strong class="text-slate-100">valor de extras</strong>, al <strong class="text-slate-100">total</strong> y al <strong class="text-slate-100">saldo</strong> de la reserva.
                Luego puedes registrar el pago final y hacer el Check-out.
              </p>
              <button type="submit"
                      class="inline-flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-medium text-white">
                Guardar consumos y actualizar saldo
              </button>
            </div>
          </form>
        <?php endif; ?>
      </section>

      <!-- 4) Pago final -->
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
          <div>
            <h2 class="text-lg font-medium text-slate-50">4. Registrar pago final</h2>
            <p class="text-sm text-slate-300">
              Si el huésped tiene saldo pendiente, registra aquí el último pago antes de hacer Check-out.
            </p>
          </div>
          <div class="rounded-lg bg-blue-950 border border-blue-500 px-3 py-2 text-sm">
            <div class="text-xs text-blue-300">Saldo actual</div>
            <div class="font-semibold text-blue-200"><?= mp_money($saldo) ?></div>
          </div>
        </div>

        <form method="post" class="grid gap-3 md:grid-cols-4 md:items-end">
          <input type="hidden" name="accion" value="registrar_pago">
          <input type="hidden" name="id_reserva" value="<?= (int)$reserva['id_reserva'] ?>">

          <div>
            <label class="block text-xs text-slate-400 mb-1">Método de pago</label>
            <select name="metodo"
                    class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="DATÁFONO">Datáfono</option>
              <option value="TRANSFERENCIA">Transferencia</option>
              <option value="EFECTIVO">Efectivo</option>
              <option value="OTRO">Otro</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-slate-400 mb-1">Valor a pagar</label>
            <input type="number" name="valor"
                   value="<?= $saldo > 0 ? $saldo : '' ?>"
                   min="1"
                   class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>

          <div class="md:col-span-2">
            <label class="block text-xs text-slate-400 mb-1">Nota (opcional)</label>
            <input type="text" name="nota"
                   placeholder="Ej: Pago final en datáfono, referencia XXXX"
                   class="w-full rounded-md bg-slate-800 border border-slate-600 px-2 py-1 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>

          <div class="md:col-span-4 flex justify-end">
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 px-5 py-2 text-sm font-medium text-white">
              Registrar pago
            </button>
          </div>
        </form>

        <!-- Historial de pagos -->
        <div class="mt-3">
          <h3 class="text-sm font-medium text-slate-50 mb-2">Historial de pagos</h3>
          <?php if (empty($pagosReserva)): ?>
            <p class="text-xs text-slate-400">No hay pagos registrados en la tabla de pagos.</p>
          <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-slate-700 bg-slate-900">
              <table class="min-w-full text-xs">
                <thead class="bg-slate-800 text-slate-200">
                  <tr>
                    <th class="px-2 py-1 text-left">#Pago</th>
                    <th class="px-2 py-1 text-left">Fecha</th>
                    <th class="px-2 py-1 text-left">Método</th>
                    <th class="px-2 py-1 text-right">Valor</th>
                    <th class="px-2 py-1 text-left">Nota</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pagosReserva as $p): ?>
                    <tr class="border-t border-slate-700">
                      <td class="px-2 py-1 text-slate-100">Pago #<?= (int)$p['num_pago'] ?></td>
                      <td class="px-2 py-1 text-slate-100"><?= htmlspecialchars($p['fecha']) ?></td>
                      <td class="px-2 py-1 text-slate-100"><?= htmlspecialchars($p['metodo']) ?></td>
                      <td class="px-2 py-1 text-right text-slate-100"><?= mp_money($p['valor']) ?></td>
                      <td class="px-2 py-1 text-slate-100"><?= htmlspecialchars($p['nota']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 5) Botón de Checkout -->
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-3">
        <h2 class="text-lg font-medium text-slate-50">5. Confirmar Check-out</h2>
        <p class="text-sm text-slate-300">
          Al hacer Check-out, la reserva quedará marcada como <strong class="text-blue-300">CHECKOUT</strong> y el camping/glamping se libera de nuevo,
          incluso si la fecha ya pasó, para que no siga apareciendo tomado en tu pantalla de disponibilidad.
        </p>

        <form method="post"
              onsubmit="return confirm('¿Confirmar Check-out de esta reserva y liberar el alojamiento?');">
          <input type="hidden" name="accion" value="confirmar_checkout">
          <input type="hidden" name="id_reserva" value="<?= (int)$reserva['id_reserva'] ?>">

          <button type="submit"
                  class="inline-flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 px-5 py-2 text-sm font-semibold text-white">
            ✅ Confirmar Check-out y liberar alojamiento
          </button>
        </form>
      </section>

    <?php else: ?>
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm">
        <p class="text-sm text-slate-200">
          Busca una reserva para realizar el Check-out.
        </p>
      </section>
    <?php endif; ?>
  </div>

  <!-- Script filtro de servicios -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const inputBusqueda = document.getElementById('buscar-servicio');
      const selectCategoria = document.getElementById('filtro-categoria');
      const filas = Array.from(document.querySelectorAll('#tbody-servicios tr'));

      function aplicarFiltro() {
        const texto = (inputBusqueda?.value || '').toLowerCase();
        const categoriaSel = selectCategoria?.value || '';

        filas.forEach(fila => {
          const nombre = fila.querySelector('.col-nombre')?.textContent.toLowerCase() || '';
          const categoria = fila.getAttribute('data-categoria') || '';

          const coincideTexto = texto === '' || nombre.includes(texto);
          const coincideCategoria = categoriaSel === '' || categoria === categoriaSel;

          if (coincideTexto && coincideCategoria) {
            fila.classList.remove('hidden');
          } else {
            fila.classList.add('hidden');
          }
        });
      }

      if (inputBusqueda) {
        inputBusqueda.addEventListener('input', aplicarFiltro);
      }
      if (selectCategoria) {
        selectCategoria.addEventListener('change', aplicarFiltro);
      }
    });
  </script>
</body>
</html>
