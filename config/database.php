<?php
$host    = 'localhost';
$dbname  = 'folders_vergelijker';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('<p style="color:red;padding:2rem;font-family:sans-serif;">Databasefout: ' . htmlspecialchars($e->getMessage()) . '</p>');
}
