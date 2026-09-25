<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

$email = trim($_POST['email'] ?? '');


// Basic email format validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => true,
        'status' => 'invalid',
        'sub_status' => 'failed_syntax_check'
    ]);

    exit;
}


// Load ZeroBounce API key from private config file
$config = require __DIR__ . '/../zerobounce-config.php';

$zeroBounceApiKey = $config['api_key'] ?? '';

if ($zeroBounceApiKey === '') {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Email validation service is not configured.'
    ]);

    exit;
}


// ZeroBounce validation API
$url = 'https://api.zerobounce.net/v2/validate?' . http_build_query([
    'api_key' => $zeroBounceApiKey,
    'email' => $email,
    'ip_address' => ''
]);


$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);


$response = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);


// cURL error
if ($response === false || $curlError !== '') {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Email validation service is temporarily unavailable.'
    ]);

    exit;
}


// Decode ZeroBounce response
$data = json_decode($response, true);


// Invalid API response
if (!is_array($data)) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid response from email validation service.'
    ]);

    exit;
}


// ZeroBounce API error
if (isset($data['error'])) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Email validation service error.'
    ]);

    exit;
}


// Return validation result to enquiry.html
echo json_encode([
    'success' => true,
    'status' => strtolower($data['status'] ?? 'unknown'),
    'sub_status' => strtolower($data['sub_status'] ?? ''),
]);