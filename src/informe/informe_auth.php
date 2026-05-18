<?php

require_once __DIR__ . '/../auth/jwt.php';

function informe_clean_string($value) {
    return trim((string)($value ?? ''));
}

function informes_column_exists($conn, $column) {
    $column = informe_clean_string($column);
    if ($column === '') {
        return false;
    }
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'informes_guardados'
            AND COLUMN_NAME = ?"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return intval($row['total'] ?? 0) > 0;
}

function informes_get_auth_user($conn, $required = true) {
    $token = get_bearer_token();
    if ($token === '') {
        if ($required) {
            throw new Exception('Sesion requerida', 401);
        }
        return null;
    }

    $payload = jwt_decode_payload($token);
    $email = strtolower(informe_clean_string($payload['email'] ?? ''));
    if ($email === '') {
        throw new Exception('Token invalido', 401);
    }

    $stmt = $conn->prepare('SELECT id, email, nombre, role, activo FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Error preparando usuario: ' . $conn->error, 500);
    }
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        throw new Exception('Error consultando usuario: ' . $stmt->error, 500);
    }
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$user || intval($user['activo']) !== 1) {
        throw new Exception('Usuario no autorizado', 401);
    }

    return [
        'id' => intval($user['id']),
        'email' => (string)$user['email'],
        'nombre' => (string)$user['nombre'],
        'role' => strtolower((string)$user['role'])
    ];
}

function informes_get_user_cliente_ids($conn, $usuario_id, $role) {
    if (strtolower((string)$role) === 'admin') {
        return [];
    }

    $stmt = $conn->prepare(
        'SELECT cliente_id FROM usuario_clientes WHERE usuario_id = ? ORDER BY cliente_id ASC'
    );
    if (!$stmt) {
        throw new Exception('Error preparando clientes de usuario: ' . $conn->error, 500);
    }
    $stmt->bind_param('i', $usuario_id);
    if (!$stmt->execute()) {
        throw new Exception('Error consultando clientes de usuario: ' . $stmt->error, 500);
    }
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $id = intval($row['cliente_id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $stmt->close();
    return array_values(array_unique($ids));
}

function informes_exception_code($e, $fallback = 500) {
    $code = intval($e->getCode());
    return ($code >= 400 && $code < 600) ? $code : $fallback;
}

?>
