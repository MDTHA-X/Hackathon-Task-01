<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Use POST to translate text.']);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    respond(400, ['ok' => false, 'error' => 'Send JSON with a text field.']);
}

$text = trim((string) ($input['text'] ?? ''));
if ($text === '') {
    respond(400, ['ok' => false, 'error' => 'No text was provided.']);
}

$source = trim((string) ($input['source'] ?? 'bn'));
$target = trim((string) ($input['target'] ?? 'en'));

$timeout = (int) ($config['request_timeout_seconds'] ?? 75);
$translated = translateText($text, $source, $target, $timeout, $config);

if ($translated === '') {
    respond(502, ['ok' => false, 'error' => 'Translation is unavailable right now.']);
}

respond(200, [
    'ok' => true,
    'english' => $target === 'en' ? $translated : null,
    'translated' => $translated,
]);

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function translateText(string $text, string $source, string $target, int $timeout, array $config = []): string
{
    $apiKey = trim((string) ($config['gemini_api_key'] ?? ''));
    if ($apiKey !== '') {
        $translated = translateWithGemini($text, $source, $target, $apiKey, $timeout);
        if ($translated !== '') {
            return $translated;
        }
    }

    $translated = translateWithGooglePublic($text, $source, $target, $timeout);
    if ($translated !== '') {
        return $translated;
    }

    return translateWithMyMemory($text, $source, $target, $timeout);
}

function translateWithGemini(string $text, string $source, string $target, string $apiKey, int $timeout): string
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);
    
    $sourceName = $source === 'bn' ? 'Bangla' : 'English';
    $targetName = $target === 'en' ? 'English' : 'Bangla';

    $prompt = "Translate the following $sourceName text to $targetName and correct it if necessary. Return only the translated $targetName text with no additional commentary:\n\n" . $text;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    if ($ch === false) {
        return '';
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(5, min($timeout, 30)),
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return '';
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return '';
    }

    $translatedText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return trim($translatedText);
}

function translateWithGooglePublic(string $text, string $source, string $target, int $timeout): string
{
    $query = http_build_query([
        'client' => 'gtx',
        'sl' => $source,
        'tl' => $target,
        'dt' => 't',
        'q' => $text,
    ]);

    $body = fetchUrl('https://translate.googleapis.com/translate_a/single?' . $query, $timeout);
    if ($body === '') {
        return '';
    }

    $json = json_decode($body, true);
    if (!is_array($json) || !isset($json[0]) || !is_array($json[0])) {
        return '';
    }

    $parts = [];
    foreach ($json[0] as $segment) {
        if (isset($segment[0]) && is_string($segment[0])) {
            $parts[] = $segment[0];
        }
    }

    return trim(implode('', $parts));
}

function translateWithMyMemory(string $text, string $source, string $target, int $timeout): string
{
    $query = http_build_query([
        'q' => $text,
        'langpair' => "$source|$target",
    ]);

    $body = fetchUrl('https://api.mymemory.translated.net/get?' . $query, $timeout);
    if ($body === '') {
        return '';
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return '';
    }

    return trim((string) ($json['responseData']['translatedText'] ?? ''));
}

function fetchUrl(string $url, int $timeout): string
{
    $ch = curl_init($url);
    if ($ch === false) {
        return '';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => max(5, min($timeout, 30)),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json,text/plain,*/*',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return '';
    }

    return (string) $response;
}
