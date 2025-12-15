<?php
// disponibilidad.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

// =========================
// SESIÓN / ROL
// =========================
$idUsuario = $_SESSION['id_usuario'] ?? null;
$rol       = $_SESSION['rol']        ?? null;

if (!$idUsuario) {
    header("Location: ../login.php");
    exit;
}

// URL para botón "Volver"
$menuUrl = '../index.php';
if ($rol === 'ADMIN') {
    // Para admin lo dejamos ir a reservas (listado)
    $menuUrl = 'reservas.php';
} elseif ($rol === 'ASESOR') {
    $menuUrl = '../asesor/menu.php';
} elseif ($rol === 'RECEPCION') {
    $menuUrl = '../recepcion/menu.php';
}

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Error de conexión: ' . htmlspecialchars($e->getMessage()));
}

/**
 * MAPEOS DE TIPO
 * Ajusta según cómo guardes el tipo en BD
 * (GLAMPING / CAMPING / HABITACION, etc.)
 */
function tipoToKey(string $tipo): string {
    $t = strtolower(trim($tipo));
    if (str_contains($t, 'glamp')) return 'glamping';
    if (str_contains($t, 'camp'))  return 'camping';
    return 'room'; // habitaciones
}
function keyToLabel(string $key): string {
    return match($key) {
        'glamping' => 'Glamping',
        'camping'  => 'Camping',
        default    => 'Habitación',
    };
}

// Para cuando todavía no uses la BD y quieras forzar cantidades:
$defaultCounts = [
    'glamping' => 16,
    'camping'  => 150,
    'room'     => 7, // SOLO 7 HABITACIONES
];

// Configuración específica de las habitaciones (fallback)
$roomMeta = [
    1 => ['capacidad' => 8, 'banio_privado' => false], // #1: 8 personas, sin baño privado
    2 => ['capacidad' => 2, 'banio_privado' => false], // #2: 2 personas, sin baño privado
    3 => ['capacidad' => 2, 'banio_privado' => false], // #3: 2 personas, sin baño privado
    4 => ['capacidad' => 2, 'banio_privado' => true ], // #4: 2 personas, baño privado
    5 => ['capacidad' => 2, 'banio_privado' => false], // #5: 2 personas, sin baño privado
    6 => ['capacidad' => 8, 'banio_privado' => true ], // #6: 8 personas, baño privado
    7 => ['capacidad' => 6, 'banio_privado' => true ], // #7: 6 personas, baño privado
];

// =========================
// 1) Cargar UNIDADES
// =========================
$data          = ['glamping' => [], 'camping' => [], 'room' => []];
$totals        = ['glamping' => 0, 'camping' => 0, 'room' => 0];
$indexByKeyNum = []; // Mapa [key][numero] => índice en $data[key]

try {
    /**
     * Suposición:
     *  - Tabla `alojamientos` con:
     *      id_alojamiento (PK)
     *      tipo           (GLAMPING / CAMPING / HABITACION)
     *      numero         (1,2,3,...)
     */
    $sqlUnidades = "
        SELECT 
            a.id_alojamiento,
            a.tipo,
            a.numero
        FROM alojamientos a
        ORDER BY a.tipo, a.numero
    ";
    $stmt = $pdo->query($sqlUnidades);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key   = tipoToKey($row['tipo']);
        $label = keyToLabel($key);

        $unit = [
            'internal_id' => (int)$row['id_alojamiento'],
            'id'          => (int)$row['numero'],   // número visible (1,2,3,...)
            'type'        => $label,
            'bookings'    => [],
        ];

        $index = count($data[$key]);
        $data[$key][]                     = $unit;
        $indexByKeyNum[$key][$unit['id']] = $index;
        $totals[$key]++;
    }
} catch (Exception $e) {
    // Si falla la consulta, seguimos con datos por defecto
}

// Si aún no tienes tabla de unidades, o está vacía, usamos fallback
if ($totals['glamping'] === 0 && $totals['camping'] === 0 && $totals['room'] === 0) {
    foreach ($defaultCounts as $key => $count) {
        for ($i = 1; $i <= $count; $i++) {
            $unit = [
                'internal_id' => $i,      // temporal
                'id'          => $i,
                'type'        => keyToLabel($key),
                'bookings'    => [],      // sin reservas => todo verde
            ];

            // Solo para habitaciones, añadimos capacidad y baño privado (fallback)
            if ($key === 'room' && isset($roomMeta[$i])) {
                $unit['capacity']      = $roomMeta[$i]['capacidad'];
                $unit['bathroom_priv'] = $roomMeta[$i]['banio_privado']; // boolean
            }

            $index          = count($data[$key]);
            $data[$key][]   = $unit;
            $indexByKeyNum[$key][$unit['id']] = $index;
        }
        $totals[$key] = $count;
    }
}

