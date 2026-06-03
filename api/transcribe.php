<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Use POST to transcribe audio.']);
}

$apiKey = trim((string) ($config['deepgram_api_key'] ?? ''));
if ($apiKey === '') {
    respond(500, ['ok' => false, 'error' => 'Deepgram API key is not configured.']);
}

if (!isset($_FILES['audio'])) {
    respond(400, ['ok' => false, 'error' => 'No audio file was uploaded.']);
}

$upload = $_FILES['audio'];
$uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    respond(400, ['ok' => false, 'error' => uploadErrorMessage($uploadError)]);
}

$tmpPath = (string) ($upload['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    respond(400, ['ok' => false, 'error' => 'The uploaded audio file is invalid.']);
}

$maxBytes = (int) ($config['max_upload_bytes'] ?? 26214400);
$fileSize = (int) filesize($tmpPath);
if ($fileSize <= 0) {
    respond(400, ['ok' => false, 'error' => 'The recording was empty.']);
}
if ($fileSize > $maxBytes) {
    respond(413, ['ok' => false, 'error' => 'The recording is too large for this local demo.']);
}

$languageMode = normalizeLanguageMode($_POST['language'] ?? 'bn');
$translateToEnglish = parseBool($_POST['translate'] ?? 'true');
$smartFormat = parseBool($_POST['smart_format'] ?? 'true');
$audioBytes = file_get_contents($tmpPath);
if ($audioBytes === false) {
    respond(500, ['ok' => false, 'error' => 'Could not read the uploaded recording.']);
}

$contentType = detectContentType($tmpPath, (string) ($upload['type'] ?? ''));
$deepgram = transcribeWithDeepgram(
    $audioBytes,
    $contentType,
    $apiKey,
    $config,
    $languageMode,
    $smartFormat
);

$fallbackUsed = false;
$result = extractDeepgramResult($deepgram['body']);

if ($languageMode === 'auto' && shouldRetryBangla($result)) {
    $banglaDeepgram = transcribeWithDeepgram(
        $audioBytes,
        $contentType,
        $apiKey,
        $config,
        'bn',
        $smartFormat
    );
    $banglaResult = extractDeepgramResult($banglaDeepgram['body']);

    if (strlen($banglaResult['transcript']) >= strlen($result['transcript'])) {
        $deepgram = $banglaDeepgram;
        $result = $banglaResult;
        $fallbackUsed = true;
    }
}

if ($result['language'] === '' && $languageMode !== 'auto') {
    $result['language'] = $languageMode;
}

$english = '';
$translationSource = 'none';
if ($result['transcript'] !== '') {
    if (isEnglish($result['language'])) {
        $english = $result['transcript'];
        $translationSource = 'original';
    } elseif (
        $translateToEnglish
        && (isBangla($result['language']) || $languageMode === 'bn' || containsBanglaScript($result['transcript']))
    ) {
        $translated = translateBanglaToEnglish($result['transcript'], (int) ($config['request_timeout_seconds'] ?? 75));
        if ($translated !== '') {
            $english = $translated;
            $translationSource = 'translation';
        } else {
            $english = $result['transcript'];
            $translationSource = 'translation_unavailable';
        }
    } elseif ($translateToEnglish) {
        $english = $result['transcript'];
        $translationSource = 'untranslated';
    } else {
        $translationSource = 'disabled';
    }
}

respond(200, [
    'ok' => true,
    'timestamp' => gmdate('c'),
    'language' => $result['language'],
    'language_confidence' => $result['language_confidence'],
    'transcript' => $result['transcript'],
    'english' => $english,
    'translation_source' => $translationSource,
    'fallback_used' => $fallbackUsed,
    'duration' => $result['duration'],
    'deepgram_request_id' => $result['request_id'],
]);

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizeLanguageMode(mixed $language): string
{
    $value = strtolower(trim((string) $language));
    return in_array($value, ['auto', 'en', 'bn'], true) ? $value : 'bn';
}

function parseBool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed ?? true;
}

