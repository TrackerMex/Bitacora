# Guía de usuario — Nuevas funcionalidades

**TrackerSystem · Sistema de Monitoreo y Planificación de Flotas**
Actualización de junio 2026

Esta guía explica, paso a paso, cómo usar las cuatro mejoras incorporadas a la plataforma.
Está pensada para usuarios finales (clientes y operadores) y no requiere conocimientos técnicos.

---

## Índice

1. [Conceptos rápidos](#0-conceptos-rápidos)
2. [Identificar registros nuevos](#1-identificar-registros-nuevos)
3. [Editar y eliminar viajes](#2-editar-y-eliminar-viajes)
4. [Papelera del administrador](#3-papelera-del-administrador)
5. [Rutas multi-tramo y plantillas](#4-rutas-multi-tramo-y-plantillas)
6. [Validaciones GPS y accesorios](#5-validaciones-gps-y-accesorios)
7. [Preguntas frecuentes](#preguntas-frecuentes)

---

## 0. Conceptos rápidos

La plataforma tiene **dos vistas** que trabajan juntas:

| Vista | Para qué sirve | Quién la usa |
|---|---|---|
| **Planificador de Flotas** | Crear y editar viajes, unidades y plantillas de ruta. | Editores / administradores |
| **Bitácora (Monitoreo)** | Ver el estado de los viajes, GPS, KPIs e informes. | Todos (según permisos) |

**Roles de usuario:**
- **Administrador** — acceso total, incluida la Papelera.
- **Editor** — puede crear y modificar viajes y unidades.
- **Lector** — solo consulta; no puede modificar ni eliminar.

> Lo que creas o editas en el **Planificador** se refleja automáticamente en la **Bitácora**.

---

## 1. Identificar registros nuevos

**Qué es:** los viajes recién creados se resaltan automáticamente en la Bitácora para que
no se te pase nada nuevo.

![Lista de viajes con indicador de registro nuevo](img/01-viajes-nuevo.png)

**Cómo se ve:**
- El viaje nuevo aparece con **borde y fondo verde** y la etiqueta **🆕 Nuevo**.
- En el encabezado de cada cliente verás un contador, por ejemplo **«1 nuevo»**, que indica
  cuántos registros recientes tiene ese cliente.

**Cómo usarlo:**
1. Entra a la **Bitácora** y abre la pestaña **Viajes**.
2. Localiza de un vistazo los viajes resaltados en verde: son los más recientes.
3. Haz clic en el viaje para ver sus tramos y detalles.

**Cuándo deja de marcarse:** un viaje se considera «nuevo» durante sus **primeras 24 horas**.
Pasado ese tiempo, el resaltado desaparece solo, sin que tengas que hacer nada.

> 💡 No necesitas marcar nada como «leído»: el sistema lo gestiona por ti según la fecha de creación.

---

## 2. Editar y eliminar viajes

**Qué es:** ahora puedes corregir la información de una unidad o de un viaje directamente
desde el Planificador, sin pedir apoyo al equipo técnico.

![Tarjeta de unidad con botones de edición y borrado](img/06_Unidades%20en%20Sesión.png)

### 2.1 Editar los datos de la unidad
1. En el **Planificador**, ubica la tarjeta de la unidad.
2. Haz clic en **✏️ Editar unidad**.
3. Actualiza **operador, placas, teléfonos** o **equipos**.
4. Guarda. Los cambios se reflejan en la Bitácora.

### 2.2 Editar un tramo
Útil cuando una unidad tiene varios tramos y solo cambia uno.
1. En el viaje, ubica el tramo (T1, T2, …).
2. Haz clic en **✏️ Editar** sobre ese tramo.
3. Modifica **Ruta, Origen, Lugar de carga, Destino** o **Instrucciones**.
4. Guarda. Solo se actualiza ese tramo; el resto del viaje no cambia.

### 2.3 Eliminar un tramo
1. En el tramo correspondiente, haz clic en **🗑️**.
2. Confirma la acción cuando el sistema lo pida.
3. El tramo se quita sin afectar a los demás del viaje.

### 2.4 Eliminar un viaje completo
1. En la tarjeta de la unidad, haz clic en **🗑️ Eliminar viaje VJ-XXX**.
2. Confirma la acción.
3. El viaje completo se cancela y **queda registrado en la Papelera** del administrador
   (ver sección 3).

> ⚠️ **Importante:**
> - Todas las acciones de eliminar **piden confirmación** antes de ejecutarse.
> - Los usuarios con rol **Lector** no ven estos botones: no pueden modificar ni eliminar.
> - Eliminar **no borra definitivamente**: el registro queda como «Cancelado» y trazado en la Papelera.

---

## 3. Papelera del administrador

**Qué es:** una pestaña exclusiva del administrador que muestra **todo lo que los clientes
eliminan**, con trazabilidad completa.

**Para qué sirve:**
- Saber **qué** se eliminó, **quién** lo eliminó, **cuándo** y **por qué**.
- Mantener el control sin que se pierda información.

**Cómo acceder (solo administrador):**
1. Inicia sesión con un usuario **administrador**.
2. En la Bitácora, abre la pestaña **🗑️ Papelera**.
3. Verás una tabla con los viajes eliminados:

| Columna | Significado |
|---|---|
| **Folio** | Identificador del viaje (ej. VJ-008). |
| **Cliente / Unidad** | A quién pertenecía el viaje. |
| **Estado** | Aparece como **Cancelado**. |
| **Eliminado por** | Usuario que realizó la eliminación. |
| **Fecha** | Fecha y hora de la eliminación. |
| **Motivo** | Razón registrada del borrado. |

> 🔒 Los usuarios **Lector** y **Editor** **no ven** la Papelera. Es visibilidad exclusiva del administrador.

**Diferencia clave:**
- Un viaje **eliminado por un cliente** → sale de la pestaña Viajes y aparece en la **Papelera**.
- Un viaje **cancelado operativamente** (sin eliminar) → permanece visible en la pestaña Viajes
  con su estado «cancelado».

---

## 4. Rutas multi-tramo y plantillas

**Qué es:** el planificador permite registrar viajes con **varios tramos** y que abarcan
**varios días**, y reutilizar rutas frecuentes mediante **plantillas**.

**Un viaje se compone de tramos, cada uno con sus etapas:**

> **Origen** (salida de patio) → **Carga** (cita y lugar) → **Tramo** (trayecto) → **Destino** (descarga)

### 4.1 Crear una plantilla de ruta
Sirve para no volver a escribir rutas que usas seguido.

![Formulario para crear una plantilla de ruta](img/04-plantillas-form.png)

1. Abre el **Catálogo de Plantillas de Ruta**.
2. Haz clic en **Agregar Nueva Plantilla**.
3. Completa los campos:
   - **Etiqueta / Nombre** — identificador de la plantilla (ej. `CDMX-MTY-EXPRESS`).
   - **Ruta / Identificador** — código de ruta (ej. `MEX-001`).
   - **Punto de origen**, **Lugar de carga**, **Punto de destino**.
   - **Duración estimada (horas)** — opcional.
   - **Instrucciones predeterminadas** — opcional.
4. Haz clic en **Guardar Plantilla**.

### 4.2 Usar una plantilla al crear un viaje
![Selector de plantilla con campos autocompletados](img/05-usar-plantilla.png)

1. Al crear un tramo, abre el desplegable **Usar plantilla de ruta (opcional)**.
2. Selecciona la plantilla deseada.
3. Los campos **Ruta, Origen, Lugar de carga, Destino e Instrucciones** se rellenan
   **automáticamente**.
4. Ajusta lo que necesites y continúa.

> 💡 Las plantillas son por cliente: cada cliente ve y reutiliza sus propias rutas.

---

## 5. Validaciones GPS y accesorios

**Qué es:** una vista consolidada que muestra, por unidad, el estado del **GPS** y de los
**accesorios** de seguridad.

![Vista de validaciones GPS y accesorios](img/02-Validaciones%20GPS%20y%20Accesorios.png)

**Cómo usarlo:**
1. En la Bitácora, abre la pestaña **Validaciones GPS**.
2. Usa los filtros superiores para acotar por **Unidad**, **Cliente** o **Estado**.
3. Cada tarjeta de unidad muestra:
   - **Estado general:** ✅ **Operativo** o ⚠️ **Con fallas**.
   - **Checklist de accesorios:** GPS principal, GPS secundario, cámara, botón de pánico,
     candado electrónico. Cada uno con ✅ (correcto) o ❌ (con falla).
   - **Fecha de validación:** cuándo se verificó por última vez.

**Cómo leerlo rápido:**
- Una unidad con cualquier accesorio en ❌ se marca como **Con fallas** → requiere atención.
- Las unidades en verde (**Operativo**) están listas para operar.

---

## Preguntas frecuentes

**¿Por qué ya no veo un viaje que eliminé?**
Porque pasó a la **Papelera**. Si eres administrador, lo encuentras en esa pestaña; si no,
pídeselo al administrador.

**Eliminé algo por error. ¿Se puede recuperar?**
La Papelera es de **solo visualización**: muestra lo eliminado con su motivo, pero no
restaura. Si necesitas el viaje de vuelta, vuelve a crearlo en el Planificador.

**¿Por qué no veo los botones de editar/eliminar?**
Tu usuario probablemente tiene rol **Lector**, que es de solo consulta. Pide a un
administrador que ajuste tus permisos si necesitas editar.

**¿Cuánto tiempo dura la etiqueta «Nuevo»?**
24 horas desde que se creó el viaje. Después desaparece automáticamente.

**Edité un tramo, ¿afecta a los demás tramos del viaje?**
No. La edición por tramo es independiente: solo cambia el tramo que seleccionaste.

---

*Documento de referencia · TrackerSystem · trackersystem.tech*
