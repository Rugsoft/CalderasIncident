# DOSSIER TÉCNICO DE INSTALACIÓN Y DESPLIEGUE
## Sistema de Gestión de Incidencias y Mantenimiento Técnico
### Empresa: Calderas CESI S.L. &bull; Versión: 1.0 (MVP)

---

## 1. Resumen Ejecutivo y Alcance del Sistema

El presente sistema web ha sido diseñado específicamente para la empresa **Calderas CESI S.L.**, especializada en fabricación, instalación y mantenimiento preventivo y correctivo de calderas de gas, biomasa, gasoil y sistemas híbridos de aerotermia.

El software permite digitalizar el ciclo completo de atención a averías y solicitudes reglamentarias (RITE), ofreciendo:
* **Área Privada de Clientes Titulares:** Registro de usuarios, apertura de partes de avería con especificación del modelo de caldera, número de serie, dirección de la vivienda y seguimiento del estado en tiempo real.
* **Consola de Trabajo del Técnico de Mantenimiento:** Recepción de partes de trabajo, consulta de datos de la instalación y teléfono de contacto, publicación de notas técnicas y avance de estados operativos de reparación.
* **Panel de Control de Administración:** Supervisión de todas las incidencias de la empresa, métricas de carga de trabajo y asignación rápida de avisos a los técnicos disponibles.
* **Registro Inmutable de Auditoría:** Historial cronológico obligatorio que audita cada transición de estado con autor, fecha y justificación técnica.

---

## 2. Requisitos del Sistema y Servidor

Para garantizar la estabilidad y el rendimiento del aplicativo, el entorno de ejecución debe cumplir las siguientes especificaciones:

| Componente | Requisito Mínimo Recomendado |
| :--- | :--- |
| **Servidor Web** | Apache 2.4 o superior (con módulos `mod_rewrite` y `mod_authz_core` habilitados). |
| **Intérprete PHP** | PHP **8.1**, 8.2 o superior (con extensiones activas: `pdo_mysql`, `mbstring`, `openssl`, `session`). |
| **Base de Datos** | MySQL 8.0+ o MariaDB 10.4+ con soporte para el motor de almacenamiento **InnoDB**. |
| **Cotejamiento SQL** | Juego de caracteres `utf8mb4` y colación `utf8mb4_unicode_ci` (soporte completo de caracteres y tildes). |
| **Entorno Local** | XAMPP 8.1+ / WampServer / Docker LAMP Stack. |

---

## 3. Estructura de Directorios y Segregación de Perímetros

Siguiendo las directrices profesionales del módulo formativo **MF0493_3 (Implantación Web)**, el proyecto implementa una segregación estricta entre los puntos de entrada web y los componentes privados de lógica y datos:

```text
IncidenciasCalderas/
├── index.php                 # Portal de bienvenida público y buscador por referencia
├── login.php                 # Autenticación defensiva de usuarios
├── logout.php                # Cierre de sesión seguro con token CSRF
├── registro.php              # Alta de clientes titulares (rol fijado en servidor)
├── panel.php                 # Panel adaptativo según rol (Solicitante / Técnico / Admin)
├── nueva_incidencia.php      # Formulario transaccional de reporte de averías
├── ver_incidencia.php        # Ficha técnica, control Anti-IDOR, notas y estados
├── assets/
│   └── css/
│       └── cesi.css          # Hoja de estilos corporativa Calderas CESI (Responsive)
├── config/                   # [PRIVADO - Bloqueado por .htaccess]
│   ├── .htaccess             # Require all denied
│   └── database.php          # Credenciales de conexión a MySQL
├── src/                      # [PRIVADO - Bloqueado por .htaccess]
│   ├── .htaccess             # Require all denied
│   ├── db.php                # Factoría PDO con manejo defensivo de errores (HTTP 503)
│   ├── auth.php              # Ciclo de vida de sesiones seguras y control RBAC
│   └── helpers.php           # Sanitización XSS, tokens CSRF y utilidades
├── views/                    # [PRIVADO - Bloqueado por .htaccess]
│   ├── .htaccess             # Require all denied
│   ├── header.php            # Cabecera HTML común y navegación dinámica
│   ├── footer.php            # Pie de página corporativo
│   ├── error_403.php         # Pantalla amigable de Acceso Denegado
│   └── error_503.php         # Pantalla amigable de Mantenimiento de Servidor
├── sql/                      # [PRIVADO - Bloqueado por .htaccess]
│   ├── .htaccess             # Require all denied
│   ├── 01_crear_base_datos.sql      # Creación de base de datos utf8mb4
│   ├── 02_estructura_tablas.sql      # Tablas InnoDB, índices y claves foráneas
│   └── 03_datos_semilla.sql          # Catálogo CESI, usuarios demo e incidencias
└── docs/                     # [PRIVADO - Bloqueado por .htaccess]
    ├── DOSSIER_INSTALACION.md        # Este manual técnico de implantación
    └── AMPLIACIONES_FUTURAS_BACKLOG.docx # Memoria técnica de mejoras para Word
```