// =========================
// 2) Cargar RESERVAS
// =========================
//
// Ahora se basa en:
//  - reservas.tipo_alojamiento
//  - reservas.numero_alojamiento
//  - reservas.fecha_ingreso
//  - reservas.noches
// y NO en reservas.id_alojamiento
//
try {
    /**
     * Suposición:
     *  - Tabla `reservas`:
     *      tipo_alojamiento    (GLAMPING / CAMPING / HABITACION)
     *      numero_alojamiento  (1,2,3,...)
     *      fecha_ingreso       (YYYY-MM-DD)
     *      noches              (INT)
     *      estado              (RESERVADA / CHECKIN / CHECKOUT / CANCELADA, etc.)
     *      codigo, plan_nombre, adultos, menores, valor_total
     *  - Tabla `clientes`:
     *      id_cliente, nombre, whatsapp
     *
     *  Consideramos ocupadas todas las que NO están CANCELADAS NI en CHECKOUT.
     */
    $sqlReservas = "
        SELECT 
            r.tipo_alojamiento,
            r.numero_alojamiento,
            r.fecha_ingreso,
            r.noches,
            r.estado,
            r.id_reserva,
            r.codigo,
            r.plan_nombre,
            r.adultos,
            r.menores,
            r.valor_total,
            c.nombre   AS cliente_nombre,
            c.whatsapp AS cliente_whatsapp
        FROM reservas r
        JOIN clientes c ON c.id_cliente = r.id_cliente
        WHERE (r.estado IS NULL OR r.estado NOT IN ('CANCELADA','CHECKOUT'))
    ";
    $stmtR = $pdo->query($sqlReservas);

    while ($row = $stmtR->fetch(PDO::FETCH_ASSOC)) {
        $tipoBD = $row['tipo_alojamiento'] ?? '';
        $num    = (int)($row['numero_alojamiento'] ?? 0);
        $key    = tipoToKey($tipoBD);

        if ($num <= 0 || !isset($data[$key]) || empty($data[$key])) {
            continue; // tipo o número no mapeado
        }

        if (!isset($indexByKeyNum[$key][$num])) {
            continue; // no existe esa unidad con ese número en el panel
        }

        $idx = $indexByKeyNum[$key][$num];

        $start  = $row['fecha_ingreso'];
        $noches = (int)($row['noches'] ?? 1);
        if ($noches <= 0) $noches = 1;

        // Calcular fecha_salida = fecha_ingreso + noches
        try {
            $dt = new DateTime($start);
            $dt->modify('+' . $noches . ' day');
            $end = $dt->format('Y-m-d');
        } catch (Exception $e) {
            // Si falla el parseo, asumimos +1 día
            $end = $start;
        }

        $data[$key][$idx]['bookings'][] = [
            'start'            => $start,                    // YYYY-MM-DD
            'end'              => $end,                      // YYYY-MM-DD (checkout)
            'id_reserva'       => (int)$row['id_reserva'],
            'codigo'           => $row['codigo'] ?? '',
            'plan_nombre'      => $row['plan_nombre'] ?? '',
            'estado'           => $row['estado'] ?? '',
            'cliente_nombre'   => $row['cliente_nombre'] ?? '',
            'cliente_whatsapp' => $row['cliente_whatsapp'] ?? '',
            'adultos'          => (int)($row['adultos'] ?? 0),
            'menores'          => (int)($row['menores'] ?? 0),
            'valor_total'      => (int)($row['valor_total'] ?? 0),
            'noches'           => $noches,
        ];
    }
} catch (Exception $e) {
    // Si falla, simplemente no hay reservas cargadas
}

// =========================
// 3) Parámetros de preselección
// =========================
//
// Ejemplo posible (no obligatorio):
// disponibilidad.php?tipo=glamping&unidad=3&desde=2025-12-10&hasta=2025-12-12
//
$preTipo   = isset($_GET['tipo'])   ? strtolower(trim($_GET['tipo'])) : '';
$preUnidad = isset($_GET['unidad']) ? (int)$_GET['unidad']            : 0;
$preDesde  = $_GET['desde'] ?? '';
$preHasta  = $_GET['hasta'] ?? '';

// Si vienes desde registro_reserva.php?fecha=YYYY-MM-DD
$preFecha = $_GET['fecha'] ?? '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $preFecha) && $preDesde === '' && $preHasta === '') {
    $preDesde = $preFecha;
    $dt = new DateTime($preFecha);
    $dt->modify('+1 day');
    $preHasta = $dt->format('Y-m-d');
}

// Normalizamos tipo a glamping / camping / room
switch ($preTipo) {
    case 'glamping':
    case 'glampings':
        $preTipoKey = 'glamping';
        break;
    case 'camping':
    case 'campings':
        $preTipoKey = 'camping';
        break;
    case 'room':
    case 'habitacion':
    case 'habitaciones':
        $preTipoKey = 'room';
        break;
    default:
        $preTipoKey = '';
}

