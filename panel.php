<?php
declare(strict_types=1);

/**
 * panel.php
 * Panel de control adaptativo según rol RBAC (Cliente, Técnico o Administrador)
 */

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();
exigir_autenticacion();

$usuario = usuario_actual();
$pdo = conectar();

$filtroEstado = trim($_GET['estado'] ?? '');
$rol = $usuario['rol'];

// Procesamiento de asignación rápida por parte del Administrador
if ($rol === 'administrador' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'asignar_rapido') {
    $token = $_POST['csrf_token'] ?? null;
    if (validar_token_csrf($token)) {
        $incId = (int)($_POST['incidencia_id'] ?? 0);
        $tecId = !empty($_POST['tecnico_id']) ? (int)$_POST['tecnico_id'] : null;

        if ($incId > 0) {
            $nuevoEstado = $tecId ? 'asignada' : 'nueva';
            $stmtAsig = $pdo->prepare('UPDATE incidencias SET tecnico_asignado_id = :tec, estado = :est WHERE id = :id');
            $stmtAsig->execute(['tec' => $tecId, 'est' => $nuevoEstado, 'id' => $incId]);

            // Registrar en historial
            $stmtHist = $pdo->prepare('INSERT INTO historial_estados (incidencia_id, usuario_id, estado_anterior, estado_nuevo, motivo, cambiado_en)
                                       VALUES (:inc, :usr, NULL, :est, :mot, NOW())');
            $stmtHist->execute([
                'inc' => $incId,
                'usr' => $usuario['id'],
                'est' => $nuevoEstado,
                'mot' => $tecId ? 'Asignación rápida de técnico desde panel de administración' : 'Desasignación de técnico',
            ]);

            set_flash('exito', 'Técnico asignado correctamente.');
            header('Location: panel.php');
            exit;
        }
    }
}

// Cargar técnicos activos para el selector de admin
$tecnicos = [];
if ($rol === 'administrador') {
    $tecnicos = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'tecnico' AND activo = 1 ORDER BY nombre ASC")->fetchAll();
}

// Lógica de consulta según rol
$params = [];
$clausulaWhere = [];

if ($rol === 'solicitante') {
    $clausulaWhere[] = 'i.solicitante_id = :solicitante_id';
    $params['solicitante_id'] = $usuario['id'];
} elseif ($rol === 'tecnico') {
    // El técnico ve las suyas o las nuevas sin asignar
    $ver = $_GET['ver'] ?? 'mias';
    if ($ver === 'nuevas') {
        $clausulaWhere[] = 'i.estado = "nueva"';
    } else {
        $clausulaWhere[] = 'i.tecnico_asignado_id = :tecnico_id';
        $params['tecnico_id'] = $usuario['id'];
    }
}

if ($filtroEstado !== '') {
    $clausulaWhere[] = 'i.estado = :estado';
    $params['estado'] = $filtroEstado;
}

$whereSql = !empty($clausulaWhere) ? 'WHERE ' . implode(' AND ', $clausulaWhere) : '';

$sql = "SELECT 
            i.id, i.referencia, i.titulo, i.prioridad, i.estado, i.creada_en,
            i.direccion_instalacion, i.telefono_contacto,
            u.nombre AS cliente_nombre,
            t.nombre AS tecnico_nombre,
            m.nombre AS modelo_caldera,
            c.nombre AS categoria_nombre
        FROM incidencias i
        JOIN usuarios u ON i.solicitante_id = u.id
        LEFT JOIN usuarios t ON i.tecnico_asignado_id = t.id
        LEFT JOIN modelos_caldera m ON i.modelo_caldera_id = m.id
        JOIN categorias_averia c ON i.categoria_id = c.id
        $whereSql
        ORDER BY 
            FIELD(i.prioridad, 'urgente', 'alta', 'media', 'baja'),
            i.creada_en DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidencias = $stmt->fetchAll();

// Métricas de panel
if ($rol === 'solicitante') {
    $stTotal = $pdo->prepare('SELECT COUNT(*) FROM incidencias WHERE solicitante_id = ?');
    $stTotal->execute([$usuario['id']]);
    $totalCasos = (int)$stTotal->fetchColumn();

    $stPend = $pdo->prepare('SELECT COUNT(*) FROM incidencias WHERE solicitante_id = ? AND estado IN ("nueva", "asignada", "en_proceso")');
    $stPend->execute([$usuario['id']]);
    $totalPend = (int)$stPend->fetchColumn();

    $stRes = $pdo->prepare('SELECT COUNT(*) FROM incidencias WHERE solicitante_id = ? AND estado IN ("resuelta", "cerrada")');
    $stRes->execute([$usuario['id']]);
    $totalRes = (int)$stRes->fetchColumn();
} elseif ($rol === 'tecnico') {
    $stMias = $pdo->prepare('SELECT COUNT(*) FROM incidencias WHERE tecnico_asignado_id = ? AND estado IN ("asignada", "en_proceso")');
    $stMias->execute([$usuario['id']]);
    $totalCasos = (int)$stMias->fetchColumn();

    $stUrg = $pdo->prepare('SELECT COUNT(*) FROM incidencias WHERE tecnico_asignado_id = ? AND prioridad = "urgente" AND estado != "resuelta"');
    $stUrg->execute([$usuario['id']]);
    $totalPend = (int)$stUrg->fetchColumn();

    $stNuevas = $pdo->query('SELECT COUNT(*) FROM incidencias WHERE estado = "nueva"');
    $totalRes = (int)$stNuevas->fetchColumn();
} else { // admin
    $totalCasos = (int)$pdo->query('SELECT COUNT(*) FROM incidencias')->fetchColumn();
    $totalPend = (int)$pdo->query('SELECT COUNT(*) FROM incidencias WHERE estado = "nueva"')->fetchColumn();
    $totalRes = (int)$pdo->query('SELECT COUNT(*) FROM incidencias WHERE estado IN ("asignada", "en_proceso")')->fetchColumn();
}

$tituloPagina = 'Panel de ' . ucfirst($rol);
require_once __DIR__ . '/views/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size:1.8rem; font-weight:800; color:var(--cesi-blue-dark);">
      Panel de <?= e(ucfirst($rol)) ?> &bull; <span style="font-weight:400; color:var(--cesi-text-muted);"><?= e($usuario['nombre']) ?></span>
    </h1>
    <p style="color:var(--cesi-text-muted); font-size:0.9rem;">Gestor Operativo de Calderas CESI</p>
  </div>

  <?php if ($rol === 'solicitante'): ?>
    <a href="nueva_incidencia.php" class="btn btn-flame">+ Reportar Nueva Avería / Mantenimiento</a>
  <?php endif; ?>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label"><?= ($rol === 'solicitante') ? 'Mis Incidencias Totales' : (($rol === 'tecnico') ? 'Mis Averías Asignadas' : 'Total Incidencias Sistema') ?></div>
    <div class="stat-number"><?= $totalCasos ?></div>
  </div>
  <div class="stat-card alert">
    <div class="stat-label"><?= ($rol === 'solicitante') ? 'Avisos en Curso' : (($rol === 'tecnico') ? 'Averías Urgentes Activas' : 'Avisos Nuevos sin Asignar') ?></div>
    <div class="stat-number"><?= $totalPend ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label"><?= ($rol === 'solicitante') ? 'Calderas Reparadas' : (($rol === 'tecnico') ? 'Bolsa de Casos Nuevos' : 'En Reparación Actualmente') ?></div>
    <div class="stat-number"><?= $totalRes ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">
      <?php if ($rol === 'solicitante'): ?>
        Mis Solicitudes y Averías de Caldera
      <?php elseif ($rol === 'tecnico'): ?>
        Cola de Trabajo del Técnico de Calderas
      <?php else: ?>
        Consola Global de Incidencias CESI
      <?php endif; ?>
    </h2>

    <!-- Filtros rápidos según rol -->
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      <?php if ($rol === 'tecnico'): ?>
        <a href="panel.php?ver=mias" class="btn btn-sm <?= (($_GET['ver'] ?? 'mias') === 'mias') ? 'btn-primary' : 'btn-secondary' ?>">Mis Asignadas</a>
        <a href="panel.php?ver=nuevas" class="btn btn-sm <?= (($_GET['ver'] ?? '') === 'nuevas') ? 'btn-flame' : 'btn-secondary' ?>">Bolsa de Nuevas</a>
      <?php endif; ?>

      <form action="panel.php" method="GET" style="display:inline-flex; gap:0.35rem;">
        <?php if ($rol === 'tecnico' && isset($_GET['ver'])): ?>
          <input type="hidden" name="ver" value="<?= e($_GET['ver']) ?>">
        <?php endif; ?>
        <select name="estado" class="form-control" style="padding:0.3rem 0.6rem; font-size:0.85rem;" onchange="this.form.submit()">
          <option value="">-- Todos los estados --</option>
          <option value="nueva" <?= ($filtroEstado === 'nueva') ? 'selected' : '' ?>>Nueva</option>
          <option value="asignada" <?= ($filtroEstado === 'asignada') ? 'selected' : '' ?>>Asignada</option>
          <option value="en_proceso" <?= ($filtroEstado === 'en_proceso') ? 'selected' : '' ?>>En Proceso</option>
          <option value="resuelta" <?= ($filtroEstado === 'resuelta') ? 'selected' : '' ?>>Resuelta</option>
          <option value="cerrada" <?= ($filtroEstado === 'cerrada') ? 'selected' : '' ?>>Cerrada</option>
        </select>
      </form>
    </div>
  </div>

  <?php if (empty($incidencias)): ?>
    <div style="text-align:center; padding:3rem 1rem; color:var(--cesi-text-muted);">
      <p style="font-size:1.1rem; margin-bottom:1rem;">No hay incidencias que coincidan con el criterio seleccionado.</p>
      <?php if ($rol === 'solicitante'): ?>
        <a href="nueva_incidencia.php" class="btn btn-flame">Abrir Primer Reporte de Caldera</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Ref.</th>
            <th>Fecha</th>
            <th>Modelo Caldera</th>
            <th>Tipo Avería / Resumen</th>
            <?php if ($rol !== 'solicitante'): ?>
              <th>Cliente / Dirección</th>
              <th>Técnico Asignado</th>
            <?php endif; ?>
            <th>Prioridad</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incidencias as $inc): ?>
            <tr>
              <td>
                <a href="ver_incidencia.php?id=<?= (int)$inc['id'] ?>" style="font-family:monospace; font-weight:700; color:var(--cesi-blue-primary); text-decoration:none;">
                  <?= e($inc['referencia']) ?>
                </a>
              </td>
              <td><?= formatear_fecha($inc['creada_en'], 'd/m/Y') ?></td>
              <td>
                <strong><?= e($inc['modelo_caldera'] ?? 'No especificado') ?></strong>
              </td>
              <td>
                <div style="font-weight:600;"><?= e($inc['titulo']) ?></div>
                <small style="color:var(--cesi-text-muted);"><?= e($inc['categoria_nombre']) ?></small>
              </td>

              <?php if ($rol !== 'solicitante'): ?>
                <td>
                  <div><?= e($inc['cliente_nombre']) ?></div>
                  <small style="color:var(--cesi-text-muted);"><?= e($inc['direccion_instalacion']) ?></small>
                </td>
                <td>
                  <?php if ($rol === 'administrador'): ?>
                    <form action="panel.php" method="POST" style="display:inline-flex; align-items:center; gap:0.25rem;">
                      <?= campo_csrf() ?>
                      <input type="hidden" name="accion" value="asignar_rapido">
                      <input type="hidden" name="incidencia_id" value="<?= (int)$inc['id'] ?>">
                      <select name="tecnico_id" class="form-control" style="font-size:0.8rem; padding:0.25rem; width:130px;" onchange="this.form.submit()">
                        <option value="">-- Sin técnico --</option>
                        <?php foreach ($tecnicos as $t): ?>
                          <option value="<?= (int)$t['id'] ?>" <?= ($inc['tecnico_nombre'] === $t['nombre']) ? 'selected' : '' ?>>
                            <?= e($t['nombre']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                  <?php else: ?>
                    <?= $inc['tecnico_nombre'] ? e($inc['tecnico_nombre']) : '<span style="color:#e67e22; font-weight:600;">Sin asignar</span>' ?>
                  <?php endif; ?>
                </td>
              <?php endif; ?>

              <td><?= badge_prioridad($inc['prioridad']) ?></td>
              <td><?= badge_estado($inc['estado']) ?></td>
              <td>
                <a href="ver_incidencia.php?id=<?= (int)$inc['id'] ?>" class="btn btn-secondary btn-sm">Ver Detalle</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
