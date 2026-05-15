/**
 * WEBHOOK FINAL - Adaptado para la estructura de Alex
 *
 * Tabla Tramos: 21 columnas (A-V)
 * - Columna 2 (C): unidad ("16BH8N")
 * - Columna 17 (R): fecha_salida
 * - Columna 18 (S): fecha_entrada_carga
 * - Columna 19 (T): fecha_salida_carga
 * - Columna 20 (U): fecha_descarga
 * - Columna 21 (V): tramo_id
 */

// ===== CONFIGURACIÓN =====
const SHEETS_IDS = {
  PLANIFICADOR: "1v-M4lioCip89zbNnTQUf2ZGDC8OsdncjRSSONFoYxiQ", // Cambiar por tu ID
  BITACORA: "1u9L81Jp5vRJ0YSaBnDPDJkvdqoamMOfM6OyXiOwHEGY", // Cambiar por tu ID
};

const EVENT_TO_COLUMN = {
  salida: "fecha_salida",
  entrada_carga: "fecha_entrada_carga",
  salida_carga: "fecha_salida_carga",
  descarga: "fecha_descarga",
};

// ===== PUNTO DE ENTRADA =====
function doPost(e) {
  try {
    const payload = JSON.parse(e.postData.contents);

    // 1. Validar token
    const token =
      PropertiesService.getScriptProperties().getProperty("WIALON_TOKEN");
    if (payload.token !== token) {
      return crearRespuesta(false, "Token inválido", 401);
    }

    // 2. Extraer datos del payload
    const { unidad, event_type, pos_time, position, speed, odometer } = payload;

    if (!unidad || !event_type || !pos_time) {
      return crearRespuesta(
        false,
        "Campos requeridos: unidad, event_type, pos_time",
        400,
      );
    }

    // 3. Validar event_type
    if (!EVENT_TO_COLUMN[event_type]) {
      registrarErrorLog("EVENT_TYPE_INVALIDO", event_type, unidad);
      return crearRespuesta(
        false,
        "Tipo de evento no soportado: " + event_type,
        400,
      );
    }

    // 4. Rate limiting
    try {
      verificarRateLimit(unidad);
    } catch (err) {
      registrarErrorLog("RATE_LIMIT_EXCEEDED", unidad, event_type);
      return crearRespuesta(false, "Rate limit excedido para: " + unidad, 429);
    }

    // 5. Buscar tramo por unidad
    const tramo = buscarTramoActivo(unidad);
    if (!tramo) {
      registrarErrorLog("TRAMO_NO_ENCONTRADO", unidad, event_type);
      return crearRespuesta(false, "No hay tramo para unidad: " + unidad, 404);
    }

    // 6. Generar IDs y timestamp
    const notificacion_id = Utilities.getUuid();
    const timestamp_recibida = new Date().toISOString();

    // 7. OPERACIÓN A: Registrar en NotificacionesLog
    registrarNotificacion({
      notificacion_id,
      tramo_id: tramo.tramo_id,
      folio: tramo.folio,
      unidad,
      tipo_evento: event_type,
      timestamp_evento: pos_time,
      timestamp_recibida: timestamp_recibida,
      latitud: position ? position.latitude : null,
      longitud: position ? position.longitude : null,
      velocidad: speed || 0,
      odometro: odometer || 0,
      webhook_status: "éxito",
    });

    // 8. OPERACIÓN B: Actualizar columna en Tramo
    const columna = EVENT_TO_COLUMN[event_type];
    const resultado_update = actualizarTramo(unidad, columna, pos_time);

    // 9. Retornar éxito con metadata
    return crearRespuesta(true, notificacion_id, {
      tramo_id: tramo.tramo_id,
      folio: tramo.folio,
      columna_actualizada: columna,
      fue_actualizado: resultado_update.fue_actualizado,
      razon: resultado_update.razon,
    });
  } catch (error) {
    Logger.log("❌ EXCEPCIÓN en doPost: " + error.message);
    registrarErrorLog("EXCEPCIÓN", error.message, error.stack);
    return crearRespuesta(false, "Error interno: " + error.message, 500);
  }
}

// ===== FUNCIÓN 1: Buscar tramo por unidad =====
/**
 * Busca un tramo en la tabla Tramos que tenga:
 * - Columna "unidad" (índice 2) = unidad pasada
 * - Columna "tramo_id" (índice 21) no vacía
 */
