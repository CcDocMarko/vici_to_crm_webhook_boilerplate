<?php
#NOTE: "American Remodeling" WEBHOOK TO ADD NOTES TO POTENTIAL CUSTOMERS VIA THE LEADPERFECTION API
require_once 'config.php';
require_once 'functions.php';
# fetch request
$result = $_REQUEST;
$CRM_URL = 'https://th97a.leadperfection.com/djson.aspx';

$cookie_get = exec_curl('http://148.72.132.231/custom/get-cookies.php', 'GET', []);

if (!isset($cookie_get['cookie'])) {
	echo 'No cookie obtained.';
	exit();
}

$CRM_COOKIE = $cookie_get['cookie'];
# Today's timestamp
$req_date = date('Y-m-d H:i:s');

function addNoteForProspect($prospect_id, $full_note, $phone, $auth)
{
	$headers = [
		'Authorization: ' . $auth,
		'accept: */*',
		'Content-Type: application/x-www-form-urlencoded'
	];

	$endpoint_add_note = BASE_URL . '/api/SalesApi/AddNotes';

	$fields_add_note = [
		'rectype' => 'cst',
		'recid' => $prospect_id,
		'notes' => $full_note,
	];

	$created_note = exec_curl($endpoint_add_note, 'POST', $headers, http_build_query($fields_add_note));

	if (ENABLE_DEBUG) {
		$file_name = "notes_response_log.txt";
		$logger = createLogger($file_name);
		$logger("Response from AddNotes, for Client: $prospect_id, Number: $phone. Result:  $created_note");
	}

	return $created_note;
}


function updateProspectID($lead_id, $prospect_id, $phone)
{
	$update_params = http_build_query([
		'lead_id' => $lead_id,
		'prospect_id' => $prospect_id,
		'search_method' => 'LEAD_ID',
		'custom_fields' => 'Y'
	]);

	$response = update_lead($update_params);

	if (ENABLE_DEBUG) {
		$file_name = "update_prospect_id_log.txt";
		$logger = createLogger($file_name);
		$logger("Update response for Lead ID: $lead_id, Prospect ID: $prospect_id, Phone: $phone - " . $response . PHP_EOL);
	}

	return $response;
}

function fetchCustomerData($custId)
{
	global $CRM_URL, $CRM_COOKIE;

	$url = $CRM_URL . '?[{"ajax":"GetCustomer","options":"0","term":"get","format":"jsondata","data":[{"custid":"' . $custId . '"}]}]:';

	$headers = [
		"Cookie: " . $CRM_COOKIE
	];

	$response = exec_curl($url, 'GET', $headers);

	return $response;
}

function updateCustomerData($payload, $action)
{
	global $CRM_URL, $CRM_COOKIE;

	$url = $CRM_URL . '?[{"ajax":"' . $action . '","options":"0","term":"get","format":"jsondata","data":[' . $payload . ']}]:';

	$headers = [
		"Cookie: " . $CRM_COOKIE
	];

	$response = exec_curl($url, 'GET', $headers);

	return $response;
}

/*
Employee Mapping:
473  - Samantha Ellwood
461  - Cristina Gallardo
333  - Amber Garrett
361  - Desiree Gephardt
520  - Kaylee Kelly
513  - Tracey Urquiza
408  - Darian Wise
*/

$employeeMapping = [
	850004 => 473,
	850005 => 461,
	// No direct match found for "Amber Garrett"
	850002 => 361,
	850009 => 520,
	850007 => 513,
	850003 => 408
];

$map_disposition = [
	'3rdPAR' => '3rd Party',
	'BADCRD' => 'Bad Credit',
	'FL' => 'Language',
	'BAI' => 'Bad Info',
	'NScope' => 'NG-Scope',
	'NIN' => 'No Interest',
	'Compet' => 'Competitor',
	'Insura' => 'Insurance',
];

$map_call_status = [
	"CONF" => "Confirmed",
	"Set" => "AP",
	"NA" => "No Answer",
	"HANGUP" => "HU",
	"Competitor" => "COMP",
	"LM" => "Left Message",
	"A" => "AM",
	"BZ" => "Busy",
	"WN" => "Wrong Number",
	"Language" => "FL",
	"CB" => "CB",
	"No Interest" => "NI",
	"DNC" => "DNC",
	"NG2" => "NG",
	"FM" => "Fax Machine",
	"OOS" => "OS",
	"ZZZZZ" => "Verified"
];

function mapCallStatus($status, $map, $default_status)
{
	return isset($map[$status]) ? $map[$status] : $default_status;
}

