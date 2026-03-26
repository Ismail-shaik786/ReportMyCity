<?php
/**
 * Migration Script — Split Officers into Head & Field Collections
 */
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$oldOfficers = $db->getCollection('officers');
$headOfficers = $db->getCollection('head_officers');
$fieldOfficers = $db->getCollection('field_officers');

$countHead = 0;
$countField = 0;

$cursor = $oldOfficers->find();
foreach ($cursor as $o) {
    $role = $o['role'] ?? 'local_officer';
    $isHead = in_array($role, ['senior_officer', 'admin', 'national_admin', 'state_admin', 'district_admin']);
    
    if ($isHead) {
        // Since we are splitting, we might want to check for duplicates
        $headOfficers->replaceOne(['email' => $o['email']], $o, ['upsert' => true]);
        $countHead++;
    } else {
        $fieldOfficers->replaceOne(['email' => $o['email']], $o, ['upsert' => true]);
        $countField++;
    }
}

echo "Migration Complete!\n";
echo "Processed $countHead Head/Admin records to 'head_officers'.\n";
echo "Processed $countField field officer records to 'field_officers'.\n";
echo "Note: The original 'officers' collection remains intact for safety. You can remove it once verified.\n";
