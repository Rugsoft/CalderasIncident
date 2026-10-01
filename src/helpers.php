<?php
declare(strict_types=1);

/**
 * helpers.php
 * Utilidades de seguridad, sanitización XSS, tokens CSRF y formateo
 */

/**
 * Escapar salida para prevenir vulnerabilidades Cross-Site Scripting (XSS)
 */
function e(?string $valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generar token CSRF seguro en la sesión
 */
function generar_token_csrf(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF mediante comparación a tiempo constante
 */
function validar_token_csrf(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Imprimir campo input oculto con token CSRF
 */
function campo_csrf(): string
{
    $token = generar_token_csrf();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Formatear marcas temporales mediante DateTimeImmutable
 */
function formatear_fecha(?string $fecha, string $formato = 'd/m/Y H:i'): string
{
    if (empty($fecha)) {
        return '-';
    }

    try {
        $dt = new DateTimeImmutable($fecha);
        return $dt->format($formato);
    } catch (Exception) {
        return e($fecha);
    }
}

/**
 * Generar la siguiente referencia pública para Calderas CESI (ej: CESI-2026-0042)
 */
function generar_referencia(PDO $pdo): string
{
    $anioActual = (int)date('Y');
    $prefijo = sprintf('CESI-%d-', $anioActual);

    $sql = 'SELECT referencia FROM incidencias 
            WHERE referencia LIKE :prefijo 
            ORDER BY id DESC LIMIT 1 FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['prefijo' => $prefijo . '%']);
    $ultima = $stmt->fetchColumn();

    if ($ultima && preg_match('/-(\d+)$/', (string)$ultima, $matches)) {
        $secuencia = ((int)$matches[1]) + 1;
    } else {
        $secuencia = 1;
    }

    return sprintf('CESI-%d-%04d', $anioActual, $secuencia);
}

/**
 * Mensajes Flash en sesión
 */
function set_flash(string $tipo, string $mensaje): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'tipo'    => $tipo, // 'exito', 'error', 'info', 'aviso'
        'mensaje' => $mensaje,
    ];
}

function get_flash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Renderizado de insignias de estado con estilo corporativo
 */
function badge_estado(string $estado): string
{
    $clases = [
        'nueva'       => 'badge-estado-nueva',
        'asignada'    => 'badge-estado-asignada',
        'en_proceso'  => 'badge-estado-proceso',
        'resuelta'    => 'badge-estado-resuelta',
        'cerrada'     => 'badge-estado-cerrada',
    ];

    $nombres = [
        'nueva'       => 'Nueva (Pendiente)',
        'asignada'    => 'Técnico Asignado',
        'en_proceso'  => 'En Reparación',
        'resuelta'    => 'Resuelta',
        'cerrada'     => 'Cerrada / Finalizada',
    ];

    $clase = $clases[$estado] ?? 'badge-generica';
    $texto = $nombres[$estado] ?? ucfirst($estado);

    return sprintf('<span class="badge %s">%s</span>', e($clase), e($texto));
}

/**
 * Renderizado de insignias de prioridad
 */
function badge_prioridad(string $prioridad): string
{
    $clases = [
        'baja'    => 'badge-prio-baja',
        'media'   => 'badge-prio-media',
        'alta'    => 'badge-prio-alta',
        'urgente' => 'badge-prio-urgente',
    ];

    $nombres = [
        'baja'    => 'Baja',
        'media'   => 'Media',
        'alta'    => 'Alta',
        'urgente' => 'URGENTE',
    ];

    $clase = $clases[$prioridad] ?? 'badge-generica';
    $texto = $nombres[$prioridad] ?? ucfirst($prioridad);

    return sprintf('<span class="badge %s">%s</span>', e($clase), e($texto));
}
