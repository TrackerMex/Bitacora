/**
 * WEBHOOK CORREGIDO - Índices ajustados
 *
 * CORRECCIÓN: tramo_id está en índice 20, NO 21
 * Actualizar en índices: 16 (fecha_salida), 17, 18, 19
 */

const SHEETS_IDS = {
  PLANIFICADOR: 'TU_ID_SHEETS_PLANIFICADOR',
  BITACORA: 'TU_ID_SHEETS_BITACORA'
};

const EVENT_TO_COLUMN = {
  'salida': 'fecha_salida',
  'entrada_carga': 'fecha_entrada_carga',
  'salida_carga': 'fecha_salida_carga',
  'descarga': 'fecha_descarga'
};

function doPost(e) {
  try {
    const payload = JSON.parse(e.postData.contents);

    const token = PropertiesService.getScriptProperties().getProperty('WIALON_TOKEN');
    if (payload.token !== token) {
      return crearRespuesta(false, 'Token inválido', 401);
    }

    const { unidad, event_type, pos_time, position, speed, odometer } = payload;

    if (!unidad || !event_type || !pos_time) {
      return crearRespuesta(false, 'Campos requeridos: unidad, event_type, pos_time', 400);
    }

    if (!EVENT_TO_COLUMN[event_type]) {
      registrarErrorLog('EVENT_TYPE_INVALIDO', event_type, unidad);
      return crearRespuesta(false, 'Tipo de evento no soportado: ' + event_type, 400);
    }

    try {
      verificarRateLimit(unidad);
    } catch (err) {
      registrarErrorLog('RATE_LIMIT_EXCEEDED', unidad, event_type);
      return crearRespuesta(false, 'Rate limit excedido para: ' + unidad, 429);
    }

    // BÚSQUEDA CORREGIDA: tramo_id en índice 20
    const tramo = buscarTramoActivo(unidad);
    if (!tramo) {
      registrarErrorLog('TRAMO_NO_ENCONTRADO', unidad, event_type);
      return crearRespuesta(false, 'No hay tramo para unidad: ' + unidad, 404);
    }

    const notificacion_id = Utilities.getUuid();
    const timestamp_recibida = new Date().toISOString();

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
      webhook_status: 'éxito'
    });

    const columna = EVENT_TO_COLUMN[event_type];
    const resultado_update = actualizarTramo(unidad, columna, pos_time);

    return crearRespuesta(true, notificacion_id, {
      tramo_id: tramo.tramo_id,
      folio: tramo.folio,
      columna_actualizada: columna,
      fue_actualizado: resultado_update.fue_actualizado,
      razon: resultado_update.razon
    });

  } catch (error) {
    Logger.log('❌ EXCEPCIÓN en doPost: ' + error.message);
    registrarErrorLog('EXCEPCIÓN', error.message, error.stack);
    return crearRespuesta(false, 'Error interno: ' + error.message, 500);
  }
}

// ===== FUNCIÓN 1: Buscar tramo =====
function buscarTramoActivo(unidad) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
    const tramoSheet = ss.getSheetByName('Tramos');

    if (!tramoSheet) {
      throw new Error('Hoja "Tramos" no existe');
    }

    const data = tramoSheet.getDataRange().getValues();

    // ÍNDICES CORREGIDOS:
    // 2 = unidad
    // 20 = tramo_id (NO 21)
    for (let i = 1; i < data.length; i++) {
      const rowUnidad = data[i][2];    // Columna C: unidad
      const rowTramoId = data[i][20];  // Columna U: tramo_id (CORREGIDO: era 21)
      const rowFolio = data[i][1];     // Columna B: folio

      if (rowUnidad === unidad && rowTramoId !== '' && rowTramoId !== null) {
        Logger.log(`✅ Tramo encontrado: unidad=${unidad}, tramo_id=${rowTramoId}`);
        return {
          tramo_id: rowTramoId,
          folio: rowFolio,
          unidad: rowUnidad,
          fila: i + 1
        };
      }
    }

    Logger.log(`⚠️ No se encontró tramo para unidad: ${unidad}`);
    return null;

  } catch (error) {
    Logger.log('❌ Error en buscarTramoActivo: ' + error.message);
    throw error;
  }
}

