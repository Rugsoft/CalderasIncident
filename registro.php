<?php
declare(strict_types=1);

/**
 * registro.php
 * Alta de clientes solicitantes con rol forzado en servidor (Manual 4)
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
$datos = [
    'nombre'   => '',
    'email'    => '',
    'telefono' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!validar_token_csrf($token)) {
        $errores[] = 'Error de seguridad: Petición rechazada por token CSRF inválido.';
    } else {
        $datos['nombre']   = trim($_POST['nombre'] ?? '');
        $datos['email']    = trim($_POST['email'] ?? '');
        $datos['telefono'] = trim($_POST['telefono'] ?? '');
        $password          = (string)($_POST['password'] ?? '');
        $passwordConfirm   = (string)($_POST['password_confirm'] ?? '');

        if (mb_strlen($datos['nombre']) < 3) {
            $errores[] = 'El nombre y apellidos deben tener al menos 3 caracteres.';
        }
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El formato de correo electrónico no es válido.';
        }
        if (mb_strlen($datos['telefono']) < 7) {
            $errores[] = 'Indique un teléfono de contacto válido para la visita del técnico.';
        }
        if (mb_strlen($password) < 8) {
            $errores[] = 'La contraseña debe contener un mínimo de 8 caracteres.';
        }
        if ($password !== $passwordConfirm) {
            $errores[] = 'Las contraseñas introducidas no coinciden.';
        }

        if (empty($errores)) {
            $pdo = conectar();

            // Comprobar colisión de correo
            $stmtCheck = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
            $stmtCheck->execute(['email' => $datos['email']]);
            if ($stmtCheck->fetch()) {
                $errores[] = 'Ya existe un usuario registrado con esa dirección de correo electrónico.';
            } else {
                // Inserción segura con rol FORZADO como literal 'solicitante'
                $sql = 'INSERT INTO usuarios (nombre, email, telefono, password_hash, rol, activo)
                        VALUES (:nombre, :email, :telefono, :hash, "solicitante", 1)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nombre'   => $datos['nombre'],
                    'email'    => $datos['email'],
                    'telefono' => $datos['telefono'],
                    'hash'     => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $nuevoId = (int)$pdo->lastInsertId();
                iniciar_sesion_usuario([
                    'id'       => $nuevoId,
                    'nombre'   => $datos['nombre'],
                    'email'    => $datos['email'],
                    'rol'      => 'solicitante',
                    'telefono' => $datos['telefono'],
                ]);

                set_flash('exito', '¡Cuenta de cliente creada con éxito! Ya puede registrar la avería o solicitud de mantenimiento de su caldera.');
                header('Location: nueva_incidencia.php');
                exit;
            }
        }
    }
}

$tituloPagina = 'Registro de Cliente Titular';
require_once __DIR__ . '/views/header.php';
?>

<div class="auth-wrapper" style="max-width: 550px;">
  <div class="card">
    <div class="card-header" style="text-align:center; display:block;">
      <h2 class="card-title">Registro de Cliente / Titular de Caldera</h2>
      <p style="color:var(--cesi-text-muted); font-size:0.9rem; margin-top:0.3rem;">Servicio Técnico Oficial Calderas CESI</p>
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

    <form action="registro.php" method="POST">
      <?= campo_csrf() ?>

      <div class="form-group">
        <label for="nombre" class="form-label">Nombre y Apellidos:</label>
        <input type="text" id="nombre" name="nombre" class="form-control" value="<?= e($datos['nombre']) ?>" required placeholder="Ej: María García Pérez">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="email" class="form-label">Correo Electrónico:</label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($datos['email']) ?>" required placeholder="ejemplo@correo.com">
        </div>
        <div class="form-group">
          <label for="telefono" class="form-label">Teléfono Móvil:</label>
          <input type="text" id="telefono" name="telefono" class="form-control" value="<?= e($datos['telefono']) ?>" required placeholder="+34 600 000 000">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password" class="form-label">Contraseña (mínimo 8 car.):</label>
          <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
        </div>
        <div class="form-group">
          <label for="password_confirm" class="form-label">Repetir Contraseña:</label>
          <input type="password" id="password_confirm" name="password_confirm" class="form-control" required placeholder="••••••••">
        </div>
      </div>

      <button type="submit" class="btn btn-flame btn-block" style="padding:0.75rem; margin-top:1rem;">Crear Cuenta de Cliente</button>
    </form>

    <div style="margin-top: 1.5rem; text-align:center; font-size:0.9rem; border-top:1px solid #ebedef; padding-top:1rem;">
      ¿Ya dispone de una cuenta de acceso? <br>
      <a href="login.php" style="color:var(--cesi-blue-primary); font-weight:700;">Iniciar Sesión Aquí</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
