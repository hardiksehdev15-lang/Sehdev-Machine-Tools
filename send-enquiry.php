<?php
/*
|--------------------------------------------------------------------------
| Sehdev Machine Tools — Enquiry Form Handler
|--------------------------------------------------------------------------
| Hostinger-native PHP mail() version.
|
| IMPORTANT:
| 1. Replace the two email addresses below with your real Hostinger
|    business email address.
| 2. Keep FROM as an email address belonging to your domain/Hostinger.
| 3. This file is called by enquiry.html via POST.
|--------------------------------------------------------------------------
*/

$recipientEmail = 'info@sehdevmachinetools.com';
$fromEmail      = 'info@sehdevmachinetools.com';
$fromName       = 'Sehdev Machine Tools Website';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* ================================================================
   RATE LIMITING — MAX 3 REQUESTS PER IP / 30 MINUTES
   ================================================================ */

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$rateLimitDir = __DIR__ . '/rate-limit';

if (!is_dir($rateLimitDir)) {
    mkdir($rateLimitDir, 0755, true);
}

$ipKey = hash('sha256', $ip);
$rateFile = $rateLimitDir . '/' . $ipKey . '.json';

$now = time();
$window = 30 * 60; // 30 minutes
$maxRequests = 3;

$requests = [];

if (is_file($rateFile)) {
    $stored = json_decode(
        file_get_contents($rateFile),
        true
    );

    if (is_array($stored)) {
        $requests = $stored;
    }
}

/* Remove requests older than 30 minutes */
$requests = array_values(
    array_filter(
        $requests,
        static fn($timestamp) =>
            is_int($timestamp) &&
            ($now - $timestamp) < $window
    )
);

/* Block if limit reached */
if (count($requests) >= $maxRequests) {
    redirectBack(
        'error',
        'Too many enquiries from this connection. Please try again later.'
    );
}

/* Record this request */
$requests[] = $now;

file_put_contents(
    $rateFile,
    json_encode($requests),
    LOCK_EX
);

/* ================================================================
   TURNSTILE BOT PROTECTION
   ================================================================ */

$turnstileSecret = '0x4AAAAAAFDHb_Z2IxsB0Gv-IF0-h_MgUEY';
$turnstileToken  = $_POST['cf-turnstile-response'] ?? '';

if ($turnstileToken === '') {
    redirectBack('error', 'Please complete the security verification.');
}

$turnstileData = [
    'secret'   => $turnstileSecret,
    'response' => $turnstileToken,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
];

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($turnstileData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded'
    ]
]);

$turnstileResponse = curl_exec($ch);
$curlError = curl_error($ch);

curl_close($ch);

if ($turnstileResponse === false || $curlError !== '') {
    redirectBack(
        'error',
        'Security verification failed. Please try again.'
    );
}

$turnstileResult = json_decode($turnstileResponse, true);

if (
    !is_array($turnstileResult) ||
    empty($turnstileResult['success'])
) {
    $errorCodes = $turnstileResult['error-codes'] ?? [];

    $errorMessage = !empty($errorCodes)
        ? implode(', ', $errorCodes)
        : 'No error code returned';

    redirectBack(
        'error',
        'Turnstile error: ' . $errorMessage
    );
}

/* ================================================================
   01. HELPER FUNCTIONS
   ================================================================ */

function cleanText($value): string
{
    $value = is_string($value) ? $value : '';
    $value = trim($value);
    return preg_replace("/[\r\n]+/", ' ', $value);
}

