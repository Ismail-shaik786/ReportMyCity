<?php
/**
 * ReportMyCity — MongoDB Database Connection
 * 
 * Provides a singleton connection to the MongoDB database
 * and helper functions to access collections.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env variables only if they exist (local development)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use MongoDB\Client;

class Database {
    private static $instance = null;
    private $client;
    private $db;

    private function __construct() {
        try {
            $mongoUri = $_ENV['MONGO_URI'] ?? 'mongodb://localhost:27017';
            $mongoDb  = $_ENV['MONGO_DB']  ?? 'reportmycity';

            // Explicitly set a short timeout for initial connection check
            $this->client = new Client($mongoUri, [
                'serverSelectionTimeoutMS' => 2000
            ]);
            // Attempt a simple command to verify connection
            $this->client->listDatabases();
            $this->db = $this->client->$mongoDb;
        } catch (Exception $e) {
            header('Content-Type: text/plain');
            die("ReportMyCity Error: Database connection failed. Please ensure MongoDB is running.\nDetails: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDatabase() {
        return $this->db;
    }

    public function getCollection($name) {
        return $this->db->$name;
    }

    /**
     * Seed default admin if none exists
     */
    public function seedAdmin() {
        try {
            $adminEmail    = $_ENV['ADMIN_EMAIL']    ?? 'admin@reportmycity.com';
            $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';

            $admins   = $this->getCollection('admins');
            $existing = $admins->findOne(['email' => $adminEmail]);
            if (!$existing) {
                $admins->insertOne([
                    'name'       => 'Admin',
                    'email'      => $adminEmail,
                    'password'   => password_hash($adminPassword, PASSWORD_BCRYPT),
                    'role'       => 'admin',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            // Seed failed (possibly validation schema) — skip silently
        }
    }

    /**
     * Seed default officer if none exists
     */
    public function seedOfficer() {
        try {
            $officerEmail    = $_ENV['OFFICER_EMAIL']    ?? 'officer@reportmycity.com';
            $officerPassword = $_ENV['OFFICER_PASSWORD'] ?? 'officer123';

            $officers = $this->getCollection('officers');
            $existing = $officers->findOne(['email' => $officerEmail]);
            if (!$existing) {
                $officers->insertOne([
                    'name'       => 'Default Officer',
                    'email'      => $officerEmail,
                    'phone'      => '',
                    'password'   => password_hash($officerPassword, PASSWORD_BCRYPT),
                    'role'       => 'officer',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            // Seed failed (possibly validation schema) — skip silently
        }
    }
}

// Auto-seed admin and officer on first load
$database = Database::getInstance();
$database->seedAdmin();
$database->seedOfficer();
