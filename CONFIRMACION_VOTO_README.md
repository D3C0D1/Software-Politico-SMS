# Sistema de Confirmación de Voto

## 📱 Vista Móvil para Votantes

Este sistema permite que los votantes confirmen si ya ejercieron su derecho al voto mediante un link único y personalizado.

## ✨ Características

### 1. **Link Único por Votante**
- Cada votante tiene un link único y seguro
- El link incluye un token de seguridad basado en su ID y cédula
- Formato: `confirmar_voto.php?id=123&token=abc123...`

### 2. **Vista Móvil Optimizada**
- Diseño responsive para dispositivos móviles
- Interfaz simple con dos botones grandes: "SÍ, YA VOTÉ" y "NO, AÚN NO"
- Colores del Partido Liberal (rojo y blanco)
- Logo del partido incluido

### 3. **Información del Votante**
- Muestra nombre completo
- Número de cédula
- Mesa de votación
- Estado actual (Ya votó / Pendiente)

### 4. **Integración con el Sistema**
- La columna "Ya Votó" aparece en:
  - `registros.php` (Vista de Votantes)
  - `todos_registros.php` (Base de Datos Completa)
- Botón para copiar el link único de cada votante
- Actualización automática del estado en la base de datos

## 🚀 Cómo Usar

### Paso 1: Copiar el Link
1. Ve a "Votantes" o "Base de Datos"
2. Busca el votante deseado
3. Haz clic en el botón azul con icono de link (🔗)
4. El link se copiará automáticamente al portapapeles

### Paso 2: Enviar el Link al Votante
Puedes enviar el link por:
- **WhatsApp**: Pega el link en un mensaje
- **SMS**: Usa el sistema de envío de SMS con el link incluido
- **Email**: Envía el link por correo electrónico

### Paso 3: El Votante Confirma
1. El votante abre el link en su celular
2. Ve su información personal
3. Selecciona "SÍ, YA VOTÉ" o "NO, AÚN NO"
4. Hace clic en "Confirmar Respuesta"
5. El sistema actualiza automáticamente el estado

## 📊 Visualización en el Dashboard

### Columna "Ya Votó"
- ✅ **Badge Verde "Sí"**: El votante confirmó que ya votó
- ❌ **Badge Rojo "No"**: El votante aún no ha votado

### Columna "Link Confirmación"
- Botón azul con icono de link
- Al hacer clic, copia el link único al portapapeles
- Mensaje de confirmación cuando se copia exitosamente

## 🔒 Seguridad

### Token de Validación
- Cada link incluye un token MD5 único
- El token se genera con: `md5(ID + Cédula + 'voto2026')`
- Si el token no coincide, el link es rechazado
- Previene acceso no autorizado

### Validaciones
- Verifica que el votante exista en la base de datos
- Valida el token antes de mostrar la información
- Protege contra manipulación de URLs

## 💾 Base de Datos

### Nueva Columna: `ya_voto`
- Tipo: INTEGER
- Valores: 0 (No) o 1 (Sí)
- Default: 0
- Se actualiza cuando el votante confirma

## 📱 Ejemplo de Link

```
http://tudominio.com/Politico/confirmar_voto.php?id=15&token=a1b2c3d4e5f6g7h8i9j0
```

## 🎨 Diseño de la Vista Móvil

- **Fondo**: Gradiente rojo del Partido Liberal
- **Tarjeta**: Blanca con bordes redondeados
- **Botones**: Grandes y táctiles
  - Verde para "SÍ, YA VOTÉ"
  - Rojo para "NO, AÚN NO"
- **Iconos**: FontAwesome para mejor UX
- **Responsive**: Se adapta a cualquier tamaño de pantalla

## ⚙️ Archivos Modificados

1. `migration_ya_voto.php` - Migración de base de datos
2. `confirmar_voto.php` - Vista móvil para votantes
3. `registros.php` - Añadida columna "Ya Votó" y botón de link
4. `todos_registros.php` - Añadida columna "Ya Votó" y botón de link

## 📝 Notas Importantes

- El link es permanente y puede usarse múltiples veces
- El votante puede cambiar su respuesta si es necesario
- El sistema muestra el estado actual cada vez que se abre el link
- Compatible con todos los navegadores móviles modernos

## 🆘 Solución de Problemas

### El link no funciona
- Verifica que el votante exista en la base de datos
- Asegúrate de que el token sea correcto
- Revisa que la URL esté completa

### No se actualiza el estado
- Verifica la conexión a la base de datos
- Revisa los permisos de escritura
- Comprueba que el formulario se esté enviando correctamente

---

**Desarrollado para el Partido Liberal Colombiano**
© 2026 - Sistema de Gestión Electoral
