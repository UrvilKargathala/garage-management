<?php
/**
 * database/test-connection.php
 * Garage Management System — Developer Tool
 *
 * ┌─────────────────────────────────────────────────────────┐
 * │  PURPOSE : Verify that PHP can connect to MySQL via PDO │
 * │  ACCESS  : http://localhost/GarageManagement/database/  │
 * │            test-connection.php                          │
 * │                                                         │
 * │  ⚠  DELETE OR BLOCK THIS FILE BEFORE GOING LIVE.       │
 * │     It is a development-only diagnostic tool.           │
 * └─────────────────────────────────────────────────────────┘
 *
 * What this page checks:
 *  1.  PHP version (must be 8.0+)
 *  2.  PDO extension is loaded
 *  3.  pdo_mysql driver is available
 *  4.  Can open a PDO connection to MySQL
 *  5.  PDO attributes match the project requirements
 *  6.  Can execute a real SELECT query (SELECT 1)
 *  7.  Can reach the correct database (garage_management)
 *  8.  All 7 required tables exist
 *  9.  Row counts in seeded tables
 * 10.  MySQL server version
 *
 * SECURITY:
 *  - DB_HOST, DB_NAME, DB_USER are shown (non-sensitive on localhost).
 *  - DB_PASS is NEVER displayed — only whether it is set or empty.
 *  - PDO attribute values are resolved to human-readable labels.
 */

// ── Load project database configuration ──────────────────────
require_once __DIR__ . '/../config/database.php';

// ── Helper: render a pass / fail / warn badge ─────────────────
function badge(string $type, string $label): string
{
    $styles = [
        'pass' => 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;',
        'fail' => 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;',
        'warn' => 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;',
        'info' => 'background:#e0f2fe;color:#0c4a6e;border:1px solid #7dd3fc;',
    ];
    $style = $styles[$type] ?? $styles['info'];
    $icon  = ['pass' => '✔', 'fail' => '✘', 'warn' => '⚠', 'info' => 'ℹ'][$type] ?? '';

    return "<span style='display:inline-block;padding:2px 10px;border-radius:12px;
                          font-size:0.8rem;font-weight:700;{$style}'>{$icon} {$label}</span>";
}

// ── Helper: one table row ─────────────────────────────────────
function row(string $check, string $result, string $badge): string
{
    return "<tr>
                <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;
                           font-weight:600;color:#374151;width:40%;'>{$check}</td>
                <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;
                           color:#4b5563;width:40%;font-family:monospace;'>{$result}</td>
                <td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;
                           width:20%;'>{$badge}</td>
            </tr>";
}

// ── Collect all checks ────────────────────────────────────────
$checks    = [];   // array of row() strings
$allPassed = true;

// ─────────────────────────────────────────────────────────────
//  CHECK 1 : PHP Version
// ─────────────────────────────────────────────────────────────
$phpVersion = PHP_VERSION;
$phpOk      = version_compare($phpVersion, '8.0.0', '>=');
$allPassed  = $allPassed && $phpOk;
$checks[]   = row(
    'PHP Version',
    $phpVersion,
    $phpOk ? badge('pass', 'OK — 8.0+') : badge('fail', 'Need PHP 8.0+')
);

// ─────────────────────────────────────────────────────────────
//  CHECK 2 : PDO Extension loaded
// ─────────────────────────────────────────────────────────────
$pdoLoaded = extension_loaded('pdo');
$allPassed = $allPassed && $pdoLoaded;
$checks[]  = row(
    'PDO Extension',
    $pdoLoaded ? 'Loaded' : 'NOT loaded',
    $pdoLoaded ? badge('pass', 'OK') : badge('fail', 'Missing')
);

// ─────────────────────────────────────────────────────────────
//  CHECK 3 : pdo_mysql driver available
// ─────────────────────────────────────────────────────────────
$pdoMysql  = in_array('mysql', PDO::getAvailableDrivers(), true);
$allPassed = $allPassed && $pdoMysql;
$checks[]  = row(
    'PDO MySQL Driver',
    implode(', ', PDO::getAvailableDrivers()),
    $pdoMysql ? badge('pass', 'mysql ✔') : badge('fail', 'pdo_mysql missing')
);

// ─────────────────────────────────────────────────────────────
//  CHECK 4 : PDO Connection — attempt to open
// ─────────────────────────────────────────────────────────────
$pdo          = null;
$connectError = '';

