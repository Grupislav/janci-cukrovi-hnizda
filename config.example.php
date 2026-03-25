<?php
/**
 * Zkopíruj jako config.php a doplň hodnoty. Soubor config.php je v .gitignore.
 */
// define('JANCI_CUKROVI_DEBUG', true); // jen lokálně – výpisy chyb v send_order.php

/** false = pouze stránka údržby; true = formulář v provozu */
$siteEnabled = false;

// Wedos MySQL (příklad): host md394.wedos.net, DB d199169_*, uživatel w199169_*
$host    = 'localhost';
$db      = 'databaze';
$user    = 'uzivatel';
$pass    = 'heslo';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('janci-cukrovi-hnizda DB: ' . $e->getMessage());
}

$ownerEmail = 'vas@email.cz';

$mailFrom = 'Janči cukroví <info@example.com>';

$supportPhone = '777 000 000';
