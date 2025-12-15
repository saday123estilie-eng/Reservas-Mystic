<?php
// procesar_edicion_reserva.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    die("Sesión expirada.");
}

require_once __DIR__ . '/../config/conexion.php';

$idReserva = isset($_POST['id_reserva']) ? (int)$_POST['id_reserva'] : 0;

if ($idReserva <= 0) {
    die("ID de reserva inválido.");
}

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================
    // 1) Traer reserva + cliente
    // ==========================
    $sql = "
        SELECT
            r.*,
            c.id_cliente,
            c.nombre  AS nombre_cliente,
            c.cedula  AS cedula_cliente,
            c.whatsapp
        FROM reservas r
        INNER JOIN clientes c ON r.id_cliente = c.id_cliente
        WHERE r.id_reserva = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idReserva]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        throw new Exception("Reserva no encontrada.");
    }

    // ==========================
    // 2) Campos a auditar/editar
    // ==========================
    $camposEditables = [
        // CLIENTE
        'nombre_cliente',   // clientes.nombre
        'whatsapp',         // clientes.whatsapp

        // RESERVA
        'codigo',
        'fecha_ingreso',
        'noches',
        'adultos',
        'menores',
        'id_plan',
        'valor_total',
        'observaciones'
    ];

    $camposSoloAuditar = [
        'cedula_cliente',
        'plan_nombre',
        'valor_extras',
        'pago_total',
        'pago_efectivo',
        'pago_transferencia',
        'pago_datafono',
        'saldo'
    ];

    $camposParaAuditoria = array_merge($camposSoloAuditar, $camposEditables);

    // ==========================
    // 3) Construir ANTES / DESPUES base
    // ==========================
    $antes   = [];
    $despues = [];

    foreach ($camposParaAuditoria as $campo) {
        $antes[$campo] = $original[$campo] ?? null;

        if (in_array($campo, $camposEditables, true)) {
            $despues[$campo] = isset($_POST[$campo]) ? $_POST[$campo] : null;
        } else {
            $despues[$campo] = $original[$campo] ?? null;
        }
    }

    // Normalizar numéricos clave
    $despues['noches']      = (int)($despues['noches'] ?? $original['noches']);
    $despues['adultos']     = (int)($despues['adultos'] ?? $original['adultos']);
    $despues['menores']     = (int)($despues['menores'] ?? $original['menores']);
    $despues['id_plan']     = $despues['id_plan'] !== null && $despues['id_plan'] !== ''
        ? (int)$despues['id_plan']
        : (int)$original['id_plan'];

    $despues['valor_total'] = $despues['valor_total'] !== null && $despues['valor_total'] !== ''
        ? (int)$despues['valor_total']
        : (int)$original['valor_total'];

    // Valores base actuales
    $valorExtrasBase   = (int)$original['valor_extras'];
    $valorTotalBase    = (int)$despues['valor_total']; // lo que ponga el usuario en el formulario
    $pagoTotalBase     = (int)$original['pago_total'];
    $pagoEfectivoBase  = (int)$original['pago_efectivo'];
    $pagoTransfBase    = (int)$original['pago_transferencia'];
    $pagoDatafonoBase  = (int)$original['pago_datafono'];

    // ==========================
    // 4) Leer datos de nuevo servicio y nuevo pago
    // ==========================
    $nuevoServicioId       = isset($_POST['nuevo_servicio_id']) ? (int)$_POST['nuevo_servicio_id'] : 0;
    $nuevoServicioCantidad = isset($_POST['nuevo_servicio_cantidad']) ? (int)$_POST['nuevo_servicio_cantidad'] : 0;

    $nuevoPagoFecha  = isset($_POST['nuevo_pago_fecha'])  ? trim($_POST['nuevo_pago_fecha'])  : '';
    $nuevoPagoMetodo = isset($_POST['nuevo_pago_metodo']) ? trim($_POST['nuevo_pago_metodo']) : '';
    $nuevoPagoMonto  = isset($_POST['nuevo_pago_monto'])  ? (int)$_POST['nuevo_pago_monto']   : 0;
    $nuevoPagoNota   = isset($_POST['nuevo_pago_nota'])   ? trim($_POST['nuevo_pago_nota'])   : '';

    $hayNuevoServicio = $nuevoServicioId > 0 && $nuevoServicioCantidad > 0;
    $hayNuevoPago     = $nuevoPagoMonto > 0 && $nuevoPagoFecha !== '' && $nuevoPagoMetodo !== '';

    // ==========================
    // 5) Ver si cambió algo en campos base
    // ==========================
    $hayCambiosCampos = false;
    foreach ($camposParaAuditoria as $campo) {
        if (($antes[$campo] ?? null) != ($despues[$campo] ?? null)) {
            $hayCambiosCampos = true;
            break;
        }
    }

    if (!$hayCambiosCampos && !$hayNuevoServicio && !$hayNuevoPago) {
        header("Location: ../Vista/editar_reserva.php?id_reserva=" . $idReserva);
        exit;
    }

    // ==========================
    // 6) Comenzar transacción
    // ==========================
    $pdo->beginTransaction();

    $valorExtrasFinal  = $valorExtrasBase;
    $valorTotalFinal   = $valorTotalBase;
    $pagoTotalFinal    = $pagoTotalBase;
    $pagoEfectivoFinal = $pagoEfectivoBase;
    $pagoTransfFinal   = $pagoTransfBase;
    $pagoDatafonoFinal = $pagoDatafonoBase;

    // ==========================
    // 7) Nuevo servicio extra → reservas_servicios + valor_extras/valor_total
    // ==========================
    if ($hayNuevoServicio) {
        // Traer info del servicio
        $sqlServ = "
            SELECT nombre, precio_base
            FROM servicios
            WHERE id_servicio = :id
            LIMIT 1
        ";
        $stmtServ = $pdo->prepare($sqlServ);
        $stmtServ->execute([':id' => $nuevoServicioId]);
        $servRow = $stmtServ->fetch(PDO::FETCH_ASSOC);

        if (!$servRow) {
            throw new Exception("Servicio no encontrado para ID {$nuevoServicioId}");
        }

        $precioUnitario = (int)$servRow['precio_base'];
        $subtotalExtra  = $precioUnitario * $nuevoServicioCantidad;

        // Insertar en reservas_servicios (como extra, no incluido en plan)
        $sqlInsServ = "
            INSERT INTO reservas_servicios (
                id_reserva,
                id_servicio,
                cantidad,
                incluido_en_plan,
                precio_unit_aplicado,
                total_linea,
                incluidas
            ) VALUES (
                :id_reserva,
                :id_servicio,
                :cantidad,
                0,
                :precio_unit_aplicado,
                :total_linea,
                0
            )
        ";
        $stmtInsServ = $pdo->prepare($sqlInsServ);
        $stmtInsServ->execute([
            ':id_reserva'          => $idReserva,
            ':id_servicio'         => $nuevoServicioId,
            ':cantidad'            => $nuevoServicioCantidad,
            ':precio_unit_aplicado'=> $precioUnitario,
            ':total_linea'         => $subtotalExtra
        ]);

        // Ajustar totales de extras y total general
        $valorExtrasFinal += $subtotalExtra;
        $valorTotalFinal  += $subtotalExtra;
    }

    // ==========================
    // 8) Nuevo pago → pagos + pago_total + breakdown
    // ==========================
    if ($hayNuevoPago) {
        // Calcular num_pago consecutivo
        $sqlMaxNum = "SELECT COALESCE(MAX(num_pago), 0) AS max_num FROM pagos WHERE id_reserva = :id";
        $stmtMax = $pdo->prepare($sqlMaxNum);
        $stmtMax->execute([':id' => $idReserva]);
        $maxRow   = $stmtMax->fetch(PDO::FETCH_ASSOC);
        $nextNum  = ((int)$maxRow['max_num']) + 1;

        // Insertar pago
        $sqlInsPago = "
            INSERT INTO pagos (
                id_reserva,
                num_pago,
                fecha,
                metodo,
                valor,
                nota
            ) VALUES (
                :id_reserva,
                :num_pago,
                :fecha,
                :metodo,
                :valor,
                :nota
            )
        ";
        $stmtPago = $pdo->prepare($sqlInsPago);
        $stmtPago->execute([
            ':id_reserva' => $idReserva,
            ':num_pago'   => $nextNum,
            ':fecha'      => $nuevoPagoFecha,
            ':metodo'     => $nuevoPagoMetodo,
            ':valor'      => $nuevoPagoMonto,
            ':nota'       => $nuevoPagoNota
        ]);

        // Ajustar totales de pago
        $pagoTotalFinal += $nuevoPagoMonto;

        // Ajustar breakdown según método
        switch ($nuevoPagoMetodo) {
            case 'EFECTIVO':
                $pagoEfectivoFinal += $nuevoPagoMonto;
                break;
            case 'TRANSFERENCIA':
                $pagoTransfFinal += $nuevoPagoMonto;
                break;
            case 'DATÁFONO':
                $pagoDatafonoFinal += $nuevoPagoMonto;
                break;
            default:
                // OTRO: solo suma al pago_total
                break;
        }
    }

    // ==========================
    // 9) Calcular saldo final
    // ==========================
    $saldoFinal = $valorTotalFinal - $pagoTotalFinal;

    // Actualizar también en arreglo "despues" para auditoría
    $despues['valor_extras']       = $valorExtrasFinal;
    $despues['valor_total']        = $valorTotalFinal;
    $despues['pago_total']         = $pagoTotalFinal;
    $despues['pago_efectivo']      = $pagoEfectivoFinal;
    $despues['pago_transferencia'] = $pagoTransfFinal;
    $despues['pago_datafono']      = $pagoDatafonoFinal;
    $despues['saldo']              = $saldoFinal;

    // ==========================
    // 10) Actualizar CLIENTE (nombre, whatsapp)
    // ==========================
    $sqlUpdCliente = "
        UPDATE clientes
        SET nombre = :nombre, whatsapp = :whatsapp
        WHERE id_cliente = :id_cliente
    ";
    $stmtCli = $pdo->prepare($sqlUpdCliente);
    $stmtCli->execute([
        ':nombre'     => $despues['nombre_cliente'],
        ':whatsapp'   => $despues['whatsapp'],
        ':id_cliente' => $original['id_cliente']
    ]);

    // ==========================
    // 11) Actualizar plan_nombre según id_plan
    // ==========================
    $planNombreNuevo = $original['plan_nombre'];

    if (!empty($despues['id_plan'])) {
        $sqlPlan = "SELECT nombre FROM planes WHERE id_plan = :id LIMIT 1";
        $stmtPlan = $pdo->prepare($sqlPlan);
        $stmtPlan->execute([':id' => $despues['id_plan']]);
        $planRow = $stmtPlan->fetch(PDO::FETCH_ASSOC);
        if ($planRow) {
            $planNombreNuevo = $planRow['nombre'];
        }
    }

    $despues['plan_nombre'] = $planNombreNuevo;

    // ==========================
    // 12) Actualizar RESERVA
    // ==========================
    $sqlUpdReserva = "
        UPDATE reservas
        SET
            codigo        = :codigo,
            fecha_ingreso = :fecha_ingreso,
            noches        = :noches,
            adultos       = :adultos,
            menores       = :menores,
            id_plan       = :id_plan,
            plan_nombre   = :plan_nombre,
            valor_extras  = :valor_extras,
            valor_total   = :valor_total,
            pago_total    = :pago_total,
            pago_efectivo = :pago_efectivo,
            pago_transferencia = :pago_transferencia,
            pago_datafono = :pago_datafono,
            saldo         = :saldo,
            observaciones = :observaciones
        WHERE id_reserva = :id_reserva
    ";

    $stmtRes = $pdo->prepare($sqlUpdReserva);
    $stmtRes->execute([
        ':codigo'            => $despues['codigo'],
        ':fecha_ingreso'     => $despues['fecha_ingreso'],
        ':noches'            => $despues['noches'],
        ':adultos'           => $despues['adultos'],
        ':menores'           => $despues['menores'],
        ':id_plan'           => $despues['id_plan'],
        ':plan_nombre'       => $planNombreNuevo,
        ':valor_extras'      => $valorExtrasFinal,
        ':valor_total'       => $valorTotalFinal,
        ':pago_total'        => $pagoTotalFinal,
        ':pago_efectivo'     => $pagoEfectivoFinal,
        ':pago_transferencia'=> $pagoTransfFinal,
        ':pago_datafono'     => $pagoDatafonoFinal,
        ':saldo'             => $saldoFinal,
        ':observaciones'     => $despues['observaciones'],
        ':id_reserva'        => $idReserva
    ]);

    // ==========================
    // 13) Insertar AUDITORÍA
    // ==========================
    $sqlAud = "
        INSERT INTO reserva_auditoria (id_reserva, usuario_id, usuario_rol, cambios)
        VALUES (:id_reserva, :usuario_id, :usuario_rol, :cambios)
    ";

    $cambiosJson = json_encode(
        [
            'antes'   => $antes,
            'despues' => $despues,
            'extras'  => [
                'nuevo_servicio' => $hayNuevoServicio ? [
                    'id_servicio' => $nuevoServicioId,
                    'cantidad'    => $nuevoServicioCantidad
                ] : null,
                'nuevo_pago'    => $hayNuevoPago ? [
                    'fecha'  => $nuevoPagoFecha,
                    'metodo' => $nuevoPagoMetodo,
                    'monto'  => $nuevoPagoMonto,
                    'nota'   => $nuevoPagoNota
                ] : null
            ]
        ],
        JSON_UNESCAPED_UNICODE
    );

    $stmtAud = $pdo->prepare($sqlAud);
    $stmtAud->execute([
        ':id_reserva'  => $idReserva,
        ':usuario_id'  => $_SESSION['id_usuario'],
        ':usuario_rol' => $_SESSION['rol'] ?? 'DESCONOCIDO',
        ':cambios'     => $cambiosJson
    ]);

    // ==========================
    // 14) Commit y redirección
    // ==========================
    $pdo->commit();

    header("Location: ../Vista/editar_reserva.php?id_reserva=" . $idReserva);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "<pre style='color:#fca5a5'>Error al guardar la edición: "
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . "</pre>";
}
