<?php
/**
 * ReportMyCity API — AI Conversational Agent
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$db = Database::getInstance();
$complaintsCol = $db->getCollection('complaints');
$usersCol = $db->getCollection('users');

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$userId = $_SESSION['user_id'];

// State Machine
if (!isset($_SESSION['ai_chat_state'])) {
    $_SESSION['ai_chat_state'] = 'idle';
    $_SESSION['ai_chat_data'] = [];
}

$state = $_SESSION['ai_chat_state'];
$reply = "";
$suggestions = [];
$action = "";

// Handle Commands
if ($message === 'RESET_SESSION') {
    $_SESSION['ai_chat_state'] = 'idle';
    $_SESSION['ai_chat_data'] = [];
    echo json_encode(['success' => true, 'reply' => 'Session Reset']);
    exit;
}

if (strpos($message, 'MY_LOCATION:') === 0) {
    $_SESSION['ai_chat_data']['location'] = substr($message, 12);
    $reply = "Got it! Your location has been recorded as: " . $_SESSION['ai_chat_data']['location'] . ". \n\nWhat is the **title** or a brief summary of your complaint? (e.g., 'Broken Streetlight' or 'Garbage Overflow')";
    $_SESSION['ai_chat_state'] = 'awaiting_title';
} elseif ($message === 'CHECK_STATUS') {
    $userComplaints = $complaintsCol->find(['user_id' => $userId], ['limit' => 3, 'sort' => ['created_at' => -1]]);
    $list = iterator_to_array($userComplaints);
    if (empty($list)) {
        $reply = "I couldn't find any recent complaints from you. Would you like to file a new one?";
        $suggestions = [['label' => '<i class="la la-bullhorn"></i> File Complaint', 'value' => 'report_complaint']];
    } else {
        $reply = "Here are your latest complaints:\n";
        foreach ($list as $c) {
            $status = $c['status'] ?? 'Pending';
            $reply .= "• **" . $c['title'] . "**: " . $status . "\n";
        }
        $reply .= "\nIs there anything else I can help with?";
    }
} else {
    // Standard Conversation State Logic
    switch ($state) {
        case 'idle':
            if (stripos($message, 'report') !== false || stripos($message, 'complaint') !== false) {
                $reply = "I can help you file a complaint. First, I'll need your location. Can you provide it?";
                $suggestions = [['label' => '<i class="la la-map-marker"></i> Use Live Location', 'value' => 'report_complaint']];
            } else {
                $reply = "I'm not sure how to respond to that, but I'm great at filing complaints! Would you like to report an issue?";
                $suggestions = [['label' => '<i class="la la-bullhorn"></i> Report Issue', 'value' => 'report_complaint']];
            }
            break;

        case 'awaiting_title':
            $_SESSION['ai_chat_data']['title'] = $message;
            $reply = "Great. Now, please select the **category** for this issue:";
            $suggestions = [
                ['label' => '<i class="la la-road"></i> Road & Infrastructure', 'value' => 'Road & Infrastructure'],
                ['label' => '<i class="la la-trash"></i> Waste Management', 'value' => 'Waste Management'],
                ['label' => '<i class="la la-tint"></i> Water Supply', 'value' => 'Water Supply'],
                ['label' => '💡 Electricity', 'value' => 'Electricity'],
                ['label' => '🌳 Public Parks', 'value' => 'Public Parks']
            ];
            $_SESSION['ai_chat_state'] = 'awaiting_category';
            break;

        case 'awaiting_category':
            $_SESSION['ai_chat_data']['category'] = $message;
            $reply = "Got it: " . $message . ". Finally, can you give me a **detailed description** of the problem?";
            $_SESSION['ai_chat_state'] = 'awaiting_description';
            break;

        case 'awaiting_description':
            $_SESSION['ai_chat_data']['description'] = $message;
            $d = $_SESSION['ai_chat_data'];
            $reply = "Perfect! Here is what I have collected:\n\n" .
                     "<i class="la la-map-marker"></i> **Location**: " . $d['location'] . "\n" .
                     "<i class="la la-clipboard"></i> **Title**: " . $d['title'] . "\n" .
                     "🗂️ **Category**: " . $d['category'] . "\n" .
                     "📝 **Description**: " . $d['description'] . "\n\n" .
                     "Should I go ahead and submit this for you?";
            $suggestions = [
                ['label' => '<i class="la la-check-circle"></i> Yes, Submit it', 'value' => 'CONFIRM_SUBMIT'],
                ['label' => '<i class="la la-times"></i> No, Cancel', 'value' => 'CANCEL_SUBMIT']
            ];
            $_SESSION['ai_chat_state'] = 'awaiting_confirmation';
            break;

        case 'awaiting_confirmation':
            if ($message === 'CONFIRM_SUBMIT') {
                $d = $_SESSION['ai_chat_data'];
                $complaintsCol->insertOne([
                    'user_id' => $userId,
                    'title' => $d['title'],
                    'category' => $d['category'],
                    'description' => $d['description'],
                    'location' => $d['location'],
                    'status' => 'Pending',
                    'risk_type' => 'Medium', // AI default
                    'created_at' => date('Y-m-d H:i:s'),
                    'date' => date('Y-m-d')
                ]);
                $reply = "Your complaint has been successfully submitted! Our team will look into it shortly. 🚀";
                $action = "RELOAD";
                $_SESSION['ai_chat_state'] = 'idle';
                $_SESSION['ai_chat_data'] = [];
            } else {
                $reply = "Submission cancelled. How else can I help?";
                $_SESSION['ai_chat_state'] = 'idle';
                $_SESSION['ai_chat_data'] = [];
            }
            break;
    }
}

echo json_encode([
    'success' => true,
    'reply' => $reply,
    'suggestions' => $suggestions,
    'action' => $action
]);
