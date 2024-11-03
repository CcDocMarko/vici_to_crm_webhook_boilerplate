<?php
/* GHL custom script, move to root when required */
require_once 'config.php';
require_once 'functions.php';
# fetch request
$result = $_REQUEST;

/*
* Array of possible values and respective fields for req body, update according to client's requirements
*  Default params => first_name, last_name, email, phone, address, city, state, postal_code, comments, tags, call_notes, recording_url, dispo, agent
*  Frequently used custom params => contact_id, avg_electric_bill, credit_score, roof_age, roof_type, shading, willing_remove_tree, list_id, lead_id, list_name, campaign
* 	Email or Phone are required to create contact
*/

/* Map as required, sample:
*VICIdial 
	Excellent, Excellent Above 720
	Good, Good 650 - 720
	Fair, Fair 580 - 650
	Poor, Poor Below 580
*CRM
	Excellent (720+) 
	Good (650 - 720)
	Fair (580 - 650)
	Poor (Below 580)
*/

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
$headers  = array('Authorization: Bearer ' . API_KEY, 'Content-Type: application/json');

/********************
 * creating contact *
 ********************/

$data = [
	"email" => $email,
	"phone" => "+1" . $phone,
	"firstName" => $firstName,
	"lastName" => $lastName,
	"address1" => $address,
	"city" => $city,
	"state" => $state,
	"postalCode" => $postal_code,
	"source" => "VICIdial",
	"tags" => [$tag],
	"customField" => [
		"vicidial_lead_id" => $leadId,
		"vicidial_list_id" => $listId,
		"vicidial_list_name" => $listName
	]
];

// Encode the array into a JSON string
$fields = json_encode($data, JSON_UNESCAPED_SLASHES);

$contactDetail = exec_curl(BASE_URL . '/v1/contacts', 'POST', $headers, $fields);
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


/****************
 * adding notes *
 ****************/
// VICIdial notes to the contact record
$endpoint = BASE_URL . '/v1/contacts/' . $contactId  . '/notes/';

$actual_recording_url = get_recording_url_domain($recordingPath);

$fields = [
	"body" => sprintf(
		"Disposition: %s \n Agent: %s \n Call Notes: %s \n Recording URL: %s",
		$disposition,
		$agent,
		str_replace("\n", "\\n", $callNotes),
		$actual_recording_url
	)
];

$jsonFields = json_encode($fields, JSON_UNESCAPED_SLASHES);

$notesResponse = exec_curl($endpoint, 'POST', $headers, $jsonFields);

if (ENABLE_DEBUG) {
	$logger = createLogger("log_notes_response.txt");
	if (!array_key_exists("createdAt", $notesResponse)) {
		$text = " Notes not added. " . json_encode($notesResponse);
		$logger($text);
	} else {
		$text = " Notes added. Notes Id: " . $notesResponse['id'];
		$logger($text);
	}
}

exit();