---

## 4. Guía de Instalación Paso a Paso

### Paso 1: Ubicación del Código Fuente
Copie la carpeta `IncidenciasCalderas` dentro del directorio de publicación del servidor web:
* En Windows con XAMPP: `C:\xampp\htdocs\curso-soc-php\IncidenciasCalderas\`
* En servidores Linux: `/var/www/html/IncidenciasCalderas/`

### Paso 2: Creación e Inicialización de la Base de Datos
La base de datos debe crearse ejecutando los tres scripts SQL secuenciales incluidos en la carpeta `sql/`.

#### Opción A: Desde Terminal / Consola (Recomendado)
Abra PowerShell o la consola del sistema y ejecute:
```powershell
# 1. Crear la base de datos con cotejamiento utf8mb4
c:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < sql/01_crear_base_datos.sql

# 2. Crear las tablas relacionales InnoDB y claves foráneas
c:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 cesi_incidencias < sql/02_estructura_tablas.sql

# 3. Cargar el catálogo de calderas, tipos de avería y usuarios de prueba
c:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 cesi_incidencias < sql/03_datos_semilla.sql
```

#### Opción B: Mediante phpMyAdmin
1. Acceda a `http://localhost/phpmyadmin/`.
2. Vaya a la pestaña **Importar**.
3. Seleccione y ejecute por orden:
   * Primero: `sql/01_crear_base_datos.sql`.
   * Segundo: Seleccione la base de datos `cesi_incidencias` en el panel izquierdo e importe `sql/02_estructura_tablas.sql`.
   * Tercero: Importe `sql/03_datos_semilla.sql`.

### Paso 3: Configuración de Parámetros de Conexión
Revise y edite el archivo `config/database.php` para indicar las credenciales correspondientes a su servidor de base de datos:

```php
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'cesi_incidencias',
    'user'     => 'root',        // Usuario de MySQL
    'password' => '',            // Contraseña de MySQL
    'charset'  => 'utf8mb4'
];
```

---

## 5. Cuentas de Acceso de Demostración (Evaluación)

El script `03_datos_semilla.sql` incluye tres usuarios de prueba representativos de cada perfil de la empresa con contraseñas encriptadas mediante `password_hash()` (algoritmo bcrypt):

| Perfil / Rol | Correo Electrónico | Contraseña | Finalidad y Funcionalidades |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@cesi.com` | `Admin1234!` | Supervisión global, asignación rápida de avisos a técnicos y consulta de métricas. |
| **Técnico Calderas** | `tecnico@cesi.com` | `Tecnico1234!` | Consulta de su cola de trabajo, avance de estados de reparación y notas técnicas internas. |
| **Cliente Titular** | `cliente@cesi.com` | `Cliente1234!` | Reporte de averías de su caldera, consulta de estado y comentarios en el expediente. |

---

## 6. Pruebas de Verificación y Humo (*Smoke Tests*)

Una vez completada la instalación, verifique el correcto funcionamiento del sistema:

1. **Carga del Portal Público:** Acceda en su navegador a `http://localhost/curso-soc-php/IncidenciasCalderas/`. Compruebe que visualiza el catálogo de calderas CESI y el buscador rápido por referencia.
2. **Prueba de Búsqueda por Referencia:** Introduzca en el buscador `CESI-2026-0001`. El sistema debe devolver la incidencia demo existente con sus insignias de estado y prioridad.
3. **Inicio de Sesión y Control RBAC:**
   - Inicie sesión con `cliente@cesi.com` y compruebe que únicamente se visualizan sus calderas.
   - Inicie sesión con `tecnico@cesi.com` y verifique la pestaña de "Mis Asignadas" y la bolsa de "Nuevas".
   - Inicie sesión con `admin@cesi.com` y compruebe que puede reasignar cualquier técnico en el desplegable.
4. **Prueba de Seguridad de Archivos Privados:**
   - Intente acceder desde el navegador a `http://localhost/curso-soc-php/IncidenciasCalderas/config/database.php`.
   - El servidor Apache debe devolver un código de error **403 Forbidden**, confirmando que las credenciales están blindadas frente a solicitudes externas.
