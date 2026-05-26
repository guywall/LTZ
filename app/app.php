<?php

declare(strict_types=1);

require __DIR__ . '/kpi.php';

function app_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $local = __DIR__ . '/../config/config.php';
    $example = __DIR__ . '/../config/config.php.example';
    $config = file_exists($local) ? require $local : require $example;

    return $config;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(mixed $value): string
{
    return 'GBP ' . number_format((float) $value, 0);
}

function pct(mixed $value): string
{
    return number_format(((float) $value) * 100, 0) . '%';
}

function percentage_input_to_decimal(mixed $value): float
{
    $number = (float) $value;
    return $number > 1 ? $number / 100 : $number;
}

function route_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') ?: '/';
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function query_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function query_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function execute_sql(string $sql, array $params = []): void
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function current_user(): ?array
{
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $user = query_one(
        'SELECT users.*, roles.role_key, roles.name AS role_name, roles.is_shareholder, roles.is_admin
         FROM users
         JOIN roles ON roles.id = users.role_id
         WHERE users.id = ? AND users.active = 1',
        [(int) $_SESSION['user_id']]
    );

    return $user ?: null;
}

function user_permissions(?array $user = null): array
{
    $user ??= current_user();
    if (!$user) {
        return [];
    }

    static $cache = [];
    $userId = (int) $user['id'];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    $rows = query_all('SELECT area, can_read, can_write FROM user_permissions WHERE user_id = ?', [$userId]);
    $permissions = [];
    foreach ($rows as $row) {
        $permissions[$row['area']] = [
            'read' => (bool) $row['can_read'],
            'write' => (bool) $row['can_write'],
        ];
    }

    return $cache[$userId] = $permissions;
}

function can_access(string $area, bool $write = false): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    if ((int) $user['is_admin'] === 1 || (int) $user['is_shareholder'] === 1) {
        return true;
    }

    $permissions = user_permissions($user);
    return isset($permissions[$area]) && ($write ? $permissions[$area]['write'] : $permissions[$area]['read']);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login');
    }

    return $user;
}

function require_area(string $area, bool $write = false): void
{
    if (!can_access($area, $write)) {
        http_response_code(403);
        render_page('Access denied', '<section class="panel"><h1>Access denied</h1><p>Your account does not have access to this area.</p></section>');
        exit;
    }
}

function handle_request(): void
{
    session_start();

    $path = route_path();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($path === '/login') {
        $method === 'POST' ? handle_login_post() : show_login();
        return;
    }

    if ($path === '/logout') {
        session_destroy();
        redirect('/login');
    }

    require_login();

    if ($method === 'POST') {
        verify_csrf();
        handle_post($path);
        return;
    }

    match ($path) {
        '/', '/dashboard' => show_dashboard(),
        '/submissions' => show_submissions(),
        '/barbers' => show_barbers(),
        '/training' => show_training(),
        '/social' => show_social(),
        '/hr' => show_hr(),
        '/leadership' => show_leadership(),
        '/strategy' => show_strategy(),
        '/risks' => show_risks(),
        '/actions' => show_actions(),
        '/profile' => show_profile(),
        '/admin/users' => show_admin_users(),
        '/admin/lookups' => show_admin_lookups(),
        '/admin/targets' => show_admin_targets(),
        default => show_not_found(),
    };
}

function handle_post(string $path): void
{
    match ($path) {
        '/login' => handle_login_post(),
        '/barbers' => store_barber_submission(),
        '/training' => store_training_submission(),
        '/social' => store_social_submission(),
        '/hr' => store_hr_submission(),
        '/risks' => store_risk(),
        '/actions' => store_action(),
        '/admin/users' => store_user(),
        '/admin/lookups' => store_lookup(),
        '/admin/targets' => update_targets(),
        default => show_not_found(),
    };
}

function show_login(string $error = ''): void
{
    $content = '<main class="login-shell">
        <section class="login-card">
            <p class="eyebrow">LTZ</p>
            <h1>Operational Intelligence</h1>
            ' . ($error ? '<div class="alert">' . e($error) . '</div>' : '') . '
            <form method="post" action="/login">
                ' . csrf_field() . '
                <label>Email<input type="email" name="email" required autofocus></label>
                <label>Password<input type="password" name="password" required></label>
                <button type="submit">Sign in</button>
            </form>
            <p class="muted">Seed password: <code>ChangeMe123!</code></p>
        </section>
    </main>';

    echo html_shell('Login', $content, false);
}

