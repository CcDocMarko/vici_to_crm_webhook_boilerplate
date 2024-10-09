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
	'credit_score',
	'roof_age',
	'roof_type',
	'avg_electric_bill',
	'shading',
	'lead_id',
	'list_id',
	'list_name',
	'willing_remove_tree'
);

$parsedFields = processFields($result, $requestBodyKeys);

# Serialize credit score for GHL
if (array_key_exists('credit_score', $parsedFields)) {
	$score = $parsedFields['credit_score'];
	$score_exists = array_key_exists($score, $creditScoreValues);
	$parsedFields['credit_score'] = $score_exists ? $creditScoreValues[$score] : 'No Score';
}

# Serialize roof shade for GHL
if (array_key_exists('shading', $parsedFields)) {
	$shade = $parsedFields['shading'];
	$shade_exists = array_key_exists($shade, $roofShadeValues);
	$parsedFields['shading'] = $shade_exists ? $roofShadeValues[$shade] : 'Uncertain';
}

# Serialize average electric bill for GHL
if (array_key_exists('avg_electric_bill', $parsedFields)) {
	$bill = preg_replace('/[^0-9]/', '', $parsedFields['avg_electric_bill']);
	$bill = (int)$bill;

	switch (true) {
		case ($bill <= 100):
			$parsedFields['avg_electric_bill'] = '$0 - $100';
			break;
		case ($bill <= 150):
			$parsedFields['avg_electric_bill'] = '$101 - $150';
			break;
		case ($bill <= 200):
			$parsedFields['avg_electric_bill'] = '$151 - $200';
			break;
		case ($bill <= 300):
			$parsedFields['avg_electric_bill'] = '$201 - $300';
			break;
		case ($bill <= 400):
			$parsedFields['avg_electric_bill'] = '$301 - $400';
			break;
		case ($bill <= 500):
			$parsedFields['avg_electric_bill'] = '$401 - $500';
			break;
		case ($bill > 500):
			$parsedFields['avg_electric_bill'] = '$500+';
			break;
	}
}

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
	'recording_url' => $recordingUrl,
	'agent' => $agent,
	'campaign' => $campaign,
	'contact_id' => $contactId,
	'credit_score' => $creditScore,
	'roof_age' => $roofAge,
	'roof_type' => $roofType,
	'avg_electric_bill' => $avgElectricBill,
	'shading' => $shading,
	'lead_id' => $leadId,
	'list_id' => $listId,
	'list_name' => $listName,
	'willing_remove_tree' => $willingRemoveTree,
] = $parsedFields;

# Authorization header
$headers  = array('Authorization: Bearer ' . API_KEY, 'Content-Type: application/json');

if ($campaign == 'NoShow') {
	if (empty($contactId) || $contactId == '' || $contactId == '--A--ghl_contact_id--B--') {
		/********************
		 * updating contact *
		 ********************/
		$fields   = '{
				"email": "' . $email . '",
				"phone": "+1' . $phone . '",
				"firstName": "' . $firstName . '",
				"lastName": "' . $lastName . '",
				"address1": "' . $address . '",
				"city": "' . $city . '",
				"state": "' . $state . '",
				"postalCode": "' . $postalCode . '",
				"source": "VICIdial",
				"tags": [
					"' . $tag . '"
				],
				"customField": {
					"credit_score": "' . $creditScore . '",
					"roof_shade": "' . $shading . '",
					"what_is_your_average_monthly_electric_bill": "' . $avgElectricBill . '",
					"will_cut_trees": "' . $willingRemoveTree . '",
			    }
			}';
		$contactDetail = exec_curl(BASE_URL, 'POST', $headers, $fields);

		$webhookLogEntry = null;

		if (ENABLE_DEBUG) {
			$webhookLogEntry = createLogger('webhook_log.txt');
		}

		if (empty($contactDetail['contact']['id'])) {
			if ($webhookLogEntry) {
				$text = "Contact not found. " . json_encode($contactDetail);
				$webhookLogEntry($text);
			}
			exit();
		}

		$contactId = $contactDetail['contact']['id'];
		if ($webhookLogEntry) {
			$text = "Contact found. Contact Id: " . $contactId;
			$webhookLogEntry($text);
		}
	} else {
		/********************
		 * updating contact *
		 ********************/
		$endpoint = BASE_URL . $contactId;
		$fields   = '{
				"email": "' . $email . '",
				"phone": "+1' . $phone . '",
				"firstName": "' . $firstName . '",
				"lastName": "' . $lastName . '",
				"address1": "' . $address . '",
				"city": "' . $city . '",
				"state": "' . $state . '",
				"postalCode": "' . $postalCode . '",
				"source": "VICIdial",
				"tags": [
					"' . $tag . '"
				],
				"customField": {
					"credit_score": "' . $creditScore . '",
					"roof_shade": "' . $shading . '",
					"what_is_your_average_monthly_electric_bill": "' . $avgElectricBill . '",
					"will_cut_trees": "' . $willingRemoveTree . '",
			    }
			}';
		$contactDetail = exec_curl($endpoint, 'PUT', $headers, $fields);

		$webhookLogEntry = null;

		if (ENABLE_DEBUG) {
			$webhookLogEntry = createLogger('webhook_log.txt');
		}

		if (empty($contactDetail['contact']['id'])) {
			// writing to file
			$text = "Contact not found. " . json_encode($contactDetail);
			if ($webhookLogEntry) {
				$webhookLogEntry($text);
			}
			exit();
		}

		$contactId = $contactDetail['contact']['id'];
		// writing to file
		$text = "Contact found. Contact Id: " . $contactId;
		if ($webhookLogEntry) {
			$webhookLogEntry($text);
		}
		/********************
		 * updating contact *
		 ********************/
	}
} else {
	/********************
	 * creating contact *
	 ********************/
	// VICIdial custom fields
	$fields   = '{
			"email": "' . $email . '",
			"phone": "+1' . $phone . '",
			"firstName": "' . $firstName . '",
			"lastName": "' . $lastName . '",
			"address1": "' . $address . '",
			"city": "' . $city . '",
			"state": "' . $state . '",
			"postalCode": "' . $postal_code . '",
			"source": "VICIdial",
			"tags": [
				"' . $tag . '"
			],
			"customField": {
				"credit_score": "' . $creditScore . '",
				"roof_shade": "' . $shading . '",
				"what_is_your_average_monthly_electric_bill": "' . $avgElectricBill . '",
				"will_cut_trees": "' . $willingRemoveTree . '",
		      "vicidial_lead_id": "' . $leadId . '",
		      "vicidial_list_id": "' . $listId . '",
		      "vicidial_list_name": "' . $listName . '"
		    }
		}';
	$contactDetail = exec_curl(BASE_URL, 'POST', $headers, $fields);
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
}

/****************
 * adding notes *
 ****************/
// VICIdial notes to the contact record
$endpoint = 'https://rest.gohighlevel.com/v1/contacts/' . $contactId  . '/notes/';
$fields   = '{"body": "Disposition: ' . $disposition . ' \\n Agent: ' . $agent . ' \\n Call Notes: ' . str_replace("\n", "\\n", $callNotes) . ' \\n Recording URL: ' . $recordingUrl . '"}';

$notesResponse = exec_curl($endpoint, 'POST', $headers, $fields);

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
