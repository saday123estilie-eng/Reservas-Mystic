<?php
// registro_reserva.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluye tu clase de conexión (ajusta la ruta si está en otra carpeta)
require_once __DIR__ . '/../config/conexion.php';

$planes        = [];
$planServicios = [];
$allServicios  = [];

// Mensaje flash para mostrar toast tipo "Reserva registrada para Juan Pérez"
$flash_ok = $_SESSION['flash_ok'] ?? null;
if ($flash_ok) {
    unset($_SESSION['flash_ok']); // se muestra una sola vez
}

try {
    /** @var PDO $pdo */
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1) Planes desde tabla `planes`
    $sqlPlanes = "
        SELECT 
            id_plan AS id,
            nombre  AS nombre
        FROM planes
        ORDER BY nombre
    ";
    $stmt = $pdo->query($sqlPlanes);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $planes[] = [
            'id'     => (int)$row['id'],
            'nombre' => $row['nombre'],
        ];
    }

    // 2) Servicios incluidos por plan: planes_servicios + servicios
    // planes_servicios: id_plan, id_servicio, cantidad_incluida, obligatorio
    // servicios: id_servicio, categoria, nombre, precio_base, activo
    $sqlServiciosPlan = "
        SELECT 
            ps.id_plan,
            ps.cantidad_incluida,
            s.id_servicio,
            s.categoria,
            s.nombre AS servicio_nombre
        FROM planes_servicios ps
        INNER JOIN servicios s ON ps.id_servicio = s.id_servicio
        ORDER BY ps.id_plan, s.categoria, s.nombre
    ";
    $stmt2 = $pdo->query($sqlServiciosPlan);

    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$row['id_plan'];
        if (!isset($planServicios[$pid])) {
            $planServicios[$pid] = [];
        }
        $planServicios[$pid][] = [
            'id_servicio'       => (int)$row['id_servicio'],
            'nombre'            => $row['servicio_nombre'],
            'grupo'             => !empty($row['categoria']) ? $row['categoria'] : 'Servicios',
            'cantidad_incluida' => isset($row['cantidad_incluida']) ? (int)$row['cantidad_incluida'] : 0,
        ];
    }

    // 3) TODOS los servicios disponibles (para poder listarlos siempre)
    $sqlAllServicios = "
        SELECT 
            id_servicio,
            categoria,
            nombre,
            precio_base
        FROM servicios
        WHERE activo = 1
        ORDER BY categoria, nombre
    ";
    $stmtAll      = $pdo->query($sqlAllServicios);
    $allServicios = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<pre style='color:red'>Error BD: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Registro | Mystic Paradise</title>

  <!-- Tailwind (CDN) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    if (window.tailwind) {
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              brand: {
                50:  '#eff6ff',
                100: '#dbeafe',
                200: '#bfdbfe',
                300: '#93c5fd',
                400: '#60a5fa',
                500: '#3b82f6',
                600: '#2563eb',
                700: '#1d4ed8',
                800: '#1e40af',
                900: '#1e3a8a',
                950: '#172554'
              }
            },
            boxShadow: {
              soft:'0 6px 18px -6px rgb(16 185 129 / 0.20), 0 2px 8px -2px rgb(15 23 42 / 0.08)'
            },
            fontFamily: { inter:["Inter","system-ui","-apple-system","Segoe UI","Roboto","sans-serif"] }
          }
        }
      };
    }
  </script>

  <style>
    :root{
      --bg:#020617; 
      --card:#020617; 
      --ink:#e5e7eb; 
      --muted:#94a3b8;
      --brand:#0f7a66;
      --brand-2:#18b795;
      --brand-3:#43d9b6;
      --ring:0 0 0 3px rgba(34, 34, 197, 0.35);
      --shadow-1:0 6px 18px rgba(15,23,42,.55);
      --shadow-2:0 40px 80px rgba(15,23,42,.9);
      --rail-w-expanded:260px;
      --fab-overlap:22px;
      --brand-grad: linear-gradient(90deg,#1e3a8a 0%,#2563eb 45%,#3b82f6 100%);
      --brand-logo-url: url("https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png");
    }

    html, body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    body{
      margin:0; color:var(--ink);
      background:
        radial-gradient(1200px 900px at 0% -20%, rgba(34, 37, 197, 0.25), transparent 70%),
        radial-gradient(1100px 800px at 100% -30%, rgba(26, 49, 216, 0.2), transparent 70%),
        radial-gradient(900px 700px at 50% 120%, rgba(15,23,42,.9), #020617 70%);
    }

    .app{ min-height:100dvh; position:relative; }
    .main{ max-width:1200px; margin:0 auto; padding:12px 10px 40px; }

    /* Sidebar */
    .sidebar{
      position:fixed; inset:10px auto 10px 10px; width:var(--rail-w-expanded);
      background:rgba(15,23,42,.96); 
      border:1px solid rgba(30,64,175,.5); 
      border-radius:18px; 
      box-shadow:var(--shadow-2);
      display:flex; flex-direction:column; transform:translateX(-120%);
      transition:transform .25s ease; z-index:9998; overflow:hidden;
    }
    .app.expanded .sidebar{ transform:translateX(0); }

    .scrim{
      position:fixed; inset:0; background:rgba(15,23,42,.65);
      backdrop-filter:saturate(140%) blur(5px);
      opacity:0; pointer-events:none; transition:opacity .2s ease; z-index:9997;
    }
    .app.expanded .scrim{ opacity:1; pointer-events:auto; }

    .sb-head{
      display:flex; align-items:center; gap:10px; padding:12px;
      border-bottom:1px solid rgba(48, 46, 220, 0.8); position:relative;
    }
    .sb-head::after{
      content:""; position:absolute; left:12px; right:12px; bottom:0; height:3px; border-radius:999px;
      background: var(--brand-grad); opacity:.9;
    }
    .brand-mark{
      width:38px;height:38px;border-radius:12px;display:grid;place-items:center;
      background:#020617;border:1px solid rgba(36, 33, 218, 0.6);box-shadow:var(--shadow-1);overflow:hidden
    }
    .brand-mark img{width:100%;height:100%;object-fit:contain;display:block}
    .brand-info{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .brand-title{font-weight:900; letter-spacing:.2px}
    .brand-sub{font-size:12px;color:var(--muted);margin-top:2px}

    .sb-search{ padding:10px 12px; border-bottom:1px solid rgba(51,65,85,.8); }
    .sb-search input{
      width:100%; border:1px solid rgba(51,65,85,1); border-radius:12px; padding:10px 12px; font-size:14px;
      background:#020617; box-shadow:var(--shadow-1); outline:none; color:var(--ink);
    }
    .sb-search input::placeholder{ color:#64748b; }
    .sb-search input:focus{ box-shadow:var(--ring),var(--shadow-1); border-color:#22c55e }

    .sb-nav{ padding:6px }
    .sb-item{
      display:flex;
      align-items:center;
      gap:12px;
      padding:10px 12px;
      margin:3px 6px;
      border-radius:999px;
      color:#e5e7eb;
      text-decoration:none;
      font-weight:600;
    }
    .sb-item:hover{
      background:rgba(30,64,175,.45);
    }
    .sb-item svg{
      width:20px;
      height:20px;
      color:#94a3b8;
    }
    .sb-item.active{
      background:rgba(16,185,129,.14);
      box-shadow:0 0 0 1px rgba(45,212,191,.4);
    }
    .sb-item.active svg{
      color:#22c55e;
    }
    .sb-item.active .sb-label{
      color:#6ee7b7;
      font-weight:800;
    }

    .sb-fab{
      position: fixed; top: 50%; left: max(8px, env(safe-area-inset-left) + 8px);
      transform: translateY(-50%); width: 40px; height: 40px; border-radius: 999px;
      background:#020617; border:1px solid rgba(30,64,175,.6); box-shadow: 0 18px 45px rgba(15,23,42,.9), 0 3px 10px rgba(15,23,42,.75);
      display:grid; place-items:center; cursor:pointer; z-index: 9999;
    }
    .sb-fab svg{ width:18px; height:18px; color:#e5e7eb }
    .sb-fab:hover{ transform: translateY(-50%) scale(1.03); }
    .app.expanded .sb-fab{ left: calc(env(safe-area-inset-left) + var(--rail-w-expanded) - var(--fab-overlap)); }
    .app.expanded .sb-fab svg{ transform: scaleX(-1); }

    @media (min-width:1024px){ .scrim{ background:transparent } }
    @media (prefers-reduced-motion: reduce){
      .sidebar, .scrim, .sb-fab { transition:none !important; }
    }

    .topbar{
      position: sticky; top: 0; z-index: 10000;
      background: radial-gradient(circle at 0 0, rgba(34,197,94,.2), transparent 55%),
                  rgba(15,23,42,.96);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(30,64,175,.7);
    }
    .topbar::before{
      content:""; position:absolute; left:0; right:0; top:0; height:3px;
      background: var(--brand-grad);
    }
    .topbar-inner{
      max-width: 1200px; margin: 0 auto; padding: 10px 12px;
      display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: center;
    }
    .brand-chip{ display:flex; align-items:center; gap:12px; padding:6px 8px; border-radius:14px; border:1px solid rgba(45,212,191,.4); background:rgba(15,23,42,.7); }
    .brand-chip:hover{ background:rgba(15,23,42,.95); box-shadow:0 10px 30px rgba(15,23,42,.9); }
    .brand-chip .mark{
      width:34px; height:34px; border-radius:10px; overflow:hidden; display:grid; place-items:center;
      background:#020617; border:1px solid rgba(45,212,191,.8); box-shadow:var(--shadow-1);
    }
    .brand-chip .mark::before{
      content:""; width:100%; height:100%; background-image: var(--brand-logo-url);
      background-size: contain; background-position: center; background-repeat: no-repeat;
      display:block;
    }
    .brand-chip .name{
      font-weight:900; letter-spacing:.4px;
      background: var(--brand-grad); -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    /* Dark cards, inputs y chips (modo normal) */
    .section{
      background: radial-gradient(circle at 0 0, rgba(34,197,94,.09), transparent 55%),
                  radial-gradient(circle at 100% 0, rgba(56,189,248,.1), transparent 55%),
                  rgba(15,23,42,.96);
      border-radius:16px;
      border:1px solid rgba(51,65,85,.9);
      box-shadow:0 20px 60px rgba(15,23,42,.9);
    }
    .field{
      border:1px solid rgba(51,65,85,1);
      border-radius:12px;
      padding:.65rem .9rem;
      background-color:#020617;
      color:#e5e7eb;
    }
    .field::placeholder{ color:#64748b; }
    .field:focus{
      outline:none;
      border-color:#22c55e;
      box-shadow:var(--ring);
    }
    .chip{
      background:rgba(15,118,110,.28);
      border:1px solid rgba(45,212,191,.65);
      border-radius:999px;
      padding:.25rem .6rem;
      font-size:.75rem;
      color:#a5f3fc;
    }

    body.mp-dark .bg-white{ background-color:#020617 !important; }
    body.mp-dark .bg-gray-50{ background-color:#020617 !important; }
    body.mp-dark .bg-gray-100{ background-color:#020617 !important; }
    body.mp-dark .border-gray-200{ border-color:#1f2937 !important; }
    body.mp-dark .border-gray-300{ border-color:#334155 !important; }

    body.mp-dark .text-gray-800,
    body.mp-dark .text-slate-800{ color:#e5e7eb !important; }
    body.mp-dark .text-gray-700,
    body.mp-dark .text-slate-700{ color:#e2e8f0 !important; }
    body.mp-dark .text-gray-500,
    body.mp-dark .text-slate-500{ color:#94a3b8 !important; }
    body.mp-dark .text-slate-400{ color:#64748b !important; }

    body.mp-dark details{ background:rgba(15,23,42,.9); }
  </style>

  <style>
    .no-tw .section{border:1px solid #e5e7eb;border-radius:16px;background:#fff}
    .no-tw .field{border:1px solid #e5e7eb;border-radius:12px;padding:.65rem .9rem}
    .no-tw .chip{background:#eef2ff;border:1px solid #c7d2fe;border-radius:999px;padding:.25rem .6rem;font-size:.75rem}
  </style>

  <!-- Datos JS: servicios por plan y todos los servicios desde PHP -->
  <script>
    const PLAN_SERVICIOS = <?php
      echo json_encode(
        $planServicios,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
      );
    ?>;

    const ALL_SERVICIOS = <?php
      echo json_encode(
        $allServicios,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
      );
    ?>;
  </script>
</head>

<body class="mp-dark min-h-dvh">
  <?php if (!empty($flash_ok)): ?>
    <div id="toast-ok" class="toast-ok">
      <strong>✔ Reserva registrada</strong>
      <span><?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <script>
      setTimeout(() => {
        const t = document.getElementById('toast-ok');
        if (t) t.style.opacity = '0';
      }, 4000);
    </script>
    <style>
      .toast-ok {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 9999;
        background: #022c22;
        border: 1px solid #16a34a;
        color: #e5fdf4;
        padding: 12px 16px;
        border-radius: 10px;
        box-shadow: 0 12px 30px rgba(0,0,0,.6);
        font-size: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        transition: opacity .4s ease;
      }
      .toast-ok strong {
        font-size: 14px;
      }
      .toast-ok span {
        font-size: 13px;
        opacity: .9;
      }
    </style>
  <?php endif; ?>

  <div id="twBanner" style="display:none;position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;background:#111827;color:#fff;padding:10px 14px;border-radius:12px">
    Tailwind no se ha podido cargar. Se aplicaron estilos básicos.
  </div>

  <div class="app" id="app">
    <!-- SIDEBAR -->
    <aside class="sidebar" aria-label="Menú lateral" id="sidebar">
      <div class="sb-head">
        <div class="brand-mark" aria-hidden="true">
          <img src="https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png" alt="Logo Mystic">
        </div>
        <div class="brand-info">
          <div class="brand-title">Mystic Paradise</div>
          <div class="brand-sub">Backoffice</div>
        </div>
      </div>

      <div class="sb-search">
        <input type="text" placeholder="Buscar..." aria-label="Buscar en menú">
      </div>

      <nav class="sb-nav" aria-label="Check-in">
        <a href="checkin.php" class="sb-item" title="Check-in">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18v10H3V10zm0 5h18M7 10V7a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
          <span class="sb-label">Check-in</span>
        </a>
        <a href="disponibilidad.php" class="sb-item" title="Disponibilidad">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 005 15.4a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1z"/></svg>
          <span class="sb-label">Disponibilidad</span>
        </a>
        <a href="#" class="sb-item active" title="Registro">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span class="sb-label">Registro</span>
        </a>
        <a href="#" class="sb-item" title="Configuración">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 005 15.4a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1z"/></svg>
          <span class="sb-label">Configuración</span>
        </a>
      </nav>
    </aside>

    <div class="scrim" id="scrim" aria-hidden="true"></div>

    <!-- FAB TOGGLE SIDEBAR -->
    <button class="sb-fab" id="sbFab" aria-label="Expandir/colapsar sidebar" title="Expandir/colapsar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6l6 6-6 6"/>
      </svg>
    </button>

    <!-- TOPBAR -->
    <header class="topbar" role="banner" aria-label="Barra superior">
      <div class="topbar-inner">
        <div class="flex items-center gap-5">
          <button class="brand-chip" type="button" id="brandToggle" title="Mostrar/ocultar menú" aria-controls="sidebar" aria-expanded="false">
            <div class="mark" aria-hidden="true"></div>
            <div class="name">MYSTIC PARADISE</div>
          </button>
          <div class="hidden sm:block">
            <p class="text-xs text-slate-500">Backoffice · Registro</p>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-100">Registro</h1>
          </div>
        </div>

        <!-- Botón de volver -->
        <div class="hidden md:flex items-center justify-end">
          <a href="reservas.php"
             class="inline-flex items-center gap-2 rounded-full border border-emerald-500/60 bg-emerald-500/10 px-3 py-1.5 text-[11px] font-medium text-emerald-100 hover:bg-emerald-500/20 hover:border-emerald-400 transition">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/25">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M11.53 4.47a.75.75 0 010 1.06L8.06 9l3.47 3.47a.75.75 0 11-1.06 1.06l-4-4a.75.75 0 010-1.06l4-4a.75.75 0 011.06 0z" clip-rule="evenodd" />
              </svg>
            </span>
            <span>Volver a reservas</span>
          </a>
        </div>
      </div>
    </header>

    <!-- MAIN -->
    <main class="main">
      <!-- ESTE FORM HACE PASO 1 (pre_reserva) -->
      <form id="formReserva" action="../Controlador/ReservaController.php" method="post" class="grid grid-cols-1 lg:grid-cols-12 gap-6" novalidate>
        <!-- Paso del flujo -->
        <input type="hidden" name="paso" value="pre_reserva">
        <!-- hidden para servicios (incluidos + extras) -->
        <input type="hidden" id="servicios_json" name="servicios_json">

        <div class="lg:col-span-8 space-y-8">
          <!-- DATOS CLIENTE -->
          <section class="section p-6">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-100">Datos del cliente</h2>
              <span class="chip inline-flex items-center gap-1"><span>Paso 1</span></span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm mb-1" for="nombre">Nombre *</label>
                <input id="nombre" name="nombre" required class="field w-full focus:outline-none" placeholder="Nombre y apellido" autocomplete="name">
                <p class="mt-1 text-xs text-slate-500">Como aparece en el documento.</p>
              </div>
              <div>
                <label class="block text-sm mb-1" for="cedula">Cédula *</label>
                <input id="cedula" name="cedula" required class="field w-full" placeholder="1012345678" inputmode="numeric" autocomplete="off">
              </div>
              <div>
                <label class="block text-sm mb-1" for="whatsapp">WhatsApp *</label>
                <input id="whatsapp" name="whatsapp" required pattern="[0-9+\s-]{7,20}" class="field w-full" placeholder="300 123 4567" autocomplete="tel">
              </div>

              <div>
                <label class="block text-sm mb-1" for="edad">Edad</label>
                <input id="edad" name="edad" type="number" readonly class="field w-full bg-slate-900/80 border-slate-700">
              </div>
              <div>
                <label class="block text-sm mb-1" for="cumple">Cumpleaños</label>
                <input id="cumple" name="cumple" type="date" class="field w-full">
              </div>
              <div>
                <label class="block text-sm mb-1" for="ciudad">Ciudad</label>
                <input id="ciudad" name="ciudad" class="field w-full" placeholder="Ciudad">
              </div>

              <div>
                <label class="block text-sm mb-1" for="correo">Correo</label>
                <input id="correo" name="correo" type="email" class="field w-full" placeholder="cliente@correo.com" autocomplete="email">
              </div>
              <div>
                <label class="block text-sm mb-1" for="medio">Medios</label>
                <select id="medio" name="medio" class="field w-full">
                  <option value="">Selecciona</option>
                  <option>Instagram</option>
                  <option>Facebook</option>
                  <option>WhatsApp</option>
                  <option>Recomendación</option>
                  <option>Otro</option>
                </select>
              </div>
              <div>
                <label class="block text-sm mb-1" for="estrato">Estrato</label>
                <input id="estrato" name="estrato" class="field w-full" placeholder="Estrato">
              </div>

              <div class="md:col-span-3">
                <label class="block text-sm mb-1" for="profesion">Profesión</label>
                <input id="profesion" name="profesion" class="field w-full" placeholder="Profesión">
              </div>
            </div>
          </section>

          <!-- DATOS RESERVA -->
          <section class="section p-6">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-100">Datos de la reserva</h2>
              <span class="chip inline-flex items-center gap-1"><span>Paso 2</span></span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
              <div class="md:col-span-2">
                <label class="block text-sm mb-1" for="agente">Agente</label>
                <input id="agente" name="agente" class="field w-full" placeholder="Nombre del asesor">
              </div>
              <div>
                <label class="block text-sm mb-1" for="fecha_ingreso">F. Ingreso *</label>
                <input id="fecha_ingreso" name="fecha_ingreso" type="date" required class="field w-full">
              </div>
              <div>
                <label class="block text-sm mb-1" for="noches">Noches *</label>
                <input id="noches" name="noches" type="number" min="1" value="1" required class="field w-full">
              </div>
              <div>
                <label class="block text-sm mb-1" for="adultos">Adultos *</label>
                <input id="adultos" name="adultos" type="number" min="1" value="2" required class="field w-full">
              </div>
              <div>
                <label class="block text-sm mb-1" for="menores">Menores</label>
                <input id="menores" name="menores" type="number" min="0" value="0" class="field w-full">
              </div>

              <div>
                <label class="block text-sm mb-1" for="hora">Hora</label>
                <input id="hora" name="hora" type="time" class="field w-full">
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm mb-1" for="plan">Plan</label>
                <select id="plan" name="plan" class="field w-full">
                  <option value="">Selecciona</option>
                  <?php foreach ($planes as $p): ?>
                    <option
                      value="<?php echo (int)$p['id']; ?>"
                      data-plan-id="<?php echo (int)$p['id']; ?>"
                    >
                      <?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-sm mb-1" for="codigo">Código</label>
                <input id="codigo" name="codigo" class="field w-full" placeholder="Se genera automáticamente">
              </div>
              <div>
                <label class="block text-sm mb-1" for="descuento">%</label>
                <input id="descuento" name="descuento" type="number" min="0" max="100" value="0" class="field w-full" placeholder="0–100">
              </div>
              <div>
                <label class="block text-sm mb-1" for="precio_unit">Precio unitario</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm">COP</span>
                  <input id="precio_unit" name="precio_unit" type="text" inputmode="numeric" data-format-miles value="0" class="field money w-full pl-12">
                </div>
                <p class="text-xs text-gray-500 mt-1">Por persona / noche.</p>
              </div>
              <div>
                <label class="block text-sm mb-1" for="parqueadero">Parqueadero</label>
                <select id="parqueadero" name="parqueadero" class="field w-full">
                  <option value="">Selecciona</option>
                  <option>Sí</option>
                  <option>No</option>
                </select>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm mb-1" for="valor_total">Valor</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm">COP</span>
                  <input id="valor_total" name="valor_total" type="text" inputmode="numeric" data-format-miles value="0" class="field money w-full pl-12">
                </div>
                <p class="text-xs text-gray-500 mt-1">Se ingresa manualmente (no autocalcula).</p>
              </div>
            </div>

            <div class="mt-4">
              <label class="block text-sm mb-1" for="observaciones">Observaciones</label>
              <textarea id="observaciones" name="observaciones" rows="3" class="field w-full" placeholder="Notas, preferencias, restricciones, etc."></textarea>
            </div>
          </section>

          <!-- ADICIONALES -->
          <section class="section p-6">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-100">Adicionales</h2>
              <span class="chip inline-flex items-center gap-1"><span id="svc_count">0</span> seleccionados</span>
            </div>

            <div class="mt-4 flex flex-col md:flex-row md:items-center gap-3">
              <div class="flex-1 relative">
                <input id="svc_search" class="field w-full pl-10" placeholder="Buscar servicio (ej. jetsky, picnic, masajes)">
                <svg class="w-5 h-5 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
              </div>
              <div class="flex items-center gap-2 text-sm">
                <button type="button" id="svc_collapse" class="px-3 py-2 rounded-lg border border-slate-600 bg-slate-900 hover:bg-slate-800">Contraer</button>
                <button type="button" id="svc_expand" class="px-3 py-2 rounded-lg border border-slate-600 bg-slate-900 hover:bg-slate-800">Expandir</button>
                <button type="button" id="svc_clear" class="px-3 py-2 rounded-lg border border-slate-600 bg-slate-900 hover:bg-slate-800">Limpiar</button>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4" id="svc_groups">
              <template id="svc_group_tpl">
                <details class="rounded-xl border border-slate-700 bg-slate-950/60 p-4 group open">
                  <summary class="flex items-center justify-between cursor-pointer select-none">
                    <div class="flex items-center gap-2">
                      <span class="font-medium group-open:text-brand-300 text-slate-100">__TITLE__</span>
                      <span class="ml-1 text-xs text-slate-500">(<span class="svc-in">0</span> ítems)</span>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 9l6 6 6-6"/></svg>
                  </summary>
                  <div class="mt-3 space-y-2 text-sm">__ROWS__</div>
                </details>
              </template>

              <template id="svc_row_tpl">
                <div class="flex items-center justify-between svc-row" data-precio="__PRECIO__">
                  <label class="flex items-center gap-2">
                    <input type="checkbox" class="h-4 w-4 svc-check" data-id-servicio="__ID__">
                    <span class="svc-name">__NAME__</span>
                  </label>
                  <input type="number" min="0" step="1" class="field w-20 qty" placeholder="0" disabled>
                </div>
              </template>

              <div id="svc_container" class="contents"></div>
            </div>
          </section>

          <!-- PAGOS -->
          <section class="section p-6">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-slate-100">Pagos</h2>
              <span class="chip inline-flex items-center gap-1"><span>Paso 4</span></span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-3" data-pay="1">
                <h4 class="font-medium mb-2 text-slate-100">Pago 1</h4>
                <label class="block text-sm mb-1">Fecha</label>
                <input name="pago1_fecha" type="date" class="field w-full mb-2">
                <label class="block text-sm mb-1">Valor</label>
                <input id="pago1_valor" name="pago1_valor" type="text" data-format-miles inputmode="numeric" class="field money w-full" data-locked="0">
                <label class="block text-sm mb-1 mt-2">Método de pago</label>
                <select name="pago1_metodo" class="field w-full" data-locked="0">
                  <option value="DATÁFONO">Datáfono</option>
                  <option value="TRANSFERENCIA">Transferencia</option>
                  <option value="EFECTIVO">Efectivo</option>
                </select>
                <div class="grid grid-cols-2 gap-2 mt-3">
                  <button type="button" id="pago1_button" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium">Registrar</button>
                  <button type="button" id="corregir1_button" class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium">Corregir</button>
                </div>
              </div>

              <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-3" data-pay="2">
                <h4 class="font-medium mb-2 text-slate-100">Pago 2</h4>
                <label class="block text-sm mb-1">Fecha</label>
                <input name="pago2_fecha" type="date" class="field w-full mb-2">
                <label class="block text-sm mb-1">Valor</label>
                <input id="pago2_valor" name="pago2_valor" type="text" data-format-miles inputmode="numeric" class="field money w-full" data-locked="0">
                <label class="block text-sm mb-1 mt-2">Método de pago</label>
                <select name="pago2_metodo" class="field w-full" data-locked="0">
                  <option value="DATÁFONO">Datáfono</option>
                  <option value="TRANSFERENCIA">Transferencia</option>
                  <option value="EFECTIVO">Efectivo</option>
                </select>
                <div class="grid grid-cols-2 gap-2 mt-3">
                  <button type="button" id="pago2_button" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium">Registrar</button>
                  <button type="button" id="corregir2_button" class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium">Corregir</button>
                </div>
              </div>

              <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-3" data-pay="3">
                <h4 class="font-medium mb-2 text-slate-100">Pago 3</h4>
                <label class="block text-sm mb-1">Fecha</label>
                <input name="pago3_fecha" type="date" class="field w-full mb-2">
                <label class="block text-sm mb-1">Valor</label>
                <input id="pago3_valor" name="pago3_valor" type="text" data-format-miles inputmode="numeric" class="field money w-full" data-locked="0">
                <label class="block text-sm mb-1 mt-2">Método de pago</label>
                <select name="pago3_metodo" class="field w-full" data-locked="0">
                  <option value="DATÁFONO">Datáfono</option>
                  <option value="TRANSFERENCIA">Transferencia</option>
                  <option value="EFECTIVO">Efectivo</option>
                </select>
                <div class="grid grid-cols-2 gap-2 mt-3">
                  <button type="button" id="pago3_button" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium">Registrar</button>
                  <button type="button" id="corregir3_button" class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium">Corregir</button>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4 items-end">
              <div>
                <label class="block text-sm mb-1" for="pago_total">Pago total</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm">COP</span>
                  <input id="pago_total" name="pago_total" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full pl-12">
                </div>
              </div>
              <div>
                <label class="block text-sm mb-1" for="pago_datafono">Datáfono</label>
                <input id="pago_datafono" name="pago_datafono" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full">
              </div>
              <div>
                <label class="block text-sm mb-1" for="pago_transferencia">Transferencia</label>
                <input id="pago_transferencia" name="pago_transferencia" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full">
              </div>
              <div>
                <label class="block text-sm mb-1" for="pago_efectivo">Efectivo</label>
                <input id="pago_efectivo" name="pago_efectivo" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full">
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
              <div>
                <label class="block text-sm mb-1" for="saldo">Saldo</label>
                <input id="saldo" name="saldo" type="text" data-format-miles inputmode="numeric" value="0" readonly class="field money w-full bg-slate-900/80 border-slate-700">
              </div>
            </div>

            <div class="flex justify-between items-center mt-6">
              <button type="button" id="btnLimpiar" class="px-5 py-2 rounded-xl border border-slate-600 bg-slate-900 hover:bg-slate-800">Limpiar</button>
              <div class="flex items-center gap-2">
                <button type="button" id="btnGuardarBorrador" class="px-4 py-2 rounded-xl border border-slate-600 bg-slate-900 hover:bg-slate-800">Guardar borrador</button>
                <button type="submit" class="px-6 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-semibold shadow-soft">Registrar</button>
              </div>
            </div>
          </section>
        </div>

        <!-- RESUMEN -->
        <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-24 h-max">
          <section class="section p-6">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-semibold text-slate-100">Resumen</h3>
              <span class="chip rounded-full text-xs">Vista previa</span>
            </div>
            <dl class="mt-4 divide-y divide-slate-800 text-sm" aria-live="polite">
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Cliente</dt><dd id="r_cliente" class="font-medium text-slate-100">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Contacto</dt><dd id="r_contacto" class="font-medium text-slate-100">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Plan</dt><dd id="r_plan" class="font-medium text-slate-100">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Ingreso</dt><dd id="r_ingreso" class="font-medium text-slate-100">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Noches</dt><dd id="r_noches" class="font-medium text-slate-100">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Personas</dt><dd id="r_personas" class="font-medium text-slate-100">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Valor</dt><dd id="r_valor" class="font-semibold text-emerald-300">COP 0</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-400">Saldo</dt><dd id="r_saldo" class="font-semibold text-brand-300">COP 0</dd></div>
            </dl>

            <!-- resumen de servicios -->
            <div class="mt-4 pt-4 border-t border-slate-800">
              <h4 class="text-sm font-semibold text-slate-100">Servicios seleccionados</h4>
              <ul id="r_servicios" class="mt-2 text-xs text-slate-400 space-y-1">
                <li class="italic text-slate-500">Sin servicios adicionales.</li>
              </ul>
              <p id="r_servicios_total" class="mt-2 text-xs font-semibold text-slate-200">
                Extras: COP 0
              </p>
            </div>
          </section>
          <section class="p-4 rounded-xl border border-amber-400/50 bg-amber-500/10 text-amber-100 text-sm">
            ⚠️ <strong>Recordatorio:</strong> el <em>Valor</em> total y los <em>Pagos</em> se ingresan manualmente. El <em>Saldo</em> se recalcula al guardar.
          </section>
        </aside>
      </form>
    </main>
  </div>

  <!-- BARRA INFERIOR MÓVIL -->
  <div class="fixed bottom-0 inset-x-0 md:hidden bg-slate-950/95 backdrop-blur border-t border-slate-800 p-3 flex items-center justify-between z-40">
    <div class="text-sm text-slate-100"><span class="font-medium">Saldo: </span><span id="r_saldo_m">COP 0</span></div>
    <button form="formReserva" type="submit" class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-semibold">Registrar</button>
  </div>

  <script>
    (function(){
      // Si Tailwind no está disponible, activamos modo "no-tw"
      if (!window.tailwind) {
        document.documentElement.classList.add('no-tw');
        document.getElementById('twBanner').style.display = 'block';
      }

      const app   = document.getElementById('app');
      const fab   = document.getElementById('sbFab');
      const scrim = document.getElementById('scrim');
      const brandToggle = document.getElementById('brandToggle');

      if(localStorage.getItem('sb_expanded') === '1'){
        app.classList.add('expanded');
      }
      const syncAria = () =>
        brandToggle?.setAttribute(
          'aria-expanded',
          app.classList.contains('expanded') ? 'true' : 'false'
        );

      function toggleSidebar(){
        app.classList.toggle('expanded');
        localStorage.setItem('sb_expanded', app.classList.contains('expanded') ? '1' : '0');
        syncAria();
      }
      fab.addEventListener('click', toggleSidebar);
      scrim.addEventListener('click', toggleSidebar);
      brandToggle?.addEventListener('click', toggleSidebar);
      window.addEventListener('keydown', (e)=>{
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
          e.preventDefault();
          toggleSidebar();
        }
        if (e.key === 'Escape' && app.classList.contains('expanded')) toggleSidebar();
      });
      syncAria();

      const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
      const $  = (s, r=document) => r.querySelector(s);
      const fmt = (n) => new Intl.NumberFormat('es-CO').format(Number(n||0));
      const unfmt = (s) => Number(String(s||'0').replace(/[^0-9]/g, ''));

      const svcJsonInput = document.getElementById('servicios_json');

      function attachMoneyMask(el){
        el.addEventListener('input', () => {
          const val = unfmt(el.value);
          el.value = fmt(val);
          el.setSelectionRange(el.value.length, el.value.length);
          recalc();
        });
        el.addEventListener('blur', () => el.value = fmt(unfmt(el.value)) );
      }
      $$('input[data-format-miles]').forEach(attachMoneyMask);

      const cumple = $('#cumple'), edad = $('#edad');
      if (cumple) {
        cumple.addEventListener('change', () => {
          const d = new Date(cumple.value);
          if (!isNaN(d)){
            const today = new Date();
            let e = today.getFullYear() - d.getFullYear();
            const m = today.getMonth() - d.getMonth();
            if (m < 0 || (m===0 && today.getDate() < d.getDate())) e--;
            edad.value = e;
          } else {
            edad.value = '';
          }
        });
      }

      // === Servicios dinámicos ===
      const groupTpl   = document.getElementById('svc_group_tpl').innerHTML;
      const rowTpl     = document.getElementById('svc_row_tpl').innerHTML;
      const container  = document.getElementById('svc_container');
      const svcSearch  = document.getElementById('svc_search');
      const svcExpand  = document.getElementById('svc_expand');
      const svcCollapse= document.getElementById('svc_collapse');
      const svcClear   = document.getElementById('svc_clear');
      const planSelect = document.getElementById('plan');

      function getSelectedPlanId() {
        if (!planSelect) return null;
        const opt = planSelect.options[planSelect.selectedIndex];
        return opt ? opt.getAttribute('data-plan-id') : null;
      }

      // Detectar si el plan actual es un "plan pareja"
      function isPlanPareja() {
        if (!planSelect) return false;
        const opt = planSelect.options[planSelect.selectedIndex];
        if (!opt) return false;
        const name = (opt.textContent || '').toLowerCase();
        return name.includes('pareja');
      }

      // 👉 Reglas de inclusión (por persona + excepciones)
      function computeIncludedQtyForService(s, personas) {
        const base = Number(s.cantidad_incluida || 0);
        const baseOrOne = base > 0 ? base : 1;
        const nameLower = String(s.nombre || '').toLowerCase();
        const pareja = isPlanPareja();

        // Camping
        if (nameLower.includes('camping')) {
          if (personas <= 0) return 0;
          if (pareja) {
            const parejas = Math.max(1, Math.round(personas / 2));
            return baseOrOne * parejas;
          }
          const bloques = Math.max(1, Math.ceil(personas / 4));
          return baseOrOne * bloques;
        }

        // Kit fogata
        if (nameLower.includes('kit fogata') || nameLower.includes('fogata')) {
          if (pareja) {
            if (personas < 2) return 0;
            const parejas = Math.max(1, Math.round(personas / 2));
            return baseOrOne * parejas;
          }
          if (personas < 3) return 0;
          const bloques = Math.floor(personas / 3);
          return baseOrOne * Math.max(1, bloques);
        }

        // Siembra una planta
        if (
          nameLower.includes('siembra una planta') ||
          (nameLower.includes('siembra') && nameLower.includes('planta'))
        ) {
          if (pareja) {
            if (personas < 2) return 0;
            const parejas = Math.max(1, Math.round(personas / 2));
            return baseOrOne * parejas;
          }
          if (personas < 3) return 0;
          const bloques = Math.floor(personas / 3);
          return baseOrOne * Math.max(1, bloques);
        }

        // Globo de cantoya
        if (nameLower.includes('globo de cantoya') || nameLower.includes('cantoya')) {
          if (personas < 3) return 0;
          return baseOrOne;
        }

        // Bandeja de cervezas
        if (
          nameLower.includes('bandeja de cerveza') ||
          (nameLower.includes('bandeja') && nameLower.includes('cervez'))
        ) {
          if (personas < 3) return 0;
          const bloques = Math.floor(personas / 3);
          return baseOrOne * Math.max(1, bloques);
        }

        // Regla general: servicio incluido por persona
        return base * personas;
      }

      function updateSvcCounters(){
        const checked = $$('#svc_container .svc-check:checked').length;
        document.getElementById('svc_count').textContent = checked;
        $$('#svc_container details').forEach(det => {
          const inside = det.querySelectorAll('.svc-check').length;
          det.querySelector('.svc-in').textContent = inside;
        });
      }

      function getPlanIncludedQtyMap() {
        const pid = getSelectedPlanId();
        const map = {};

        const ad = Number(document.getElementById('adultos')?.value || 0);
        const me = Number(document.getElementById('menores')?.value || 0);
        const personas = ad + me;

        if (pid && PLAN_SERVICIOS[pid]) {
          PLAN_SERVICIOS[pid].forEach(s => {
            const id = parseInt(s.id_servicio, 10);
            const baseQty = computeIncludedQtyForService(s, personas);
            map[id] = baseQty;
          });
        }
        return map;
      }

      function buildServiciosJson() {
        const payload = [];
        const incluidosMap = getPlanIncludedQtyMap();

        $$('#svc_container .svc-row').forEach(row => {
          const chk = row.querySelector('.svc-check');
          if (!chk || !chk.checked) return;

          const id = parseInt(chk.dataset.idServicio || '0', 10);
          if (!id) return;

          const name = row.querySelector('.svc-name')?.textContent.trim() || '';
          const qtyInput = row.querySelector('.qty');
          const cantTotal = Number(qtyInput?.value || 0);
          if (cantTotal <= 0) return;

          const incl = incluidosMap[id] || 0;
          const cantExtra = Math.max(cantTotal - incl, 0);

          const precioUnit = Number(row.dataset.precio || 0);
          const valorExtra = precioUnit * cantExtra;

          payload.push({
            id_servicio: id,
            nombre: name,
            cantidad_total: cantTotal,
            cantidad_incluida: incl,
            cantidad_extra: cantExtra,
            precio_unit: precioUnit,
            valor_extra: valorExtra
          });
        });

        svcJsonInput.value = JSON.stringify(payload);
        return payload;
      }

      function renderServices(filter='') {
        container.innerHTML = '';

        const pid = getSelectedPlanId();
        let incluidos = [];
        let incluidosMap = {};

        const ad = Number(document.getElementById('adultos')?.value || 0);
        const me = Number(document.getElementById('menores')?.value || 0);
        const personas = ad + me;

        if (pid && PLAN_SERVICIOS[pid]) {
          PLAN_SERVICIOS[pid].forEach(s => {
            const id = parseInt(s.id_servicio, 10);
            const baseQty = computeIncludedQtyForService(s, personas);
            if (baseQty > 0) {
              incluidos.push(id);
              incluidosMap[id] = baseQty;
            }
          });
        }

        const grouped = {};

        (ALL_SERVICIOS || []).forEach(s => {
          const grupo = s.categoria || 'Servicios';
          if (!grouped[grupo]) grouped[grupo] = [];

          if (filter && !String(s.nombre).toLowerCase().includes(filter.toLowerCase())) return;

          grouped[grupo].push({
            id_servicio: parseInt(s.id_servicio, 10),
            nombre: s.nombre,
            precio: Number(s.precio_base || 0),
            incluido: incluidos.includes(parseInt(s.id_servicio, 10))
          });
        });

        Object.keys(grouped).forEach(grpName => {
          const list = grouped[grpName];
          if (!list.length) return;

          let rows = '';
          list.forEach(s => {
            rows += rowTpl
                .replace(/__NAME__/g, s.nombre)
                .replace(/__ID__/g, s.id_servicio)
                .replace(/__PRECIO__/g, s.precio);
          });

          const html = groupTpl
            .replace('__TITLE__', grpName)
            .replace('__ROWS__', rows);

          const frag = document.createElement('div');
          frag.innerHTML = html;
          const det = frag.firstElementChild;

          container.appendChild(det);
        });

        // Marcar y setear cantidad por defecto según las reglas
        $$('#svc_container .svc-row').forEach(row => {
          const chk = row.querySelector('.svc-check');
          const qty = row.querySelector('.qty');
          const id  = parseInt(chk.getAttribute('data-id-servicio') || '0', 10);

          if (incluidos.includes(id)) {
            chk.checked = true;
            qty.disabled = false;
            const cant = incluidosMap[id] && incluidosMap[id] > 0 ? incluidosMap[id] : 1;
            qty.value = cant;
          }
        });

        updateSvcCounters();

        $$('#svc_container .svc-check').forEach(chk => {
          chk.addEventListener('change', (e)=> {
            const row = e.target.closest('.svc-row');
            const qty = row.querySelector('.qty');
            qty.disabled = !e.target.checked;
            if (!e.target.checked) qty.value = '';
            updateSvcCounters();
            recalc();
          });
        });

        $$('#svc_container .qty').forEach(input => {
          input.addEventListener('input', () => {
            recalc();
          });
        });
      }

      planSelect.addEventListener('change', () => {
        renderServices(svcSearch.value || '');
        recalc();
      });

      svcSearch.addEventListener('input', (e)=> {
        renderServices(e.target.value || '');
        recalc();
      });

      svcExpand.addEventListener('click', ()=> $$('#svc_container details').forEach(d=> d.open = true));
      svcCollapse.addEventListener('click', ()=> $$('#svc_container details').forEach(d=> d.open = false));
      svcClear.addEventListener('click', ()=> {
        $$('#svc_container .svc-check:checked').forEach(ch => {
          ch.checked=false;
          ch.dispatchEvent(new Event('change'));
        });
        recalc();
      });

      // Cuando cambian adultos/menores, se recalculan incluidos
      ['adultos','menores'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
          el.addEventListener('input', () => {
            renderServices(svcSearch.value || '');
            recalc();
          });
        }
      });

      renderServices();

      function recalc(){

        const nombre = document.getElementById('nombre');
        const whatsapp = document.getElementById('whatsapp');
        const correo = document.getElementById('correo');
        const plan = document.getElementById('plan');
        const fecha_ingreso = document.getElementById('fecha_ingreso');
        const noches = document.getElementById('noches');
        const adultos = document.getElementById('adultos');
        const menores = document.getElementById('menores');
        const valor_total = document.getElementById('valor_total');
        const saldo = document.getElementById('saldo');
        const pago1_valor = document.getElementById('pago1_valor');
        const pago2_valor = document.getElementById('pago2_valor');
        const pago3_valor = document.getElementById('pago3_valor');

        // Resumen cliente
        document.getElementById('r_cliente').textContent = (nombre.value || '—');

        const tel = whatsapp.value.trim();
        const email = correo.value.trim();
        document.getElementById('r_contacto').textContent = [tel, email].filter(Boolean).join(' · ') || '—';

        let planLabel = '—';
        if (plan && plan.selectedIndex > 0) {
          planLabel = plan.options[plan.selectedIndex].textContent.trim();
        }
        document.getElementById('r_plan').textContent = planLabel;

        document.getElementById('r_ingreso').textContent = (fecha_ingreso.value || '—');

        const ad = Number(adultos.value || 0);
        const me = Number(menores.value || 0);

        document.getElementById('r_noches').textContent = (noches.value || '—');
        document.getElementById('r_personas').textContent = (ad + me) || '—';

        // Extras + lista
        const servicios = buildServiciosJson();
        let totalExtras = 0;

        const lista = document.getElementById('r_servicios');
        lista.innerHTML = '';

        if (!servicios.length) {
          const li = document.createElement('li');
          li.textContent = 'Sin servicios adicionales.';
          li.className = 'italic text-slate-500';
          lista.appendChild(li);
        } else {
          servicios.forEach(s => {
            totalExtras += Number(s.valor_extra || 0);

            const li = document.createElement('li');
            let texto = `${s.nombre} · ${s.cantidad_total}`;

            if (s.cantidad_incluida > 0) {
              texto += ` (incluye ${s.cantidad_incluida}`;
              if (s.cantidad_extra > 0) {
                texto += ` + ${s.cantidad_extra} extra`;
              }
              texto += ')';
            }

            if (s.valor_extra > 0) {
              texto += ` → COP ${fmt(s.valor_extra)}`;
            }

            li.textContent = texto;
            lista.appendChild(li);
          });
        }

        document.getElementById('r_servicios_total').textContent =
          'Extras: COP ' + fmt(totalExtras);

        // Valor total final = base (manual) + extras
        const valorBase = unfmt(valor_total.value);
        const valorFinal = valorBase + totalExtras;

        document.getElementById('r_valor').textContent =
          'COP ' + fmt(valorFinal);

        // Saldo = valor final - pagos
        const pagos =
          unfmt(pago1_valor.value) +
          unfmt(pago2_valor.value) +
          unfmt(pago3_valor.value);

        const saldoFinal = Math.max(valorFinal - pagos, 0);

        saldo.value = fmt(saldoFinal);
        document.getElementById('r_saldo').textContent =
          'COP ' + fmt(saldoFinal);
        document.getElementById('r_saldo_m').textContent =
          'COP ' + fmt(saldoFinal);
      }

      function recalcPayments(){
        const pago1_valor = document.getElementById('pago1_valor');
        const pago2_valor = document.getElementById('pago2_valor');
        const pago3_valor = document.getElementById('pago3_valor');
        const pago_total  = document.getElementById('pago_total');

        const v1 = unfmt(pago1_valor.value);
        const v2 = unfmt(pago2_valor.value);
        const v3 = unfmt(pago3_valor.value);

        const totalPagos = v1 + v2 + v3;
        pago_total.value = fmt(totalPagos);

        recalc();
      }

      ['#pago1_valor','#pago2_valor','#pago3_valor','#valor_total'].forEach(sel => {
        const el = document.querySelector(sel);
        if (el) el.addEventListener('input', recalcPayments);
      });

      // Bloqueo / corrección de pagos
      [1,2,3].forEach(i=>{
        const btnReg = document.getElementById(`pago${i}_button`);
        const btnCor = document.getElementById(`corregir${i}_button`);
        const inpVal = document.getElementById(`pago${i}_valor`);
        const selMet = document.querySelector(`[name="pago${i}_metodo"]`);

        if (!btnReg || !btnCor || !inpVal || !selMet) return;

        // Si el servidor en algún momento marca data-locked="1", respetar eso
        if (inpVal.dataset.locked === '1') {
          inpVal.readOnly = true;
          selMet.style.pointerEvents = 'none';
          selMet.classList.add('opacity-60','cursor-not-allowed');
          btnReg.classList.add('opacity-60','pointer-events-none');
        }

        btnReg.addEventListener('click', ()=>{
          inpVal.readOnly = true;
          inpVal.dataset.locked = '1';

          selMet.style.pointerEvents = 'none';
          selMet.classList.add('opacity-60','cursor-not-allowed');
          selMet.dataset.locked = '1';

          btnReg.classList.add('opacity-60','pointer-events-none');
          recalcPayments();
        });

        btnCor.addEventListener('click', ()=>{
          inpVal.readOnly = false;
          inpVal.dataset.locked = '0';

          selMet.style.pointerEvents = '';
          selMet.classList.remove('opacity-60','cursor-not-allowed');
          selMet.dataset.locked = '0';

          btnReg.classList.remove('opacity-60','pointer-events-none');
          inpVal.focus();
        });
      });

      // Escuchar cambios generales del formulario
      $$('#formReserva input, #formReserva select, #formReserva textarea').forEach(el =>
        el.addEventListener('input', recalc)
      );
      recalc();

      document.getElementById('btnLimpiar').addEventListener('click', ()=>{
        document.getElementById('formReserva').reset();
        document.getElementById('svc_search').value='';
        if (svcJsonInput) svcJsonInput.value = '[]';
        renderServices();
        recalcPayments();
        recalc();
      });

      document.getElementById('formReserva').addEventListener('submit', (e)=>{
        buildServiciosJson();

        const valor = unfmt(document.getElementById('valor_total').value);
        const totalPagos = unfmt(document.getElementById('pago_total').value);
        if (totalPagos > valor) {
          e.preventDefault();
          alert('El total de pagos no puede superar el Valor de la reserva.');
          return false;
        }
      });
    })();
  </script>
</body>
</html>
