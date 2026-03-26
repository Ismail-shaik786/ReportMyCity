<?php
session_start();
$_SESSION['user_id'] = 'dummy';
$_SESSION['role'] = 'national_admin';

$data = [
    'complaint_id' => '650abcd12345678901234567', // Fake ID
    'action' => 'delete'
];

$ch = curl_init('http://localhost:8000/api/update_status.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: PHPSESSID=' . session_id()
]);

$response = curl_exec($ch);
echo "Response: $response\n";
