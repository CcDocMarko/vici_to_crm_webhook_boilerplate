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

/*********
 * Zoho access token
 */

$jsonFilePath = '/var/www/html/custom/zoho/{json filename}.json';

$jsonContents = file_get_contents($jsonFilePath);

$data = json_decode($jsonContents, true);

$access_token = $data['access_token'];

$headers  = array('Authorization: Zoho-oauthtoken ' . $access_token, 'Content-Type: application/json');

# Body Payload

$payload = [
	"data" => [
		[
			"Owner" => [
				"id" => "", # USER ID
			],
			"Last_Name"       => $lastName,
			"Email"           => $email,
			"Description"     => $callNotes,
			"Website"         => "crm.zoho.com",
			"First_Name"      => $firstName,
			"Lead_Status"     => "",
			"Phone"           => $phone,
			"Street"          => $address,
			"Zip_Code"        => $postalCode,
			"City"            => $city,
			"State"           => $state,
			"Lead_Source"     => "VICIdial",
			"Country"         => "USA",
		]
	]
];

$payload = json_encode($payload, JSON_UNESCAPED_SLASHES);

$webhookLogEntry = null;

/***********
 * Updating contact 
 */

$contact_exists = exec_curl(BASE_URL . '/Leads/search?phone=' . $phone, 'GET', $headers);

if (!empty($contact_exists['data'])) {

	$contact_id = $contact_exists['data'][0]['id'];

	$contactDetail = exec_curl(BASE_URL . '/Leads/' . $contact_id, 'PUT', $headers, $payload);

	if ($contactDetail['message'] == "Contact has been updated successfully") {
		if ($webhookLogEntry) {
			$text = "Contact succesfully updated. " . $contact_id;
			$webhookLogEntry($text);
		}
	}
	exit();
}

/********** 
 * Updated contact 
 */

/********************
 * 
 * Creating contact *
 ********************/

$contactDetail = exec_curl(BASE_URL . '/Leads', 'POST', $headers, $payload);

if (ENABLE_DEBUG) {
	$webhookLogEntry = createLogger('lead_create_log_' . TIMESTAMP . '.txt');
	$webhookLogEntry(json_encode($contactDetail));
}

/********************
 * Created contact *
 ********************/
