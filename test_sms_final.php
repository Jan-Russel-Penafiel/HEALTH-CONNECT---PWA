<?php
/**
 * Final SMS Test Script
 * Tests the IPROG SMS API with shortened messages
 */

// Test message (short version)
$message = "This is an important message from the Organization. Hello Test, reminder: Appointment on Dec 28 at 10:00 AM. Please arrive early. Thank you. - Respective Personnel";

echo "Message length: " . strlen($message) . "\n\n";

$api_url = "https://sms.iprogtech.com/api/v1/sms_messages";
$api_key = "1ef3b27ea753780a90cbdf07d027fb7b52791004";
$phone_number = "09760595056";

$data = [
    'api_token' => $api_key,
    'phone_number' => $phone_number,
    'message' => $message
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";

if ($error) {
    echo "cURL Error: $error\n";
}

// Parse and check response
$result = json_decode($response, true);
if ($result) {
    if (isset($result['status']) && $result['status'] === 500) {
        echo "\n❌ API ERROR: " . ($result['message'] ?? 'Unknown error') . "\n";
    } else if (isset($result['status']) && $result['status'] === 200) {
        echo "\n✅ SMS SENT SUCCESSFULLY!\n";
        echo "Message ID: " . ($result['message_id'] ?? 'N/A') . "\n";
        echo "SMS Rate: " . ($result['sms_rate'] ?? 'N/A') . "\n";
    }
}
