<?php
#NOTE: "American Remodeling" WEBHOOK TO ADD NOTES TO POTENTIAL CUSTOMERS VIA THE LEADPERFECTION API
require_once 'config.php';
require_once 'functions.php';
# fetch request
$result = $_REQUEST;
$CRM_URL = 'https://th97a.leadperfection.com/djson.aspx';
$CRM_COOKIES = '.AspNet.ApplicationCookie=65yT9DKnQCwHkxb_o1XMYuRzR8j3NNDActLUx5JKgpps1NUIoRJRdDD584sAfxMHYnmJqbBIZinX88l1iTDZ--_4-UQFbc_T4IisEZRnH2qyCE4Qac3Xy9DzBT-qScs-JyviWwqCNx3uUpMweV-sGDAAd-j_sBLk6O49W7wAVG-nch61tfY4ySyeSid6nfFSLxcxGmmbz0JY3gpLL-fRJQOmRiacDgMMbiOQcvetRa8CJqiCmyOyhBF0LbvJk63i6-KN_cKYvtpwb3j-CSj8XZnrcQbz3rkrCCP1MClsIfi9BpZvce2jJNwwKLFAP7yDlj6XEgS0KBkrEEOqiqpRZ90jE6NNNqJl4yKAy_jsfFN5jqhYtSpwutXe7rSGcSOJ';
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
		'prospect_id' => $prospect_id
	]);

	$response = update_lead($update_params);

	if (ENABLE_DEBUG) {
		$file_name = "update_prospect_id_log.txt";
		$logger = createLogger($file_name);
		$logger("Update response for Lead ID: $lead_id, Prospect ID: $prospect_id, Phone: $phone - " . json_encode($response) . PHP_EOL);
	}

	return $response;
}

function fetchCustomerData($custId)
{
	global $CRM_URL, $CRM_COOKIES;

	$url = $CRM_URL . '?[{"ajax":"GetCustomer","options":"0","term":"get","format":"jsondata","data":[{"custid":"' . $custId . '"}]}]:';

	$headers = [
		"Cookie: " . $CRM_COOKIES
	];

	$response = exec_curl($url, 'GET', $headers);

	return $response;
}

function updateCustomerData($payload)
{
	global $CRM_URL, $CRM_COOKIES;

	$url = $CRM_URL . '?[{"ajax":"SaveProspectInfo","options":"0","term":"get","format":"jsondata","data":[' . $payload . ']}]:';

	$headers = [
		"Cookie: " . $CRM_COOKIES
	];

	$response = exec_curl($url, 'GET', $headers);

	return $response;
}

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
	'prospect_id'
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
	'prospect_id' => $prospect_id
] = $parsed_fields;


/***********
	TOKEN
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

$dateTime = new DateTime($app_date . ' ' . $app_time);

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

			$formattedDate = $dateTime->format('Ymd');
			$formattedTime = $dateTime->format('Hi');

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

	updateCustomerData($infoPayload);
}

/**
 * ADD NOTE
 */

$prospect_response = addNoteForProspect($prospect_id, $full_note, $phone, $auth);