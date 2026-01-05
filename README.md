# Heatmap Leben

Plugin de WordPress para capturar y visualizar mapas de calor de múltiples páginas. Permite ver el mapa por URL con filtros de fecha, exportar CSV de eventos y descargar la imagen del heatmap.

## Características

- ✅ Captura de clicks y movimientos (muestreados) en frontend.
- ✅ Agrupación por URL de página.
- ✅ **Captura automática de altura real de la página** (scrollHeight).
- ✅ **Slider de escala interactivo** para zoom del heatmap (10%-200%).
- ✅ **Normalización automática de URLs** para evitar duplicados con query strings.
- ✅ Panel de administración con:
  - Selector de página y rango de fechas.
  - **Filtro por tipo de dispositivo** (mobile, desktop, todos).
  - Filtro por tipo de evento (clicks, movimientos, todos).
  - **Control de escala visual del heatmap**.
  - Visualización del mapa sobre la página en canvas interactivo.
  - Exportación de imagen (PNG) del heatmap (capa de calor).
  - Exportación CSV de eventos crudos.
  - Visualización de estadísticas (total eventos, clicks, movimientos, sesiones únicas).
  - **Gestión de screenshots por dispositivo** (móvil y desktop separados).
  - **Normalización de URLs en base de datos**.
- ✅ Datos sin PII: se usa un ID de sesión aleatorio almacenado localmente.
- ✅ Renderizado con canvas nativo (sin dependencias externas).

## Novedades

### v1.3.2
- 🔧 **Fix Device Type:** Solución completa para el error "Unknown column 'device_type'".
- 🔄 **Migración Automática Mejorada:** El plugin ahora detecta y crea la columna device_type automáticamente al cargar.
- 🛠️ **Script de Fix Rápido:** Incluye archivo fix-device-type.php para solución inmediata.
- 📝 **Documentación Mejorada:** Guía completa de troubleshooting en el README.

### v1.3.1
- ⚡ **Indicador de Carga:** El botón "Actualizar" ahora muestra un spinner animado mientras carga datos.
- 🖥️ **Filtro Desktop por Defecto:** El filtro de dispositivo ahora inicia en "Desktop" para mejor usabilidad.
- 📅 **Fecha Automática:** El campo "hasta" se establece automáticamente al día de hoy.
- 🔄 **Migración Automática:** Los eventos antiguos sin device_type se migran automáticamente a "desktop".

### v1.3.0
- 📱 **Separación Mobile/Desktop:** Ahora el plugin separa automáticamente los eventos entre dispositivos móviles (≤768px) y desktop.
- 🎚️ **Filtro de Dispositivo:** Nuevo selector en la interfaz para visualizar el heatmap de mobile, desktop o ambos.
- 📊 **Estadísticas por Dispositivo:** Las estadísticas se filtran según el dispositivo seleccionado.
- 📸 **Screenshots por Dispositivo:** Gestión separada de screenshots para mobile y desktop.

### v1.2.1
- 🖼️ **Fix Imágenes Grandes:** Mejora en la carga de screenshots muy grandes (3000x10000+ px) usando dimensiones del servidor como fallback.

### v1.2.0
- 🎚️ **Slider de Escala:** Control deslizante para ajustar el zoom del heatmap entre 10% y 200% (por defecto 50%).
- 📐 **Tamaño Real de Página:** El heatmap ahora utiliza las dimensiones reales de la página capturada desde la base de datos.
- 🔗 **Normalización de URLs:** Nueva funcionalidad para eliminar parámetros de query string y agrupar correctamente eventos duplicados.
- ✨ **Interfaz Mejorada:** Mejor organización de los controles en la barra de herramientas.

## Instalación

1. Copia la carpeta `heatmap-leben` en `wp-content/plugins/`.
2. Activa el plugin desde el panel de plugins de WordPress.
3. Ve a **"Heatmap"** en el menú de administrador.

> **Requisitos:** WordPress 5.8+, PHP 7.4+

## Uso

### Recolección de datos

El plugin captura automáticamente:
- Eventos de **click** (densidad: 5)
- Eventos de **movimiento de ratón** (muestreados cada 120ms, densidad: 1)
- Coordenadas relativas al viewport
- Scroll horizontal y vertical
- **Altura total de la página** (scrollHeight)
- **Tipo de dispositivo** (mobile si viewport ≤ 768px, sino desktop)
- Identificador de sesión única

