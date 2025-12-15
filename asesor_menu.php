<?php
// asesor/menu.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'ASESOR') {
    header("Location: ../login.php");
    exit;
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Asesor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel Asesor · Mystic Paradise</title>
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

/* GRID DE OPCIONES */
.section-title{
  margin:16px 0 8px;
  font-size:14px;
  color:#9ca3af;
  text-transform:uppercase;
  letter-spacing:.18px;
}

.grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(230px,1fr));
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
.card-tag-accent{
  border-color:#22c55e;
  color:#bbf7d0;
}
.card-tag-metric{
  border-color:#f59e0b;
  color:#ffedd5;
}

/* Responsive */
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
        <span class="brand-sub">Panel del asesor</span>
      </div>
    </div>

    <div class="top-actions">
      <!-- Acceso directo a disponibilidad -->
      <a href="../Vista/disponibilidad.php" class="btn" title="Ver disponibilidad">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="2" d="M4 7h16M4 12h10M4 17h7"/>
        </svg>
        Disponibilidad
      </a>

      <!-- ⭐ RANKING -->
      <a href="../Vista/ranking.php" class="btn" title="Ver ranking">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-width="2" d="M8 21h8M9 17V9l-2-2V5h10v2l-2 2v8M9 9h6"/>
        </svg>
        Ranking
      </a>

      <!-- 🔴 CERRAR SESIÓN -->
      <a href="../Vista/logout.php" class="btn btn-logout" title="Cerrar sesión">
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
          <span class="avatar-role">ASESOR</span>
        </div>
      </div>
    </div>
  </div>
</header>

<main class="main">
  <!-- Hero -->
  <section class="hero">
    <div class="hero-left">
      <h1 class="hero-title">Hola, <?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?> 👋</h1>
      <p class="hero-sub">
        Desde aquí puedes consultar la disponibilidad y cargar reservas que el cliente ya tiene
        confirmadas por otros canales. No modificas configuración, solo gestionas ocupación.
      </p>
      <div class="hero-pill">
        <span class="hero-pill-dot"></span>
        <span>Rol asesor · Acceso limitado</span>
      </div>
    </div>
    <div class="hero-right">
      <div class="hero-stat">
        <span>Acceso principal</span>
        <b>Disponibilidad de alojamientos</b>
      </div>
      <div class="hero-stat">
        <span>Próximo paso</span>
        <span>Usar <b>Comparativa Reservas</b> para reservas externas (lógica especial).</span>
      </div>
    </div>
  </section>

  <!-- Grid de módulos del asesor -->
  <h2 class="section-title">Opciones del asesor</h2>
  <section class="grid">
    <!-- DISPONIBILIDAD -->
    <a href="../Vista/disponibilidad.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M4 4h16v4H4zM4 10h7v10H4zM13 10h7v6h-7zM13 18h7"/>
          </svg>
        </div>
        <div class="card-tag card-tag-primary">Vista</div>
        <h3 class="card-title">Disponibilidad</h3>
        <p class="card-desc">
          Ve glampings, campings y habitaciones ocupadas o libres. 
          Puedes abrir el pop-up para ver de quién es la reserva, pero no asignar unidades.
        </p>
      </article>
    </a>

    <!-- SUBIR RESERVA -->
    <a href="../Vista/subir_reserva.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M12 16V4m0 0L8 8m4-4 4 4M6 16v3a1 1 0 001 1h10a1 1 0 001-1v-3"/>
          </svg>
        </div>
        <div class="card-tag card-tag-accent">Especial</div>
        <h3 class="card-title">Comparativa De Reservas</h3>
        <p class="card-desc">
          Aqui se subira el excel de las reservas que ya tenga el asesor
          para hacer la comparativa y asi generar el cobro correspondiente
        </p>
      </article>
    </a>

    <!-- NUEVA CARD: RANKING DE ASESORES -->
    <a href="../Vista/ranking.php" class="card-link">
      <article class="card">
        <div class="card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-width="2" d="M8 21h8M9 17V9l-2-2V5h10v2l-2 2v8M9 9h6"/>
          </svg>
        </div>
        <div class="card-tag card-tag-metric">Métricas</div>
        <h3 class="card-title">Ranking de Comerciales Mystic</h3>
        <p class="card-desc">
          Consulta tu posición según reservas confirmadas, ingresos generados y otros indicadores
          de desempeño del equipo comercial.
        </p>
      </article>
    </a>
  </section>
</main>

</body>
</html>
