<?php
declare(strict_types=1);

/**
 * nueva_incidencia.php
 * Formulario de reporte de avería o mantenimiento preventivo con transacción atómica (Manual 5)
 */

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';

iniciar_sesion_segura();
exigir_autenticacion();

$usuario = usuario_actual();
$pdo = conectar();

// Cargar catálogo de calderas CESI y categorías de avería
$modelos = $pdo->query('SELECT id, codigo, nombre, combustible, potencia_kw FROM modelos_caldera WHERE activo = 1 ORDER BY id ASC')->fetchAll();
$categorias = $pdo->query('SELECT id, nombre, descripcion, prioridad_sugerida FROM categorias_averia WHERE activa = 1 ORDER BY id ASC')->fetchAll();

$errores = [];
$datos = [
    'modelo_caldera_id'     => '',
    'categoria_id'          => '',
    'numero_serie'          => '',
    'direccion_instalacion' => '',
    'telefono_contacto'     => $usuario['telefono'] ?? '',
    'titulo'                => '',
    'descripcion'           => '',
    'prioridad'             => 'media',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!validar_token_csrf($token)) {
        $errores[] = 'Error de seguridad: Solicitud rechazada por token CSRF inválido.';
    } else {
        $datos['modelo_caldera_id']     = trim($_POST['modelo_caldera_id'] ?? '');
        $datos['categoria_id']          = trim($_POST['categoria_id'] ?? '');
        $datos['numero_serie']          = trim($_POST['numero_serie'] ?? '');
        $datos['direccion_instalacion'] = trim($_POST['direccion_instalacion'] ?? '');
        $datos['telefono_contacto']     = trim($_POST['telefono_contacto'] ?? '');
        $datos['titulo']                = trim($_POST['titulo'] ?? '');
        $datos['descripcion']           = trim($_POST['descripcion'] ?? '');
        $datos['prioridad']             = trim($_POST['prioridad'] ?? 'media');

        // Validaciones defensivas
        if (empty($datos['categoria_id'])) {
            $errores[] = 'Debe seleccionar el tipo de problema o avería.';
        }
        if (mb_strlen($datos['direccion_instalacion']) < 5) {
            $errores[] = 'Indique la dirección completa del inmueble donde está instalada la caldera.';
        }
        if (mb_strlen($datos['telefono_contacto']) < 7) {
            $errores[] = 'Indique un teléfono para que el servicio técnico coordine la visita.';
        }
        if (mb_strlen($datos['titulo']) < 5 || mb_strlen($datos['titulo']) > 150) {
            $errores[] = 'El título resumen debe contener entre 5 y 150 caracteres.';
        }
        if (mb_strlen($datos['descripcion']) < 15) {
            $errores[] = 'Por favor, describa con más detalle los síntomas de la caldera o el código de error.';
        }

        $prioridadesValidas = ['baja', 'media', 'alta', 'urgente'];
        if (!in_array($datos['prioridad'], $prioridadesValidas, true)) {
            $datos['prioridad'] = 'media';
        }

        if (empty($errores)) {
            try {
                // Inicio de transacción atómica ACID
                $pdo->beginTransaction();

                // 1. Generar referencia correlativa con bloqueo pesimista
                $referencia = generar_referencia($pdo);

                // 2. Insertar cabecera de incidencia
                $sqlInc = 'INSERT INTO incidencias (
                    referencia, solicitante_id, modelo_caldera_id, categoria_id,
                    numero_serie, direccion_instalacion, telefono_contacto,
                    titulo, descripcion, prioridad, estado, creada_en
                ) VALUES (
                    :referencia, :solicitante_id, :modelo_id, :categoria_id,
                    :num_serie, :direccion, :telefono,
                    :titulo, :descripcion, :prioridad, "nueva", NOW()
                )';
                $stmtInc = $pdo->prepare($sqlInc);
                $stmtInc->execute([
                    'referencia'      => $referencia,
                    'solicitante_id'  => $usuario['id'],
                    'modelo_id'       => !empty($datos['modelo_caldera_id']) ? (int)$datos['modelo_caldera_id'] : null,
                    'categoria_id'    => (int)$datos['categoria_id'],
                    'num_serie'       => $datos['numero_serie'] !== '' ? $datos['numero_serie'] : null,
                    'direccion'       => $datos['direccion_instalacion'],
                    'telefono'        => $datos['telefono_contacto'],
                    'titulo'          => $datos['titulo'],
                    'descripcion'     => $datos['descripcion'],
                    'prioridad'       => $datos['prioridad'],
                ]);

                $incidenciaId = (int)$pdo->lastInsertId();

                // 3. Insertar pista inmutable de auditoría
                $sqlHist = 'INSERT INTO historial_estados (
                    incidencia_id, usuario_id, estado_anterior, estado_nuevo, motivo, cambiado_en
                ) VALUES (
                    :incidencia_id, :usuario_id, NULL, "nueva", "Apertura de aviso de avería / mantenimiento por el titular", NOW()
                )';
                $stmtHist = $pdo->prepare($sqlHist);
                $stmtHist->execute([
                    'incidencia_id' => $incidenciaId,
                    'usuario_id'    => $usuario['id'],
                ]);

                $pdo->commit();

                set_flash('exito', sprintf('Incidencia registrada con éxito. Su número de parte oficial es %s. Un técnico de Calderas CESI revisará su caso.', $referencia));
                header('Location: ver_incidencia.php?id=' . $incidenciaId);
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Error en alta de incidencia: ' . $e->getMessage());
                $errores[] = 'Se produjo un error al guardar la incidencia en la base de datos. Por favor, reinténtelo.';
            }
        }
    }
}