### Panel de administración

1. Navega a **Heatmap → Mapa de Calor**.
2. Selecciona una **página** en el desplegable.
3. (Opcional) Filtra por **rango de fechas**.
4. (Opcional) Filtra por **tipo de evento** (clicks, movimientos, todos).
5. (Opcional) Filtra por **dispositivo** (todos, desktop, mobile).
6. Haz clic en **"Actualizar"** para renderizar el mapa.

### Controles del canvas

- **Hacer zoom:** Rueda del ratón o pinch en touch devices.
- **Desplazar:** Click + arrastrar o dos dedos en touch.
- **Doble click:** Reset a zoom 1x.
- **Información:** Pasa el ratón sobre el mapa para ver coordenadas.

### Exportación

- **Exportar imagen:** Descarga el PNG con la capa de calor (sin fondo).
- **Exportar CSV:** Descarga todos los eventos con detalles (x, y, scroll, viewport, timestamp).

### Ajustes

Accede a **Heatmap → Ajustes** para:
- ✅ Habilitar/deshabilitar filtro de bots.
- ✅ Definir patrones personalizados de bots.
- ✅ Incluir/excluir usuarios autenticados en el tracking.
- ✅ Definir usuarios recurrentes por fecha o lookback (N días).
- ✅ **Gestionar screenshots por dispositivo** (separados para mobile y desktop).
- ✅ Eliminar datos en rango de fechas.
- ✅ Normalizar URLs existentes.
- ✅ Migrar device_type en eventos antiguos.

## Notas técnicas

### Tabla de base de datos

```
${wp_prefix}heatmap_leben_events
```

**Campos:**
- `id` - ID único (PK)
- `page_url` - URL de la página donde se capturó el evento
- `page_id` - ID del post/página (si es singular)
- `event_type` - Tipo de evento: 'click', 'move'
- `device_type` - Tipo de dispositivo: 'mobile', 'desktop' (nuevo en v1.3.0)
- `x` - Posición X dentro del viewport
- `y` - Posición Y dentro del viewport
- `viewport_w` - Ancho del viewport
- `viewport_h` - Alto del viewport
- `scroll_x` - Scroll horizontal en píxeles
- `scroll_y` - Scroll vertical en píxeles
- `page_height` - **Altura total de la página** (nuevo)
- `density` - Factor de densidad (1-5)
- `session_id` - ID de sesión única (sin PII)
- `created_at` - Timestamp de creación

### Algoritmo de renderizado

1. **Captura:** El JS recolecta eventos con `{x, y, scroll_x, scroll_y, viewport_w, viewport_h, page_height}`.

2. **Normalización:** Se calcula la escala promedio basada en `page_height` real en lugar del `viewport_h`:
   ```
   scaleY = canvas_height / page_height_promedio
   ```

3. **Posicionamiento:** Se escalan las coordenadas del documento:
   ```
   canvas_posX = (x) * scaleX
   canvas_posY = (y + scroll_y) * scaleY
   ```

4. **Influencia:** Cada evento genera una gaussiana de influencia usando un radio de 35px y decay exponencial.

5. **Colorización:** Se normaliza la densidad máxima y se aplica un gradiente azul → verde → rojo → naranja.

### API AJAX

#### `wp_ajax_hm_leben_event` (frontend)

**Endpoint:** `/wp-admin/admin-ajax.php?action=hm_leben_event`

**Parámetros:**
```javascript
{
  action: 'hm_leben_event',
  nonce: 'wp_nonce_value',
  batch: JSON.stringify([
    {
      t: 'click|move',
      x: number,
      y: number,
      vw: number,
      vh: number,
      sx: number,
      sy: number,
      ph: number,        // page_height
      dt: 'mobile|desktop', // device_type
      d: number,         // density
      page: string,      // URL
      pageId: number,
      s: string          // session_id
    }
  ])
}
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "inserted": 42
  }
}
```

## Desinstalación

