<?php
// nuBuilder Next - Configuration
// Modern rebuild of nuBuilder Forte

define('NU_VERSION', '5.0.0');
define('NU_BUILD_DATE', '2026-05-20');

// Database
$nuConfig['dbHost']     = 'localhost';
$nuConfig['dbName']     = 'ictfjcom_bnv5';
$nuConfig['dbUser']     = 'ictfjcom_nb5_2026';
$nuConfig['dbPassword'] = 'Nilsa@2026';
$nuConfig['dbCharset']  = 'utf8mb4';

// Security
$nuConfig['globeadminPassword'] = 'changeme';
$nuConfig['sessionTimeout']     = 3600;
$nuConfig['maxLoginAttempts']   = 5;
$nuConfig['lockoutDuration']    = 900;
$nuConfig['csrfTokenName']      = 'nu_csrf';

// Features
$nuConfig['enable2FA']        = true;
$nuConfig['enableAPI']        = true;
$nuConfig['enableAuditTrail'] = true;
$nuConfig['enableFileUploads']  = true;
$nuConfig['maxUploadSize']      = 10485760; // 10MB
$nuConfig['allowedFileTypes'] = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','csv'];

// Paths
$nuConfig['baseUrl']    = '/nubuilder-next/';
$nuConfig['uploadPath'] = __DIR__ . '/uploads/';
$nuConfig['logPath']    = __DIR__ . '/logs/';

// Display
$nuConfig['siteTitle'] = 'nuBuilder Next';
$nuConfig['theme']     = 'auto'; // auto, light, dark

// API
$nuConfig['apiRateLimit'] = 1000; // requests per hour
$nuConfig['apiKeyHeader'] = 'X-API-Key';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $nuConfig['logPath'] . 'php_errors.log');

session_start();
header('Content-Type: text/html; charset=utf-8');
?>
