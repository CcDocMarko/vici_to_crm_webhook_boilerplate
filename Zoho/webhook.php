<?php
/* GHL custom script, move to root when required */
require_once 'config.php';
require_once 'functions.php';
# fetch request
$result = $_REQUEST;

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

$parsedFields = processFields($result, $requestBodyKeys);

# verifying email
if ((empty($email)) || (strpos($email, '@') === false)) {
	$email = "dummy" . "@dummymail.com";
}

/*
* Specific fields that require treatment outside of processFields()
*/

$disposition = '';
$tag = '';

if ($parsedFields['dispo'] == 'APPTBK') {
	$disposition = 'Appointment Booked';
	$tag = 'cc appt';
}

# Unpacking, edit required values, remove unrequired
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
	'agent' => $agent,
	'campaign' => $campaign,
	'contact_id' => $contactId,
	'lead_id' => $leadId,
	'list_id' => $listId,
	'list_name' => $listName,
] = $parsedFields;

# Authorization header
$headers  = array('Authorization: Zoho-oauthtoken ' . API_KEY, 'Content-Type: application/json');

/********************
 * creating contact *
 ********************/

$data = [
	"contact_name" => $firstName . ' ' . $lastName,
	"billing_address" => [
		"address" => $address,
		"city" => $city,
		"state" => $state,
		"zip" => $postal_code,
		"phone" => "+1" . $phone
	],
	"language_code" => "en",
	"tags" => [
		[
			"tag_id" => $tag
		]
	],
	"custom_fields" => [
		[
			"index" => 1,
			"value" => $leadId
		],
		[
			"index" => 2,
			"value" => $listId
		],
		[
			"index" => 3,
			"value" => $listName
		]
	],
	"notes" => sprintf(
		"Disposition: %s \n Agent: %s \n Call Notes: %s \n Recording URL: %s",
		$disposition,
		$agent,
		str_replace("\n", "\\n", $callNotes),
		$actual_recording_url
	)
];


// Encode the array into a JSON string
$fields = json_encode($data, JSON_UNESCAPED_SLASHES);

$contactDetail = exec_curl(BASE_URL . '/contacts', 'POST', $headers, $fields);
$webhookLogEntry = null;

if (ENABLE_DEBUG) {
	$webhookLogEntry = createLogger('webhook_log.txt');
}

if (empty($contactDetail['contact']['id'])) {
	// writing to file
	if ($webhookLogEntry) {
		$text = "Couldn't create a new Contact. " . json_encode($contactDetail);
		$webhookLogEntry($text);
	}
	exit();
}

$contactId = $contactDetail['contact']['id'];
// writing to file
if ($webhookLogEntry) {
	$text = "Contact succesfully created. Contact Id: " . $contactId;
	$webhookLogEntry($text);
}
/********************
 * creating contact *
 ********************/
