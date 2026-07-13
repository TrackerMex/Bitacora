---
name: TrackerSystem Bitácora
description: Sistema operativo de monitoreo logístico para centro de control, confiable, preciso y legible en pantallas de operación
colors:
  primary: "#4f46e5"
  primary-strong: "#4338ca"
  action-blue: "#2563eb"
  action-blue-hover: "#1d4ed8"
  success: "#16a34a"
  success-soft: "#d1fae5"
  success-text: "#065f46"
  danger: "#dc2626"
  danger-soft: "#fee2e2"
  danger-text: "#991b1b"
  warning: "#ca8a04"
  warning-soft: "#fef3c7"
  warning-text: "#92400e"
  orange-soft: "#fed7aa"
  orange-text: "#c2410c"
  info-soft: "#dbeafe"
  info-text: "#1e40af"
  amber-panel: "#fffbeb"
  amber-border: "#fbbf24"
  surface-page: "#f3f4f6"
  surface-panel: "#f9fafb"
  surface-card: "#ffffff"
  border-muted: "#e5e7eb"
  border-control: "#d1d5db"
  text-strong: "#111827"
  text-heading: "#1f2937"
  text-body: "#374151"
  text-muted: "#4b5563"
  text-subtle: "#6b7280"
typography:
  display:
    fontFamily: "Inter, sans-serif"
    fontSize: "1.875rem"
    fontWeight: 800
    lineHeight: 1.2
  headline:
    fontFamily: "Inter, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.3
  title:
    fontFamily: "Inter, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 700
    lineHeight: 1.35
  body:
    fontFamily: "Inter, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Inter, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.3
rounded:
  sm: "0.375rem"
  md: "0.5rem"
  lg: "0.75rem"
  pill: "9999px"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "2rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface-card}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
  button-primary-hover:
    backgroundColor: "{colors.primary-strong}"
  button-dark:
    backgroundColor: "{colors.text-strong}"
    textColor: "{colors.surface-card}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
  button-secondary:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.text-body}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
  card:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.text-body}"
    rounded: "{rounded.lg}"
    padding: "1.25rem"
  input:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.text-body}"
    rounded: "{rounded.sm}"
    padding: "0.5rem"
---

# Design System: TrackerSystem Bitácora

## 1. Overview

**Creative North Star: "Mesa de Control Logístico"**

TrackerSystem Bitácora debe sentirse como una mesa de control real: sobria, confiable y preparada para operación continua. La interfaz sirve a usuarios que monitorean viajes, validan GPS, revisan incidencias, consultan KPIs y generan informes bajo presión operativa. La estética no debe competir con la información. Debe ayudar a detectar estado, riesgo, cliente, unidad y siguiente acción con rapidez.

La personalidad visual es confiable, operativa y precisa. El sistema usa una base clara, neutrales fríos y color semántico para estados. Verde confirma operación correcta, rojo marca riesgo, amarillo y naranja advierten atención, azul identifica información operativa o acciones de edición. El color debe ser señal, nunca decoración.

Este sistema rechaza explícitamente el SaaS genérico. No debe parecer una plantilla de dashboard con métricas infladas, gradientes decorativos, tarjetas repetidas sin intención o colores usados para adornar. La densidad está permitida porque el producto es una herramienta de monitoreo. La clave es que esa densidad sea legible, estable y trazable.

**Key Characteristics:**

- Interfaz de producto, no landing page.
- Alta legibilidad en pantallas de centro de monitoreo.
- Jerarquía clara para estado, unidad, cliente, ruta e incidencia.
- Color semántico consistente para operación logística.
- Componentes familiares: tabs, cards, tablas, filtros, modales y formularios.
- Diseño sobrio, funcional y resistente al uso prolongado.

## 2. Colors

La paleta combina neutrales fríos con colores de estado claramente diferenciados para operación logística.

### Primary

- **Azul Índigo Operativo**: color principal para navegación activa, filtros seleccionados e inicio de sesión. Debe usarse con moderación para indicar selección, foco funcional o acción principal.
- **Índigo de Confirmación**: variante hover del color principal. Refuerza interacción sin introducir otro acento visual.

### Secondary