function buscarTramoActivo(unidad) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
    const tramoSheet = ss.getSheetByName("Datos");

    if (!tramoSheet) {
      Logger.log('❌ Hoja "Tramos" no encontrada');
      throw new Error('Hoja "Tramos" no existe');
    }

    const data = tramoSheet.getDataRange().getValues();

    // Headers: [fecha(0), folio(1), unidad(2), placas(3), ..., tramo_id(21)]
    for (let i = 1; i < data.length; i++) {
      const rowUnidad = data[i][2]; // Columna "unidad" (índice 2)
      const rowTramoId = data[i][20]; // Columna "tramo_id" (índice 21)
      const rowFolio = data[i][1]; // Columna "folio" (índice 1)

      // Criterios: unidad coincide Y tramo_id no está vacío
      if (rowUnidad === unidad && rowTramoId !== "" && rowTramoId !== null) {
        Logger.log(
          `✅ Tramo encontrado: unidad=${unidad}, tramo_id=${rowTramoId}`,
        );
        return {
          tramo_id: rowTramoId,
          folio: rowFolio,
          unidad: rowUnidad,
          fila: i + 1,
        };
      }
    }

    Logger.log(`⚠️ No se encontró tramo para unidad: ${unidad}`);
    return null;
  } catch (error) {
    Logger.log("❌ Error en buscarTramoActivo: " + error.message);
    throw error;
  }
}

// ===== FUNCIÓN 2: Actualizar columna en Tramo =====
/**
 * Actualiza la columna especificada en la fila del tramo.
 * Primera llegada gana: si ya tiene valor, NO sobrescribe.
 *
 * columnMap:
 * - fecha_salida: 16 (Columna R)
 * - fecha_entrada_carga: 17 (Columna S)
 * - fecha_salida_carga: 18 (Columna T)
 * - fecha_descarga: 19 (Columna U)
 */
function actualizarTramo(unidad, columna, timestamp_evento) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
    const tramoSheet = ss.getSheetByName("Datos");

    // Mapeo de columnas a índices 0-based
    const columnMap = {
      fecha_salida: 16, // Columna R
      fecha_entrada_carga: 17, // Columna S
      fecha_salida_carga: 18, // Columna T
      fecha_descarga: 19, // Columna U
    };

    const colIndex = columnMap[columna];
    if (colIndex === undefined) {
      return {
        fue_actualizado: false,
        razon: "Columna no mapeada: " + columna,
      };
    }

    const data = tramoSheet.getDataRange().getValues();

    // Buscar fila del tramo
    let filaActualizar = -1;
    let tramoIdEncontrado = "";

    for (let i = 1; i < data.length; i++) {
      const rowUnidad = data[i][2]; // Columna "unidad" (índice 2)
      const rowTramoId = data[i][21]; // Columna "tramo_id" (índice 21)

      if (rowUnidad === unidad && rowTramoId !== "" && rowTramoId !== null) {
        filaActualizar = i + 1; // +1 para convertir a 1-based
        tramoIdEncontrado = rowTramoId;
        break;
      }
    }

    if (filaActualizar === -1) {
      return {
        fue_actualizado: false,
        razon: "No se encontró tramo para unidad: " + unidad,
      };
    }

    // Obtener valor actual de la celda
    // colIndex es 0-based, pero getRange necesita 1-based
    const cellRef = tramoSheet.getRange(filaActualizar, colIndex + 1);
    const valorActual = cellRef.getValue();

    // REGLA: Primera llegada gana
    if (valorActual === "" || valorActual === null) {
      cellRef.setValue(timestamp_evento);
      Logger.log(
        `✅ Actualizado: tramo=${tramoIdEncontrado}, col=${columna}, valor=${timestamp_evento}`,
      );
      return {
        fue_actualizado: true,
        razon: "Actualizado exitosamente",
      };
    } else {
      Logger.log(`⚠️ Columna ya tiene valor: ${columna} = ${valorActual}`);
      return {
        fue_actualizado: false,
        razon:
          "Columna ya tiene valor: " + valorActual + " (primera llegada gana)",
      };
    }
  } catch (error) {
    Logger.log("❌ Error en actualizarTramo: " + error.message);
    registrarErrorLog("ERROR_UPDATE_TRAMO", error.message, unidad);
    return {
      fue_actualizado: false,
      razon: "Excepción: " + error.message,
    };
  }
}

// ===== FUNCIÓN 3: Registrar notificación en log =====
/**
 * Inserta una fila en NotificacionesLog con toda la información del evento.
 */
function registrarNotificacion(datos) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
    const notifSheet = ss.getSheetByName("NotificacionesLog");

    if (!notifSheet) {
      throw new Error('Hoja "NotificacionesLog" no existe');
    }

    const row = [
      datos.notificacion_id,
      datos.tramo_id,
      datos.folio,
      datos.unidad,
      datos.tipo_evento,
      datos.timestamp_evento,
      datos.timestamp_recibida,
      datos.latitud || "",
      datos.longitud || "",
      datos.velocidad || 0,
      datos.odometro || 0,
      datos.webhook_status,
    ];

    notifSheet.appendRow(row);
    Logger.log(`✅ Registrado en NotificacionesLog: ${datos.notificacion_id}`);
  } catch (error) {
    Logger.log("❌ Error en registrarNotificacion: " + error.message);
    registrarErrorLog("ERROR_REGISTRAR_LOG", error.message, datos.unidad);
  }
}

