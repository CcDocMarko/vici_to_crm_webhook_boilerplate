<?php
// functions.php

/**
 * Executes a cURL request.
 *
 * @param string $endpoint URL to execute
 * @param string $method   HTTP method (POST or GET)
 * @param array  $headers  Header information
 * @param string $fields   Post fields
 * @return array $response Decoded JSON response
 */
function exec_curl($endpoint, $method, $headers, $fields = '')
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_HTTPHEADER => $headers,
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return json_decode($response, true);
}

/**
 * Creates a logger function to write messages to a file.
 *
 * @param string $file_name Name of the log file
 * @return Closure Logger function
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

/**
 * Executes a cURL request to the given URL.
 *
 * @param string $url URL to request
 *
 * @return string $response cURL response
 */
function exec_curl_VICI($url)
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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return $response;
}


/**
 * Assigns the array of parameter values to a new one and returns it.
 * Optionally logs to a file.
 *
 * @param array $result    Array of results from URL request params or response body
 * @param array $paramKeys Array of variable keys to process
 * @return array $parseFields Processed fields
 */
function processFields($result, $paramKeys)
{
    if (ENABLE_DEBUG) {
        $file_name = "log_url_params.txt";
        $logger = createLogger($file_name);
    }

    if (!empty($result)) {
        $parseFields = [];
        foreach ($result as $key => $value) {
            if (in_array($key, $paramKeys)) {
                $parseFields[$key] = $value;
                if (ENABLE_DEBUG) {
                    $logMessage = "Request params has $key, with value: $value" . PHP_EOL;
                    $logger($logMessage);
                }
            }
        }
        return $parseFields;
    } else {
        $text = "\nEmpty Request. No Parameters in the Request.";
        if (ENABLE_DEBUG && isset($logger)) {
            $logger($text);
        }
        exit();
    }
}


/**
 * Rounds given time to the nearest half hour
 *
 * @param string $time Time in H:i format
 * @return string Rounded time in H:i format
 */
function roundToNearestHalfHour($time)
{
    list($hours, $minutes) = explode(':', $time);
    $hours = intval($hours);
    $minutes = intval($minutes);

    if ($minutes < 15) {
        $minutes = 0;
    } elseif ($minutes < 45) {
        $minutes = 30;
    } else {
        $minutes = 0;
        $hours++;
    }

    if ($hours >= 24) {
        $hours = $hours % 24;
    }

    $roundedTime = sprintf('%02d:%02d', $hours, $minutes);
    $dateTime = DateTime::createFromFormat('H:i', $roundedTime);

    if (!$dateTime) {
        return '';
    }

    return $dateTime->format('g:i A');
}


/**
 * Make a call to VICIdial API
 *
 * @param string $call_function The function to call
 * @param string $params        The parameters to pass to the function
 *
 * @return string The response from the API
 */
function vicidial_api($call_function = 'update_lead', $params = '')
{
    $url_build = DIALER_URL . '/vicidial/non_agent_api.php?' . 'source=Webhook' . '&user=' . VICIDIAL_USER . '&pass=' . VICIDIAL_PASS . '&function=' . $call_function . '&' . $params;

    return exec_curl_VICI($url_build);
}

/**
 * Updates a lead in VICIdial
 *
 * @param string $params The parameters to pass to the update_lead function
 *
 * @return string The response from the API
 */
function update_lead($params = '')
{
    return vicidial_api('update_lead', $params);
}

/**
 * Creates a new lead in VICIdial
 *
 * @param string $params The parameters to pass to the add_lead function
 *
 * @return string The response from the API
 */
function create_lead($params = '')
{
    return vicidial_api('add_lead', $params);
}


/**
 * Retrieves a recording URL from one of the VICIdial domains, given a recording path.
 * 
 * Tries each of the domains in VICIDIAL_DOMAINS and returns the first one that returns a 200 status code.
 * 
 * If no URLs are found after $maxAttempts, returns 'No recording URL'.
 * 
 * @param string $recordingPath The path to the recording
 * @return string The URL of the recording or 'No recording URL'
 */
function get_recording_url_domain($recordingPath)
{
    $recordingLogEntry = null;

    if (ENABLE_DEBUG) {
        $recordingLogEntry = createLogger('recording_urls_logs.txt');
    }

    $attempts = 0;
    $maxAttempts = 5;

    $hit_url = null;

    while (!$hit_url) {
        if ($attempts >= $maxAttempts) {
            if (ENABLE_DEBUG) {
                $recordingLogEntry("Exceeded maximum attempts ($maxAttempts) to retrieve the recording URL." . PHP_EOL);
            }
            break;
        }

        foreach (VICIDIAL_DOMAINS as $domain) {
            $recordingURL = $domain . $recordingPath;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $recordingURL);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

            curl_exec($ch);

            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $statuses[$recordingURL] = $statusCode;

            curl_close($ch);
        }

        $recordingLogEntry("Fetched responses" . json_encode($statuses) . PHP_EOL);

        foreach ($statuses as $url => $status) {
            if ($status === 200) {
                $hit_url = $url;
            }
        }


        if (!$hit_url) {
            $attempts++;
            if (ENABLE_DEBUG) {
                $recordingLogEntry("Attempt $attempts: No recording URL found. Retrying after 60 seconds..." . PHP_EOL);
            }
            sleep(60);
        }
    }

    if ($hit_url) {
        if (ENABLE_DEBUG) {
            $recordingLogEntry("Successfully retrieved the recording URL after $attempts attempts." . PHP_EOL);
        }
        return $hit_url;
    } else {
        if (ENABLE_DEBUG) {
            $recordingLogEntry("Failed to retrieve the recording URL after $maxAttempts attempts." . PHP_EOL);
        }
        return 'No recording URL';
    }
}

/**
 * Fetches the latest recording URL for a given agent.
 *
 * @param string $agent The login name of the agent.
 *
 * @return array An associative array containing the recording_id and recording_url
 *               of the latest recording for the given agent on the current day.
 */
function get_latest_recording_details($agent)
{
    $localTime = new DateTime();
    $date_only = $localTime->format('Y-m-d');

    $params = [
        'source' => 'Webhook',
        'stage' => 'pipe',
        'agent_user' => $agent,
        'date' => $date_only
    ];

    $recordingLogEntry = null;
    $encoded_params = http_build_query($params);

    $response = vicidial_api('recording_lookup', $encoded_params);
    $lines = explode("\n", trim($response));
    $lastRecord = end($lines);
    $record = explode('|', $lastRecord);
    if (ENABLE_DEBUG) {
        $recordingLogEntry = createLogger('recording_log.txt');
        $recordingLogEntry('Last record: ' . $lastRecord . PHP_EOL);
    }
    return $record;
}
