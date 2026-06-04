<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Use POST to extract history.']);
}

$apiKey = trim((string) ($config['gemini_api_key'] ?? ''));
if ($apiKey === '') {
    respond(500, ['ok' => false, 'error' => 'Gemini API key is not configured.']);
}

if (!isset($_FILES['images']) || !is_array($_FILES['images']['tmp_name'])) {
    respond(400, ['ok' => false, 'error' => 'No images were provided.']);
}

$tmpNames = $_FILES['images']['tmp_name'];
$types = $_FILES['images']['type'];
$errors = $_FILES['images']['error'];
$sizes = $_FILES['images']['size'];

$parts = [];
$parts[] = [
    'text' => "Extract all relevant medical history, patient details, medications, and previous diagnoses from the provided documents. Output only the extracted information in a clear, well-structured text format."
];

foreach ($tmpNames as $i => $tmpName) {
    if ($errors[$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
        continue;
    }
    $mime = $types[$i];
    if (strpos($mime, 'image/') !== 0) {
        continue;
    }
    
    $fileData = file_get_contents($tmpName);
    if ($fileData === false) {
        continue;
    }
    
    $parts[] = [
        'inline_data' => [
            'mime_type' => $mime,
            'data' => base64_encode($fileData)
        ]
    ];
}

if (count($parts) === 1) {
    respond(400, ['ok' => false, 'error' => 'No valid images could be processed.']);
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);

$payload = [
    'contents' => [
        [
            'parts' => $parts
        ]
    ]
];

$timeout = (int) ($config['request_timeout_seconds'] ?? 75);
$ch = curl_init($url);
if ($ch === false) {
    respond(500, ['ok' => false, 'error' => 'Failed to initialize curl.']);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => max(10, min($timeout, 60)),
]);

$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($response === false || $status < 200 || $status >= 300) {
    $errorMessage = 'Gemini API request failed.';
    if ($response) {
        $json = json_decode((string) $response, true);
        if (isset($json['error']['message'])) {
            $errorMessage = $json['error']['message'];
        } else {
            $errorMessage = "Gemini API error ($status): " . substr((string) $response, 0, 100);
        }
    }
    respond(502, ['ok' => false, 'error' => $errorMessage]);
}

$json = json_decode($response, true);
$extractedText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

respond(200, [
    'ok' => true,
    'text' => trim($extractedText),
]);

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
