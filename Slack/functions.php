<?php

function createLogger($file_name)
{
    $req_date = date('Y-m-d H:i:s');
    file_put_contents($file_name, "\n*******************TIMESTAMP : $req_date *********************\n", FILE_APPEND);
    return function ($message) use ($file_name) {
        file_put_contents($file_name, $message, FILE_APPEND);
    };
}



function processFields($result, $paramKeys)
{
    if (ENABLE_DEBUG) {
        $file_name = "log_params.txt";
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