// ===== FUNCIÓN 2: Actualizar columna =====
function actualizarTramo(unidad, columna, timestamp_evento) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
    const tramoSheet = ss.getSheetByName('Tramos');

    // ÍNDICES CORREGIDOS:
    const columnMap = {
      'fecha_salida': 16,              // Columna Q (CORREGIDO: era 17)
      'fecha_entrada_carga': 17,       // Columna R (CORREGIDO: era 18)
      'fecha_salida_carga': 18,        // Columna S (CORREGIDO: era 19)
      'fecha_descarga': 19             // Columna T (CORREGIDO: era 20)
    };

    const colIndex = columnMap[columna];
    if (colIndex === undefined) {
      return {
        fue_actualizado: false,
        razon: 'Columna no mapeada: ' + columna
      };
    }

    const data = tramoSheet.getDataRange().getValues();

    let filaActualizar = -1;
    let tramoIdEncontrado = '';

    for (let i = 1; i < data.length; i++) {
      const rowUnidad = data[i][2];    // Columna C
      const rowTramoId = data[i][20];  // Columna U (CORREGIDO: era 21)

      if (rowUnidad === unidad && rowTramoId !== '' && rowTramoId !== null) {
        filaActualizar = i + 1;
        tramoIdEncontrado = rowTramoId;
        break;
      }
    }

    if (filaActualizar === -1) {
      return {
        fue_actualizado: false,
        razon: 'No se encontró tramo para unidad: ' + unidad
      };
    }

    const cellRef = tramoSheet.getRange(filaActualizar, colIndex + 1);
    const valorActual = cellRef.getValue();

    if (valorActual === '' || valorActual === null) {
      cellRef.setValue(timestamp_evento);
      Logger.log(`✅ Actualizado: tramo=${tramoIdEncontrado}, col=${columna}, valor=${timestamp_evento}`);
      return {
        fue_actualizado: true,
        razon: 'Actualizado exitosamente'
      };
    } else {
      Logger.log(`⚠️ Columna ya tiene valor: ${columna} = ${valorActual}`);
      return {
        fue_actualizado: false,
        razon: 'Columna ya tiene valor: ' + valorActual + ' (primera llegada gana)'
      };
    }

  } catch (error) {
    Logger.log('❌ Error en actualizarTramo: ' + error.message);
    registrarErrorLog('ERROR_UPDATE_TRAMO', error.message, unidad);
    return {
      fue_actualizado: false,
      razon: 'Excepción: ' + error.message
    };
  }
}

// ===== FUNCIÓN 3: Registrar notificación =====
function registrarNotificacion(datos) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
    const notifSheet = ss.getSheetByName('NotificacionesLog');

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
      datos.latitud || '',
      datos.longitud || '',
      datos.velocidad || 0,
      datos.odometro || 0,
      datos.webhook_status
    ];

    notifSheet.appendRow(row);
    Logger.log(`✅ Registrado: ${datos.notificacion_id}`);

  } catch (error) {
    Logger.log('❌ Error en registrarNotificacion: ' + error.message);
    registrarErrorLog('ERROR_REGISTRAR_LOG', error.message, datos.unidad);
  }
}

// ===== FUNCIÓN 4: Registrar errores =====
function registrarErrorLog(tipoError, mensaje, contexto = '') {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
    const logSheet = ss.getSheetByName('WebhookSecurityLog');

    if (!logSheet) {
      Logger.log('⚠️ WebhookSecurityLog no existe');
      return;
    }

    const row = [
      Utilities.getUuid(),
      new Date().toISOString(),
      tipoError,
      mensaje,
      contexto
    ];

    logSheet.appendRow(row);

  } catch (error) {
    Logger.log('⚠️ Error en registrarErrorLog: ' + error.message);
  }
}

// ===== FUNCIÓN 5: Rate limit =====
function verificarRateLimit(unidad) {
  const cache = CacheService.getScriptCache();
  const key = 'rate_' + unidad;
  let count = cache.get(key);
  count = count ? parseInt(count) + 1 : 1;

  if (count > 100) {
    throw new Error('Rate limit excedido para ' + unidad + ' (máx 100/minuto)');
  }

  cache.put(key, count.toString(), 60);
}

// ===== FUNCIÓN 6: Respuesta JSON =====
function crearRespuesta(success, data, statusCodeOrMetadata = 200, metadata = {}) {
  const response = {
    success: success,
    data: data,
    timestamp: new Date().toISOString()
  };

  if (typeof statusCodeOrMetadata === 'object') {
    Object.assign(response, statusCodeOrMetadata);
  } else if (typeof metadata === 'object') {
    Object.assign(response, metadata);
  }

  return ContentService.createTextOutput(JSON.stringify(response))
    .setMimeType(ContentService.MimeType.JSON);
}

// ===== SETUP =====
function setupScriptProperties() {
  const props = PropertiesService.getScriptProperties();
  props.setProperty('WIALON_TOKEN', 'orh5537498');
  props.setProperty('PLANIFICADOR_SHEET_ID', 'TU_ID');
  props.setProperty('BITACORA_SHEET_ID', 'TU_ID');
  Logger.log('✅ Script Properties configuradas');
}

// ===== TEST =====
function testWebhook() {
  Logger.log('=== TEST ===');
  const testPayload = {
    token: 'orh5537498',
    unidad: '35BD7J',
    event_type: 'salida',
    pos_time: new Date().toISOString(),
    position: { latitude: 40.7128, longitude: -74.0060 },
    speed: 0,
    odometer: 50000
  };

  try {
    const token = PropertiesService.getScriptProperties().getProperty('WIALON_TOKEN');
    if (testPayload.token !== token) {
      Logger.log('❌ Token inválido');
      return;
    }

    const tramo = buscarTramoActivo(testPayload.unidad);
    if (!tramo) {
      Logger.log('❌ Tramo no encontrado');
      return;
    }

    Logger.log(`✅ Tramo: ${tramo.tramo_id}`);
    const resultado = actualizarTramo(testPayload.unidad, 'fecha_salida', testPayload.pos_time);
    Logger.log(`✅ Actualizado: ${resultado.fue_actualizado}`);

  } catch (error) {
    Logger.log('❌ Error: ' + error.message);
  }
}
