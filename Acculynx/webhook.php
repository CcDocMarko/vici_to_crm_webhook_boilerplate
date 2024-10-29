<?php
#NOTE: ""  WEBHOOK FOR CREATING LEADS THROUGH ACCULYNX API
require_once 'config.php';
require_once 'functions.php';

$SALESPERSON = '';
# fetch request
$result = $_REQUEST;

/*
* Array of possible values and respective fields for req body, update according to client's requirements
*  Default params => first_name, last_name, email, phone, address, city, state, postal_code, comments, tags, call_notes, recording_url, dispo, agent
* 	Email or Phone are required to create contact
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
	'call_notes',
	'dispo',
	'agent',
	'recording_url',
	'list_id',
	'lead_id',
	'list_name',
	'campaign',
	'acculynx_job_id',
	'trade_types',
	'job_category',
	'work_type',
	'current_milestone'
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
	'call_notes' => $callNotes,
	'dispo' => $disposition,
	'agent' => $agent,
	'recording_url' => $recordingPath,
	'list_id' => $listId,
	'lead_id' => $leadId,
	'list_name' => $listName,
	'current_milestone' => $milestone,
	'trade_types' => $tradeTypes,
	'job_category' => $jobCategory,
	'work_type' => $workType
] = $parsedFields;

$actual_recording_url = get_recording_url_domain($recordingPath);

# Authorization header
$headers  = array('Authorization: Bearer ' . API_KEY, 'Content-Type: application/json');

$appt_notes = ' Agent: ' . $agent . ' Call Notes: ' . $callNotes . ' Recording URL: ' . $actual_recording_url;

$payload =  '{
		"emailAddress": "' . addslashes($email) . '",
		"phoneNumber1": "' . addslashes($phone) . '",
		"phoneExtension1": "' . '1' . '",
		"firstName": "' . addslashes($firstName) . '",
		"lastName": "' . addslashes($lastName) . '",
		"street": "' . addslashes($address) . '",
		"city": "' . addslashes($city) . '",
		"state": "' . addslashes($state) . '",
		"zip": "' . addslashes($postalCode) . '",
		"notes": "' . addslashes($notes) . '",
		"salesPerson": "' . $SALESPERSON . '"';

if (!empty($workType)) {
	$payload .= ', "workType": "' . addslashes($workType) . '"';
}

if (!empty($tradeTypes)) {

	function processTradeTypes($tradeTypes)
	{
		$tradeTypesArray = explode(", ", $tradeTypes);
		$quotedTradeTypes = array_map(function ($type) {
			return '"' . addslashes($type) . '"';
		}, $tradeTypesArray);
		return implode(", ", $quotedTradeTypes);
	}

	$processedTradeTypes = processTradeTypes($tradeTypes);

	$payload .= ', "tradeTypes": [' . $processedTradeTypes . ']';
}

if (!empty($jobCategory)) {
	$payload .= ', "jobCategory": "' . addslashes($jobCategory) . '"';
}

$payload .= '}';

$response = exec_curl($ENDPOINT, 'POST', $headers, $payload);

$webhookLogEntry = null;

if (ENABLE_DEBUG) {
	$webhookLogEntry = createLogger('webhook_log.txt');
}

if (!$response['success']) {
	if ($webhookLogEntry) {
		$text = "Couldn't create a new Lead. " . json_encode($response['errors']);
		$webhookLogEntry($text);
	}
	exit();
};

if ($webhookLogEntry) {
	$text = "Lead succesfully created!  ";
	$webhookLogEntry($text);
}
