<?php
// Validation, phone normalization and auto-priority scoring for incoming leads.

/** Fields a repeat submission may update when the customer filled them in again. */
const CRM_MERGEABLE_FIELDS = ['full_name', 'email', 'company', 'job_title', 'zone', 'region', 'city', 'service', 'budget', 'timeline'];

/** Each option can be sent as its display name (our own form) or its id (the website). */
function crm_lists()
{
    static $lists = null;
    if ($lists === null) {
        $config = crm_config();
        $sources = [
            'job_title' => 'job_titles',
            'region' => 'regions',
            'service' => 'services',
            'budget' => 'budgets',
            'timeline' => 'timelines',
        ];
        $lists = [];
        foreach ($sources as $field => $key) {
            $lists[$field] = [];
            foreach ($config[$key] as $item) {
                $lists[$field][$item['id']] = $item;
                $lists[$field][$item['name']] = $item;
            }
        }
    }
    return $lists;
}

function crm_latin_digits($value)
{
    return strtr((string) $value, [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ]);
}

/** Egyptian numbers are stored as 01xxxxxxxxx; everything else as +<country><number>. */
function crm_normalize_phone($raw)
{
    $p = preg_replace('/[^0-9+]/', '', crm_latin_digits($raw));
    $intl = strpos($p, '+') === 0 || strpos($p, '00') === 0;
    $p = preg_replace('/^00/', '', preg_replace('/^[+]/', '', $p));
    if (preg_match('/^201[0125][0-9]{8}$/D', $p)) {
        return '0' . substr($p, 2);
    }
    if ($intl) {
        return '+' . $p;
    }
    if (preg_match('/^1[0125][0-9]{8}$/D', $p)) {
        return '0' . $p;
    }
    return $p;
}

function crm_valid_phone($p)
{
    if (strpos($p, '+') === 0) {
        return (bool) preg_match('/^[+][0-9]{8,15}$/D', $p);
    }
    if (strpos($p, '01') === 0) {
        return (bool) preg_match('/^01[0125][0-9]{8}$/D', $p);
    }
    return (bool) preg_match('/^0[0-9]{8,9}$/D', $p);
}

/** One line of text: control characters and runs of whitespace become a single space. */
function crm_text($value, $max)
{
    if (!is_scalar($value)) {
        return '';
    }
    $s = preg_replace('/[\x00-\x1f\x7f]+/u', ' ', (string) $value);
    if ($s === null) {
        return ''; // not valid UTF-8
    }
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $s)), 0, $max, 'UTF-8');
}

/** Multi-line text: line breaks are kept, other control characters are removed. */
function crm_long_text($value, $max)
{
    if (!is_scalar($value)) {
        return '';
    }
    $s = str_replace(["\r\n", "\r"], "\n", (string) $value);
    $s = preg_replace('/[\x00-\x09\x0b-\x1f\x7f]/u', '', $s);
    return $s === null ? '' : mb_substr(trim($s), 0, $max, 'UTF-8');
}

function crm_choice($value, array $list)
{
    $key = crm_text($value, 200);
    return isset($list[$key]) ? $list[$key]['name'] : '';
}

/**
 * A lead needs a name and one way to reach them. Everything else is optional, so a
 * submission forwarded from the website is never dropped over a missing dropdown.
 *
 * @return array [lead, errors]
 */
function crm_validate_lead($body)
{
    $b = is_array($body) ? $body : [];
    $get = function ($key) use ($b) {
        return isset($b[$key]) ? $b[$key] : '';
    };
    $lists = crm_lists();

    $phoneTyped = crm_text(crm_latin_digits(is_scalar($get('phone')) ? $get('phone') : ''), 40);
    $phone = crm_normalize_phone($phoneTyped);
    $phoneOk = crm_valid_phone($phone);
    $email = mb_strtolower(crm_text($get('email'), 160), 'UTF-8');
    $emailOk = (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/Du', $email);

    $lead = [
        'full_name' => crm_text($get('full_name'), 120),
        // An unrecognized number is kept as typed when there is an email to fall back on.
        'phone' => $phoneOk ? $phone : ($emailOk ? $phoneTyped : ''),
        'email' => $email,
        'company' => crm_text($get('company'), 160),
        'job_title' => crm_choice($get('job_title'), $lists['job_title']),
        'region' => crm_choice($get('region'), $lists['region']),
        'city' => crm_text($get('city'), 120),
        'service' => crm_choice($get('service'), $lists['service']),
        'budget' => crm_choice($get('budget'), $lists['budget']),
        'timeline' => crm_choice($get('timeline'), $lists['timeline']),
        'message' => crm_long_text($get('message'), 4000),
        'source' => crm_text($get('source'), 200),
    ];
    if ($lead['source'] === '') {
        $lead['source'] = 'website';
    }
    $lead['zone'] = $lead['region'] !== '' ? $lists['region'][$lead['region']]['zone'] : '';

    // A value the lists do not know (the website's lists drifted from ours, or free text)
    // is kept at the end of the message instead of being thrown away.
    $labels = ['job_title' => 'المنصب', 'region' => 'المكان', 'service' => 'الخدمة', 'budget' => 'الميزانية', 'timeline' => 'التوقيت'];
    $unknown = [];
    foreach ($labels as $field => $label) {
        $typed = crm_text($get($field), 200);
        if ($typed !== '' && $lead[$field] === '') {
            $unknown[] = $label . ': ' . $typed;
        }
    }
    if ($unknown) {
        $lead['message'] = trim($lead['message'] . "\n\n" . implode("\n", $unknown));
    }

    $errors = [];
    if (mb_strlen($lead['full_name'], 'UTF-8') < 2) {
        $errors['full_name'] = 'اكتب الاسم بالكامل';
    }
    if ($email !== '' && !$emailOk) {
        $errors['email'] = 'البريد الإلكتروني غير صحيح';
    }
    if ($phoneTyped === '' && !$emailOk) {
        $errors['phone'] = 'اكتب رقم موبايل أو بريد إلكتروني';
    } elseif ($phoneTyped !== '' && !$phoneOk && !$emailOk) {
        $errors['phone'] = 'رقم الموبايل غير صحيح';
    }

    return [$lead, $errors];
}

/** Each repeat submission adds a point (max +2): a customer who comes back is warmer. */
function crm_score_lead(array $lead, $submissions = 1)
{
    $lists = crm_lists();
    $score = 0;
    foreach (['job_title', 'service', 'budget', 'timeline'] as $field) {
        $value = isset($lead[$field]) ? $lead[$field] : '';
        if ($value !== '' && isset($lists[$field][$value])) {
            $score += (int) $lists[$field][$value]['weight'];
        }
    }
    return $score + min((int) $submissions - 1, 2);
}

function crm_priority_for($score)
{
    $t = crm_config()['priority_thresholds'];
    return $score >= $t['high'] ? 'high' : ($score >= $t['medium'] ? 'medium' : 'low');
}
