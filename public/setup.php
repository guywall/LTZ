<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schemaFile = $root . '/database/schema.sql';
$seedFile = $root . '/database/seed.sql';
$configDir = $root . '/config';
$configFile = $configDir . '/config.php';
$lockFile = $configDir . '/installed.lock';

function setup_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setup_php_string(string $value): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
}

function setup_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if (!$inSingle && !$inDouble && $char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && $char === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if (!$inDouble && $char === "'") {
            $inSingle = !$inSingle;
        } elseif (!$inSingle && $char === '"') {
            $inDouble = !$inDouble;
        }

        if (!$inSingle && !$inDouble && $char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function setup_connect(string $host, string $port, ?string $database, string $user, string $password): PDO
{
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
    if ($database !== null && $database !== '') {
        $dsn .= ';dbname=' . $database;
    }

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function setup_exec_file(PDO $pdo, string $path): int
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing SQL file: ' . $path);
    }

    $count = 0;
    foreach (setup_split_sql((string) file_get_contents($path)) as $statement) {
        $pdo->exec($statement);
        $count++;
    }

    return $count;
}

function setup_table_count(PDO $pdo, string $database): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
    $statement->execute([$database]);
    return (int) $statement->fetchColumn();
}

function setup_drop_tables(PDO $pdo, string $database): void
{
    $statement = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = ?');
    $statement->execute([$database]);
    $tables = $statement->fetchAll(PDO::FETCH_COLUMN);

    if (!$tables) {
        return;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function setup_write_config(string $configFile, array $data): void
{
    $content = "<?php\n\nreturn [\n"
        . "    'app_name' => 'LTZ Operational Intelligence',\n"
        . "    'db_host' => " . setup_php_string($data['host']) . ",\n"
        . "    'db_port' => " . setup_php_string($data['port']) . ",\n"
        . "    'db_name' => " . setup_php_string($data['database']) . ",\n"
        . "    'db_user' => " . setup_php_string($data['user']) . ",\n"
        . "    'db_pass' => " . setup_php_string($data['password']) . ",\n"
        . "];\n";

    if (file_put_contents($configFile, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write config/config.php. Check Plesk file permissions.');
    }
}

function setup_render(string $title, string $body): void
{
    echo '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . setup_e($title) . ' | LTZ Setup</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<main class="setup-shell">
' . $body . '
</main>
</body>
</html>';
}

if (is_file($lockFile)) {
    setup_render('Already installed', '<section class="setup-card">
        <p class="eyebrow">Setup locked</p>
        <h1>LTZ is already installed</h1>
        <p>The installer is disabled because <code>config/installed.lock</code> exists.</p>
        <p>Delete that lock file from Plesk File Manager only if you intentionally need to reinstall.</p>
        <a class="button-link" href="/login">Go to login</a>
    </section>');
    exit;
}

$errors = [];
$success = false;
$summary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['host'] ?? 'localhost'));
    $port = trim((string) ($_POST['port'] ?? '3306'));
    $database = trim((string) ($_POST['database'] ?? ''));
    $user = trim((string) ($_POST['user'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $createDatabase = isset($_POST['create_database']);
    $resetDatabase = isset($_POST['reset_database']);
    $installSeed = isset($_POST['install_seed']);

    if ($host === '' || $port === '' || $database === '' || $user === '') {
        $errors[] = 'Host, port, database name, and database user are required.';
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        $errors[] = 'Database name may only contain letters, numbers, and underscores.';
    }

    if (!is_dir($configDir) || !is_writable($configDir)) {
        $errors[] = 'The config directory is not writable by PHP. In Plesk, give the subscription/system user write access to the config folder for setup.';
    }

    if (!$errors) {
        try {
            if ($createDatabase) {
                $serverPdo = setup_connect($host, $port, null, $user, $password);
                $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $summary[] = 'Database checked/created.';
            }

            $pdo = setup_connect($host, $port, $database, $user, $password);

            $existingTables = setup_table_count($pdo, $database);
            if ($existingTables > 0 && !$resetDatabase) {
                throw new RuntimeException('This database already contains tables. Tick "Reset existing tables" if you want the installer to replace them.');
            }

            if ($resetDatabase) {
                setup_drop_tables($pdo, $database);
                $summary[] = 'Existing tables dropped.';
            }

            $schemaStatements = setup_exec_file($pdo, $schemaFile);
            $summary[] = 'Schema installed (' . $schemaStatements . ' statements).';

            if ($installSeed) {
                $seedStatements = setup_exec_file($pdo, $seedFile);
                $summary[] = 'Seed data installed (' . $seedStatements . ' statements).';
            }

            setup_write_config($configFile, [
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'user' => $user,
                'password' => $password,
            ]);
            $summary[] = 'Application config written.';

            file_put_contents($lockFile, 'Installed ' . date(DATE_ATOM) . PHP_EOL, LOCK_EX);
            $summary[] = 'Installer locked.';
            $success = true;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

if ($success) {
    $items = '';
    foreach ($summary as $item) {
        $items .= '<li>' . setup_e($item) . '</li>';
    }

    setup_render('Installed', '<section class="setup-card">
        <p class="eyebrow">Setup complete</p>
        <h1>LTZ is installed</h1>
        <ul class="compact-list">' . $items . '</ul>
        <p>Seed login password: <code>ChangeMe123!</code>. Change the seed passwords before client use.</p>
        <a class="button-link" href="/login">Go to login</a>
    </section>');
    exit;
}

$errorHtml = '';
if ($errors) {
    $errorHtml = '<div class="alert"><strong>Setup needs attention</strong><ul>';
    foreach ($errors as $error) {
        $errorHtml .= '<li>' . setup_e($error) . '</li>';
    }
    $errorHtml .= '</ul></div>';
}

setup_render('Setup', '<section class="setup-card">
    <p class="eyebrow">Plesk web installer</p>
    <h1>Install LTZ Operational Intelligence</h1>
    <p class="muted">Create a MySQL database in Plesk first, then enter those credentials here. If the database user has permission, the installer can also create the database name below.</p>
    ' . $errorHtml . '
    <form class="setup-form" method="post" action="/setup.php">
        <label>Database host<input name="host" value="' . setup_e($_POST['host'] ?? 'localhost') . '" required></label>
        <label>Database port<input name="port" value="' . setup_e($_POST['port'] ?? '3306') . '" required></label>
        <label>Database name<input name="database" value="' . setup_e($_POST['database'] ?? 'ltz_operational_intelligence') . '" required></label>
        <label>Database user<input name="user" value="' . setup_e($_POST['user'] ?? '') . '" required></label>
        <label>Database password<input type="password" name="password" value="' . setup_e($_POST['password'] ?? '') . '"></label>
        <label class="check-row"><input type="checkbox" name="create_database"> Create database if missing</label>
        <label class="check-row"><input type="checkbox" name="reset_database"> Reset existing tables</label>
        <label class="check-row"><input type="checkbox" name="install_seed" checked> Install seed data</label>
        <button type="submit">Install database and app config</button>
    </form>
    <p class="muted">After installation this setup page locks itself with <code>config/installed.lock</code>.</p>
</section>');