$request_body_keys = [
	'first_name',
	'last_name',
	'address',
	'city',
	'state',
	'postal_code',
	'email',
	'phone',
	'agent',
	'dispo',
	'call_notes',
	'lead_id',
	'recording_url',
	'app_date',
	'app_time',
	'product_type',
	'prospect_id',
	'ils_id',
	'lds_id',
	'mkt_id',
	'setter'
];

$parsed_fields = processFields($result, $request_body_keys);

if (!isset($parsed_fields['phone'])) {
	echo 'Phone number is required';
	exit();
}

$disposition = '';

if ($parsed_fields['dispo'] == 'Set') {
	$disposition = 'Appointment Booked';
} else {
	$disposition = $parsed_fields['dispo'];
}

[
	'first_name' => $first_name,
	'last_name' => $last_name,
	'address' => $address,
	'city' => $city,
	'state' => $state,
	'postal_code' => $postal_code,
	'email' =>	$email,
	'phone' => $phone,
	'call_notes' => $additional_notes,
	'recording_url' => $recordingUrl,
	'lead_id' => $lead_id,
	'agent' => $agent,
	'dispo' => $dispo,
	'app_date' => $app_date,
	'app_time' => $app_time,
	'product_type' => $product_type,
	'prospect_id' => $prospect_id,
	'ils_id' => $issued_lead_id,
	'lds_id' => $lp_lead_id,
	'mkt_id' => $market_id
] = $parsed_fields;


/***********
	TOKEN & COOKIE
 ************/

$endpoint_get_token = BASE_URL . 'token';

$fields_get_token = [
	'grant_type' => 'password',
	'username' => 'CCD',
	'password' => 'CCD3',
	'clientid' => 'th97a',
	'appkey' => API_KEY
];

$headers = ['accept: */*', 'Content-Type: application/x-www-form-urlencoded'];

$result_auth = exec_curl($endpoint_get_token, 'POST', $headers, http_build_query($fields_get_token));

if (!isset($result_auth['access_token'])) {
	echo 'No token obtained.';
	exit();
}
$token = $result_auth['access_token'];
$auth = 'Bearer ' . $token;

/***********
 CUSTOMER
 ************/

$mapped_dispo = mapCallStatus($dispo, $map_disposition, $dispo);

$call_status = mapCallStatus($mapped_dispo, $map_call_status, 'NG');

if ($mapped_dispo === "A" || $mapped_dispo === "HANGUP") {
	$mapped_dispo = "data";
}

$dateTime = new DateTime($app_date . ' ' . roundToNearestHalfHour($app_time));

if ($dateTime < new DateTime()) {
	$dateTime = new DateTime();
}

if ($mapped_dispo === "CB") {

	$get_callback_url =  DIALER_URL . "vicidial/non_agent_api.php?source=webhook&user=" . VICIDIAL_USER . "&pass=" . VICIDIAL_PASS . "&function=lead_callback_info&lead_id=" . $lead_id;

	$response = exec_curl_VICI($get_callback_url, 'GET', array('Accept: text/plain'));

	$lines = explode("\n", trim($response));

	$last_line = end($lines);

	if (ENABLE_DEBUG) {
		$file_name = "callback_response_log.txt";
		$logger = createLogger($file_name);
	}

	while (empty(trim($last_line)) && count($lines) > 0) {
		array_pop($lines);
		$last_line = end($lines);
	}

	if (!empty($last_line)) {
		$fields = explode("|", $last_line);

		if (count($fields) >= 6) {
			$callback_date = $fields[5];
			$callback_msg = end($fields);
			$dateTime = new DateTime($callback_date);
			$additional_notes = $callback_msg;
			if (ENABLE_DEBUG) {
				$logger("response: " . json_encode($lines) . PHP_EOL);
				$logger("callback_date: " . $callback_date . PHP_EOL);
				$logger("callback_msg: " . $callback_msg . PHP_EOL);
			}
		} else {
			if (ENABLE_DEBUG) {
				$text = "Last line empty.";
				$logger($text);
			}
		}
	}
}

$full_note = 'Disposition: ' . $disposition . '/ Agent: ' . $agent . '/ Call Notes: ' . str_replace("\n", "\\n", $additional_notes) . ' / Recording URL: ' . $recordingUrl;

$endpoint_get_customer = BASE_URL . 'api/Customers/GetCustomers2';

$headers = [
	'Authorization: ' . $auth,
	'accept: */*',
	'Content-Type: application/x-www-form-urlencoded'
];

