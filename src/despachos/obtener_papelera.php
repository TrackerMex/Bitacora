<?php

error_reporting(0);
ini_set("display_errors", 0);
ini_set("memory_limit", "256M");
ini_set("max_execution_time", 30);

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

try {
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        throw new Exception("Método no permitido. Use GET.");
    }

    require_once __DIR__ . "/../auth/jwt.php";

    $token = get_bearer_token();
    if (!$token) {
        http_response_code(401);
        throw new Exception("Sesión requerida o expirada.");
    }
    try {
        $payload = jwt_decode_payload($token);
    } catch (Exception $jwtEx) {
        http_response_code(401);
        throw new Exception("Sesión requerida o expirada.");
    }

    $email = strtolower(trim($payload["email"] ?? ""));
    if ($email === "") {
        http_response_code(401);
        throw new Exception("Token inválido.");
    }

    require_once __DIR__ . "/../db/db.php";

    $stmt = $conn->prepare(
        "SELECT id, role FROM usuarios WHERE LOWER(email) = ? AND activo = 1 LIMIT 1",
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        http_response_code(401);
        throw new Exception("Usuario no encontrado o inactivo.");
    }
    if (strtolower($user["role"]) !== "admin") {
        http_response_code(403);
        throw new Exception("Solo el administrador puede ver la papelera.");
    }

    $sql = "SELECT
                v.id,
                v.folio,
                v.estado,
                v.fecha_inicio,
                v.fecha_fin,
                v.eliminado_at,
                v.eliminado_motivo,
                c.nombre  AS cliente,
                u.economico AS unidad,
                u.placas,
                u.operador,
                del.nombre AS eliminado_por_nombre,
                del.email  AS eliminado_por_email,
                (SELECT COUNT(*) FROM viaje_tramos vt WHERE vt.viaje_id = v.id) AS total_tramos
            FROM viajes v
            INNER JOIN clientes c ON c.id = v.cliente_id
            INNER JOIN unidades u ON u.id = v.unidad_id
            LEFT JOIN usuarios del ON del.id = v.eliminado_por_usuario_id
            WHERE v.eliminado_at IS NOT NULL
            ORDER BY v.eliminado_at DESC";

    $res = $conn->query($sql);
    if (!$res) {
        throw new Exception("Error consultando papelera: " . $conn->error);
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            "id" => (int) $r["id"],
            "folio" => (string) $r["folio"],
            "estado" => (string) $r["estado"],
            "fecha_inicio" => (string) ($r["fecha_inicio"] ?? ""),
            "fecha_fin" =>
                $r["fecha_fin"] !== null ? (string) $r["fecha_fin"] : "",
            "eliminado_at" => (string) $r["eliminado_at"],
            "eliminado_motivo" =>
                $r["eliminado_motivo"] !== null
                    ? (string) $r["eliminado_motivo"]
                    : "",
            "cliente" => (string) $r["cliente"],
            "unidad" => (string) $r["unidad"],
            "placas" => (string) ($r["placas"] ?? ""),
            "operador" => (string) ($r["operador"] ?? ""),
            "eliminado_por" => $r["eliminado_por_nombre"]
                ? (string) $r["eliminado_por_nombre"]
                : ((string) ($r["eliminado_por_email"] ?? "Desconocido")),
            "eliminado_por_email" => (string) ($r["eliminado_por_email"] ?? ""),
            "total_tramos" => (int) $r["total_tramos"],
        ];
    }
    $res->free();

    $response["success"] = true;
    $response["message"] = "Papelera obtenida correctamente";
    $response["data"] = $rows;
    $response["count"] = count($rows);
} catch (Exception $e) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    $response["success"] = false;
    $response["message"] = "Error: " . $e->getMessage();
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
