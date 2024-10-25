<?php
/* GiddyUp custom script, move to root when required */
/* URL to be attached to dialer
https://login.theccdocs.com/custom/legacy-tx/webhook.php?
first_name=--A--first_name--B--&
last_name=--A--last_name--B--&
email=--A--email--B--&
phone=--A--phone_number--B--&
address=--A--address1--B--&
address2=--A--address2--B--&
city=--A--city--B--&
state=--A--state--B--&
postal_code=--A--postal_code--B--&
call_notes=--A--call_notes--B--&
dispo=--A--dispo--B--&
agent=--A--fullname--B--&
recording_url=https://login.theccdocs.com/RECORDINGS/MP3/--A--recording_filename--B---all.mp3&
list_id=--A--list_id--B--&
lead_id=--A--lead_id--B--&
list_name=--A--list_name--B--&
campaign=--A--campaign--B--&
roof_type=--A--roof_type--B--&
roof_age=--A--roof_age--B--&
decision_maker=--A--decision_maker--B--&
appt_date=--A--appt_date--B--&
appt_time=--A--appt_time--B--
*/

require_once 'config.php';
require_once 'functions.php';
# fetch request
$result = $_REQUEST;
$ZAPIER_URL = 'https://hooks.zapier.com/hooks/catch/18292128/292polm/';

$requestBodyKeys = array(
	'first_name',
	'last_name',
	'email',
	'phone',
	'address',
	'address2',
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
	'roof_age',
	'roof_type',
	'decision_maker',
	'appt_date',
	'appt_time',
);

$parsedFields = processFields($result, $requestBodyKeys);

[
	'first_name' => $first_name,
	'last_name' => $last_name,
	'address' => $address,
	'address2' => $address2,
	'city' => $city,
	'state' => $state,
	'postal_code' => $postal_code,
	'email' =>	$email,
	'phone' => $phone,
	'call_notes' => $additional_notes,
	'recording_url' => $recordingPath,
	'lead_id' => $lead_id,
	'agent' => $agent,
	'dispo' => $dispo,
	'appt_date' => $appt_date,
	'appt_time' => $appt_time,
	'roof_age' => $roof_age,
	'roof_type' => $roof_type,
	'decision_maker' => $decision_maker
] = $parsedFields;

if ($dispo != 'APPTBK') {
	$log_non_sale = createLogger('non_sale_dial.txt');
	$log_non_sale($dispo . ' ' . $phone);
	exit();
}

$actual_recording_url = get_recording_url_domain($recordingPath);

$appt_notes = ' Agent: ' . $agent . ' Call Notes: ' . $additional_notes . ' Recording URL: ' . $actual_recording_url;

#Appointment datetime converted into CDT timezone.
$dateTime = new DateTime($appt_date . ' ' . roundToNearestHalfHour($appt_time));
$dateTime->modify('+1 hour');
$convertedDateTime = $dateTime->format('Y-m-d g:i A');

$description = 'Roof Age :' . $roof_age . ' Roof Type: ' . $roof_type . ' Decision Maker: ' . $decision_maker;

$fields = [
	'company' => 'Placeholder',
	'first_name' => $first_name,
	'last_name' => $last_name,
	'address' => $address,
	'address2' => $address2,
	'city' => $city,
	'state' => $state,
	'postal_code' => $postal_code,
	'email' =>	$email,
	'phone' => $phone,
	'home_phone' => '',
	'work_phone' => $phone,
	'call_notes' => $appt_notes,
	'description' => $description,
	'appointment_date' => $convertedDateTime,
	'additional_notes' => $additional_notes,
];

$jsonObject = json_encode(["properties" => $fields], JSON_PRETTY_PRINT);

$headers = array(
	"Content-Type: application/json",
	"Content-Length: " . strlen($jsonObject),
);

$response = exec_curl($ZAPIER_URL, 'POST', $headers, $jsonObject);

if (ENABLE_DEBUG) {
	$dump = createLogger('response-dump.txt');
	$dump(json_encode($response));
}

exit();