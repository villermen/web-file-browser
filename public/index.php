<?php

// Let dev-server handle regular files.
if (php_sapi_name() === 'cli-server') {
    if (preg_match('~^(/[^#?]+)~', $_SERVER['REQUEST_URI'], $matches)) {
        if (is_file(__DIR__ . $matches[1])) {
            return false;
        }
    }
}

require_once(__DIR__ . '/../vendor/autoload.php');

$app = new Villermen\WebFileBrowser\App(__DIR__ . '/../config.yml');
$app->run();
