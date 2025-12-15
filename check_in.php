<?php
// check_in.php
// Pantalla de Check-in de reservas: buscar reserva, ver saldo, agregar extras y registrar pagos

require_once __DIR__ . '/../config/conexion.php';

// ===============================
//  SESIÓN Y ROL DEL USUARIO
// ===============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ajusta estos nombres de variables/roles a tu sistema real
$idUsuario      = $_SESSION['id_usuario'] ?? null;
$rol            = $_SESSION['rol']        ?? null;
$nombreUsuario  = $_SESSION['nombre']     ?? 'Usuario';

if (!$idUsuario) {
    // Si no hay usuario logueado, mandamos a login
    header("Location: ../login.php");
    exit;
}

// URL del menú según el rol (por si la quieres usar en otros lugares)
$menuUrl = '../index.php'; // valor por defecto

if ($rol === 'ADMIN') {
    $menuUrl = '../admin/menu.php';
} elseif ($rol === 'ASESOR') {
    $menuUrl = '../asesor/menu.php';
} elseif ($rol === 'RECEPCION') {
    $menuUrl = '../Vista/recepcion/menu.php';
}

$mensajeOk      = '';
$mensajeErr     = '';
$reserva        = null;
$servicios      = [];
$extrasReserva  = [];
$pagosReserva   = [];

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

// Mensajes provenientes de redirecciones (PRG)
if (isset($_GET['ok'])) {
    switch ($_GET['ok']) {
        case 'extras':
            $mensajeOk = "Extras guardados y saldo actualizado.";
            break;
        case 'pago':
            $mensajeOk = "Pago registrado y saldo actualizado.";
            break;
        case 'extra_edit':
            $mensajeOk = "Servicio extra actualizado correctamente.";
            break;
        case 'extra_del':
            $mensajeOk = "Servicio extra eliminado correctamente.";
            break;
    }
}

