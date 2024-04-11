<?php
#NOTE: "CLIENT_NAME" WEBHOOK FOR CREATING LEADS THROUGH GHL API V1.1.2

$API_KEY = 'Get API Key from Settings/Business Profile';
$ENDPOINT = 'https://rest.gohighlevel.com/v1/contacts/';
$ENABLE_DEBUG = true; #Set logger
# fetch request
$result = $_REQUEST;

/**
 * [This function executes curl]
 * @param  [string] $endpoint 	[url to execute]
 * @param  [string] $method 	[POST or GET]
 * @param  [string] $fields 	[post fields]
 * @param  [string] $headers 	[header info]
 * 
 * @return [string] $response   [curl response]
 */
function exec_curl($endpoint, $method, $headers, $fields = '')
{

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => $endpoint,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_POSTFIELDS => $fields,
		CURLOPT_HTTPHEADER => $headers,
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return json_decode($response, true);
}

// Logging data Function to create a new log.
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

/** 
 * [This function assigns the array of params values to a new one and returns it]
 * [Can log to a file optionally]
 * @param [array]  $result Array of results from URL request params or response's body
 * @param [array]  $keyToVariables Array of variables
 * @param [string] $filename name of file for logging
 */
function processFields($result, $paramKeys)
{
	global $ENABLE_DEBUG;

	if ($ENABLE_DEBUG) {
		$file_name = "log_url_params.txt";
		$logger = createLogger($file_name);
	}

	if (!empty($result)) {
		$parseFields = [];
		foreach ($result as $key => $value) {
			if (in_array($key, $paramKeys)) {
				$parseFields[$key] = $value;
				if ($ENABLE_DEBUG) {
					$logMessage = "Request params has $key, with value: $value" . PHP_EOL;
					$logger($logMessage);
				}
			}
		}
		return $parseFields;
	} else {
		$text = "\nEmpty Request. No Parameters in the Request.";
		if ($ENABLE_DEBUG && isset($logger)) {
			# Log only if debug mode is enabled and logger object is created
			$logger($text);
		}
		exit();
	}
}

/** 
 * Array for serializing response credit scores
 */
$creditScoreValues = array(
	'Excellent' => 'Excellent (720+)',
	'Good' => 'Good (650 - 720)',
	'Fair' => 'Fair (580 - 650)',
	'Poor' => 'Poor (Below 580)'
);

/*
* Array of possible values and respective fields for req body, update according to client's requirements
*  Default params => first_name, last_name, email, phone, address, city, state, postal_code, comments, tags, call_notes, recording_url, dispo, agent
*  Frequently used custom params => contact_id, avg_electric_bill, credit_score, roof_age, roof_type, shading, willing_remove_tree, list_id, lead_id, list_name, campaign
* 	Email or Phone are required to create contact
*/

/*
*VICIdial 
	Excellent, Excellent Above 720
	Good, Good 650 - 720
	Fair, Fair 580 - 650
	Poor, Poor Below 580
*GHL
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
	$tag = ['cc appt'];
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
$headers  = array('Authorization: Bearer ' . $API_KEY, 'Content-Type: application/json');

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
					"avg_electric_bill": "' . $avgElectricBill . '",
					"will_cut_trees": "' . $willingRemoveTree . '",
			    }
			}';
		$contactDetail = exec_curl($ENDPOINT, 'POST', $fields, $headers);

		$webhookLogEntry = null;

		if ($ENABLE_DEBUG) {
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
		$endpoint = $ENDPOINT . $contactId;
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
					"avg_electric_bill": "' . $avgElectricBill . '",
					"will_cut_trees": "' . $willingRemoveTree . '",
			    }
			}';
		$contactDetail = exec_curl($endpoint, 'PUT', $fields, $headers);

		$webhookLogEntry = null;

		if ($ENABLE_DEBUG) {
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
	$headers  = array('Authorization: Bearer ' . $API_KEY, 'Content-Type: application/json');
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
				"avg_electric_bill": "' . $avgElectricBill . '",
				"will_cut_trees": "' . $willingRemoveTree . '",
		      "vicidial_lead_id": "' . $leadId . '",
		      "vicidial_list_id": "' . $listId . '",
		      "vicidial_list_name": "' . $listName . '"
		    }
		}';
	$contactDetail = exec_curl($ENDPOINT, 'POST', $fields, $headers);
	$webhookLogEntry = null;

	if ($ENABLE_DEBUG) {
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
$fields   = '{"body": "Disposition: ' . $disposition . ' \\n Agent: ' . $agent . ' \\n Call Notes: ' . str_replace("\n", "\\n", $call_notes) . ' \\n Recording URL: ' . $recording_url . '"}';

$notesResponse = exec_curl($endpoint, 'POST', $headers, $fields);

if ($ENABLE_DEBUG) {
	$logger = createLogger("log_notes_response.txt");
	if (!array_key_exists("createdAt", $notesResponse)) {
		$text = " Notes not added. " . json_encode($notesResponse);
		$logger($text);
	} else {
		$text = " Notes added. Notes Id: " . $notesResponse['id'];
		$logger($text);
	}
}

/****************
 * adding notes *
 ****************/
exit();
