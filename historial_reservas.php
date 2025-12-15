<?php
// historial_reservas.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

$q      = isset($_GET['q']) ? trim($_GET['q']) : '';
$accion = isset($_GET['accion']) ? trim($_GET['accion']) : '';

$registros    = [];
$error        = '';
$serviciosMap = [];

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==============================
    // 1) CARGAR REGISTROS AUDITORÍA
    // ==============================
    $where  = [];
    $params = [];

    // Filtro por búsqueda (código, cliente, cédula)
    if ($q !== '') {
        $where[] = "(r.codigo LIKE :q
                     OR c.nombre LIKE :q
                     OR c.cedula LIKE :q)";
        $params[':q'] = "%{$q}%";
    }

    // Filtro por acción (ACTUALIZACION / CANCELACION / CREACION)
    if ($accion !== '' && in_array($accion, ['ACTUALIZACION', 'CANCELACION', 'CREACION'], true)) {
        $where[]           = "a.accion = :accion";
        $params[':accion'] = $accion;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    $sql = "
        SELECT
            a.id_auditoria,
            a.id_reserva,
            a.usuario_id,
            a.usuario_rol,
            a.accion,
            a.cambios,
            a.fecha,
            r.codigo,
            r.plan_nombre,
            r.estado,
            r.fecha_ingreso,
            c.nombre AS nombre_cliente,
            c.cedula AS cedula_cliente,
            u.nombre AS nombre_usuario
        FROM reserva_auditoria a
        INNER JOIN reservas r ON a.id_reserva = r.id_reserva
        INNER JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON a.usuario_id = u.id_usuario
        $whereSql
        ORDER BY a.fecha DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==============================
    // 2) PREFETCH SERVICIOS (id → info)
    // ==============================
    $stmtSrv = $pdo->query("SELECT id_servicio, nombre, categoria, precio_base FROM servicios");
    while ($srv = $stmtSrv->fetch(PDO::FETCH_ASSOC)) {
        $serviciosMap[(int)$srv['id_servicio']] = $srv;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Mapeo de campos a etiquetas legibles
$etiquetasCampo = [
    'nombre_cliente'      => 'Nombre cliente',
    'cedula_cliente'      => 'Cédula',
    'whatsapp'            => 'WhatsApp',
    'codigo'              => 'Código reserva',
    'fecha_ingreso'       => 'Fecha de ingreso',
    'noches'              => 'Noches',
    'adultos'             => 'Adultos',
    'menores'             => 'Menores',
    'id_plan'             => 'Plan (ID)',
    'plan_nombre'         => 'Plan',
    'valor_total'         => 'Valor total',
    'valor_extras'        => 'Valor extras',
    'saldo'               => 'Saldo',
    'pago_total'          => 'Pago total',
    'pago_efectivo'       => 'Pago efectivo',
    'pago_transferencia'  => 'Pago transferencia',
    'pago_datafono'       => 'Pago datáfono',
    'observaciones'       => 'Observaciones',
    'estado'              => 'Estado',
];

// Campos que se muestran como dinero
$camposMoneda = [
    'valor_total',
    'valor_extras',
    'saldo',
    'pago_total',
    'pago_efectivo',
    'pago_transferencia',
    'pago_datafono',
];

// Formateador de valores para el historial
function formatearValorHistorial(string $campo, $valor, array $camposMoneda): string
{
    if ($valor === null || $valor === '') {
        return '—';
    }

    // Moneda
    if (in_array($campo, $camposMoneda, true)) {
        return '$' . number_format((float)$valor, 0, ',', '.');
    }

    // Numéricos simples
    if (is_numeric($valor) && !in_array($campo, $camposMoneda, true)) {
        return (string)$valor;
    }

    // Texto / fechas tal cual
    return (string)$valor;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de reservas | Mystic Paradise</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">

<div class="max-w-6xl mx-auto py-8 px-4">

    <!-- HEADER -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <a href="Ver_reservas.php"
               class="inline-flex items-center gap-2 rounded-full border border-blue-400 bg-slate-800/70 px-3 py-1.5 text-xs font-medium text-blue-100 hover:bg-slate-700 transition">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M11.53 4.47a.75.75 0 010 1.06L8.06 9l3.47 3.47a.75.75 0 11-1.06 1.06l-4-4a.75.75 0 010-1.06l4-4a.75.75 0 011.06 0z"
                              clip-rule="evenodd"/>
                    </svg>
                </span>
                <span>Volver a reservas</span>
            </a>

            <a href="admin_menu.php"
               class="inline-flex items-center gap-2 rounded-full border border-slate-500 bg-slate-800/70 px-3 py-1.5 text-xs font-medium text-slate-100 hover:bg-slate-700 transition">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 2a1 1 0 01.832.445l6 8.5A1 1 0 0116 12H4a1 1 0 01-.832-1.555l6-8.5A1 1 0 0110 2z" />
                        <path d="M4 13a1 1 0 000 2h12a1 1 0 100-2H4z" />
                    </svg>
                </span>
                <span>Volver al menú admin</span>
            </a>
        </div>

        <div class="text-right">
            <h1 class="text-lg font-bold text-blue-100">
                Historial de modificaciones y cancelaciones
            </h1>
            <p class="text-[11px] text-slate-400 mt-1">
                Aquí ves cambios de datos, pagos y servicios extra de cada reserva.
            </p>
        </div>
    </div>

    <!-- FILTROS -->
    <section class="bg-slate-800/80 border border-blue-500/40 rounded-2xl p-4 mb-6">
        <form method="get" class="flex flex-col md:flex-row gap-3 md:items-end">
            <div class="flex-1">
                <label class="block text-xs mb-1 text-slate-400">Buscar</label>
                <input
                    type="text"
                    name="q"
                    value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Código, nombre cliente o cédula"
                    class="w-full rounded-xl bg-slate-900/60 border border-slate-600 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <div>
                <label class="block text-xs mb-1 text-slate-400">Acción</label>
                <select
                    name="accion"
                    class="rounded-xl bg-slate-900/60 border border-slate-600 px-3 py-2 text-sm"
                >
                    <option value="">Todas</option>
                    <option value="ACTUALIZACION" <?php echo $accion === 'ACTUALIZACION' ? 'selected' : ''; ?>>
                        Solo modificaciones
                    </option>
                    <option value="CANCELACION" <?php echo $accion === 'CANCELACION' ? 'selected' : ''; ?>>
                        Solo cancelaciones
                    </option>
                    <option value="CREACION" <?php echo $accion === 'CREACION' ? 'selected' : ''; ?>>
                        Solo creaciones
                    </option>
                </select>
            </div>

            <button
                type="submit"
                class="rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow"
            >
                Filtrar
            </button>
        </form>
    </section>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded-xl bg-red-900/40 border border-red-500/60 text-sm text-red-100">
            Error: <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($registros)): ?>
        <p class="text-sm text-slate-300">No hay registros de historial para los filtros actuales.</p>
    <?php else: ?>

        <div class="overflow-x-auto text-xs">
            <table class="min-w-full border border-slate-700 rounded-xl overflow-hidden">
                <thead class="bg-slate-900/80 text-slate-300">
                <tr>
                    <th class="px-3 py-2 text-left">Fecha</th>
                    <th class="px-3 py-2 text-left">Reserva</th>
                    <th class="px-3 py-2 text-left">Cliente</th>
                    <th class="px-3 py-2 text-left">Acción</th>
                    <th class="px-3 py-2 text-left">Usuario</th>
                    <th class="px-3 py-2 text-left">Cambios</th>
                    <th class="px-3 py-2 text-center">Ver</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($registros as $row): ?>
                    <?php
                    // Color según acción
                    $bg = '';
                    if ($row['accion'] === 'CANCELACION') {
                        $bg = 'bg-red-900/30';
                    } elseif ($row['accion'] === 'ACTUALIZACION') {
                        $bg = 'bg-amber-900/20';
                    } elseif ($row['accion'] === 'CREACION') {
                        $bg = 'bg-emerald-900/20';
                    }

                    // Decodificar JSON de cambios
                    $cambios = json_decode($row['cambios'], true);

                    $servicioNuevo = null;
                    $pagoNuevo     = null;
                    $infoServicio  = null;
                    $diffCampos    = [];

                    if (is_array($cambios)) {

                        // --------- datos base antes/después ---------
                        $antes   = isset($cambios['antes']) && is_array($cambios['antes']) ? $cambios['antes'] : [];
                        $despues = isset($cambios['despues']) && is_array($cambios['despues']) ? $cambios['despues'] : [];

                        // --------- servicio extra ---------
                        if (isset($cambios['extras']['nuevo_servicio']) && is_array($cambios['extras']['nuevo_servicio'])) {
                            $servicioNuevo = $cambios['extras']['nuevo_servicio'];

                            $idServ = isset($servicioNuevo['id_servicio']) ? (int)$servicioNuevo['id_servicio'] : null;
                            if ($idServ && isset($serviciosMap[$idServ])) {
                                $infoServicio = $serviciosMap[$idServ];
                            }
                        }

                        // --------- abono / pago nuevo ---------
                        if (isset($cambios['extras']['nuevo_pago']) && is_array($cambios['extras']['nuevo_pago'])) {
                            $pagoNuevo = $cambios['extras']['nuevo_pago'];
                        }

                        // --------- otros campos modificados ---------
                        if (!empty($despues)) {
                            $maxMostar = 8;

                            foreach ($despues as $campo => $nuevoValor) {
                                $valorAntes = $antes[$campo] ?? null;

                                // Solo mostrar si cambió
                                if ($valorAntes == $nuevoValor) {
                                    continue;
                                }

                                // Saltar campos técnicos
                                if (in_array($campo, ['actualizado_en', 'creado_en'], true)) {
                                    continue;
                                }

                                // Estos campos ya se explican en las tarjetas especiales;
                                // si quieres que NO se repitan abajo, déjalos aquí:
                                $camposExplicadosEnTarjetas = [
                                    'valor_extras',
                                    'saldo',
                                    'valor_total',
                                    'pago_total',
                                    'pago_efectivo',
                                    'pago_transferencia',
                                    'pago_datafono',
                                ];
                                if (in_array($campo, $camposExplicadosEnTarjetas, true)) {
                                    continue;
                                }

                                $label       = $etiquetasCampo[$campo] ?? $campo;
                                $valAntesFmt = formatearValorHistorial($campo, $valorAntes, $camposMoneda);
                                $valDespFmt  = formatearValorHistorial($campo, $nuevoValor, $camposMoneda);

                                $diffCampos[] = [
                                    'campo'   => $campo,
                                    'label'   => $label,
                                    'antes'   => $valAntesFmt,
                                    'despues' => $valDespFmt,
                                ];

                                if (count($diffCampos) >= $maxMostar) {
                                    $diffCampos[] = [
                                        'campo'   => '__more__',
                                        'label'   => 'Otros campos',
                                        'antes'   => '…',
                                        'despues' => '…',
                                    ];
                                    break;
                                }
                            }
                        }

                    } else {
                        // Ej: cancelación sin JSON estructurado
                        if ($row['accion'] === 'CANCELACION') {
                            $diffCampos[] = [
                                'campo'   => 'estado',
                                'label'   => 'Estado',
                                'antes'   => 'RESERVADA',
                                'despues' => 'CANCELADA',
                            ];
                        }
                    }

                    $nombreUsuario = $row['nombre_usuario'] ?: ('Rol: ' . ($row['usuario_rol'] ?: 'DESCONOCIDO'));
                    ?>
                    <tr class="border-t border-slate-800 <?php echo $bg; ?>">
                        <td class="px-3 py-2 align-top">
                            <div class="font-mono text-slate-200">
                                <?php echo htmlspecialchars($row['fecha']); ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <div class="font-mono text-blue-200">
                                #<?php echo (int)$row['id_reserva']; ?> · <?php echo htmlspecialchars($row['codigo']); ?>
                            </div>
                            <div class="text-[11px] text-slate-400">
                                Plan: <?php echo htmlspecialchars($row['plan_nombre']); ?>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                Fecha ingreso: <?php echo htmlspecialchars($row['fecha_ingreso']); ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <div class="text-slate-100">
                                <?php echo htmlspecialchars($row['nombre_cliente']); ?>
                            </div>
                            <div class="text-[11px] text-slate-400">
                                CC: <?php echo htmlspecialchars($row['cedula_cliente']); ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold
                                <?php
                                echo $row['accion'] === 'CANCELACION'
                                    ? 'bg-red-500/20 text-red-200 border border-red-400/60'
                                    : ($row['accion'] === 'ACTUALIZACION'
                                        ? 'bg-amber-500/20 text-amber-100 border border-amber-400/60'
                                        : 'bg-emerald-500/20 text-emerald-100 border border-emerald-400/60');
                                ?>
                            ">
                                <?php echo htmlspecialchars($row['accion']); ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <div class="text-slate-100">
                                <?php echo htmlspecialchars($nombreUsuario); ?>
                            </div>
                        </td>

                        <!-- CAMBIOS: servicio extra, abono y otros campos -->
                        <td class="px-3 py-2 align-top text-slate-200 space-y-2">

                            <?php if ($servicioNuevo): ?>
                                <?php
                                $cantidad    = isset($servicioNuevo['cantidad']) ? (int)$servicioNuevo['cantidad'] : 1;
                                $idServ      = isset($servicioNuevo['id_servicio']) ? (int)$servicioNuevo['id_servicio'] : null;
                                $nombreServ  = $infoServicio['nombre']   ?? ('ID servicio ' . $idServ);
                                $categoria   = $infoServicio['categoria'] ?? '';
                                $precioBase  = isset($infoServicio['precio_base']) ? (int)$infoServicio['precio_base'] : 0;
                                $subtotalEst = $precioBase * max(1, $cantidad);

                                $extrasAntes = isset($cambios['antes']['valor_extras']) ? (int)$cambios['antes']['valor_extras'] : null;
                                $extrasDesp  = isset($cambios['despues']['valor_extras']) ? (int)$cambios['despues']['valor_extras'] : null;
                                $totAntes    = isset($cambios['antes']['valor_total']) ? (int)$cambios['antes']['valor_total'] : null;
                                $totDesp     = isset($cambios['despues']['valor_total']) ? (int)$cambios['despues']['valor_total'] : null;
                                ?>
                                <!-- SERVICIO EXTRA AGREGADO -->
                                <div class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2">
                                    <div class="text-[11px] font-semibold text-emerald-300 mb-1">
                                        🎁 Servicio extra agregado
                                    </div>
                                    <div class="text-[11px] text-slate-200 space-y-0.5">
                                        <?php if ($categoria): ?>
                                            <div>
                                                <span class="text-slate-400">Categoría:</span>
                                                <?php echo htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <span class="text-slate-400">Servicio:</span>
                                            <?php echo htmlspecialchars($nombreServ, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <span class="text-slate-400">Cantidad:</span>
                                            <?php echo $cantidad; ?>
                                        </div>
                                        <div>
                                            <span class="text-slate-400">Precio unitario:</span>
                                            <?php echo '$' . number_format($precioBase, 0, ',', '.'); ?>
                                        </div>
                                        <div>
                                            <span class="text-slate-400">Subtotal estimado:</span>
                                            <span class="font-semibold text-emerald-200">
                                                <?php echo '$' . number_format($subtotalEst, 0, ',', '.'); ?>
                                            </span>
                                        </div>

                                        <?php if ($extrasAntes !== null && $extrasDesp !== null): ?>
                                            <div class="mt-1">
                                                <span class="text-slate-400">Valor extras:</span>
                                                <span class="line-through text-slate-500 mr-1">
                                                    <?php echo '$' . number_format($extrasAntes, 0, ',', '.'); ?>
                                                </span>
                                                <span class="font-semibold text-emerald-200">
                                                    <?php echo '$' . number_format($extrasDesp, 0, ',', '.'); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($totAntes !== null && $totDesp !== null): ?>
                                            <div>
                                                <span class="text-slate-400">Total reserva:</span>
                                                <span class="line-through text-slate-500 mr-1">
                                                    <?php echo '$' . number_format($totAntes, 0, ',', '.'); ?>
                                                </span>
                                                <span class="font-semibold text-emerald-200">
                                                    <?php echo '$' . number_format($totDesp, 0, ',', '.'); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($pagoNuevo): ?>
                                <?php
                                $monto      = isset($pagoNuevo['monto']) ? (int)$pagoNuevo['monto'] : 0;
                                $fechaPago  = $pagoNuevo['fecha'] ?? '';
                                $metodoPago = $pagoNuevo['metodo'] ?? '';
                                $notaPago   = $pagoNuevo['nota'] ?? '';

                                $saldoAntes = isset($cambios['antes']['saldo']) ? (int)$cambios['antes']['saldo'] : null;
                                $saldoDesp  = isset($cambios['despues']['saldo']) ? (int)$cambios['despues']['saldo'] : null;
                                $pagoTAntes = isset($cambios['antes']['pago_total']) ? (int)$cambios['antes']['pago_total'] : null;
                                $pagoTDesp  = isset($cambios['despues']['pago_total']) ? (int)$cambios['despues']['pago_total'] : null;
                                ?>
                                <!-- ABONO / PAGO NUEVO -->
                                <div class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2">
                                    <div class="text-[11px] font-semibold text-sky-300 mb-1">
                                        💰 Nuevo abono registrado
                                    </div>
                                    <div class="text-[11px] text-slate-200 space-y-0.5">
                                        <?php if ($fechaPago): ?>
                                            <div>
                                                <span class="text-slate-400">Fecha:</span>
                                                <?php echo htmlspecialchars($fechaPago, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($metodoPago): ?>
                                            <div>
                                                <span class="text-slate-400">Método:</span>
                                                <?php echo htmlspecialchars($metodoPago, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <span class="text-slate-400">Monto:</span>
                                            <span class="font-semibold text-sky-200">
                                                <?php echo '$' . number_format($monto, 0, ',', '.'); ?>
                                            </span>
                                        </div>
                                        <?php if ($notaPago !== ''): ?>
                                            <div>
                                                <span class="text-slate-400">Nota:</span>
                                                <?php echo htmlspecialchars($notaPago, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($saldoAntes !== null && $saldoDesp !== null): ?>
                                            <div class="mt-1">
                                                <span class="text-slate-400">Saldo:</span>
                                                <span class="line-through text-slate-500 mr-1">
                                                    <?php echo '$' . number_format($saldoAntes, 0, ',', '.'); ?>
                                                </span>
                                                <span class="font-semibold text-sky-200">
                                                    <?php echo '$' . number_format($saldoDesp, 0, ',', '.'); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($pagoTAntes !== null && $pagoTDesp !== null): ?>
                                            <div>
                                                <span class="text-slate-400">Pago total:</span>
                                                <span class="line-through text-slate-500 mr-1">
                                                    <?php echo '$' . number_format($pagoTAntes, 0, ',', '.'); ?>
                                                </span>
                                                <span class="font-semibold text-sky-200">
                                                    <?php echo '$' . number_format($pagoTDesp, 0, ',', '.'); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($diffCampos)): ?>
                                <!-- OTROS CAMBIOS DE DATOS -->
                                <div class="rounded-lg bg-slate-900/60 border border-slate-800 px-3 py-2">
                                    <div class="text-[11px] font-semibold text-amber-200 mb-1">
                                        ✏️ Otros cambios en la reserva
                                    </div>
                                    <div class="space-y-1">
                                        <?php foreach ($diffCampos as $c): ?>
                                            <?php if ($c['campo'] === '__more__'): ?>
                                                <div class="text-[11px] text-slate-400 italic">
                                                    … hay más campos modificados.
                                                </div>
                                            <?php else: ?>
                                                <div class="rounded-md bg-slate-950/60 border border-slate-800 px-2 py-1.5">
                                                    <div class="text-[11px] text-slate-400 mb-0.5">
                                                        <?php echo htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                    <div class="flex items-center gap-1 text-[11px]">
                                                        <span class="flex-1 text-slate-500 line-through decoration-slate-700">
                                                            <?php echo htmlspecialchars($c['antes'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                        <span class="mx-1 text-slate-500">→</span>
                                                        <span class="flex-1 font-semibold text-slate-50">
                                                            <?php echo htmlspecialchars($c['despues'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$servicioNuevo && !$pagoNuevo && empty($diffCampos)): ?>
                                <span class="text-[11px] text-slate-400">Sin detalle de cambios.</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-3 py-2 align-top text-center">
                            <button
                                type="button"
                                onclick="toggleDetalle(<?php echo (int)$row['id_auditoria']; ?>)"
                                class="px-3 py-1 rounded-lg bg-slate-700 hover:bg-slate-600 text-[11px]"
                            >
                                Ver JSON
                            </button>
                        </td>
                    </tr>
                    <tr id="detalle-<?php echo (int)$row['id_auditoria']; ?>" class="hidden border-t border-slate-800">
                        <td colspan="7" class="px-3 py-2 bg-slate-950/70">
                            <pre class="text-[11px] text-slate-200 whitespace-pre-wrap">
<?php echo htmlspecialchars($row['cambios']); ?>
                            </pre>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<script>
    function toggleDetalle(id) {
        const row = document.getElementById('detalle-' + id);
        if (!row) return;
        row.classList.toggle('hidden');
    }
</script>

</body>
</html>
