<?php
ini_set('display_errors', 1);

$DOMAIN = 'REPLACE THIS STRING WITH THE CALL CENTER DOMAIN ex. callcenterdomain.ccdocs.com';
$PASS = 'REPLACE WITH APIUSER PASSWORD';
$url 		   = '';
$lead_id	   = '';
$str_notes 	   = '';
$date          = date('M-Y');
$req_date 	   = date('Y-m-d H:i:s');
$timestamp_obj = new DateTime();
$timestamp     = $timestamp_obj->getTimestamp();
$file_name 	   = 'update_notes_webhook_log_' . $date . '.txt';

$text = "\n $req_date | Incoming Request." . json_encode($_REQUEST);
$log_file = createLogger($file_name);
$log_file($text);

$phone		 = $_REQUEST['phone'];
$contact_id  = $_REQUEST['contact_id'];
$location_id = $_REQUEST['location_id'];
if (isset($_REQUEST['lead_id'])) {
	$lead_id = $_REQUEST['lead_id'];
}
$phone = preg_replace('/[^\,0-9]/', '', $phone);

$get_notes_response = get_notes($contact_id);

if (array_key_exists("notes", $get_notes_response)) {

	foreach ($get_notes_response['notes'] as $notes) {
		$temp = explode('.', $notes['createdAt']);
		$notes_date = $temp[0];
		$notes_date = str_replace('T', ' ', $notes_date);
		$str_notes .= 'Created At -> ' . $notes_date . ' \nNotes -> ' . $notes['body'] . '\n\n';
	}
	$str_notes = urlencode($str_notes);
	if ($lead_id == '') {
		$url = $DOMAIN . '/vicidial/non_agent_api.php?source=GHL&user=APIUSER&pass=' . $PASS . '&function=update_lead&search_method=PHONE_NUMBER&records=5&phone_number=' . $phone . '&comments=' . $str_notes;
	} else {
		$url = $DOMAIN . '/vicidial/non_agent_api.php?source=GHL&user=APIUSER&pass=' . $PASS . '&function=update_lead&search_method=LEAD_ID&&lead_id=' . $lead_id . '&comments=' . $str_notes;
	}

	$add_notes_response = add_notes_vicidial($url);

	$result = strpos($add_notes_response, 'SUCCESS');
	if ($result) {
		echo $add_notes_response;
		$text = "\n $req_date | Notes Added to the Contact. " . $add_notes_response;
		$log_file($text);
	} else {
		echo $add_notes_response;
		$text = "\n $req_date | Error Adding Notes to the Contact. " . $add_notes_response;
		$log_file($text);
	}
} else {
	$text = "\n $req_date | Error Getting Notes. " . json_encode($get_notes_response);
	$log_file($text);
}

exit;

/**
 * [This function get the notes from GHL for specified contact]
 * @param  [string] $contact_id [GHL Contact Id]
 * @return [array]  $response   [Contact Detail]
 */
function get_notes($contact_id)
{
	$AUTH_TOKEN = 'REPLACE WITH LOCATION ACCOUNT API KEY FROM BUSINESS PROFILE';
	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://rest.gohighlevel.com/v1/contacts/' . $contact_id . '/notes/',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'GET',
		CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer ' . $AUTH_TOKEN,
		),
	));

	$response = curl_exec($curl);
	$response = json_decode($response, true);
	print_r(curl_error($curl));
	curl_close($curl);

	return $response;
}

/**
 * [This function add the notes to specified contact in VICIdial]
 * @param  [string] $contact_id [GHL Notes]
 * @param  [string] $phone		[Contact Phone]
 * @return [string] $response   [Respnse String]
 */
function add_notes_vicidial($url)
{
	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'GET',
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return $response;
}

/**
 * [This function logs information in txt file]
 * @param  [string] $file_name 	[Text File Name]
 * @return function $message [Text Message]
 */
function createLogger($file_name)
{
	// Append today's timestamp to the file upon initialization
	$req_date = date('Y-m-d H:i:s');
	file_put_contents($file_name, "\n*******************TIMESTAMP : $req_date *********************\n", FILE_APPEND);

	// Return the closure for logging
	return function ($message) use ($file_name) {
		// Log the message to the file
		file_put_contents($file_name, $message, FILE_APPEND);
	};
}
