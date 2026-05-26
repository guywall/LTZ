<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';

function migrate_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function migrate_render(string $title, string $body): void
{
    $publicBase = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($publicBase === '/' || $publicBase === '.') {
        $publicBase = '';
    }

    echo '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . migrate_e($title) . ' | LTZ Migration</title>
    <link rel="stylesheet" href="' . migrate_e($publicBase) . '/assets/styles.css">
</head>
<body><main class="setup-shell">' . $body . '</main></body></html>';
}

if (!is_file($configFile)) {
    migrate_render('Config missing', '<section class="setup-card"><h1>Config missing</h1><p>Run setup first so <code>config/config.php</code> exists.</p></section>');
    exit;
}

$config = require $configFile;
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
$pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$messages = [];

function migrate_column_exists(PDO $pdo, string $database, string $table, string $column): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
    $statement->execute([$database, $table, $column]);
    return (int) $statement->fetchColumn() > 0;
}

function migrate_index_exists(PDO $pdo, string $database, string $table, string $index): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?');
    $statement->execute([$database, $table, $index]);
    return (int) $statement->fetchColumn() > 0;
}

function migrate_constraint_exists(PDO $pdo, string $database, string $constraint): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND constraint_name = ?');
    $statement->execute([$database, $constraint]);
    return (int) $statement->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS learners (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL UNIQUE,
            status ENUM('Active', 'Paused', 'Completed', 'Withdrawn') NOT NULL DEFAULT 'Active',
            start_date DATE NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'Learners table ready.';

        if (!migrate_column_exists($pdo, $config['db_name'], 'training_submissions', 'learner_id')) {
            $pdo->exec('ALTER TABLE training_submissions ADD COLUMN learner_id INT UNSIGNED NULL AFTER week_start');
            $messages[] = 'Added learner_id to training submissions.';
        }

        if (migrate_column_exists($pdo, $config['db_name'], 'training_submissions', 'learner')) {
            $pdo->exec("INSERT IGNORE INTO learners (name)
                SELECT DISTINCT learner
                FROM training_submissions
                WHERE learner IS NOT NULL AND learner <> ''");
            $pdo->exec('UPDATE training_submissions ts JOIN learners l ON l.name = ts.learner SET ts.learner_id = l.id WHERE ts.learner_id IS NULL');
            $messages[] = 'Existing free-text learners migrated into learner records.';
        }

        if (!migrate_index_exists($pdo, $config['db_name'], 'training_submissions', 'idx_training_learner')) {
            $pdo->exec('ALTER TABLE training_submissions ADD INDEX idx_training_learner (learner_id)');
            $messages[] = 'Learner index added.';
        }

        if (!migrate_constraint_exists($pdo, $config['db_name'], 'fk_training_learner')) {
            $pdo->exec('ALTER TABLE training_submissions ADD CONSTRAINT fk_training_learner FOREIGN KEY (learner_id) REFERENCES learners(id)');
            $messages[] = 'Learner relationship added.';
        }

        $messages[] = 'Migration complete.';
    } catch (Throwable $exception) {
        $messages[] = 'Migration failed: ' . $exception->getMessage();
    }
}

$items = '';
foreach ($messages as $message) {
    $items .= '<li>' . migrate_e($message) . '</li>';
}

migrate_render('Learner migration', '<section class="setup-card">
    <p class="eyebrow">Database migration</p>
    <h1>Upgrade training learners</h1>
    <p class="muted">This creates learner records and links existing weekly training logs to them.</p>
    ' . ($items ? '<ul class="insight-list">' . $items . '</ul>' : '') . '
    <form method="post" action="' . migrate_e((string) ($_SERVER['REQUEST_URI'] ?? '/migrate.php')) . '">
        <button type="submit">Run learner migration</button>
    </form>
    <p class="muted">After this succeeds, remove <code>public/migrate.php</code> from the server.</p>
</section>');

