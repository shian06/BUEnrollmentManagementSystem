<?php
// Database connection settings.
// These are XAMPP's defaults: user "root" with no password.
// Change them here if your MySQL uses a different user or password.
$DB_HOST = 'localhost';
$DB_NAME = 'bu_enrollment';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<h2>Cannot connect to the database.</h2>'
      . '<p>Make sure MySQL is running in XAMPP and that you imported '
      . '<b>bu_enrollment_system.sql</b> in phpMyAdmin.</p>'
      . '<p><small>' . htmlspecialchars($e->getMessage()) . '</small></p>');
}