// Seguridad básica para fechas
$preDesde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $preDesde) ? $preDesde : '';
$preHasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $preHasta) ? $preHasta : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Disponibilidad de Alojamientos</title>
<style>
  :root{
    /* PALETA DARK + AZUL */
    --bg:#020617;
    --card:#020617;
    --ink:#e5e7eb;
    --muted:#9ca3af;

    --brand:#2563eb;
    --brand-2:#38bdf8;
    --brand-3:#6366f1;

    --ok-bg:#022c22;
    --ok-bd:#064e3b;
    --ok:#6ee7b7;

    --bad-bg:#3f1f1f;
    --bad-bd:#7f1d1d;
    --bad:#fecaca;

    --ring:0 0 0 3px rgba(56,189,248,.35);
    --shadow-1:0 2px 10px rgba(0,0,0,.45);
    --shadow-2:0 18px 45px rgba(0,0,0,.75);
    --radius:16px;

    --brand-grad: linear-gradient(90deg,#1d4ed8 0%,#2563eb 30%,#38bdf8 65%,#6366f1 100%);
    --brand-logo-url: url("https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png");
  }

  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
    color:var(--ink);
    background:
      radial-gradient(1200px 800px at 10% -10%, rgba(37,99,235,.25), transparent 70%),
      radial-gradient(900px 700px at 100% 0%, rgba(56,189,248,.2), transparent 70%),
      linear-gradient(180deg,#020617 0%, #020617 100%);
  }

  .app{ min-height:100dvh; position:relative; }
  .main{ max-width:1200px; margin:0 auto; padding:12px 10px 40px; }

  /* -------- TOPBAR -------- */
  .topbar{
    position:sticky; top:0; z-index:10000;
    background:rgba(15,23,42,.96);
    backdrop-filter: blur(14px);
    border-bottom:1px solid #1f2937;
  }
  .topbar::before{
    content:""; position:absolute; left:0; right:0; top:0; height:3px; background:var(--brand-grad);
  }
  .topbar-inner{
    max-width:1200px; margin:0 auto; padding:10px 12px;
    display:grid; grid-template-columns:1fr auto; gap:12px; align-items:center;
  }

  .topbar-left{
    display:flex; align-items:center; gap:14px; flex-wrap:wrap;
  }

  .brand-chip{
    display:flex; align-items:center; gap:10px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid #1f2937;
    background:rgba(15,23,42,.9);
    box-shadow:var(--shadow-1);
  }
  .brand-mark{
    width:32px;height:32px;border-radius:10px;overflow:hidden;display:grid;place-items:center;
    background:#020617;border:1px solid #1f2937; box-shadow:var(--shadow-1);
  }
  .brand-mark::before{
    content:""; width:100%; height:100%;
    background-image:var(--brand-logo-url); background-size:contain; background-repeat:no-repeat; background-position:center;
    display:block;
  }
  .brand-text{
    font-weight:900;
    letter-spacing:.3px;
    color:#e5f2ff;
    font-size:13px;
  }
  .crumb{
    font-size:12px;
    color:#64748b;
    margin-top:1px;
  }

  .page-title{
    display:flex; flex-direction:column;
  }
  .page-title h1{
    margin:0;
    font-size: clamp(18px, 2.2vw, 22px);
    font-weight:800;
    color:#f9fafb;
  }
  .page-title span{
    font-size:12px;
    color:#64748b;
  }

  .actions{
    display:flex; gap:10px; align-items:center;
  }
  .btn{
    border-radius:999px;
    padding:8px 14px;
    font-weight:700;
    letter-spacing:.2px;
    cursor:pointer;
    user-select:none;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    box-shadow:var(--shadow-1);
    font-size:12px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    transition:background .18s ease,border-color .18s ease,transform .12s ease;
    text-decoration:none;
  }
  .btn svg{width:14px;height:14px}
  .btn:focus{outline:none; box-shadow:var(--ring),var(--shadow-1)}
  .btn:hover{
    background:#020617;
    border-color:#2563eb;
    transform:translateY(-1px);
  }

  .profile-chip{
    border-radius:999px;
    padding:4px 8px 4px 4px;
    border:1px solid #1f2937;
    background:rgba(15,23,42,.95);
    box-shadow:var(--shadow-1);
    display:flex;
    align-items:center;
    gap:8px;
    cursor:pointer;
  }
  .profile-avatar{
    width:30px; height:30px;
    border-radius:999px;
    background:linear-gradient(135deg,#38bdf8,#6366f1);
    display:grid; place-items:center;
    color:#f9fafb;
    font-weight:800;
    font-size:14px;
  }
  .profile-meta{
    display:flex; flex-direction:column;
  }
  .profile-name{
    font-size:12px;
    font-weight:700;
    color:#e5e7eb;
  }
  .profile-role{
    font-size:11px;
    color:#64748b;
  }

  .hero{
    background: radial-gradient(circle at 0% 0%, rgba(56,189,248,.18), transparent 60%),
                linear-gradient(145deg, rgba(15,23,42,.95), rgba(15,23,42,.98));
    border:1px solid #1f2937;
    border-radius:22px;
    box-shadow:var(--shadow-2);
    padding:18px 20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin:12px 0 14px 0;
  }
  .hero-title{
    margin:0;
    letter-spacing:.2px;
    font-size: clamp(22px, 2.6vw, 30px);
    color:#f9fafb;
  }
  .hero-sub{
    margin:.3rem 0 0;
    color: var(--muted);
    font-size:14px;
  }
  .badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 10px;
    border-radius:999px;
    background:#020617;
    border:1px solid #1f2937;
    box-shadow: var(--shadow-1);
    font-size:12.5px;
    color:#e5e7eb;
    font-weight:700;
  }
  .badge svg{ width:16px; height:16px; }

  .summary{
    display:grid;
    grid-template-columns: repeat(3,1fr);
    gap:12px;
    margin-bottom:12px;
  }
  .card{
    background:radial-gradient(circle at 0% 0%, rgba(37,99,235,.2), transparent 55%),
               #020617;
    border-radius: var(--radius);
    border:1px solid #1f2937;
    box-shadow: var(--shadow-1);
    padding:16px;
  }
  .kpi-top{ display:flex; align-items:center; justify-content:space-between; gap:10px }
  .kpi-left{ display:flex; align-items:center; gap:12px }
  .kpi-ico{
    width:40px; height:40px;
    border-radius:12px;
    background:radial-gradient(circle at 0% 0%, rgba(56,189,248,.35), transparent 70%);
    display:grid; place-items:center;
    border:1px solid #1d4ed8;
  }
  .kpi-ico svg{ width:20px; height:20px; color: var(--brand-2) }
  .kpi-title{ margin:0; font-size:16px; letter-spacing:.2px; color:#e5e7eb; }
  .kpi-nums{ font-size:13.5px; color: var(--muted) }
  .kpi-nums b{ color:#f9fafb; font-size:18px; margin-left:4px }
  .bar{
    height:9px;
    border-radius:999px;
    background:#020617;
    overflow:hidden;
    border:1px solid #111827;
    margin-top:10px;
  }
  .bar>span{
    display:block;
    height:100%;
    width:0%;
    background: linear-gradient(90deg, var(--brand), var(--brand-2));
    box-shadow:0 0 14px rgba(56,189,248,.7);
    transition: width .45s ease;
  }

  .toolbar{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin:8px 0 6px;
  }
  .left-tools,.right-tools{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
  }
  .search{
    background:#020617;
    border:1px solid #1f2937;
    border-radius:12px;
    padding:11px 14px;
    min-width:min(340px,92vw);
    box-shadow:var(--shadow-1);
    font-size:15px;
    outline:none;
    color:#e5e7eb;
  }
  .search::placeholder{ color:#64748b; }
  .search:focus{
    box-shadow:var(--ring),var(--shadow-1);
    border-color:#2563eb;
  }
  .date{
    background:#020617;
    border:1px solid #1f2937;
    border-radius:12px;
    padding:9px 12px;
    font-size:14px;
    box-shadow:var(--shadow-1);
    outline:none;
    color:#e5e7eb;
  }
  .date:focus{
    box-shadow:var(--ring),var(--shadow-1);
    border-color:#2563eb;
  }
  .toggle{
    display:inline-flex;
    gap:8px;
    align-items:center;
    padding:10px 12px;
    background:#020617;
    border:1px solid #1f2937;
    border-radius:12px;
    box-shadow:var(--shadow-1);
    font-size:14px;
    cursor:pointer;
    user-select:none;
    color:#e5e7eb;
  }
  .toggle input{ accent-color: var(--brand-2); }

  .tabs{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:6px;
    border-bottom:1px solid #1f2937;
  }
  .tab{
    position:relative;
    background:transparent;
    border:0;
    cursor:pointer;
    padding:12px 16px;
    border-radius:12px 12px 0 0;
    font-weight:800;
    color:#64748b;
    letter-spacing:.2px;
  }
  .tab[aria-selected="true"]{
    color: var(--brand-2);
  }
  .tab[aria-selected="true"]::after{
    content:"";
    position:absolute;
    left:12px; right:12px; bottom:-1px;
    height:3px; border-radius:999px;
    background: linear-gradient(90deg, var(--brand), var(--brand-3));
    box-shadow: 0 6px 18px rgba(56,189,248,.7);
  }

  .panel{
    background:radial-gradient(circle at 100% 0%, rgba(37,99,235,.15), transparent 55%),
               #020617;
    border:1px solid #1f2937;
    border-radius: 0 16px 16px 16px;
    box-shadow: var(--shadow-2);
    padding:16px;
    margin-top:0;
  }
  .legend{
    display:flex;
    gap:18px;
    align-items:center;
    color:var(--muted);
    font-size:13.5px;
    margin-bottom:10px;
  }
  .dot{
    width:12px;
    height:12px;
    border-radius:999px;
    display:inline-block;
    margin-right:6px;
    border:1px solid rgba(15,23,42,.8);
  }
  .ok{ background: var(--ok-bg); border-color: var(--ok-bd); }
  .bad{ background: var(--bad-bg); border-color: var(--bad-bd); }

  .list{
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap:12px;
  }
  .unit{
    position:relative;
    padding:12px 12px 14px;
    border-radius:14px;
    border:1px solid #1f2937;
    background:#020617;
    transition: transform .1s ease, box-shadow .18s ease, border-color .18s ease;
    color:#e5e7eb;
  }
  .unit.available{
    cursor:pointer;
  }
  .unit:hover{
    transform: translateY(-2px);
    box-shadow: 0 18px 40px rgba(0,0,0,.7);
    border-color:#2563eb;
  }
  .unit:focus{
    outline:none;
    box-shadow: var(--ring);
  }
  .available{
    background: var(--ok-bg);
    border-color: var(--ok-bd);
    color: var(--ok);
  }
  .booked{
    background: var(--bad-bg);
    border-color: var(--bad-bd);
    color: var(--bad);
    cursor:pointer;
  }
  .chip{
    position:absolute; top:8px; right:8px;
    font-size:12px; font-weight:800; letter-spacing:.2px;
    padding:4px 8px; border-radius:999px;
    background:rgba(15,23,42,.85);
    backdrop-filter: blur(6px);
    border:1px solid rgba(15,23,42,.9);
    color:#e5e7eb;
  }
  .available .chip{
    background:rgba(6,95,70,.95);
    border-color:#16a34a;
    color:#f0fdf4;
  }
  .booked .chip{
    background:rgba(127,29,29,.95);
    border-color:#f97373;
    color:#fee2e2;
  }
  .id{
    font-size:22px;
    font-weight:900;
    letter-spacing:.4px;
  }
  .type{
    display:block;
    margin-top:3px;
    color:#cbd5f5;
    font-size:13.5px;
  }
  .room-meta{
    display:block;
    margin-top:2px;
    font-size:12px;
    color:#9ca3af;
  }
  .empty{
    text-align:center;
    color: var(--muted);
    padding:18px 8px;
    font-size:15px;
  }

  .unit-selected{
    box-shadow:0 0 0 2px #38bdf8, 0 0 40px rgba(56,189,248,.8);
    border-color:#38bdf8 !important;
  }

  /* MODAL DETALLES RESERVA */
  .modal-backdrop{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.85);
    backdrop-filter:blur(18px);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:99999;
  }

  .modal-backdrop[hidden]{
    display:none !important;
  }

  .modal-card{
    width:min(460px, 96vw);
    border-radius:24px;
    border:1px solid #1f2937;
    background:radial-gradient(circle at 0% 0%, rgba(56,189,248,.12), transparent 55%),
               #020617;
    box-shadow:0 26px 80px rgba(0,0,0,.95);
    padding:18px 20px 18px;
    color:#e5e7eb;
  }
  .modal-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:12px;
  }
  .modal-title{
    margin:0;
    font-size:18px;
    font-weight:800;
    letter-spacing:.3px;
  }
  .modal-sub{
    font-size:12px;
    color:#9ca3af;
    margin-top:2px;
  }
  .pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    font-size:11px;
    border:1px solid #1f2937;
    background:rgba(15,23,42,.9);
    color:#e5e7eb;
  }
  .pill-dot{
    width:7px; height:7px;
    border-radius:999px;
    background:#22c55e;
  }
  .pill-dot.bad{
    background:#ef4444;
  }
  .modal-body{
    border-top:1px solid #1f2937;
    border-bottom:1px solid #1f2937;
    padding:10px 0 12px;
    margin-bottom:12px;
  }
  .row{
    display:flex;
    justify-content:space-between;
    gap:10px;
    font-size:13px;
    margin-bottom:6px;
  }
  .row-label{
    color:#9ca3af;
  }
  .row-value{
    font-weight:600;
    text-align:right;
  }
  .modal-footer{
    display:flex;
    justify-content:flex-end;
    gap:8px;
  }
  .btn-ghost{
    border-radius:999px;
    padding:7px 13px;
    font-size:12px;
    border:1px solid #1f2937;
    background:transparent;
    color:#e5e7eb;
    cursor:pointer;
  }
  .btn-primary{
    border-radius:999px;
    padding:7px 13px;
    font-size:12px;
    border:0;
    background:var(--brand-grad);
    color:#f9fafb;
    font-weight:700;
    cursor:pointer;
  }
  .btn-primary:hover{
    opacity:.9;
  }

  @media (max-width:768px){
    .topbar-inner{
      grid-template-columns:1fr;
    }
    .actions{
      justify-content:flex-end;
    }
    .hero{
      flex-direction:column;
      align-items:flex-start;
    }
  }
</style>

<script>
  // Exponer el rol al JS (para bloquear creación de reserva al ASESOR)
  const ROL_USUARIO = '<?= htmlspecialchars((string)$rol, ENT_QUOTES, 'UTF-8') ?>';
</script>
</head>
<body>

<div class="app" id="app">

  <!-- TOPBAR -->
  <header class="topbar" role="banner">
    <div class="topbar-inner">

      <div class="topbar-left">
        <div class="brand-chip">
          <div class="brand-mark" aria-hidden="true"></div>
          <div>
            <div class="brand-text">MYSTIC PARADISE</div>
            <div class="crumb">Backoffice · Disponibilidad</div>
          </div>
        </div>

        <div class="page-title">
          <h1>Disponibilidad</h1>
          <span>
            <?php if ($rol === 'ASESOR'): ?>
              Vista solo lectura: puedes consultar quién ocupa cada alojamiento.
            <?php else: ?>
              Selecciona una unidad disponible para asignarla a una reserva.
            <?php endif; ?>
          </span>
        </div>
      </div>

      <div class="actions">
        <!-- Botón VOLVER -->
        <a href="<?= htmlspecialchars($menuUrl) ?>" class="btn" title="Volver al menú">
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M11.5 4.5L7 9l4.5 4.5"/>
          </svg>
          <span>Volver</span>
        </a>

        <!-- Perfil usuario (simple) -->
        <button type="button" class="profile-chip" title="Perfil de usuario">
          <div class="profile-avatar">
            MP
          </div>
          <div class="profile-meta">
            <span class="profile-name">Usuario</span>
            <span class="profile-role"><?= htmlspecialchars((string)$rol) ?></span>
          </div>
        </button>
      </div>

    </div>
  </header>

  <!-- MAIN -->
  <main class="main">
    <section class="hero" role="region" aria-label="Encabezado de disponibilidad">
      <div>
        <h1 class="hero-title">Disponibilidad de Alojamientos</h1>
        <p class="hero-sub">
          Filtra por fechas, tipo, número y estado.
          <?php if ($rol === 'ASESOR'): ?>
            Haz clic en un cuadrito rojo para ver de quién es la reserva.
          <?php else: ?>
            Haz clic en un cuadrito verde para asignarlo a una reserva, o en rojo para ver el detalle.
          <?php endif; ?>
        </p>
      </div>
      <span class="badge" title="Conexión segura">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="2" d="M6 10V8a6 6 0 1112 0v2M6 10h12v10H6V10z"/>
        </svg>
        Panel Interno
      </span>
    </section>

    <!-- KPIs: CAMPINGS, GLAMPINGS, HABITACIONES -->
    <section class="summary" aria-label="Resumen de disponibilidad">
      <!-- CAMPINGS PRIMERO -->
      <article class="card" id="kpi-camping">
        <div class="kpi-top">
          <div class="kpi-left">
            <div class="kpi-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="2" d="M2 20l10-16 10 16H2zm10-9l6 9M12 11l-6 9"/>
              </svg>
            </div>
            <div>
              <h3 class="kpi-title">Campings</h3>
              <div class="kpi-nums">Total <b id="camping-total">150</b> · Disp. <b id="camping-available">--</b></div>
            </div>
          </div>
        </div>
        <div class="bar" aria-hidden="true"><span></span></div>
      </article>

      <!-- GLAMPINGS SEGUNDOS -->
      <article class="card" id="kpi-glamping">
        <div class="kpi-top">
          <div class="kpi-left">
            <div class="kpi-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="2" d="M3 20l9-16 9 16H3zm9-16v16"/>
              </svg>
            </div>
            <div>
              <h3 class="kpi-title">Glampings</h3>
              <div class="kpi-nums">Total <b id="glamping-total">16</b> · Disp. <b id="glamping-available">--</b></div>
            </div>
          </div>
        </div>
        <div class="bar" aria-hidden="true"><span></span></div>
      </article>

      <!-- HABITACIONES AL FINAL -->
      <article class="card" id="kpi-room">
        <div class="kpi-top">
          <div class="kpi-left">
            <div class="kpi-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="2" d="M3 10h18v10H3V10zm0 5h18M7 10V7a2 2 0 012-2h2a2 2 0 012 2v3"/>
              </svg>
            </div>
            <div>
              <h3 class="kpi-title">Habitaciones</h3>
              <div class="kpi-nums">Total <b id="room-total">7</b> · Disp. <b id="room-available">--</b></div>
            </div>
          </div>
        </div>
        <div class="bar" aria-hidden="true"><span></span></div>
      </article>
    </section>

    <!-- Filtros -->
    <div class="toolbar" role="region" aria-label="Filtros y acciones">
      <div class="left-tools">
        <input id="searchInput" class="search" type="text"
               placeholder="Buscar por número o tipo (p. ej. 12, Glamping)..."
               aria-label="Buscar unidad" />
      </div>
      <div class="right-tools">
        <input id="dateIn"  class="date" type="date" aria-label="Fecha de entrada">
        <input id="dateOut" class="date" type="date" aria-label="Fecha de salida (checkout)">
        <label class="toggle" title="Mostrar solo unidades disponibles">
          <input type="checkbox" id="onlyAvailable" />
          <span>Solo disponibles</span>
        </label>
      </div>
    </div>

    <!-- Tabs: CAMPINGS, GLAMPINGS, HABITACIONES -->
    <nav class="tabs" role="tablist" aria-label="Tipos de alojamiento">
      <button class="tab" role="tab" aria-selected="true" tabindex="0" data-type="camping">Campings</button>
      <button class="tab" role="tab" aria-selected="false" tabindex="-1" data-type="glamping">Glampings</button>
      <button class="tab" role="tab" aria-selected="false" tabindex="-1" data-type="room">Habitaciones</button>
    </nav>

    <!-- Panel + Lista -->
    <section class="panel" aria-label="Listado">
      <div class="legend" aria-hidden="true">
        <span><i class="dot ok"></i>Disponible</span>
        <span><i class="dot bad"></i>Ocupado</span>
      </div>
      <div id="unitList" class="list" aria-live="polite"></div>
      <div id="emptyState" class="empty" hidden>Sin resultados para los filtros aplicados.</div>
    </section>
  </main>
</div>

<!-- MODAL DETALLE RESERVA -->
<div id="bookingModal" class="modal-backdrop" hidden>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-header">
      <div>
        <h2 id="modalTitle" class="modal-title">Reserva</h2>
        <p id="modalSubtitle" class="modal-sub"></p>
      </div>
      <div id="modalEstadoPill" class="pill">
        <span class="pill-dot" id="modalEstadoDot"></span>
        <span id="modalEstadoText">Estado</span>
      </div>
    </div>

    <div class="modal-body">
      <div class="row">
        <div class="row-label">Cliente</div>
        <div class="row-value" id="modalCliente"></div>
      </div>
      <div class="row">
        <div class="row-label">WhatsApp</div>
        <div class="row-value" id="modalWhatsApp"></div>
      </div>
      <div class="row">
        <div class="row-label">Plan</div>
        <div class="row-value" id="modalPlan"></div>
      </div>
      <div class="row">
        <div class="row-label">Fechas</div>
        <div class="row-value" id="modalFechas"></div>
      </div>
      <div class="row">
        <div class="row-label">Ocupación</div>
        <div class="row-value" id="modalOcupacion"></div>
      </div>
      <div class="row">
        <div class="row-label">Total</div>
        <div class="row-value" id="modalTotal"></div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn-ghost" id="modalCloseBtn">Cerrar</button>
    </div>
  </div>
</div>

<script>
  // ====== DATA DESDE PHP ====== 
  const data   = <?php echo json_encode($data,   JSON_UNESCAPED_UNICODE); ?>;
  const totals = <?php echo json_encode($totals, JSON_UNESCAPED_UNICODE); ?>;

  // Parámetros de preselección
  const PRE_TYPE  = <?php echo json_encode($preTipoKey, JSON_UNESCAPED_UNICODE); ?>;
  const PRE_UNIT  = <?php echo $preUnidad ?: 'null'; ?>;
  const PRE_FROM  = <?php echo json_encode($preDesde, JSON_UNESCAPED_UNICODE); ?>;
  const PRE_TO    = <?php echo json_encode($preHasta, JSON_UNESCAPED_UNICODE); ?>;

  // Fechas / utilidades
  const toLocalDate = (y,m,d) => new Date(y, m-1, d);
  const parseDateInput = (s) => {
    const [Y,M,D]=s?.split('-').map(Number);
    return toLocalDate(Y||0,M||1,D||1);
  };
  const addDays = (d,n)=>{ const x=new Date(d); x.setDate(x.getDate()+n); return x; };
  const formatInput = (d)=> `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const overlaps = (aStart,aEnd,bStart,bEnd)=> (aStart < bEnd) && (aEnd > bStart);

  const fmtDMY = (s) => {
    if (!s) return '';
    const [y,m,d] = s.split('-');
    return `${d}/${m}/${y}`;
  };
  const fmtMoney = (v) => {
    try{
      return new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(v||0);
    }catch(e){
      return `COP ${v || 0}`;
    }
  };

  // DOM
  const unitListElm=document.getElementById('unitList');
  const emptyState=document.getElementById('emptyState');
  const tabs=[...document.querySelectorAll('.tab')];
  const searchInput=document.getElementById('searchInput');
  const onlyChk=document.getElementById('onlyAvailable');
  const dateInElm=document.getElementById('dateIn');
  const dateOutElm=document.getElementById('dateOut');

  const kpiMap={
    glamping:{el:document.getElementById('kpi-glamping'),total:document.getElementById('glamping-total'),available:document.getElementById('glamping-available')},
    camping:{el:document.getElementById('kpi-camping'),total:document.getElementById('camping-total'),available:document.getElementById('camping-available')},
    room:{el:document.getElementById('kpi-room'),total:document.getElementById('room-total'),available:document.getElementById('room-available')}
  };

  // Modal DOM
  const modal            = document.getElementById('bookingModal');
  const modalTitle       = document.getElementById('modalTitle');
  const modalSubtitle    = document.getElementById('modalSubtitle');
  const modalEstadoPill  = document.getElementById('modalEstadoPill');
  const modalEstadoDot   = document.getElementById('modalEstadoDot');
  const modalEstadoText  = document.getElementById('modalEstadoText');
  const modalCliente     = document.getElementById('modalCliente');
  const modalWhatsApp    = document.getElementById('modalWhatsApp');
  const modalPlan        = document.getElementById('modalPlan');
  const modalFechas      = document.getElementById('modalFechas');
  const modalOcupacion   = document.getElementById('modalOcupacion');
  const modalTotal       = document.getElementById('modalTotal');
  const modalCloseBtn    = document.getElementById('modalCloseBtn');

  modal.hidden = true;

  // Estado/rango
  let activeType = PRE_TYPE || 'camping'; // CAMPING POR DEFECTO
  const today=new Date();

  let dInDefault, dOutDefault;
  if (PRE_FROM && PRE_TO) {
    dInDefault = parseDateInput(PRE_FROM);
    dOutDefault = parseDateInput(PRE_TO);
  } else {
    dInDefault=toLocalDate(today.getFullYear(),today.getMonth()+1,today.getDate());
    dOutDefault=addDays(dInDefault,1);
  }

  dateInElm.value=formatInput(dInDefault);
  dateOutElm.value=formatInput(dOutDefault);
  dateOutElm.min=dateInElm.value;

  function currentRange(){
    let s=parseDateInput(dateInElm.value);
    let e=parseDateInput(dateOutElm.value);
    if(addDays(s,1)>e){
      e=addDays(s,1);
      dateOutElm.value=formatInput(e);
    }
    dateOutElm.min=formatInput(addDays(s,1));
    return {start:s,end:e};
  }

  const unitIsAvailable=(u,start,end)=> !u.bookings.some(b=>{
    const bs=parseDateInput(b.start), be=parseDateInput(b.end);
    return overlaps(start,end,bs,be);
  });

  // KPIs
  function updateSummary(){
    const {start,end}=currentRange();
    ['glamping','camping','room'].forEach(type=>{
      const total=totals[type] || 0;
      const avail=(data[type]||[]).filter(u=>unitIsAvailable(u,start,end)).length;
      kpiMap[type].total.textContent=total;
      kpiMap[type].available.textContent=avail;
      const pct= total? Math.round((avail/total)*100):0;
      const bar=kpiMap[type].el.querySelector('.bar > span');
      requestAnimationFrame(()=> bar.style.width=pct+'%');
      kpiMap[type].el.setAttribute('aria-label',`${type}: ${avail} disponibles de ${total} (${pct}%)`);
    });
  }

  // función para mapear tipo clave -> texto BD
  function tipoKeyToBD(tipoKey){
    switch(tipoKey){
      case 'glamping': return 'GLAMPING';
      case 'camping':  return 'CAMPING';
      case 'room':     return 'HABITACION';
      default:         return 'GLAMPING';
    }
  }

  // Crear formulario oculto y enviarlo a ReservaController (unidades libres)
  function enviarSeleccion(tipoKey, numero){
    const tipoBD = tipoKeyToBD(tipoKey);
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '../Controlador/ReservaController.php';

    const paso = document.createElement('input');
    paso.type = 'hidden';
    paso.name = 'paso';
    paso.value = 'confirmar';

    const tipo = document.createElement('input');
    tipo.type = 'hidden';
    tipo.name = 'tipo_alojamiento';
    tipo.value = tipoBD;

    const num = document.createElement('input');
    num.type = 'hidden';
    num.name = 'numero_alojamiento';
    num.value = String(numero);

    form.appendChild(paso);
    form.appendChild(tipo);
    form.appendChild(num);

    document.body.appendChild(form);
    form.submit();
  }

  // MODAL: helpers
  function openModal(booking, tipoKey, numero){
    if(!booking) return;

    modalTitle.textContent = booking.codigo ? `Reserva ${booking.codigo}` : 'Reserva ocupada';
    modalSubtitle.textContent = `${tipoKey.toUpperCase()} ${numero} · ${fmtDMY(booking.start)} – ${fmtDMY(booking.end)} · ${booking.noches || 1} noche(s)`;

    // Estado
    const estado = (booking.estado || 'RESERVADA').toUpperCase();
    modalEstadoText.textContent = estado;
    if(estado === 'CANCELADA'){
      modalEstadoDot.classList.add('bad');
    }else{
      modalEstadoDot.classList.remove('bad');
    }

    modalCliente.textContent   = booking.cliente_nombre || 'Sin nombre';
    modalWhatsApp.textContent  = booking.cliente_whatsapp || '-';
    modalPlan.textContent      = booking.plan_nombre || 'Sin plan asignado';
    modalFechas.textContent    = `${fmtDMY(booking.start)} → ${fmtDMY(booking.end)} · ${booking.noches || 1} noche(s)`;
    modalOcupacion.textContent = `${booking.adultos || 0} adulto(s)` + ((booking.menores||0) ? ` · ${booking.menores} menor(es)` : '');
    modalTotal.textContent     = fmtMoney(booking.valor_total || 0);

    modal.hidden = false;
  }

  function closeModal(){
    modal.hidden = true;
  }

  modalCloseBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e)=>{
    if(e.target === modal){
      closeModal();
    }
  });
  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape' && !modal.hidden){
      closeModal();
    }
  });

  // Buscar unidad y booking correspondiente
  function findUnit(tipoKey, numero){
    return (data[tipoKey] || []).find(u => u.id === numero) || null;
  }

  function openModalForUnit(tipoKey, numero){
    const unit = findUnit(tipoKey, numero);
    if(!unit || !unit.bookings || unit.bookings.length === 0) return;

    const {start,end} = currentRange();
    const overlapBookings = unit.bookings.filter(b=>{
      const bs=parseDateInput(b.start), be=parseDateInput(b.end);
      return overlaps(start,end,bs,be);
    });

    const booking = overlapBookings[0] || unit.bookings[0];
    openModal(booking, tipoKey, numero);
  }

  // Handlers de click en unidades
  function attachUnitHandlers(){
    const cards = unitListElm.querySelectorAll('.unit');
    cards.forEach(card=>{
      const available = card.dataset.available === '1';
      const tipoKey   = card.dataset.typeKey;
      const numero    = parseInt(card.dataset.id,10) || 0;
      if(!tipoKey || !numero) return;

      card.onclick = null;

      if(available){
        // 👉 Solo ADMIN y RECEPCION pueden asignar una unidad disponible
        if (ROL_USUARIO === 'ADMIN' || ROL_USUARIO === 'RECEPCION') {
          card.addEventListener('click', ()=>{
            enviarSeleccion(tipoKey, numero);
          });
        } else {
          // Asesor u otro rol solo lectura
          card.addEventListener('click', ()=>{
            console.log('Rol solo lectura: no puede crear reservas desde disponibilidad.');
          });
        }
      }else{
        // 👉 Ocupado: siempre se puede ver el pop-up con el detalle
        card.addEventListener('click', ()=>{
          openModalForUnit(tipoKey, numero);
        });
      }
    });
  }

  // Render listado
  function renderList(){
    const {start,end}=currentRange();
    const term=searchInput.value.trim().toLowerCase();
    const only=onlyChk.checked;

    const baseList = data[activeType] || [];

    const list=baseList.map(u=>({...u,available:unitIsAvailable(u,start,end)}))
      .filter(u=>{
        const byText=u.id.toString().includes(term)||u.type.toLowerCase().includes(term);
        return byText && (only?u.available:true);
      });

    unitListElm.innerHTML=list.map(u=>{
      const isSelected = PRE_UNIT && u.id === PRE_UNIT;
      const isRoom = activeType === 'room';

      const capacityLabel = isRoom && typeof u.capacity !== 'undefined'
        ? `${u.capacity} persona${u.capacity === 1 ? '' : 's'}`
        : '';

      const bathroomLabel = isRoom && typeof u.bathroom_priv !== 'undefined'
        ? (u.bathroom_priv ? 'Baño privado' : 'Sin baño privado')
        : '';

      const extraLine = isRoom && (capacityLabel || bathroomLabel)
        ? `<span class="room-meta">${capacityLabel}${capacityLabel && bathroomLabel ? ' · ' : ''}${bathroomLabel}</span>`
        : '';

      let aria = `${u.type} ${u.id} ${u.available?'disponible':'ocupado'} del ${dateInElm.value} al ${dateOutElm.value}`;
      if (isRoom) {
        if (capacityLabel) aria += ` · Capacidad ${capacityLabel}`;
        if (bathroomLabel) aria += ` · ${bathroomLabel}`;
      }

      return `
      <div class="unit ${u.available?'available':'booked'} ${isSelected?'unit-selected':''}"
           tabindex="0"
           data-id="${u.id}"
           data-type-key="${activeType}"
           data-type-label="${u.type}"
           data-available="${u.available ? '1' : '0'}"
           aria-label="${aria}">
        <span class="chip">${u.available?'Disponible':'Ocupado'}</span>
        <div class="id">${u.id}</div>
        <span class="type">${u.type}</span>
        ${extraLine}
      </div>`;
    }).join('');

    emptyState.hidden = list.length !== 0;

    // Después de pintar, enganchar los clicks
    attachUnitHandlers();
  }

  // Tabs
  function setActiveTab(type){
    activeType=type;
    tabs.forEach(t=>{
      const sel=t.dataset.type===type;
      t.setAttribute('aria-selected',sel);
      t.setAttribute('tabindex',sel?'0':'-1');
    });
    searchInput.value='';
    renderList();
  }
  tabs.forEach(tab=>{
    tab.addEventListener('click',()=>setActiveTab(tab.dataset.type));
    tab.addEventListener('keydown',e=>{
      if(e.key==='Enter'||e.key===' '){
        e.preventDefault();
        setActiveTab(tab.dataset.type);
      }
      if(e.key==='ArrowRight'||e.key==='ArrowLeft'){
        e.preventDefault();
        const i=tabs.indexOf(tab);
        const n=e.key==='ArrowRight'?(i+1)%tabs.length:(i-1+tabs.length)%tabs.length;
        tabs[n].focus();
      }
    });
  });

  // Eventos
  searchInput.addEventListener('input', renderList);
  onlyChk.addEventListener('change', renderList);
  dateInElm.addEventListener('change', ()=>{updateSummary();renderList();});
  dateOutElm.addEventListener('change', ()=>{updateSummary();renderList();});

  // Init
  setActiveTab(activeType);
  updateSummary();
</script>
</body>
</html>
