<?php
/* HUBSPOT custom script, move to root when required */
require_once 'config.php';
require_once 'functions.php';
# fetch request
$result = $_REQUEST;
# Add this URL into campaign Dispo URL field
# https://login.theccdocs.com/custom/webhook.php?first_name=--A--first_name--B--&last_name=--A--last_name--B--&email=--A--email--B--&phone=--A--phone_number--B--&address=--A--address1--B--&city=--A--city--B--&state=--A--state--B--&postal_code=--A--postal_code--B--&call_notes=--A--call_notes--B--&dispo=--A--dispo--B--&agent=--A--fullname--B--&recording_url=RECORDINGS/MP3/--A--recording_filename--B---all.mp3&list_id=--A--list_id--B--&lead_id=--A--lead_id--B--&list_name=--A--list_name--B--&campaign=--A--campaign--B--&shade=--A--shade--B--&roof_age=--A--roof_age--B--&willing_remove_tree=--A--willing_remove_tree--B--&home_owned=--A--home_owned--B--&electric_provider=--A--electric_provider--B--&appt_type=--A--appt_type--B--&appointment_date=--A--appointment_date--B--&appointment_time=--A--appointment_time--B--&utility_provider=--A--utility_provider--B--


/*
* Array of possible values and respective fields for req body, update according to client's requirements
*  Default params => first_name, last_name, email, phone, address, city, state, postal_code, comments, tags, call_notes, recording_url, dispo, agent
*  Frequently used custom params => contact_id, avg_electric_bill, credit_score, roof_age, roof_type, shading, willing_remove_tree, list_id, lead_id, list_name, campaign
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
	'comments',
	'call_notes',
	'recording_url',
	'dispo',
	'agent',
	'campaign',
	'roof_age',
	'shade',
	'lead_id',
	'list_id',
	'list_name',
	'willing_remove_tree',
	'home_owned',
	'electric_provider',
	'appt_type',
	'appointment_date',
	'appointment_time',
	'utility_provider',
	'hs_id'
);

$parsedFields = processFields($result, $requestBodyKeys);

# Map home owned
$homeOwnedValues = [
	0 => "Less than 12 months",
	1 => "1-5 Years",
	2 => "6-10 Years",
	3 => "10-15 Years",
	4 => "15-20+ Years"
];

$parsedFields['home_owned'] = $parsedFields['home_owned'] ?  $homeOwnedValues[intval($parsedFields['home_owned'])] : '';

# Map appt type
$apptTypeValues = [
	0 => "In-Home",
	1 => "Virtual/Phone"
];

$parsedFields['appt_type'] = $parsedFields['appt_type'] ?  $apptTypeValues[intval($parsedFields['appt_type'])] : '';

#Parse appointment date into YYYY-MM-DD
if ($parsedFields['appointment_date']) {
	$apptDate = $parsedFields['appointment_date'];
	$apptDate = date('Y-m-d', strtotime($apptDate));
	$parsedFields['appointment_date'] = $apptDate;
}

#Parse appointment time into HH:MM, round to either nearest 00 or 30 minutes
if ($parsedFields['appointment_time']) {
	$parsedFields['appointment_time'] = roundToNearestHalfHour($parsedFields['appointment_time']);
}

# Map roof age
$roofAgeValues = [
	0 => "Less than 10 years old",
	1 => "10-20 years old",
	2 => "16-20 Years old",
	3 => "20+ years old",
	4 => "Ground Mount Interest"
];

$parsedFields['roof_age'] = $parsedFields['roof_age'] ?  $roofAgeValues[intval($parsedFields['roof_age'])] : '';

# Map Utility Provider
$utilityProviderValues = [
	0 => "ACE",
	1 => "PSE&G",
	2 => "JCP&L",
	3 => "VNLD",
	4 => "PECP",
	5 => "PP&L",
	6 => "MedEd",
	7 => "Sussex Rural CoOp",
	8 => "Orange & Rockland",
	9 => "Other/Unknown",
	10 => "Eversource",
	11 => "National Grid",
	12 => "Unitil",
	13 => "Wolfeboro Electric",
	14 => "Belmont Electric",
	15 => "Liberty Utilities",
	16 => "Georgetown Electric",
	17 => "THE BOROUGH OF MADISON",
	18 => "Ashland Electric",
	19 => "Norwood Electric",
	20 => "PLMP",
	21 => "NH CO-OP",
	22 => "Delmarva",
	23 => "Delaware Electric Cooperative",
	24 => "United Illuminating(UI)",
	25 => "Lakely Electric Light Company",
	26 => "Hull Electric",
	27 => "RI Energy"
];

$parsedFields['utility_provider'] = $parsedFields['utility_provider'] ?  $utilityProviderValues[intval($parsedFields['utility_provider'])] : '';

#Map disposition

$leadDispositionValues = [
	"APPTBK"  => "Appointment Set",
	"DQClos" => "DQ - Closed Circut",
	"DQTree" => "DQ - Tree Shading",
	"DQRoof" => "DQ - Roof Type",
	"DQSolR" => "DQ - Solar/Roof Follow Up",
	"DQLow" => "DQ - Low Usage",
	"DQOoS" => "DQ - Outside of Service area",
	"DQUtil" => "DQ - Bad Utility",
	"DNC" => "DO NOT CALL!!",
	"DNReq" => "Did Not Make Request",
	"DNQ" => "UNQUALIFIED"
];

if (!isset($leadDispositionValues[$parsedFields['dispo']])) {
	if (ENABLE_DEBUG) {
		$error_dump = createlogger('error-dump.txt');
		$error_dump("Dispo not found: " . $parsedFields['dispo'] . ", for phone" . $parsedFields['phone']) . PHP_EOL;
	}
	exit();
}

$parsedFields['dispo'] = $leadDispositionValues[$parsedFields['dispo']];

$parsedFields['email'] = isset($parsedFields['email']) ? $parsedFields['email'] : 'phonenumber@call.com';

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
	'roof_age' => $roofAge,
	'shade' => $shade,
	'lead_id' => $leadId,
	'list_id' => $listId,
	'list_name' => $listName,
	'willing_remove_tree' => $willingRemoveTree,
	'home_owned' => $homeOwned,
	'electric_provider' => $electricProvider,
	'appt_type' => $apptType,
	'appointment_date' => $appointmentDate,
	'appointment_time' => $appointmentTime,
	'utility_provider' => $utilityProvider,
	'dispo' => $disposition,
	'hs_id' => $huspotId
] = $parsedFields;

$actual_recording_url = get_recording_url_domain($recordingPath);

$appt_notes = ' Agent: ' . $agent . ' Call Notes: ' . $callNotes . ' Recording URL: ' . $actual_recording_url;

$fields = [
	"firstname" => $firstName,
	"lastname" => $lastName,
	"email" => $email,
	"phone" => $phone,
	"address" => $address,
	"city" => $city,
	"state" => $state,
	"zip_code" => $postalCode,
	"roof_age_new" => $roofAge,
	"shade" => $shade,
	"willing_to_remove_trees_" => $willingRemoveTree,
	"how_long_have_you_owned_the_home_" => $homeOwned,
	"who_is_your_electric_provider" => $electricProvider,
	"appt_type" => $apptType,
	"appointment_date" => $appointmentDate,
	"new_appointment_time" => $appointmentTime,
	"utility_provider_cloned_" => $utilityProvider,
	"hs_lead_status" => $disposition,
	"additional_appointment_details" => $appt_notes,
];

$properties = array_filter($fields, function ($value) {
	return $value !== null && $value !== false && $value !== '';
});

$properties["customer_type"] = "Solar/Roof";
$properties["hs_language"] = "en";

$jsonObject = json_encode(["properties" => $properties], JSON_PRETTY_PRINT);

if (ENABLE_DEBUG) {
	$dump = createlogger('payload-dump.txt');
	$dump($jsonObject);
}

# Setting Authorization
$headers  = array('Authorization: Bearer ' . API_KEY, 'Content-Type: application/json');

# UPDATE AGED LEADS

$endpoint = isset($parsedFields["hs_id"])  ? BASE_URL . "objects/contacts/" . $parsedFields["hs_id"] : BASE_URL . "objects/contacts/" . $parsedFields["email"] . "?idProperty=email";
$aged_lead = exec_curl($endpoint, 'PATCH', $headers, $jsonObject);
$hs_id = null;
$response = $aged_lead;

if ($response["message"] == "resource not found" && $disposition == "Appointment Set") {
	# CREATE LEAD
	$endpoint = BASE_URL . "objects/contacts";
	$newContact = exec_curl($endpoint, 'POST', $headers, $jsonObject);
	$hs_id = $newContact["id"];
	$response = $newContact;
} else {
	$hs_id = $response["id"];
}

$vici_response = 'Unable to update CRM id in vicidial';

if ($hs_id) {
	$params = [
		'custom_fields' => 'Y',
		'hs_id' => $hs_id,
		'lead_id' => $leadId,
		'search_method' => 'LEAD_ID'
	];

	$vici_response = update_lead(http_build_query($params));
}

if (ENABLE_DEBUG) {
	$dump = createlogger('response-dump.txt');
	$dump(json_encode($response) . PHP_EOL . $vici_response);
}