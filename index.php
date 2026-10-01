<?php
declare(strict_types=1);

/**
 * index.php
 * Portal público de bienvenida y acceso para clientes y técnicos de Calderas CESI
 */

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();
$usuario = usuario_actual();
$pdo = conectar();

// Consulta rápida de modelos y categorías para mostrar servicios
$stmtModelos = $pdo->query('SELECT nombre, combustible, potencia_kw FROM modelos_caldera WHERE activo = 1 LIMIT 4');
$modelosDestacados = $stmtModelos->fetchAll();

// Si se realiza una búsqueda rápida por referencia pública (ej: CESI-2026-0001)
$busquedaReferencia = trim($_GET['ref'] ?? '');
$resultadoBusqueda = null;
if ($busquedaReferencia !== '') {
    $stmtBusq = $pdo->prepare('SELECT id, referencia, titulo, estado, prioridad, creada_en FROM incidencias WHERE referencia = :ref');
    $stmtBusq->execute(['ref' => $busquedaReferencia]);
    $resultadoBusqueda = $stmtBusq->fetch();
}

$tituloPagina = 'Servicio Técnico Oficial';
require_once __DIR__ . '/views/header.php';
?>

<div style="background: linear-gradient(rgba(13,34,56,0.85), rgba(21,67,96,0.85)), url('assets/css/banner.jpg') center/cover; color:#fff; border-radius: var(--cesi-radius); padding: 3rem 2rem; margin-bottom: 2.5rem; text-align:center;">
  <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.8rem;">
    Servicio Técnico y Mantenimiento de <span style="color:var(--cesi-flame-light);">Calderas CESI</span>
  </h1>
  <p style="font-size: 1.15rem; max-width: 750px; margin: 0 auto 1.75rem; color: #d5dbdb;">
    Gestión integral de incidencias, averías de calefacción y revisiones periódicas oficiales (RITE). Atención rápida para mantener su confort y seguridad.
  </p>

  <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
    <?php if ($usuario): ?>
      <a href="panel.php" class="btn btn-flame" style="font-size:1.1rem; padding:0.8rem 1.8rem;">Ir a Mi Panel de Control</a>
      <?php if ($usuario['rol'] === 'solicitante'): ?>
        <a href="nueva_incidencia.php" class="btn btn-primary" style="background:#fff; color:var(--cesi-blue-dark); font-size:1.1rem; padding:0.8rem 1.8rem;">Reportar Nueva Avería</a>
      <?php endif; ?>
    <?php else: ?>
      <a href="registro.php" class="btn btn-flame" style="font-size:1.1rem; padding:0.8rem 1.8rem;">Registrar Avería / Cliente Nuevo</a>
      <a href="login.php" class="btn btn-primary" style="background:#fff; color:var(--cesi-blue-dark); font-size:1.1rem; padding:0.8rem 1.8rem;">Acceso Clientes y Técnicos</a>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom: 2rem;">
  <div class="card-header">
    <h2 class="card-title">🔍 Consulta Rápida de Estado por Referencia</h2>
  </div>
  <p style="color:var(--cesi-text-muted); margin-bottom:1rem; font-size:0.95rem;">
    Si ya dispone de un código de parte de servicio de Calderas CESI (ej: <code>CESI-2026-0001</code>), puede consultar su estado actual directamente:
  </p>
  <form action="index.php" method="GET" style="display:flex; gap:0.75rem; max-width:600px; flex-wrap:wrap;">
    <input type="text" name="ref" value="<?= e($busquedaReferencia) ?>" placeholder="Ej: CESI-2026-0001" class="form-control" style="flex:1; min-width:240px;" required>
    <button type="submit" class="btn btn-primary">Consultar</button>
  </form>

  <?php if ($busquedaReferencia !== ''): ?>
    <div style="margin-top:1.5rem; padding:1.25rem; background:#f8f9fa; border-radius:var(--cesi-radius); border-left:4px solid var(--cesi-blue-primary);">
      <?php if ($resultadoBusqueda): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
          <div>
            <strong style="font-size:1.1rem; font-family:monospace;"><?= e($resultadoBusqueda['referencia']) ?></strong> &bull; <?= e($resultadoBusqueda['titulo']) ?>
            <div style="color:var(--cesi-text-muted); font-size:0.85rem; margin-top:0.3rem;">Registrada el: <?= formatear_fecha($resultadoBusqueda['creada_en']) ?></div>
          </div>
          <div>
            <?= badge_estado($resultadoBusqueda['estado']) ?>
            <?= badge_prioridad($resultadoBusqueda['prioridad']) ?>
            <a href="ver_incidencia.php?id=<?= (int)$resultadoBusqueda['id'] ?>" class="btn btn-secondary btn-sm" style="margin-left:0.5rem;">Ver Ficha Completa</a>
          </div>
        </div>
      <?php else: ?>
        <p style="color:var(--cesi-danger); margin:0;">
          No se encontró ninguna incidencia con la referencia <strong><?= e($busquedaReferencia) ?></strong>. Compruebe el código o inicie sesión en su panel.
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Modelos Oficiales CESI</div>
    <div class="stat-number">6</div>
    <small style="color:var(--cesi-text-muted);">Gas, Pellet, Aerotermia</small>
  </div>
  <div class="stat-card alert">
    <div class="stat-label">Urgencias Técnicas 24h</div>
    <div class="stat-number">900 123 456</div>
    <small style="color:var(--cesi-flame-primary); font-weight:bold;">Guardia Activa</small>
  </div>
  <div class="stat-card">
    <div class="stat-label">Garantía de Reparación</div>
    <div class="stat-number">100%</div>
    <small style="color:var(--cesi-text-muted);">Repuestos originales</small>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Gama de Calderas y Sistemas en Mantenimiento</h2>
  </div>
  <div class="table-responsive">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Modelo Caldera</th>
          <th>Tipo Combustible</th>
          <th>Potencia Térmica</th>
          <th>Cobertura Técnica</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($modelosDestacados as $m): ?>
          <tr>
            <td><strong><?= e($m['nombre']) ?></strong></td>
            <td><span class="badge" style="background:#eaf2f8; color:#2471a3;"><?= e(ucfirst(str_replace('_', ' ', $m['combustible']))) ?></span></td>
            <td><?= $m['potencia_kw'] ? e($m['potencia_kw']) . ' kW' : 'Modular' ?></td>
            <td><span style="color:var(--cesi-success); font-weight:600;">✓ Mantenimiento Oficial CESI</span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
