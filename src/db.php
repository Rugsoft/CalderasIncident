<?php
declare(strict_types=1);

/**
 * db.php
 * Factoría de conexión PDO desacoplada y robusta (Manual 3)
 */

function conectar(?array $config = null): PDO
{
    static $instancia = null;

    if ($instancia !== null) {
        return $instancia;
    }

    if ($config === null) {
        $rutaConfig = dirname(__DIR__) . '/config/database.php';
        if (!file_exists($rutaConfig)) {
            error_log('Error crítico: Archivo de configuración database.php no encontrado en ' . $rutaConfig);
            http_response_code(503);
            die('Servicio temporalmente no disponible por mantenimiento de infraestructura.');
        }
        $config = require $rutaConfig;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'] ?? '127.0.0.1',
        (int)($config['port'] ?? 3306),
        $config['dbname'] ?? 'cesi_incidencias',
        $config['charset'] ?? 'utf8mb4'
    );

    try {
        $instancia = new PDO(
            $dsn,
            (string)($config['user'] ?? 'root'),
            (string)($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
        return $instancia;
    } catch (PDOException $e) {
        error_log('Fallo de conexión PDO con MySQL: ' . $e->getMessage());
        http_response_code(503);
        // Respuesta opaca y segura para el cliente, sin exponer credenciales
        include dirname(__DIR__) . '/views/error_503.php';
        exit;
    }
}
