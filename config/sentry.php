<?php

if (!function_exists("getEnvVar")) {
    require_once __DIR__ . "/environment.php";
}

function sentry_is_enabled()
{
    return defined("SENTRY_ENABLED") && SENTRY_ENABLED;
}

function sentry_normalize_level($level)
{
    $level = strtolower(trim((string)$level));

    if ($level === "warning") {
        return "warn";
    }

    if (in_array($level, ["trace", "debug", "info", "warn", "error", "fatal"], true)) {
        return $level;
    }

    return "error";
}

function sentry_level_priority($level)
{
    $priorities = [
        "trace" => 10,
        "debug" => 20,
        "info" => 30,
        "warn" => 40,
        "error" => 50,
        "fatal" => 60,
    ];

    $level = sentry_normalize_level($level);
    return $priorities[$level] ?? $priorities["error"];
}

function sentry_filter_context($context)
{
    $sensitive_keys = [
        "password",
        "pass",
        "token",
        "authorization",
        "jwt",
        "secret",
        "api_key",
        "apikey",
        "dsn",
        "telefono",
        "phone",
        "email",
        "correo",
        "ubicacion",
        "gps",
        "lat",
        "lng",
        "longitud",
        "latitud",
    ];

    if (!is_array($context)) {
        return ["value" => (string)$context];
    }

    $filtered = [];

    foreach ($context as $key => $value) {
        $key_string = strtolower((string)$key);
        $is_sensitive = false;

        foreach ($sensitive_keys as $sensitive_key) {
            if (strpos($key_string, $sensitive_key) !== false) {
                $is_sensitive = true;
                break;
            }
        }

        if ($is_sensitive) {
            $filtered[$key] = "[Filtered]";
            continue;
        }

        if (is_array($value)) {
            $filtered[$key] = sentry_filter_context($value);
            continue;
        }

        if (is_object($value)) {
            $filtered[$key] = get_class($value);
            continue;
        }

        $filtered[$key] = $value;
    }

    return $filtered;
}

function sentry_capture_exception($exception, $context = [])
{
    if (!sentry_is_enabled() || !($exception instanceof Throwable)) {
        return null;
    }

    try {
        $context = sentry_filter_context($context);

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($context) {
            if (!empty($context)) {
                $scope->setContext("bitacora", $context);
            }
        });

        return \Sentry\captureException($exception);
    } catch (Throwable $ignored) {
        return null;
    }
}

function sentry_log($level, $message, $context = [])
{
    if (!sentry_is_enabled() || !getEnvBool("SENTRY_CAPTURE_LOGS", true)) {
        return null;
    }

    $level = sentry_normalize_level($level);
    $minimum_level = sentry_normalize_level(getEnvVar("SENTRY_LOG_LEVEL", "warning"));

    if (sentry_level_priority($level) < sentry_level_priority($minimum_level)) {
        return null;
    }

    try {
        $context = sentry_filter_context($context);
        $logger = \Sentry\logger();

        if ($level === "warn") {
            $logger->warn((string)$message, [], $context);
        } elseif (method_exists($logger, $level)) {
            $logger->{$level}((string)$message, [], $context);
        } else {
            $logger->error((string)$message, [], $context);
        }

        return null;
    } catch (Throwable $ignored) {
        return null;
    }
}

$autoload_file = __DIR__ . "/../vendor/autoload.php";
$sentry_dsn = getEnvVar("SENTRY_DSN", "");
$sentry_enabled = false;

if ($sentry_dsn && file_exists($autoload_file)) {
    require_once $autoload_file;

    try {
        \Sentry\init([
            "dsn" => $sentry_dsn,
            "environment" => getEnvVar("SENTRY_ENVIRONMENT", ENVIRONMENT),
            "release" => getEnvVar("SENTRY_RELEASE", "bitacora@local"),
            "enable_logs" => getEnvBool("SENTRY_CAPTURE_LOGS", true),
            "send_default_pii" => false,
            "max_request_body_size" => "none",
            "sample_rate" => (float)getEnvVar("SENTRY_SAMPLE_RATE", 1.0),
            "traces_sample_rate" => (float)getEnvVar("SENTRY_TRACES_SAMPLE_RATE", 0.0),
            "tags" => [
                "app" => "bitacora",
                "runtime" => "php",
            ],
            "before_send" => function (\Sentry\Event $event, ?\Sentry\EventHint $hint = null) {
                return $event;
            },
        ]);

        $sentry_enabled = true;
    } catch (Throwable $e) {
        error_log("Sentry initialization error: " . $e->getMessage());
    }
}

if (!defined("SENTRY_ENABLED")) {
    define("SENTRY_ENABLED", $sentry_enabled);
}
