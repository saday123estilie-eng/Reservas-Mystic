<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $mensajeError = "Debes completar todos los campos.";
    } else {
        try {
            $pdo = conexion::getConexion();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql  = "SELECT id_usuario, nombre, email, password_hash, rol 
                     FROM usuarios 
                     WHERE email = :email 
                     LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($password, $usuario['password_hash'])) {

                session_regenerate_id(true);

                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['rol']        = $usuario['rol'];

                switch ($usuario['rol']) {
                    case 'ADMIN': header('Location: admin_menu.php'); break;
                    case 'ASESOR': header('Location: asesor_menu.php'); break;
                    case 'RECEPCION': header('Location: recepcion_menu.php'); break;
                    default: header('Location: login.php'); break;
                }
                exit;
            } else {
                $mensajeError = "Correo o contraseña incorrectos.";
            }
        } catch (Exception $e) {
            $mensajeError = "Error al iniciar sesión. Intenta de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login | Mystic Paradise</title>
  
  <script src="https://cdn.tailwindcss.com"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50:'#eaf3ff',
              100:'#c9deff',
              200:'#a4c8ff',
              300:'#7eb1ff',
              400:'#589aff',
              500:'#2f82ff',  /* Azul principal */
              600:'#1e6ade',
              700:'#1552ad',
              800:'#0d3a7c',
              900:'#07224b',
              950:'#031227'
            }
          }
        }
      }
    }
  </script>

  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>

</head>

<body class="bg-[#0A0C14] text-white h-dvh"> 
  <main class="w-full h-full"> 

    <div class="grid grid-cols-1 md:grid-cols-2 w-full h-full">

      <!--  ------------------------
            LADO IZQUIERDO (SIN OPACAR IMAGEN)
           ------------------------ -->
      <section class="relative hidden md:block w-full h-full">

        <img src="https://i.ibb.co/93mPghnZ/Pasadia.jpg"
             class="absolute inset-0 h-full w-full object-cover">

        <div class="absolute top-10 left-10 z-20">
          <span class="text-white font-semibold text-xl tracking-wider drop-shadow-lg">
            MYSTIC PARADISE
          </span>
        </div>
      </section>


      <!--  ------------------------
            FORMULARIO
           ------------------------ -->
      <section class="flex flex-col justify-center px-6 py-10 md:px-16 bg-[#0F111A]">

        <div class="max-w-md mx-auto w-full">

          <div class="flex items-center justify-between mb-10">
            <div class="flex items-center gap-2">
              <img src="https://i.ibb.co/tp96Q55N/LOGO-MYSTIC.png" class="h-10">
              <span class="text-sm opacity-90">MYSTIC PARADISE</span>
            </div>

            <a class="px-3 py-1.5 rounded-full border border-brand-400 text-brand-400 text-sm">
              ES ▾
            </a>
          </div>

          <h1 class="text-4xl font-extrabold">Hola de nuevo 👋</h1>
          <p class="text-slate-400 mt-2">Bienvenido(a) a tu panel de Mystic Paradise</p>

          <?php if ($mensajeError !== ''): ?>
            <div class="mt-4 p-3 bg-red-500/20 border border-red-500 text-red-300 rounded-lg text-sm">
              <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <form action="" method="post" class="space-y-5 mt-8">

            <!-- Email -->
            <div>
              <label class="text-sm text-slate-300">Email</label>
              <div class="relative border border-brand-600 bg-[#0A0C14] rounded-xl">
                <input type="email" name="email"
                       class="w-full bg-transparent px-4 py-3 rounded-xl outline-none"
                       placeholder="tucorreo@ejemplo.com">
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-brand-400">
                  <i class="fa-solid fa-envelope"></i>
                </span>
              </div>
            </div>

            <!-- Password -->
            <div>
              <div class="flex justify-between">
                <label class="text-sm text-slate-300">Contraseña</label>
                <a class="text-brand-400 text-sm hover:underline">¿Olvidaste tu contraseña?</a>
              </div>

              <div class="relative border border-brand-600 bg-[#0A0C14] rounded-xl">
                <input id="password" name="password" type="password"
                       class="w-full bg-transparent px-4 py-3 rounded-xl outline-none"
                       placeholder="Contraseña">

                <span class="absolute right-10 top-1/2 -translate-y-1/2 text-brand-400">
                  <i class="fa-solid fa-lock"></i>
                </span>

                <button type="button" id="togglePassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-brand-400">
                  <i id="eyeClosed" class="fa-solid fa-eye-slash"></i>
                  <i id="eyeOpen" class="fa-solid fa-eye hidden"></i>
                </button>
              </div>
            </div>

            <!-- Recordarme -->
            <label class="flex items-center gap-2 text-slate-300">
              <input type="checkbox" class="accent-brand-500">
              Recordarme
            </label>

            <!-- Botón -->
            <button class="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold">
              Iniciar sesión
            </button>
          </form>

          <p class="mt-6 text-center text-slate-400 text-sm">
            ¿No tienes una cuenta?
            <a class="text-brand-400 hover:underline">Regístrate</a>
          </p>

        </div>

      </section>

    </div>
  </main>


<script>
// Mostrar / ocultar contraseña
const btn = document.getElementById("togglePassword");
const pwd = document.getElementById("password");
const eyeOpen = document.getElementById("eyeOpen");
const eyeClosed = document.getElementById("eyeClosed");

btn.addEventListener("click", () => {
    const show = pwd.type === "password";
    pwd.type = show ? "text" : "password";
    eyeOpen.classList.toggle("hidden", !show);
    eyeClosed.classList.toggle("hidden", show);
});
</script>

</body>
</html>