$is_new_record = false;

$fields_get_customer = [
	'lastname' => $last_name,
	'phone' => $phone
];

$logger = null;

if (ENABLE_DEBUG) {
	$file_name = "response_customer_log.txt";
	$logger = createLogger($file_name);
}

if (!$prospect_id) {
	$result_customer = exec_curl($endpoint_get_customer, 'POST', $headers, http_build_query($fields_get_customer));

	if (empty($result_customer)) {
		if (ENABLE_DEBUG) {
			$logger("Dialed phone: $phone is not a customer registered in LP" . PHP_EOL);
		}
		if ($disposition == "Appointment Booked") {

			$endpoint_create_customer = BASE_URL . 'api/Leads/LeadAdd';

			$formattedDate = $dateTime->format('m/d/Y');
			$formattedTime = $dateTime->format('h:iA');

			$payload = http_build_query([
				'firstname' => $first_name,
				'lastname' => $last_name,
				'address1' => $address,
				'city' => $city,
				'state' => $state,
				'zip' => $postal_code,
				'phone' => $phone,
				'productID' => $product_type,
				'email' => $email,
				'notes' => $additional_notes,
				'apptdate' => $formattedDate,
				'appttime' => $formattedTime,
				'source' => 'Call Center',
				'srs_id' => 1595,
			]);

			$result_new_customer = exec_curl($endpoint_create_customer, 'POST', $headers, $payload);
			if (ENABLE_DEBUG) {
				$logger("Response from LeadAdd, for Customer: $phone. Result:" .  json_encode($result_new_customer) . PHP_EOL);
				$logger("Payload for LeadAdd:" . json_encode($payload) . PHP_EOL);
			}
			# Since there's not prospectID immediately assigned yet after creating lead in CRM, execution should be stopped.
			exit();
		}
	} else {

		$prospect_id = $result_customer[0]['ProspectID'];

		if (count($result_customer) > 1) {
			$target_customer = null;
			foreach ($result_customer as $customer) {
				if (strpos($customer['CSZ'], $postal_code) !== false) {
					$target_customer = $customer;
					break;
				}
			}
			if (!$target_customer) {
				$target_customer = $result_customer[0];
			}
			$prospect_id = $target_customer['ProspectID'];
			if (ENABLE_DEBUG) {
				$logger("Response from CustomerGet, for Customer: $phone. Result:" .  json_encode($result_customer) . PHP_EOL);
				$logger("Found customer:" . json_encode($target_customer) . PHP_EOL);
			}
		}

		updateProspectID($lead_id, $prospect_id, $phone);
	}
}

/***
 * LEAD PERFECTION ASPNET API
 * 
 */

$result_customer = fetchCustomerData($prospect_id);

if ($result_customer["Records"]) {

	$record = $result_customer["Records"][0];
	$rev = $record["rev"];

	$prospectInfo = [
		"custid" => $prospect_id,
		"custrev" => $rev,
		"firstname1" => $first_name,
		"lastname1" => $last_name,
		"address1_1" => $address,
		"city1" => $city,
		"state1" => $state,
		"zip1" => $postal_code,
		"email1" => $email,
		"phone1" => $phone,
	];

	$infoPayload = urlencode(json_encode($prospectInfo));

	$saved_prospect_info = updateCustomerData($infoPayload, 'SaveProspectInfo');

	# Update a prospect lead's disposition
	$lead_records = $result_customer["Records9"];

	if (!$lp_lead_id) {
		$lp_lead_id = $lead_records[0]["leadnumber"];
	}

	$leadPayload = urlencode(json_encode([
		"custid" => $prospect_id,
		"lds_id" => $lp_lead_id,
		"dsp_id" => $mapped_dispo,
		"notes" => $additional_notes,
		"custrev" => $rev,
		'productid' => $product_type,
		#'pro_id' => "539"
	]));

	$lead_update = updateCustomerData($leadPayload, 'SaveLead');

	# Update a prospect call log tab

	$new_call_Record = [
		"custid" => $prospect_id,
		"calls_id" => 0,
		"calls_phone" => $phone,
		"calls_type" => "T",
		"calls_result" => mapCallStatus($mapped_dispo, $map_call_status, 'NG'), 
	];

	$callPayload = urlencode(json_encode($new_call_Record));
}

/**
 * ADD NOTE
 */

$prospect_response = addNoteForProspect($prospect_id, $full_note, $phone, $auth);


# Callback updates from API side
# Add call record in the call tab