<?php
session_start();
$_SESSION['role'] = 'national_admin';
$_SESSION['user_id'] = 'dummy';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action' => 'delete', 'complaint_id' => '650abcd12345678901234567'];

require 'api/update_status.php';