$tituloPagina = 'Reportar Incidencia o Mantenimiento';
require_once __DIR__ . '/views/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto 2rem;">
  <div class="card-header">
    <div>
      <h1 class="card-title">🔧 Reporte de Avería o Mantenimiento de Caldera</h1>
      <p style="color:var(--cesi-text-muted); font-size:0.9rem; margin-top:0.25rem;">
        Complete los datos de su equipo para que el Servicio Técnico Oficial de Calderas CESI gestione su solicitud.
      </p>
    </div>
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

  <form action="nueva_incidencia.php" method="POST">
    <?= campo_csrf() ?>

    <div style="background:#fdf2e9; border-left:4px solid var(--cesi-flame-primary); padding:1rem; border-radius:var(--cesi-radius); margin-bottom:1.5rem;">
      <h3 style="font-size:1.05rem; color:var(--cesi-flame-primary); margin-bottom:0.25rem;">1. Datos de la Caldera y Avería</h3>
      <p style="font-size:0.85rem; color:var(--cesi-text-muted); margin:0;">
        Seleccione el modelo instalado y la sintomatología detectada.
      </p>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="modelo_caldera_id" class="form-label">Modelo de Caldera CESI:</label>
        <select id="modelo_caldera_id" name="modelo_caldera_id" class="form-control">
          <option value="">-- Seleccione su modelo (si lo conoce) --</option>
          <?php foreach ($modelos as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= ((string)$datos['modelo_caldera_id'] === (string)$m['id']) ? 'selected' : '' ?>>
              <?= e($m['nombre']) ?> (<?= e($m['codigo']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-help">Si no conoce el modelo exacto, revise la pegatina en el frontal o lateral del equipo.</div>
      </div>

      <div class="form-group">
        <label for="numero_serie" class="form-label">Número de Serie / Matrícula (opcional):</label>
        <input type="text" id="numero_serie" name="numero_serie" class="form-control" value="<?= e($datos['numero_serie']) ?>" placeholder="Ej: CESI-24-984521">
        <div class="form-help">Figura en la placa metálica de características del aparato.</div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="categoria_id" class="form-label">Tipo de Avería o Servicio (*):</label>
        <select id="categoria_id" name="categoria_id" class="form-control" required>
          <option value="">-- Seleccione el tipo de avería --</option>
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= ((string)$datos['categoria_id'] === (string)$cat['id']) ? 'selected' : '' ?>>
              <?= e($cat['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="prioridad" class="form-label">Nivel de Urgencia:</label>
        <select id="prioridad" name="prioridad" class="form-control" required>
          <option value="baja" <?= ($datos['prioridad'] === 'baja') ? 'selected' : '' ?>>Baja (Revisión preventiva, dudas de funcionamiento)</option>
          <option value="media" <?= ($datos['prioridad'] === 'media') ? 'selected' : '' ?>>Media (Falla puntual, radiadores tibios)</option>
          <option value="alta" <?= ($datos['prioridad'] === 'alta') ? 'selected' : '' ?>>Alta (Sin agua caliente, goteo de presión)</option>
          <option value="urgente" <?= ($datos['prioridad'] === 'urgente') ? 'selected' : '' ?>>URGENTE (Fuga de agua abundante, corte total en invierno)</option>
        </select>
      </div>
    </div>

    <div style="background:#eaf2f8; border-left:4px solid var(--cesi-blue-primary); padding:1rem; border-radius:var(--cesi-radius); margin:1.5rem 0 1rem;">
      <h3 style="font-size:1.05rem; color:var(--cesi-blue-primary); margin-bottom:0.25rem;">2. Ubicación de la Instalación y Contacto</h3>
      <p style="font-size:0.85rem; color:var(--cesi-text-muted); margin:0;">
        Dirección donde debe personarse el técnico de Calderas CESI.
      </p>
    </div>

    <div class="form-row">
      <div class="form-group" style="grid-column: 1 / -1;">
        <label for="direccion_instalacion" class="form-label">Dirección Completa de la Vivienda / Inmueble (*):</label>
        <input type="text" id="direccion_instalacion" name="direccion_instalacion" class="form-control" value="<?= e($datos['direccion_instalacion']) ?>" required placeholder="Calle, número, piso, puerta, código postal y población">
      </div>
    </div>

    <div class="form-group">
      <label for="telefono_contacto" class="form-label">Teléfono de Contacto para la Cita (*):</label>
      <input type="text" id="telefono_contacto" name="telefono_contacto" class="form-control" value="<?= e($datos['telefono_contacto']) ?>" required placeholder="+34 600 000 000">
    </div>

    <div style="background:#f8f9fa; border-left:4px solid var(--cesi-border); padding:1rem; border-radius:var(--cesi-radius); margin:1.5rem 0 1rem;">
      <h3 style="font-size:1.05rem; color:var(--cesi-blue-dark); margin-bottom:0.25rem;">3. Descripción de los Síntomas</h3>
    </div>

    <div class="form-group">
      <label for="titulo" class="form-label">Resumen Breve del Problema (*):</label>
      <input type="text" id="titulo" name="titulo" class="form-control" value="<?= e($datos['titulo']) ?>" required placeholder="Ej: Pérdida continua de presión hasta 0.4 bar y código F22">
    </div>

    <div class="form-group">
      <label for="descripcion" class="form-label">Descripción Detallada (*):</label>
      <textarea id="descripcion" name="descripcion" class="form-control" rows="5" required placeholder="Indique si hay ruidos, si sale agua, si el manómetro baja, si el fallo ocurre solo con radiadores o también con el agua caliente de los grifos..."><?= e($datos['descripcion']) ?></textarea>
      <div class="form-help">Cuantos más detalles técnicos aporte, mejor preparado acudirá nuestro técnico con los repuestos específicos.</div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:2rem;">
      <a href="panel.php" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-flame" style="padding:0.75rem 2rem; font-size:1rem;">Registrar Parte de Incidencia</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
