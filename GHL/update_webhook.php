<?php
ini_set('display_errors', 1);
require_once 'config.php';
require_once 'functions.php';

$url 		   = '';
$lead_id	   = '';
$str_notes 	   = '';
$date          = date('M-Y');
$req_date 	   = date('Y-m-d H:i:s');
$timestamp_obj = new DateTime();
$timestamp     = $timestamp_obj->getTimestamp();
$file_name 	   = 'update_webhook_log_' . $date . '.txt';

$text = "\n $req_date | Incoming Request." . json_encode($_REQUEST);
$log_file = createLogger($file_name);
$log_file($text);

$phone		 = $_REQUEST['phone'];
$contact_id  = $_REQUEST['contact_id'];
$location_id = $_REQUEST['location_id'];
$function    = $_REQUEST['function'];

if (isset($_REQUEST['lead_id'])) {
	$lead_id = $_REQUEST['lead_id'];
}

$phone = preg_replace('/[^\,0-9]/', '', $phone);

$TARGET_URL = BASE_URL . '/v1/contacts/' . $contact_id . '/notes/';

if (isset($function) && $function == 'add_tags') {
	$TARGET_URL = BASE_URL . '/v1/tags/';
}

$get_response = exec_curl($TARGET_URL, 'GET', array('Authorization: Bearer ' . API_KEY));

if (array_key_exists("notes", $get_response)) {

	foreach ($get_response['notes'] as $notes) {
		$temp = explode('.', $notes['createdAt']);
		$notes_date = $temp[0];
		$notes_date = str_replace('T', ' ', $notes_date);
		$str_notes .= 'Created At -> ' . $notes_date . ' \nNotes -> ' . $notes['body'] . '\n\n';
	}

	$str_notes = urlencode($str_notes);

	if ($lead_id == '') {
		$params = 'search_method=PHONE_NUMBER&records=5&phone_number=' . $phone . '&comments=' . $str_notes;
	} else {
		$params = 'search_method=LEAD_ID&lead_id=' . $lead_id . '&comments=' . $str_notes;
	}

	$add_notes_response = update_lead($params);

	$result = strpos($add_notes_response, 'SUCCESS');
	if (is_numeric($result)) {
		$text = "\n $req_date | Notes Added to the Contact. " . $add_notes_response;
		$log_file($text);
	} else {
		$text = "\n $req_date | Error Adding Notes to the Contact. " . $add_notes_response;
		$log_file($text);
	}

	exit();
}

if (array_key_exists("tags", $get_response)) {
	$tags = [];
	foreach ($get_response['tags'] as $tag) {
		array_push($tags, $tag['name']);
	}
	$str_tags = implode(',', $tags);
	if ($lead_id == '') {
		$params = 'search_method=PHONE_NUMBER&records=5&phone_number=' . $phone . '&custom_fields=Y&tags=' . $str_tags;
	} else {
		$params = 'search_method=LEAD_ID&lead_id=' . $lead_id . '&custom_fields=Y&tags=' . $str_tags;
	}

	$add_tags_response = update_lead($params);

	$result = strpos($add_tags_response, 'SUCCESS');

	if (is_numeric($result)) {
		$text = "\n $req_date | Tags Added to the Contact. " . $add_tags_response;
		$log_file($text);
	} else {
		$text = "\n $req_date | Error Adding Tags to the Contact. " . $add_tags_response;
		$log_file($text);
	}
}

$text = "\n $req_date | Error Fetching Data. " . json_encode($get_response);

$log_file($text);
