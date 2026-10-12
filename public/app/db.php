<?php
// Storage: one SQLite file in the data folder. All SQL lives here.

const CRM_INSERT_FIELDS = ['full_name', 'phone', 'email', 'company', 'job_title', 'zone', 'region', 'city',
    'service', 'budget', 'timeline', 'message', 'source', 'score', 'priority'];
const CRM_FILTER_FIELDS = ['priority', 'status', 'zone', 'region', 'job_title', 'service', 'source'];
const CRM_FACETS = ['priority', 'status', 'zone', 'region', 'job_title', 'service'];
const CRM_PAGE_SIZE = 50;

function crm_data_dir()
{
    $custom = getenv('CRM_DATA_DIR');
    if ($custom) {
        return rtrim($custom, '/\\');
    }
    $settings = crm_settings();
    return !empty($settings['data_dir']) ? rtrim($settings['data_dir'], '/\\') : dirname(__DIR__) . '/data';
}

function crm_db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dir = crm_data_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $pdo = new PDO('sqlite:' . $dir . '/' . crm_settings()['db_file']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() < 1) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS leads (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name         TEXT    NOT NULL,
                phone             TEXT    NOT NULL DEFAULT '',
                email             TEXT    NOT NULL DEFAULT '',
                company           TEXT    NOT NULL DEFAULT '',
                job_title         TEXT    NOT NULL DEFAULT '',
                zone              TEXT    NOT NULL DEFAULT '',
                region            TEXT    NOT NULL DEFAULT '',
                city              TEXT    NOT NULL DEFAULT '',
                service           TEXT    NOT NULL DEFAULT '',
                budget            TEXT    NOT NULL DEFAULT '',
                timeline          TEXT    NOT NULL DEFAULT '',
                message           TEXT    NOT NULL DEFAULT '',
                source            TEXT    NOT NULL DEFAULT '',
                score             INTEGER NOT NULL DEFAULT 0,
                priority          TEXT    NOT NULL DEFAULT 'low',
                priority_locked   INTEGER NOT NULL DEFAULT 0,
                status            TEXT    NOT NULL DEFAULT 'new',
                submissions       INTEGER NOT NULL DEFAULT 1,
                created_at        TEXT    NOT NULL,
                last_submitted_at TEXT    NOT NULL,
                updated_at        TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_leads_phone     ON leads(phone);
            CREATE INDEX IF NOT EXISTS idx_leads_email     ON leads(email);
            CREATE INDEX IF NOT EXISTS idx_leads_priority  ON leads(priority);
            CREATE INDEX IF NOT EXISTS idx_leads_status    ON leads(status);
            CREATE INDEX IF NOT EXISTS idx_leads_zone      ON leads(zone);
            CREATE INDEX IF NOT EXISTS idx_leads_region    ON leads(region);
            CREATE INDEX IF NOT EXISTS idx_leads_job_title ON leads(job_title);
            CREATE INDEX IF NOT EXISTS idx_leads_last      ON leads(last_submitted_at);

            CREATE TABLE IF NOT EXISTS notes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id    INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
                body       TEXT    NOT NULL,
                author     TEXT    NOT NULL DEFAULT '',
                created_at TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_notes_lead ON notes(lead_id);

            CREATE TABLE IF NOT EXISTS rate_hits (
                bucket TEXT    NOT NULL,
                ip     TEXT    NOT NULL,
                at     INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_rate_hits ON rate_hits(bucket, ip, at);

            PRAGMA user_version = 1;
        ");
    }
    return $pdo;
}

/** UTC, in the one format every timestamp column uses, so values sort and compare as text. */
function crm_now()
{
    return gmdate('Y-m-d H:i:s');
}