- **Azul de Acción**: usado en acciones de edición, enlaces a mapas, botones de descarga y elementos relacionados con información consultable.
- **Azul de Estado Programado**: azul suave para estados informativos como Programado, Salida de carga o Cancelado cuando se requiere una lectura no crítica.

### Tertiary

- **Verde Operativo**: usado para estados correctos, viajes a tiempo, unidades operativas y acciones de guardado.
- **Rojo Crítico**: usado para cierre de sesión, errores, retrasos, fallas, incidencias críticas y acciones destructivas.
- **Ámbar de Atención**: usado para En proceso, En ruta, campos no programados, instrucciones especiales o advertencias operativas.
- **Naranja de Incidencia**: usado para severidad media o condiciones que requieren atención sin ser críticas.

### Neutral

- **Fondo de Sala Clara**: fondo general de la app. Mantiene el monitoreo legible durante sesiones largas.
- **Panel Operativo**: fondo secundario para celdas, bloques internos y secciones de baja prioridad.
- **Superficie de Tarjeta**: superficie base para headers, cards, tablas, modales y contenedores.
- **Línea de División**: borde para separar celdas, tablas, cards y regiones sin generar ruido.
- **Texto Fuerte**: títulos principales y contenido de máxima prioridad.
- **Texto de Trabajo**: cuerpo, datos, etiquetas secundarias y contenido de cards.
- **Texto Atenuado**: metadatos, pistas, placas, subtítulos y mensajes de apoyo.

### Named Rules

**The Signal Color Rule.** Verde, rojo, amarillo, naranja y azul solo se usan para comunicar estado, acción o selección. Está prohibido usarlos como decoración.

**The Monitoring Contrast Rule.** Todo estado crítico debe combinar color con texto, etiqueta o icono. No se debe depender solo del color.

**The Restrained Accent Rule.** El índigo principal debe ocupar una fracción pequeña de la pantalla. Si todo parece seleccionado, nada está seleccionado.

## 3. Typography

**Display Font:** Inter, con fallback sans-serif  
**Body Font:** Inter, con fallback sans-serif  
**Label/Mono Font:** No hay familia distinta documentada

**Character:** La tipografía es limpia, directa y de producto. Inter funciona bien para datos, etiquetas, tablas y pantallas de monitoreo porque mantiene buena lectura en tamaños pequeños sin parecer decorativa.

### Hierarchy

- **Display**: peso 800, tamaño 1.875rem, línea 1.2. Se usa en el título principal de la aplicación. Debe reservarse para identidad de pantalla, no para cada sección.
- **Headline**: peso 600, tamaño 1.5rem, línea 1.3. Se usa en títulos de pestañas como KPIs, Informe Ejecutivo e Informes Guardados.
- **Title**: peso 700, tamaño 1.125rem, línea 1.35. Se usa en títulos de tarjetas, unidades, paneles de chart y encabezados internos.
- **Body**: peso 400, tamaño 0.875rem, línea 1.5. Se usa para datos operativos, rutas, clientes, operadores, tablas y textos de apoyo.
- **Label**: peso 600, tamaño 0.75rem, línea 1.3. Se usa para badges, folios, filtros, instrucciones especiales, etiquetas de estado y metadatos.

### Named Rules

**The Data First Rule.** La tipografía debe favorecer lectura de datos antes que personalidad. No usar fuentes display en botones, labels, tablas o estados.

**The Short Label Rule.** Las etiquetas deben ser breves y operativas: Folio, Cliente, Unidad, Ruta, Estado, Motivo. Evitar frases largas dentro de chips o badges.

**The Distance Check Rule.** Títulos, estados e incidencias deben poder leerse en pantallas de monitoreo a distancia moderada. Si un operador debe acercarse para distinguir criticidad, la jerarquía falla.

## 4. Elevation

El sistema usa una estrategia híbrida: superficies blancas sobre fondo gris claro, bordes suaves y sombras moderadas. La profundidad debe aclarar jerarquía, no decorar. Las sombras actuales aparecen en headers, contenedores de pestañas, cards, KPIs, charts y modales. En pantallas densas, preferir borde más fondo tonal antes que sombras pesadas.

### Shadow Vocabulary

