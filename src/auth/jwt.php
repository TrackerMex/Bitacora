<?php

require_once __DIR__ . '/../../config/environment.php';

function base64url_encode($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
  $padding = 4 - (strlen($data) % 4);
  if ($padding < 4) {
    $data .= str_repeat('=', $padding);
  }
  return base64_decode(strtr($data, '-_', '+/'));
}

function get_jwt_secret() {
  $secret = getEnvVar('JWT_SECRET', '');
  if ($secret === '') {
    $secret = getEnvVar('API_KEY', '');
  }
  if ($secret === '' || (ENVIRONMENT !== 'development' && strlen($secret) < 32)) {
    throw new Exception('JWT_SECRET no configurado correctamente', 500);
  }
  return $secret;
}

function jwt_encode_payload($payload, $ttl_seconds = 28800) {
  $now = time();
  $payload['iat'] = $now;
  $payload['nbf'] = $now;
  $payload['exp'] = $now + intval($ttl_seconds);

  $header = ['typ' => 'JWT', 'alg' => 'HS256'];
  $segments = [
    base64url_encode(json_encode($header, JSON_UNESCAPED_UNICODE)),
    base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE))
  ];
  $signature = hash_hmac('sha256', implode('.', $segments), get_jwt_secret(), true);
  $segments[] = base64url_encode($signature);
  return [implode('.', $segments), $payload['exp']];
}

function jwt_decode_payload($token) {
  $parts = explode('.', trim((string)$token));
  if (count($parts) !== 3) {
    throw new Exception('Token invalido', 401);
  }

  [$encoded_header, $encoded_payload, $encoded_signature] = $parts;
  $header = json_decode(base64url_decode($encoded_header), true);
  $payload = json_decode(base64url_decode($encoded_payload), true);
  if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256') {
    throw new Exception('Token invalido', 401);
  }

  $expected = base64url_encode(hash_hmac('sha256', $encoded_header . '.' . $encoded_payload, get_jwt_secret(), true));
  if (!hash_equals($expected, $encoded_signature)) {
    throw new Exception('Token invalido', 401);
  }

  $now = time();
  if (isset($payload['nbf']) && intval($payload['nbf']) > $now) {
    throw new Exception('Token aun no valido', 401);
  }
  if (!isset($payload['exp']) || intval($payload['exp']) < $now) {
    throw new Exception('Sesion expirada', 401);
  }

  return $payload;
}

function get_authorization_header() {
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    return $_SERVER['HTTP_AUTHORIZATION'];
  }
  if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
  }
  if (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    foreach ($headers as $key => $value) {
      if (strtolower($key) === 'authorization') {
        return $value;
      }
    }
  }
  return '';
}

function get_bearer_token() {
  $header = get_authorization_header();
  if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
    return trim($m[1]);
  }
  return '';
}

?>
