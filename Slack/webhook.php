<?php
require_once 'config.php';
require_once 'functions.php';

$result = $_REQUEST;

// https://login.theccdocs.com/custom/clean-solar/slack/webhook.php?first_name=--A--first_name--B--&last_name=--A--last_name--B--&email=--A--email--B--&phone=--A--phone_number--B--&address=--A--address1--B--&city=--A--city--B--&state=--A--state--B--&postal_code=--A--postal_code--B--&call_notes=--A--call_notes--B--&dispo=--A--dispo--B--&agent=--A--fullname--B--&recording_url=/RECORDINGS/MP3/--A--recording_filename--B---all.mp3&campaign=--A--campaign--B--&list_id=--A--list_id--B--&lead_id=--A--lead_id--B--&list_name=--A--list_name--B--&contact_id=--A--ghl_contact_id--B--

$requestBodyKeys = array(
    'first_name',
    'last_name',
    'email',
    'phone',
    'address',
    'city',
    'state',
    'postal_code',
    'comments',
    'call_notes',
    'recording_url',
    'dispo',
    'agent',
    'campaign',
    'contact_id',
    'lead_id',
    'list_id',
    'list_name',
);


$webhookLogEntry = createLogger('webhook_slack_log.txt');

$parsedFields = processFields($result, $requestBodyKeys);

// Destructure based on client spacific fields

[
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'phone' => $phone,
    'address' => $address,
    'city' => $city,
    'state' => $state,
    'postal_code' => $postalCode,
    'comments' => $comments,
    'call_notes' => $callNotes,
    'recording_url' => $recordingPath,
    'dispo' => $dispo,
    'agent' => $agent,
    'campaign' => $campaign,
    'contact_id' => $contactId,
    'lead_id' => $leadId,
    'list_id' => $listId,
    'list_name' => $listName,
] = $parsedFields;

$disposition = '';

if ($dispo == 'APPTBK') {
    $disposition = 'Appointment Booked';
} else {
    $disposition = 'Disposition: ' . $dispo;
}

$recordingUrl = get_recording_url_domain($recordingPath);

// Construct the Slack message
$message = sprintf(
    "*New Lead Received:*\n*Name:* %s %s\n*Email:* %s\n*Phone:* %s\n*Address:* %s, %s, %s, %s\n*Disposition:* %s\n*Agent:* %s\n*Lead ID:* %s\n*Call Notes:* %s\n*Recording URL:* %s",
    $firstName,
    $lastName,
    $email,
    $phone,
    $address,
    $city,
    $state,
    $postalCode,
    $disposition,
    $agent,
    $leadId,
    str_replace("\n", "\\n", $callNotes),
    $recordingUrl
);


$slackToken = TOKEN;
$channelId = CHANNEL_ID;

$data = [
    'channel' => $channelId,
    'text' => $message
];

$headers = array(
    'Authorization: Bearer ' . $slackToken,
    'Content-Type: application/json'
);

$ch = curl_init('https://slack.com/api/chat.postMessage');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$webhookLogEntry("Slack API Response (HTTP $httpCode): " . $response);

if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    if (isset($responseData['ok']) && $responseData['ok']) {
        $text = "Message sent successfully to Slack. Timestamp: " . $responseData['ts'];
    } else {
        $text = "Failed to send message to Slack. Error: " . $responseData['error'];
    }
} else {
    $text = "HTTP Error: $httpCode, Response: $response";
}

// Write the final log entry
$webhookLogEntry($text);

exit();