- **Surface Resting**: sombra media de Tailwind equivalente a `shadow-md`. Se usa en header, panel de cliente, contenedores de charts y cards que necesitan separación.
- **Operational Card**: sombra grande de Tailwind equivalente a `shadow-lg`. Se usa en cards de viaje y contenedores principales cuando compiten con mucha información.
- **Critical Overlay**: sombra extra grande de Tailwind equivalente a `shadow-2xl`. Se usa solo en modales.
- **Metric Lift**: sombra intensa de Tailwind equivalente a `shadow-xl`. Se usa en KPI cards, pero debe mantenerse bajo control para evitar apariencia de dashboard genérico.

### Named Rules

**The Flat Under Load Rule.** En vistas densas, los bordes y fondos tonales tienen prioridad sobre sombras fuertes. Si una pantalla parece llena de cajas flotantes, hay demasiada elevación.

**The Modal Exception Rule.** `shadow-2xl` pertenece a overlays y diálogos. No debe usarse para cards comunes.

**The Border Before Shadow Rule.** Tablas, filtros y celdas deben separarse con bordes claros antes que con sombras.

## 5. Components

### Buttons

Los botones deben sentirse táctiles, claros y predecibles.

- **Shape:** esquinas redondeadas funcionales, normalmente 0.5rem o 0.75rem.
- **Primary:** fondo índigo o azul, texto claro, padding de 0.5rem por 1rem. Usar para iniciar sesión, acciones seleccionadas y edición.
- **Dark Action:** fondo casi negro tintado, texto claro. Usar para acciones utilitarias como descargar PDF o aplicar rangos.
- **Success Action:** fondo verde, texto claro. Usar para guardar informe o confirmar operación.
- **Danger Action:** fondo rojo, texto claro. Usar para cerrar sesión, eliminar o marcar riesgo.
- **Secondary:** fondo blanco, borde claro, texto gris. Usar para filtros no seleccionados y descargas secundarias.
- **Hover / Focus:** el hover oscurece el color base un paso. El focus debe ser visible, idealmente con anillo o borde reforzado.
- **Disabled:** usar gris medio con texto claro para acciones no disponibles. No ocultar una acción si explica el estado operativo.

### Chips

Los chips comunican estado, filtros y folios.

- **Style:** forma pill para estados, fondos suaves y texto saturado.
- **State:** un chip seleccionado puede usar índigo sólido. Un chip no seleccionado debe usar superficie blanca, borde claro y texto gris.
- **Operational Use:** Programado, En ruta, Cargando, Realizado, No realizado, Cancelado y No programada deben mantener colores estables en toda la app.
- **Critical Rule:** Un estado crítico no debe ser solo rojo. Debe incluir texto explícito como Con retraso, No realizado, Con fallas o Crítica.

### Cards / Containers

Las cards organizan información operativa de viaje, unidad, rutas, KPIs y gráficos.

- **Corner Style:** esquinas amplias, normalmente 0.75rem.
- **Background:** superficie blanca sobre fondo gris claro.
- **Shadow Strategy:** sombra media o grande solo cuando la card necesita distinguirse. Para tablas y celdas, usar borde.
- **Border:** gris claro, especialmente en charts, cards de rutas y contenedores de información.
- **Internal Padding:** 1.25rem para cards de datos, 1.5rem para paneles principales y charts.
- **Content Pattern:** unidad y folio arriba, metadatos debajo, instrucciones especiales en bloque ámbar.
- **Empty State:** usar una superficie blanca centrada con mensaje claro. Evitar “sin datos” cuando se puede indicar siguiente acción.

### Inputs / Fields

Los campos deben parecer estándar, no inventados.

- **Style:** fondo blanco, borde gris, padding de 0.5rem y radio de 0.375rem a 0.5rem.
- **Focus:** debe reforzar borde o anillo sin cambiar layout.
- **Error:** texto rojo claro y mensaje específico junto al campo o bloque de formulario.
- **Disabled / Readonly:** mantener lectura clara, con fondo neutral y texto gris.
- **Date Inputs:** deben conservar formato nativo para fiabilidad en operación.

### Navigation

La navegación principal usa tabs horizontales con scroll en pantallas estrechas.

