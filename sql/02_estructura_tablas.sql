-- =====================================================================
-- 02_estructura_tablas.sql
-- Proyecto: Gestor de Incidencias y Mantenimiento - Calderas CESI
-- Descripción: Estructura de tablas relacionales con motor InnoDB y FKs
-- =====================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE `cesi_incidencias`;

-- Desactivar temporalmente revisión de claves foráneas para creación limpia
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `historial_estados`;
DROP TABLE IF EXISTS `comentarios`;
DROP TABLE IF EXISTS `incidencias`;
DROP TABLE IF EXISTS `categorias_averia`;
DROP TABLE IF EXISTS `modelos_caldera`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABLA: usuarios
-- Gestiona el control de acceso basado en roles (RBAC: solicitante, tecnico, administrador)
CREATE TABLE `usuarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `telefono` VARCHAR(25) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('solicitante', 'tecnico', 'administrador') NOT NULL DEFAULT 'solicitante',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_usuario_rol` (`rol`, `activo`),
  INDEX `idx_usuario_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLA: modelos_caldera
-- Catálogo de productos fabricados/mantenidos por Calderas CESI
CREATE TABLE `modelos_caldera` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(30) NOT NULL UNIQUE,
  `nombre` VARCHAR(120) NOT NULL,
  `combustible` ENUM('gas_natural', 'propano', 'pellet_biomasa', 'aerotermia', 'gasoil') NOT NULL DEFAULT 'gas_natural',
  `potencia_kw` DECIMAL(5,2) NULL,
  `descripcion` TEXT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  INDEX `idx_modelo_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLA: categorias_averia
-- Tipología de problemas específicos de calderas y mantenimiento
CREATE TABLE `categorias_averia` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `descripcion` VARCHAR(255) NULL,
  `prioridad_sugerida` ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  INDEX `idx_categoria_activa` (`activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABLA: incidencias
-- Entidad troncal de gestión de incidencias y partes de servicio técnico
CREATE TABLE `incidencias` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `referencia` VARCHAR(25) NOT NULL UNIQUE,
  `solicitante_id` INT UNSIGNED NOT NULL,
  `modelo_caldera_id` INT UNSIGNED NULL,
  `categoria_id` INT UNSIGNED NOT NULL,
  `numero_serie` VARCHAR(60) NULL,
  `direccion_instalacion` VARCHAR(255) NOT NULL,
  `telefono_contacto` VARCHAR(25) NOT NULL,
  `titulo` VARCHAR(150) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `prioridad` ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
  `estado` ENUM('nueva', 'asignada', 'en_proceso', 'resuelta', 'cerrada') NOT NULL DEFAULT 'nueva',
  `tecnico_asignado_id` INT UNSIGNED NULL,
  `creada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_incidencia_solicitante`
    FOREIGN KEY (`solicitante_id`) REFERENCES `usuarios` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,

  CONSTRAINT `fk_incidencia_modelo`
    FOREIGN KEY (`modelo_caldera_id`) REFERENCES `modelos_caldera` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,

  CONSTRAINT `fk_incidencia_categoria`
    FOREIGN KEY (`categoria_id`) REFERENCES `categorias_averia` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,

  CONSTRAINT `fk_incidencia_tecnico`
    FOREIGN KEY (`tecnico_asignado_id`) REFERENCES `usuarios` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,

  INDEX `idx_incidencia_estado` (`estado`),
  INDEX `idx_incidencia_solicitante` (`solicitante_id`),
  INDEX `idx_incidencia_tecnico` (`tecnico_asignado_id`),
  INDEX `idx_incidencia_prioridad` (`prioridad`),
  INDEX `idx_incidencia_creada` (`creada_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLA: comentarios
-- Mensajes del hilo de incidencia: públicos (cliente/técnico) e internos (solo técnicos/admin)
CREATE TABLE `comentarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incidencia_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `mensaje` TEXT NOT NULL,
  `tipo` ENUM('publico', 'interno') NOT NULL DEFAULT 'publico',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_comentario_incidencia`
    FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,

  CONSTRAINT `fk_comentario_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,

  INDEX `idx_comentario_incidencia` (`incidencia_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLA: historial_estados
-- Pista de auditoría inmutable sobre transiciones de estado de cada caldera
CREATE TABLE `historial_estados` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incidencia_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `estado_anterior` ENUM('nueva', 'asignada', 'en_proceso', 'resuelta', 'cerrada') NULL,
  `estado_nuevo` ENUM('nueva', 'asignada', 'en_proceso', 'resuelta', 'cerrada') NOT NULL,
  `motivo` VARCHAR(255) NULL,
  `cambiado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_historial_incidencia`
    FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,

  CONSTRAINT `fk_historial_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,

  INDEX `idx_historial_incidencia` (`incidencia_id`, `cambiado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