/** The UTC timestamp at which a calendar day starts in the business timezone. */
function crm_day_start_utc($date, $addDays = 0)
{
    $day = new DateTime($date . ' 00:00:00', new DateTimeZone(crm_config()['timezone']));
    if ($addDays) {
        $day->modify('+' . (int) $addDays . ' day');
    }
    $day->setTimezone(new DateTimeZone('UTC'));
    return $day->format('Y-m-d H:i:s');
}

function crm_query($sql, array $params = [])
{
    $statement = crm_db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

/** Older PHP versions return SQLite integers as strings; the dashboard needs real numbers. */
function crm_lead_row($row)
{
    if (!$row) {
        return null;
    }
    foreach (['id', 'score', 'priority_locked', 'submissions'] as $field) {
        $row[$field] = (int) $row[$field];
    }
    return $row;
}

function crm_insert_lead(array $lead)
{
    $now = crm_now();
    $row = [':created_at' => $now, ':last_submitted_at' => $now, ':updated_at' => $now];
    foreach (CRM_INSERT_FIELDS as $field) {
        $row[':' . $field] = isset($lead[$field]) ? $lead[$field] : '';
    }
    $columns = array_merge(CRM_INSERT_FIELDS, ['created_at', 'last_submitted_at', 'updated_at']);
    crm_query('INSERT INTO leads (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')', $row);
    return (int) crm_db()->lastInsertId();
}

function crm_get_lead($id)
{
    return crm_lead_row(crm_query('SELECT * FROM leads WHERE id = ?', [$id])->fetch());
}

function crm_find_lead_by($column, $value)
{
    $column = $column === 'email' ? 'email' : 'phone';
    return crm_lead_row(crm_query("SELECT * FROM leads WHERE $column = ? ORDER BY id LIMIT 1", [$value])->fetch());
}

/** Column names come from server code only, never from the request. */
function crm_update_lead($id, array $fields)
{
    $sets = [];
    $params = [':id' => $id, ':updated_at' => crm_now()];
    foreach ($fields as $column => $value) {
        $sets[] = "$column = :$column";
        $params[':' . $column] = $value;
    }
    $sets[] = 'updated_at = :updated_at';
    crm_query('UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
}

function crm_delete_lead($id)
{
    crm_query('DELETE FROM leads WHERE id = ?', [$id]);
}

function crm_add_note($leadId, $body, $author = '')
{
    crm_query('INSERT INTO notes (lead_id, body, author, created_at) VALUES (?, ?, ?, ?)', [$leadId, $body, $author, crm_now()]);
}

function crm_list_notes($leadId)
{
    $notes = crm_query('SELECT * FROM notes WHERE lead_id = ? ORDER BY created_at DESC, id DESC', [$leadId])->fetchAll();
    foreach ($notes as &$note) {
        $note['id'] = (int) $note['id'];
        $note['lead_id'] = (int) $note['lead_id'];
    }
    return $notes;
}

/** `$exclude` drops one filter so a facet can count across all of its own values. */
function crm_filters(array $q, $exclude = null)
{
    $where = [];
    $params = [];
    foreach (CRM_FILTER_FIELDS as $field) {
        if ($field !== $exclude && isset($q[$field]) && is_string($q[$field]) && $q[$field] !== '') {
            $where[] = "$field = :$field";
            $params[':' . $field] = $q[$field];
        }
    }

    $search = trim(crm_latin_digits(isset($q['q']) && is_string($q['q']) ? $q['q'] : ''));
    if (preg_match('/^[0-9\s+-]+$/D', $search)) {
        $search = preg_replace('/[\s-]/', '', $search);
    }
    if ($search !== '') {
        $likes = [];
        foreach (['full_name', 'phone', 'email', 'company', 'city', 'message'] as $i => $column) {
            $likes[] = "$column LIKE :q$i";
            $params[":q$i"] = '%' . $search . '%';
        }
        $where[] = '(' . implode(' OR ', $likes) . ')';
    }

    $isDate = function ($value) {
        return is_string($value) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) && strtotime($value) !== false;
    };
    if (isset($q['from']) && $isDate($q['from'])) {
        $where[] = 'created_at >= :from';
        $params[':from'] = crm_day_start_utc($q['from']);
    }
    if (isset($q['to']) && $isDate($q['to'])) {
        $where[] = 'created_at < :to';
        $params[':to'] = crm_day_start_utc($q['to'], 1);
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function crm_order_by($sort)
{
    $priority = "CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END";
    $sorts = [
        'recent' => 'last_submitted_at DESC, id DESC',
        'oldest' => 'created_at ASC, id ASC',
        'priority' => "$priority, score DESC, last_submitted_at DESC, id DESC",
        'name' => 'full_name ASC',
        'region' => 'zone, region, last_submitted_at DESC',
    ];
    return is_string($sort) && isset($sorts[$sort]) ? $sorts[$sort] : $sorts['recent'];
}

function crm_all_leads(array $q)
{
    list($where, $params) = crm_filters($q);
    $order = crm_order_by(isset($q['sort']) ? $q['sort'] : '');
    return array_map('crm_lead_row', crm_query("SELECT * FROM leads $where ORDER BY $order", $params)->fetchAll());
}

function crm_list_leads(array $q)
{
    list($where, $params) = crm_filters($q);
    $order = crm_order_by(isset($q['sort']) ? $q['sort'] : '');

    $total = (int) crm_query("SELECT COUNT(*) FROM leads $where", $params)->fetchColumn();
    $pages = max(1, (int) ceil($total / CRM_PAGE_SIZE));
    $page = min(max(1, isset($q['page']) ? (int) $q['page'] : 1), $pages);
    $offset = ($page - 1) * CRM_PAGE_SIZE;

    // LIMIT and OFFSET are integers computed above, not request text.
    $items = crm_query("SELECT * FROM leads $where ORDER BY $order LIMIT " . CRM_PAGE_SIZE . " OFFSET $offset", $params)->fetchAll();
    return ['items' => array_map('crm_lead_row', $items), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function crm_stats(array $q)
{
    $facets = [];
    foreach (CRM_FACETS as $facet) {
        list($where, $params) = crm_filters($q, $facet);
        $rows = crm_query("SELECT $facet AS value, COUNT(*) AS count FROM leads $where GROUP BY $facet ORDER BY count DESC", $params)->fetchAll();
        foreach ($rows as &$row) {
            $row['count'] = (int) $row['count'];
        }
        unset($row);
        $facets[$facet] = $rows;
    }

    $today = (new DateTime('now', new DateTimeZone(crm_config()['timezone'])))->format('Y-m-d');
    $totals = crm_query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(created_at >= :today), 0) AS today,
                COALESCE(SUM(created_at >= :week), 0) AS week,
                COALESCE(SUM(status = 'new'), 0) AS new,
                COALESCE(SUM(priority = 'high' AND status NOT IN ('won', 'lost')), 0) AS hot,
                COALESCE(SUM(status = 'won'), 0) AS won
         FROM leads",
        [':today' => crm_day_start_utc($today), ':week' => gmdate('Y-m-d H:i:s', time() - 7 * 86400)]
    )->fetch();

    return ['totals' => array_map('intval', $totals), 'facets' => $facets];
}

/** How many times this address hit a bucket inside the window. Old rows are cleared as we go. */
function crm_rate_count($bucket, $ip, $windowSeconds)
{
    $since = time() - $windowSeconds;
    crm_query('DELETE FROM rate_hits WHERE bucket = ? AND at < ?', [$bucket, $since]);
    return (int) crm_query('SELECT COUNT(*) FROM rate_hits WHERE bucket = ? AND ip = ? AND at >= ?', [$bucket, $ip, $since])->fetchColumn();
}

function crm_rate_hit($bucket, $ip)
{
    crm_query('INSERT INTO rate_hits (bucket, ip, at) VALUES (?, ?, ?)', [$bucket, $ip, time()]);
}
