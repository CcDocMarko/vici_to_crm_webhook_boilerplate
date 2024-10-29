<?php

require_once 'functions.php';

$logger = createLogger('webhook_log.txt');

$result = $_REQUEST;
/***
 * The API_URL should be gotten from "Integrate with Zapier" in the settings part of the client CRM dashboard, it should have the following params:
 * company_id
 * company_abbr 
 */
$API_URL = '';

$requestBodyKeys = array(
    'first_name',
    'last_name',
    'email',
    'phone_number',
    'street_address',
    'city',
    'state',
    'zip_code',
    'call_notes',
    'recording_url',
    'dispo',
    'agent',
    'lead_id',
    'list_id',
    'sole_decision_maker'
);

$parsedFields = array_fill_keys($requestBodyKeys, null);

$requiredFields = array(
    'first_name',
    'email',
    'street_address',
    'city',
    'state',
    'zip_code'
);

$missingFields = [];

foreach ($requestBodyKeys as $key) {
    if (isset($result[$key])) {
        $parsedFields[$key] = $result[$key];
    }
}

// Check for missing required fields
foreach ($requiredFields as $field) {
    if (is_null($parsedFields[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    // Log error for missing fields
    $logger("Error: Missing required fields: " . implode(', ', $missingFields) . PHP_EOL);
    return; // Exit early if required fields are missing
}

// Extract the variables from the parsed fields
extract($parsedFields);

// Define headers for the request
$headers  = array('Content-Type: application/json');

// Create payload
$payload = json_encode([
    "email" => $email,
    "phone_number" => $phone,
    "first_name" => $first_name,
    "last_name" => $last_name,
    "street_address" => $street_address,
    "city" => $city,
    "state" => $state,
    // HARD CODE REP USERNAMES HERE
    "rep" => "",
    "rep_2" => "",
    "zip_code" => $zip_code,
    "lead_source" => "CC Docs",
    "note" => "Agent: $agent.\n$call_notes\nSole Decision Maker: $sole_decision_maker\nDispo: $dispo\nRecording URL: $recording_url\nList ID: $list_id\nLead ID: $lead_id"
]);

// Log the payload being sent
$logger("Payload: " . $payload . PHP_EOL);

// Execute cURL request
$leadDetail = exec_curl($API_URL, 'POST', $headers, $payload);

// Log the response
$logger("Response: " . json_encode($leadDetail) . PHP_EOL);

// Check response
if (isset($leadDetail['success']) && !$leadDetail['success']) {
    // Log error
    $logger("API call failed: " . json_encode($leadDetail) . PHP_EOL);
} else {
    // Log success message
    $logger("Lead added successfully." . PHP_EOL);
}
