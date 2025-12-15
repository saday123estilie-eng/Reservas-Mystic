<?php
// admin_menu.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'ADMIN') {
  header("Location: ../login.php");
  exit;
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel administrador | Mystic Paradise</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root{
      --bg:#020617;
      --card:#020617;
      --ink:#e5e7eb;
      --muted:#9ca3af;

      --brand:#2563eb;
      --brand-2:#38bdf8;
      --brand-3:#6366f1;

      --ring:0 0 0 3px rgba(56,189,248,.35);
      --shadow-1:0 2px 10px rgba(0,0,0,.45);
      --shadow-2:0 22px 60px rgba(0,0,0,.85);
      --radius:18px;

      --brand-grad: linear-gradient(120deg,#1d4ed8 0%,#2563eb 25%,#38bdf8 60%,#6366f1 100%);
      --brand-logo-url: url("https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png");
    }

    *{box-sizing:border-box}
    html,body{height:100%;}
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      color:var(--ink);
      background:
        radial-gradient(1200px 800px at 0% -10%, rgba(37,99,235,.25), transparent 70%),
        radial-gradient(900px 700px at 120% 0%, rgba(56,189,248,.2), transparent 70%),
        linear-gradient(180deg,#020617 0%,#020617 100%);
    }

    /* TOPBAR */
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
    }
    .brand-sub{
      font-size:12px;
      color:#64748b;
    }

    .top-actions{
      display:flex; align-items:center; gap:10px;
    }
    .btn{
      border-radius:999px;
      padding:7px 13px;
      font-size:12px;
      font-weight:600;
      letter-spacing:.2px;
      border:1px solid #1f2937;
      background:#020617;
      color:#e5e7eb;
      box-shadow:var(--shadow-1);
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      gap:6px;
      cursor:pointer;
    }
    .btn:hover{
      border-color:#2563eb;
    }
    .btn svg{width:14px;height:14px;}

    .btn-logout{
      border-color:#7f1d1d;
    }
    .btn-logout:hover{
      border-color:#ef4444;
      color:#fecaca;
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
    }
    .avatar-meta{
      display:flex; flex-direction:column;
    }
    .avatar-name{
      font-size:12px;
      font-weight:700;
    }
    .avatar-role{
      font-size:11px;
      color:#64748b;
    }

    /* MAIN LAYOUT */
    .main{
      max-width:1200px;
      margin:0 auto;
      padding:18px 12px 40px;
    }

    /* HERO */
    .hero{
      border-radius:24px;
      border:1px solid #1f2937;
      background:
        radial-gradient(circle at 0% 0%, rgba(56,189,248,.26), transparent 60%),
        radial-gradient(circle at 100% 0%, rgba(129,140,248,.16), transparent 60%),
        #020617;
      box-shadow:var(--shadow-2);
      padding:18px 18px 18px;
      display:flex;
      justify-content:space-between;
      gap:12px;
      align-items:center;
    }
    .hero-left{
      max-width:70%;
    }
    .hero-title{
      margin:0;
      font-size: clamp(22px, 2.6vw, 30px);
      font-weight:900;
      letter-spacing:.3px;
    }
    .hero-sub{
      margin:4px 0 0;
      font-size:14px;
      color:var(--muted);
    }
    .hero-pill{
      margin-top:10px;
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:6px 10px;
      border-radius:999px;
      background:#020617;
      border:1px solid #1f2937;
      font-size:12px;
      color:#e5e7eb;
    }
    .hero-pill-dot{
      width:9px;height:9px;
      border-radius:999px;
      background:#22c55e;
      box-shadow:0 0 10px rgba(34,197,94,.7);
    }

    .hero-right{
      display:flex;
      flex-direction:column;
      gap:6px;
      align-items:flex-end;
      font-size:12px;
      color:#9ca3af;
    }
    .hero-stat{
      display:flex; flex-direction:column; align-items:flex-end;
    }
    .hero-stat span:first-child{
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.16px;
    }
    .hero-stat b{
      font-size:16px;
    }

    /* SECCIONES / GRID */
    .section-title{
      margin:16px 0 4px;
      font-size:14px;
      color:#9ca3af;
      text-transform:uppercase;
      letter-spacing:.18px;
    }
    .section-sub{
      margin:0 0 10px;
      font-size:12px;
      color:#6b7280;
    }

    /* Grid 4 columnas en escritorio */
    .grid{
      display:grid;
      grid-template-columns:repeat(4, minmax(0,1fr));
      gap:14px;
    }

    .card-link{
      text-decoration:none;
      color:inherit;
    }
    .card{
      position:relative;
      border-radius:var(--radius);
      border:1px solid #1f2937;
      background:radial-gradient(circle at 0% 0%, rgba(37,99,235,.22), transparent 58%),
                 #020617;
      padding:14px 14px 16px;
      box-shadow:var(--shadow-1);
      display:flex;
      flex-direction:column;
      gap:6px;
      cursor:pointer;
      transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .card:hover{
      transform:translateY(-2px) scale(1.01);
      box-shadow:0 22px 60px rgba(0,0,0,.9);
      border-color:#2563eb;
    }
    .card-icon{
      width:34px;height:34px;
      border-radius:12px;
      border:1px solid #1d4ed8;
      background:radial-gradient(circle at 0% 0%, rgba(56,189,248,.35), transparent 65%);
      display:grid;place-items:center;
    }
    .card-icon svg{
      width:18px;height:18px;
    }
    .card-title{
      font-size:14px;
      font-weight:700;
    }
    .card-desc{
      font-size:12px;
      color:#9ca3af;
    }
    .card-tag{
      position:absolute;
      top:10px; right:10px;
      font-size:10px;
      text-transform:uppercase;
      letter-spacing:.16px;
      padding:3px 7px;
      border-radius:999px;
      border:1px solid #1f2937;
      background:rgba(15,23,42,.92);
      color:#9ca3af;
    }
    .card-tag-primary{
      border-color:#2563eb;
      color:#bfdbfe;
    }
    .card-tag-green{
      border-color:#22c55e;
      color:#bbf7d0;
    }
    .card-tag-amber{
      border-color:#f59e0b;
      color:#fed7aa;
    }

    @media (max-width: 768px){
      .topbar-inner{
        flex-direction:column;
        align-items:flex-start;
      }
      .hero{
        flex-direction:column;
        align-items:flex-start;
      }
      .hero-left{
        max-width:100%;
      }
      .hero-right{
        align-items:flex-start;
      }
      .grid{
        grid-template-columns:repeat(2, minmax(0,1fr));
      }
    }

    @media (max-width: 480px){
      .grid{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <div class="brand-logo"></div>
      <div class="brand-text">
        <span class="brand-title">MYSTIC PARADISE</span>
        <span class="brand-sub">Panel administrador</span>
      </div>
    </div>

    <div class="top-actions">
      <!-- acceso directo a reservas -->
      <a href="Ver_reservas.php" class="btn" title="Ir a reservas">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="2" d="M4 5h16M4 10h16M4 15h10M4 20h7"/>
        </svg>
        Reservas
      </a>

      <a href="/logout.php" class="btn btn-logout" title="Cerrar sesión">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="2" d="M15 17l5-5-5-5M20 12H9M11 5V4a2 2 0 00-2-2H5a2 2 0 00-2 2v16a2 2 0 002 2h4a2 2 0 002-2v-1"/>
        </svg>
        Salir
      </a>

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
          <span class="avatar-role">ADMIN</span>
        </div>
      </div>
    </div>
  </div>
</header>

<main class="main">
  <!-- HERO -->
  <section class="hero">
    <div class="hero-left">
      <h1 class="hero-title">Bienvenido, <?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?> 👑</h1>
      <p class="hero-sub">
        Desde este panel administras reservas, reportes, clientes, usuarios y toda la configuración
        interna de Mystic Paradise.
      </p>
      <div class="hero-pill">
        <span class="hero-pill-dot"></span>
        <span>Rol administrador · Control total del backoffice</span>
      </div>
    </div>
    <div class="hero-right">
      <div class="hero-stat">
        <span>Área crítica</span>
        <b>Reportes &amp; reservas</b>
      </div>
      <div class="hero-stat">
        <span>Configuración</span>
        <span>Planes, servicios, usuarios y pagos en un solo lugar.</span>
      </div>
    </div>
  </section>

  <!-- BLOQUE 1: Reportes -->
  <h2 class="section-title">Reportes</h2>
  <p class="section-sub">Analiza ventas, desempeño de planes y comportamiento general del negocio.</p>
  <section class="grid">
    <!-- Reporte de ventas -->
    <a href="reporte_ventas.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 19h16M5 17l3-7 4 5 4-9 3 11"/>
          </svg>
        </div>
        <div class="card-tag card-tag-primary">Finanzas</div>
        <h3 class="card-title">Reporte de ventas</h3>
        <p class="card-desc">
          Ingresos por método de pago y por rango de fechas. Ideal para cierres diarios y mensuales.
        </p>
      </article>
    </a>

    <!-- Reporte de planes -->
    <a href="reporteplan.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 6h16M4 10h10M4 14h7M4 18h4"/>
          </svg>
        </div>
        <div class="card-tag card-tag-primary">Planes</div>
        <h3 class="card-title">Reporte de planes</h3>
        <p class="card-desc">
          Analiza el rendimiento de pasadías, glamping, camping y otros planes vendidos.
        </p>
      </article>
    </a>

    <!-- Reportes generales -->
    <a href="reporte.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M5 3h4v18H5zM10 9h4v12h-4zM15 13h4v8h-4z"/>
          </svg>
        </div>
        <div class="card-tag card-tag-primary">Global</div>
        <h3 class="card-title">Reportes generales</h3>
        <p class="card-desc">
          Resumen consolidado de reservas, servicios adicionales y comportamiento de pagos.
        </p>
      </article>
    </a>
  </section>

  <!-- BLOQUE 2: Operación diaria -->
  <h2 class="section-title">Operación diaria</h2>
  <p class="section-sub">Herramientas clave para manejar reservas y relación con los huéspedes.</p>
  <section class="grid">
    <!-- Reservas -->
    <a href="Ver_reservas.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M5 4h14v16H5zM9 4v16M5 9h4"/>
          </svg>
        </div>
        <div class="card-tag card-tag-green">Core</div>
        <h3 class="card-title">Reservas</h3>
        <p class="card-desc">
          Gestión completa de reservas: creación, cambios, check-in y check-out.
        </p>
      </article>
    </a>

    <!-- Clientes -->
    <a href="clientes.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 20a8 8 0 0116 0"/>
          </svg>
        </div>
        <div class="card-tag card-tag-green">Huéspedes</div>
        <h3 class="card-title">Clientes</h3>
        <p class="card-desc">
          Listado de huéspedes, historial de visitas y datos de contacto para seguimiento.
        </p>
      </article>
    </a>

    <!-- Pagos -->
    <a href="pagos.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 7h16v10H4zM8 11h4m-4 3h2"/>
          </svg>
        </div>
        <div class="card-tag card-tag-amber">Caja</div>
        <h3 class="card-title">Pagos</h3>
        <p class="card-desc">
          Registro de abonos, control de saldos pendientes y correcciones de pagos.
        </p>
      </article>
    </a>
  </section>

  <!-- BLOQUE 3: Configuración -->
  <h2 class="section-title">Configuración</h2>
  <p class="section-sub">Define qué se vende, a qué precio y quién tiene acceso al sistema.</p>
  <section class="grid">
    <!-- Usuarios -->
    <a href="usuarios.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M16 11a4 4 0 10-8 0 4 4 0 008 0zM6 19a6 6 0 0112 0"/>
          </svg>
        </div>
        <div class="card-tag">Seguridad</div>
        <h3 class="card-title">Usuarios</h3>
        <p class="card-desc">
          Cuentas internas, roles (admin, asesor, recepción) y niveles de acceso.
        </p>
      </article>
    </a>

    <!-- 🔁 Antes: Planes → Ahora: Disponibilidad -->
    <a href="disponibilidad.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 5h16M4 10h8M4 15h6M4 20h10M16 9l4 4-4 4"/>
          </svg>
        </div>
        <div class="card-tag">Inventario</div>
        <h3 class="card-title">Disponibilidad</h3>
        <p class="card-desc">
          Vista de ocupación y cupos disponibles por fecha y tipo de alojamiento.
        </p>
      </article>
    </a>

    <!-- Servicios -->
      <a href="historial_reservas.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 5h16M4 10h8M4 15h6M4 20h10M16 9l4 4-4 4"/>
          </svg>
        </div>
        <div class="card-tag">Core</div>
        <h3 class="card-title">Historial Ediciones</h3>
        <p class="card-desc">
          Vista de Historial de ediciones en las reservas y quien las realiza.
        </p>
      </article>
    </a>
  </section>
</main>

</body>
</html>
