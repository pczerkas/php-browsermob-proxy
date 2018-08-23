<?php

require '../vendor/autoload.php';

$Client = new RapidSpike\BrowserMobProxy\Client("{$argv[1]}:{$argv[2]}");
$Client->open('trustAllServers=true&useEcc=true');
$Client->timeouts(['connection' => 30, 'request' => 30]);
