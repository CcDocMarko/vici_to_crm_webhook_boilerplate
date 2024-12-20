<?php
/*
Credentials for Zoho clients will be hardcoded below.
*/
$clients = [
    'sierra' => [
        'client_id' => 'SIERRA_CLIENT_ID',
        'client_secret' => 'SIERRA_CLIENT_SECRET',
        'refresh_token' => 'SIERRA_REFRESH_TOKEN',
        'token_file' => 'sierra_token.json'
    ],
    'sunshine' => [
        'client_id' => 'SUNSHINE_CLIENT_ID',
        'client_secret' => 'SUNSHINE_CLIENT_SECRET',
        'refresh_token' => 'SUNSHINE_REFRESH_TOKEN',
        'token_file' => 'sunshine_token.json'
    ]
];

// Zoho OAuth token refresh URL
$token_url = "https://accounts.zoho.com/oauth/v2/token";

// Function to refresh and save access tokens
function refreshAccessToken($token_url, $client_name, $client_data) {
    // Prepare data for the token request
    $data = [
        'grant_type' => 'refresh_token',
        'client_id' => $client_data['client_id'],
        'client_secret' => $client_data['client_secret'],
        'refresh_token' => $client_data['refresh_token']
    ];

    // Initialize cURL
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    // Execute the request and get the response
    $response = curl_exec($ch);

    if ($response === false) {
        error_log("[$client_name] cURL Error: " . curl_error($ch));
        curl_close($ch);
        return;
    }

    // Close cURL connecti
    curl_close($ch);

    // Decode the response JSON
    $response_data = json_decode($response, true);

    // Check if the access_token is available
    if (isset($response_data['access_token'])) {
        $access_token = $response_data['access_token'];

        // Prepare the data to write to the file
        $token_data = [
            'access_token' => $access_token,
            'fetched_at' => date('Y-m-d H:i:s') // Timestamp for tracking
        ];

        // Write the token data to the respective JSON file
        if (file_put_contents($client_data['token_file'], json_encode($token_data, JSON_PRETTY_PRINT))) {
            error_log("[$client_name] Access token saved to {$client_data['token_file']} successfully.");
        } else {
            error_log("[$client_name] Error writing the access token to {$client_data['token_file']}.");
        }
    } else {
        // Handle error if the access token is not returned
        $error_message = $response_data['error'] ?? 'Unknown error';
        error_log("[$client_name] Error fetching access token: $error_message");
    }
}

// Loop through each client to refresh and save their tokens
foreach ($clients as $client_name => $client_data) {
    refreshAccessToken($token_url, $client_name, $client_data);
}

error_log("Token refresh process completed for all clients.");