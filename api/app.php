<?php
// api/app.php

// Configure error reporting for production
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

use App\Factory\ApplicationFactory;

// Create and run the Slim app
$app = ApplicationFactory::create();
$app->run();
