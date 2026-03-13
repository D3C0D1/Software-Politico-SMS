# RESUMEN DE CAMBIOS - FILTROS POR ORGANIZACIÓN Y PERMISOS

**Fecha:** 2026-02-16  
**Objetivo:** Implementar filtros por organización y restricciones de acceso

---

## 📋 CAMBIOS REALIZADOS

### 1. ✅ Sidebar - Restricción de "Gestión de Usuarios"

**Archivo:** `includes/sidebar.php`

**Cambio:** La opción "Gestión de Usuarios" ahora solo es visible para usuarios con rol `admin`.

**Antes:**
```php
<?php if ($_SESSION['role'] !== 'lider'): ?>
    <!-- Gestión Usuarios visible para cualquier no-líder -->
<?php endif; ?>
```

**Después:**
```php
<?php if ($_SESSION['role'] !== 'lider'): ?>
    <!-- Base de Datos -->
<?php endif; ?>

<?php if ($_SESSION['role'] === 'admin'): ?>
    <!-- Gestión Usuarios solo para admin -->
<?php endif; ?>
```

---

### 2. ✅ Gestión de Usuarios - Filtro por Organización

**Archivo:** `usuarios.php`

**Cambios realizados:**

#### a) Filtrar usuarios mostrados (líneas 92-95)
```php
// ANTES
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");

// DESPUÉS  
$organizacion_id = $_SESSION['organizacion_id'] ?? 1;
$stmt = $pdo->prepare("SELECT * FROM users WHERE organizacion_id = ? ORDER BY id DESC");
$stmt->execute([$organizacion_id]);
```

#### b) Asignar organización al crear usuarios (líneas 43-46)
```php
// ANTES
$stmt = $pdo->prepare("INSERT INTO users (username, password, role, name) VALUES (?, ?, ?, ?)");
$stmt->execute([$username, $hash, $role, $name]);

// DESPUÉS
$organizacion_id = $_SESSION['organizacion_id'] ?? 1;
$stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, organizacion_id) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$username, $hash, $role, $name, $organizacion_id]);
```

---

### 3. ✅ Base de Datos - Filtro por Organización

**Archivo:** `todos_registros.php`

**Estado:** ✅ Ya estaba filtrado correctamente por organización (líneas 75-82)

---

### 4. ✅ Tabla AJAX de Registros - Filtro por Organización

**Archivo:** `ajax_todos_registros_table.php`

**Cambio:**
```php
// ANTES
$stmt = $pdo->prepare("
    SELECT r.*, u.username as leader_username, u.name as leader_name 
    FROM registros r 
    LEFT JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC
");
$stmt->execute();

// DESPUÉS
$organizacion_id = $_SESSION['organizacion_id'] ?? 1;
$stmt = $pdo->prepare("
    SELECT r.*, u.username as leader_username, u.name as leader_name 
    FROM registros r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE r.organizacion_id = :org_id
    ORDER BY r.created_at DESC
");
$stmt->execute(['org_id' => $organizacion_id]);
```

---

### 5. ✅ Estadísticas AJAX - Filtro por Organización

**Archivo:** `ajax_todos_stats.php`

**Cambio:**
```php
// ANTES
$stmt = $pdo->query("SELECT ... FROM registros");

// DESPUÉS
$organizacion_id = $_SESSION['organizacion_id'] ?? 1;
$stmt = $pdo->prepare("SELECT ... FROM registros WHERE organizacion_id = ?");
$stmt->execute([$organizacion_id]);
```

---

## 🎯 RESULTADO FINAL

### Restricciones de Acceso:
- ✅ **Líderes:** NO pueden ver "Gestión de Usuarios"
- ✅ **Operadores:** NO pueden ver "Gestión de Usuarios"  
- ✅ **Administradores:** SÍ pueden ver "Gestión de Usuarios"

### Filtros por Organización:
- ✅ **Gestión de Usuarios:** Muestra solo usuarios de la misma organización
- ✅ **Base de Datos:** Muestra solo registros de la misma organización
- ✅ **Estadísticas:** Calculadas solo con datos de la misma organización
- ✅ **Crear Usuario:** Se asigna automáticamente a la organización del admin

### Aislamiento de Datos:
Cada organización solo puede ver y gestionar:
- Sus propios usuarios
- Sus propios líderes
- Sus propios votantes
- Sus propias estadísticas

---

## 📝 NOTAS TÉCNICAS

- Se utiliza `$_SESSION['organizacion_id']` para identificar la organización del usuario actual
- Se usa valor por defecto `1` si no está definido: `$_SESSION['organizacion_id'] ?? 1`
- Todos los filtros se implementan a nivel de base de datos (consultas SQL)
- Los cambios son retrocompatibles y no afectan la funcionalidad existente

---

**Generado:** 2026-02-16 09:45:00