try {
    $pdo      = getDBConnection(); // defined in config/database.php
    $checks[] = row(
        'PDO Connection',
        'Connected to <strong>' . htmlspecialchars(DB_NAME) . '</strong>'
            . ' on <strong>' . htmlspecialchars(DB_HOST) . '</strong>',
        badge('pass', 'Connected')
    );
} catch (PDOException $e) {
    $connectError = htmlspecialchars($e->getMessage(), ENT_QUOTES);
    $allPassed    = false;
    $checks[]     = row(
        'PDO Connection',
        $connectError,
        badge('fail', 'Failed')
    );
}

// ─────────────────────────────────────────────────────────────
//  CHECKS 5-10 : Only run if connection succeeded
// ─────────────────────────────────────────────────────────────
if ($pdo !== null) {

    // ── CHECK 5a : ATTR_ERRMODE ───────────────────────────────
    $errMode   = (int) $pdo->getAttribute(PDO::ATTR_ERRMODE);
    $errOk     = ($errMode === PDO::ERRMODE_EXCEPTION);
    $allPassed = $allPassed && $errOk;
    $checks[]  = row(
        'PDO::ATTR_ERRMODE',
        $errOk ? 'ERRMODE_EXCEPTION (' . $errMode . ')' : 'Value: ' . $errMode,
        $errOk ? badge('pass', 'Correct') : badge('fail', 'Must be ERRMODE_EXCEPTION')
    );

    // ── CHECK 5b : ATTR_DEFAULT_FETCH_MODE ───────────────────
    $fetchMode = (int) $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
    $fetchOk   = ($fetchMode === PDO::FETCH_ASSOC);
    $allPassed = $allPassed && $fetchOk;
    $checks[]  = row(
        'PDO::ATTR_DEFAULT_FETCH_MODE',
        $fetchOk ? 'FETCH_ASSOC (' . $fetchMode . ')' : 'Value: ' . $fetchMode,
        $fetchOk ? badge('pass', 'Correct') : badge('fail', 'Must be FETCH_ASSOC')
    );

    // ── CHECK 5c : ATTR_EMULATE_PREPARES ─────────────────────
    $emulate   = (bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $emulOk    = ($emulate === false);
    $allPassed = $allPassed && $emulOk;
    $checks[]  = row(
        'PDO::ATTR_EMULATE_PREPARES',
        $emulOk ? 'false (real prepared statements)' : 'true (emulated)',
        $emulOk ? badge('pass', 'Correct') : badge('warn', 'Should be false')
    );

    // ── CHECK 6 : Live query — SELECT 1 ───────────────────────
    try {
        $stmt     = $pdo->query('SELECT 1 AS ping');
        $result   = $stmt->fetch();
        $queryOk  = ($result['ping'] ?? null) === 1;
        $allPassed = $allPassed && $queryOk;
        $checks[] = row(
            'Live Query (SELECT 1)',
            'Returned: ' . ($result['ping'] ?? 'null'),
            $queryOk ? badge('pass', 'OK') : badge('fail', 'Unexpected result')
        );
    } catch (PDOException $e) {
        $allPassed = false;
        $checks[]  = row('Live Query (SELECT 1)', htmlspecialchars($e->getMessage()), badge('fail', 'Failed'));
    }

    // ── CHECK 7 : Correct database selected ──────────────────
    try {
        $stmt     = $pdo->query('SELECT DATABASE() AS db');
        $result   = $stmt->fetch();
        $dbName   = $result['db'] ?? '';
        $dbOk     = ($dbName === DB_NAME);
        $allPassed = $allPassed && $dbOk;
        $checks[] = row(
            'Active Database',
            htmlspecialchars($dbName),
            $dbOk ? badge('pass', 'Correct') : badge('fail', 'Expected: ' . DB_NAME)
        );
    } catch (PDOException $e) {
        $allPassed = false;
        $checks[]  = row('Active Database', htmlspecialchars($e->getMessage()), badge('fail', 'Failed'));
    }

    // ── CHECK 8 : MySQL server version ────────────────────────
    try {
        $serverVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $checks[]  = row('MySQL Server Version', htmlspecialchars($serverVer), badge('info', 'Info'));
    } catch (PDOException $e) {
        $checks[] = row('MySQL Server Version', 'Unable to retrieve', badge('warn', 'Warning'));
    }

    // ── CHECK 9 : All 7 required tables exist ────────────────
    $requiredTables = [
        'users', 'login_logs', 'customers',
        'vehicles', 'services', 'bookings', 'service_history',
    ];
    try {
        $stmt       = $pdo->query("SHOW TABLES FROM `" . DB_NAME . "`");
        $existing   = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing    = array_diff($requiredTables, $existing);
        $tablesOk   = count($missing) === 0;
        $allPassed  = $allPassed && $tablesOk;
        $checks[]   = row(
            'Required Tables (' . count($requiredTables) . ')',
            $tablesOk
                ? implode(', ', $requiredTables)
                : 'Missing: ' . implode(', ', $missing),
            $tablesOk
                ? badge('pass', 'All ' . count($requiredTables) . ' found')
                : badge('fail', count($missing) . ' missing — import garage.sql')
        );
    } catch (PDOException $e) {
        $allPassed = false;
        $checks[]  = row('Required Tables', htmlspecialchars($e->getMessage()), badge('fail', 'Failed'));
    }

    // ── CHECK 10 : Row counts for seeded tables ───────────────
    $seededTables = ['users' => 1, 'services' => 8];
    foreach ($seededTables as $tbl => $expected) {
        try {
            $stmt   = $pdo->query("SELECT COUNT(*) AS cnt FROM `{$tbl}`");
            $row_   = $stmt->fetch();
            $actual = (int) ($row_['cnt'] ?? 0);
            $ok     = ($actual >= $expected);
            $checks[] = row(
                "Rows in `{$tbl}`",
                "{$actual} row(s) (expected ≥ {$expected})",
                $ok ? badge('pass', 'OK') : badge('warn', "Expected ≥ {$expected}")
            );
        } catch (PDOException $e) {
            $checks[] = row("Rows in `{$tbl}`", htmlspecialchars($e->getMessage()), badge('fail', 'Error'));
        }
    }

    // ── CHECK 11 : Charset ────────────────────────────────────
    try {
        $stmt    = $pdo->query("SELECT @@character_set_connection AS cs, @@collation_connection AS col");
        $result  = $stmt->fetch();
        $csOk    = str_contains((string)($result['cs'] ?? ''), 'utf8mb4');
        $checks[] = row(
            'Connection Charset',
            htmlspecialchars(($result['cs'] ?? '') . ' / ' . ($result['col'] ?? '')),
            $csOk ? badge('pass', 'utf8mb4') : badge('warn', 'Expected utf8mb4')
        );
    } catch (PDOException $e) {
        $checks[] = row('Connection Charset', htmlspecialchars($e->getMessage()), badge('warn', 'Could not verify'));
    }

} // end: if connection succeeded

// ─────────────────────────────────────────────────────────────
//  Credential display (safe — password is NEVER shown)
// ─────────────────────────────────────────────────────────────
$credInfo = [
    'Host'     => htmlspecialchars(DB_HOST),
    'Port'     => htmlspecialchars((string) DB_PORT),
    'Database' => htmlspecialchars(DB_NAME),
    'User'     => htmlspecialchars(DB_USER),
    'Password' => DB_PASS === '' ? '<em style="color:#9ca3af">(empty — XAMPP default)</em>' : '<em style="color:#059669">●●●●● (set)</em>',
    'Charset'  => htmlspecialchars(DB_CHARSET),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>DB Connection Test | Garage Management System</title>
    <style>
        /* ── Reset & base ──────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0d1117;
            color: #e6edf3;
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        /* ── Layout ────────────────────────────────────────── */
        .container {
            max-width: 860px;
            margin: 0 auto;
        }

        /* ── Dev-tool warning banner ───────────────────────── */
        .dev-banner {
            background: linear-gradient(90deg, #78350f, #92400e);
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fef3c7;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* ── Page header ───────────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg, #1e2535, #252d40);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #f8fafc;
        }

        .page-header p {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-top: 0.3rem;
        }

        /* ── Overall result pill ───────────────────────────── */
        .result-pill {
            padding: 0.6rem 1.5rem;
            border-radius: 2rem;
            font-weight: 800;
            font-size: 1rem;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .result-pill.success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #6ee7b7;
        }

        .result-pill.failure {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fca5a5;
        }

        /* ── Card ──────────────────────────────────────────── */
        .card {
            background: #161b27;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: #1e2535;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── Checks table ──────────────────────────────────── */
        .checks-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            background: #ffffff;
            color: #111827;
        }

        .checks-table thead th {
            background: #1a1f2e;
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            text-align: left;
        }

        /* ── Config grid ───────────────────────────────────── */
        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .config-item {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            gap: 1rem;
            align-items: baseline;
        }

        .config-item:nth-child(odd) { background: rgba(255,255,255,0.02); }

        .config-key {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            min-width: 80px;
            flex-shrink: 0;
        }

        .config-val {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.875rem;
            color: #f97316;
            word-break: break-all;
        }

        /* ── Success / Error hero ──────────────────────────── */
        .result-hero {
            text-align: center;
            padding: 2rem;
        }

        .result-hero .icon {
            font-size: 3.5rem;
            line-height: 1;
            margin-bottom: 0.75rem;
        }

        .result-hero h2 {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .result-hero p {
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .success-text { color: #34d399; }
        .failure-text { color: #f87171; }

        /* ── Error box ─────────────────────────────────────── */
        .error-box {
            background: #1f0a0a;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin: 1rem 1.25rem;
            font-family: monospace;
            font-size: 0.85rem;
            color: #fca5a5;
        }

        /* ── Next steps ────────────────────────────────────── */
        .steps-list {
            padding: 1.25rem;
            list-style: none;
            counter-reset: step;
        }

        .steps-list li {
            counter-increment: step;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.875rem;
            color: #cbd5e1;
        }

        .steps-list li::before {
            content: counter(step);
            background: #f97316;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 800;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        code {
            background: rgba(249,115,22,0.15);
            color: #fb923c;
            padding: 1px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85em;
        }

        @media (max-width: 600px) {
            .config-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- ── Developer Warning Banner ──────────────────────── -->
    <div class="dev-banner">
        ⚠ DEVELOPER TOOL — Delete or restrict <code>database/test-connection.php</code>
        before deploying to a production or shared server.
    </div>

    <!-- ── Page Header ───────────────────────────────────── -->
    <div class="page-header">
        <div>
            <h1>🔌 Database Connection Test</h1>
            <p>Garage Management System &nbsp;·&nbsp; config/database.php &nbsp;·&nbsp;
               <?= date('d M Y, h:i:s A') ?></p>
        </div>
        <div class="result-pill <?= $allPassed ? 'success' : 'failure' ?>">
            <?= $allPassed ? '✔ All Checks Passed' : '✘ One or More Failed' ?>
        </div>
    </div>

    <!-- ── Result Hero ───────────────────────────────────── -->
    <div class="card">
        <div class="result-hero">
            <?php if ($allPassed): ?>
                <div class="icon">✅</div>
                <h2 class="success-text">Database connection successful.</h2>
                <p>PHP connected to MySQL via PDO. All required tables were found.<br />
                   The application is ready to proceed to the next development step.</p>
            <?php else: ?>
                <div class="icon">❌</div>
                <h2 class="failure-text">Database connection failed.</h2>
                <p>One or more checks did not pass. See the details below to fix the issue.</p>
                <?php if ($connectError): ?>
                    <div class="error-box"><?= $connectError ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── All Diagnostic Checks ─────────────────────────── -->
    <div class="card">
        <div class="card-header">🔍 Diagnostic Checks</div>
        <table class="checks-table">
            <thead>
                <tr>
                    <th>Check</th>
                    <th>Value / Result</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?= implode("\n", $checks) ?>
            </tbody>
        </table>
    </div>

    <!-- ── Connection Configuration (no password) ─────────── -->
    <div class="card">
        <div class="card-header">⚙ Connection Configuration (config/database.php)</div>
        <div class="config-grid">
            <?php foreach ($credInfo as $key => $val): ?>
            <div class="config-item">
                <span class="config-key"><?= htmlspecialchars($key) ?></span>
                <span class="config-val"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Next Steps ────────────────────────────────────── -->
    <div class="card">
        <div class="card-header">🚀 Next Steps</div>
        <ul class="steps-list">
            <?php if ($allPassed): ?>
                <li>Connection is confirmed working. You can now proceed to implement the
                    <strong>authentication module</strong> (login, register, session management).</li>
                <li><strong>Delete this file</strong> (<code>database/test-connection.php</code>)
                    once development is complete, or block access to the
                    <code>database/</code> folder via <code>.htaccess</code>.</li>
                <li>Use <code>getDBConnection()</code> in every module page by adding:
                    <code>require_once __DIR__ . '/../config/database.php';</code></li>
                <li>Always use <strong>prepared statements</strong>:
                    <code>$stmt = $pdo->prepare("SELECT * FROM table WHERE id = ?");</code></li>
            <?php else: ?>
                <li>Make sure <strong>MySQL</strong> is started in the XAMPP Control Panel.</li>
                <li>Open <code>config/database.php</code> and verify
                    <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>,
                    <code>DB_PASS</code> are correct.</li>
                <li>If the database does not exist, open <strong>phpMyAdmin</strong>
                    (<code>http://localhost/phpmyadmin</code>), create a database named
                    <code>garage_management</code>, and import
                    <code>database/garage.sql</code>.</li>
                <li>Refresh this page after making changes.</li>
            <?php endif; ?>
        </ul>
    </div>

</div><!-- /.container -->
</body>
</html>
