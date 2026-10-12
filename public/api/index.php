<?php
/**
 * The CRM's API. Every request lands here, either as /api/<route> (rewritten by .htaccess)
 * or as /api/index.php?r=<route>, which works even where rewriting is unavailable.
 *
 *   Public:  GET options · POST leads · GET health
 *   Admin:   POST login · POST logout · GET me · GET leads · GET stats · GET export.csv
 *            GET leads/{id} · POST leads/{id} · POST leads/{id}/notes · POST leads/{id}/delete
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$route = trim(isset($_GET['r']) && is_string($_GET['r']) ? $_GET['r'] : '', '/');

// ---------- public ----------

if ($route === 'health') {
    // Says what is wrong after an upload, without revealing anything else.
    $problem = null;
    if (!extension_loaded('pdo_sqlite')) {
        $problem = 'pdo_sqlite';
    } elseif (!is_file(dirname(__DIR__) . '/app/settings.php')) {
        $problem = 'settings';
    } else {
        // A data folder that does not exist yet is created on first use, if its parent allows it.
        $dir = crm_data_dir();
        if (!is_writable(is_dir($dir) ? $dir : dirname($dir))) {
            $problem = 'data_dir';
        }
    }
    crm_json(['ok' => $problem === null, 'problem' => $problem]);
}

if ($route === 'options') {
    crm_public_cors();
    $config = crm_config();
    $public = function (array $list) {
        return array_map(function ($item) {
            return ['id' => $item['id'], 'name' => $item['name']] + (isset($item['zone']) ? ['zone' => $item['zone']] : []);
        }, $list);
    };
    crm_json([
        'regions' => $public($config['regions']),
        'jobTitles' => $public($config['job_titles']),
        'services' => $public($config['services']),
        'budgets' => $public($config['budgets']),
        'timelines' => $public($config['timelines']),
        'priorities' => $config['priorities'],
        'statuses' => $config['statuses'],
    ]);
}

if ($route === 'leads' && ($method === 'POST' || $method === 'OPTIONS')) {
    crm_public_cors();
    // A plain HTML form may post here too; everything else sends JSON.
    $body = crm_is_json_request() ? crm_body() : $_POST;

    // Honeypot: real visitors never see or fill the "website" field.
    if (!empty($body['website'])) {
        crm_json(['ok' => true]);
    }

    // The website's server forwards every visitor's submission from one IP address, so it
    // identifies itself with a key instead of being rate limited as if it were one visitor.
    $key = crm_settings()['integration_key'];
    $trusted = $key !== '' && crm_header('X-CRM-Key') !== '' && crm_same(crm_header('X-CRM-Key'), $key);
    if (!$trusted) {
        if (crm_rate_count('lead', crm_client_ip(), 600) >= 10) {
            crm_error('محاولات كتير، جرّب تاني بعد شوية', 429);
        }
        crm_rate_hit('lead', crm_client_ip());
    }

    list($lead, $errors) = crm_validate_lead($body);
    if ($errors) {
        crm_json(['error' => 'راجع البيانات المطلوبة', 'errors' => $errors], 400);
    }

    // The same person is recognized by phone number, or by email when they gave no number.
    $existing = $lead['phone'] !== '' ? crm_find_lead_by('phone', $lead['phone']) : null;
    if (!$existing && $lead['email'] !== '') {
        $existing = crm_find_lead_by('email', $lead['email']);
    }
    if (!$existing) {
        $lead['score'] = crm_score_lead($lead);
        $lead['priority'] = crm_priority_for($lead['score']);
        crm_json(['ok' => true, 'id' => crm_insert_lead($lead)], 201);
    }

    // Same person again: update what they re-entered and log it, instead of a duplicate row.
    $merged = $existing;
    foreach (CRM_MERGEABLE_FIELDS as $field) {
        if ($lead[$field] !== '') {
            $merged[$field] = $lead[$field];
        }
    }
    $submissions = $existing['submissions'] + 1;
    $score = crm_score_lead($merged, $submissions);
    $update = ['submissions' => $submissions, 'score' => $score, 'last_submitted_at' => crm_now()];
    foreach (CRM_MERGEABLE_FIELDS as $field) {
        $update[$field] = $merged[$field];
    }
    if (!$existing['priority_locked']) {
        $update['priority'] = crm_priority_for($score);
    }

    $note = ["العميل سجّل تاني من الفورم (مرة رقم $submissions)"];
    if ($lead['phone'] !== '' && $existing['phone'] === '') {
        $update['phone'] = $lead['phone'];
    } elseif ($lead['phone'] !== '' && $lead['phone'] !== $existing['phone']) {
        $note[] = 'كتب رقم مختلف: ' . $lead['phone'];
    }
    if ($lead['message'] !== '') {
        $note[] = $lead['message'];
    }

    crm_update_lead($existing['id'], $update);
    crm_add_note($existing['id'], implode("\n", $note), 'النظام');
    crm_json(['ok' => true, 'id' => $existing['id']]);
}

// ---------- admin: login ----------

if ($route === 'login' && $method === 'POST') {
    $ip = crm_client_ip();
    if (crm_rate_count('login', $ip, 900) >= 10) {
        crm_error('محاولات كتير، جرّب تاني بعد شوية', 429);
    }
    $body = crm_is_json_request() ? crm_body() : [];
    $settings = crm_settings();
    // Evaluate both so timing doesn't reveal which one was wrong.
    $userOk = crm_same(isset($body['username']) && is_string($body['username']) ? $body['username'] : '', $settings['admin_user']);
    $passOk = crm_same(isset($body['password']) && is_string($body['password']) ? $body['password'] : '', $settings['admin_password']);
    if (!$userOk || !$passOk) {
        crm_rate_hit('login', $ip); // only wrong attempts count
        crm_error('اسم المستخدم أو كلمة السر غلط', 401);
    }
    crm_start_session($settings['admin_user']);
    crm_json(['ok' => true, 'user' => $settings['admin_user']]);
}

if ($route === 'logout' && $method === 'POST') {
    crm_send_session_cookie('', 0);
    crm_json(['ok' => true]);
}

// ---------- admin: everything below needs a login ----------

if ($route === 'me' && $method === 'GET') {
    crm_json(['user' => crm_require_login(), 'data_outside_web_folder' => crm_data_is_private()]);
}

if ($route === 'leads' && $method === 'GET') {
    crm_require_login();
    crm_json(crm_list_leads($_GET));
}

if ($route === 'stats' && $method === 'GET') {
    crm_require_login();
    crm_json(crm_stats($_GET));
}

if ($route === 'export.csv' && $method === 'GET') {
    crm_require_login();
    $config = crm_config();
    $columns = [
        'full_name' => 'الاسم', 'phone' => 'الموبايل', 'email' => 'البريد', 'company' => 'الشركة',
        'job_title' => 'المنصب', 'zone' => 'المنطقة', 'region' => 'المحافظة / الدولة', 'city' => 'المدينة',
        'service' => 'الخدمة', 'budget' => 'الميزانية', 'timeline' => 'التوقيت',
        'priority' => 'الأولوية', 'score' => 'النقاط', 'status' => 'الحالة', 'submissions' => 'مرات التسجيل',
        'source' => 'المصدر', 'message' => 'الرسالة', 'created_at' => 'تاريخ التسجيل (UTC)',
    ];
    $cell = function ($key, $value) use ($config) {
        $s = (string) $value;
        if ($key === 'priority' && isset($config['priorities'][$s])) {
            $s = $config['priorities'][$s];
        }
        if ($key === 'status' && isset($config['statuses'][$s])) {
            $s = $config['statuses'][$s];
        }
        // Keeps Excel from dropping the leading 0 (only for plain numbers, so nothing can be injected).
        if ($key === 'phone' && preg_match('/^[+]?[0-9]+$/D', $s)) {
            return '="' . $s . '"';
        }
        // Neutralize spreadsheet formulas in user-supplied text.
        if ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false) {
            $s = "'" . $s;
        }
        return '"' . str_replace('"', '""', $s) . '"';
    };

    $lines = ['"' . implode('","', $columns) . '"'];
    foreach (crm_all_leads($_GET) as $row) {
        $cells = [];
        foreach ($columns as $key => $label) {
            $cells[] = $cell($key, $row[$key]);
        }
        $lines[] = implode(',', $cells);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="crm-leads-' . gmdate('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF" . implode("\r\n", $lines); // the BOM makes Excel read Arabic correctly
    exit;
}

if (preg_match('#^leads/([1-9][0-9]{0,9})(/notes|/delete)?$#D', $route, $match)) {
    $user = crm_require_login();
    $lead = crm_get_lead((int) $match[1]);
    if (!$lead) {
        crm_error('العميل مش موجود', 404);
    }
    $action = isset($match[2]) ? $match[2] : '';
    $config = crm_config();
    $details = function ($id) {
        $lead = crm_get_lead($id);
        $lead['auto_priority'] = crm_priority_for($lead['score']);
        return ['lead' => $lead, 'notes' => crm_list_notes($id)];
    };

    if ($action === '' && $method === 'GET') {
        crm_json($details($lead['id']));
    }

    if ($action === '' && ($method === 'POST' || $method === 'PATCH')) {
        $body = crm_body();
        $update = [];
        $log = [];

        if (isset($body['status']) && $body['status'] !== $lead['status']) {
            $status = $body['status'];
            if (!is_string($status) || !isset($config['statuses'][$status])) {
                crm_error('حالة غير معروفة', 400);
            }
            $update['status'] = $status;
            $log[] = 'الحالة: من "' . $config['statuses'][$lead['status']] . '" إلى "' . $config['statuses'][$status] . '"';
        }

        if (isset($body['priority'])) {
            $priority = $body['priority'];
            if ($priority === 'auto') {
                $auto = crm_priority_for($lead['score']);
                $update['priority_locked'] = 0;
                $update['priority'] = $auto;
                $log[] = 'الأولوية رجعت تلقائي (' . $config['priorities'][$auto] . ')';
            } elseif (!is_string($priority) || !isset($config['priorities'][$priority])) {
                crm_error('أولوية غير معروفة', 400);
            } elseif ($priority !== $lead['priority'] || !$lead['priority_locked']) {
                $update['priority_locked'] = 1;
                $update['priority'] = $priority;
                $log[] = 'الأولوية: من "' . $config['priorities'][$lead['priority']] . '" إلى "' . $config['priorities'][$priority] . '"';
            }
        }

        crm_update_lead($lead['id'], $update);
        foreach ($log as $line) {
            crm_add_note($lead['id'], $line, $user);
        }
        crm_json($details($lead['id']));
    }

    if ($action === '/notes' && $method === 'POST') {
        $body = crm_body();
        $text = crm_long_text(isset($body['body']) ? $body['body'] : '', 2000);
        if ($text === '') {
            crm_error('اكتب الملاحظة', 400);
        }
        crm_add_note($lead['id'], $text, $user);
        crm_update_lead($lead['id'], []);
        crm_json(['notes' => crm_list_notes($lead['id'])], 201);
    }

    if (($action === '/delete' && $method === 'POST') || ($action === '' && $method === 'DELETE')) {
        crm_delete_lead($lead['id']);
        crm_json(['ok' => true]);
    }
}

crm_error('Not found', 404);