/**
 * 1) Procesar POST (agregar extras o registrar pago o editar/eliminar extras)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    /**
     * 1.a) Agregar servicios extras durante el Check-in
     */
    if ($accion === 'agregar_extras') {
        $id_reserva = isset($_POST['id_reserva']) ? (int)$_POST['id_reserva'] : 0;
        $extrasPost = isset($_POST['extras']) ? $_POST['extras'] : [];

        if ($id_reserva <= 0) {
            $mensajeErr = "Reserva inválida para Check-in.";
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

                    // 1) Obtener precio del servicio
                    $stmt = $pdo->prepare("SELECT precio_base FROM servicios WHERE id_servicio = :id AND activo = 1");
                    $stmt->execute([':id' => $id_servicio]);
                    $serv = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$serv) {
                        continue;
                    }

                    $precio_unit = (int)$serv['precio_base'];
                    $total_linea = $precio_unit * $cantidad;

                    // 2) Insertar en reservas_servicios como extra (no incluido en plan)
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
                    // 3) Traer la reserva actual
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

                    // 4) Actualizar reserva (incluye estado CHECKIN)
                    $stmtUp = $pdo->prepare("
                        UPDATE reservas
                        SET valor_extras = :ve,
                            valor_total  = :vt,
                            saldo        = :saldo,
                            estado       = 'CHECKIN'
                        WHERE id_reserva = :id
                    ");
                    $stmtUp->execute([
                        ':ve'    => $nuevo_valor_extras,
                        ':vt'    => $nuevo_valor_total,
                        ':saldo' => $nuevo_saldo,
                        ':id'    => $id_reserva,
                    ]);
                } else {
                    // No se seleccionó nada, solo commit para limpiar transacción
                    $pdo->commit();
                    header("Location: check_in.php?id_reserva=" . $id_reserva);
                    exit;
                }

                $pdo->commit();

                // 🔁 PRG: redirigir para evitar re-envío del POST al refrescar
                header("Location: check_in.php?id_reserva=" . $id_reserva . "&ok=extras");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $mensajeErr = "Error al agregar extras: " . $e->getMessage();
            }
        }
    }

    /**
     * 1.b) Registrar pago (datáfono / transferencia / efectivo / otro)
     */
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
                /** 0) Traer la reserva actual */
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

                /** 1) Calcular num_pago (siguiente número) */
                $stmtNum = $pdo->prepare("
                    SELECT COALESCE(MAX(num_pago), 0) AS max_num
                    FROM pagos
                    WHERE id_reserva = :id_reserva
                ");
                $stmtNum->execute([':id_reserva' => $id_reserva]);
                $rowNum  = $stmtNum->fetch(PDO::FETCH_ASSOC);
                $numPago = (int)$rowNum['max_num'] + 1;

                /** 2) Insertar en tabla pagos */
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

                /** 3) Calcular incrementos según el método */
                $incEfectivo      = ($metodo === 'EFECTIVO')      ? $valor : 0;
                $incTransferencia = ($metodo === 'TRANSFERENCIA') ? $valor : 0;
                $incDatafono      = ($metodo === 'DATÁFONO')      ? $valor : 0;

                /** 4) Calcular nuevos acumulados */
                $nuevo_pago_total        = $pago_total_actual + $valor;
                $nuevo_pago_efectivo     = $pago_efectivo_act + $incEfectivo;
                $nuevo_pago_transfer     = $pago_trans_act    + $incTransferencia;
                $nuevo_pago_datafono     = $pago_datafono_act + $incDatafono;
                $nuevo_saldo             = max($valor_total - $nuevo_pago_total, 0);

                /** 5) Actualizar acumulados en reservas */
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

                // PRG: redirigir para que al refrescar no se reenvíe el POST
                header("Location: check_in.php?id_reserva=" . $id_reserva . "&ok=pago");
                exit;

            } catch (Exception $e) {
                $mensajeErr = "Error al registrar el pago: " . $e->getMessage();
            }
        }
    }

    /**
     * 1.c) Actualizar cantidad de un servicio extra
     */
    if ($accion === 'actualizar_extra') {
        $id_rs          = isset($_POST['id_reserva_servicio']) ? (int)$_POST['id_reserva_servicio'] : 0;
        $nueva_cantidad = isset($_POST['nueva_cantidad']) ? (int)$_POST['nueva_cantidad'] : 0;

        if ($id_rs <= 0 || $nueva_cantidad <= 0) {
            $mensajeErr = "Datos inválidos para actualizar el servicio extra.";
        } else {
            try {
                $pdo->beginTransaction();

                // Obtener línea de reserva_servicio
                $stmt = $pdo->prepare("
                    SELECT id_reserva, incluido_en_plan, precio_unit_aplicado
                    FROM reservas_servicios
                    WHERE id_reserva_servicio = :id
                    FOR UPDATE
                ");
                $stmt->execute([':id' => $id_rs]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    throw new Exception("Servicio extra no encontrado.");
                }

                if ((int)$row['incluido_en_plan'] === 1) {
                    throw new Exception("No se puede editar la cantidad de un servicio incluido en el plan.");
                }

                $id_reserva  = (int)$row['id_reserva'];
                $precio_unit = (int)$row['precio_unit_aplicado'];
                $nuevo_total = $precio_unit * $nueva_cantidad;

                // Actualizar la línea
                $stmtUpLine = $pdo->prepare("
                    UPDATE reservas_servicios
                    SET cantidad = :cant, total_linea = :total
                    WHERE id_reserva_servicio = :id
                ");
                $stmtUpLine->execute([
                    ':cant'  => $nueva_cantidad,
                    ':total' => $nuevo_total,
                    ':id'    => $id_rs,
                ]);

                // Recalcular valor_extras, valor_total y saldo
                $stmtSum = $pdo->prepare("
                    SELECT COALESCE(SUM(total_linea),0) AS suma_extras
                    FROM reservas_servicios
                    WHERE id_reserva = :id_reserva
                      AND incluido_en_plan = 0
                ");
                $stmtSum->execute([':id_reserva' => $id_reserva]);
                $suma = $stmtSum->fetch(PDO::FETCH_ASSOC);
                $valor_extras = (int)$suma['suma_extras'];

                $stmtRes = $pdo->prepare("
                    SELECT valor_base, descuento_valor, pago_total
                    FROM reservas
                    WHERE id_reserva = :id_reserva
                    FOR UPDATE
                ");
                $stmtRes->execute([':id_reserva' => $id_reserva]);
                $r = $stmtRes->fetch(PDO::FETCH_ASSOC);
                if (!$r) {
                    throw new Exception("Reserva no encontrada al recalcular.");
                }

                $valor_base      = (int)$r['valor_base'];
                $descuento_valor = (int)$r['descuento_valor'];
                $pago_total      = (int)$r['pago_total'];

                $valor_total = $valor_base + $valor_extras - $descuento_valor;
                $saldo       = $valor_total - $pago_total;

                $stmtUpRes = $pdo->prepare("
                    UPDATE reservas
                    SET valor_extras = :ve,
                        valor_total  = :vt,
                        saldo        = :saldo
                    WHERE id_reserva = :id_reserva
                ");
                $stmtUpRes->execute([
                    ':ve'         => $valor_extras,
                    ':vt'         => $valor_total,
                    ':saldo'      => $saldo,
                    ':id_reserva' => $id_reserva,
                ]);

                $pdo->commit();

                // 🔁 PRG
                header("Location: check_in.php?id_reserva=" . $id_reserva . "&ok=extra_edit");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $mensajeErr = "Error al actualizar el servicio extra: " . $e->getMessage();
            }
        }
    }

    /**
     * 1.d) Eliminar un servicio extra
     */
    if ($accion === 'eliminar_extra') {
        $id_rs = isset($_POST['id_reserva_servicio']) ? (int)$_POST['id_reserva_servicio'] : 0;

        if ($id_rs <= 0) {
            $mensajeErr = "Datos inválidos para eliminar el servicio extra.";
        } else {
            try {
                $pdo->beginTransaction();

                // Obtener línea de reserva_servicio
                $stmt = $pdo->prepare("
                    SELECT id_reserva, incluido_en_plan
                    FROM reservas_servicios
                    WHERE id_reserva_servicio = :id
                    FOR UPDATE
                ");
                $stmt->execute([':id' => $id_rs]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    throw new Exception("Servicio extra no encontrado.");
                }

                if ((int)$row['incluido_en_plan'] === 1) {
                    throw new Exception("No se puede eliminar un servicio incluido en el plan.");
                }

                $id_reserva = (int)$row['id_reserva'];

                // Eliminar línea
                $stmtDel = $pdo->prepare("
                    DELETE FROM reservas_servicios
                    WHERE id_reserva_servicio = :id
                ");
                $stmtDel->execute([':id' => $id_rs]);

                // Recalcular extras y totales
                $stmtSum = $pdo->prepare("
                    SELECT COALESCE(SUM(total_linea),0) AS suma_extras
                    FROM reservas_servicios
                    WHERE id_reserva = :id_reserva
                      AND incluido_en_plan = 0
                ");
                $stmtSum->execute([':id_reserva' => $id_reserva]);
                $suma = $stmtSum->fetch(PDO::FETCH_ASSOC);
                $valor_extras = (int)$suma['suma_extras'];

                $stmtRes = $pdo->prepare("
                    SELECT valor_base, descuento_valor, pago_total
                    FROM reservas
                    WHERE id_reserva = :id_reserva
                    FOR UPDATE
                ");
                $stmtRes->execute([':id_reserva' => $id_reserva]);
                $r = $stmtRes->fetch(PDO::FETCH_ASSOC);
                if (!$r) {
                    throw new Exception("Reserva no encontrada al recalcular.");
                }

                $valor_base      = (int)$r['valor_base'];
                $descuento_valor = (int)$r['descuento_valor'];
                $pago_total      = (int)$r['pago_total'];

                $valor_total = $valor_base + $valor_extras - $descuento_valor;
                $saldo       = $valor_total - $pago_total;

                $stmtUpRes = $pdo->prepare("
                    UPDATE reservas
                    SET valor_extras = :ve,
                        valor_total  = :vt,
                        saldo        = :saldo
                    WHERE id_reserva = :id_reserva
                ");
                $stmtUpRes->execute([
                    ':ve'         => $valor_extras,
                    ':vt'         => $valor_total,
                    ':saldo'      => $saldo,
                    ':id_reserva' => $id_reserva,
                ]);

                $pdo->commit();

                // 🔁 PRG
                header("Location: check_in.php?id_reserva=" . $id_reserva . "&ok=extra_del");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $mensajeErr = "Error al eliminar el servicio extra: " . $e->getMessage();
            }
        }
    }
}

/**
 * 2) Buscar la reserva seleccionada
 */
$idReservaSeleccionada = 0;

// a) Si viene id_reserva por GET
if (isset($_GET['id_reserva']) && (int)$_GET['id_reserva'] > 0) {
    $idReservaSeleccionada = (int)$_GET['id_reserva'];
} else {
    // b) Buscar por código / cédula / nombre
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

// c) Si ya tenemos idReservaSeleccionada (por GET o por búsqueda), cargamos toda la info
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

    if (!$reserva) {
        $mensajeErr = "No se encontró la reserva seleccionada.";
    }
}

// d) Cargar catálogo de servicios activos
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

// 🔹 Construir listado de categorías para el filtro
$categoriasServicios = [];
foreach ($servicios as $srv) {
    $cat = trim((string)$srv['categoria']);
    if ($cat !== '' && !in_array($cat, $categoriasServicios, true)) {
        $categoriasServicios[] = $cat;
    }
}
sort($categoriasServicios);

// e) Cargar servicios ya asociados a la reserva (incluidos y extras)
if ($reserva) {
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

    // f) Cargar pagos de la reserva
    $stmtPagos = $pdo->prepare("
        SELECT num_pago, fecha, metodo, valor, nota
        FROM pagos
        WHERE id_reserva = :id_reserva
        ORDER BY fecha, num_pago
    ");
    $stmtPagos->execute([':id_reserva' => $reserva['id_reserva']]);
    $pagosReserva = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
}

// Función helper para formatear dinero
function mp_money($valor) {
    return '$' . number_format((int)$valor, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Check-in | Mystic Paradise</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
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

    /* TOPBAR estilo Mystic */
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
      border-color:#38bdf8;
      color:#e0f2fe;
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

<!-- 🎨 Tema oscuro azul + negro -->
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-50">

  <!-- 🔹 Encabezado Mystic (topbar) -->
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand">
        <!-- 🔙 Botón VOLVER a donde estaba antes -->
        <a href="javascript:window.history.back();" class="btn-top btn-top-back" title="Volver a la pantalla anterior">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M10 19l-7-7 7-7M3 12h18"/>
          </svg>
          Volver
        </a>

        <!-- 🧭 Botón para ir directo al menú de recepción -->
        <a href="../Vista/recepcion_menu.php" class="btn-top" title="Volver al menú de recepción">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/>
          </svg>
          Menú recepción
        </a>

        <div class="brand-logo"></div>
        <div class="brand-text">
          <span class="brand-title">MYSTIC PARADISE</span>
          <span class="brand-sub">Check-in de reservas</span>
        </div>
      </div>

      <div class="top-actions">
        <!-- Acceso rápido a disponibilidad -->
        <a href="disponibilidad.php" class="btn-top" title="Ver disponibilidad de alojamientos">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 4h16v4H4zM4 10h7v10H4zM13 10h7v6h-7zM13 18h7"/>
          </svg>
          Disponibilidad
        </a>

        <!-- Acceso rápido a reservas -->
        <a href="Ver_reservas.php" class="btn-top" title="Ver reservas">
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
            <span class="avatar-role"><?= htmlspecialchars($rol ?? '', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">

    <!-- Header interno de la página -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-2">
      <div>
        <h1 class="text-2xl font-semibold text-blue-400">Check-in de reservas</h1>
        <p class="text-sm text-slate-300 mt-1">
          Busca la reserva del cliente, revisa el saldo pendiente, agrega servicios adicionales y registra los pagos finales.
        </p>
      </div>

      <div class="flex flex-col items-end text-xs text-slate-400">
        <span>Usuario: <span class="text-slate-100 font-semibold"><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></span></span>
        <span>Rol: <span class="text-slate-100 font-semibold"><?= htmlspecialchars((string)$rol, ENT_QUOTES, 'UTF-8') ?></span></span>
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

    <!-- 1) Bloque de búsqueda -->
    <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-3">
      <h2 class="text-lg font-medium text-slate-50">1. Buscar reserva</h2>
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
        Puedes rellenar uno o varios campos. Si hay varias coincidencias, se mostrará la más reciente.
      </p>
    </section>

    <!-- 2) Bloque de resumen de reserva -->
    <?php if ($reserva): ?>
      <?php
        $saldo        = (int)$reserva['saldo'];
        $valor_base   = (int)$reserva['valor_base'];
        $valor_extras = (int)$reserva['valor_extras'];
        $valor_total  = (int)$reserva['valor_total'];
        $pago_total   = (int)$reserva['pago_total'];
      ?>
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

        <!-- Servicios ya asociados a la reserva -->
        <div class="mt-4">
          <h3 class="text-sm font-medium text-slate-50 mb-2">Servicios ya registrados en esta reserva</h3>
          <?php if (empty($extrasReserva)): ?>
            <p class="text-xs text-slate-400">Aún no hay servicios registrados para esta reserva.</p>
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
                    <th class="px-2 py-1 text-center">Acciones</th>
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
                      <td class="px-2 py-1 text-center">
                        <?php if ((int)$er['incluido_en_plan'] === 0): ?>
                          <!-- Formulario para actualizar cantidad 💾 -->
                          <form method="post" class="inline-flex items-center gap-1">
                            <input type="hidden" name="accion" value="actualizar_extra">
                            <input type="hidden" name="id_reserva_servicio" value="<?= (int)$er['id_reserva_servicio'] ?>">
                            <input type="number"
                                   name="nueva_cantidad"
                                   value="<?= (int)$er['cantidad'] ?>"
                                   min="1"
                                   class="w-14 rounded-md bg-slate-900 border border-slate-600 px-1 py-0.5 text-[11px] text-center text-slate-50 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <button type="submit"
                                    class="rounded-md bg-blue-600 hover:bg-blue-700 px-2 py-1 text-[11px] text-white"
                                    title="Actualizar cantidad">
                              💾
                            </button>
                          </form>

                          <!-- Formulario para eliminar extra 🗑️ -->
                          <form method="post"
                                class="inline-block ml-1"
                                onsubmit="return confirm('¿Eliminar este servicio extra de la reserva?');">
                            <input type="hidden" name="accion" value="eliminar_extra">
                            <input type="hidden" name="id_reserva_servicio" value="<?= (int)$er['id_reserva_servicio'] ?>">
                            <button type="submit"
                                    class="rounded-md bg-red-600 hover:bg-red-700 px-2 py-1 text-[11px] text-white"
                                    title="Eliminar extra">
                              🗑️
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="text-[11px] text-slate-500">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- 3) Bloque para agregar servicios extras -->
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <h2 class="text-lg font-medium text-slate-50">3. Agregar servicios adicionales (Check-in)</h2>
            <p class="text-xs text-slate-400">
              Marca los servicios que el huésped desea usar ahora; se suman al saldo pendiente.
            </p>
          </div>
          <!-- 🔍 Controles de filtro (nombre + categoría), útiles en celular -->
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
              <table class="min-w-full text-xs" id="tabla-servicios">
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
                        <input type="checkbox" name="extras[<?= (int)$s['id_servicio'] ?>][usar]" value="1"
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
                Al guardar, se actualizará el <strong class="text-slate-100">valor de extras</strong>, el <strong class="text-slate-100">total</strong> y el <strong class="text-slate-100">saldo</strong> de la reserva, y el estado pasará a <span class="font-semibold text-blue-400">CHECKIN</span>.
              </p>
              <button type="submit"
                      class="inline-flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-medium text-white">
                Guardar extras y actualizar saldo
              </button>
            </div>
          </form>
        <?php endif; ?>
      </section>

      <!-- 4) Bloque para registrar pagos -->
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm space-y-4">
        <div class="flex sales flex-col md:flex-row md:items-center md:justify-between gap-2">
          <div>
            <h2 class="text-lg font-medium text-slate-50">4. Registrar pago</h2>
            <p class="text-sm text-slate-300">
              Usa esta sección para cobrar el saldo pendiente al huésped (datáfono, transferencia, efectivo, etc.).
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
            <p class="text-[11px] text-slate-400 mt-1">
              Puedes cobrar el saldo completo o un abono parcial.
            </p>
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
              Registrar pago y actualizar saldo
            </button>
          </div>
        </form>

        <!-- Historial de pagos -->
        <div class="mt-3">
          <h3 class="text-sm font-medium text-slate-50 mb-2">Historial de pagos de esta reserva</h3>
          <?php if (empty($pagosReserva)): ?>
            <p class="text-xs text-slate-400">
              Aún no hay pagos registrados en la tabla de pagos. Es posible que el anticipo inicial se haya guardado solo en la reserva.
            </p>
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
    <?php else: ?>
      <section class="rounded-xl border border-slate-700 bg-slate-900/90 backdrop-blur-sm p-4 shadow-sm">
        <p class="text-sm text-slate-200">
          Busca una reserva para iniciar el proceso de Check-in.
        </p>
      </section>
    <?php endif; ?>

  </div>

  <!-- 🔍 Script para buscar y filtrar servicios (también en celular) -->
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
