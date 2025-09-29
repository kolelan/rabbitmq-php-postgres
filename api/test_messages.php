<?php
// api/test_messages.php

echo "Testing POST /messages endpoint...\n\n";

// Test 1: Create a custom message
$data1 = [
    'action' => 'send_email',
    'data' => [
        'to' => 'user@example.com',
        'subject' => 'Welcome Email',
        'body' => 'Welcome to our service!'
    ],
    'queue' => 'email_queue',
    'priority' => 'high',
    'metadata' => [
        'source' => 'api_test',
        'timestamp' => date('c')
    ]
];

$json1 = json_encode($data1);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json1)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Test 1 - Custom Email Message:\n";
echo "HTTP Code: $httpCode1\n";
echo "Response: $response1\n\n";

// Test 2: Create a notification message
$data2 = [
    'action' => 'send_notification',
    'data' => [
        'user_id' => 123,
        'type' => 'info',
        'title' => 'System Update',
        'message' => 'The system will be updated tonight at 2 AM.'
    ],
    'queue' => 'notification_queue'
];

$json2 = json_encode($data2);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json2)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Test 2 - Notification Message:\n";
echo "HTTP Code: $httpCode2\n";
echo "Response: $response2\n\n";

// Test 3: Create a data processing message
$data3 = [
    'action' => 'process_data',
    'data' => [
        'file_path' => '/uploads/data.csv',
        'format' => 'csv',
        'options' => [
            'delimiter' => ',',
            'encoding' => 'utf-8'
        ]
    ],
    'queue' => 'data_queue',
    'priority' => 'normal'
];

$json3 = json_encode($data3);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json3);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json3)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response3 = curl_exec($ch);
$httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Test 3 - Data Processing Message:\n";
echo "HTTP Code: $httpCode3\n";
echo "Response: $response3\n\n";

// Test 4: Error case - missing action
$data4 = [
    'data' => [
        'test' => 'missing action'
    ]
];

$json4 = json_encode($data4);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json4);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json4)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response4 = curl_exec($ch);
$httpCode4 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Test 4 - Error Case (Missing Action):\n";
echo "HTTP Code: $httpCode4\n";
echo "Response: $response4\n\n";

echo "All tests completed!\n";