function handle_login_post(): void
{
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $user = query_one(
        'SELECT users.*, roles.role_key FROM users JOIN roles ON roles.id = users.role_id WHERE email = ? AND active = 1',
        [$email]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        show_login('Those login details were not recognised.');
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    redirect('/dashboard');
}

function html_shell(string $title, string $content, bool $withNav = true): string
{
    $config = app_config();
    $nav = $withNav ? navigation() : '';

    return '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . e($title) . ' | ' . e($config['app_name']) . '</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
' . $nav . '
' . $content . '
</body>
</html>';
}

function render_page(string $title, string $content): void
{
    echo html_shell($title, '<main class="app-shell">' . flash_html() . $content . '</main>');
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function flash_html(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $message = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);

    return '<div class="notice">' . e($message) . '</div>';
}

function navigation(): string
{
    $items = [
        ['/dashboard', 'Dashboard', 'dashboard'],
        ['/barbers', 'Barbers', 'barbers'],
        ['/training', 'Training', 'training'],
        ['/social', 'Social', 'social'],
        ['/hr', 'HR', 'hr'],
        ['/leadership', 'Leadership', 'leadership'],
        ['/strategy', '5x5', 'strategy'],
        ['/risks', 'Risks', 'leadership'],
        ['/actions', 'Actions', 'leadership'],
        ['/admin/users', 'Admin', 'admin'],
    ];

    $links = '';
    $path = route_path();
    foreach ($items as [$href, $label, $area]) {
        if (!can_access($area)) {
            continue;
        }
        $active = str_starts_with($path, $href) ? ' class="active"' : '';
        $links .= '<a href="' . e($href) . '"' . $active . '>' . e($label) . '</a>';
    }

    $user = current_user();

    return '<header class="topbar">
        <a class="brand" href="/dashboard">LTZ OI</a>
        <nav>' . $links . '</nav>
        <div class="user-menu"><a href="/profile">' . e($user['name'] ?? '') . '</a><a href="/logout">Logout</a></div>
    </header>';
}

function selected_week(): string
{
    $week = (string) ($_GET['week'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
        return $week;
    }

    $latest = query_one(
        "SELECT MAX(week_start) AS week_start FROM (
            SELECT week_start FROM weekly_barber_submissions
            UNION ALL SELECT week_start FROM training_submissions
            UNION ALL SELECT week_start FROM brand_submissions
            UNION ALL SELECT week_start FROM hr_recruitment_submissions
        ) weeks"
    );

    return $latest['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
}

function week_filter(string $basePath): string
{
    $week = selected_week();
    return '<form class="filterbar" method="get" action="' . e($basePath) . '">
        <label>Week<input type="date" name="week" value="' . e($week) . '"></label>
        <button type="submit">Apply</button>
    </form>';
}

function lookup_options(string $table, ?int $selected = null): string
{
    $allowed = ['sites', 'barbers', 'brands', 'recruitment_roles', 'users'];
    if (!in_array($table, $allowed, true)) {
        return '';
    }

    $nameColumn = $table === 'users' ? 'name' : 'name';
    $rows = query_all("SELECT id, {$nameColumn} AS name FROM {$table} ORDER BY name");
    $html = '';
    foreach ($rows as $row) {
        $isSelected = (int) $row['id'] === $selected ? ' selected' : '';
        $html .= '<option value="' . (int) $row['id'] . '"' . $isSelected . '>' . e($row['name']) . '</option>';
    }

    return $html;
}

function targets_for_kpi(): array
{
    $rows = query_all('SELECT kpi_key, target_value, amber_threshold FROM targets');
    $targets = [];
    foreach ($rows as $row) {
        $key = (string) $row['kpi_key'];
        $targets[$key . '_target'] = (float) $row['target_value'];
        $targets[$key . '_amber'] = (float) $row['amber_threshold'];
    }

    return $targets;
}

function badge(string $rag): string
{
    $class = strtolower($rag);
    return '<span class="rag rag-' . e($class) . '">' . e($rag) . '</span>';
}

function card(string $label, string $value, string $rag = '', string $href = ''): string
{
    $inner = '<span>' . e($label) . '</span><strong>' . $value . '</strong>' . ($rag ? badge($rag) : '');
    if ($href !== '') {
        return '<a class="metric metric-link" href="' . e($href) . '">' . $inner . '</a>';
    }

    return '<article class="metric">' . $inner . '</article>';
}

function show_dashboard(): void
{
    $user = current_user();
    if ($user && (int) $user['is_admin'] !== 1 && (int) $user['is_shareholder'] !== 1) {
        if (can_access('barbers')) {
            show_barber_dashboard();
            return;
        }
        if (can_access('training')) {
            show_training_dashboard();
            return;
        }
        if (can_access('social')) {
            show_social_dashboard();
            return;
        }
        if (can_access('hr')) {
            show_hr_dashboard();
            return;
        }
    }

    show_executive_dashboard();
}

function show_executive_dashboard(): void
{
    $week = selected_week();
    $targets = targets_for_kpi();
    $metrics = executive_metrics($week, $targets);
    $leadership = leadership_rows($week, $targets);

    $weekParam = '?week=' . rawurlencode($week);
    $cards = card('Weekly RTB', money($metrics['weekly_rtb']), $metrics['weekly_rtb_rag'], '/barbers' . $weekParam)
        . card('Occupied chairs', (string) $metrics['occupied_chairs'], $metrics['occupied_chairs_rag'], '/barbers' . $weekParam)
        . card('Active learners', (string) $metrics['active_learners'], $metrics['active_learners_rag'], '/training' . $weekParam)
        . card('Social leads', (string) $metrics['social_leads'], $metrics['social_leads_rag'], '/social' . $weekParam)
        . card('Senior pipeline', (string) $metrics['senior_pipeline'], $metrics['senior_pipeline_rag'], '/hr' . $weekParam)
        . card('Open actions', (string) $metrics['open_actions'], '', '/actions')
        . card('Open risks', (string) $metrics['open_risks'], '', '/risks');

    $rows = '';
    foreach ($leadership as $row) {
        $rows .= '<tr><td>' . e($row['leader']) . '</td><td>' . e($row['area']) . '</td><td>' . badge($row['overall_rag']) . '</td><td>' . (int) $row['open_risks'] . '</td><td>' . (int) $row['open_actions'] . '</td></tr>';
    }

    render_page('Dashboard', '<section class="hero">
        <div><p class="eyebrow">Weekly cadence</p><h1>Executive dashboard</h1><p>Operational, people, social, and training signals for the selected week.</p></div>
        ' . week_filter('/dashboard') . '
    </section>
    <section class="metric-grid">' . $cards . '</section>
    <section class="panel"><div class="panel-title"><h2>Leadership RAG</h2><a href="/leadership?week=' . e($week) . '">Open leadership view</a></div>
        <table><thead><tr><th>Leader</th><th>Area</th><th>Overall</th><th>Open risks</th><th>Open actions</th></tr></thead><tbody>' . $rows . '</tbody></table>
    </section>');
}

function average_value(array $values): float
{
    $values = array_map('floatval', $values);
    return count($values) ? array_sum($values) / count($values) : 0.0;
}

function insight_panel(string $title, array $items, string $empty): string
{
    $list = '';
    foreach ($items as $item) {
        $list .= '<li>' . e($item) . '</li>';
    }

    if ($list === '') {
        $list = '<li class="muted">' . e($empty) . '</li>';
    }

    return '<section class="panel insight-panel"><h2>' . e($title) . '</h2><ul class="insight-list">' . $list . '</ul></section>';
}

function show_barber_dashboard(): void
{
    require_area('barbers');
    $week = selected_week();
    $targets = targets_for_kpi();
    $rows = barber_rows($week, $targets);
    $count = count($rows);
    $totalRtb = array_sum(array_column($rows, 'rtb'));
    $avgRtb = $count ? $totalRtb / $count : 0;
    $avgDays = average_value(array_column($rows, 'days_worked'));
    $avgRebooking = average_value(array_column($rows, 'rebooking_pct'));
    $avgUtilisation = average_value(array_column($rows, 'utilisation_pct'));
    $occupied = count(array_filter($rows, fn(array $row): bool => (float) $row['rtb'] > 0));

    $cards = card('Weekly RTB', money($totalRtb), rag_threshold($avgRtb, $targets['barber_rtb_target'], $targets['barber_rtb_amber']), '/barbers?week=' . rawurlencode($week))
        . card('Occupied chairs', (string) $occupied, '', '/barbers?week=' . rawurlencode($week))
        . card('Avg RTB / barber', money($avgRtb), rag_threshold($avgRtb, $targets['barber_rtb_target'], $targets['barber_rtb_amber']), '/barbers?week=' . rawurlencode($week))
        . card('Avg days worked', number_format($avgDays, 1), rag_threshold($avgDays, $targets['barber_days_target'], $targets['barber_days_amber']), '/barbers?week=' . rawurlencode($week))
        . card('Avg rebooking', pct($avgRebooking), rag_threshold($avgRebooking, $targets['barber_rebooking_target'], $targets['barber_rebooking_amber']), '/barbers?week=' . rawurlencode($week))
        . card('Avg utilisation', pct($avgUtilisation), rag_threshold($avgUtilisation, $targets['barber_utilisation_target'], $targets['barber_utilisation_amber']), '/barbers?week=' . rawurlencode($week));

    $strong = [];
    $attention = [];
    $body = '';
    foreach ($rows as $row) {
        if ($row['overall_rag'] === RAG_GREEN) {
            $strong[] = $row['barber'] . ' is green overall at ' . money($row['rtb']) . ' RTB across ' . $row['days_worked'] . ' days.';
        } else {
            $attention[] = $row['barber'] . ' needs focus: RTB ' . $row['rtb_rag'] . ', days ' . $row['days_rag'] . ', overall ' . $row['overall_rag'] . '.';
        }
        $body .= '<tr><td>' . e($row['site']) . '</td><td>' . e($row['barber']) . '</td><td>' . money($row['rtb']) . '</td><td>' . e($row['days_worked']) . '</td><td>' . badge($row['rtb_rag']) . '</td><td>' . badge($row['days_rag']) . '</td><td>' . badge($row['overall_rag']) . '</td></tr>';
    }

    render_page('Barber Dashboard', '<section class="hero"><div><p class="eyebrow">Operations dashboard</p><h1>Barber weekly performance</h1><p>RTB, chair occupancy, days worked, rebooking, and utilisation for the selected week.</p></div>' . week_filter('/dashboard') . '</section>
    <section class="metric-grid">' . $cards . '</section>
    <div class="two-col">' . insight_panel('Performing strongly', $strong, 'No green barber performance yet for this week.') . insight_panel('Areas to improve', $attention, 'No amber or red barber performance this week.') . '</div>
    <section class="panel"><div class="panel-title"><h2>RAG factors</h2><a href="/barbers?week=' . e($week) . '">Add or review submissions</a></div><table><thead><tr><th>Site</th><th>Barber</th><th>RTB</th><th>Days</th><th>RTB RAG</th><th>Days RAG</th><th>Overall</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function show_training_dashboard(): void
{
    require_area('training');
    $week = selected_week();
    $targets = targets_for_kpi();
    $rows = query_all('SELECT * FROM training_submissions WHERE week_start = ? ORDER BY learner', [$week]);
    $count = count($rows);
    $avgAttendance = average_value(array_column($rows, 'attendance_pct'));
    $avgProgress = average_value(array_column($rows, 'progress_pct'));
    $flags = array_sum(array_map('intval', array_column($rows, 'safeguarding_flags')));
    $attendanceRag = rag_threshold($avgAttendance, $targets['training_attendance_target'], $targets['training_attendance_amber']);
    $safeguardingRag = $flags === 0 ? RAG_GREEN : ($flags === 1 ? RAG_AMBER : RAG_RED);

    $cards = card('Active learners', (string) $count, '', '/training?week=' . rawurlencode($week))
        . card('Avg attendance', pct($avgAttendance), $attendanceRag, '/training?week=' . rawurlencode($week))
        . card('Avg progress', pct($avgProgress), '', '/training?week=' . rawurlencode($week))
        . card('Safeguarding flags', (string) $flags, $safeguardingRag, '/training?week=' . rawurlencode($week));

    $strong = [];
    $attention = [];
    $body = '';
    foreach ($rows as $row) {
        $rag = training_rag((float) $row['attendance_pct'], (int) $row['safeguarding_flags'], $targets);
        if ($rag['overall_rag'] === RAG_GREEN) {
            $strong[] = $row['learner'] . ' is on track with ' . pct($row['attendance_pct']) . ' attendance and no safeguarding flags.';
        } else {
            $attention[] = $row['learner'] . ' needs focus: attendance ' . $rag['attendance_rag'] . ', safeguarding ' . $rag['safeguarding_rag'] . '.';
        }
        $body .= '<tr><td>' . e($row['learner']) . '</td><td>' . pct($row['attendance_pct']) . '</td><td>' . pct($row['progress_pct']) . '</td><td>' . e($row['epa_readiness']) . '</td><td>' . (int) $row['safeguarding_flags'] . '</td><td>' . badge($rag['attendance_rag']) . '</td><td>' . badge($rag['safeguarding_rag']) . '</td><td>' . badge($rag['overall_rag']) . '</td></tr>';
    }

    render_page('Training Dashboard', '<section class="hero"><div><p class="eyebrow">Training dashboard</p><h1>Learner health</h1><p>Attendance, progress, EPA readiness, and safeguarding pressure for the selected week.</p></div>' . week_filter('/dashboard') . '</section>
    <section class="metric-grid">' . $cards . '</section>
    <div class="two-col">' . insight_panel('Performing strongly', $strong, 'No learners are green overall yet for this week.') . insight_panel('Areas to improve', $attention, 'No learner interventions highlighted this week.') . '</div>
    <section class="panel"><div class="panel-title"><h2>RAG factors</h2><a href="/training?week=' . e($week) . '">Add or review submissions</a></div><table><thead><tr><th>Learner</th><th>Attendance</th><th>Progress</th><th>EPA</th><th>Flags</th><th>Attendance RAG</th><th>Safeguarding RAG</th><th>Overall</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function show_social_dashboard(): void
{
    require_area('social');
    $week = selected_week();
    $targets = targets_for_kpi();
    $rows = query_all('SELECT b.*, brands.name AS brand FROM brand_submissions b JOIN brands ON brands.id = b.brand_id WHERE b.week_start = ? ORDER BY brands.name', [$week]);
    $brandCount = max(count($rows), 1);
    $posts = array_sum(array_map('intval', array_column($rows, 'posts')));
    $reels = array_sum(array_map('intval', array_column($rows, 'reels')));
    $leads = array_sum(array_map('intval', array_column($rows, 'leads')));
    $followUps = array_sum(array_map('intval', array_column($rows, 'follow_ups')));
    $followUpPct = $leads > 0 ? $followUps / $leads : 1;

    $cards = card('Posts', (string) $posts, rag_threshold($posts, $brandCount * $targets['social_posts_target'], $brandCount * $targets['social_posts_amber']), '/social?week=' . rawurlencode($week))
        . card('Reels', (string) $reels, rag_threshold($reels, $brandCount * $targets['social_reels_target'], $brandCount * $targets['social_reels_amber']), '/social?week=' . rawurlencode($week))
        . card('Leads', (string) $leads, '', '/social?week=' . rawurlencode($week))
        . card('Follow-up rate', pct($followUpPct), rag_threshold($followUpPct, $targets['social_followup_target'], $targets['social_followup_amber']), '/social?week=' . rawurlencode($week));

    $strong = [];
    $attention = [];
    $body = '';
    foreach ($rows as $row) {
        $rag = brand_rag((int) $row['posts'], (int) $row['reels'], (int) $row['leads'], (int) $row['follow_ups'], $targets);
        if ($rag['overall_rag'] === RAG_GREEN) {
            $strong[] = $row['brand'] . ' is green overall with ' . $row['posts'] . ' posts, ' . $row['reels'] . ' reels, and ' . $row['leads'] . ' leads.';
        } else {
            $attention[] = $row['brand'] . ' needs focus: posts ' . $rag['posts_rag'] . ', reels ' . $rag['reels_rag'] . ', follow-up ' . $rag['followup_rag'] . '.';
        }
        $body .= '<tr><td>' . e($row['brand']) . '</td><td>' . (int) $row['posts'] . '</td><td>' . (int) $row['reels'] . '</td><td>' . (int) $row['leads'] . '</td><td>' . (int) $row['follow_ups'] . '</td><td>' . badge($rag['posts_rag']) . '</td><td>' . badge($rag['reels_rag']) . '</td><td>' . badge($rag['followup_rag']) . '</td><td>' . badge($rag['overall_rag']) . '</td></tr>';
    }

    render_page('Social Dashboard', '<section class="hero"><div><p class="eyebrow">Social dashboard</p><h1>Brand cadence and lead discipline</h1><p>Content cadence, lead generation, and follow-up performance for the selected week.</p></div>' . week_filter('/dashboard') . '</section>
    <section class="metric-grid">' . $cards . '</section>
    <div class="two-col">' . insight_panel('Performing strongly', $strong, 'No brands are green overall yet for this week.') . insight_panel('Areas to improve', $attention, 'No brand issues highlighted this week.') . '</div>
    <section class="panel"><div class="panel-title"><h2>RAG factors</h2><a href="/social?week=' . e($week) . '">Add or review submissions</a></div><table><thead><tr><th>Brand</th><th>Posts</th><th>Reels</th><th>Leads</th><th>Follow-ups</th><th>Posts RAG</th><th>Reels RAG</th><th>Follow-up RAG</th><th>Overall</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function show_hr_dashboard(): void
{
    require_area('hr');
    $week = selected_week();
    $rows = query_all('SELECT h.*, r.name AS role_name FROM hr_recruitment_submissions h JOIN recruitment_roles r ON r.id = h.role_id WHERE h.week_start = ? ORDER BY r.name', [$week]);
    $required = array_sum(array_map('intval', array_column($rows, 'required_count')));
    $pipeline = array_sum(array_map('intval', array_column($rows, 'active_pipeline')));
    $interviews = array_sum(array_map('intval', array_column($rows, 'interviews')));
    $offers = array_sum(array_map('intval', array_column($rows, 'offers')));
    $gap = $required - $pipeline;
    $pipelineRag = $pipeline >= $required ? RAG_GREEN : ($pipeline > 0 ? RAG_AMBER : RAG_RED);

    $cards = card('Required roles', (string) $required, '', '/hr?week=' . rawurlencode($week))
        . card('Active pipeline', (string) $pipeline, $pipelineRag, '/hr?week=' . rawurlencode($week))
        . card('Pipeline gap', (string) $gap, $gap <= 0 ? RAG_GREEN : RAG_RED, '/hr?week=' . rawurlencode($week))
        . card('Interviews', (string) $interviews, '', '/hr?week=' . rawurlencode($week))
        . card('Offers', (string) $offers, '', '/hr?week=' . rawurlencode($week));

    $strong = [];
    $attention = [];
    $body = '';
    foreach ($rows as $row) {
        $rag = recruitment_rag((int) $row['required_count'], (int) $row['active_pipeline']);
        $roleGap = (int) $row['required_count'] - (int) $row['active_pipeline'];
        if ($rag['pipeline_rag'] === RAG_GREEN) {
            $strong[] = $row['role_name'] . ' pipeline meets requirement with ' . $row['active_pipeline'] . ' active candidates.';
        } else {
            $attention[] = $row['role_name'] . ' has a pipeline gap of ' . $roleGap . ' against requirement.';
        }
        $body .= '<tr><td>' . e($row['role_name']) . '</td><td>' . (int) $row['required_count'] . '</td><td>' . (int) $row['active_pipeline'] . '</td><td>' . $roleGap . '</td><td>' . (int) $row['interviews'] . '</td><td>' . (int) $row['offers'] . '</td><td>' . badge($rag['pipeline_rag']) . '</td></tr>';
    }

    render_page('HR Dashboard', '<section class="hero"><div><p class="eyebrow">HR dashboard</p><h1>Recruitment pipeline</h1><p>Pipeline coverage, gaps, interviews, and offers for the selected week.</p></div>' . week_filter('/dashboard') . '</section>
    <section class="metric-grid">' . $cards . '</section>
    <div class="two-col">' . insight_panel('Performing strongly', $strong, 'No roles are green on pipeline yet for this week.') . insight_panel('Areas to improve', $attention, 'No recruitment gaps highlighted this week.') . '</div>
    <section class="panel"><div class="panel-title"><h2>RAG factors</h2><a href="/hr?week=' . e($week) . '">Add or review submissions</a></div><table><thead><tr><th>Role</th><th>Required</th><th>Pipeline</th><th>Gap</th><th>Interviews</th><th>Offers</th><th>Pipeline RAG</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function executive_metrics(string $week, array $targets): array
{
    $weeklyRtb = (float) (query_one('SELECT COALESCE(SUM(rtb_cash + rtb_card), 0) AS total FROM weekly_barber_submissions WHERE week_start = ?', [$week])['total'] ?? 0);
    $occupied = (int) (query_one('SELECT COUNT(*) AS total FROM weekly_barber_submissions WHERE week_start = ? AND (rtb_cash + rtb_card) > 0', [$week])['total'] ?? 0);
    $learners = (int) (query_one('SELECT COUNT(*) AS total FROM training_submissions WHERE week_start = ?', [$week])['total'] ?? 0);
    $socialLeads = (int) (query_one('SELECT COALESCE(SUM(leads), 0) AS total FROM brand_submissions WHERE week_start = ?', [$week])['total'] ?? 0);
    $senior = (int) (query_one(
        'SELECT COALESCE(MAX(active_pipeline), 0) AS total
         FROM hr_recruitment_submissions h
         JOIN recruitment_roles r ON r.id = h.role_id
         WHERE h.week_start = ? AND r.name = ?',
        [$week, 'Senior Barber']
    )['total'] ?? 0);
    $openActions = (int) (query_one("SELECT COUNT(*) AS total FROM action_tracker WHERE status <> 'Closed'", [])['total'] ?? 0);
    $openRisks = (int) (query_one("SELECT COUNT(*) AS total FROM risk_register WHERE status <> 'Closed'", [])['total'] ?? 0);

    return [
        'weekly_rtb' => $weeklyRtb,
        'weekly_rtb_rag' => rag_threshold($weeklyRtb, $targets['strategy_weekly_rtb_target'], $targets['strategy_weekly_rtb_amber']),
        'occupied_chairs' => $occupied,
        'occupied_chairs_rag' => rag_threshold($occupied, $targets['strategy_occupied_chairs_target'], $targets['strategy_occupied_chairs_amber']),
        'active_learners' => $learners,
        'active_learners_rag' => rag_threshold($learners, $targets['strategy_active_learners_target'], $targets['strategy_active_learners_amber']),
        'social_leads' => $socialLeads,
        'social_leads_rag' => rag_threshold($socialLeads, $targets['strategy_social_leads_target'], $targets['strategy_social_leads_amber']),
        'senior_pipeline' => $senior,
        'senior_pipeline_rag' => rag_threshold($senior, $targets['strategy_senior_pipeline_target'], $targets['strategy_senior_pipeline_amber']),
        'open_actions' => $openActions,
        'open_risks' => $openRisks,
    ];
}

function barber_rows(string $week, array $targets): array
{
    $rows = query_all(
        'SELECT w.*, s.name AS site, b.name AS barber, u.name AS submitted_by_name
         FROM weekly_barber_submissions w
         JOIN sites s ON s.id = w.site_id
         JOIN barbers b ON b.id = w.barber_id
         JOIN users u ON u.id = w.submitted_by
         WHERE w.week_start = ?
         ORDER BY s.name, b.name',
        [$week]
    );

    foreach ($rows as &$row) {
        $row['rtb'] = (float) $row['rtb_cash'] + (float) $row['rtb_card'];
        $row += barber_rag((float) $row['rtb'], (float) $row['days_worked'], $targets);
    }

    return $rows;
}

function show_barbers(): void
{
    require_area('barbers');
    $week = selected_week();
    $targets = targets_for_kpi();
    $rows = barber_rows($week, $targets);

    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . e($row['site']) . '</td><td>' . e($row['barber']) . '</td><td>' . money($row['rtb']) . '</td><td>' . e($row['days_worked']) . '</td><td>' . pct($row['rebooking_pct']) . '</td><td>' . pct($row['utilisation_pct']) . '</td><td>' . badge($row['overall_rag']) . '</td></tr>';
    }

    render_page('Barbers', '<section class="hero"><div><p class="eyebrow">Operations</p><h1>Barber submissions</h1></div>' . week_filter('/barbers') . '</section>
    ' . submission_form_barber($week) . '
    <section class="panel"><h2>Weekly barber RAG</h2><table><thead><tr><th>Site</th><th>Barber</th><th>RTB</th><th>Days</th><th>Rebooking</th><th>Utilisation</th><th>RAG</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function submission_form_barber(string $week): string
{
    if (!can_access('barbers', true)) {
        return '';
    }

    return '<section class="panel"><h2>Add barber submission</h2><form class="grid-form" method="post" action="/barbers?week=' . e($week) . '">' . csrf_field() . '
        <label>Week<input type="date" name="week_start" value="' . e($week) . '" required></label>
        <label>Site<select name="site_id" required>' . lookup_options('sites') . '</select></label>
        <label>Barber<select name="barber_id" required>' . lookup_options('barbers') . '</select></label>
        <label>RTB cash<input type="number" name="rtb_cash" min="0" step="0.01" required></label>
        <label>RTB card<input type="number" name="rtb_card" min="0" step="0.01" required></label>
        <label>Total sales<input type="number" name="total_sales" min="0" step="0.01" required></label>
        <label>Days worked<input type="number" name="days_worked" min="0" max="7" step="0.5" required></label>
        <label>Rebooking %<input type="number" name="rebooking_pct" min="0" max="100" step="0.1" required></label>
        <label>Utilisation %<input type="number" name="utilisation_pct" min="0" max="100" step="0.1" required></label>
        <label class="span-2">Notes<textarea name="notes"></textarea></label>
        <button type="submit">Save submission</button>
    </form></section>';
}

function store_barber_submission(): void
{
    require_area('barbers', true);
    execute_sql(
        'INSERT INTO weekly_barber_submissions (week_start, site_id, barber_id, rtb_cash, rtb_card, total_sales, days_worked, rebooking_pct, utilisation_pct, notes, submitted_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $_POST['week_start'],
            (int) $_POST['site_id'],
            (int) $_POST['barber_id'],
            (float) $_POST['rtb_cash'],
            (float) $_POST['rtb_card'],
            (float) $_POST['total_sales'],
            (float) $_POST['days_worked'],
            percentage_input_to_decimal($_POST['rebooking_pct']),
            percentage_input_to_decimal($_POST['utilisation_pct']),
            trim((string) ($_POST['notes'] ?? '')) ?: null,
            (int) current_user()['id'],
        ]
    );
    flash('Barber submission saved.');
    redirect('/barbers?week=' . e((string) $_POST['week_start']));
}

function show_training(): void
{
    require_area('training');
    $week = selected_week();
    $targets = targets_for_kpi();
    $rows = query_all('SELECT t.*, u.name AS submitted_by_name FROM training_submissions t JOIN users u ON u.id = t.submitted_by WHERE t.week_start = ? ORDER BY learner', [$week]);
    $body = '';
    foreach ($rows as $row) {
        $rag = training_rag((float) $row['attendance_pct'], (int) $row['safeguarding_flags'], $targets);
        $body .= '<tr><td>' . e($row['learner']) . '</td><td>' . pct($row['attendance_pct']) . '</td><td>' . pct($row['progress_pct']) . '</td><td>' . e($row['epa_readiness']) . '</td><td>' . (int) $row['safeguarding_flags'] . '</td><td>' . badge($rag['overall_rag']) . '</td></tr>';
    }

    render_page('Training', '<section class="hero"><div><p class="eyebrow">Training</p><h1>Learner health</h1></div>' . week_filter('/training') . '</section>' . submission_form_training($week) . '<section class="panel"><h2>Weekly learner RAG</h2><table><thead><tr><th>Learner</th><th>Attendance</th><th>Progress</th><th>EPA</th><th>Flags</th><th>RAG</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function submission_form_training(string $week): string
{
    if (!can_access('training', true)) {
        return '';
    }

    return '<section class="panel"><h2>Add training submission</h2><form class="grid-form" method="post" action="/training?week=' . e($week) . '">' . csrf_field() . '
        <label>Week<input type="date" name="week_start" value="' . e($week) . '" required></label>
        <label>Learner<input name="learner" required></label>
        <label>Attendance %<input type="number" name="attendance_pct" min="0" max="100" step="0.1" required></label>
        <label>Progress %<input type="number" name="progress_pct" min="0" max="100" step="0.1" required></label>
        <label>EPA readiness<select name="epa_readiness"><option>On Track</option><option>At Risk</option><option>Not Ready</option></select></label>
        <label>Safeguarding flags<input type="number" name="safeguarding_flags" min="0" step="1" required></label>
        <label class="span-2">Risk notes<textarea name="risk_notes"></textarea></label>
        <button type="submit">Save submission</button>
    </form></section>';
}

function store_training_submission(): void
{
    require_area('training', true);
    execute_sql(
        'INSERT INTO training_submissions (week_start, learner, attendance_pct, progress_pct, epa_readiness, safeguarding_flags, risk_notes, submitted_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $_POST['week_start'],
            trim((string) $_POST['learner']),
            percentage_input_to_decimal($_POST['attendance_pct']),
            percentage_input_to_decimal($_POST['progress_pct']),
            (string) $_POST['epa_readiness'],
            (int) $_POST['safeguarding_flags'],
            trim((string) ($_POST['risk_notes'] ?? '')) ?: null,
            (int) current_user()['id'],
        ]
    );
    flash('Training submission saved.');
    redirect('/training?week=' . e((string) $_POST['week_start']));
}

function show_social(): void
{
    require_area('social');
    $week = selected_week();
    $targets = targets_for_kpi();
    $rows = query_all('SELECT b.*, brands.name AS brand FROM brand_submissions b JOIN brands ON brands.id = b.brand_id WHERE b.week_start = ? ORDER BY brands.name', [$week]);
    $body = '';
    foreach ($rows as $row) {
        $rag = brand_rag((int) $row['posts'], (int) $row['reels'], (int) $row['leads'], (int) $row['follow_ups'], $targets);
        $body .= '<tr><td>' . e($row['brand']) . '</td><td>' . (int) $row['posts'] . '</td><td>' . (int) $row['reels'] . '</td><td>' . number_format((int) $row['reach']) . '</td><td>' . (int) $row['leads'] . '</td><td>' . (int) $row['follow_ups'] . '</td><td>' . pct($row['conversion_pct']) . '</td><td>' . badge($rag['overall_rag']) . '</td></tr>';
    }

    render_page('Social', '<section class="hero"><div><p class="eyebrow">Brand</p><h1>Social media metrics</h1></div>' . week_filter('/social') . '</section>' . submission_form_social($week) . '<section class="panel"><h2>Weekly brand RAG</h2><table><thead><tr><th>Brand</th><th>Posts</th><th>Reels</th><th>Reach</th><th>Leads</th><th>Follow-ups</th><th>Conversion</th><th>RAG</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function submission_form_social(string $week): string
{
    if (!can_access('social', true)) {
        return '';
    }

    return '<section class="panel"><h2>Add social submission</h2><form class="grid-form" method="post" action="/social?week=' . e($week) . '">' . csrf_field() . '
        <label>Week<input type="date" name="week_start" value="' . e($week) . '" required></label>
        <label>Brand<select name="brand_id" required>' . lookup_options('brands') . '</select></label>
        <label>Posts<input type="number" name="posts" min="0" step="1" required></label>
        <label>Reels<input type="number" name="reels" min="0" step="1" required></label>
        <label>Reach<input type="number" name="reach" min="0" step="1" required></label>
        <label>Engagement<input type="number" name="engagement" min="0" step="1" required></label>
        <label>Leads<input type="number" name="leads" min="0" step="1" required></label>
        <label>Follow-ups<input type="number" name="follow_ups" min="0" step="1" required></label>
        <label>Conversion %<input type="number" name="conversion_pct" min="0" max="100" step="0.1" required></label>
        <label class="span-2">Notes<textarea name="notes"></textarea></label>
        <button type="submit">Save submission</button>
    </form></section>';
}

function store_social_submission(): void
{
    require_area('social', true);
    execute_sql(
        'INSERT INTO brand_submissions (week_start, brand_id, posts, reels, reach, engagement, leads, follow_ups, conversion_pct, notes, submitted_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $_POST['week_start'],
            (int) $_POST['brand_id'],
            (int) $_POST['posts'],
            (int) $_POST['reels'],
            (int) $_POST['reach'],
            (int) $_POST['engagement'],
            (int) $_POST['leads'],
            (int) $_POST['follow_ups'],
            percentage_input_to_decimal($_POST['conversion_pct']),
            trim((string) ($_POST['notes'] ?? '')) ?: null,
            (int) current_user()['id'],
        ]
    );
    flash('Social submission saved.');
    redirect('/social?week=' . e((string) $_POST['week_start']));
}

function show_hr(): void
{
    require_area('hr');
    $week = selected_week();
    $rows = query_all('SELECT h.*, r.name AS role_name FROM hr_recruitment_submissions h JOIN recruitment_roles r ON r.id = h.role_id WHERE h.week_start = ? ORDER BY r.name', [$week]);
    $body = '';
    foreach ($rows as $row) {
        $rag = recruitment_rag((int) $row['required_count'], (int) $row['active_pipeline']);
        $gap = (int) $row['required_count'] - (int) $row['active_pipeline'];
        $body .= '<tr><td>' . e($row['role_name']) . '</td><td>' . (int) $row['required_count'] . '</td><td>' . (int) $row['active_pipeline'] . '</td><td>' . (int) $row['interviews'] . '</td><td>' . (int) $row['offers'] . '</td><td>' . $gap . '</td><td>' . badge($rag['pipeline_rag']) . '</td></tr>';
    }

    render_page('HR', '<section class="hero"><div><p class="eyebrow">People</p><h1>Recruitment pipeline</h1></div>' . week_filter('/hr') . '</section>' . submission_form_hr($week) . '<section class="panel"><h2>Weekly HR RAG</h2><table><thead><tr><th>Role</th><th>Required</th><th>Pipeline</th><th>Interviews</th><th>Offers</th><th>Gap</th><th>RAG</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function submission_form_hr(string $week): string
{
    if (!can_access('hr', true)) {
        return '';
    }

    return '<section class="panel"><h2>Add HR submission</h2><form class="grid-form" method="post" action="/hr?week=' . e($week) . '">' . csrf_field() . '
        <label>Week<input type="date" name="week_start" value="' . e($week) . '" required></label>
        <label>Role<select name="role_id" required>' . lookup_options('recruitment_roles') . '</select></label>
        <label>Required<input type="number" name="required_count" min="0" step="1" required></label>
        <label>Active pipeline<input type="number" name="active_pipeline" min="0" step="1" required></label>
        <label>Interviews<input type="number" name="interviews" min="0" step="1" required></label>
        <label>Offers<input type="number" name="offers" min="0" step="1" required></label>
        <label class="span-2">Notes<textarea name="notes"></textarea></label>
        <button type="submit">Save submission</button>
    </form></section>';
}

function store_hr_submission(): void
{
    require_area('hr', true);
    execute_sql(
        'INSERT INTO hr_recruitment_submissions (week_start, role_id, required_count, active_pipeline, interviews, offers, notes, submitted_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $_POST['week_start'],
            (int) $_POST['role_id'],
            (int) $_POST['required_count'],
            (int) $_POST['active_pipeline'],
            (int) $_POST['interviews'],
            (int) $_POST['offers'],
            trim((string) ($_POST['notes'] ?? '')) ?: null,
            (int) current_user()['id'],
        ]
    );
    flash('HR submission saved.');
    redirect('/hr?week=' . e((string) $_POST['week_start']));
}

function leadership_rows(string $week, array $targets): array
{
    $leaders = query_all('SELECT leaders.*, users.id AS user_id FROM leaders LEFT JOIN users ON users.name = leaders.name ORDER BY leaders.id');
    $barberScores = array_column(barber_rows($week, $targets), 'score');
    $brandScores = [];
    foreach (query_all('SELECT * FROM brand_submissions WHERE week_start = ?', [$week]) as $row) {
        $brandScores[] = brand_rag((int) $row['posts'], (int) $row['reels'], (int) $row['leads'], (int) $row['follow_ups'], $targets)['score'];
    }
    $trainingScores = [];
    foreach (query_all('SELECT * FROM training_submissions WHERE week_start = ?', [$week]) as $row) {
        $trainingScores[] = training_rag((float) $row['attendance_pct'], (int) $row['safeguarding_flags'], $targets)['score'];
    }
    $hrScores = [];
    foreach (query_all('SELECT * FROM hr_recruitment_submissions WHERE week_start = ?', [$week]) as $row) {
        $hrScores[] = recruitment_rag((int) $row['required_count'], (int) $row['active_pipeline'])['score'];
    }

    $avg = fn(array $scores): float => count($scores) ? array_sum($scores) / count($scores) : 3.0;
    $rows = [];
    foreach ($leaders as $leader) {
        $userId = (int) ($leader['user_id'] ?? 0);
        $actionScores = [];
        foreach (query_all('SELECT priority, status FROM action_tracker WHERE owner_user_id = ?', [$userId]) as $row) {
            $actionScores[] = priority_status_score($row['priority'], $row['status']);
        }
        foreach (query_all('SELECT priority, status FROM risk_register WHERE owner_user_id = ?', [$userId]) as $row) {
            $actionScores[] = priority_status_score($row['priority'], $row['status']);
        }

        $revenue = $leader['name'] === 'Cosmin' ? $avg($barberScores) : 3.0;
        $brand = $leader['name'] === 'Mario' ? $avg($brandScores) : 3.0;
        $training = $leader['name'] === 'Ravi' ? $avg($trainingScores) : 3.0;
        $recruitment = $leader['name'] === 'Luke' ? $avg($hrScores) : 3.0;
        $riskAction = $avg($actionScores);
        $overall = ($revenue + $brand + $training + $recruitment + $riskAction) / 5;

        $rows[] = [
            'leader' => $leader['name'],
            'area' => $leader['area'],
            'revenue_score' => $revenue,
            'brand_score' => $brand,
            'training_score' => $training,
            'recruitment_score' => $recruitment,
            'risk_action_score' => $riskAction,
            'overall_rag' => rag_from_score($overall),
            'open_risks' => (int) (query_one("SELECT COUNT(*) AS total FROM risk_register WHERE owner_user_id = ? AND status <> 'Closed'", [$userId])['total'] ?? 0),
            'open_actions' => (int) (query_one("SELECT COUNT(*) AS total FROM action_tracker WHERE owner_user_id = ? AND status <> 'Closed'", [$userId])['total'] ?? 0),
        ];
    }

    return $rows;
}

function show_leadership(): void
{
    require_area('leadership');
    $week = selected_week();
    $targets = targets_for_kpi();
    $rows = leadership_rows($week, $targets);
    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . e($row['leader']) . '</td><td>' . e($row['area']) . '</td><td>' . number_format($row['revenue_score'], 1) . '</td><td>' . number_format($row['brand_score'], 1) . '</td><td>' . number_format($row['training_score'], 1) . '</td><td>' . number_format($row['recruitment_score'], 1) . '</td><td>' . number_format($row['risk_action_score'], 1) . '</td><td>' . badge($row['overall_rag']) . '</td></tr>';
    }

    render_page('Leadership', '<section class="hero"><div><p class="eyebrow">Shareholder view</p><h1>Leadership RAG</h1></div>' . week_filter('/leadership') . '</section><section class="panel"><table><thead><tr><th>Leader</th><th>Area</th><th>Revenue</th><th>Brand</th><th>Training</th><th>Recruitment</th><th>Risk/action</th><th>Overall</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function show_strategy(): void
{
    require_area('strategy');
    $week = selected_week();
    $targets = targets_for_kpi();
    $metrics = executive_metrics($week, $targets);
    $items = [
        ['Weekly RTB', $metrics['weekly_rtb'], $targets['strategy_weekly_rtb_target'], $metrics['weekly_rtb_rag'], true],
        ['Occupied Chairs', $metrics['occupied_chairs'], $targets['strategy_occupied_chairs_target'], $metrics['occupied_chairs_rag'], false],
        ['Active Learners', $metrics['active_learners'], $targets['strategy_active_learners_target'], $metrics['active_learners_rag'], false],
        ['Social Leads', $metrics['social_leads'], $targets['strategy_social_leads_target'], $metrics['social_leads_rag'], false],
        ['Senior Barber Pipeline', $metrics['senior_pipeline'], $targets['strategy_senior_pipeline_target'], $metrics['senior_pipeline_rag'], false],
    ];

    $body = '';
    foreach ($items as [$name, $current, $required, $rag, $isMoney]) {
        $variance = (float) $current - (float) $required;
        $body .= '<tr><td>' . e($name) . '</td><td>' . ($isMoney ? money($current) : e($current)) . '</td><td>' . ($isMoney ? money($required) : e($required)) . '</td><td>' . ($isMoney ? money($variance) : e($variance)) . '</td><td>' . badge($rag) . '</td></tr>';
    }

    render_page('5x5 Strategy', '<section class="hero"><div><p class="eyebrow">5x5</p><h1>Strategic run-rate</h1></div>' . week_filter('/strategy') . '</section><section class="panel"><table><thead><tr><th>KPI</th><th>Current</th><th>Required</th><th>Variance</th><th>Status</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function show_risks(): void
{
    require_area('leadership');
    $rows = query_all('SELECT r.*, u.name AS owner FROM risk_register r JOIN users u ON u.id = r.owner_user_id ORDER BY r.status, r.priority, r.due_date');
    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . e($row['week_start']) . '</td><td>' . e($row['trigger_label']) . '</td><td>' . e($row['risk']) . '</td><td>' . e($row['owner']) . '</td><td>' . e($row['priority']) . '</td><td>' . e($row['status']) . '</td><td>' . e($row['due_date']) . '</td></tr>';
    }

    render_page('Risks', '<section class="hero"><div><p class="eyebrow">Governance</p><h1>Risk register</h1></div></section>' . risk_form() . '<section class="panel"><table><thead><tr><th>Week</th><th>Trigger</th><th>Risk</th><th>Owner</th><th>Priority</th><th>Status</th><th>Due</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function risk_form(): string
{
    return '<section class="panel"><h2>Add risk</h2><form class="grid-form" method="post" action="/risks">' . csrf_field() . '
        <label>Week<input type="date" name="week_start" value="' . e(selected_week()) . '" required></label>
        <label>Owner<select name="owner_user_id">' . lookup_options('users') . '</select></label>
        <label>Priority<select name="priority"><option>High</option><option>Medium</option><option>Low</option></select></label>
        <label>Status<select name="status"><option>Open</option><option>In Progress</option><option>Closed</option></select></label>
        <label>Due date<input type="date" name="due_date"></label>
        <label>Trigger<input name="trigger_label" required></label>
        <label class="span-2">Risk<textarea name="risk" required></textarea></label>
        <label class="span-2">Notes<textarea name="notes"></textarea></label>
        <button type="submit">Save risk</button>
    </form></section>';
}

function store_risk(): void
{
    require_area('leadership', true);
    execute_sql(
        'INSERT INTO risk_register (week_start, trigger_label, risk, owner_user_id, priority, status, due_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$_POST['week_start'], $_POST['trigger_label'], $_POST['risk'], (int) $_POST['owner_user_id'], $_POST['priority'], $_POST['status'], $_POST['due_date'] ?: null, $_POST['notes'] ?: null]
    );
    flash('Risk saved.');
    redirect('/risks');
}

function show_actions(): void
{
    require_area('leadership');
    $rows = query_all('SELECT a.*, u.name AS owner FROM action_tracker a JOIN users u ON u.id = a.owner_user_id ORDER BY a.status, a.priority, a.due_date');
    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . e($row['week_start']) . '</td><td>' . e($row['owner']) . '</td><td>' . e($row['action']) . '</td><td>' . e($row['linked_area']) . '</td><td>' . e($row['priority']) . '</td><td>' . e($row['status']) . '</td><td>' . e($row['due_date']) . '</td></tr>';
    }

    render_page('Actions', '<section class="hero"><div><p class="eyebrow">Governance</p><h1>Action tracker</h1></div></section>' . action_form() . '<section class="panel"><table><thead><tr><th>Week</th><th>Owner</th><th>Action</th><th>Area</th><th>Priority</th><th>Status</th><th>Due</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function action_form(): string
{
    return '<section class="panel"><h2>Add action</h2><form class="grid-form" method="post" action="/actions">' . csrf_field() . '
        <label>Week<input type="date" name="week_start" value="' . e(selected_week()) . '" required></label>
        <label>Owner<select name="owner_user_id">' . lookup_options('users') . '</select></label>
        <label>Priority<select name="priority"><option>High</option><option>Medium</option><option>Low</option></select></label>
        <label>Status<select name="status"><option>Open</option><option>In Progress</option><option>Closed</option></select></label>
        <label>Due date<input type="date" name="due_date"></label>
        <label>Linked area<input name="linked_area" required></label>
        <label class="span-2">Action<textarea name="action" required></textarea></label>
        <label class="span-2">Notes<textarea name="notes"></textarea></label>
        <button type="submit">Save action</button>
    </form></section>';
}

function store_action(): void
{
    require_area('leadership', true);
    execute_sql(
        'INSERT INTO action_tracker (week_start, owner_user_id, action, due_date, status, priority, linked_area, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$_POST['week_start'], (int) $_POST['owner_user_id'], $_POST['action'], $_POST['due_date'] ?: null, $_POST['status'], $_POST['priority'], $_POST['linked_area'], $_POST['notes'] ?: null]
    );
    flash('Action saved.');
    redirect('/actions');
}

function show_submissions(): void
{
    $cards = '';
    foreach ([['/barbers', 'Barbers', 'barbers'], ['/training', 'Training', 'training'], ['/social', 'Social', 'social'], ['/hr', 'HR', 'hr']] as [$href, $label, $area]) {
        if (can_access($area)) {
            $cards .= '<a class="tile" href="' . e($href) . '"><strong>' . e($label) . '</strong><span>Open weekly submissions</span></a>';
        }
    }
    render_page('Submissions', '<section class="hero"><div><p class="eyebrow">Weekly inputs</p><h1>Submissions</h1></div></section><section class="tile-grid">' . $cards . '</section>');
}

function show_profile(): void
{
    $user = current_user();
    render_page('Profile', '<section class="panel"><h1>Profile</h1><p><strong>' . e($user['name']) . '</strong></p><p>' . e($user['email']) . '</p><p>' . e($user['role_name']) . '</p></section>');
}

function show_admin_users(): void
{
    require_area('admin');
    $rows = query_all('SELECT users.*, roles.name AS role_name FROM users JOIN roles ON roles.id = users.role_id ORDER BY users.name');
    $roles = query_all('SELECT id, name FROM roles ORDER BY id');
    $roleOptions = '';
    foreach ($roles as $role) {
        $roleOptions .= '<option value="' . (int) $role['id'] . '">' . e($role['name']) . '</option>';
    }
    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . e($row['name']) . '</td><td>' . e($row['email']) . '</td><td>' . e($row['role_name']) . '</td><td>' . ((int) $row['active'] ? 'Active' : 'Inactive') . '</td></tr>';
    }

    render_page('Admin Users', '<section class="hero"><div><p class="eyebrow">Admin</p><h1>Users</h1></div><div class="admin-links"><a href="/admin/lookups">Lookups</a><a href="/admin/targets">Targets</a></div></section>
    <section class="panel"><h2>Create user</h2><form class="grid-form" method="post" action="/admin/users">' . csrf_field() . '
        <label>Name<input name="name" required></label>
        <label>Email<input type="email" name="email" required></label>
        <label>Role<select name="role_id">' . $roleOptions . '</select></label>
        <label>Password<input type="password" name="password" required></label>
        <button type="submit">Create user</button>
    </form></section>
    <section class="panel"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody>' . $body . '</tbody></table></section>');
}

function store_user(): void
{
    require_area('admin', true);
    execute_sql(
        'INSERT INTO users (role_id, name, email, password_hash) VALUES (?, ?, ?, ?)',
        [(int) $_POST['role_id'], trim((string) $_POST['name']), strtolower(trim((string) $_POST['email'])), password_hash((string) $_POST['password'], PASSWORD_DEFAULT)]
    );
    $userId = (int) db()->lastInsertId();
    $role = query_one('SELECT role_key, is_shareholder FROM roles WHERE id = ?', [(int) $_POST['role_id']]);
    $roleArea = match ($role['role_key']) {
        'barber' => 'barbers',
        default => $role['role_key'],
    };
    $areas = ((int) $role['is_shareholder'] === 1)
        ? ['barbers', 'training', 'social', 'hr', 'leadership', 'strategy', 'admin']
        : [$roleArea];
    foreach ($areas as $area) {
        execute_sql('INSERT INTO user_permissions (user_id, area, can_read, can_write) VALUES (?, ?, 1, 1)', [$userId, $area]);
    }
    flash('User created.');
    redirect('/admin/users');
}

function show_admin_targets(): void
{
    require_area('admin');
    $rows = query_all('SELECT * FROM targets ORDER BY area, kpi');
    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr><td>' . e($row['area']) . '</td><td>' . e($row['kpi']) . '</td><td><input name="target_' . (int) $row['id'] . '" value="' . e($row['target_value']) . '"></td><td><input name="amber_' . (int) $row['id'] . '" value="' . e($row['amber_threshold']) . '"></td><td>' . e($row['unit']) . '</td><td>' . e($row['notes']) . '</td></tr>';
    }

    render_page('Targets', '<section class="hero"><div><p class="eyebrow">Admin</p><h1>KPI targets</h1></div></section><section class="panel"><form method="post" action="/admin/targets">' . csrf_field() . '<table><thead><tr><th>Area</th><th>KPI</th><th>Target</th><th>Amber</th><th>Unit</th><th>Notes</th></tr></thead><tbody>' . $body . '</tbody></table><button type="submit">Update targets</button></form></section>');
}

function update_targets(): void
{
    require_area('admin', true);
    $rows = query_all('SELECT id FROM targets');
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        execute_sql('UPDATE targets SET target_value = ?, amber_threshold = ? WHERE id = ?', [(float) $_POST["target_{$id}"], (float) $_POST["amber_{$id}"], $id]);
    }
    flash('Targets updated.');
    redirect('/admin/targets');
}

function show_admin_lookups(): void
{
    require_area('admin');
    $sections = '';
    foreach (['sites' => 'Site', 'barbers' => 'Barber', 'brands' => 'Brand', 'recruitment_roles' => 'Recruitment role'] as $table => $label) {
        $rows = query_all("SELECT name FROM {$table} ORDER BY name");
        $list = implode('', array_map(fn($row) => '<li>' . e($row['name']) . '</li>', $rows));
        $sections .= '<section class="panel"><h2>' . e($label) . 's</h2><ul class="compact-list">' . $list . '</ul><form class="inline-form" method="post" action="/admin/lookups">' . csrf_field() . '<input type="hidden" name="table" value="' . e($table) . '"><input name="name" placeholder="New ' . e(strtolower($label)) . '" required><button type="submit">Add</button></form></section>';
    }

    render_page('Lookups', '<section class="hero"><div><p class="eyebrow">Admin</p><h1>Lookups</h1></div></section><div class="two-col">' . $sections . '</div>');
}

function store_lookup(): void
{
    require_area('admin', true);
    $table = (string) $_POST['table'];
    if (!in_array($table, ['sites', 'barbers', 'brands', 'recruitment_roles'], true)) {
        http_response_code(400);
        exit('Invalid lookup table.');
    }
    execute_sql("INSERT INTO {$table} (name) VALUES (?)", [trim((string) $_POST['name'])]);
    flash('Lookup added.');
    redirect('/admin/lookups');
}

function show_not_found(): void
{
    http_response_code(404);
    render_page('Not found', '<section class="panel"><h1>Not found</h1><p>The requested page does not exist.</p></section>');
}
