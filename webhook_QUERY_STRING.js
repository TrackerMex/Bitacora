/**
 * WEBHOOK FINAL - Acepta Query String de Wialon
 * Formato: token=X&unidad=Y&event_type=Z&...
 */

const SHEETS_IDS = {
  BITACORA: 'TU_BITACORA_SHEET_ID'
};

const EVENT_TO_COLUMN = {
  'salida': 'fecha_salida',
  'entrada_carga': 'fecha_entrada_carga',
  'salida_carga': 'fecha_salida_carga',
  'descarga': 'fecha_descarga'
};

function doPost(e) {
  try {
    // ✅ PARSEAR QUERY STRING (no JSON)
    const params = e.parameter;

    Logger.log('=== WEBHOOK RECIBIDO ===');
    Logger.log('Parámetros: ' + JSON.stringify(params));

    // Extraer parámetros
    const token = params.token;
    const unidad = params.unidad;
    const event_type = params.event_type;
    const pos_time = params.pos_time;
    const latitude = params.latitude;
    const longitude = params.longitude;
    const speed = params.speed;
    const odometer = params.odometer;

    // 1. Validar token
    const expectedToken = PropertiesService.getScriptProperties().getProperty('WIALON_TOKEN');
    if (token !== expectedToken) {
      Logger.log('❌ Token inválido: ' + token);
      return crearRespuesta(false, 'Token inválido');
    }

    // 2. Validar parámetros requeridos
    if (!unidad || !event_type || !pos_time) {
      Logger.log('❌ Faltan parámetros: unidad=' + unidad + ', event_type=' + event_type + ', pos_time=' + pos_time);
      return crearRespuesta(false, 'Faltan parámetros requeridos');
    }

    // 3. Validar event_type
    if (!EVENT_TO_COLUMN[event_type]) {
      Logger.log('❌ event_type no soportado: ' + event_type);
      registrarErrorLog('EVENT_TYPE_INVALIDO', event_type, unidad);
      return crearRespuesta(false, 'Tipo de evento no soportado: ' + event_type);
    }

    // 4. Parsear valores numéricos (limpiar espacios y unidades)
    let speedNum = 0;
    let odometerNum = 0;

    if (speed) {
      speedNum = parseFloat(speed.toString().replace(/[^\d.-]/g, '')) || 0;
    }
    if (odometer) {
      odometerNum = parseFloat(odometer.toString().replace(/[^\d.-]/g, '')) || 0;
    }

    const lat = parseFloat(latitude) || 0;
    const lon = parseFloat(longitude) || 0;

    Logger.log('✅ Parámetros válidos:');
    Logger.log('   unidad: ' + unidad);
    Logger.log('   event_type: ' + event_type);
    Logger.log('   pos_time: ' + pos_time);
    Logger.log('   speed: ' + speedNum);
    Logger.log('   odometer: ' + odometerNum);
    Logger.log('   lat: ' + lat + ', lon: ' + lon);

    // 5. Rate limiting
    try {
      verificarRateLimit(unidad);
    } catch (err) {
      Logger.log('⚠️ Rate limit: ' + err.message);
      registrarErrorLog('RATE_LIMIT_EXCEEDED', unidad, event_type);
      return crearRespuesta(false, 'Rate limit excedido');
    }

    // 6. Buscar tramo
    const tramo = buscarTramoActivo(unidad);
    if (!tramo) {
      Logger.log('❌ Tramo no encontrado para: ' + unidad);
      registrarErrorLog('TRAMO_NO_ENCONTRADO', unidad, event_type);
      return crearRespuesta(false, 'No hay tramo para unidad: ' + unidad);
    }

    Logger.log('✅ Tramo encontrado: ' + tramo.tramo_id);

    // 7. Registrar notificación
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
      latitud: lat,
      longitud: lon,
      velocidad: speedNum,
      odometro: odometerNum,
      webhook_status: 'éxito'
    });

    Logger.log('✅ Registrado en NotificacionesLog');

    // 8. Actualizar tramo
    const columna = EVENT_TO_COLUMN[event_type];
    const resultado_update = actualizarTramo(unidad, columna, pos_time);

    Logger.log('✅ Actualización: fue_actualizado=' + resultado_update.fue_actualizado);

    // 9. Respuesta exitosa
    return crearRespuesta(true, {
      notificacion_id,
      tramo_id: tramo.tramo_id,
      folio: tramo.folio,
      columna_actualizada: columna,
      fue_actualizado: resultado_update.fue_actualizado,
      razon: resultado_update.razon
    });

  } catch (error) {
    Logger.log('❌ EXCEPCIÓN: ' + error.message);
    Logger.log('Stack: ' + error.stack);
    registrarErrorLog('EXCEPCIÓN', error.message, error.stack);
    return crearRespuesta(false, 'Error interno: ' + error.message);
  }
}

// ===== FUNCIÓN 1: Buscar tramo =====
function buscarTramoActivo(unidad) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
    const tramoSheet = ss.getSheetByName('Datos');

    if (!tramoSheet) {
      throw new Error('Hoja "Datos" no existe');
    }

    const data = tramoSheet.getDataRange().getValues();

    for (let i = 1; i < data.length; i++) {
      const rowUnidad = data[i][2];
      const rowTramoId = data[i][20];
      const rowFolio = data[i][1];

      if (rowUnidad === unidad && rowTramoId !== '' && rowTramoId !== null) {
        return {
          tramo_id: rowTramoId,
          folio: rowFolio,
          unidad: rowUnidad,
          fila: i + 1
        };
      }
    }

    return null;

  } catch (error) {
    Logger.log('❌ Error en buscarTramoActivo: ' + error.message);
    throw error;
  }
}

// ===== FUNCIÓN 2: Actualizar columna =====
function actualizarTramo(unidad, columna, timestamp_evento) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
    const tramoSheet = ss.getSheetByName('Datos');

    const columnMap = {
      'fecha_salida': 16,
      'fecha_entrada_carga': 17,
      'fecha_salida_carga': 18,
      'fecha_descarga': 19
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
      const rowUnidad = data[i][2];
      const rowTramoId = data[i][20];

      if (rowUnidad === unidad && rowTramoId !== '' && rowTramoId !== null) {
        filaActualizar = i + 1;
        tramoIdEncontrado = rowTramoId;
        break;
      }
    }

    if (filaActualizar === -1) {
      return {
        fue_actualizado: false,
        razon: 'No se encontró tramo'
      };
    }

    const cellRef = tramoSheet.getRange(filaActualizar, colIndex + 1);
    const valorActual = cellRef.getValue();

    if (valorActual === '' || valorActual === null) {
      cellRef.setValue(timestamp_evento);
      return {
        fue_actualizado: true,
        razon: 'Actualizado exitosamente'
      };
    } else {
      return {
        fue_actualizado: false,
        razon: 'Columna ya tiene valor'
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

    if (!logSheet) return;

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
    throw new Error('Rate limit excedido');
  }

  cache.put(key, count.toString(), 60);
}

// ===== FUNCIÓN 6: Respuesta =====
function crearRespuesta(success, data) {
  const response = {
    success: success,
    data: data,
    timestamp: new Date().toISOString()
  };

  return ContentService.createTextOutput(JSON.stringify(response))
    .setMimeType(ContentService.MimeType.JSON);
}

// ===== SETUP =====
function setupScriptProperties() {
  const props = PropertiesService.getScriptProperties();
  props.setProperty('WIALON_TOKEN', 'orh5537498');
  props.setProperty('BITACORA_SHEET_ID', 'TU_BITACORA_ID');
  Logger.log('✅ Script Properties configuradas');
}
