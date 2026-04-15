<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/_helpers.php';

function parse_ruta_marker($observaciones)
{
    $text = (string) $observaciones;
    if ($text === '') {
        return [null, null];
    }

    if (!preg_match('/\[RUTA:([^\]|]+)(?:\|([^\]]+))?\]/', $text, $m)) {
        return [null, null];
    }

    $codigo = isset($m[1]) ? trim((string) $m[1]) : null;
    $nombre = isset($m[2]) ? trim((string) $m[2]) : null;
    if ($codigo === '') {
        $codigo = null;
    }
    if ($nombre === '') {
        $nombre = null;
    }

    return [$codigo, $nombre];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Metodo no permitido. Use GET.');
    }

    $codigo_filter = isset($_GET['codigo_ruta']) ? strtoupper(trim((string) $_GET['codigo_ruta'])) : '';

    $sql = "SELECT unidad, folio, fecha_programada, observaciones
            FROM seguimiento_despacho
            WHERE observaciones LIKE '%[RUTA:%'";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    $agg = [];
    foreach ($rows as $r) {
        list($codigo, $nombre) = parse_ruta_marker($r['observaciones'] ?? '');
        if (!$codigo) {
            continue;
        }

        $codigo = strtoupper($codigo);
        if ($codigo_filter !== '' && $codigo !== $codigo_filter) {
            continue;
        }

        if (!isset($agg[$codigo])) {
            $agg[$codigo] = [
                'codigo_ruta' => $codigo,
                'nombre_ruta' => $nombre ?: '',
                'usos_total' => 0,
                'ultimo_uso' => '',
                'unidades' => [],
            ];
        }

        if ($agg[$codigo]['nombre_ruta'] === '' && $nombre) {
            $agg[$codigo]['nombre_ruta'] = $nombre;
        }

        $agg[$codigo]['usos_total']++;

        $fecha = isset($r['fecha_programada']) ? trim((string) $r['fecha_programada']) : '';
        if ($fecha !== '' && ($agg[$codigo]['ultimo_uso'] === '' || $fecha > $agg[$codigo]['ultimo_uso'])) {
            $agg[$codigo]['ultimo_uso'] = $fecha;
        }

        $unidad = isset($r['unidad']) ? trim((string) $r['unidad']) : '';
        if ($unidad !== '') {
            if (!isset($agg[$codigo]['unidades'][$unidad])) {
                $agg[$codigo]['unidades'][$unidad] = [
                    'unidad' => $unidad,
                    'usos' => 0,
                    'ultimo_uso' => '',
                ];
            }
            $agg[$codigo]['unidades'][$unidad]['usos']++;
            if ($fecha !== '' && ($agg[$codigo]['unidades'][$unidad]['ultimo_uso'] === '' || $fecha > $agg[$codigo]['unidades'][$unidad]['ultimo_uso'])) {
                $agg[$codigo]['unidades'][$unidad]['ultimo_uso'] = $fecha;
            }
        }
    }

    $data = [];
    foreach ($agg as $item) {
        $units = array_values($item['unidades']);
        usort($units, function ($a, $b) {
            if (intval($b['usos']) !== intval($a['usos'])) {
                return intval($b['usos']) <=> intval($a['usos']);
            }
            return strcmp((string) $a['unidad'], (string) $b['unidad']);
        });

        $item['unidades'] = $units;
        $item['unidades_count'] = count($units);
        $data[] = $item;
    }

    usort($data, function ($a, $b) {
        if (intval($b['usos_total']) !== intval($a['usos_total'])) {
            return intval($b['usos_total']) <=> intval($a['usos_total']);
        }
        return strcmp((string) $a['codigo_ruta'], (string) $b['codigo_ruta']);
    });

    rutas_json_response(true, 'Uso de plantillas obtenido correctamente.', [
        'data' => $data,
        'count' => count($data),
    ]);
} catch (Exception $e) {
    rutas_json_response(false, 'Error: ' . $e->getMessage(), [
        'data' => [],
        'count' => 0,
    ], 400);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
