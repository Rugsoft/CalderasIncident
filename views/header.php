<?php
declare(strict_types=1);

/**
 * header.php
 * Plantilla de cabecera común y navegación RBAC para Calderas CESI
 */

require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/auth.php';

$usuario = usuario_actual();
$flash = get_flash();
$tituloPagina = $tituloPagina ?? 'Portal de Incidencias y Mantenimiento';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($tituloPagina) ?> - Calderas CESI</title>
  <link rel="stylesheet" href="assets/css/cesi.css">
</head>
<body>

<header class="site-header">
  <div class="header-container">
    <a href="index.php" class="brand-link">
      <div>
        <div class="brand-logo-text">CALDERAS <span>CESI</span></div>
        <span class="brand-tagline">Servicio Técnico Oficial y Mantenimiento</span>
      </div>
    </a>

    <nav>
      <ul class="nav-links">
        <li><a href="index.php">Inicio</a></li>
        
        <?php if ($usuario): ?>
          <li><a href="panel.php">Mi Panel</a></li>
          <?php if ($usuario['rol'] === 'solicitante'): ?>
            <li><a href="nueva_incidencia.php" style="background-color: var(--cesi-flame-primary); color: #fff;">+ Reportar Avería</a></li>
          <?php endif; ?>
          <li class="nav-badge-user">
            <strong><?= e($usuario['nombre']) ?></strong> (<?= e(ucfirst($usuario['rol'])) ?>)
          </li>
          <li>
            <form action="logout.php" method="POST" style="display:inline;">
              <?= campo_csrf() ?>
              <button type="submit" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.2); color:#fff; border:none;">Salir</button>
            </form>
          </li>
        <?php else: ?>
          <li><a href="login.php">Iniciar Sesión</a></li>
          <li><a href="registro.php" style="background-color: var(--cesi-flame-primary); color: #fff;">Registrarse</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>

<main class="main-content">
  <?php if ($flash): ?>
    <div class="flash-message flash-<?= e($flash['tipo']) ?>">
      <span><?= e($flash['mensaje']) ?></span>
    </div>
  <?php endif; ?>
