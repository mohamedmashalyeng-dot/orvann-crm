<?php
// Local preview only (the real server uses the .htaccess files instead):
//   php -S localhost:3000 -t public dev-router.php

// Local leads go to local-data/, outside public/, so they can never be uploaded by accident.
if (!getenv('CRM_DATA_DIR')) {
    putenv('CRM_DATA_DIR=' . __DIR__ . '/local-data');
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Same protection the .htaccess files give on the real server.
if (preg_match('#^/(app|data)(/|$)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

// /api/leads -> /api/index.php?r=leads
if (preg_match('#^/api/(.+)$#', $path, $match) && $match[1] !== 'index.php') {
    $_GET['r'] = $match[1];
    require __DIR__ . '/public/api/index.php';
    return true;
}

return false; // pages, scripts, styles, and /api/index.php itself
