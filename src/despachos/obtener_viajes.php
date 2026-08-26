<?php

error_reporting(0);
ini_set("display_errors", 0);
ini_set("memory_limit", "256M");
ini_set("max_execution_time", 30);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$response = [
    "success" => false,
    "message" => "",
    "data" => [],
    "count" => 0,
    "total" => 0,
];

function parse_id_list_v($value)
{
    $ids = [];
    foreach (explode(",", (string) $value) as $part) {
        $id = intval(trim($part));
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function to_date_v($v)
{
    $s = trim((string) ($v ?? ""));
    if ($s === "") {
        return null;
    }
    if (preg_match("/^\d{4}-\d{2}-\d{2}/", $s)) {
        return substr($s, 0, 10);
    }
    return null;
}

function str_or_null($v)
{
    $s = trim((string) ($v ?? ""));
    return $s === "" ? null : $s;
}

function firma_ruta_v($origen, $lugar_carga, $destino)
{
    $norm = function ($v) {
        return mb_strtolower(trim((string) ($v ?? "")), "UTF-8");
    };
    $firma = $norm($origen) . "|" . $norm($lugar_carga) . "|" . $norm($destino);
    return $firma === "||" ? null : $firma;
}

function db_column_exists_v($conn, $table, $column)
{
    $stmt = $conn->prepare(
        "SELECT 1
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1",
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $row;
}

const RUTA_FRECUENTE_MIN_USOS = 5;

try {
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        throw new Exception("Método no permitido. Use GET.");
    }

    require_once __DIR__ . "/../db/db.php";

    $cliente_ids = parse_id_list_v($_GET["cliente_ids"] ?? "");
    $fecha_desde = to_date_v($_GET["fecha_desde"] ?? "");
    $fecha_hasta = to_date_v($_GET["fecha_hasta"] ?? "");
    $unidad_id = isset($_GET["unidad_id"]) ? intval($_GET["unidad_id"]) : null;
    $estado_f = trim((string) ($_GET["estado"] ?? ""));
    $con_tramos = ($_GET["con_tramos"] ?? "1") !== "0";
    $limit = min(max(intval($_GET["limit"] ?? 200), 1), 500);
    $offset = max(intval($_GET["offset"] ?? 0), 0);

    $estados_validos = ["planificado", "en_curso", "completado", "cancelado"];

    $where = ["c.activo = 1", "u.activo = 1", "v.eliminado_at IS NULL"];
    $types = "";
    $params = [];

    if (count($cliente_ids) > 0) {
        $ph = implode(",", array_fill(0, count($cliente_ids), "?"));
        $where[] = "v.cliente_id IN ($ph)";
        $types .= str_repeat("i", count($cliente_ids));
        foreach ($cliente_ids as $id) {
            $params[] = $id;
        }
    } elseif (isset($_GET["cliente_ids"])) {
        $where[] = "1 = 0";
    }

    if ($unidad_id > 0) {
        $where[] = "v.unidad_id = ?";
        $types .= "i";
        $params[] = $unidad_id;
    }

    if ($fecha_desde) {
        $where[] =
            "(v.fecha_fin >= ? OR (v.fecha_fin IS NULL AND v.fecha_inicio >= ?))";
        $types .= "ss";
        $params[] = $fecha_desde;
        $params[] = $fecha_desde;
    }
    if ($fecha_hasta) {
        $where[] = "v.fecha_inicio <= ?";
        $types .= "s";
        $params[] = $fecha_hasta;
    }

    if (in_array($estado_f, $estados_validos, true)) {
        $where[] = "v.estado = ?";
        $types .= "s";
        $params[] = $estado_f;
    }

    // Los cancelados operativos siguen disponibles al filtrar por ese estado.
    // El listado normal solo muestra viajes con al menos un tramo activo.
    if ($estado_f !== "cancelado") {
        $where[] = "EXISTS (
            SELECT 1
              FROM viaje_tramos vt_activos
             WHERE vt_activos.viaje_id = v.id
               AND vt_activos.estado <> 'cancelado'
               AND vt_activos.eliminado_at IS NULL
        )";
    }

    $where_sql = implode(" AND ", $where);

    $sql_viajes = "
        SELECT
            v.id                       AS viaje_id,
            v.cliente_id,
            c.nombre                   AS cliente,
            v.unidad_id,
            u.economico                AS unidad,
            u.placas,
            u.operador,
            u.telefonos                AS telefono,
            u.equipos                  AS id_equipos,
            v.folio,
            v.fecha_inicio,
            v.fecha_fin,
            v.estado,
            v.notas,
            v.created_by_usuario_id,
            CASE
              WHEN v.created_at > DATE_ADD(NOW(), INTERVAL 2 MINUTE)
              THEN DATE_SUB(v.created_at, INTERVAL 6 HOUR)
              ELSE v.created_at
            END AS created_at,
            CASE
              WHEN v.updated_at > DATE_ADD(NOW(), INTERVAL 2 MINUTE)
              THEN DATE_SUB(v.updated_at, INTERVAL 6 HOUR)
              ELSE v.updated_at
            END AS updated_at,
            COUNT(vt.id)               AS total_tramos,
            SUM(vt.estado = 'completado') AS tramos_completados,
            MIN(vt.salida_patio)       AS primera_salida,
            MAX(vt.descarga_programada) AS ultima_descarga
        FROM viajes v
        INNER JOIN clientes c ON c.id = v.cliente_id
        INNER JOIN unidades u ON u.id = v.unidad_id
        LEFT JOIN viaje_tramos vt
          ON vt.viaje_id = v.id
         AND vt.estado <> 'cancelado'
         AND vt.eliminado_at IS NULL
        WHERE $where_sql
        GROUP BY v.id
        ORDER BY v.fecha_inicio DESC, v.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $types_pag = $types . "ii";
    $params_pag = array_merge($params, [$limit, $offset]);

    $stmt = $conn->prepare($sql_viajes);
    if (!$stmt) {
        throw new Exception(
            "Error preparando consulta de viajes: " . $conn->error,
        );
    }

    if (count($params_pag)) {
        $refs = [$types_pag];
        $copy = $params_pag;
        foreach ($copy as $k => $v) {
            $refs[] = &$copy[$k];
        }
        call_user_func_array([$stmt, "bind_param"], $refs);
    }

    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando consulta: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $viajes = [];
    $viaje_ids = [];

    while ($row = $result->fetch_assoc()) {
        $vid = (int) $row["viaje_id"];
        $viaje_ids[] = $vid;

        $es_multi_dia =
            $row["fecha_fin"] !== null &&
            $row["fecha_fin"] !== $row["fecha_inicio"];

        $viajes[$vid] = [
            "viaje_id" => $vid,
            "cliente_id" => (int) $row["cliente_id"],
            "cliente" => (string) $row["cliente"],
            "unidad_id" => (int) $row["unidad_id"],
            "unidad" => (string) $row["unidad"],
            "placas" => (string) $row["placas"],
            "operador" => (string) $row["operador"],
            "telefono" => (string) $row["telefono"],
            "id_equipos" => (string) $row["id_equipos"],
            "folio" => (string) $row["folio"],
            "fecha_inicio" => (string) $row["fecha_inicio"],
            "fecha_fin" => $row["fecha_fin"]
                ? (string) $row["fecha_fin"]
                : null,
            "es_multi_dia" => $es_multi_dia,
            "estado" => (string) $row["estado"],
            "notas" => $row["notas"] ? (string) $row["notas"] : null,
            "total_tramos" => (int) $row["total_tramos"],
            "tramos_completados" => (int) $row["tramos_completados"],
            "primera_salida" => $row["primera_salida"]
                ? (string) $row["primera_salida"]
                : null,
            "ultima_descarga" => $row["ultima_descarga"]
                ? (string) $row["ultima_descarga"]
                : null,
            "created_at" => (string) $row["created_at"],
            "updated_at" => (string) $row["updated_at"],
            "tramos" => [],
        ];
    }
    $stmt->close();

    if ($con_tramos && count($viaje_ids) > 0) {
        $ph_v = implode(",", array_fill(0, count($viaje_ids), "?"));
        $has_regreso_origen = db_column_exists_v(
            $conn,
            "viaje_tramos",
            "requiere_regreso_origen",
        );
        $select_regreso_origen = $has_regreso_origen
            ? "vt.requiere_regreso_origen,
                vt.regreso_origen_programado,
                vt.regreso_origen_real,"
            : "0 AS requiere_regreso_origen,
                NULL AS regreso_origen_programado,
                NULL AS regreso_origen_real,";
        $sql_t = "
            SELECT
                vt.id, vt.viaje_id, vt.tramo_numero,
                d_match.id AS despacho_id,
                vt.origen, vt.lugar_carga, vt.destino, vt.ruta,
                vt.instrucciones,
                vt.operador_monitoreo,
                vt.gps_estado, vt.gps_timestamp,
                vt.salida_patio,      vt.salida_patio_real,
                vt.cita_carga,        vt.cita_carga_real,
                vt.salida_carga,      vt.salida_carga_real,
                vt.descarga_programada, vt.descarga_real, vt.vacio_real,
                $select_regreso_origen
                vt.estado, vt.created_at, vt.updated_at
            FROM viaje_tramos vt
            INNER JOIN viajes v_match
              ON v_match.id = vt.viaje_id
            LEFT JOIN despachos d_match
              ON d_match.cliente_id = v_match.cliente_id
             AND d_match.unidad_id = v_match.unidad_id
             AND d_match.folio = v_match.folio
             AND d_match.tramo_numero = vt.tramo_numero
             AND d_match.fecha_programada = DATE(
               COALESCE(vt.salida_patio, v_match.fecha_inicio)
             )
            WHERE vt.viaje_id IN ($ph_v)
              AND vt.estado <> 'cancelado'
              AND vt.eliminado_at IS NULL
            ORDER BY vt.viaje_id ASC, vt.tramo_numero ASC
        ";

        $stmt_t = $conn->prepare($sql_t);
        if (!$stmt_t) {
            throw new Exception(
                "Error preparando consulta de tramos: " . $conn->error,
            );
        }

        $refs_t = [str_repeat("i", count($viaje_ids))];
        $copy_ids = $viaje_ids;
        foreach ($copy_ids as $k => $v) {
            $refs_t[] = &$copy_ids[$k];
        }
        call_user_func_array([$stmt_t, "bind_param"], $refs_t);

        if (!$stmt_t->execute()) {
            throw new Exception(
                "Error ejecutando consulta de tramos: " . $stmt_t->error,
            );
        }

        $res_t = $stmt_t->get_result();
        while ($t = $res_t->fetch_assoc()) {
            $vid = (int) $t["viaje_id"];
            if (!isset($viajes[$vid])) {
                continue;
            }
            $viajes[$vid]["tramos"][] = [
                "id" => (int) $t["id"],
                "viaje_id" => $vid,
                "tramo_numero" => (int) $t["tramo_numero"],
                "despacho_id" => $t["despacho_id"] !== null
                    ? (int) $t["despacho_id"]
                    : null,
                "origen" => (string) $t["origen"],
                "lugar_carga" => (string) $t["lugar_carga"],
                "destino" => (string) $t["destino"],
                "ruta" => (string) $t["ruta"],
                "instrucciones" => (string) $t["instrucciones"],
                "operador_monitoreo" => $t["operador_monitoreo"]
                    ? (string) $t["operador_monitoreo"]
                    : null,
                "gps_estado" => $t["gps_estado"]
                    ? (string) $t["gps_estado"]
                    : null,
                "gps_timestamp" => $t["gps_timestamp"]
                    ? (string) $t["gps_timestamp"]
                    : null,
                "salida_patio" => $t["salida_patio"]
                    ? (string) $t["salida_patio"]
                    : null,
                "salida_patio_real" => $t["salida_patio_real"]
                    ? (string) $t["salida_patio_real"]
                    : null,
                "cita_carga" => $t["cita_carga"]
                    ? (string) $t["cita_carga"]
                    : null,
                "cita_carga_real" => $t["cita_carga_real"]
                    ? (string) $t["cita_carga_real"]
                    : null,
                "salida_carga" => $t["salida_carga"]
                    ? (string) $t["salida_carga"]
                    : null,
                "salida_carga_real" => $t["salida_carga_real"]
                    ? (string) $t["salida_carga_real"]
                    : null,
                "descarga_programada" => $t["descarga_programada"]
                    ? (string) $t["descarga_programada"]
                    : null,
                "descarga_real" => $t["descarga_real"]
                    ? (string) $t["descarga_real"]
                    : null,
                "vacio_real" => $t["vacio_real"]
                    ? (string) $t["vacio_real"]
                    : null,
                "requiere_regreso_origen" =>
                    (int) ($t["requiere_regreso_origen"] ?? 0),
                "regreso_origen_programado" => $t["regreso_origen_programado"]
                    ? (string) $t["regreso_origen_programado"]
                    : null,
                "regreso_origen_real" => $t["regreso_origen_real"]
                    ? (string) $t["regreso_origen_real"]
                    : null,
                "estado" => (string) $t["estado"],
                // Enriquecimiento desde el catálogo de rutas (se llena más abajo)
                "veces_usada" => 0,
                "es_ruta_frecuente" => false,
                "es_favorito" => false,
                "incidencias" => [],
            ];
        }
        $stmt_t->close();

        $cliente_ids_pagina = [];
        foreach ($viajes as $vw) {
            $cliente_ids_pagina[(int) $vw["cliente_id"]] = true;
        }
        $cliente_ids_pagina = array_keys($cliente_ids_pagina);

        if (count($cliente_ids_pagina) > 0) {
            $ph_c = implode(
                ",",
                array_fill(0, count($cliente_ids_pagina), "?"),
            );
            $sql_cat = "
                SELECT cliente_id, firma_ruta, veces_usada, es_favorito
                  FROM tramos_catalogo
                 WHERE activo = 1
                   AND firma_ruta IS NOT NULL
                   AND cliente_id IN ($ph_c)
            ";
            $stmt_cat = $conn->prepare($sql_cat);
            if ($stmt_cat) {
                $refs_cat = [str_repeat("i", count($cliente_ids_pagina))];
                $copy_cat = $cliente_ids_pagina;
                foreach ($copy_cat as $k => $v) {
                    $refs_cat[] = &$copy_cat[$k];
                }
                call_user_func_array([$stmt_cat, "bind_param"], $refs_cat);
                $stmt_cat->execute();
                $res_cat = $stmt_cat->get_result();

                $catalogo = [];
                while ($cr = $res_cat->fetch_assoc()) {
                    $key = (int) $cr["cliente_id"] . "|" . $cr["firma_ruta"];
                    $catalogo[$key] = [
                        "veces_usada" => (int) $cr["veces_usada"],
                        "es_favorito" => (int) $cr["es_favorito"] === 1,
                    ];
                }
                $stmt_cat->close();

                foreach ($viajes as $vid => &$vref) {
                    $cid = (int) $vref["cliente_id"];
                    foreach ($vref["tramos"] as &$tref) {
                        $firma = firma_ruta_v(
                            $tref["origen"],
                            $tref["lugar_carga"],
                            $tref["destino"],
                        );
                        if ($firma === null) {
                            continue;
                        }
                        $key = $cid . "|" . $firma;
                        if (isset($catalogo[$key])) {
                            $tref["veces_usada"] =
                                $catalogo[$key]["veces_usada"];
                            $tref["es_favorito"] =
                                $catalogo[$key]["es_favorito"];
                            $tref["es_ruta_frecuente"] =
                                $catalogo[$key]["veces_usada"] >=
                                RUTA_FRECUENTE_MIN_USOS;
                        }
                    }
                    unset($tref);
                }
                unset($vref);
            }
        }

        if (count($viaje_ids) > 0) {
            $ph_inc = implode(",", array_fill(0, count($viaje_ids), "?"));
            $sql_inc = "
                SELECT id, viaje_id, tramo_id, tipo, severidad, fecha, direccion, notas, created_at
                  FROM viaje_incidencias
                 WHERE viaje_id IN ($ph_inc)
                 ORDER BY tramo_id ASC, fecha ASC
            ";
            $stmt_inc = $conn->prepare($sql_inc);
            if ($stmt_inc) {
                $refs_inc = [str_repeat("i", count($viaje_ids))];
                $copy_inc = $viaje_ids;
                foreach ($copy_inc as $k => $v) {
                    $refs_inc[] = &$copy_inc[$k];
                }
                call_user_func_array([$stmt_inc, "bind_param"], $refs_inc);
                $stmt_inc->execute();
                $res_inc = $stmt_inc->get_result();
                while ($inc = $res_inc->fetch_assoc()) {
                    $vid = (int) $inc["viaje_id"];
                    $tid = (int) $inc["tramo_id"];
                    if (!isset($viajes[$vid])) {
                        continue;
                    }
                    foreach ($viajes[$vid]["tramos"] as &$tramo) {
                        if ($tramo["id"] === $tid) {
                            $tramo["incidencias"][] = [
                                "id" => (int) $inc["id"],
                                "tramo_id" => $tid,
                                "tramo_numero" => (int) $tramo["tramo_numero"],
                                "tramo_origen" => (string) $tramo["origen"],
                                "tramo_destino" => (string) $tramo["destino"],
                                "ruta_tramo" => (string) $tramo["ruta"],
                                "tipo" => (string) $inc["tipo"],
                                "severidad" => (string) $inc["severidad"],
                                "fecha" => (string) $inc["fecha"],
                                "direccion" => $inc["direccion"]
                                    ? (string) $inc["direccion"]
                                    : null,
                                "notas" => $inc["notas"]
                                    ? (string) $inc["notas"]
                                    : null,
                                "created_at" => (string) $inc["created_at"],
                            ];
                            break;
                        }
                    }
                    unset($tramo);
                }
                $stmt_inc->close();
            }
        }
    }

    $sql_count = "
        SELECT COUNT(DISTINCT v.id) AS total
        FROM viajes v
        INNER JOIN clientes c ON c.id = v.cliente_id
        INNER JOIN unidades u ON u.id = v.unidad_id
        WHERE $where_sql
    ";

    $stmt_c = $conn->prepare($sql_count);
    if (!$stmt_c) {
        throw new Exception("Error preparando conteo: " . $conn->error);
    }

    if (count($params)) {
        $refs_c = [$types];
        $copy_c = $params;
        foreach ($copy_c as $k => $v) {
            $refs_c[] = &$copy_c[$k];
        }
        call_user_func_array([$stmt_c, "bind_param"], $refs_c);
    }

    $stmt_c->execute();
    $total = (int) $stmt_c->get_result()->fetch_assoc()["total"];
    $stmt_c->close();

    $response["success"] = true;
    $response["message"] = "Viajes obtenidos correctamente";
    $response["data"] = array_values($viajes);
    $response["count"] = count($viajes);
    $response["total"] = $total;
    $response["limit"] = $limit;
    $response["offset"] = $offset;
} catch (Exception $e) {
    http_response_code(500);
    $response["success"] = false;
    $response["message"] = "Error: " . $e->getMessage();
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
