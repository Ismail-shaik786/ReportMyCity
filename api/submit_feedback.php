<?php
/**
 * ReportMyCity API — Submit User Feedback & Rating
 */
require_once __DIR__ . '/../config/database.php';
use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintId = $_POST['complaint_id'] ?? '';
    $rating = intval($_POST['rating'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');
    $userId = $_SESSION['user_id'];

    if (empty($complaintId) || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid rating or complaint ID.']);
        exit;
    }

    try {
        $db = Database::getInstance();
        $complaints = $db->getCollection('complaints');

        // Check if complaint belongs to this user and is resolved
        $complaint = $complaints->findOne([
            '_id' => new \MongoDB\BSON\ObjectId($complaintId),
            'user_id' => $userId,
            'status' => 'Resolved'
        ]);

        if (!$complaint) {
            echo json_encode(['success' => false, 'message' => 'Complaint not found or not eligible for feedback.']);
            exit;
        }

        // Update with rating and feedback
        $result = $complaints->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($complaintId)],
            ['$set' => [
                'rating' => $rating,
                'user_feedback' => $feedback,
                'feedback_at' => date('Y-m-d H:i:s')
            ]]
        );

        if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully. Thank you!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update feedback.']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
