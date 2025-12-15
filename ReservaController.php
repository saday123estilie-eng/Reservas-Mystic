<?php
// ReservaController.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

// Paso puede venir por POST (normal) o por GET (por si acaso)
$paso = $_POST['paso'] ?? $_GET['paso'] ?? 'pre_reserva';

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ===========================
    // PASO 1: PRE-RESERVA
    // ===========================
    if ($paso === 'pre_reserva' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        // Aseguramos que servicios_json exista
        if (empty($_POST['servicios_json'])) {
            $_POST['servicios_json'] = '[]';
        }

        // Guardamos TODO el formulario en sesión
        $_SESSION['reserva_draft'] = $_POST;

        // Tomamos la fecha de ingreso del formulario
        $fechaIngreso = isset($_POST['fecha_ingreso']) ? trim($_POST['fecha_ingreso']) : '';

        // URL hacia la vista de disponibilidad (está en /Vista/)
        $url = '../Vista/disponibilidad.php';

        // Si la fecha es válida, la mandamos por GET para que filtre
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIngreso)) {
            $url .= '?fecha=' . urlencode($fechaIngreso);
        }

        header('Location: ' . $url);
        exit;
    }

    // ===========================
    // PASO 2: CONFIRMAR E INSERTAR
    // ===========================
    if ($paso === 'confirmar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        // Si alguien llegó a confirmar sin pasar por el formulario,
        // lo mandamos de vuelta a registro_reserva.
        if (empty($_SESSION['reserva_draft'])) {
            $_SESSION['flash_ok'] = 'No se encontró pre-reserva en sesión. Vuelve a registrar la reserva.';
            header('Location: ../Controlador/CtRegistroReserva.php');
            exit;
        }

        $draft = $_SESSION['reserva_draft'];

        // Datos de alojamiento elegidos en disponibilidad.php
        $tipo_alojamiento   = isset($_POST['tipo_alojamiento']) ? $_POST['tipo_alojamiento'] : null;
        $numero_alojamiento = isset($_POST['numero_alojamiento']) ? (int)$_POST['numero_alojamiento'] : null;

        if (!$tipo_alojamiento || !$numero_alojamiento) {
            throw new RuntimeException('Debes seleccionar tipo y número de alojamiento.');
        }

        // =========
        // CLIENTE
        // =========
        $nombre   = trim($draft['nombre'] ?? '');
        $cedula   = trim($draft['cedula'] ?? '');
        $whatsapp = trim($draft['whatsapp'] ?? '');

        if ($nombre === '' || $cedula === '' || $whatsapp === '') {
            throw new RuntimeException('Faltan datos obligatorios del cliente.');
        }

        $correo    = trim($draft['correo'] ?? '');
        $ciudad    = trim($draft['ciudad'] ?? '');
        $cumple    = !empty($draft['cumple']) ? $draft['cumple'] : null;
        $edad      = !empty($draft['edad']) ? (int)$draft['edad'] : null;
        $estrato   = trim($draft['estrato'] ?? '');
        $profesion = trim($draft['profesion'] ?? '');
        $medio     = trim($draft['medio'] ?? '');

        // Buscar si cliente ya existe
        $sqlCli = "SELECT id_cliente FROM clientes WHERE cedula = :cedula LIMIT 1";
        $stCli  = $pdo->prepare($sqlCli);
        $stCli->execute([':cedula' => $cedula]);
        $id_cliente = $stCli->fetchColumn();

        if (!$id_cliente) {
            $sqlInsCli = "
              INSERT INTO clientes
                (nombre, cedula, whatsapp, correo, ciudad, cumple, edad, estrato, profesion, medio)
              VALUES
                (:nombre, :cedula, :whatsapp, :correo, :ciudad, :cumple, :edad, :estrato, :profesion, :medio)
            ";
            $stInsCli = $pdo->prepare($sqlInsCli);
            $stInsCli->execute([
                ':nombre'    => $nombre,
                ':cedula'    => $cedula,
                ':whatsapp'  => $whatsapp,
                ':correo'    => $correo ?: null,
                ':ciudad'    => $ciudad ?: null,
                ':cumple'    => $cumple ?: null,
                ':edad'      => $edad ?: null,
                ':estrato'   => $estrato ?: null,
                ':profesion' => $profesion ?: null,
                ':medio'     => $medio ?: null,
            ]);
            $id_cliente = $pdo->lastInsertId();
        }

        // =========
        // RESERVA
        // =========
        $id_plan = !empty($draft['plan']) ? (int)$draft['plan'] : null;
        $codigo  = trim($draft['codigo'] ?? '');
        if ($codigo === '') {
            $codigo = 'R-' . date('Ymd-His');
        }

        // nombre del plan (redundante)
        $plan_nombre = '';
        if ($id_plan) {
            $stPlan = $pdo->prepare("SELECT nombre FROM planes WHERE id_plan = :id");
            $stPlan->execute([':id' => $id_plan]);
            $plan_nombre = (string)$stPlan->fetchColumn();
        }

        $fecha_ingreso = $draft['fecha_ingreso'] ?? date('Y-m-d');
        $hora          = !empty($draft['hora']) ? $draft['hora'] : null;
        $noches        = (int)($draft['noches'] ?? 1);
        $adultos       = (int)($draft['adultos'] ?? 2);
        $menores       = (int)($draft['menores'] ?? 0);

        $precio_unit = (int)preg_replace('/\D/', '', $draft['precio_unit'] ?? '0');
        $valor_total = (int)preg_replace('/\D/', '', $draft['valor_total'] ?? '0');

        // pagos
        $pago1_valor = (int)preg_replace('/\D/', '', $draft['pago1_valor'] ?? '0');
        $pago2_valor = (int)preg_replace('/\D/', '', $draft['pago2_valor'] ?? '0');
        $pago3_valor = (int)preg_replace('/\D/', '', $draft['pago3_valor'] ?? '0');

        $pago_total         = $pago1_valor + $pago2_valor + $pago3_valor;
        $pago_datafono      = (int)preg_replace('/\D/', '', $draft['pago_datafono'] ?? '0');
        $pago_transferencia = (int)preg_replace('/\D/', '', $draft['pago_transferencia'] ?? '0');
        $pago_efectivo      = (int)preg_replace('/\D/', '', $draft['pago_efectivo'] ?? '0');

        $saldo = max($valor_total - $pago_total, 0);

        $parqueadero   = trim($draft['parqueadero'] ?? '');
        $agente        = trim($draft['agente'] ?? '');
        $observaciones = trim($draft['observaciones'] ?? '');

        // Descuento
        $descuento_porcentaje = (int)($draft['descuento'] ?? 0);
        $descuento_valor      = 0; // si quieres, aquí calculas el valor según tu lógica

        // Servicios extras desde JSON
        $valor_extras   = 0;
        $servicios_json = $draft['servicios_json'] ?? '[]';
        $servicios      = json_decode($servicios_json, true);
        if (is_array($servicios)) {
            foreach ($servicios as $svc) {
                $valor_extras += (int)($svc['valor_extra'] ?? 0);
            }
        }

        $valor_base = max($valor_total - $valor_extras, 0);

        // Usuario logueado
        $id_usuario = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;

        // Transacción: reserva + servicios + pagos
        $pdo->beginTransaction();

        $sqlRes = "
          INSERT INTO reservas
          (
            id_cliente, id_plan, tipo_alojamiento, numero_alojamiento,
            codigo, plan_nombre,
            fecha_ingreso, hora, noches, adultos, menores,
            precio_unit, valor_base, valor_extras,
            descuento_porcentaje, descuento_valor,
            valor_total, saldo,
            parqueadero, medio, agente, observaciones,
            pago_total, pago_efectivo, pago_transferencia, pago_datafono,
            id_usuario, estado
          )
          VALUES
          (
            :id_cliente, :id_plan, :tipo_alojamiento, :numero_alojamiento,
            :codigo, :plan_nombre,
            :fecha_ingreso, :hora, :noches, :adultos, :menores,
            :precio_unit, :valor_base, :valor_extras,
            :descuento_porcentaje, :descuento_valor,
            :valor_total, :saldo,
            :parqueadero, :medio, :agente, :observaciones,
            :pago_total, :pago_efectivo, :pago_transferencia, :pago_datafono,
            :id_usuario, 'RESERVADA'
          )
        ";

        $stRes = $pdo->prepare($sqlRes);
        $stRes->execute([
            ':id_cliente'           => $id_cliente,
            ':id_plan'              => $id_plan,
            ':tipo_alojamiento'     => $tipo_alojamiento,
            ':numero_alojamiento'   => $numero_alojamiento,
            ':codigo'               => $codigo,
            ':plan_nombre'          => $plan_nombre,
            ':fecha_ingreso'        => $fecha_ingreso,
            ':hora'                 => $hora,
            ':noches'               => $noches,
            ':adultos'              => $adultos,
            ':menores'              => $menores,
            ':precio_unit'          => $precio_unit,
            ':valor_base'           => $valor_base,
            ':valor_extras'         => $valor_extras,
            ':descuento_porcentaje' => $descuento_porcentaje,
            ':descuento_valor'      => $descuento_valor,
            ':valor_total'          => $valor_total,
            ':saldo'                => $saldo,
            ':parqueadero'          => $parqueadero ?: null,
            ':medio'                => $medio ?: null,
            ':agente'               => $agente ?: null,
            ':observaciones'        => $observaciones ?: null,
            ':pago_total'           => $pago_total,
            ':pago_efectivo'        => $pago_efectivo,
            ':pago_transferencia'   => $pago_transferencia,
            ':pago_datafono'        => $pago_datafono,
            ':id_usuario'           => $id_usuario,
        ]);

        $id_reserva = $pdo->lastInsertId();

        // ===========================
        // RESERVA_SERVICIOS
        // ===========================
        if (is_array($servicios) && !empty($servicios)) {
            $sqlRS = "
              INSERT INTO reservas_servicios
                (id_reserva, id_servicio, cantidad, incluidas, incluido_en_plan, precio_unit_aplicado, total_linea)
              VALUES
                (:id_reserva, :id_servicio, :cantidad, :incluidas, :incluido_en_plan, :precio_unit_aplicado, :total_linea)
            ";
            $stRS = $pdo->prepare($sqlRS);

            foreach ($servicios as $svc) {
                $id_servicio      = (int)($svc['id_servicio'] ?? 0);
                $cant_total       = (int)($svc['cantidad_total'] ?? 0);
                $incluidas        = (int)($svc['cantidad_incluida'] ?? 0);
                $cant_extra       = (int)($svc['cantidad_extra'] ?? 0);
                $precio_unit      = (int)($svc['precio_unit'] ?? 0);
                $valor_extra      = (int)($svc['valor_extra'] ?? 0);
                $incluido_en_plan = $cant_extra > 0 ? 0 : 1;

                if ($cant_total <= 0 || $id_servicio <= 0) continue;

                $stRS->execute([
                    ':id_reserva'           => $id_reserva,
                    ':id_servicio'          => $id_servicio,
                    ':cantidad'             => $cant_total,
                    ':incluidas'            => $incluidas,
                    ':incluido_en_plan'     => $incluido_en_plan,
                    ':precio_unit_aplicado' => $precio_unit,
                    ':total_linea'          => $valor_extra,
                ]);
            }
        }

        // ===========================
        // PAGOS
        // ===========================
        $sqlPago = "
          INSERT INTO pagos
            (id_reserva, num_pago, fecha, metodo, valor, nota)
          VALUES
            (:id_reserva, :num_pago, :fecha, :metodo, :valor, :nota)
        ";
        $stPago = $pdo->prepare($sqlPago);

        if ($pago1_valor > 0 && !empty($draft['pago1_fecha'])) {
            $stPago->execute([
                ':id_reserva' => $id_reserva,
                ':num_pago'   => 1,
                ':fecha'      => $draft['pago1_fecha'],
                ':metodo'     => $draft['pago1_metodo'] ?? 'EFECTIVO',
                ':valor'      => $pago1_valor,
                ':nota'       => '',
            ]);
        }
        if ($pago2_valor > 0 && !empty($draft['pago2_fecha'])) {
            $stPago->execute([
                ':id_reserva' => $id_reserva,
                ':num_pago'   => 2,
                ':fecha'      => $draft['pago2_fecha'],
                ':metodo'     => $draft['pago2_metodo'] ?? 'EFECTIVO',
                ':valor'      => $pago2_valor,
                ':nota'       => '',
            ]);
        }
        if ($pago3_valor > 0 && !empty($draft['pago3_fecha'])) {
            $stPago->execute([
                ':id_reserva' => $id_reserva,
                ':num_pago'   => 3,
                ':fecha'      => $draft['pago3_fecha'],
                ':metodo'     => $draft['pago3_metodo'] ?? 'EFECTIVO',
                ':valor'      => $pago3_valor,
                ':nota'       => '',
            ]);
        }

        $pdo->commit();

        // Limpiar draft
        unset($_SESSION['reserva_draft']);

        // Mensaje para el toast en registro_reserva.php
        $_SESSION['flash_ok'] = "Reserva registrada para {$nombre} ({$codigo}).";

        // Volvemos al formulario de registro para seguir creando reservas
        header('Location: ../Controlador/CtRegistroReserva.php');
        exit;
    }

    // Si llega un paso desconocido o sin POST válido: mandar al formulario
    header('Location: ../Controlador/CtRegistroReserva.php');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<pre style='color:red'>Error en ReservaController: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
