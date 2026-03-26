<?php
/**
 * ReportMyCity — Migration: Gamification Fields
 */
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$usersCol = $db->getCollection('users');

$cursor = $usersCol->find();
$count = 0;

foreach ($cursor as $user) {
    if (!isset($user['points']) || !isset($user['level'])) {
        $usersCol->updateOne(
            ['_id' => $user['_id']],
            ['$set' => [
                'points' => 0,
                'level'  => 'Beginner',
                'badges' => [],
                'total_points_earned' => 0,
                'last_login_at' => null
            ]]
        );
        $count++;
    }
}

echo "Successfully updated $count users with gamification fields.\n";
