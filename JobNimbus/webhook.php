<?php
require_once 'config.php';
require_once 'functions.php';
// MODIFY PAYLOAD BODY AND UPPER CASE VARIABLES AS REQUIRED

/*
CLIENT_ASSIGNEE_ID and CCDOCS_USER_ID can be gotten from inspecting a test payload using tools like postman or from the logger
*/
$CCDOCS_USER_ID = ''; # FOR EXAMPLE : "lyd8dmatnt1emyae1jpgl1k"
$CLIENT_ASSIGNEE_ID = ''; # FOR EXAMPLE : "23bsa"
$CLIENT_REP_NAME = ''; # FOR EXAMPLE : "John Doe"
$CCDOCS_USER = ''; # EMAIL FOR CCDOCS ACCESS TO CLIENT CRM
$CONTACT_URL = "https://app.jobnimbus.com/api1/contacts?actor={$CCDOCS_USER}";
$ACTIVITY_URL = 'https://app.jobnimbus.com/api1/activities';

$logger = createLogger('webhook_log.txt');

$result = $_REQUEST;

$requestBodyKeys = array(
    'first_name',
    'last_name',
    'email',
    'phone',
    'street_address',
    'city',
    'state',
    'zip_code',
    'note',
);

$parsedFields = [];
foreach ($requestBodyKeys as $key) {
    if (isset($result[$key])) {
        $parsedFields[$key] = $result[$key];
    }
}

extract($parsedFields);


$headers = array(
    'Content-Type: application/json',
    'Authorization: Bearer ' . API_KEY
);

$timestamp = time();


$payload = json_encode([
    "email" => strtolower($email),
    "mobile_phone" => $phone,
    "home_phone" => $phone,
    "first_name" => $first_name,
    "last_name" => $last_name,
    "address_line1" => $street_address,
    "city" => $city,
    "state_text" => $state,
    "zip" => $zip_code,
    "source_name" => "Telemarketing",
    "display_name" => "{$first_name} {$last_name}",
    "type" => "contact",
    "owners" => [
        [
            "id" => $CCDOCS_USER_ID
        ],
        [
            "id" => $CLIENT_ASSIGNEE_ID
        ]
    ],
    "date_created" => $timestamp,
    "sales_rep_name" => $CLIENT_REP_NAME,
    "description" => $note,
    "tags" => ["ccdocs"],
    "status_name" => "Lead"
]);

$logger("Payload: " . $payload . PHP_EOL);

$leadDetail = exec_curl($CONTACT_URL, 'POST', $headers, $payload);

$logger("Response: " . json_encode($leadDetail) . PHP_EOL);


if ($leadDetail != null) {
    $logger("Lead added successfully." . PHP_EOL);
} else {
    $logger("Lead API call failed: " . json_encode($leadDetail) . PHP_EOL);
}


$customerValue = $leadDetail['jnid'];

if ($customerValue) {
    $notePayload = json_encode([
        "type" => "activity",
        "note" => $note,
        "date_created" => $timestamp,
        "record_type_name" => "Note",
        "primary" => [
            "id" => $customerValue
        ]
    ]);

    $noteDetails = exec_curl($ACTIVITY_URL, 'POST', $headers, $notePayload);

    $logger("Response: " . json_encode($noteDetails) . PHP_EOL);
    if (isset($noteDetails['success']) && !$noteDetails['success']) {
        $logger("Notes API call failed: " . json_encode($noteDetails) . PHP_EOL);
    } else {
        $logger("Notes added to Lead successfully." . PHP_EOL);
    }
} else {
    $logger("Customer ID not found in response." . PHP_EOL);
}
?>