function cleanMultiline($value): string
{
    $value = is_string($value) ? $value : '';
    return trim($value);
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectBack(string $status, string $message = ''): never
{
    $query = '?status=' . rawurlencode($status);

    if ($message !== '') {
        $query .= '&message=' . rawurlencode($message);
    }

    header('Location: enquiry.html' . $query);
    exit;
}

/* ================================================================
   02. READ + VALIDATE MAIN FIELDS
   ================================================================ */

$fullName       = cleanText($_POST['full_name'] ?? '');
$company        = cleanText($_POST['company'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone          = cleanText($_POST['phone'] ?? '');
$city           = cleanText($_POST['city'] ?? '');
$country        = cleanText($_POST['country'] ?? '');
$projectDetails = cleanMultiline($_POST['project_details'] ?? '');
$serviceType    = cleanText($_POST['service_type'] ?? '');
$sourceService  = cleanText($_POST['source_service'] ?? '');
$productName    = cleanText($_POST['product_name'] ?? '');
$consent        = cleanText($_POST['consent'] ?? '');

/* ================================================================
   HONEYPOT ANTI-SPAM
   ================================================================ */

$websiteUrl = trim($_POST['website_url'] ?? '');

if ($websiteUrl !== '') {
    redirectBack(
        'error',
        'Unable to process this enquiry.'
    );
}

if ($fullName === '') {
    redirectBack('error', 'Please enter your full name.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectBack('error', 'Please enter a valid email address.');
}

/* ================================================================
   EMAIL DOMAIN / MX CHECK
   ================================================================ */

$emailDomain = strtolower(
    substr(strrchr($email, "@"), 1)
);

if (
    $emailDomain === '' ||
    !checkdnsrr($emailDomain, 'MX')
) {
    redirectBack(
        'error',
        'Please enter a valid email address.'
    );
}

if ($consent !== 'yes') {
    redirectBack('error', 'Please accept the contact consent checkbox.');
}

/* ================================================================
   03. MACHINERY SELECTION
   ================================================================ */

$machinery = $_POST['machinery'] ?? [];

if (!is_array($machinery)) {
    $machinery = [];
}

$machinery = array_values(
    array_filter(
        array_map('cleanText', $machinery),
        static fn($item) => $item !== ''
    )
);

/* ================================================================
   04. SERVICE-SPECIFIC DETAILS
   ================================================================ */

$serviceFields = [
    'cust_line_type'       => cleanText($_POST['cust_line_type'] ?? ''),
    'cust_throughput'      => cleanText($_POST['cust_throughput'] ?? ''),
    'cust_format'          => cleanText($_POST['cust_format'] ?? ''),
    'cust_pain_points'     => cleanMultiline($_POST['cust_pain_points'] ?? ''),
    'cust_plc'             => cleanText($_POST['cust_plc'] ?? ''),
    'cust_integration'     => cleanText($_POST['cust_integration'] ?? ''),
    'cust_deadline'        => cleanText($_POST['cust_deadline'] ?? ''),

    'rev_part'             => cleanText($_POST['rev_part'] ?? ''),
    'rev_qty'              => cleanText($_POST['rev_qty'] ?? ''),
    'rev_sample'           => cleanText($_POST['rev_sample'] ?? ''),
    'rev_material'         => cleanText($_POST['rev_material'] ?? ''),
    'rev_conditions'       => cleanText($_POST['rev_conditions'] ?? ''),
    'rev_docs'             => cleanText($_POST['rev_docs'] ?? ''),
    'rev_tolerance'        => cleanText($_POST['rev_tolerance'] ?? ''),
    'rev_urgency'          => cleanText($_POST['rev_urgency'] ?? ''),

    'refurb_machine_name'  => cleanText($_POST['refurb_machine_name'] ?? ''),
    'refurb_condition'     => cleanText($_POST['refurb_condition'] ?? ''),
    'refurb_year'          => cleanText($_POST['refurb_year'] ?? ''),
    'refurb_notes'         => cleanMultiline($_POST['refurb_notes'] ?? ''),
];

/* ================================================================
   05. BUILD EMAIL SUBJECT
   ================================================================ */

$serviceLabels = [
    'customization' => 'Design Customization',
    'reverse'       => 'Reverse Engineering',
    'refurbishment' => 'Product Refurbishment',
];

if ($productName !== '') {
    $subject = 'New Enquiry - ' . $productName;
} elseif ($serviceType !== '') {
    $subject = 'New Enquiry - ' . ($serviceLabels[$serviceType] ?? $serviceType);
} elseif (!empty($machinery)) {
    $subject = 'New Enquiry - ' . implode(', ', $machinery);
} else {
    $subject = 'New Website Enquiry';
}

/* ================================================================
   06. BUILD PLAIN-TEXT EMAIL BODY
   ================================================================ */

$bodyParts = [];

$bodyParts[] = 'SEHDEV MACHINE TOOLS';
$bodyParts[] = 'NEW ENQUIRY';
$bodyParts[] = str_repeat('=', 48);
$bodyParts[] = '';

if ($productName !== '') {
    $bodyParts[] = 'PRODUCT';
    $bodyParts[] = '-------';
    $bodyParts[] = $productName;
    $bodyParts[] = '';
} elseif (!empty($machinery)) {
    $bodyParts[] = 'MACHINERY INTERESTED IN';
    $bodyParts[] = '-----------------------';
    $bodyParts[] = implode(', ', $machinery);
    $bodyParts[] = '';
}

if ($serviceType !== '') {
    $bodyParts[] = 'SERVICE';
    $bodyParts[] = '-------';
    $bodyParts[] = $serviceLabels[$serviceType] ?? $serviceType;
    if ($sourceService !== '') {
        $bodyParts[] = 'Source: ' . $sourceService;
    }
    $bodyParts[] = '';
}

$bodyParts[] = 'CUSTOMER DETAILS';
$bodyParts[] = '----------------';
$bodyParts[] = 'Name: ' . $fullName;
if ($company !== '') $bodyParts[] = 'Company: ' . $company;
$bodyParts[] = 'Email: ' . $email;
if ($phone !== '') $bodyParts[] = 'Phone: ' . $phone;
if ($city !== '') $bodyParts[] = 'City: ' . $city;
if ($country !== '') $bodyParts[] = 'Country: ' . $country;
$bodyParts[] = '';

if ($projectDetails !== '') {
    $bodyParts[] = 'ENQUIRY DETAILS';
    $bodyParts[] = '---------------';
    $bodyParts[] = $projectDetails;
    $bodyParts[] = '';
}

if ($serviceType === 'customization') {
    $details = [];
    $map = [
        'cust_line_type' => 'Machine/Line Type',
        'cust_throughput' => 'Target Throughput',
        'cust_format' => 'Container/Format',
        'cust_pain_points' => 'Pain Points / Change Parts',
        'cust_plc' => 'PLC/HMI Brand',
        'cust_integration' => 'Integration Needed',
        'cust_deadline' => 'Timeline / Deadline'
    ];
    foreach ($map as $key => $label) {
        if ($serviceFields[$key] !== '') $details[] = $label . ': ' . $serviceFields[$key];
    }
    if ($details) {
        $bodyParts[] = 'CUSTOMIZATION DETAILS';
        $bodyParts[] = '---------------------';
        $bodyParts = array_merge($bodyParts, $details);
        $bodyParts[] = '';
    }
}

if ($serviceType === 'reverse') {
    $details = [];
    $map = [
        'rev_part' => 'Part Name / Function',
        'rev_qty' => 'Quantity Needed',
        'rev_sample' => 'Sample Available',
        'rev_material' => 'Material',
        'rev_conditions' => 'Operating Conditions',
        'rev_docs' => 'Drawings/Documents Available',
        'rev_tolerance' => 'Tolerance / Critical Fits',
        'rev_urgency' => 'Urgency'
    ];
    foreach ($map as $key => $label) {
        if ($serviceFields[$key] !== '') $details[] = $label . ': ' . $serviceFields[$key];
    }
    if ($details) {
        $bodyParts[] = 'REVERSE ENGINEERING DETAILS';
        $bodyParts[] = '---------------------------';
        $bodyParts = array_merge($bodyParts, $details);
        $bodyParts[] = '';
    }
}

if ($serviceType === 'refurbishment') {
    $details = [];
    $map = [
        'refurb_machine_name' => 'Machine',
        'refurb_condition' => 'Current Condition',
        'refurb_year' => 'Year of Manufacture',
        'refurb_notes' => 'Issues / Upgrades'
    ];
    foreach ($map as $key => $label) {
        if ($serviceFields[$key] !== '') $details[] = $label . ': ' . $serviceFields[$key];
    }
    if ($details) {
        $bodyParts[] = 'PRODUCT REFURBISHMENT DETAILS';
        $bodyParts[] = '-----------------------------';
        $bodyParts = array_merge($bodyParts, $details);
        $bodyParts[] = '';
    }
}

$body = implode("\r\n", $bodyParts);

/* ================================================================
   07. PREPARE MIME EMAIL
   ================================================================ */

$boundary = '=_Part_' . md5(uniqid((string)mt_rand(), true));

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$encodedSubject = function_exists('mb_encode_mimeheader')
    ? mb_encode_mimeheader($subject, 'UTF-8', 'B')
    : $subject;
$headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

$message = '';
$message .= '--' . $boundary . "\r\n";
$message .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
$message .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
$message .= $body . "\r\n\r\n";

/* ================================================================
   08. ATTACH UPLOADED FILES
   ================================================================ */

$allowedExtensions = [
    'jpg', 'jpeg', 'png', 'webp',
    'pdf',
    'doc', 'docx',
    'xls', 'xlsx',
    'zip',
    'mp4', 'mov', 'avi'
];

$maxFileSize = 8 * 1024 * 1024; // 8 MB per file

if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {

    $fileCount = count($_FILES['attachments']['name']);

    for ($i = 0; $i < $fileCount; $i++) {

        $error    = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $tmpName  = $_FILES['attachments']['tmp_name'][$i] ?? '';
        $fileName = basename($_FILES['attachments']['name'][$i] ?? '');
        $fileSize = (int)($_FILES['attachments']['size'][$i] ?? 0);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            continue;
        }

        if ($fileSize > $maxFileSize) {
            continue;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $fileData = file_get_contents($tmpName);

        if ($fileData === false) {
            continue;
        }

        $contentType = 'application/octet-stream';

        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($tmpName);
            if (is_string($detected) && $detected !== '') {
                $contentType = $detected;
            }
        }

        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: ' . $contentType . '; name="' . addcslashes($fileName, '"\\') . '"' . "\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . addcslashes($fileName, '"\\') . '"' . "\r\n";
        $message .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
        $message .= chunk_split(base64_encode($fileData)) . "\r\n";
    }
}

$message .= '--' . $boundary . "--\r\n";

/* ================================================================
   09. SEND EMAIL
   ================================================================ */

$sent = mail(
    $recipientEmail,
    $encodedSubject,
    $message,
    implode("\r\n", $headers)
);

/* ================================================================
   10. RESULT
   ================================================================ */

if (!$sent) {
    redirectBack(
        'error',
        'We could not send your enquiry right now. Please try again or contact us directly.'
    );
}

redirectBack(
    'success',
    'Your enquiry has been submitted successfully. We will contact you soon.'
);
?>
