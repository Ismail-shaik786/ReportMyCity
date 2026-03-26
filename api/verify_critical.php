<?php
/**
 * ReportMyCity API — Verify Critical Complaint
 */
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Load .env if not already loaded
if (!isset($_ENV['SMTP_HOST'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$complaintId = $data['complaint_id'] ?? '';
$action      = $data['action'] ?? '';
$officerId   = $data['officer_id'] ?? '';

if (!$complaintId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$db = Database::getInstance();
$complaintsCol = $db->getCollection('complaints');
$officersCol   = $db->getCollection('officers');
$notifCol      = $db->getCollection('notifications');

try {
    $cIdObj = new MongoDB\BSON\ObjectId($complaintId);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$complaint = $complaintsCol->findOne(['_id' => $cIdObj]);
if (!$complaint) {
    echo json_encode(['success' => false, 'message' => 'Complaint not found']);
    exit;
}

if ($action === 'verify') {
    if (!$officerId) {
        echo json_encode(['success' => false, 'message' => 'Officer must be assigned for verified critical issues.']);
        exit;
    }
    
    // Find officer name
    $offObj = null;
    try { $offObj = new MongoDB\BSON\ObjectId($officerId); } catch(Exception $e){}
    $officer = $officersCol->findOne(['_id' => $offObj]);
    $officerName = $officer ? $officer['name'] : 'Unknown Officer';

    $updateData = [
        'is_verified_critical'  => true,
        'assigned_officer_id'   => $officerId,
        'assigned_officer_name' => $officerName,
        'status'                => 'In Progress', // Fast tracked
        'admin_reply'           => 'This issue has been verified as CRITICAL and prioritized. An officer has been dispatched immediately.'
    ];

    $complaintsCol->updateOne(
        ['_id' => $cIdObj],
        ['$set' => $updateData]
    );

    // Notify Officer
    $notifCol->insertOne([
        'user_id'    => $officerId,
        'role'       => 'officer',
        'message'    => "<i class="la la-bell"></i> URGENT: You have been assigned a VERIFIED CRITICAL complaint: " . $complaint['title'],
        'is_read'    => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Notify User
    $notifCol->insertOne([
        'user_id'    => $complaint['user_id'],
        'role'       => 'user',
        'message'    => "<i class="la la-bell"></i> Your critical complaint has been VERIFIED and immediately assigned to " . $officerName,
        'is_read'    => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Send Email to the Assigned Officer using PHPMailer
    $officerEmail = $officer['email'] ?? '';
    if (!empty($officerEmail)) {
        $mail = new PHPMailer(true);
        try {
            // Server settings — loaded from .env
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST']     ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
            $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);

            // Recipients
            $mail->setFrom(
                $_ENV['SMTP_FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'] ?? '',
                $_ENV['SMTP_FROM_NAME']  ?? 'ReportMyCity Admin'
            );
            $mail->addAddress($officerEmail, $officerName);

            // Attach Citizen's Uploaded Photo if it exists
            if (!empty($complaint['image'])) {
                $imagePath = __DIR__ . '/../' . ltrim($complaint['image'], '/');
                if (file_exists($imagePath)) {
                    $mail->addAttachment($imagePath, 'Citizen_Uploaded_Issue_Photo.jpg');
                }
            }

            // Generate Map Tracking URL
            $mapLink = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($complaint['location'] ?? '');

            // Content
            $mail->isHTML(true);
            $mail->Subject = '<i class="la la-bell"></i> URGENT: Critical ReportMyCity Complaint Assigned to You';
            $mail->Body    = "<div style=\"font-family: Arial, sans-serif; color: #333; line-height: 1.6;\">" .
                             "<h2 style=\"color: #ef4444;\"><i class="la la-bell"></i> Critical Response Required</h2>" .
                             "<p>Hello <strong>" . htmlspecialchars($officerName) . "</strong>,</p>" .
                             "<p>A critical complaint has just been verified by the Admin and the fast-tracked status is now <strong>ACTIVE</strong>.</p>" .
                             "<p>You have been assigned to handle this issue immediately. Please review the details below:</p>" .
                             "<ul style=\"background: #f8fafc; padding: 15px 15px 15px 30px; border-radius: 5px; border-left: 4px solid #3b82f6;\">" .
                             "<li><strong>Tracking ID:</strong> #" . substr((string)$complaint['_id'], -6) . "</li>" .
                             "<li><strong>Title:</strong> " . htmlspecialchars($complaint['title']) . "</li>" .
                             "<li><strong>Category:</strong> " . htmlspecialchars($complaint['category']) . "</li>" .
                             "<li><strong>Location:</strong> " . htmlspecialchars($complaint['location']) . "</li>" .
                             "</ul>" .
                             "<p><strong>Citizen's Description:</strong><br><span style=\"color: #555;\">" . nl2br(htmlspecialchars($complaint['description'])) . "</span></p>" .
                             "<p style=\"margin: 25px 0;\"><a href=\"{$mapLink}\" style=\"display: inline-block; padding: 12px 20px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;\"><i class="la la-map"></i> Start Live Tracking & Navigation on Google Maps</a></p>" .
                             "<p><em>Note: If the citizen uploaded a photo, it has been attached to this email.</em></p>" .
                             "<p>Please check your Field Officer Portal and attend to this at your earliest priority.</p>" .
                             "<p><strong>ReportMyCity Administration</strong></p>" .
                             "</div>";

            // Plain text fallback
            $mail->AltBody = "Hello " . $officerName . ",\n\n" .
                             "A critical complaint has just been verified by the Admin and fast-tracked status is ACTIVE.\n" .
                             "You have been assigned to handle this issue immediately.\n\n" .
                             "Title: " . $complaint['title'] . "\n" .
                             "Category: " . $complaint['category'] . "\n" .
                             "Location: " . $complaint['location'] . "\n" .
                             "Description: " . $complaint['description'] . "\n\n" .
                             "<i class="la la-map"></i> Track & Navigate Location: " . $mapLink . "\n\n" .
                             "(See attachments for any uploaded photos.)\n\n" .
                             "Please check your Field Officer Portal and attend to this at your earliest priority.\n\n" .
                             "ReportMyCity Administration";

            $mail->send();
            $emailSent = true;
        } catch (Exception $e) {
            // Optionally log error
            error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
            $emailSent = false;
        }
    }

    $msg = 'Complaint verified and officer dispatched urgently!';
    if (isset($emailSent)) {
        if ($emailSent) {
            $msg .= ' Email sent successfully to the officer.';
        } else {
            $msg .= ' However, the email failed to send.';
        }
    }

    echo json_encode(['success' => true, 'message' => $msg]);
} elseif ($action === 'downgrade') {
    $complaintsCol->updateOne(
        ['_id' => $cIdObj],
        ['$set' => [
            'risk_type' => 'Medium',
            'is_verified_critical' => false,
            'admin_reply' => 'The risk level of this complaint has been reassessed to Medium. It will be scheduled for standard assignment.'
        ]]
    );

    // Notify User
    $notifCol->insertOne([
        'user_id'    => $complaint['user_id'],
        'role'       => 'user',
        'message'    => "Your complaint '" . $complaint['title'] . "' was reviewed and reassessed as Medium risk.",
        'is_read'    => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    echo json_encode(['success' => true, 'message' => 'Complaint risk downgraded to Medium.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