- **Style:** tabs con padding generoso, peso 600 y radio superior.
- **Active:** fondo blanco, texto fuerte y subrayado índigo de 3px.
- **Inactive:** texto gris, sin fondo pesado.
- **Mobile:** permitir overflow horizontal, no comprimir labels hasta perder significado.
- **Permission Behavior:** las tabs no permitidas se ocultan. La navegación debe mantener orden estable para los roles que sí tienen acceso.

### Tables

Las tablas presentan reportes, papelera, informes guardados y detalle operativo.

- **Style:** texto de 0.875rem, encabezados semibold, divisores horizontales claros.
- **Header:** fondo blanco o gris muy claro, texto gris fuerte.
- **Rows:** separación por línea, no por sombra.
- **Overflow:** usar scroll horizontal cuando el contenido lo requiera.
- **Density:** la densidad es aceptable, siempre que los encabezados y acciones sigan siendo escaneables.

### Modals

Los modales existen para login, detalle y edición. Deben usarse solo cuando la tarea requiere foco completo.

- **Overlay:** fondo negro translúcido al 45%.
- **Container:** superficie blanca, radio 0.75rem, sombra fuerte, ancho máximo definido.
- **Padding:** 1.5rem a 2rem.
- **Title:** 1.5rem, peso 700, texto gris fuerte.
- **Forms:** labels pequeñas y campos estándar.
- **Rule:** no introducir nuevos modales para tareas que puedan resolverse con edición inline o panel progresivo.

### Signature Component

**Registro Board de Monitoreo**

El board de registro organiza unidades contra etapas operativas. En escritorio usa grid con columnas estables y divisores finos. En pantallas menores a 1024px se transforma en bloques apilados con labels generadas por `data-label`.

- **Desktop:** grid estructurado, celdas centradas, encabezado gris.
- **Mobile:** filas convertidas en cards apiladas, labels visibles y alineación izquierda.
- **States:** cada celda debe usar color semántico, texto y estructura consistente.
- **Alert Light:** los focos verde y rojo indican estado de alerta. El parpadeo rojo debe reservarse para atención real, no decoración.

## 6. Extracted Primitives

### State Block

Bloque reutilizable para estados vacíos, de carga y de atención en tabs, tablas, listados y cuerpos de modal. En `src/index.html` quedó encapsulado en `renderStateCard()`.

- **Variants:** `neutral`, `soft`, `warning`, `plain`.
- **Options:** `fullWidth`, `compact`, `loading`, `description`.
- **Use:** mensajes de no data, carga y aviso antes de crear bloques únicos por caso.
- **Rule:** si el mensaje no necesita interacción, debe vivir en este patrón antes de abrir una nueva variante visual.

## 7. Do's and Don'ts

### Do:

- **Do** usar color como señal operativa: verde para correcto, rojo para crítico, amarillo o naranja para atención, azul para información o acción.
- **Do** mantener Inter como familia principal para toda la interfaz.
- **Do** diseñar para pantallas de monitoreo: estados legibles, labels cortas y jerarquía clara.
- **Do** mantener los tabs como patrón principal de navegación.
- **Do** usar bordes claros para tablas, boards y grids densos.
- **Do** combinar color con texto o iconos en estados críticos.
- **Do** reservar sombras fuertes para modales y superficies de alta prioridad.
- **Do** mantener acciones destructivas en rojo y con confirmación.
- **Do** conservar densidad cuando ayuda a monitorear más unidades sin cambiar de pantalla.

### Don't:

- **Don't** hacer que parezca un SaaS genérico.
- **Don't** usar gradientes decorativos para títulos, métricas o botones.
- **Don't** usar color solo como adorno.
- **Don't** repetir grids de cards idénticas sin jerarquía operativa.
- **Don't** usar el patrón de “número grande con label pequeña” como solución universal para todos los datos.
- **Don't** depender solo del color para comunicar incidencias o fallas.
- **Don't** aplicar `border-left` o `border-right` mayor a 1px como acento decorativo en cards o alertas nuevas.
- **Don't** introducir glassmorphism, blur decorativo o fondos translúcidos fuera del overlay modal.
- **Don't** convertir vistas operativas en landing pages o dashboards promocionales.
- **Don't** usar modales como primera solución para cada acción nueva.
