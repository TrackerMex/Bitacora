<?php

error_reporting(0);
ini_set("display_errors", 0);
ini_set("memory_limit", "256M");
ini_set("max_execution_time", 30);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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

    $where = ["c.activo = 1", "u.activo = 1"];
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
            v.created_at,
            v.updated_at,
            COUNT(vt.id)               AS total_tramos,
            SUM(vt.estado = 'completado') AS tramos_completados,
            MIN(vt.salida_patio)       AS primera_salida,
            MAX(vt.descarga_programada) AS ultima_descarga
        FROM viajes v
        INNER JOIN clientes c ON c.id = v.cliente_id
        INNER JOIN unidades u ON u.id = v.unidad_id
        LEFT JOIN viaje_tramos vt ON vt.viaje_id = v.id AND vt.estado != 'cancelado'
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
        $sql_t = "
            SELECT
                vt.id, vt.viaje_id, vt.tramo_numero,
                vt.origen, vt.lugar_carga, vt.destino, vt.ruta,
                vt.instrucciones, vt.salida_patio, vt.cita_carga,
                vt.salida_carga, vt.descarga_programada, vt.estado,
                vt.created_at, vt.updated_at
            FROM viaje_tramos vt
            WHERE vt.viaje_id IN ($ph_v)
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
                "origen" => (string) $t["origen"],
                "lugar_carga" => (string) $t["lugar_carga"],
                "destino" => (string) $t["destino"],
                "ruta" => (string) $t["ruta"],
                "instrucciones" => (string) $t["instrucciones"],
                "salida_patio" => $t["salida_patio"]
                    ? (string) $t["salida_patio"]
                    : null,
                "cita_carga" => $t["cita_carga"]
                    ? (string) $t["cita_carga"]
                    : null,
                "salida_carga" => $t["salida_carga"]
                    ? (string) $t["salida_carga"]
                    : null,
                "descarga_programada" => $t["descarga_programada"]
                    ? (string) $t["descarga_programada"]
                    : null,
                "estado" => (string) $t["estado"],
            ];
        }
        $stmt_t->close();
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
