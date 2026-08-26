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

function resp_err($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(
        ["ok" => false, "error" => $message],
        JSON_UNESCAPED_UNICODE,
    );
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
    } catch (Exception $jwt_error) {
        resp_err("Sesión requerida o expirada.", 401);
    }

    $email = strtolower(trim((string) ($payload["email"] ?? "")));
    if ($email === "") {
        resp_err("Token inválido.", 401);
    }

    require_once __DIR__ . "/../db/db.php";

    $stmt = $conn->prepare(
        "SELECT id, role FROM usuarios WHERE LOWER(email) = ? AND activo = 1 LIMIT 1",
    );
    if (!$stmt) {
        throw new Exception("Error preparando usuario: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        resp_err("Usuario no encontrado o inactivo.", 401);
    }
    if (strtolower((string) $user["role"]) !== "admin") {
        resp_err("Esta acción requiere rol administrador.", 403);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) {
        resp_err("JSON inválido.", 400);
    }

    $viaje_id = (int) ($data["viaje_id"] ?? 0);
    $motivo = trim((string) ($data["motivo"] ?? ""));
    $motivo_length = function_exists("mb_strlen")
        ? mb_strlen($motivo, "UTF-8")
        : strlen($motivo);
    $tramo_ids_raw = $data["tramo_ids"] ?? [];

    if ($viaje_id <= 0) {
        resp_err("viaje_id requerido.", 400);
    }
    if ($motivo_length < 10 || $motivo_length > 500) {
        resp_err("El motivo debe tener entre 10 y 500 caracteres.", 400);
    }
    if (!is_array($tramo_ids_raw)) {
        resp_err("La selección de tramos es inválida.", 400);
    }

    $tramo_ids = [];
    foreach ($tramo_ids_raw as $tramo_id_raw) {
        $tramo_id = (int) $tramo_id_raw;
        if ($tramo_id > 0) {
            $tramo_ids[$tramo_id] = $tramo_id;
        }
    }
    $tramo_ids = array_values($tramo_ids);
    if (count($tramo_ids) === 0) {
        resp_err("Selecciona al menos un tramo para reabrir.", 400);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT id, estado FROM viajes WHERE id = ? LIMIT 1 FOR UPDATE",
        );
        if (!$stmt) {
            throw new Exception("Error preparando viaje: " . $conn->error);
        }
        $stmt->bind_param("i", $viaje_id);
        $stmt->execute();
        $viaje = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$viaje) {
            throw new Exception("Viaje no encontrado.", 404);
        }
        if ((string) $viaje["estado"] !== "completado") {
            throw new Exception(
                "Solo se pueden reabrir viajes completados.",
                409,
            );
        }

        $placeholders = implode(",", array_fill(0, count($tramo_ids), "?"));
        $types = str_repeat("i", count($tramo_ids) + 1);
        $params = array_merge([$viaje_id], $tramo_ids);
        $stmt = $conn->prepare(
            "SELECT id FROM viaje_tramos
              WHERE viaje_id = ? AND id IN ($placeholders)
                AND estado = 'completado' AND eliminado_at IS NULL
              FOR UPDATE",
        );
        if (!$stmt) {
            throw new Exception("Error preparando tramos: " . $conn->error);
        }
        $refs = [$types];
        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }
        call_user_func_array([$stmt, "bind_param"], $refs);
        $stmt->execute();
        $result = $stmt->get_result();
        $tramos_validos = [];
        while ($row = $result->fetch_assoc()) {
            $tramos_validos[] = (int) $row["id"];
        }
        $stmt->close();

        sort($tramos_validos);
        $esperados = $tramo_ids;
        sort($esperados);
        if ($tramos_validos !== $esperados) {
            throw new Exception(
                "Uno o más tramos ya no están completados o no pertenecen al viaje.",
                409,
            );
        }

        $update_placeholders = implode(
            ",",
            array_fill(0, count($tramos_validos), "?"),
        );
        $update_types = str_repeat("i", count($tramos_validos));
        $update_params = $tramos_validos;
        $stmt = $conn->prepare(
            "UPDATE viaje_tramos SET estado = 'en_curso'
              WHERE id IN ($update_placeholders)",
        );
        if (!$stmt) {
            throw new Exception(
                "Error preparando reapertura de tramos: " . $conn->error,
            );
        }
        $refs = [$update_types];
        foreach ($update_params as $key => $value) {
            $refs[] = &$update_params[$key];
        }
        call_user_func_array([$stmt, "bind_param"], $refs);
        if (!$stmt->execute()) {
            throw new Exception("Error reabriendo tramos: " . $stmt->error);
        }
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE viajes SET estado = 'en_curso' WHERE id = ?",
        );
        if (!$stmt) {
            throw new Exception(
                "Error preparando reapertura del viaje: " . $conn->error,
            );
        }
        $stmt->bind_param("i", $viaje_id);
        if (!$stmt->execute()) {
            throw new Exception("Error reabriendo viaje: " . $stmt->error);
        }
        $stmt->close();

        $tramo_ids_json = json_encode(
            $tramos_validos,
            JSON_UNESCAPED_UNICODE,
        );
        if ($tramo_ids_json === false) {
            throw new Exception("No se pudo preparar la auditoría.");
        }
        $usuario_id = (int) $user["id"];
        $estado_previo = (string) $viaje["estado"];
        $stmt = $conn->prepare(
            "INSERT INTO viaje_reaperturas
              (viaje_id, usuario_id, estado_previo, motivo, tramo_ids)
             VALUES (?, ?, ?, ?, ?)",
        );
        if (!$stmt) {
            throw new Exception(
                "El historial de reaperturas no está instalado. Aplica la migración 2026-08-26_reaperturas_viaje.sql.",
                503,
            );
        }
        $stmt->bind_param(
            "iisss",
            $viaje_id,
            $usuario_id,
            $estado_previo,
            $motivo,
            $tramo_ids_json,
        );
        if (!$stmt->execute()) {
            throw new Exception(
                "Error registrando la reapertura: " . $stmt->error,
            );
        }
        $reapertura_id = (int) $stmt->insert_id;
        $stmt->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    resp_ok([
        "message" => "Viaje reabierto correctamente.",
        "reapertura_id" => $reapertura_id,
        "viaje_id" => $viaje_id,
        "estado" => "en_curso",
        "tramo_ids" => $tramos_validos,
    ]);
} catch (Exception $e) {
    $code = (int) $e->getCode();
    if ($code < 400 || $code > 599) {
        $code = 500;
    }
    error_log("[reabrir_viaje] ERROR: " . $e->getMessage());
    resp_err("Error: " . $e->getMessage(), $code);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
