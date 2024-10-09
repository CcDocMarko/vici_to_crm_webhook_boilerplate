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
            // Log only if debug mode is enabled and logger object is created
            $logger($text);
        }
        exit();
    }
}