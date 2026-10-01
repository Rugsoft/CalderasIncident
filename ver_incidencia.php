<?php
declare(strict_types=1);

/**
 * ver_incidencia.php
 * Ficha detallada de incidencia de caldera, seguimiento, notas técnicas y estados (Manuales 5, 7, 8, 9)
 */

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();
exigir_autenticacion();

$usuario = usuario_actual();
$pdo = conectar();

$idIncidencia = (int)($_GET['id'] ?? 0);
if ($idIncidencia <= 0) {
    set_flash('error', 'Identificador de incidencia no válido.');
    header('Location: panel.php');
    exit;
}

// 1. Consulta de la incidencia y verificación de permisos (Anti-IDOR)
$sqlInc = 'SELECT 
                i.*,
                u.nombre AS cliente_nombre,
                u.email AS cliente_email,
                t.nombre AS tecnico_nombre,
                t.email AS tecnico_email,
                m.nombre AS modelo_caldera_nombre,
                m.codigo AS modelo_caldera_codigo,
                m.combustible AS modelo_combustible,
                m.potencia_kw AS modelo_potencia,
                c.nombre AS categoria_nombre
           FROM incidencias i
           JOIN usuarios u ON i.solicitante_id = u.id
           LEFT JOIN usuarios t ON i.tecnico_asignado_id = t.id
           LEFT JOIN modelos_caldera m ON i.modelo_caldera_id = m.id
           JOIN categorias_averia c ON i.categoria_id = c.id
           WHERE i.id = :id LIMIT 1';
$stmtInc = $pdo->prepare($sqlInc);
$stmtInc->execute(['id' => $idIncidencia]);
$incidencia = $stmtInc->fetch();

if (!$incidencia) {
    set_flash('error', 'La incidencia solicitada no existe.');
    header('Location: panel.php');
    exit;
}

// Control Anti-IDOR: Si es solicitante, debe ser el titular de la incidencia
if ($usuario['rol'] === 'solicitante' && (int)$incidencia['solicitante_id'] !== $usuario['id']) {
    error_log(sprintf('Intento de acceso IDOR detectado: Usuario %d intentó ver incidencia %d', $usuario['id'], $idIncidencia));
    set_flash('error', 'No tiene autorización para consultar esta incidencia.');
    header('Location: panel.php');
    exit;
}

