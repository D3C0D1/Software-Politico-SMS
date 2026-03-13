# Corrección: Sistema de Confirmación de Voto

## 🐛 Problema Identificado

El sistema estaba mostrando "Sí" en la columna "Ya Votó" incluso cuando el votante NO había respondido en el formulario del link.

### Causa del Problema:
- La columna `ya_voto` tenía valor por defecto `0` (cero)
- El código mostraba "No" solo cuando `ya_voto != 1`
- No había forma de distinguir entre:
  - **No ha respondido** (pendiente)
  - **Respondió que NO votó**

## ✅ Solución Implementada

### 1. Tres Estados para `ya_voto`:
- **NULL** = No ha respondido (Pendiente)
- **0** = Respondió que NO votó
- **1** = Respondió que SÍ votó

### 2. Actualización de Base de Datos:
```sql
UPDATE registros 
SET ya_voto = NULL 
WHERE ya_voto IS NULL OR ya_voto = 0;
```

### 3. Lógica de Visualización Actualizada:

#### En `registros.php` y `todos_registros.php`:
```php
<?php if (isset($registro['ya_voto']) && $registro['ya_voto'] === 1): ?>
    <span class="badge badge-votante"><i class="fas fa-check-circle"></i> Sí</span>
<?php elseif (isset($registro['ya_voto']) && $registro['ya_voto'] === 0): ?>
    <span class="badge badge-lider"><i class="fas fa-times-circle"></i> No</span>
<?php else: ?>
    <span class="badge badge-admin"><i class="fas fa-clock"></i> Pendiente</span>
<?php endif; ?>
```

### 4. Badges Visuales:
- 🟢 **Verde "Sí"** = Ya votó (confirmado)
- 🔴 **Rojo "No"** = No ha votado (confirmado)
- 🟡 **Amarillo "Pendiente"** = No ha respondido aún

## 📊 Flujo Correcto Ahora:

### Escenario 1: Votante Recién Registrado
```
1. Se crea el votante
2. ya_voto = NULL
3. Vista muestra: "Pendiente" (badge amarillo)
```

### Escenario 2: Votante Confirma que SÍ Votó
```
1. Votante abre el link
2. Selecciona "SÍ, YA VOTÉ"
3. ya_voto = 1
4. Vista muestra: "Sí" (badge verde)
5. estado_voto = 'voto'
```

### Escenario 3: Votante Confirma que NO ha Votado
```
1. Votante abre el link
2. Selecciona "NO, AÚN NO"
3. ya_voto = 0
4. Vista muestra: "No" (badge rojo)
5. estado_voto = 'pendiente'
```

## 🔄 Cambios en el Formulario de Confirmación

El formulario `confirmar_voto.php` ahora maneja correctamente los tres estados:

```php
// Al enviar el formulario
$yaVoto = isset($_POST['ya_voto']) && $_POST['ya_voto'] == '1' ? 1 : 0;
$estado = $yaVoto ? 'voto' : 'pendiente';

UPDATE registros 
SET ya_voto = ?, estado_voto = ? 
WHERE id = ?
```

## 🎨 Cambio de Imagen de Perfil

También se actualizó la imagen de perfil de usuarios a `assets/img/liberal.png` según solicitado.

## ✅ Verificación

Para verificar que todo funciona correctamente:

1. **Votante sin responder**:
   - Debe mostrar badge amarillo "Pendiente"
   - `ya_voto` debe ser NULL

2. **Votante que confirmó SÍ**:
   - Debe mostrar badge verde "Sí"
   - `ya_voto` debe ser 1
   - `estado_voto` debe ser 'voto'

3. **Votante que confirmó NO**:
   - Debe mostrar badge rojo "No"
   - `ya_voto` debe ser 0
   - `estado_voto` debe ser 'pendiente'

## 📝 Archivos Modificados

1. `fix_ya_voto_null.php` - Script para actualizar registros existentes
2. `registros.php` - Actualizada lógica de visualización
3. `todos_registros.php` - Actualizada lógica de visualización
4. `confirmar_voto.php` - Manejo correcto de los tres estados

---

**Fecha:** 2026-02-09
**Sistema:** Partido Liberal - Gestión Electoral
