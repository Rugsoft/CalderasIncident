<?php
declare(strict_types=1);

/**
 * auth.php
 * Gestión de autenticación defensiva, ciclo de vida de sesiones y RBAC (Manual 4)
 */

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Directivas de endurecimiento para la cookie de sesión
        $opciones = [
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        // Habilitar flag Secure si la conexión es HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $opciones['secure'] = true;
        }

        session_set_cookie_params($opciones);
        session_start();
    }
}

function esta_autenticado(): bool
{
    iniciar_sesion_segura();
    return !empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_rol']);
}

function usuario_actual(): ?array
{
    if (!esta_autenticado()) {
        return null;
    }

    return [
        'id'       => (int)$_SESSION['usuario_id'],
        'nombre'   => (string)$_SESSION['usuario_nombre'],
        'email'    => (string)$_SESSION['usuario_email'],
        'rol'      => (string)$_SESSION['usuario_rol'],
        'telefono' => (string)($_SESSION['usuario_telefono'] ?? ''),
    ];
}

function exigir_autenticacion(): void
{
    if (!esta_autenticado()) {
        set_flash('aviso', 'Debe iniciar sesión para acceder a esta sección.');
        header('Location: login.php');
        exit;
    }
}

function exigir_rol(array|string $rolesPermitidos): void
{
    exigir_autenticacion();
    $roles = (array)$rolesPermitidos;

    $usuario = usuario_actual();
    if (!$usuario || !in_array($usuario['rol'], $roles, true)) {
        http_response_code(403);
        include dirname(__DIR__) . '/views/error_403.php';
        exit;
    }
}

function iniciar_sesion_usuario(array $usuario): void
{
    iniciar_sesion_segura();

    // Regenerar ID de sesión para prevenir fijación de sesión (Session Fixation)
    session_regenerate_id(true);

    $_SESSION['usuario_id']       = (int)$usuario['id'];
    $_SESSION['usuario_nombre']   = (string)$usuario['nombre'];
    $_SESSION['usuario_email']    = (string)$usuario['email'];
    $_SESSION['usuario_rol']      = (string)$usuario['rol'];
    $_SESSION['usuario_telefono'] = (string)($usuario['telefono'] ?? '');
}

function cerrar_sesion(): void
{
    iniciar_sesion_segura();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
