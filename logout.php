<?php
declare(strict_types=1);

/**
 * logout.php
 * Cierre de sesión seguro con validación de petición POST y CSRF (Manual 4)
 */

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (validar_token_csrf($token)) {
        cerrar_sesion();
        iniciar_sesion_segura();
        set_flash('info', 'Ha cerrado sesión correctamente. Gracias por usar el portal de Calderas CESI.');
    } else {
        set_flash('error', 'Token de seguridad inválido al cerrar sesión.');
    }
}

header('Location: login.php');
exit;