// ===== FUNCIÓN 4: Registrar errores =====
/**
 * Registra intentos fallidos en WebhookSecurityLog para auditoría y debugging.
 */
function registrarErrorLog(tipoError, mensaje, contexto = "") {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
    const logSheet = ss.getSheetByName("WebhookSecurityLog");

    if (!logSheet) {
      Logger.log(
        '⚠️ Hoja "WebhookSecurityLog" no existe, no se puede registrar error',
      );
      return;
    }

    const row = [
      Utilities.getUuid(),
      new Date().toISOString(),
      tipoError,
      mensaje,
      contexto,
    ];

    logSheet.appendRow(row);
    Logger.log(`📝 Error registrado: ${tipoError}`);
  } catch (error) {
    Logger.log("⚠️ Error registrando en WebhookSecurityLog: " + error.message);
  }
}

// ===== FUNCIÓN 5: Verificar rate limit =====
/**
 * Limita a 100 eventos por minuto por unidad.
 * Usa Google Apps Script Cache.
 */
function verificarRateLimit(unidad) {
  const cache = CacheService.getScriptCache();
  const key = "rate_" + unidad;
  let count = cache.get(key);
  count = count ? parseInt(count) + 1 : 1;

  if (count > 100) {
    throw new Error("Rate limit excedido para " + unidad + " (máx 100/minuto)");
  }

  cache.put(key, count.toString(), 60); // 60 segundos de ventana
}

// ===== FUNCIÓN 6: Crear respuesta JSON =====
/**
 * Retorna respuesta en formato JSON con status code.
 */
function crearRespuesta(
  success,
  data,
  statusCodeOrMetadata = 200,
  metadata = {},
) {
  const response = {
    success: success,
    data: data,
    timestamp: new Date().toISOString(),
  };

  // Si el tercer parámetro es un objeto, es metadata. Si es número, es status code
  if (typeof statusCodeOrMetadata === "object") {
    Object.assign(response, statusCodeOrMetadata);
  } else if (typeof metadata === "object") {
    Object.assign(response, metadata);
  }

  return ContentService.createTextOutput(JSON.stringify(response)).setMimeType(
    ContentService.MimeType.JSON,
  );
}

// ===== SETUP: Ejecutar una sola vez =====
/**
 * Ejecuta una sola vez para guardar IDs y token en Script Properties.
 * Luego puedes eliminar o comentar esta función.
 */
function setupScriptProperties() {
  const props = PropertiesService.getScriptProperties();

  // Cambiar estos valores por los reales
  props.setProperty("WIALON_TOKEN", "orh5537498");
  props.setProperty("PLANIFICADOR_SHEET_ID", "TU_ID_SHEETS_PLANIFICADOR");
  props.setProperty("BITACORA_SHEET_ID", "TU_ID_SHEETS_BITACORA");

  Logger.log("✅ Script Properties configuradas exitosamente");
}

// ===== TEST: Función de prueba =====
/**
 * Ejecuta un test simulando un evento de Wialon.
 * Útil para debugging sin Wialon real.
 */
function testWebhook() {
  Logger.log("=== TEST WEBHOOK ===");

  const testPayload = {
    token: "orh5537498",
    unidad: "16BH8N", // Cambiar por una unidad real de tu Sheets
    event_type: "salida",
    pos_time: new Date().toISOString(),
    position: {
      latitude: 40.7128,
      longitude: -74.006,
    },
    speed: 0,
    odometer: 50000,
  };

  // Simular doPost()
  try {
    const payload = testPayload;

    // Validar token
    const token =
      PropertiesService.getScriptProperties().getProperty("WIALON_TOKEN");
    if (payload.token !== token) {
      Logger.log("❌ Token inválido");
      return;
    }

    const { unidad, event_type, pos_time, position, speed, odometer } = payload;

    if (!EVENT_TO_COLUMN[event_type]) {
      Logger.log("❌ event_type no soportado");
      return;
    }

    const tramo = buscarTramoActivo(unidad);
    if (!tramo) {
      Logger.log("❌ No se encontró tramo para unidad: " + unidad);
      return;
    }

    Logger.log(`✅ Tramo encontrado: ${tramo.tramo_id}`);

    const columna = EVENT_TO_COLUMN[event_type];
    const resultado = actualizarTramo(unidad, columna, pos_time);

    Logger.log(`✅ Resultado de actualización: ${resultado.fue_actualizado}`);
    Logger.log(`   Razón: ${resultado.razon}`);
  } catch (error) {
    Logger.log("❌ Error en test: " + error.message);
  }
}
