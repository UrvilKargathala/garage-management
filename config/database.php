<?php
/**
 * config/database.php
 * Garage Management System
 *
 * Central database configuration.
 * Every PHP page that needs the database calls getDBConnection().
 *
 * ─── Technology ──────────────────────────────────────────────
 *  Driver  : PDO (PHP Data Objects)
 *  Database: MySQL / MariaDB (via XAMPP)
 *
 * ─── Why PDO? ────────────────────────────────────────────────
 *  PDO uses "prepared statements".  User-supplied values are
 *  passed separately from the SQL text, so the database driver
 *  treats them as pure data — never as executable SQL.
 *  This completely prevents SQL Injection attacks.
 *
 * ─── Usage example in any module ────────────────────────────
 *  require_once __DIR__ . '/../config/database.php';
 *  $pdo  = getDBConnection();
 *  $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
 *  $stmt->execute([$id]);
 *  $row  = $stmt->fetch();
 * ─────────────────────────────────────────────────────────────
 */

// ── Database credentials ──────────────────────────────────────
// Update these values to match your XAMPP / MySQL setup.
// For XAMPP defaults: host=localhost, user=root, password=''

define('DB_HOST',    '127.0.0.1');          // MySQL server hostname
define('DB_NAME',    'garage_management'); // Database name (must match garage.sql)
define('DB_USER',    'root');               // MySQL username
define('DB_PASS',    '');                   // MySQL password (XAMPP default is empty)
define('DB_CHARSET', 'utf8mb4');            // Character set — supports emojis & all languages
define('DB_PORT',    3306);                 // Default MySQL port

// ── PDO connection options ────────────────────────────────────
// These three settings are best practice for all PHP projects.
define('DB_OPTIONS', [
    // Throw a PDOException on any database error (easy to catch and debug)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Return each row as an associative array: $row['column_name']
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // FALSE = use real prepared statements (not emulated ones)
    // This is more secure and avoids certain edge-case type issues.
    PDO::ATTR_EMULATE_PREPARES   => false,

    // Tell MySQL to use the correct charset from the very first packet
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
]);

/**
 * getDBConnection()
 *
 * Creates and returns a PDO database connection object.
 *
 * How it works:
 *  1. Build a DSN (Data Source Name) string that tells PDO:
 *       - which driver to use  (mysql:)
 *       - which server         (host=localhost)
 *       - which database       (dbname=garage_management)
 *       - what charset         (charset=utf8mb4)
 *  2. Create a new PDO object with the DSN, username, password,
 *     and options defined above.
 *  3. If the connection fails, catch the PDOException and display
 *     a friendly error message instead of a PHP fatal error.
 *
 * @return PDO   A live database connection object.
 */
function getDBConnection(): PDO
{
    // DSN (Data Source Name) — connection string for PDO
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        // Create the PDO connection
        $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        return $pdo;

    } catch (PDOException $e) {
        /*
         * Connection failed.
         * In production: log the error and show a generic message.
         * For development: we show the actual error so you can fix it.
         *
         * Common causes:
         *  - MySQL service not started in XAMPP
         *  - Wrong DB_NAME, DB_USER, or DB_PASS above
         *  - Database 'garage_management' has not been created yet
         *    → Import database/garage.sql via phpMyAdmin first.
         */
        $isDev = in_array(DB_HOST, ['localhost', '127.0.0.1'], true);

        $detail = $isDev
            ? '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>
               <p><strong>Fix:</strong> Make sure MySQL is running in XAMPP and the database
               <code>garage_management</code> exists. Import <code>database/garage.sql</code>
               via phpMyAdmin if you have not done so yet.</p>'
            : '<p>Please contact the system administrator.</p>';

        die('<!DOCTYPE html><html lang="en"><head>
                <meta charset="UTF-8">
                <title>Database Error</title>
                <style>
                    body { font-family: sans-serif; background: #f8d7da; display:flex;
                           align-items:center; justify-content:center; min-height:100vh; margin:0; }
                    .box { background:#fff; border:1px solid #f5c6cb; border-radius:8px;
                           padding:2rem; max-width:560px; }
                    h2   { color:#721c24; margin-top:0; }
                    code { background:#f1f1f1; padding:2px 6px; border-radius:4px; }
                </style>
             </head><body>
                <div class="box">
                    <h2>⚠ Database Connection Failed</h2>
                    ' . $detail . '
                </div>
             </body></html>');
    }
}
