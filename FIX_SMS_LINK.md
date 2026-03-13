# FIX: API Key y Links de Confirmación

**Fecha:** 2026-02-16  
**Problema:** Error "API Key de Onurix no encontrada" y links de confirmación rotos (404).

## 🛠️ CAMBIOS REALIZADOS

### 1. ✅ Corrección de API Key (`ajax_send_link_sms.php`)

**Problema:** El script buscaba las credenciales solo en el usuario actual (que si es líder no las tiene) o en un admin global genérico que podía no coincidir con la organización.

**Solución:** Se modificó la lógica para:
1. Obtener el `organizacion_id` de la sesión del usuario actual.
2. Buscar las credenciales (`onurix_client`, `onurix_key`) en el usuario con rol `admin` perteneciente a la **mismo organización**.
3. Mantener un fallback a un admin global por compatibilidad.

```php
// QUERY IMPLEMENTADA
SELECT onurix_client, onurix_key FROM users WHERE role = 'admin' AND organizacion_id = ? LIMIT 1
```

### 2. ✅ Corrección de Enlaces Rotos (`ajax_registros_table.php` y `ajax_todos_registros_table.php`)

**Problema:** La generación del link usaba `str_replace` sobre `PHP_SELF` de una manera propensa a errores, generando URLs incorrectas (ej. `/ajax_registros_table.php/confirmar_voto.php` o similar) que resultaban en errores 404 "Página no encontrada". También usaba `http://` forzado.

**Solución:** Se implementó una detección robusta del protocolo y ruta base.

```php
// LÓGICA IMPLEMENTADA
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$scriptPath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$confirmLink = $protocol . $domainName . $scriptPath . '/confirmar_voto.php...';
```

Esto asegura que el link siempre apunte a `confirmar_voto.php` en el directorio correcto, independientemente de si se llama por AJAX o directamente.

## 🧪 PRUEBAS RECOMENDADAS

1. Iniciar sesión como Líder.
2. Ir a `registros.php`.
3. Hacer clic en el botón verde de SMS para un votante.
4. Verificar que dice "Enviado correctamente" (API Key OK).
5. Verificar en el celular que llega el SMS.
6. Hacer clic en el link del SMS y verificar que abre la página de confirmación (Link OK).
