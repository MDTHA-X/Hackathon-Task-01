<?php
declare(strict_types=1);

$localConfig = __DIR__ . '/config.local.php';

$config = [
    'deepgram_api_key' => getenv('DEEPGRAM_API_KEY') ?: '',
    'deepgram_endpoint' => 'https://api.deepgram.com/v1/listen',
    'max_upload_bytes' => 25 * 1024 * 1024,
    'request_timeout_seconds' => 75,
];

if (is_file($localConfig)) {
    $localValues = require $localConfig;
    if (is_array($localValues)) {
        $config = array_replace($config, $localValues);
    }
}

return $config;
