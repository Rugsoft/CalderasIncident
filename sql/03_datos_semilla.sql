-- =====================================================================
-- 03_datos_semilla.sql
-- Proyecto: Gestor de Incidencias y Mantenimiento - Calderas CESI
-- Descripción: Datos iniciales de catálogo, usuarios de prueba e incidencias demo
-- =====================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE `cesi_incidencias`;

-- 1. USUARIOS DE PRUEBA (Contraseñas con bcrypt / PASSWORD_DEFAULT)
-- Administrador: admin@cesi.com     / Clave: Admin1234!
-- Técnico:       tecnico@cesi.com   / Clave: Tecnico1234!
-- Cliente:       cliente@cesi.com   / Clave: Cliente1234!
INSERT INTO `usuarios` (`id`, `nombre`, `email`, `telefono`, `password_hash`, `rol`, `activo`) VALUES
(1, 'Carlos Admin (Calderas CESI)', 'admin@cesi.com', '+34 900 123 456', '$2y$10$h5sgzrpQNZjW7clJgQRMxeldljOT5DgNVgwuR8Hb5/KRUOInG65Ua', 'administrador', 1),
(2, 'Laura Técnica (Mantenimiento)', 'tecnico@cesi.com', '+34 611 222 333', '$2y$10$hRZnp53hmIVe0/tlxn65I.bu2R2VbueumpeK04ZRxMbdAkjcmJIAG', 'tecnico', 1),
(3, 'David Cliente (Titular Caldera)', 'cliente@cesi.com', '+34 644 555 666', '$2y$10$8dPxK5VPWnitIlweaEmmUe/OXmew.SCD5gXmaaHqLW8ZEAyUeC2ue', 'solicitante', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 2. CATÁLOGO DE MODELOS DE CALDERAS CESI
INSERT INTO `modelos_caldera` (`id`, `codigo`, `nombre`, `combustible`, `potencia_kw`, `descripcion`, `activo`) VALUES
(1, 'CESI-ECO-24', 'CESI EcoCondens 24kW Mural', 'gas_natural', 24.00, 'Caldera mural de condensación de alta eficiencia con microacumulación para ACS.', 1),
(2, 'CESI-MAX-30', 'CESI TermoMax 30kW Mixta', 'gas_natural', 30.00, 'Caldera mixta de alto caudal para viviendas unifamiliares con calefacción y ACS.', 1),
(3, 'CESI-PRO-28', 'CESI ProGas 28kW Propano', 'propano', 28.00, 'Caldera optimizada para instalaciones GLP/Propano en zonas sin red de gas.', 1),
(4, 'CESI-BIO-18', 'CESI BioPellet 18kW Compact', 'pellet_biomasa', 18.00, 'Caldera ecológica de biomasa con encendido automático y tolva de 45kg.', 1),
(5, 'CESI-AERO-14', 'CESI AeroHybrid 14kW Bomba Calor', 'aerotermia', 14.00, 'Sistema híbrido caldera de apoyo con bomba de calor aire-agua inverter.', 1),
(6, 'CESI-OTRO', 'Otro Modelo / Caldera Antigua CESI', 'gas_natural', NULL, 'Para instalaciones anteriores o modelos no catalogados en la gama actual.', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 3. CATEGORÍAS DE AVERÍAS Y MANTENIMIENTO CESI
INSERT INTO `categorias_averia` (`id`, `nombre`, `descripcion`, `prioridad_sugerida`, `activa`) VALUES
(1, 'Pérdida de presión / Fuga de agua', 'El manómetro desciende por debajo de 1 bar o existe goteo visible en racores.', 'alta', 1),
(2, 'Bloqueo de quemador / Código de error', 'La caldera no arranca, chispea sin ignición o muestra código de fallo (E01, F28, etc.).', 'urgente', 1),
(3, 'Falta de Agua Caliente Sanitaria (ACS)', 'Los radiadores funcionan pero el agua de grifos y ducha sale fría o templada.', 'alta', 1),
(4, 'Ruidos anómalos o vibraciones de bomba', 'Silbidos en el intercambiador primario, traqueteos o bomba de circulación atascada.', 'media', 1),
(5, 'Revisión anual obligatoria (RITE) y puesta a punto', 'Mantenimiento preventivo, análisis de combustión y limpieza de quemador según normativa.', 'baja', 1),
(6, 'Avería de termostato o control de temperatura', 'El termostato ambiente no comunica con la placa receptora o no modula la llama.', 'media', 1),
(7, 'Otros / Consulta técnica de funcionamiento', 'Dudas de funcionamiento, purgado de circuito o consultas generales de cliente.', 'baja', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- 4. INCIDENCIAS DEMO INICIALES
INSERT INTO `incidencias` (
  `id`, `referencia`, `solicitante_id`, `modelo_caldera_id`, `categoria_id`,
  `numero_serie`, `direccion_instalacion`, `telefono_contacto`,
  `titulo`, `descripcion`, `prioridad`, `estado`, `tecnico_asignado_id`, `creada_en`
) VALUES
(
  1, 'CESI-2026-0001', 3, 1, 1,
  'CESI-24-984521-A', 'Calle Mayor 14, 2º B, Barcelona', '+34 644 555 666',
  'Goteo constante en llave de vaciado y presión a 0.5 bar',
  'Detectado goteo de agua bajo la caldera ayer por la noche. El manómetro marca 0.5 bar y la caldera emite un pitido intermitente al abrir el grifo caliente.',
  'alta', 'en_proceso', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)
),
(
  2, 'CESI-2026-0002', 3, 2, 5,
  'CESI-30-771420-C', 'Avenida Diagonal 450, 4º 1ª, Barcelona', '+34 644 555 666',
  'Solicitud de revisión anual preventiva RITE antes de invierno',
  'Corresponde realizar la revisión periódica anual reglamentaria y comprobación de tiro de humos para el certificado de mantenimiento.',
  'baja', 'asignada', 2, DATE_SUB(NOW(), INTERVAL 1 DAY)
),
(
  3, 'CESI-2026-0003', 3, 4, 2,
  'CESI-BIO-120034', 'Urbanización Bellavista 8, Sant Cugat', '+34 644 555 666',
  'Caldera BioPellet bloqueada con código de alarma AL-03',
  'El sinfín alimentador no carga pellet en el crisol y la caldera no completa el ciclo de ignición. Salta error AL-03 en el panel digital.',
  'urgente', 'nueva', NULL, NOW()
)
ON DUPLICATE KEY UPDATE `referencia` = VALUES(`referencia`);

-- 5. HISTORIAL DE ESTADOS DEMO (Auditoría inmutable)
INSERT INTO `historial_estados` (`incidencia_id`, `usuario_id`, `estado_anterior`, `estado_nuevo`, `motivo`, `cambiado_en`) VALUES
(1, 3, NULL, 'nueva', 'Registro inicial del cliente por avería de presión', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 1, 'nueva', 'asignada', 'Asignada a Laura Técnica para intervención a domicilio', DATE_SUB(NOW(), INTERVAL 36 HOUR)),
(1, 2, 'asignada', 'en_proceso', 'Técnico desplazado; sustitución de junta y purgado en curso', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(2, 3, NULL, 'nueva', 'Solicitud de revisión anual', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 1, 'nueva', 'asignada', 'Asignada en calendario de ruta preventiva', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(3, 3, NULL, 'nueva', 'Aviso urgente de bloqueo de encendido', NOW())
ON DUPLICATE KEY UPDATE `motivo` = VALUES(`motivo`);

-- 6. COMENTARIOS DEMO (Públicos e internos)
INSERT INTO `comentarios` (`incidencia_id`, `usuario_id`, `mensaje`, `tipo`, `creado_en`) VALUES
(1, 3, 'He colocado un recipiente debajo para recoger el agua, no moja el suelo.', 'publico', DATE_SUB(NOW(), INTERVAL 35 HOUR)),
(1, 2, 'Revisado número de serie en almacén: llevamos racor de latón 3/4" y válvula de seguridad de repuesto.', 'interno', DATE_SUB(NOW(), INTERVAL 24 HOUR)),
(1, 2, 'Buenos días David, llegaremos entre las 11:30 y las 12:00 a su domicilio.', 'publico', DATE_SUB(NOW(), INTERVAL 12 HOUR))
ON DUPLICATE KEY UPDATE `mensaje` = VALUES(`mensaje`);
