-- =====================================================================
-- 01_crear_base_datos.sql
-- Proyecto: Gestor de Incidencias y Mantenimiento - Calderas CESI
-- Descripción: Creación de la base de datos con cotejamiento seguro UTF-8
-- =====================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS `cesi_incidencias`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `cesi_incidencias`;
