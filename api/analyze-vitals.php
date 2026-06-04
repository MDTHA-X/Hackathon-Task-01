<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Use POST to analyze vitals.']);
}

$apiKey = trim((string) ($config['gemini_api_key'] ?? ''));
if ($apiKey === '') {
    respond(500, ['ok' => false, 'error' => 'Gemini API key is not configured.']);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    respond(400, ['ok' => false, 'error' => 'Invalid JSON payload.']);
}

$demographics = $input['demographics'] ?? [];
$history = $input['history'] ?? 'No history provided';
$vitals = $input['vitals'] ?? [];

$promptText = "You are an advanced medical AI assisting a Community Health Worker (CHW) in the field. Your job is to analyze patient data, triage the severity, and provide actionable advice.
CRITICAL INSTRUCTION: The patient's life relies on your accuracy. Prioritize patient safety. Do NOT hallucinate.

Patient Demographics:
- Name: " . ($demographics['name'] ?? 'Unknown') . "
- Age: " . ($demographics['age'] ?? 'Unknown') . "
- Sex: " . ($demographics['sex'] ?? 'Unknown') . "

Extracted Medical History:
$history

Patient Vitals (Current):
- Blood Pressure: " . ($vitals['bp'] ?? 'Unknown') . "
- Heart Rate: " . ($vitals['hr'] ?? 'Unknown') . " bpm
- Temperature: " . ($vitals['temp'] ?? 'Unknown') . " F
- Oxygen Saturation (SpO2): " . ($vitals['spo2'] ?? 'Unknown') . " %
- Blood Glucose: " . ($vitals['bg'] ?? 'Unknown') . " mg/dL

Please analyze the above data and provide a response formatted as JSON matching the requested schema.
1. Determine a Triage Severity Score (Green, Yellow, Red, Black).
2. Provide reasoning for the anomalies.
3. Suggest differential diagnoses based on reported symptoms and history.
4. Recommend immediate first-aid steps the CHW can take on-site.
5. Suggest which type of specialist(s) the patient needs to see.";

$schema = [
    'type' => 'OBJECT',
    'properties' => [
        'triage_score' => [
            'type' => 'STRING',
            'description' => 'Must be one of: Green, Yellow, Red, Black'
        ],
        'reasoning' => [
            'type' => 'STRING',
            'description' => 'Reasoning for the triage score and any anomalies found.'
        ],
        'differential_diagnoses' => [
            'type' => 'ARRAY',
            'items' => ['type' => 'STRING'],
            'description' => 'Likely differential diagnoses based on reported symptoms.'
        ],
        'first_aid' => [
            'type' => 'ARRAY',
            'items' => ['type' => 'STRING'],
            'description' => 'Immediate first-aid steps the CHW can take on-site.'
        ],
        'specialist_recommendation' => [
            'type' => 'ARRAY',
            'items' => ['type' => 'STRING'],
            'description' => 'Suggested specialist(s) the patient needs to see.'
        ]
    ],
    'required' => ['triage_score', 'reasoning', 'differential_diagnoses', 'first_aid', 'specialist_recommendation']
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $promptText]
            ]
        ]
    ],
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        'responseSchema' => $schema,
        'temperature' => 0.1
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
$responseText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
$analysis = json_decode($responseText, true) ?: [];

respond(200, [
    'ok' => true,
    'analysis' => $analysis,
]);

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
