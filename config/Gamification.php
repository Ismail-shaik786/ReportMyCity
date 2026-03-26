<?php
/**
 * ReportMyCity — Gamification & Engagement Engine
 */
require_once __DIR__ . '/database.php';

class Gamification {
    // Points Configuration
    private static $rules = [
        'valid_complaint'   => 10,
        'with_evidence'     => 5,
        'high_priority'     => 15,
        'resolved'          => 20,
        'feedback_given'    => 5,
        'daily_login'       => 2,
        'fake_complaint'    => -20
    ];

    // Level Configuration
    private static $levels = [
        ['name' => 'Beginner',           'min' => 0,    'max' => 50],
        ['name' => 'Active Citizen',     'min' => 51,   'max' => 150],
        ['name' => 'Responsible Citizen', 'min' => 151,  'max' => 300],
        ['name' => 'Civic Leader',       'min' => 301,  'max' => 600],
        ['name' => 'Civic Hero',         'min' => 601,  'max' => 999999]
    ];

    /**
     * Awards points to a user based on an action key.
     */
    public static function awardPoints($userId, $action, $additionalPoints = 0) {
        if (!$userId) return false;
        
        $db = Database::getInstance();
        $usersCol = $db->getCollection('users');
        
        $pointsToAdd = (self::$rules[$action] ?? 0) + $additionalPoints;
        if ($pointsToAdd == 0) return false;

        try {
            $user = $usersCol->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            if (!$user) return false;

            $newPoints = ($user['points'] ?? 0) + $pointsToAdd;
            if ($newPoints < 0) $newPoints = 0; 

            $newLevel = self::calculateLevel($newPoints);

            // Update User
            $usersCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => [
                    'points' => $newPoints,
                    'level'  => $newLevel
                ]]
            );

            // Check for Badges
            self::checkBadges($userId, $newPoints, $user);

            return [
                'success' => true,
                'points_earned' => $pointsToAdd,
                'total_points' => $newPoints,
                'level' => $newLevel
            ];
        } catch (Exception $e) {
            error_log("Gamification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deduct points for fake/invalid complaints
     */
    public static function penalize($userId, $reason = 'fake_complaint') {
        if (!$userId) return false;

        $db = Database::getInstance();
        $users = $db->getCollection('users');
        
        $penalty = self::$rules[$reason] ?? -20;
        if ($penalty > 0) $penalty = -$penalty;

        try {
            $user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            if (!$user) return false;

            $newPoints = ($user['points'] ?? 0) + $penalty;
            if ($newPoints < 0) $newPoints = 0;

            $newLevel = self::calculateLevel($newPoints);
            
            $users->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => [
                    'points' => $newPoints,
                    'level'  => $newLevel
                ]]
            );
            
            return ['points_deducted' => $penalty, 'current_points' => $newPoints, 'level' => $newLevel];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Logic to award badges automatically
     */
    private static function checkBadges($userId, $points, $userDoc) {
        $db = Database::getInstance();
        $usersCol = $db->getCollection('users');
        $complaintsCol = $db->getCollection('complaints');
        
        $currentBadges = isset($userDoc['badges']) ? (array)$userDoc['badges'] : [];
        $newBadges = [];

        // 1. First Reporter
        if (!in_array('First Reporter', $currentBadges)) {
            $count = $complaintsCol->countDocuments(['user_id' => $userId]);
            if ($count >= 1) $newBadges[] = 'First Reporter';
        }

        // 2. Problem Solver (10 resolved)
        if (!in_array('Problem Solver', $currentBadges)) {
            $count = $complaintsCol->countDocuments(['user_id' => $userId, 'status' => 'Resolved']);
            if ($count >= 10) $newBadges[] = 'Problem Solver';
        }

        // 3. Community Helper (300+ points)
        if (!in_array('Community Helper', $currentBadges) && $points >= 300) {
            $newBadges[] = 'Community Helper';
        }

        // 4. Civic Hero Level Badge
        if (!in_array('Civic Hero Badge', $currentBadges) && $points >= 600) {
            $newBadges[] = 'Civic Hero Badge';
        }

        if (!empty($newBadges)) {
            $usersCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$addToSet' => ['badges' => ['$each' => $newBadges]]]
            );
        }
    }

    /**
     * Helper to calculate level based on points
     */
    public static function calculateLevel($points) {
        $foundLevel = 'Beginner';
        foreach (self::$levels as $lvl) {
            if ($points >= $lvl['min'] && $points <= $lvl['max']) {
                $foundLevel = $lvl['name'];
                break;
            }
        }
        return $foundLevel;
    }

    /**
     * Returns gamification stats for a user
     */
    public static function getUserStats($userId) {
        $db = Database::getInstance();
        $user = $db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        
        if (!$user) return null;

        $points = $user['points'] ?? 0;
        $levelData = null;
        $nextLevel = null;

        foreach (self::$levels as $index => $lvl) {
            if ($points >= $lvl['min'] && $points <= $lvl['max']) {
                $levelData = $lvl;
                $nextLevel = self::$levels[$index + 1] ?? null;
                break;
            }
        }

        $progress = 0;
        if ($nextLevel) {
            $range = $nextLevel['min'] - $levelData['min'];
            $relativePoints = $points - $levelData['min'];
            $progress = round(($relativePoints / $range) * 100);
        } else {
            $progress = 100;
        }

        return [
            'points' => $points,
            'level' => $user['level'] ?? 'Beginner',
            'badges' => $user['badges'] ?? [],
            'progress' => $progress,
            'next_level' => $nextLevel ? $nextLevel['name'] : 'Max Level',
            'points_to_next' => $nextLevel ? ($nextLevel['min'] - $points) : 0
        ];
    }
}
