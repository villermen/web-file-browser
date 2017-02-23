<?php

use Villermen\WebFileBrowser\App;

require_once(__DIR__ . "/../vendor/autoload.php");

$app = new App(__DIR__ . "/../config/config.yml");
$app->run();
