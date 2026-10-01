# MEMORIA TÉCNICA DE AMPLIACIONES FUTURAS Y BACKLOG EVOLUTIVO
## Proyecto: Gestor de Incidencias y Mantenimiento Técnico - Calderas CESI S.L.
### Versión: Documento de Entrega Fase 1 (MVP) &bull; Fecha: Octubre 2026

---

## 1. Introducción y Justificación del Alcance

En el marco del desarrollo del proyecto para la empresa **Calderas CESI S.L.**, se ha priorizado la entrega de un **Producto Mínimo Viable (MVP)** funcional, robusto y seguro que cubre de forma integral el núcleo operativo del negocio:
* Identificación y autenticación segura de usuarios (Control de Acceso Basado en Roles - RBAC).
* Catálogo oficial de calderas (gas, propano, biomasa, aerotermia) y tipología de averías.
* Apertura transaccional de partes de avería con control ACID y generación de referencias correlativas (`CESI-2026-XXXX`).
* Panel de control adaptativo para clientes, técnicos y administradores.
* Control Anti-IDOR para proteger la privacidad de los clientes y expedientes.
* Hilo de comunicación bidireccional (mensajes públicos y notas técnicas internas confidenciales).
* Auditoría inmutable de estados de reparación según exigencias de mantenimiento y trazabilidad.

El presente documento expone de forma detallada aquellas funcionalidades avanzadas analizadas en los manuales formativos de ingeniería web que, por razones de plazo de entrega y priorización del núcleo transaccional, quedan programadas para las siguientes fases de desarrollo (**Fase 2 y Fase 3**).

---

## 2. Inventario de Módulos Planificados para Fases Posteriores

### 2.1. Custodia Segura de Archivos Adjuntos y Fotografías de Averías (Manual 10)
* **Objetivo:** Permitir a los clientes y técnicos adjuntar fotografías de la placa de características de la caldera, manómetros de presión, códigos de error en pantalla o facturas de instalación.
* **Diseño Arquitectónico Previsto:**
  1. *Desacoplamiento Binario:* Los archivos físicos no se almacenan como tipos BLOB en la base de datos MySQL, sino en un directorio privado fuera del `DocumentRoot` (ej. `/shared/uploads/calderas/`) para no sobrecargar el buffer pool de InnoDB ni los respaldos `mysqldump`.
  2. *Mitigación de Ejecución Remota de Código (RCE):* Se renombra cada archivo entrante mediante un hash alfanumérico único (`bin2hex(random_bytes(16))`), despojándolo de su extensión original.
  3. *Verificación MIME con `fileinfo`:* Análisis de la firma mágica de bytes en servidor (permitiendo únicamente `image/jpeg`, `image/png` y `application/pdf`), ignorando la cabecera `Content-Type` enviada por el navegador.
  4. *Descarga Controlada:* Entrega mediante cabeceras `Content-Disposition: attachment` con saneamiento de nombres mediante `rawurlencode()` y cabecera de seguridad `X-Content-Type-Options: nosniff`.

### 2.2. Parte de Trabajo Digital y Firma Biométrica del Cliente en Tablet
* **Objetivo:** Permitir que el técnico de Calderas CESI, al finalizar la reparación en el domicilio del cliente, genere un parte de intervención técnico y recoja la firma manuscrita digitalizada en pantalla.
* **Componentes Previstos:**
  * Componente HTML5 `<canvas>` con captura de trazos vectoriales y marca de tiempo UTC.
  * Inclusión de materiales sustituidos (termopares, vasos de expansión, bombas de recirculación) y mediciones de combustión reglamentarias (CO, CO2, temperatura de humos, rendimiento %).
  * Generación automática de resumen PDF firmado mediante librería `Dompdf` / `TCPDF` y envío inmediato al correo del cliente.

### 2.3. Sistema de Notificaciones en Tiempo Real (SMS / WhatsApp / Email)
* **Objetivo:** Reducir tiempos de respuesta en averías urgentes (ej. fugas de gas o paradas totales de calefacción en periodos invernales).
* **Componentes Previstos:**
  * Integración con pasarela SMS/WhatsApp (ej. Twilio API) para alertar al técnico de guardia cuando se registra una incidencia con prioridad `URGENTE`.
  * Avisos automáticos por correo electrónico (`PHPMailer`) al cliente cuando el estado de su caldera pasa a `asignada` o `resuelta`.

### 2.4. Control de Inventario y Repuestos de Calderas
* **Objetivo:** Conectar el gestor de averías con el stock del almacén central de repuestos de Calderas CESI.
* **Componentes Previstos:**
  * Tabla `piezas_repuesto` vinculada a cada modelo de caldera (códigos de racorería, intercambiadores de calor, válvulas de seguridad de 3 bares).
  * Descuento automático de existencias tras el cierre conforme de cada parte de trabajo, alertando si el stock de una pieza crítica cae por debajo del umbral mínimo de seguridad.

### 2.5. Gestión de Revisiones Periódicas Obligatorias (RITE) y Alertas Preventivas
* **Objetivo:** Automatizar la captación y fidelización de contratos de mantenimiento anual.
* **Componentes Previstos:**
  * Cron job / Tarea programada que evalúa los números de serie de calderas cuya última revisión supere los 11 meses.
  * Emisión automática de recordatorios de cita previa para la inspección reglamentaria de eficiencia energética y seguridad de combustión.

---

## 3. Matriz de Priorización y Cronograma Estimado

| Módulo / Funcionalidad | Prioridad de Negocio | Esfuerzo Estimado | Fase Programada |
| :--- | :--- | :--- | :--- |
| **Subida segura de fotos/adjuntos** | Alta | 12 horas | Fase 2.1 (Próxima release) |
| **Notificaciones automáticas Email/SMS** | Media-Alta | 8 horas | Fase 2.2 |
| **Parte de trabajo digital con firma** | Media | 16 horas | Fase 3.1 |
| **Módulo de stock y repuestos** | Media | 20 horas | Fase 3.2 |
| **Automatización de inspecciones RITE** | Estratégica | 14 horas | Fase 3.3 |

---

## 4. Conclusión Técnica

El MVP entregado en la presente versión cumple estrictamente los principios de seguridad defensiva, arquitectura de capas, desacoplamiento PDO y robustez relacional exigidos en la implantación web. La arquitectura modular adoptada garantiza que todas las ampliaciones detalladas en este documento podrán integrarse de forma limpia y transparente sin requerir reescrituras del núcleo del sistema.
