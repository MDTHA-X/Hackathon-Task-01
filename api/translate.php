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

$timeout = (int) ($config['request_timeout_seconds'] ?? 75);
$translated = translateBanglaToEnglish($text, $timeout);

if ($translated === '') {
    respond(502, ['ok' => false, 'error' => 'Translation is unavailable right now.']);
}

respond(200, [
    'ok' => true,
    'english' => $translated,
]);

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function translateBanglaToEnglish(string $text, int $timeout): string
{
    $translated = translateWithGooglePublic($text, $timeout);
    if ($translated !== '') {
        return $translated;
    }

    return translateWithMyMemory($text, $timeout);
}

function translateWithGooglePublic(string $text, int $timeout): string
{
    $query = http_build_query([
        'client' => 'gtx',
        'sl' => 'bn',
        'tl' => 'en',
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

function translateWithMyMemory(string $text, int $timeout): string
{
    $query = http_build_query([
        'q' => $text,
        'langpair' => 'bn|en',
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
