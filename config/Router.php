<?php
/**
 * ReportMyCity — Smart Complaint Router
 */
require_once __DIR__ . '/database.php';

class Router {
    /**
     * Strictly routes complaints to DEPARTMENT HEADS (senior_officer) only.
     * Field Officers (local_officer) are assigned later by their respective heads.
     */
    public static $deptMapping = [
        'Corruption & Bribery'      => 'Vigilance',
        'Roads & Infrastructure'    => 'Public Works (PWD)',
        'Water Supply Issues'       => 'Water Department',
        'Electricity Problems'      => 'Electricity Board',
        'Healthcare Complaints'     => 'Health Department',
        'Police Misconduct / Law & Order' => 'Police Department',
        'Sanitation & Garbage'      => 'Municipal Services',
        'Municipal Services'        => 'Municipal Services',
        'Education System Issues'   => 'Education Department',
        'Cybercrime / Online Fraud' => 'Cyber Cell',
        'Environmental Issues'      => 'Environment Board',
        'Women & Child Safety'      => 'Women & Child Dev',
        'Land & Property Disputes'  => 'Revenue Dept',
        'Government Scheme Issues'  => 'Social Welfare',
        'Public Transport Issues'   => 'Transport Department',
        'Tax / Revenue Issues'      => 'Revenue Dept',
        'Other'                     => 'General Administration'
    ];

    public static function getDepartment($category) {
        // If it's a legacy category, return the mapped department
        if (isset(self::$deptMapping[$category])) {
            return self::$deptMapping[$category];
        }
        
        // Otherwise, assume the category IS the department name (dynamic routing)
        return $category;
    }

    /**
     * Strictly routes complaints to DEPARTMENT HEADS (senior_officer) only.
     * Field Officers (local_officer) are assigned later by their respective heads.
     */
    public static function assignComplaint($category, $userDistrict = null, $userState = null, $accusedRole = null) {
        $db = Database::getInstance();
        $officers = $db->getCollection('head_officers');

        $targetDept = self::getDepartment($category);
        
        // RULE: New complaints go to senior_officer (Department Head)
        $query = [
            'department' => $targetDept,
            'role'       => 'senior_officer'
        ];

        // Regional Filtering: Try to find Head in the user's state
        if ($userState) {
            $query['state'] = $userState;
        }

        // Fetch all eligible Department Heads
        $cursor = $officers->find($query);
        $heads = iterator_to_array($cursor);

        if (empty($heads) && $userState) {
            // Fallback: If no head in that state, find ANY head in that dept
            unset($query['state']);
            $heads = iterator_to_array($officers->find($query));
        }

        if (empty($heads)) {
            // Last Fallback: Assign to ANY senior_officer to ensure oversight
            $heads = iterator_to_array($officers->find(['role' => 'senior_officer']));
        }

        if (!empty($heads)) {
            // Assign to the first found head (usually one per state/dept)
            return (string)$heads[0]['_id'];
        }

        return null; // Manual assignment by State/National Admin required
    }

    /**
     * Standardizes complaint lifecycle
     */
    public static function standardizeStatus($status) {
        $valid = ['Submitted', 'Assigned', 'In Progress', 'Resolved', 'Closed'];
        if (in_array($status, $valid)) return $status;

        $map = [
            'Pending' => 'Submitted',
            'Officer Completed' => 'Resolved',
        ];

        return $map[$status] ?? 'Submitted';
    }
}
