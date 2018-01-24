<?php

require_once(__DIR__ . "/../vendor/autoload.php");

$app = new Villermen\WebFileBrowser\App(__DIR__ . "/../config/config.yml");
$app->run();
