<?php

function rutas_json_response($success, $message, $extra = [], $http_code = null)
{
    if ($http_code !== null) {
        http_response_code(intval($http_code));
    }

    $response = [
        'success' => (bool) $success,
        'message' => (string) $message,
    ];

    if (is_array($extra)) {
        foreach ($extra as $key => $value) {
            $response[$key] = $value;
        }
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

function rutas_get_json_body()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    if (!empty($_POST) && is_array($_POST)) {
        return $_POST;
    }
    return [];
}

function rutas_normalize_text($value)
{
    $v = trim((string) $value);
    $map = [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ñ' => 'N',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n',
    ];

    $v = strtr($v, $map);
    $v = preg_replace('/\s+/', ' ', $v);
    return trim($v);
}

function rutas_country_code($pais)
{
    $p = strtoupper(rutas_normalize_text($pais));
    $p = preg_replace('/[^A-Z0-9]/', '', $p);
    if ($p === '') {
        return 'XX';
    }
    if (strlen($p) === 1) {
        return $p . 'X';
    }
    return substr($p, 0, 2);
}

function rutas_city_segment($ciudad)
{
    $c = strtoupper(rutas_normalize_text($ciudad));
    $c = preg_replace('/[^A-Z0-9 ]/', '', $c);
    $c = preg_replace('/\s+/', '-', trim($c));
    $c = trim($c, '-');
    if ($c === '') {
        return 'RUTA';
    }
    return substr($c, 0, 20);
}

function rutas_generate_codigo($conn, $pais_origen, $ciudad_origen)
{
    $country = rutas_country_code($pais_origen);
    $city = rutas_city_segment($ciudad_origen);
    $prefix = $country . '-' . $city;
    $like_prefix = $prefix . '-%';

    $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(codigo_ruta, '-', -1) AS UNSIGNED)) AS max_seq
            FROM rutas_fijas
            WHERE codigo_ruta LIKE ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparando consulta de codigo: ' . $conn->error);
    }
    $stmt->bind_param('s', $like_prefix);
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta de codigo: ' . $stmt->error);
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $next = 1;
    if ($row && isset($row['max_seq']) && $row['max_seq'] !== null) {
        $next = intval($row['max_seq']) + 1;
    }

    return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function rutas_validar_estado($estado)
{
    $v = trim((string) $estado);
    $allowed = ['activa', 'inactiva', 'pausada'];
    return in_array($v, $allowed, true) ? $v : 'activa';
}

function rutas_parse_secuencias($secuencias)
{
    if (!is_array($secuencias) || count($secuencias) === 0) {
        throw new Exception('Debe enviar al menos una secuencia en "secuencias".');
    }

    $items = [];
    $total_km = 0.0;
    $total_min = 0;
    $idx = 0;

    foreach ($secuencias as $sec) {
        $idx++;
        if (!is_array($sec)) {
            continue;
        }

        $origen = isset($sec['origen_municipio']) ? trim((string) $sec['origen_municipio']) : '';
        $destino = isset($sec['destino_municipio']) ? trim((string) $sec['destino_municipio']) : '';
        $distancia = floatval($sec['distancia_km'] ?? 0);
        $tiempo = intval($sec['tiempo_estimado_minutos'] ?? 0);
        $notas = isset($sec['notas']) ? trim((string) $sec['notas']) : null;
        $numero = intval($sec['numero_secuencia'] ?? $idx);
        if ($numero <= 0) {
            $numero = $idx;
        }

        if ($origen === '' || $destino === '') {
            throw new Exception('Cada secuencia requiere origen_municipio y destino_municipio.');
        }
        if ($distancia < 0) {
            throw new Exception('distancia_km no puede ser negativa.');
        }
        if ($tiempo < 0) {
            throw new Exception('tiempo_estimado_minutos no puede ser negativo.');
        }

        $total_km += $distancia;
        $total_min += $tiempo;

        $items[] = [
            'numero_secuencia' => $numero,
            'origen_municipio' => $origen,
            'destino_municipio' => $destino,
            'distancia_km' => round($distancia, 2),
            'tiempo_estimado_minutos' => $tiempo,
            'notas' => $notas,
        ];
    }

    usort($items, function ($a, $b) {
        return intval($a['numero_secuencia']) <=> intval($b['numero_secuencia']);
    });

    if (count($items) === 0) {
        throw new Exception('No se encontraron secuencias validas.');
    }

    for ($i = 0; $i < count($items); $i++) {
        $items[$i]['numero_secuencia'] = $i + 1;
    }

    return [
        'items' => $items,
        'total_km' => round($total_km, 2),
        'total_min' => intval($total_min),
        'numero_paradas' => count($items),
    ];
}
