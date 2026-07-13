<?php

error_reporting(0);
ini_set("display_errors", 0);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$response = [
    "success" => false,
    "message" => "",
    "data" => [],
    "count" => 0,
];

function bind_params_historial($stmt, $types, &$params)
{
    if ($types === "") {
        return;
    }
    $refs = [$types];
    foreach ($params as $k => $v) {
        $refs[] = &$params[$k];
    }
    call_user_func_array([$stmt, "bind_param"], $refs);
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        throw new Exception("Método no permitido. Use GET.");
    }

    require_once __DIR__ . "/../db/db.php";

    $cliente_id = intval($_GET["cliente_id"] ?? 0);
    if ($cliente_id <= 0) {
        throw new Exception("cliente_id requerido");
    }

    $q = trim((string) ($_GET["q"] ?? ""));
    $limit = min(max(intval($_GET["limit"] ?? 10), 1), 10);
    $like = "%" . mb_strtolower($q, "UTF-8") . "%";

    $where = [
        "v.cliente_id = ?",
        "(vt.estado IS NULL OR vt.estado <> 'cancelado')",
        "(NULLIF(TRIM(COALESCE(vt.ruta, '')), '') IS NOT NULL
          OR NULLIF(TRIM(COALESCE(vt.origen, '')), '') IS NOT NULL
          OR NULLIF(TRIM(COALESCE(vt.lugar_carga, '')), '') IS NOT NULL
          OR NULLIF(TRIM(COALESCE(vt.destino, '')), '') IS NOT NULL)",
    ];
    $types = "i";
    $params = [$cliente_id];
    $priority_sql = "9";

    if ($q !== "") {
        $priority_sql = "CASE
            WHEN LOWER(COALESCE(vt.ruta, '')) LIKE ? THEN 0
            WHEN LOWER(COALESCE(vt.origen, '')) LIKE ? THEN 1
            WHEN LOWER(COALESCE(vt.lugar_carga, '')) LIKE ? THEN 2
            WHEN LOWER(COALESCE(vt.destino, '')) LIKE ? THEN 3
            ELSE 4
        END";
        $types .= "ssss";
        array_push($params, $like, $like, $like, $like);

        $where[] = "(
            LOWER(COALESCE(vt.ruta, '')) LIKE ?
            OR LOWER(COALESCE(vt.origen, '')) LIKE ?
            OR LOWER(COALESCE(vt.lugar_carga, '')) LIKE ?
            OR LOWER(COALESCE(vt.destino, '')) LIKE ?
        )";
        $types .= "ssss";
        array_push($params, $like, $like, $like, $like);
    }

    $where_sql = implode(" AND ", $where);
    $sql = "
        SELECT
            COALESCE(vt.ruta, '') AS ruta,
            COALESCE(vt.origen, '') AS origen,
            COALESCE(vt.lugar_carga, '') AS lugar_carga,
            COALESCE(vt.destino, '') AS destino,
            COUNT(*) AS veces_usada,
            MAX(COALESCE(vt.updated_at, vt.created_at, v.updated_at, v.created_at)) AS ultima_vez_usada,
            MIN($priority_sql) AS prioridad
        FROM viaje_tramos vt
        INNER JOIN viajes v ON v.id = vt.viaje_id
        WHERE $where_sql
        GROUP BY
            COALESCE(vt.ruta, ''),
            COALESCE(vt.origen, ''),
            COALESCE(vt.lugar_carga, ''),
            COALESCE(vt.destino, '')
        ORDER BY prioridad ASC, veces_usada DESC, ultima_vez_usada DESC
        LIMIT ?
    ";
    $types .= "i";
    $params[] = $limit;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparando búsqueda de rutas: " . $conn->error);
    }
    bind_params_historial($stmt, $types, $params);

    if (!$stmt->execute()) {
        throw new Exception("Error buscando rutas: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $rutas = [];
    while ($row = $result->fetch_assoc()) {
        $ruta = trim((string) $row["ruta"]);
        $origen = trim((string) $row["origen"]);
        $lugar_carga = trim((string) $row["lugar_carga"]);
        $destino = trim((string) $row["destino"]);
        $recorrido = trim(($origen ?: "Origen") . " -> " . ($destino ?: "Destino"));

        $rutas[] = [
            "ruta" => $ruta,
            "origen" => $origen,
            "lugar_carga" => $lugar_carga,
            "destino" => $destino,
            "veces_usada" => intval($row["veces_usada"]),
            "ultima_vez_usada" => $row["ultima_vez_usada"],
            "etiqueta" => $ruta !== "" ? $ruta . " · " . $recorrido : $recorrido,
        ];
    }
    $stmt->close();

    $response["success"] = true;
    $response["message"] = "Rutas encontradas";
    $response["data"] = $rutas;
    $response["count"] = count($rutas);
} catch (Exception $e) {
    http_response_code(500);
    $response["success"] = false;
    $response["message"] = $e->getMessage();
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
