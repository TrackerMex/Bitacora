<?php

error_reporting(0);
ini_set("display_errors", 0);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

function resp_ok($data = [])
{
    echo json_encode(
        array_merge(["ok" => true], $data),
        JSON_UNESCAPED_UNICODE,
    );
    exit();
}

function resp_err($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $msg], JSON_UNESCAPED_UNICODE);
    exit();
}

require_once __DIR__ . "/../auth/jwt.php";

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        resp_err("Método no permitido. Use POST.", 405);
    }

    $token = get_bearer_token();
    if (!$token) {
        resp_err("Sesión requerida o expirada.", 401);
    }
    try {
        $payload = jwt_decode_payload($token);
    } catch (Exception $jwtEx) {
        resp_err("Sesión requerida o expirada.", 401);
    }

    $email = strtolower(trim($payload["email"] ?? ""));
    if ($email === "") {
        resp_err("Token inválido.", 401);
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
        resp_err("Usuario no encontrado o inactivo.", 401);
    }
    if (strtolower($user["role"]) === "lector") {
        resp_err("Sin permisos de escritura.", 403);
    }

    $usuario_id = (int) $user["id"];

    $body = json_decode(file_get_contents("php://input"), true);
    if (!is_array($body)) {
        resp_err("JSON inválido.", 400);
    }

    $viaje_id = (int) ($body["viaje_id"] ?? 0);
    $nuevo_estado = trim((string) ($body["estado"] ?? ""));

    $estados_validos = ["planificado", "en_curso", "completado", "cancelado"];

    if ($viaje_id <= 0) {
        resp_err("viaje_id requerido.", 400);
    }
    if (!in_array($nuevo_estado, $estados_validos, true)) {
        resp_err(
            "Estado inválido. Valores: " . implode(", ", $estados_validos),
            400,
        );
    }

    $stmt = $conn->prepare(
        "SELECT v.id, v.estado, v.cliente_id FROM viajes v WHERE v.id = ? LIMIT 1",
    );
    $stmt->bind_param("i", $viaje_id);
    $stmt->execute();
    $viaje = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$viaje) {
        resp_err("Viaje no encontrado.", 404);
    }

    if (strtolower($user["role"]) !== "admin") {
        $stmt = $conn->prepare(
            "SELECT 1 FROM usuario_clientes WHERE usuario_id = ? AND cliente_id = ? LIMIT 1",
        );
        $stmt->bind_param("ii", $usuario_id, $viaje["cliente_id"]);
        $stmt->execute();
        $acceso = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$acceso) {
            resp_err("Sin acceso a ese viaje.", 403);
        }
    }

    if ($viaje["estado"] === $nuevo_estado) {
        resp_ok([
            "viaje_id" => $viaje_id,
            "estado" => $nuevo_estado,
            "msg" => "El viaje ya estaba en ese estado.",
        ]);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE viajes SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $viaje_id);
        if (!$stmt->execute()) {
            throw new Exception("Error actualizando viaje: " . $stmt->error);
        }
        $stmt->close();

        if ($nuevo_estado === "completado") {
            $stmt = $conn->prepare(
                "UPDATE viaje_tramos SET estado = 'completado'
                  WHERE viaje_id = ? AND estado IN ('pendiente','en_curso')",
            );
            $stmt->bind_param("i", $viaje_id);
            $stmt->execute();
            $stmt->close();
        }

        if ($nuevo_estado === "cancelado") {
            $stmt = $conn->prepare(
                "UPDATE viaje_tramos SET estado = 'cancelado'
                  WHERE viaje_id = ? AND estado != 'completado'",
            );
            $stmt->bind_param("i", $viaje_id);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    resp_ok([
        "viaje_id" => $viaje_id,
        "estado" => $nuevo_estado,
        "estado_previo" => $viaje["estado"],
    ]);
} catch (Exception $e) {
    $debug = [
        "msg" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString(),
    ];
    error_log("[cambiar_estado_viaje] ERROR: " . json_encode($debug));
    resp_err("Error: " . $e->getMessage(), 500);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
