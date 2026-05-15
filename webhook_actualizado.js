/**
 * WEBHOOK MEJORADO: Registra notificaciones Y actualiza Tramos
 *
 * Flujo:
 * 1. Recibe evento de Wialon (salida, entrada_carga, salida_carga, descarga)
 * 2. Valida token y busca tramo_id
 * 3. Registra en NotificacionesLog (auditoría)
 * 4. Actualiza columna correspondiente en Tramos (si está vacía)
 */

// ===== CONFIGURACIÓN =====
const SHEETS_IDS = {
  PLANIFICADOR: 'xxxxx-tu-id-planificador-xxxxx', // Cambiar por tu ID
  BITACORA: 'yyyyy-tu-id-bitacora-yyyyy'          // Cambiar por tu ID
};

const WIALON_TOKEN = 'orh5537498'; // Guardar en Script Properties, no aquí

const EVENT_TO_COLUMN = {
  'salida': 'fecha_salida',
  'entrada_carga': 'fecha_entrada_carga',
  'salida_carga': 'fecha_salida_carga',
  'descarga': 'fecha_descarga'
};

// ===== PUNTO DE ENTRADA =====
function doPost(e) {
  try {
    const payload = JSON.parse(e.postData.contents);

    // 1. Validar token
    const token = PropertiesService.getScriptProperties().getProperty('WIALON_TOKEN');
    if (payload.token !== token) {
      return crearRespuesta(false, 'Token inválido', 401);
    }

    // 2. Extraer datos
    const { unit_id, event_type, pos_time, position, speed, odometer } = payload;

    // 3. Validar event_type
    if (!EVENT_TO_COLUMN[event_type]) {
      return crearRespuesta(false, 'Tipo de evento no soportado: ' + event_type, 400);
    }

    // 4. Buscar tramo activo
    const tramo = buscarTramoActivo(unit_id, new Date(pos_time));
    if (!tramo) {
      registrarErrorLog('TRAMO_NO_ENCONTRADO', unit_id, event_type, pos_time);
      return crearRespuesta(false, 'No hay tramo activo para esta unidad', 404);
    }

    // 5. Buscar nombre de unidad
    const unidad = buscarUnidad(unit_id);
    const nombre_unidad = unidad ? unidad.nombre : unit_id;

    // 6. Generar IDs y timestamp
    const notificacion_id = Utilities.getUuid();
    const timestamp_recibida = new Date().toISOString();

    // 7. OPERACIÓN A: Registrar en NotificacionesLog
    registrarNotificacion({
      notificacion_id,
      tramo_id: tramo.tramo_id,
      unit_id,
      nombre_unidad,
      tipo_evento: event_type,
      timestamp_evento: pos_time,
      timestamp_recibida: timestamp_recibida,
      latitud: position.latitude,
      longitud: position.longitude,
      velocidad: speed,
      odometro: odometer,
      webhook_status: 'éxito'
    });

    // 8. OPERACIÓN B: Actualizar Tramo (si columna está vacía)
    const columna = EVENT_TO_COLUMN[event_type];
    const resultado_update = actualizarTramo(tramo.tramo_id, columna, pos_time);

    // 9. Retornar éxito
    return crearRespuesta(true, notificacion_id, tramo.tramo_id, {
      columna_actualizada: columna,
      fue_actualizado: resultado_update.fue_actualizado,
      razon: resultado_update.razon
    });

  } catch (error) {
    registrarErrorLog('EXCEPCIÓN', error.message, error.stack);
    return crearRespuesta(false, 'Error interno: ' + error.message, 500);
  }
}

// ===== FUNCIÓN 1: Buscar tramo activo =====
function buscarTramoActivo(unit_id, posTime) {
  const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
  const tramoSheet = ss.getSheetByName('Tramos');
  const data = tramoSheet.getDataRange().getValues();

  // Headers: [tramo_id, fecha_inicio, fecha_fin, unit_id, ruta, conductor, estado, timestamp_creacion, timestamp_actualizacion]
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    const rowUnitId = row[3];
    const inicio = new Date(row[1]);
    const fin = new Date(row[2]);
    const estado = row[6];

    if (rowUnitId === unit_id &&
        estado === 'activo' &&
        posTime >= inicio &&
        posTime <= fin) {
      return {
        tramo_id: row[0],
        fila: i + 1, // Número de fila para actualizar después
        fecha_inicio: inicio,
        fecha_fin: fin
      };
    }
  }
  return null;
}

// ===== FUNCIÓN 2: Buscar unidad =====
function buscarUnidad(unit_id) {
  const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
  const unitsSheet = ss.getSheetByName('UnidadesConfig');
  const data = unitsSheet.getDataRange().getValues();

  // Headers: [unit_id, nombre_unidad, placa, telefono, email, estado]
  for (let i = 1; i < data.length; i++) {
    if (data[i][0] === unit_id) {
      return {
        unit_id: data[i][0],
        nombre: data[i][1],
        placa: data[i][2],
        email: data[i][4],
        estado: data[i][5]
      };
    }
  }
  return null;
}