function detectContentType(string $path, string $browserType): string
{
    $allowedBrowserTypes = [
        'audio/webm',
        'audio/ogg',
        'audio/wav',
        'audio/x-wav',
        'audio/mpeg',
        'audio/mp4',
        'video/webm',
    ];

    $browserType = strtolower(trim(explode(';', $browserType)[0]));
    if (in_array($browserType, $allowedBrowserTypes, true)) {
        return $browserType === 'audio/x-wav' ? 'audio/wav' : $browserType;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = $finfo ? (string) finfo_file($finfo, $path) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    return $detected !== '' ? $detected : 'application/octet-stream';
}

function transcribeWithDeepgram(
    string $audioBytes,
    string $contentType,
    string $apiKey,
    array $config,
    string $languageMode,
    bool $smartFormat
): array {
    $params = [
        'model' => 'nova-3-general',
        'punctuate' => $smartFormat ? 'true' : 'false',
        'smart_format' => $smartFormat ? 'true' : 'false',
    ];

    if ($languageMode === 'auto') {
        $params['detect_language'] = 'true';
    } else {
        $params['language'] = $languageMode;
    }

    $url = rtrim((string) ($config['deepgram_endpoint'] ?? 'https://api.deepgram.com/v1/listen'), '?')
        . '?' . http_build_query($params);

    $ch = curl_init($url);
    if ($ch === false) {
        respond(500, ['ok' => false, 'error' => 'Could not initialize the transcription request.']);
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $audioBytes,
        CURLOPT_HTTPHEADER => [
            'Authorization: Token ' . $apiKey,
            'Content-Type: ' . $contentType,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int) ($config['request_timeout_seconds'] ?? 75),
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        respond(502, ['ok' => false, 'error' => 'Deepgram request failed: ' . $curlError]);
    }

    $body = json_decode($response, true);
    if (!is_array($body)) {
        respond(502, ['ok' => false, 'error' => 'Deepgram returned an unreadable response.']);
    }

    if ($status < 200 || $status >= 300) {
        $message = (string) ($body['err_msg'] ?? $body['message'] ?? 'Deepgram returned an error.');
        respond($status >= 400 && $status < 500 ? 400 : 502, [
            'ok' => false,
            'error' => $message,
            'deepgram_status' => $status,
        ]);
    }

    return ['status' => $status, 'body' => $body];
}

function extractDeepgramResult(array $body): array
{
    $channel = $body['results']['channels'][0] ?? [];
    $alternative = $channel['alternatives'][0] ?? [];

    return [
        'transcript' => trim((string) ($alternative['transcript'] ?? '')),
        'language' => (string) ($channel['detected_language'] ?? $channel['language'] ?? ''),
        'language_confidence' => isset($channel['language_confidence']) ? (float) $channel['language_confidence'] : null,
        'duration' => isset($body['metadata']['duration']) ? (float) $body['metadata']['duration'] : null,
        'request_id' => (string) ($body['metadata']['request_id'] ?? ''),
    ];
}

function shouldRetryBangla(array $result): bool
{
    $language = strtolower((string) ($result['language'] ?? ''));
    $confidence = $result['language_confidence'];
    $transcript = trim((string) ($result['transcript'] ?? ''));

    if ($transcript === '') {
        return true;
    }

    return $language !== '' && !str_starts_with($language, 'en') && is_numeric($confidence) && (float) $confidence < 0.55;
}

function isEnglish(string $language): bool
{
    return str_starts_with(strtolower(trim($language)), 'en');
}

function isBangla(string $language): bool
{
    $value = strtolower(trim($language));
    return $value === 'bn' || str_starts_with($value, 'bn-');
}

function containsBanglaScript(string $text): bool
{
    return preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1;
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

function uploadErrorMessage(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded recording is too large.',
        UPLOAD_ERR_PARTIAL => 'The recording upload was incomplete.',
        UPLOAD_ERR_NO_FILE => 'No recording was uploaded.',
        default => 'The recording could not be uploaded.',
    };
}
