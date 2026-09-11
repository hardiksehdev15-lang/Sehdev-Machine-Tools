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

$recipientEmail = 'YOUR-BUSINESS-EMAIL@YOURDOMAIN.COM';
$fromEmail      = 'YOUR-BUSINESS-EMAIL@YOURDOMAIN.COM';
$fromName       = 'Sehdev Machine Tools Website';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
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
$consent        = cleanText($_POST['consent'] ?? '');

if ($fullName === '') {
    redirectBack('error', 'Please enter your full name.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectBack('error', 'Please enter a valid email address.');
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

$subjectService = $serviceType !== '' ? $serviceType : 'General Enquiry';
$subject = 'New Website Enquiry - ' . $subjectService;

/* ================================================================
   06. BUILD PLAIN-TEXT EMAIL BODY
   ================================================================ */

$bodyParts = [];

$bodyParts[] = "SEHDEV MACHINE TOOLS — NEW WEBSITE ENQUIRY";
$bodyParts[] = str_repeat('=', 56);
$bodyParts[] = '';
$bodyParts[] = 'CONTACT DETAILS';
$bodyParts[] = '----------------';
$bodyParts[] = 'Full Name: ' . $fullName;
$bodyParts[] = 'Company: ' . ($company !== '' ? $company : 'Not provided');
$bodyParts[] = 'Email: ' . $email;
$bodyParts[] = 'Phone: ' . ($phone !== '' ? $phone : 'Not provided');
$bodyParts[] = 'City: ' . ($city !== '' ? $city : 'Not provided');
$bodyParts[] = 'Country: ' . ($country !== '' ? $country : 'Not provided');
$bodyParts[] = '';

$bodyParts[] = 'MACHINERY INTERESTED IN';
$bodyParts[] = '-----------------------';
$bodyParts[] = $machinery ? implode(', ', $machinery) : 'Not specified';
$bodyParts[] = '';

$bodyParts[] = 'SERVICE';
$bodyParts[] = '-------';
$bodyParts[] = $serviceType !== '' ? $serviceType : 'Not specified';
$bodyParts[] = 'Source Service: ' . ($sourceService !== '' ? $sourceService : 'Not specified');
$bodyParts[] = '';

$bodyParts[] = 'PROJECT DETAILS';
$bodyParts[] = '---------------';
$bodyParts[] = $projectDetails !== '' ? $projectDetails : 'Not provided';
$bodyParts[] = '';

if ($serviceType === 'customization') {
    $bodyParts[] = 'CUSTOMIZATION DETAILS';
    $bodyParts[] = '---------------------';
    $bodyParts[] = 'Machine/Line Type: ' . ($serviceFields['cust_line_type'] ?: 'Not provided');
    $bodyParts[] = 'Target Throughput: ' . ($serviceFields['cust_throughput'] ?: 'Not provided');
    $bodyParts[] = 'Container/Format: ' . ($serviceFields['cust_format'] ?: 'Not provided');
    $bodyParts[] = 'Pain Points / Change Parts: ' . ($serviceFields['cust_pain_points'] ?: 'Not provided');
    $bodyParts[] = 'PLC/HMI Brand: ' . ($serviceFields['cust_plc'] ?: 'Not provided');
    $bodyParts[] = 'Integration Needed: ' . ($serviceFields['cust_integration'] ?: 'Not provided');
    $bodyParts[] = 'Timeline / Deadline: ' . ($serviceFields['cust_deadline'] ?: 'Not provided');
    $bodyParts[] = '';
}

if ($serviceType === 'reverse') {
    $bodyParts[] = 'REVERSE ENGINEERING DETAILS';
    $bodyParts[] = '---------------------------';
    $bodyParts[] = 'Part Name / Function: ' . ($serviceFields['rev_part'] ?: 'Not provided');
    $bodyParts[] = 'Quantity Needed: ' . ($serviceFields['rev_qty'] ?: 'Not provided');
    $bodyParts[] = 'Sample Available: ' . ($serviceFields['rev_sample'] ?: 'Not provided');
    $bodyParts[] = 'Material: ' . ($serviceFields['rev_material'] ?: 'Not provided');
    $bodyParts[] = 'Operating Conditions: ' . ($serviceFields['rev_conditions'] ?: 'Not provided');
    $bodyParts[] = 'Drawings/Documents Available: ' . ($serviceFields['rev_docs'] ?: 'Not provided');
    $bodyParts[] = 'Tolerance / Critical Fits: ' . ($serviceFields['rev_tolerance'] ?: 'Not provided');
    $bodyParts[] = 'Urgency: ' . ($serviceFields['rev_urgency'] ?: 'Not provided');
    $bodyParts[] = '';
}

if ($serviceType === 'refurbishment') {
    $bodyParts[] = 'PRODUCT REFURBISHMENT DETAILS';
    $bodyParts[] = '-----------------------------';
    $bodyParts[] = 'Machine: ' . ($serviceFields['refurb_machine_name'] ?: 'Not provided');
    $bodyParts[] = 'Current Condition: ' . ($serviceFields['refurb_condition'] ?: 'Not provided');
    $bodyParts[] = 'Year of Manufacture: ' . ($serviceFields['refurb_year'] ?: 'Not provided');
    $bodyParts[] = 'Issues / Upgrades: ' . ($serviceFields['refurb_notes'] ?: 'Not provided');
    $bodyParts[] = '';
}

$bodyParts[] = 'Submitted From: ' . ($_SERVER['HTTP_HOST'] ?? 'Website');
$bodyParts[] = 'IP Address: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown');

$body = implode("\r\n", $bodyParts);

/* ================================================================
   07. PREPARE MIME EMAIL
   ================================================================ */

$boundary = '=_Part_' . md5(uniqid((string)mt_rand(), true));

$headers = [];
$headers[] = 'MIME-Version: 1.0';
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
    $subject,
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