// ===== FUNCIÓN 3: Registrar notificación en log =====
function registrarNotificacion(datos) {
  const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
  const notifSheet = ss.getSheetByName('NotificacionesLog');

  const row = [
    datos.notificacion_id,
    datos.tramo_id,
    datos.unit_id,
    datos.nombre_unidad,
    datos.tipo_evento,
    datos.timestamp_evento,
    datos.timestamp_recibida,
    datos.latitud,
    datos.longitud,
    datos.velocidad,
    datos.odometro,
    datos.webhook_status
  ];

  notifSheet.appendRow(row);
}

// ===== FUNCIÓN 4: Actualizar columna en Tramo =====
/**
 * Actualiza una columna en la tabla Tramos si está vacía.
 * Primera llegada gana.
 */
function actualizarTramo(tramo_id, columna, timestamp_evento) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
    const tramoSheet = ss.getSheetByName('Tramos');
    const data = tramoSheet.getDataRange().getValues();

    // Mapeo columnas: [tramo_id=0, fecha_inicio=1, fecha_fin=2, unit_id=3, ruta=4, conductor=5, estado=6, ...]
    const columnMap = {
      'fecha_salida': 8,              // Índice 8 (columna I)
      'fecha_entrada_carga': 9,       // Índice 9 (columna J)
      'fecha_salida_carga': 10,       // Índice 10 (columna K)
      'fecha_descarga': 11            // Índice 11 (columna L)
    };

    const colIndex = columnMap[columna];
    if (colIndex === undefined) {
      return {
        fue_actualizado: false,
        razon: 'Columna no mapeada: ' + columna
      };
    }

    // Buscar fila del tramo
    let filaActualizar = -1;
    for (let i = 1; i < data.length; i++) {
      if (data[i][0] === tramo_id) {
        filaActualizar = i + 1; // +1 porque Sheets usa 1-indexing
        break;
      }
    }

    if (filaActualizar === -1) {
      return {
        fue_actualizado: false,
        razon: 'Tramo no encontrado en tabla: ' + tramo_id
      };
    }

    // Obtener valor actual de la columna
    const cellRef = tramoSheet.getRange(filaActualizar, colIndex + 1); // +1 porque es 0-indexed
    const valorActual = cellRef.getValue();

    // Verificar si está vacío
    if (valorActual === '' || valorActual === null) {
      // ACTUALIZAR
      cellRef.setValue(timestamp_evento);
      return {
        fue_actualizado: true,
        razon: 'Actualizado exitosamente'
      };
    } else {
      // NO ACTUALIZAR - primera llegada gana
      return {
        fue_actualizado: false,
        razon: 'Columna ya tiene valor: ' + valorActual + ' (primera llegada gana)'
      };
    }

  } catch (error) {
    registrarErrorLog('ERROR_UPDATE_TRAMO', error.message, tramo_id);
    return {
      fue_actualizado: false,
      razon: 'Excepción: ' + error.message
    };
  }
}

// ===== FUNCIÓN 5: Registrar errores =====
function registrarErrorLog(tipoError, mensaje, contexto = '') {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.BITACORA);
    const logSheet = ss.getSheetByName('WebhookSecurityLog');

    const row = [
      Utilities.getUuid(),
      new Date().toISOString(),
      tipoError,
      mensaje,
      contexto,
      Session.getTemporaryActiveUserKey() // Pseudo-IP, lo mejor que podemos hacer
    ];

    logSheet.appendRow(row);
  } catch (e) {
    // Silent fail - no queremos que un error en logging rompa el webhook
    Logger.log('Error registrando: ' + e.message);
  }
}

// ===== FUNCIÓN 6: Crear respuesta JSON =====
function crearRespuesta(success, data, statusCode = 200, metadata = {}) {
  const response = {
    success: success,
    data: data,
    timestamp: new Date().toISOString(),
    ...metadata
  };

  return ContentService.createTextOutput(JSON.stringify(response))
    .setMimeType(ContentService.MimeType.JSON);
}

// ===== FUNCIÓN 7: Rate limiting =====
function verificarRateLimit(unit_id) {
  const cache = CacheService.getScriptCache();
  const key = 'rate_' + unit_id;
  let count = cache.get(key) || 0;
  count++;

  if (count > 100) { // Max 100 eventos/minuto por unit_id
    throw new Error('Rate limit excedido para ' + unit_id);
  }

  cache.put(key, count, 60); // 60 segundos
  return true;
}

// ===== SCRIPT PROPERTIES SETUP (ejecutar una sola vez) =====
function setupScriptProperties() {
  const props = PropertiesService.getScriptProperties();

  props.setProperty('WIALON_TOKEN', 'orh5537498');
  props.setProperty('PLANIFICADOR_SHEET_ID', 'xxxxx-tu-id-planificador-xxxxx');
  props.setProperty('BITACORA_SHEET_ID', 'yyyyy-tu-id-bitacora-yyyyy');

  Logger.log('✅ Script Properties configuradas');
}
