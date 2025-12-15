<?php
// registro_reserva.php

// Incluye tu clase de conexión (ajusta la ruta si está en otra carpeta)
require_once __DIR__ . '/../config/conexion.php';

$planes        = [];
$planServicios = [];
$serviciosAll  = [];

try {
    // Crear PDO desde tu clase conexion
    $pdo = conexion::getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1) Planes desde tabla `planes`
    $sqlPlanes = "SELECT id_plan, nombre FROM planes WHERE activo = 1 ORDER BY nombre";
    $stmt      = $pdo->query($sqlPlanes);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $planes[] = [
            'id'     => (int)$row['id_plan'],
            'nombre' => $row['nombre'],
        ];
    }

    // 2) Servicios por plan desde `planes_servicios` + `servicios`
    // planes_servicios: id_plan, id_servicio, cantidad_incluida
    // servicios: id_servicio, categoria, nombre, precio_base
    $sqlServicios = "
        SELECT 
            ps.id_plan,
            ps.id_servicio,
            COALESCE(ps.cantidad_incluida, 1) AS cantidad_incluida,
            s.nombre    AS nombre_servicio,
            s.categoria AS grupo
        FROM planes_servicios ps
        INNER JOIN servicios s ON s.id_servicio = ps.id_servicio
        ORDER BY ps.id_plan, s.categoria, s.nombre
    ";
    $stmt2 = $pdo->query($sqlServicios);

    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$row['id_plan'];
        if (!isset($planServicios[$pid])) {
            $planServicios[$pid] = [];
        }
        $planServicios[$pid][] = [
            'id'        => (int)$row['id_servicio'],
            'nombre'    => $row['nombre_servicio'],
            'grupo'     => $row['grupo'] ?: 'Servicios',
            'incluidas' => (int)$row['cantidad_incluida'],
        ];
    }

    // 3) TODOS los servicios disponibles (para siempre mostrarlos como adicionales)
    $sqlServiciosAll = "
        SELECT 
            id_servicio,
            nombre,
            categoria,
            COALESCE(precio_base, 0) AS precio_base
        FROM servicios
        ORDER BY categoria, nombre
    ";
    $stmtAll = $pdo->query($sqlServiciosAll);

    while ($row = $stmtAll->fetch(PDO::FETCH_ASSOC)) {
        $serviciosAll[] = [
            'id'     => (int)$row['id_servicio'],
            'nombre' => $row['nombre'],
            'grupo'  => $row['categoria'] ?: 'Servicios',
            'precio' => (float)$row['precio_base'],
        ];
    }

} catch (Exception $e) {
    // En desarrollo puedes descomentar esto para ver el detalle:
    // echo "Error BD: " . $e->getMessage();
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
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50:'#eefdf7',100:'#d6faed',200:'#b1f4de',300:'#7ae9c9',
              400:'#3dd6b0',500:'#12b48f',600:'#0f9477',700:'#0d7863',
              800:'#0d5e50',900:'#0b4c42',950:'#062b26'
            }
          },
          boxShadow: {
            soft:'0 6px 18px -6px rgb(16 185 129 / 0.20), 0 2px 8px -2px rgb(15 23 42 / 0.08)'
          },
          fontFamily: { inter:["Inter","system-ui","-apple-system","Segoe UI","Roboto","sans-serif"] }
        }
      }
    }
  </script>

  <style>
    :root{
      --bg:#f6f8fb; --card:#ffffff; --ink:#0f172a; --muted:#64748b;
      --brand:#0f7a66; --brand-2:#18b795; --brand-3:#43d9b6;
      --ring:0 0 0 3px rgba(24,183,149,.28);
      --shadow-1:0 2px 8px rgba(2,6,23,.05);
      --shadow-2:0 12px 34px rgba(2,6,23,.07);
      --rail-w-expanded:260px;
      --fab-overlap:22px;
      --brand-grad: linear-gradient(90deg,#0f9477 0%,#18b795 45%,#43d9b6 100%);
      --brand-logo-url: url("https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png");
    }

    html, body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
    body{
      margin:0; color:var(--ink);
      background:
        radial-gradient(1000px 700px at 8% -10%, rgba(24,183,149,.10), transparent 70%),
        radial-gradient(900px 600px at 110% -20%, rgba(67,217,182,.08), transparent 70%),
        linear-gradient(180deg,#f8fafc 0%, var(--bg) 100%);
    }

    .app{ min-height:100dvh; position:relative; }
    .main{ max-width:1200px; margin:0 auto; padding:12px 10px 40px; }

    .sidebar{
      position:fixed; inset:10px auto 10px 10px; width:var(--rail-w-expanded);
      background:#fff; border:1px solid #e8eef5; border-radius:18px; box-shadow:var(--shadow-2);
      display:flex; flex-direction:column; transform:translateX(-120%);
      transition:transform .25s ease; z-index:9998; overflow:hidden;
    }
    .app.expanded .sidebar{ transform:translateX(0); }

    .scrim{
      position:fixed; inset:0; background:rgba(2,6,23,.25);
      backdrop-filter:saturate(120%) blur(1px);
      opacity:0; pointer-events:none; transition:opacity .2s ease; z-index:9997;
    }
    .app.expanded .scrim{ opacity:1; pointer-events:auto; }

    .sb-head{
      display:flex; align-items:center; gap:10px; padding:12px;
      border-bottom:1px solid #eef2f7; position:relative;
    }
    .sb-head::after{
      content:""; position:absolute; left:12px; right:12px; bottom:0; height:3px; border-radius:999px;
      background: var(--brand-grad); opacity:.65;
    }
    .brand-mark{
      width:38px;height:38px;border-radius:12px;display:grid;place-items:center;
      background:#fff;border:1px solid #dff5ee;box-shadow:var(--shadow-1);overflow:hidden
    }
    .brand-mark img{width:100%;height:100%;object-fit:contain;display:block}
    .brand-info{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .brand-title{font-weight:900; letter-spacing:.2px}
    .brand-sub{font-size:12px;color:var(--muted);margin-top:2px}

    .sb-search{ padding:10px 12px; border-bottom:1px solid #eef2f7; }
    .sb-search input{
      width:100%; border:1px solid #e8eef5; border-radius:12px; padding:10px 12px; font-size:14px;
      background:#fff; box-shadow:var(--shadow-1); outline:none
    }
    .sb-search input:focus{ box-shadow:var(--ring),var(--shadow-1); border-color:#cfe9e2 }

    .sb-nav{ padding:6px }
    .sb-item{ display:flex; align-items:center; gap:12px; padding:10px; margin:3px 6px; border-radius:12px; color:#0f172a; text-decoration:none; font-weight:600 }
    .sb-item:hover{ background:#f6f9fc }
    .sb-item svg{ width:20px; height:20px; color:#0f172a }
    .sb-item.active{ background:#edf7f4; border:1px solid #cfe9e2 }
    .sb-item.active svg{ color:var(--brand) } .sb-item.active .sb-label{ color:var(--brand); font-weight:800 }

    .sb-foot{ margin-top:auto; padding:10px; border-top:1px solid #eef2f7; }
    .card-prof{ border:1px solid #e8eef5; border-radius:14px; padding:10px; background:#fff; }
    .prof-row{ display:flex; align-items:center; gap:10px }
    .avatar{ width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#ffdd55,#7ae9c9) }
    .prof-name{ font-weight:700 } .prof-sub{ font-size:12px; color:var(--muted) }
    .prof-actions{ display:grid; gap:6px; margin-top:8px }
    .btn{ padding:10px 12px; border-radius:12px; border:1px solid #e8eef5; background:#fff; font-weight:600; cursor:pointer }
    .btn-cta{ background:linear-gradient(90deg,var(--brand),var(--brand-2)); color:#fff; border:0 }

    .sb-fab{
      position: fixed; top: 50%; left: max(8px, env(safe-area-inset-left) + 8px);
      transform: translateY(-50%); width: 40px; height: 40px; border-radius: 999px;
      background:#fff; border:1px solid #d7e4e1; box-shadow: 0 8px 22px rgba(2,6,23,.18), 0 2px 8px rgba(2,6,23,.10);
      display:grid; place-items:center; cursor:pointer; z-index: 9999;
    }
    .sb-fab svg{ width:18px; height:18px; color:#0f172a }
    .sb-fab:hover{ transform: translateY(-50%) scale(1.02); }
    .app.expanded .sb-fab{ left: calc(env(safe-area-inset-left) + var(--rail-w-expanded) - var(--fab-overlap)); }
    .app.expanded .sb-fab svg{ transform: scaleX(-1); }

    @media (min-width:1024px){ .scrim{ background:transparent } }
    @media (prefers-reduced-motion: reduce){
      .sidebar, .scrim, .sb-fab { transition:none !important; }
    }

    .topbar{
      position: sticky; top: 0; z-index: 10000;
      background: rgba(255,255,255,.86);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid #e8eef5;
    }
    .topbar::before{
      content:""; position:absolute; left:0; right:0; top:0; height:3px;
      background: var(--brand-grad);
    }
    .topbar-inner{
      max-width: 1200px; margin: 0 auto; padding: 10px 12px;
      display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: center;
    }
    .brand-chip{ display:flex; align-items:center; gap:12px; padding:6px 8px; border-radius:14px; }
    .brand-chip:hover{ background:#f5fbf9; }
    .brand-chip .mark{
      width:34px; height:34px; border-radius:10px; overflow:hidden; display:grid; place-items:center;
      background:#fff; border:1px solid #dff5ee; box-shadow: var(--shadow-1);
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

    /* Servicios incluidos visualmente */
    .svc-row.svc-included{
      background:#ecfdf5;
      border-radius:12px;
      padding-inline:6px;
    }
  </style>

  <!-- Utilidades mínimas si TW no carga -->
  <style>
    .no-tw .section{border:1px solid #e5e7eb;border-radius:16px;background:#fff}
    .no-tw .field{border:1px solid #e5e7eb;border-radius:12px;padding:.65rem .9rem}
    .no-tw .chip{background:#eef2ff;border:1px solid #c7d2fe;border-radius:999px;padding:.25rem .6rem;font-size:.75rem}
  </style>

  <!-- Datos JS: servicios por plan + todos los servicios -->
  <script>
    const PLAN_SERVICIOS = <?php
      echo json_encode(
        $planServicios,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
      );
    ?>;

    const SERVICIOS_ALL = <?php
      echo json_encode(
        $serviciosAll,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
      );
    ?>;
  </script>
</head>

<body class="bg-gray-50 text-gray-800 min-h-dvh">
  <!-- Aviso si no carga TW -->
  <div id="twBanner" style="display:none;position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;background:#111827;color:#fff;padding:10px 14px;border-radius:12px">
    Tailwind no se ha podido cargar. Se aplicaron estilos básicos.
  </div>

  <div class="app" id="app">
    <!-- ===== SIDEBAR ===== -->
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
        <a href="checkin.html" class="sb-item" title="Check-in">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18v10H3V10zm0 5h18M7 10V7a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
          <span class="sb-label">Check-in</span>
        </a>
        <a href="Disponibilidad.html" class="sb-item" title="Disponibilidad">
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

      <div class="sb-foot">
        <div class="card-prof">
          <div class="prof-row">
            <span class="avatar" aria-hidden="true"></span>
            <div>
              <div class="prof-name">John Doe</div>
              <div class="prof-sub">john@mystic.co</div>
            </div>
          </div>
          <div class="prof-actions">
            <button class="btn" type="button">Ver perfil</button>
            <button class="btn" type="button">Ajustes</button>
            <button class="btn btn-cta" type="button">Registrar</button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Scrim + FAB -->
    <div class="scrim" id="scrim" aria-hidden="true"></div>
    <button class="sb-fab" id="sbFab" aria-label="Expandir/colapsar sidebar" title="Expandir/colapsar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6l6 6-6 6"/>
      </svg>
    </button>

    <!-- ===== TOP BAR ===== -->
    <header class="topbar" role="banner" aria-label="Barra superior">
      <div class="topbar-inner">
        <div class="flex items-center gap-5">
          <button class="brand-chip" type="button" id="brandToggle" title="Mostrar/ocultar menú" aria-controls="sidebar" aria-expanded="false">
            <div class="mark" aria-hidden="true"></div>
            <div class="name">MYSTIC PARADISE</div>
          </button>
          <div class="hidden sm:block">
            <p class="text-xs text-slate-500">Backoffice · Registro</p>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-800">Registro</h1>
          </div>
        </div>
        <nav class="hidden md:flex items-center gap-2">
          <a href="#" class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Volver</a>
          <a href="#perfil" class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Mi perfil</a>
          <a href="#ayuda" class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Ayuda</a>
        </nav>
      </div>
    </header>

    <!-- ===== CONTENIDO PRINCIPAL (formulario) ===== -->
    <main class="main">
   <form id="formReserva" action="../Controlador/CtRegistroReserva.php" method="post" class="grid grid-cols-1 lg:grid-cols-12 gap-6" novalidate>
        <!-- LEFT -->
        <div class="lg:col-span-8 space-y-8">
          <!-- DATOS DEL CLIENTE -->
          <section class="section p-6 rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Datos del cliente</h2>
              <span class="chip inline-flex items-center gap-1 rounded-full border border-brand-100 bg-brand-50 px-2.5 py-1 text-xs text-brand-700">Paso 1</span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm mb-1" for="nombre">Nombre *</label>
                <input id="nombre" name="nombre" required class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-300" placeholder="Nombre y apellido" autocomplete="name">
                <p class="mt-1 text-xs text-slate-500">Como aparece en el documento.</p>
              </div>
              <div>
                <label class="block text-sm mb-1" for="cedula">Cédula *</label>
                <input id="cedula" name="cedula" required class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="1012345678" inputmode="numeric" autocomplete="off">
              </div>
              <div>
                <label class="block text-sm mb-1" for="whatsapp">WhatsApp *</label>
                <input id="whatsapp" name="whatsapp" required pattern="[0-9+\s-]{7,20}" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="300 123 4567" autocomplete="tel">
              </div>

              <div>
                <label class="block text-sm mb-1" for="edad">Edad</label>
                <input id="edad" name="edad" type="number" readonly class="field w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5">
              </div>
              <div>
                <label class="block text-sm mb-1" for="cumple">Cumpleaños</label>
                <input id="cumple" name="cumple" type="date" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
              <div>
                <label class="block text-sm mb-1" for="ciudad">Ciudad</label>
                <input id="ciudad" name="ciudad" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="Ciudad">
              </div>

              <div>
                <label class="block text-sm mb-1" for="correo">Correo</label>
                <input id="correo" name="correo" type="email" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="cliente@correo.com" autocomplete="email">
              </div>
              <div>
                <label class="block text-sm mb-1" for="medio">Medios</label>
                <select id="medio" name="medio" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                  <option value="">Selecciona</option><option>Instagram</option><option>Facebook</option><option>WhatsApp</option><option>Recomendación</option><option>Otro</option>
                </select>
              </div>
              <div>
                <label class="block text-sm mb-1" for="estrato">Estrato</label>
                <input id="estrato" name="estrato" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="Estrato">
              </div>

              <div class="md:col-span-3">
                <label class="block text-sm mb-1" for="profesion">Profesión</label>
                <input id="profesion" name="profesion" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="Profesión">
              </div>
            </div>
          </section>

          <!-- DATOS DE LA RESERVA -->
          <section class="section p-6 rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Datos de la reserva</h2>
              <span class="chip inline-flex items-center gap-1 rounded-full border border-brand-100 bg-brand-50 px-2.5 py-1 text-xs text-brand-700">Paso 2</span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
              <div class="md:col-span-2">
                <label class="block text-sm mb-1" for="agente">Agente</label>
                <input id="agente" name="agente" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="Nombre del asesor">
              </div>
              <div>
                <label class="block text-sm mb-1" for="fecha_ingreso">F. Ingreso *</label>
                <input id="fecha_ingreso" name="fecha_ingreso" type="date" required class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
              <div>
                <label class="block text-sm mb-1" for="noches">Noches *</label>
                <input id="noches" name="noches" type="number" min="1" value="1" required class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
              <div>
                <label class="block text-sm mb-1" for="adultos">Adultos *</label>
                <input id="adultos" name="adultos" type="number" min="1" value="2" required class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
              <div>
                <label class="block text-sm mb-1" for="menores">Menores</label>
                <input id="menores" name="menores" type="number" min="0" value="0" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>

              <div>
                <label class="block text-sm mb-1" for="hora">Hora</label>
                <input id="hora" name="hora" type="time" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm mb-1" for="plan">Plan</label>
                <select id="plan" name="plan" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                  <option value="">Selecciona</option>
                  <?php foreach ($planes as $p): ?>
                    <option
                      value="<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                      data-plan-id="<?php echo (int)$p['id']; ?>"
                    >
                      <?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-sm mb-1" for="codigo">Código</label>
                <input id="codigo" name="codigo" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="Se genera automáticamente">
              </div>
              <div>
                <label class="block text-sm mb-1" for="descuento">%</label>
                <input id="descuento" name="descuento" type="number" min="0" max="100" value="0" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="0–100">
              </div>
              <div>
                <label class="block text-sm mb-1" for="precio_unit">Precio unitario</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">COP</span>
                  <input id="precio_unit" name="precio_unit" type="text" inputmode="numeric" data-format-miles value="0" class="field money w-full rounded-xl border border-gray-300 bg-white pl-12 px-3 py-2.5">
                </div>
                <p class="text-xs text-gray-500 mt-1">Por persona / noche</p>
              </div>
              <div>
                <label class="block text-sm mb-1" for="parqueadero">Parqueadero</label>
                <select id="parqueadero" name="parqueadero" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                  <option value="">Selecciona</option><option>Sí</option><option>No</option>
                </select>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm mb-1" for="valor_total">Valor</label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">COP</span>
                  <input id="valor_total" name="valor_total" type="text" inputmode="numeric" data-format-miles value="0" class="field money w-full rounded-xl border border-gray-300 bg-white pl-12 px-3 py-2.5">
                </div>
                <p class="text-xs text-gray-500 mt-1">Se ingresa manualmente (no autocalcula).</p>
              </div>
            </div>

            <div class="mt-4">
              <label class="block text-sm mb-1" for="observaciones">Observaciones</label>
              <textarea id="observaciones" name="observaciones" rows="3" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5" placeholder="Notas, preferencias, restricciones, etc."></textarea>
            </div>
          </section>

          <!-- ADICIONALES / SERVICIOS -->
          <section class="section p-6 rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Adicionales</h2>
              <span class="chip inline-flex items-center gap-1 rounded-full border border-brand-100 bg-brand-50 px-2.5 py-1 text-xs text-brand-700"><span id="svc_count">0</span> seleccionados</span>
            </div>

            <div class="mt-4 flex flex-col md:flex-row md:items-center gap-3">
              <div class="flex-1 relative">
                <input id="svc_search" class="field w-full rounded-xl border border-gray-300 bg-white pl-10 px-3 py-2.5" placeholder="Buscar servicio (ej. jetsky, picnic, masajes)">
                <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
              </div>
              <div class="flex items-center gap-2 text-sm">
                <button type="button" id="svc_collapse" class="px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Contraer</button>
                <button type="button" id="svc_expand" class="px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Expandir</button>
                <button type="button" id="svc_clear" class="px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Limpiar</button>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4" id="svc_groups">
              <template id="svc_group_tpl">
                <details class="rounded-xl border border-gray-200 bg-white p-4 group open">
                  <summary class="flex items-center justify-between cursor-pointer select-none">
                    <div class="flex items-center gap-2">
                      <span class="font-medium group-open:text-brand-700">__TITLE__</span>
                      <span class="ml-1 text-xs text-slate-500">(<span class="svc-in">0</span> ítems)</span>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 9l6 6 6-6"/></svg>
                  </summary>
                  <div class="mt-3 space-y-2 text-sm">__ROWS__</div>
                </details>
              </template>

              <!-- FILA DE SERVICIO: incluye id, cantidad incluida y precio_base -->
              <template id="svc_row_tpl">
                <div class="flex items-center justify-between svc-row"
                     data-service-id="__ID__"
                     data-service-name="__NAME__"
                     data-precio="__PRECIO__"
                     data-incluidas="__INCLUIDAS__">
                  <label class="flex flex-col gap-0.5">
                    <span class="flex items-center gap-2">
                      <input type="checkbox" class="h-4 w-4 svc-check">
                      <span class="svc-name">__NAME__</span>
                    </span>
                    <span class="text-[11px] text-slate-500">
                      Incluido en plan: <strong class="svc-incl">__INCLUIDAS__</strong> ·
                      Precio base: <span class="svc-price">__PRECIO_FMT__</span>
                    </span>
                  </label>
                  <div class="flex flex-col items-end gap-1">
                    <label class="text-[11px] text-slate-500">Cantidad total</label>
                    <input type="number"
                           min="0"
                           class="field w-20 rounded-xl border border-gray-300 bg-white px-2 py-1.5 qty"
                           name="servicios[__ID__][cantidad]"
                           value="0"
                           disabled>
                    <!-- lo incluido se manda oculto -->
                    <input type="hidden"
                           name="servicios[__ID__][incluidas]"
                           class="svc-incl-hidden"
                           value="__INCLUIDAS__">
                    <input type="hidden"
                           name="servicios[__ID__][precio_base]"
                           value="__PRECIO__">
                  </div>
                </div>
              </template>

              <div id="svc_container" class="contents"></div>
            </div>
          </section>

          <!-- PAGOS -->
          <section class="section p-6 rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold">Pagos</h2>
              <span class="chip inline-flex items-center gap-1 rounded-full border border-brand-100 bg-brand-50 px-2.5 py-1 text-xs text-brand-700">Paso 4</span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="rounded-xl border border-gray-200 bg-white p-3" data-pay="1">
                <h4 class="font-medium mb-2">Pago 1</h4>
                <label class="block text-sm mb-1">Fecha</label>
                <input name="pago1_fecha" type="date" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 mb-2">
                <label class="block text-sm mb-1">Valor</label>
                <input id="pago1_valor" name="pago1_valor" type="text" data-format-miles inputmode="numeric" class="field money w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                <label class="block text-sm mb-1">Método de pago</label>
                <select name="pago1_metodo" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                  <option value="datáfono">Datáfono</option>
                  <option value="transferencia">Transferencia</option>
                  <option value="efectivo">Efectivo</option>
                </select>
                <div class="grid grid-cols-2 gap-2 mt-3">
                  <button type="button" id="pago1_button" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium">Registrar</button>
                  <button type="button" id="corregir1_button" class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium">Corregir</button>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200 bg-white p-3" data-pay="2">
                <h4 class="font-medium mb-2">Pago 2</h4>
                <label class="block text-sm mb-1">Fecha</label>
                <input name="pago2_fecha" type="date" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 mb-2">
                <label class="block text-sm mb-1">Valor</label>
                <input id="pago2_valor" name="pago2_valor" type="text" data-format-miles inputmode="numeric" class="field money w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                <label class="block text-sm mb-1">Método de pago</label>
                <select name="pago2_metodo" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                  <option value="datáfono">Datáfono</option>
                  <option value="transferencia">Transferencia</option>
                  <option value="efectivo">Efectivo</option>
                </select>
                <div class="grid grid-cols-2 gap-2 mt-3">
                  <button type="button" id="pago2_button" class="px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium">Registrar</button>
                  <button type="button" id="corregir2_button" class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium">Corregir</button>
                </div>
              </div>

              <div class="rounded-xl border border-gray-200 bg-white p-3" data-pay="3">
                <h4 class="font-medium mb-2">Pago 3</h4>
                <label class="block text-sm mb-1">Fecha</label>
                <input name="pago3_fecha" type="date" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 mb-2">
                <label class="block text-sm mb-1">Valor</label>
                <input id="pago3_valor" name="pago3_valor" type="text" data-format-miles inputmode="numeric" class="field money w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                <label class="block text-sm mb-1">Método de pago</label>
                <select name="pago3_metodo" class="field w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
                  <option value="datáfono">Datáfono</option>
                  <option value="transferencia">Transferencia</option>
                  <option value="efectivo">Efectivo</option>
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
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">COP</span>
                  <input id="pago_total" name="pago_total" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full rounded-xl border border-gray-300 bg-white pl-12 px-3 py-2.5">
                </div>
              </div>
              <div>
                <label class="block text-sm mb-1" for="pago_datafono">Datáfono</label>
                <input id="pago_datafono" name="pago_datafono" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
              <div>
                <label class="block text-sm mb-1" for="pago_transferencia">Transferencia</label>
                <input id="pago_transferencia" name="pago_transferencia" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
              <div>
                <label class="block text-sm mb-1" for="pago_efectivo">Efectivo</label>
                <input id="pago_efectivo" name="pago_efectivo" type="text" data-format-miles inputmode="numeric" value="0" class="field money w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5">
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
              <div>
                <label class="block text-sm mb-1" for="saldo">Saldo</label>
                <input id="saldo" name="saldo" type="text" data-format-miles inputmode="numeric" value="0" readonly class="field money w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5">
              </div>
            </div>

            <div class="flex justify-between items-center mt-6">
              <button type="button" id="btnLimpiar" class="px-5 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50">Limpiar</button>
              <div class="flex items-center gap-2">
                <button type="button" id="btnGuardarBorrador" class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50">Guardar borrador</button>
                <button type="submit" class="px-6 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-semibold shadow-soft">Registrar</button>
              </div>
            </div>
          </section>
        </div>

        <!-- RIGHT -->
        <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-24 h-max">
          <section class="section p-6 rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <h3 class="text-base font-semibold">Resumen</h3>
              <span class="chip rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs">Vista previa</span>
            </div>
            <dl class="mt-4 divide-y divide-gray-200 text-sm" aria-live="polite">
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Cliente</dt><dd id="r_cliente" class="font-medium">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Contacto</dt><dd id="r_contacto" class="font-medium">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Plan</dt><dd id="r_plan" class="font-medium">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Ingreso</dt><dd id="r_ingreso" class="font-medium">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Noches</dt><dd id="r_noches" class="font-medium">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Personas</dt><dd id="r_personas" class="font-medium">—</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Valor</dt><dd id="r_valor" class="font-semibold">COP 0</dd></div>
              <div class="py-3 flex justify-between"><dt class="text-slate-500">Saldo</dt><dd id="r_saldo" class="font-semibold text-brand-700">COP 0</dd></div>
            </dl>
          </section>
          <section class="p-4 rounded-xl border border-amber-200 bg-amber-50/70 text-amber-900 text-sm">
            ⚠️ <strong>Recordatorio:</strong> el <em>Valor</em> total y los <em>Pagos</em> se ingresan manualmente. El <em>Saldo</em> se recalcula al guardar.
          </section>
        </aside>
      </form>
    </main>
  </div>

  <!-- Acciones móviles -->
  <div class="fixed bottom-0 inset-x-0 md:hidden bg-white/90 backdrop-blur border-t border-gray-200 p-3 flex items-center justify-between z-40">
    <div class="text-sm"><span class="font-medium">Saldo: </span><span id="r_saldo_m">COP 0</span></div>
    <button form="formReserva" type="submit" class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-semibold">Registrar</button>
  </div>

  <!-- Script: sidebar + lógica -->
  <script>
    (function(){
      // Tailwind fallback
      if (!window.tailwind) {
        document.documentElement.classList.add('no-tw');
        document.getElementById('twBanner').style.display = 'block';
      }

      // Sidebar toggle + persistencia
      const app   = document.getElementById('app');
      const fab   = document.getElementById('sbFab');
      const scrim = document.getElementById('scrim');
      const brandToggle = document.getElementById('brandToggle');

      if(localStorage.getItem('sb_expanded') === '1'){ app.classList.add('expanded'); }
      const syncAria = () => brandToggle?.setAttribute('aria-expanded', app.classList.contains('expanded') ? 'true' : 'false');

      function toggle(){
        app.classList.toggle('expanded');
        localStorage.setItem('sb_expanded', app.classList.contains('expanded') ? '1' : '0');
        syncAria();
      }
      fab.addEventListener('click', toggle);
      scrim.addEventListener('click', toggle);
      brandToggle?.addEventListener('click', toggle);
      window.addEventListener('keydown', (e)=>{
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') { e.preventDefault(); toggle(); }
        if (e.key === 'Escape' && app.classList.contains('expanded')) toggle();
      });
      syncAria();

      // === Utilidades de la página ===
      const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
      const $  = (s, r=document) => r.querySelector(s);
      const fmt = (n) => new Intl.NumberFormat('es-CO').format(Number(n||0));
      const unfmt = (s) => Number(String(s||'0').replace(/[^0-9]/g, ''));

      // Formateo dinero
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

      // Edad
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
          } else { edad.value = ''; }
        });
      }

      // === Servicios dinámicos (mostrar SIEMPRE todos, marcar incluidos por plan con cantidad) ===
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

      function updateSvcCounters(){
        let count = 0;
        $$('#svc_container .svc-row').forEach(row => {
          const chk = row.querySelector('.svc-check');
          const qty = row.querySelector('.qty');
          const incl = Number(row.dataset.incluidas || 0);
          const qv = Number(qty?.value || 0);
          // contamos si hay cantidad total > 0 o si el plan lo incluye
          if ((chk && chk.checked) || qv > 0 || incl > 0) {
            count++;
          }
        });
        document.getElementById('svc_count').textContent = count;

        $$('#svc_container details').forEach(det => {
          const inside = det.querySelectorAll('.svc-row').length;
          det.querySelector('.svc-in').textContent = inside;
        });
      }

      function renderServices(filter='') {
        container.innerHTML = '';

        const pid = getSelectedPlanId();
        const incluidosMap = new Map(); // id_servicio => cantidad_incluida

        // Servicios incluidos según plan
        if (pid && PLAN_SERVICIOS && PLAN_SERVICIOS[pid]) {
          PLAN_SERVICIOS[pid].forEach(s => {
            if (s.id) {
              incluidosMap.set(String(s.id), Number(s.incluidas || 0));
            }
          });
        }

        if (!SERVICIOS_ALL || !SERVICIOS_ALL.length) {
          updateSvcCounters();
          return;
        }

        // Agrupar TODOS los servicios por categoría, aplicando filtro
        const grouped = {};
        SERVICIOS_ALL.forEach(s => {
          if (filter && !s.nombre.toLowerCase().includes(filter.toLowerCase())) {
            return;
          }
          const g = s.grupo || 'Servicios';
          if (!grouped[g]) grouped[g] = [];
          grouped[g].push(s);
        });

        // Pintar grupos + filas
        Object.keys(grouped).forEach(grpName => {
          const list = grouped[grpName];
          if (!list.length) return;

          let rows = '';
          list.forEach(s => {
            const sid  = String(s.id);
            const incl = incluidosMap.get(sid) || 0;
            const precio = Number(s.precio || 0);

            rows += rowTpl
              .replace(/__ID__/g, sid)
              .replace(/__NAME__/g, s.nombre)
              .replace(/__INCLUIDAS__/g, incl)
              .replace(/__PRECIO_FMT__/g, precio > 0 ? 'COP ' + fmt(precio) : '—')
              .replace(/__PRECIO__/g, precio);
          });

          const html = groupTpl
            .replace('__TITLE__', grpName)
            .replace('__ROWS__', rows);

          const frag = document.createElement('div');
          frag.innerHTML = html;
          container.appendChild(frag.firstElementChild);
        });

        // Configurar filas (incluidos / adicionales)
        $$('#svc_container .svc-row').forEach(row => {
          const chk = row.querySelector('.svc-check');
          const qty = row.querySelector('.qty');
          const incl = Number(row.dataset.incluidas || 0);

          if (incl > 0) {
            // Servicio incluido por el plan
            row.classList.add('svc-included');
            if (chk) {
              chk.checked  = true;
              chk.disabled = true; // no se puede quitar, pero sí aumentar cantidad
            }
            if (qty) {
              qty.disabled = false;
              qty.min = incl;     // mínimo lo incluido
              qty.value = incl;   // cantidad total inicial = incluidas
            }
          } else {
            // Servicio NO incluido por el plan
            if (chk && qty) {
              qty.disabled = true;
              qty.min = 0;

              chk.addEventListener('change', (e)=> {
                if (e.target.checked) {
                  qty.disabled = false;
                  if (Number(qty.value || 0) <= 0) {
                    qty.value = 1; // al seleccionarlo por primera vez, mínimo 1
                  }
                } else {
                  qty.value = 0;
                  qty.disabled = true;
                }
                updateSvcCounters();
              });
            }
          }

          if (qty) {
            qty.addEventListener('input', () => {
              const val = Number(qty.value || 0);
              const min = Number(qty.min || 0);
              if (val < min) qty.value = min;
              updateSvcCounters();
            });
          }
        });

        updateSvcCounters();
      }

      // Cambiar servicios cuando cambia el plan
      planSelect.addEventListener('change', () => {
        renderServices(svcSearch.value || '');
      });

      // Buscar dentro de los servicios
      svcSearch.addEventListener('input', (e)=> {
        renderServices(e.target.value || '');
      });

      // Expandir / contraer / limpiar
      svcExpand.addEventListener('click', ()=> $$('#svc_container details').forEach(d=> d.open = true));
      svcCollapse.addEventListener('click', ()=> $$('#svc_container details').forEach(d=> d.open = false));
      svcClear.addEventListener('click', ()=> {
        $$('#svc_container .svc-row').forEach(row => {
          const chk = row.querySelector('.svc-check');
          const qty = row.querySelector('.qty');
          const incl = Number(row.dataset.incluidas || 0);

          if (incl > 0) {
            // servicios del plan: dejamos la cantidad incluida
            if (qty) {
              qty.disabled = false;
              qty.value = incl;
              qty.min = incl;
            }
          } else {
            // adicionales: los desmarcamos
            if (chk) chk.checked = false;
            if (qty) {
              qty.value = 0;
              qty.disabled = true;
            }
          }
        });
        updateSvcCounters();
      });

      // Al inicio: mostrar todos los servicios sin plan seleccionado
      renderServices();

      // Resumen en vivo
      function recalc(){
        document.getElementById('r_cliente').textContent = (document.getElementById('nombre').value || '—');
        const tel = (document.getElementById('whatsapp').value || '').trim();
        const email = (document.getElementById('correo').value || '').trim();
        document.getElementById('r_contacto').textContent = [tel, email].filter(Boolean).join(' · ') || '—';
        document.getElementById('r_plan').textContent = (document.getElementById('plan').value || '—');
        document.getElementById('r_ingreso').textContent = (document.getElementById('fecha_ingreso').value || '—');
        const ad = Number(document.getElementById('adultos').value||0), me = Number(document.getElementById('menores').value||0);
        document.getElementById('r_noches').textContent = (document.getElementById('noches').value || '—');
        document.getElementById('r_personas').textContent = (ad+me)||'—';
        document.getElementById('r_valor').textContent = 'COP ' + fmt(unfmt(document.getElementById('valor_total').value));
        document.getElementById('r_saldo').textContent = 'COP ' + fmt(unfmt(document.getElementById('saldo').value));
        document.getElementById('r_saldo_m').textContent = 'COP ' + fmt(unfmt(document.getElementById('saldo').value));
      }
      $$('#formReserva input, #formReserva select, #formReserva textarea').forEach(el => el.addEventListener('input', recalc));
      recalc();

      // Pagos
      function recalcPayments(){
        const v1 = unfmt(document.getElementById('pago1_valor').value),
              v2 = unfmt(document.getElementById('pago2_valor').value),
              v3 = unfmt(document.getElementById('pago3_valor').value);
        const total = v1+v2+v3;
        document.getElementById('pago_total').value = fmt(total);
        const valor = unfmt(document.getElementById('valor_total').value);
        const saldo = Math.max(valor - total, 0);
        document.getElementById('saldo').value = fmt(saldo);
        recalc();
      }
      ['#pago1_valor','#pago2_valor','#pago3_valor','#valor_total'].forEach(sel => document.querySelector(sel).addEventListener('input', recalcPayments));

      [1,2,3].forEach(i=>{
        const btnReg = document.getElementById(`pago${i}_button`);
        const btnCor = document.getElementById(`corregir${i}_button`);
        const inpVal = document.getElementById(`pago${i}_valor`);
        btnReg?.addEventListener('click', ()=>{ inpVal.disabled = true; btnReg.classList.add('opacity-60','pointer-events-none'); });
        btnCor?.addEventListener('click', ()=>{ inpVal.disabled = false; btnReg.classList.remove('opacity-60','pointer-events-none'); inpVal.focus(); });
      });

      document.getElementById('btnLimpiar').addEventListener('click', ()=>{
        document.getElementById('formReserva').reset();
        document.getElementById('svc_search').value='';
        renderServices();
        recalcPayments();
        recalc();
      });

      document.getElementById('formReserva').addEventListener('submit', (e)=>{
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
