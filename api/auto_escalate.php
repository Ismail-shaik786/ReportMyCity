<?php
/**
 * ReportMyCity — Auto-Escalation Cron Script
 * 
 * Invoked to check for complaints exceeding SLAs and bumping them up
 * Default SLAs (hours):
 * Low => 72
 * Medium => 48
 * High => 24
 */
require_once __DIR__ . '/../config/database.php';

class EscalationEngine {
    public static function run() {
        $db = Database::getInstance();
        $complaints = $db->getCollection('complaints');
        $escalations = $db->getCollection('escalations');
        $actionLogs = $db->getCollection('action_logs');
        $notifications = $db->getCollection('notifications');

        $slaHours = [
            'Low' => 72,
            'Medium' => 48,
            'High' => 24,
            'Critical' => 12
        ];

        // Only escalate tickets that are Submitted or Under Review or In Progress
        // Meaning not Resolved/Closed/Rejected
        $openStatuses = ['Submitted', 'Pending', 'Under Review', 'In Progress'];
        $now = time();

        $cursor = $complaints->find(['status' => ['$in' => $openStatuses]]);
        $count = 0;

        foreach ($cursor as $c) {
            $priority = $c['priority'] ?? ($c['risk_type'] ?? 'Medium');
            $allowedHours = $slaHours[$priority] ?? 48;

            $createdAtStr = $c['created_at'] ?? null;
            if (!$createdAtStr) continue;

            $createdTime = strtotime($createdAtStr);
            $hoursPassed = ($now - $createdTime) / 3600;

            if ($hoursPassed > $allowedHours) {
                // Check if already escalated
                $isEscalated = $escalations->countDocuments(['complaint_id' => (string)$c['_id']]);
                
                if ($isEscalated == 0) {
                    // Escalate to Senior Officer / Admin
                    // We don't auto-assign to a Specific Senior Officer, we just flag it as Escalated
                    // Senior Officers query for `escalation_level > 0`
                    $complaints->updateOne(
                        ['_id' => $c['_id']],
                        ['$set' => ['status' => 'Escalated', 'escalation_level' => 1]]
                    );

                    $escalations->insertOne([
                        'complaint_id' => (string)$c['_id'],
                        'level' => 1,
                        'escalated_to' => 'Senior Officer',
                        'timestamp'    => date('Y-m-d H:i:s'),
                        'reason' => "SLA Breached ($allowedHours hours passed for $priority priority)"
                    ]);

                    $actionLogs->insertOne([
                        'complaint_id' => (string)$c['_id'],
                        'performed_by' => 'SYSTEM',
                        'role'         => 'system',
                        'action'       => 'Auto-Escalated',
                        'comment'      => "SLA Breached - Auto Escalated to Senior Officer level",
                        'timestamp'    => date('Y-m-d H:i:s')
                    ]);

                    $notifications->insertOne([
                        'role'       => 'senior_officer',
                        'message'    => "High Priority Escalation: Complaint #" . substr((string)$c['_id'], -6) . " breached SLA.",
                        'is_read'    => false,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    $count++;
                }
            }
        }
        return $count;
    }
}
