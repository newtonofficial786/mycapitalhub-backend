<?php

function env($key, $default = null) {
    static $cache = null;
    
    if ($cache === null) {
        $cache = [];
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
                list($k, $v) = explode('=', $line, 2);
                $cache[trim($k)] = trim($v);
            }
        }
    }
    
    return $cache[$key] ?? \getenv($key) ?: $default;
}