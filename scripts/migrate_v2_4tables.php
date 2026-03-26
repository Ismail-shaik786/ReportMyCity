<?php
/**
 * Migration Script — Final Tier Splitting (4 Collections)
 */
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();

// Source collections (from v1 migration)
$headSource = $db->getCollection('head_officers');
$fieldSource = $db->getCollection('field_officers');

// Target collections
$admins = $db->getCollection('admins'); // National Admin
$stateAdmins = $db->getCollection('state_admins');
$headOfficers = $db->getCollection('head_officers_new'); // Temporary to avoid overlap
$fieldOfficers = $db->getCollection('field_officers_new');

function migrateDoc($doc, $target) {
    $target->replaceOne(['email' => $doc['email']], $doc, ['upsert' => true]);
}

$sources = [$headSource, $fieldSource];
foreach ($sources as $source) {
    foreach ($source->find() as $doc) {
        $role = $doc['role'] ?? 'local_officer';
        
        if (in_array($role, ['admin', 'national_admin'])) {
            migrateDoc($doc, $admins);
        } elseif ($role === 'state_admin') {
            migrateDoc($doc, $stateAdmins);
        } elseif (in_array($role, ['senior_officer', 'district_admin'])) {
            migrateDoc($doc, $headOfficers);
        } else {
            // officer, local_officer
            migrateDoc($doc, $fieldOfficers);
        }
    }
}

// Rename collections (Safety First: drop original ones if verified, but here we just swap)
// Note: MongoDB doesn't rename across DBs, but within DB it's fine.
// But we use new names for simplicity to avoid confusion during migration.

echo "Migration v2 Complete!\n";
echo "Collections populated: admins, state_admins, head_officers_new, field_officers_new.\n";
echo "Please verify and then swap 'head_officers_new' -> 'head_officers' etc.\n";