// 2. Procesamiento de Acciones (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!validar_token_csrf($token)) {
        set_flash('error', 'Token de seguridad inválido. Operación cancelada.');
        header('Location: ver_incidencia.php?id=' . $idIncidencia);
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    // A. Publicar nuevo comentario o nota interna
    if ($accion === 'comentar') {
        $mensaje = trim($_POST['mensaje'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'publico');

        // Los clientes solo pueden redactar comentarios públicos
        if ($usuario['rol'] === 'solicitante' || !in_array($tipo, ['publico', 'interno'], true)) {
            $tipo = 'publico';
        }

        if (mb_strlen($mensaje) >= 3) {
            $stmtCom = $pdo->prepare('INSERT INTO comentarios (incidencia_id, usuario_id, mensaje, tipo, creado_en)
                                      VALUES (:inc, :usr, :msg, :tipo, NOW())');
            $stmtCom->execute([
                'inc'  => $idIncidencia,
                'usr'  => $usuario['id'],
                'msg'  => $mensaje,
                'tipo' => $tipo,
            ]);
            set_flash('exito', ($tipo === 'interno') ? 'Nota técnica interna registrada.' : 'Comentario añadido a la ficha de la caldera.');
        } else {
            set_flash('error', 'El mensaje debe tener al menos 3 caracteres.');
        }
        header('Location: ver_incidencia.php?id=' . $idIncidencia);
        exit;
    }

    // B. Cambio de Estado Operativo (Solo Técnicos o Administradores)
    if ($accion === 'cambiar_estado' && in_array($usuario['rol'], ['tecnico', 'administrador'], true)) {
        $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');
        $estadosPermitidos = ['nueva', 'asignada', 'en_proceso', 'resuelta', 'cerrada'];

        if (in_array($nuevoEstado, $estadosPermitidos, true) && $nuevoEstado !== $incidencia['estado']) {
            try {
                $pdo->beginTransaction();

                // Actualizar cabecera
                $stmtUp = $pdo->prepare('UPDATE incidencias SET estado = :nuevo WHERE id = :id');
                $stmtUp->execute(['nuevo' => $nuevoEstado, 'id' => $idIncidencia]);

                // Registrar en auditoría inmutable
                $stmtHist = $pdo->prepare('INSERT INTO historial_estados (
                    incidencia_id, usuario_id, estado_anterior, estado_nuevo, motivo, cambiado_en
                ) VALUES (
                    :inc, :usr, :ant, :nuevo, :motivo, NOW()
                )');
                $stmtHist->execute([
                    'inc'    => $idIncidencia,
                    'usr'    => $usuario['id'],
                    'ant'    => $incidencia['estado'],
                    'nuevo'  => $nuevoEstado,
                    'motivo' => $motivo !== '' ? $motivo : 'Cambio de estado operativo del técnico/admin',
                ]);

                $pdo->commit();
                set_flash('exito', 'Estado de la caldera actualizado a: ' . ucfirst(str_replace('_', ' ', $nuevoEstado)));
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Error en cambio de estado: ' . $e->getMessage());
                set_flash('error', 'No se pudo cambiar el estado en la base de datos.');
            }
        }
        header('Location: ver_incidencia.php?id=' . $idIncidencia);
        exit;
    }

    // C. Tomar / Asignar técnico
    if ($accion === 'tomar_caso' && $usuario['rol'] === 'tecnico') {
        $pdo->beginTransaction();
        $stmtTomar = $pdo->prepare('UPDATE incidencias SET tecnico_asignado_id = :tec, estado = "asignada" WHERE id = :id');
        $stmtTomar->execute(['tec' => $usuario['id'], 'id' => $idIncidencia]);

        $stmtHist = $pdo->prepare('INSERT INTO historial_estados (incidencia_id, usuario_id, estado_anterior, estado_nuevo, motivo, cambiado_en)
                                   VALUES (:inc, :usr, :ant, "asignada", "Caso asumido por técnico para intervención", NOW())');
        $stmtHist->execute([
            'inc' => $idIncidencia,
            'usr' => $usuario['id'],
            'ant' => $incidencia['estado'],
        ]);
        $pdo->commit();

        set_flash('exito', 'Ha asumido la avería de esta caldera. Ya aparece en su cola de trabajo.');
        header('Location: ver_incidencia.php?id=' . $idIncidencia);
        exit;
    }
}

// 3. Cargar comentarios según visibilidad RBAC
$filtroComentarios = ($usuario['rol'] === 'solicitante') ? 'AND c.tipo = "publico"' : '';
$sqlCom = "SELECT c.*, u.nombre AS autor_nombre, u.rol AS autor_rol
           FROM comentarios c
           JOIN usuarios u ON c.usuario_id = u.id
           WHERE c.incidencia_id = :id $filtroComentarios
           ORDER BY c.creado_en ASC";
$stmtCom = $pdo->prepare($sqlCom);
$stmtCom->execute(['id' => $idIncidencia]);
$comentarios = $stmtCom->fetchAll();

// 4. Cargar historial de auditoría
$sqlHist = 'SELECT h.*, u.nombre AS autor_nombre
            FROM historial_estados h
            JOIN usuarios u ON h.usuario_id = u.id
            WHERE h.incidencia_id = :id
            ORDER BY h.cambiado_en DESC';
$stmtHist = $pdo->prepare($sqlHist);
$stmtHist->execute(['id' => $idIncidencia]);
$historial = $stmtHist->fetchAll();

$tituloPagina = 'Incidencia ' . $incidencia['referencia'];
require_once __DIR__ . '/views/header.php';
?>

<div style="margin-bottom:1.5rem;">
  <a href="panel.php" class="btn btn-secondary btn-sm">&larr; Volver al Panel</a>
</div>

<div class="incident-header-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div>
      <span class="incident-ref"><?= e($incidencia['referencia']) ?></span>
      <h1 style="font-size:1.6rem; color:var(--cesi-blue-dark); margin:0.3rem 0;"><?= e($incidencia['titulo']) ?></h1>
      <span style="font-size:0.85rem; color:var(--cesi-text-muted);">
        Registrada el <?= formatear_fecha($incidencia['creada_en']) ?> por <strong><?= e($incidencia['cliente_nombre']) ?></strong>
      </span>
    </div>
    <div style="display:flex; gap:0.5rem; align-items:center;">
      <?= badge_prioridad($incidencia['prioridad']) ?>
      <?= badge_estado($incidencia['estado']) ?>
    </div>
  </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
  <!-- Ficha Técnica de la Caldera -->
  <div class="card" style="margin-bottom:0;">
    <h3 style="font-size:1.15rem; color:var(--cesi-blue-primary); border-bottom:2px solid #ebedef; padding-bottom:0.5rem; margin-bottom:1rem;">
      🔥 Ficha Técnica del Equipo CESI
    </h3>
    <table class="table-custom" style="font-size:0.9rem;">
      <tr>
        <th style="width:40%;">Modelo Caldera:</th>
        <td><strong><?= e($incidencia['modelo_caldera_nombre'] ?? 'No catalogada') ?></strong></td>
      </tr>
      <tr>
        <th>Código Gama:</th>
        <td><code><?= e($incidencia['modelo_caldera_codigo'] ?? '-') ?></code></td>
      </tr>
      <tr>
        <th>Combustible:</th>
        <td><?= e(ucfirst(str_replace('_', ' ', $incidencia['modelo_combustible'] ?? 'Gas'))) ?></td>
      </tr>
      <tr>
        <th>Nº de Serie:</th>
        <td><code style="background:#eaf2f8; padding:0.2rem 0.4rem; border-radius:4px;"><?= e($incidencia['numero_serie'] ?? 'No aportado') ?></code></td>
      </tr>
      <tr>
        <th>Tipo Avería / Servicio:</th>
        <td><span style="color:var(--cesi-flame-primary); font-weight:600;"><?= e($incidencia['categoria_nombre']) ?></span></td>
      </tr>
    </table>
  </div>

  <!-- Ficha de Ubicación y Asignación -->
  <div class="card" style="margin-bottom:0;">
    <h3 style="font-size:1.15rem; color:var(--cesi-blue-primary); border-bottom:2px solid #ebedef; padding-bottom:0.5rem; margin-bottom:1rem;">
      📍 Ubicación y Técnico Asignado
    </h3>
    <table class="table-custom" style="font-size:0.9rem;">
      <tr>
        <th style="width:40%;">Titular Caldera:</th>
        <td><?= e($incidencia['cliente_nombre']) ?></td>
      </tr>
      <tr>
        <th>Dirección Inmueble:</th>
        <td><strong><?= e($incidencia['direccion_instalacion']) ?></strong></td>
      </tr>
      <tr>
        <th>Teléfono Contacto:</th>
        <td><a href="tel:<?= e($incidencia['telefono_contacto']) ?>" style="color:var(--cesi-blue-light); font-weight:600; text-decoration:none;">📞 <?= e($incidencia['telefono_contacto']) ?></a></td>
      </tr>
      <tr>
        <th>Técnico Responsable:</th>
        <td>
          <?php if ($incidencia['tecnico_nombre']): ?>
            <span style="color:var(--cesi-success); font-weight:700;">👨‍🔧 <?= e($incidencia['tecnico_nombre']) ?></span>
          <?php else: ?>
            <span style="color:var(--cesi-flame-primary); font-weight:600;">Sin técnico asignado</span>
            <?php if ($usuario['rol'] === 'tecnico'): ?>
              <form action="ver_incidencia.php?id=<?= $idIncidencia ?>" method="POST" style="display:inline; margin-left:0.5rem;">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="tomar_caso">
                <button type="submit" class="btn btn-flame btn-sm" style="padding:0.2rem 0.5rem;">Asignarme</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    </table>
  </div>
</div>

<!-- Descripción de Síntomas -->
<div class="card">
  <h3 style="font-size:1.15rem; color:var(--cesi-blue-primary); margin-bottom:0.75rem;">
    📝 Síntomas Comunicados por el Cliente
  </h3>
  <div style="background:#f8f9fa; padding:1.25rem; border-radius:var(--cesi-radius); border-left:4px solid var(--cesi-blue-light); font-size:0.95rem; line-height:1.7;">
    <?= nl2br(e($incidencia['descripcion'])) ?>
  </div>
</div>

<!-- Panel de Gestión Operativa (Técnicos y Admin) -->
<?php if (in_array($usuario['rol'], ['tecnico', 'administrador'], true)): ?>
  <div class="card" style="background:#fdfefe; border:2px solid var(--cesi-blue-light);">
    <div class="card-header">
      <h3 class="card-title" style="font-size:1.2rem;">⚙️ Control Operativo de Reparación</h3>
      <span class="badge" style="background:var(--cesi-blue-primary); color:#fff;">Zona Técnica</span>
    </div>

    <form action="ver_incidencia.php?id=<?= $idIncidencia ?>" method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem; align-items:end;">
      <?= campo_csrf() ?>
      <input type="hidden" name="accion" value="cambiar_estado">

      <div class="form-group" style="margin-bottom:0;">
        <label for="nuevo_estado" class="form-label">Avanzar Estado Operativo:</label>
        <select id="nuevo_estado" name="nuevo_estado" class="form-control" required>
          <option value="nueva" <?= ($incidencia['estado'] === 'nueva') ? 'selected' : '' ?>>Nueva (Aviso entrante)</option>
          <option value="asignada" <?= ($incidencia['estado'] === 'asignada') ? 'selected' : '' ?>>Asignada a técnico</option>
          <option value="en_proceso" <?= ($incidencia['estado'] === 'en_proceso') ? 'selected' : '' ?>>En Proceso (En reparación)</option>
          <option value="resuelta" <?= ($incidencia['estado'] === 'resuelta') ? 'selected' : '' ?>>Resuelta (Caldera operativa)</option>
          <option value="cerrada" <?= ($incidencia['estado'] === 'cerrada') ? 'selected' : '' ?>>Cerrada (Conformidad cliente)</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label for="motivo" class="form-label">Motivo o Justificación del Cambio:</label>
        <input type="text" id="motivo" name="motivo" class="form-control" placeholder="Ej: Sustituido vaso de expansión y purgado">
      </div>

      <div>
        <button type="submit" class="btn btn-primary" style="height:44px; width:100%;">Actualizar Estado</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<!-- Hilo de Mensajes y Notas Técnicas -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">💬 Hilo de Seguimiento y Notas de Mantenimiento</h3>
  </div>

  <?php if (empty($comentarios)): ?>
    <p style="color:var(--cesi-text-muted); font-size:0.9rem; margin-bottom:1.5rem;">
      No hay mensajes ni comentarios registrados todavía en esta incidencia.
    </p>
  <?php else: ?>
    <div style="margin-bottom:2rem;">
      <?php foreach ($comentarios as $c): ?>
        <div class="comment-card <?= ($c['tipo'] === 'interno') ? 'interno' : '' ?>">
          <div class="comment-header">
            <div>
              <strong><?= e($c['autor_nombre']) ?></strong>
              <span class="badge" style="background:#eaeded; color:#333; font-size:0.7rem;"><?= e(ucfirst($c['autor_rol'])) ?></span>
              <?php if ($c['tipo'] === 'interno'): ?>
                <span class="badge" style="background:#f39c12; color:#fff; font-size:0.7rem;">🔒 NOTA TÉCNICA INTERNA</span>
              <?php endif; ?>
            </div>
            <span><?= formatear_fecha($c['creado_en']) ?></span>
          </div>
          <div style="font-size:0.95rem; color:var(--cesi-text-dark);">
            <?= nl2br(e($c['mensaje'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Formulario para Añadir Comentario -->
  <form action="ver_incidencia.php?id=<?= $idIncidencia ?>" method="POST" style="border-top:1px solid #ebedef; padding-top:1.5rem;">
    <?= campo_csrf() ?>
    <input type="hidden" name="accion" value="comentar">

    <div class="form-group">
      <label for="mensaje" class="form-label">Añadir mensaje al expediente:</label>
      <textarea id="mensaje" name="mensaje" class="form-control" rows="3" required placeholder="Escriba aquí cualquier consulta, información para el técnico o apunte de la reparación..."></textarea>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <?php if (in_array($usuario['rol'], ['tecnico', 'administrador'], true)): ?>
        <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem;">
          <label><strong>Tipo de nota:</strong></label>
          <label style="margin-left:0.5rem;"><input type="radio" name="tipo" value="publico" checked> Público (visible para cliente)</label>
          <label style="margin-left:0.5rem;"><input type="radio" name="tipo" value="interno"> 🔒 Interno (solo técnicos/admin)</label>
        </div>
      <?php else: ?>
        <input type="hidden" name="tipo" value="publico">
      <?php endif; ?>

      <button type="submit" class="btn btn-flame">Enviar Mensaje</button>
    </div>
  </form>
</div>

<!-- Línea de Tiempo / Auditoría Inmutable (Historial de Estados) -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">📜 Registro Histórico Inmutable de Auditoría</h3>
    <small style="color:var(--cesi-text-muted);">Trazabilidad exigida por normativa de mantenimiento</small>
  </div>

  <ul class="timeline">
    <?php foreach ($historial as $h): ?>
      <li class="timeline-item">
        <div class="timeline-date"><?= formatear_fecha($h['cambiado_en']) ?> &bull; Por: <strong><?= e($h['autor_nombre']) ?></strong></div>
        <div style="margin-top:0.25rem;">
          <?php if ($h['estado_anterior']): ?>
            Transición: <code><?= e($h['estado_anterior']) ?></code> &rarr; <strong><?= badge_estado($h['estado_nuevo']) ?></strong>
          <?php else: ?>
            Creación inicial: <strong><?= badge_estado($h['estado_nuevo']) ?></strong>
          <?php endif; ?>
        </div>
        <?php if ($h['motivo']): ?>
          <div style="font-size:0.85rem; color:var(--cesi-text-muted); margin-top:0.2rem; font-style:italic;">
            "<?= e($h['motivo']) ?>"
          </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