Al desinstalar el plugin desde WordPress:
1. ✅ Se elimina la tabla `${wp_prefix}heatmap_leben_events`.
2. ✅ Se elimina la opción de ajustes.
3. ✅ Se eliminan los attachments (screenshots) asociados.

## Limitaciones conocidas

- **Exportar imagen** descarga solo la capa de calor (sin fondo de página). Para superponerla sobre una screenshot, usa un editor de imágenes.
- Para **páginas con alturas muy grandes** (>5000px), el renderizado puede tardar varios segundos.
- El **scroll horizontal** se captura pero tiene menos impacto visual en el heatmap (foco en scroll vertical).
- Si el admin y el sitio usan **dominios/protocolos distintos**, la vista en iframe puede no funcionar (CORS/políticas de mismo origen).

## Troubleshooting

### Error "Unknown column 'device_type'"

Si ves errores como `WordPress database error Unknown column 'device_type' in 'field list'`, significa que tu base de datos necesita ser actualizada. Usa **una** de estas soluciones:

#### Opción 1: Botón de Migración (Recomendado)

1. Ve a **WordPress Admin → Heatmap → Ajustes**
2. Busca la sección **"Migrar Device Type"**
3. Haz clic en **"Migrar Device Type"**
4. Espera el mensaje de éxito

#### Opción 2: Script de Fix Rápido

1. Accede a: `https://tu-dominio.com/wp-content/plugins/heatmap-leben/fix-device-type.php`
2. Espera el mensaje de éxito
3. **IMPORTANTE:** Elimina el archivo `fix-device-type.php` después de ejecutarlo

#### Opción 3: Query SQL Manual

Ejecuta en phpMyAdmin o tu herramienta de base de datos:

```sql
ALTER TABLE `wp_heatmap_leben_events` 
ADD COLUMN `device_type` VARCHAR(20) NOT NULL DEFAULT 'desktop' AFTER `event_type`;

ALTER TABLE `wp_heatmap_leben_events` 
ADD INDEX `idx_device_type` (`device_type`);

UPDATE `wp_heatmap_leben_events` 
SET device_type = 'desktop' 
WHERE device_type IS NULL OR device_type = '';
```

> **Nota:** Reemplaza `wp_` con tu prefijo real de base de datos si es diferente.

### No se capturan eventos

1. Abre la consola del navegador (F12) y verifica que `HeatmapLeben` esté definido.
2. Navega por la página e intenta hacer clicks/mover el ratón.
3. En la consola, deberías ver logs: `📤 Enviando batch: X eventos`.
4. Si ves errores `403 Forbidden`, verifica que el nonce coincida con `heatmap_leben_admin`.

### El mapa no se renderiza

1. Asegúrate de que existan datos para la página seleccionada.
2. Comprueba la consola del navegador (Admin) para errores de canvas.
3. Si el problema persiste, ejecuta en WP-CLI:
   ```bash
   wp db query "SELECT COUNT(*) FROM wp_heatmap_leben_events;"
   ```

### Los eventos no distribuyen bien verticalmente

- Anterior: Se usaba solo `viewport_h` para escalar (error).
- Actual: Se usa `page_height` (altura real del documento).
- Solución: Asegúrate de que el campo `page_height` se rellena correctamente (nuevo en última versión).

## Cambios recientes

### v1.1.0 (29-12-2025)

- ✅ **Captura de `page_height`** (altura real de la página) en `heatmap-tracker.js`.
- ✅ **Corrección de escalado** en `heatmap-admin.js` para usar `page_height` en lugar de `viewport_h`.
- ✅ **Nuevo campo de BD:** `page_height` (INT DEFAULT 0).
- ✅ **Arreglo del nonce:** Ambas clases (public y admin) usan `'heatmap_leben_admin'`.
- ✅ **Validación de nonce mejorada** en `ajax_record_event()`.
- ✅ **Logs de debug** en el JS para facilitar troubleshooting.

### v1.0.0 (inicial)

- Captura de clicks y movimientos.
- Panel de administración con canvas.
- Exportación de imagen y CSV.
- Sistema de ajustes y filtros.

## Licencia

Este plugin está bajo desarrollo para uso interno. Adaptable según necesidades.

## Contacto y soporte

Para reportar errores o sugerencias, contacta al equipo de desarrollo.
