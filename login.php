<?php
declare(strict_types=1);

/**
 * login.php
 * Acceso defensivo con protección CSRF, verificación de bcrypt y mitigación de fuga de timing
 */

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();

if (esta_autenticado()) {
    header('Location: panel.php');
    exit;
}

$errores = [];
$emailEnviado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!validar_token_csrf($token)) {
        $errores[] = 'Error de seguridad: Solicitud rechazada por token CSRF inválido o expirado.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $emailEnviado = $email;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Introduzca un correo electrónico válido.';
        }
        if ($password === '') {
            $errores[] = 'Debe indicar su contraseña de acceso.';
        }

        if (empty($errores)) {
            $pdo = conectar();
            $stmt = $pdo->prepare('SELECT id, nombre, email, telefono, password_hash, rol, activo FROM usuarios WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $usuario = $stmt->fetch();

            // Mensaje genérico para prevenir enumeración de usuarios
            if ($usuario && (int)$usuario['activo'] === 1 && password_verify($password, $usuario['password_hash'])) {
                iniciar_sesion_usuario($usuario);
                set_flash('exito', 'Bienvenido/a de nuevo, ' . $usuario['nombre'] . '. Ha iniciado sesión como ' . ucfirst($usuario['rol']) . '.');
                header('Location: panel.php');
                exit;
            } else {
                $errores[] = 'Credenciales no válidas o cuenta no activa en el sistema.';
            }
        }
    }
}

$tituloPagina = 'Iniciar Sesión';
require_once __DIR__ . '/views/header.php';
?>

<div class="auth-wrapper">
  <div class="card">
    <div class="card-header" style="text-align:center; display:block;">
      <h2 class="card-title">Acceso al Gestor de Incidencias</h2>
      <p style="color:var(--cesi-text-muted); font-size:0.9rem; margin-top:0.3rem;">Calderas y Climatización CESI</p>
    </div>

    <?php if (!empty($errores)): ?>
      <div class="flash-message flash-error">
        <ul style="margin-left: 1.25rem;">
          <?php foreach ($errores as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <?= campo_csrf() ?>

      <div class="form-group">
        <label for="email" class="form-label">Correo Electrónico:</label>
        <input type="email" id="email" name="email" class="form-control" value="<?= e($emailEnviado) ?>" required autofocus placeholder="usuario@cesi.com">
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Contraseña:</label>
        <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
      </div>

      <button type="submit" class="btn btn-flame btn-block" style="padding:0.75rem; margin-top:1rem;">Iniciar Sesión</button>
    </form>

    <div style="margin-top: 1.5rem; text-align:center; font-size:0.9rem; border-top:1px solid #ebedef; padding-top:1rem;">
      ¿Es titular de una caldera CESI y no tiene cuenta? <br>
      <a href="registro.php" style="color:var(--cesi-flame-primary); font-weight:700;">Registrarme como Cliente</a>
    </div>

    <!-- Guía rápida para evaluación y pruebas -->
    <div style="margin-top: 1.5rem; background: #eaf2f8; padding: 1rem; border-radius: var(--cesi-radius); font-size: 0.85rem; border-left: 4px solid var(--cesi-blue-light);">
      <strong>🔑 Cuentas de Demostración para Pruebas:</strong>
      <div style="margin-top:0.5rem; display:grid; gap:0.35rem;">
        <div><strong>Administrador:</strong> <code>admin@cesi.com</code> / <code>Admin1234!</code></div>
        <div><strong>Técnico Calderas:</strong> <code>tecnico@cesi.com</code> / <code>Tecnico1234!</code></div>
        <div><strong>Cliente Titular:</strong> <code>cliente@cesi.com</code> / <code>Cliente1234!</code></div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
