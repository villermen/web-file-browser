<?php

require_once(__DIR__ . "/../vendor/autoload.php");

use Villermen\WebFileBrowser\App;

// Pass through existing files for PHP built-in web server
if (php_sapi_name() == "cli-server" && strlen($_SERVER["REQUEST_URI"]) > 1 && file_exists($_SERVER["DOCUMENT_ROOT"] . $_SERVER["REQUEST_URI"])) {
    return false;
}

$app = new App(__DIR__ . "/../config/config.yml");
$app->run();
