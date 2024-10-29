<?php
# Sample using Alabamas Roofing VICIdial to Discord
require_once 'config.php';
require_once 'functions.php';

$result = $_REQUEST;
$url       		  = "https://discord.com/api/webhooks/1300857377397936222/46vrAxjRq69-bCeDw3bTfgqWGFTv8P5am-76P6N7-F6rNaAoIEtfytBG6wOXU7a1fbmS";
$headers    	  = ['Content-Type: application/json; charset=utf-8'];

$recordingDate = new DateTime("now", new DateTimeZone('Etc/GMT+5'));

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
	'agent',
	'recording_url',
	'list_id',
	'lead_id',
	'list_name',
	'campaign',
);

$parsedFields = processFields($result, $requestBodyKeys);

if ($parsedFields['disposition'] == 'APPTBK') {
	$parsedFields['disposition'] = 'Appointment Booked';
}

$parsedFields['client'] = $parsedFields['first_name'] . ' ' . $parsedFields['last_name'];

$actual_recording_url = get_recording_url_domain($parsedFields['recording_url']);

$contentArray = [
	"🧮 Campaign: " . (isset($parsedFields['campaign']) ? $parsedFields['campaign'] : ''),
	"💸 Set By: " . (isset($parsedFields['agent']) ? $parsedFields['agent'] : ''),
	"➡️ Client Name: " . (isset($parsedFields['client']) ? $parsedFields['client'] : ''),
	"☎️ Phone: " . (isset($parsedFields['phone']) ? $parsedFields['phone'] : ''),
	"📨 Email: " . (isset($parsedFields['email']) ? $parsedFields['email'] : ''),
	"🏠 Address: " . (isset($parsedFields['address']) ? $parsedFields['address'] : ''),
	"🏙️ City: " . (isset($parsedFields['city']) ? $parsedFields['city'] : ''),
	"🏳️ State: " . (isset($parsedFields['state']) ? $parsedFields['state'] : ''),
	"🔖 List ID: " . (isset($parsedFields['list_id']) ? $parsedFields['list_id'] : ''),
	"🟢 List Name: " . (isset($parsedFields['list_name']) ? $parsedFields['list_name'] : ''),
	"🏳️ Zip Code: " . (isset($parsedFields['postal_code']) ? $parsedFields['postal_code'] : ''),
	"📝 Notes: " . (isset($parsedFields['call_notes']) ? $parsedFields['call_notes'] : ''),
	"💿 Recording URL: " . $actual_recording_url ? $actual_recording_url : 'No recording URL',
];

$contentArray = array_filter($contentArray, function ($value) {
	$parts = explode(":", $value, 2);
	return isset($parts[1]) && trim($parts[1]) !== '';
});

$content = implode("\n", $contentArray);

if (strlen($content) > 2000) {
	$content = substr($content, 0, 1998);
}

$fields = ['username' => '🤑🔥🤑 New Appointment 🤑🔥🤑', 'content' => $content];

foreach ($fields as $key => $value) {
	echo '<div>' . $value . '</div>';
}

$func_response = exec_curl($url, 'POST', $headers, $fields);

$logFile = null;

if (ENABLE_DEBUG) {
	$logFile = createLogger('response_log.txt');
}

if (empty($func_response)) {
	$logFile ? $logFile("Message post to discord.") : null;
} else {
	$logFile ? $logFile("\n Error posting message to discord." . json_encode($func_response)) : null;
}

exit();
