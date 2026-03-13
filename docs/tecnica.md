# 📄 Documentación Técnica - Software Político SMS 🗳️

Este documento proporciona una visión técnica detallada sobre la arquitectura, base de datos y funcionalidades principales del sistema.

## 🏗️ Arquitectura del Sistema

El software está construido siguiendo un patrón **Multi-Tier** (Multitenant) sobre una arquitectura monolítica robusta en PHP.

### Componentes Principales:
*   **Gestión Multi-Tenancy:** El sistema separa los datos por `organizacion_id`. Un Superadmin (Modo Dios) gestiona las organizaciones.
*   **Motor de Base de Datos:** MySQL con soporte para migraciones automáticas parciales.
*   **Capa de Presentación:** PHP dinámico con Bootstrap y Custom CSS para una experiencia premium.
*   **Integración de Terceros:** API de Onurix para el envío masivo y transaccional de SMS.

---

## 🗃️ Modelo de Datos (Base de Datos)

El sistema utiliza las siguientes tablas clave:

### 1. `organizaciones`
Almacena las entidades independientes que usan el software.
*   `id`: Identificador único.
*   `nombre_organizacion`: Nombre de la campaña o partido.
*   `status`: Estado (active/inactive).

### 2. `users`
Usuarios con diferentes roles de acceso.
*   `role`: `superadmin`, `admin`, `user`.
*   `organizacion_id`: Clave foránea que vincula al usuario con su organización.

### 3. `registros`
La tabla central que almacena tanto a **Líderes** como a **Votantes**.
*   `tipo`: `lider` o `votante`.
*   `organizacion_id`: Garantiza el aislamiento multitenant.
*   `user_id`: Vincula al votante con el líder que lo registró.
*   `estado_voto`: Seguimiento del proceso (pendiente, voto, revisado).
*   `sms_confirmacion`: Flag para control de comunicaciones enviadas.

### 4. `app_config`
Configuración dinámica por organización (Colores, Logos, Títulos).

---

## 📱 Flujo de Comunicación SMS

El sistema implementa un flujo de tres pasos para la gestión de votantes:

1.  **Inscripción:** SMS de bienvenida al ser registrado.
2.  **Citación:** Información sobre el lugar y mesa de votación.
3.  **Confirmación:** Envío de un link único para validar la intención de voto.

**Endpoint Principal:** `ajax_send_link_sms.php` -> Procesa la lógica de construcción de mensajes y llamada a la API de Onurix.

---

## 🛡️ Seguridad y Permisos

*   **Aislamiento de Datos:** Todas las consultas SQL filtran obligatoriamente por `$_SESSION['organizacion_id']`.
*   **Gestión de Sesiones:** Implementada en `config.php` con flags de seguridad.
*   **Auditoría:** Se registran logs de acciones críticas en la tabla `system_logs`.

---

## 🛠️ Mantenimiento

### Migraciones
El sistema incluye scripts de migración (`migration_*.php`) para añadir campos o tablas sin perder datos. Es recomendable ejecutar `force_migration.php` ante actualizaciones de esquema.

---
*Documentación generada para el equipo de desarrollo y soporte técnico.*
