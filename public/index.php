<?php

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
Dotenv::createImmutable(__DIR__.'/..')->safeLoad();

$app = AppFactory::create();

$app->add(new App\Middleware\SecurityHeaders());
$app->add(new App\Middleware\JsonBodyParser());
$app->add(new App\Middleware\Cors());

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true,true,true);

(require __DIR__.'/../src/routes.php')($app);

$app->run();