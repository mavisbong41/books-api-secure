<?php

use Slim\App;
use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Middleware\RateLimit;
use App\Middleware\AuthMiddleware;

return function (App $app) {

    $authCtrl = new AuthController(
        new \App\Repositories\UserRepository(\App\Database::get()),
        new \App\Auth\JwtService(),
        new \App\Repositories\AuditLogRepository(\App\Database::get())
    );

    $bookCtrl = new BookController();

    $loginMw = new RateLimit(
        (int) ($_ENV['LOGIN_RATE_LIMIT'] ?? 5),
        (int) ($_ENV['LOGIN_WINDOW_SECONDS'] ?? 60),
        'login'
    );

    $authMw = new AuthMiddleware(new \App\Auth\JwtService());

    $app->get('/', function ($req, $res) {
        $res->getBody()->write(json_encode([
            'name' => 'Books REST API',
            'version' => '4.0.0 (Ch12 hardened)',
            'status' => 'ok',
        ]));
        return $res->withHeader('Content-Type', 'application/json');
    });

    $app->options('/{routes:.+}', function ($req, $res) {
        return $res;
    });

    $app->post('/auth/register', [$authCtrl, 'register']);
    $app->post('/auth/login', [$authCtrl, 'login'])->add($loginMw);
    $app->get('/auth/me', [$authCtrl, 'me'])->add($authMw);

    // Reading books stays public (manual Test 1 hits GET /api/books with no token).
    $app->get('/api/books', [$bookCtrl, 'index']);
    $app->get('/api/books/{id}', [$bookCtrl, 'show']);

    // Writing books requires a verified identity so created_by / IDOR / admin checks work.
    $app->post('/api/books', [$bookCtrl, 'create'])->add($authMw);
    $app->put('/api/books/{id}', [$bookCtrl, 'update'])->add($authMw);
    $app->delete('/api/books/{id}', [$bookCtrl, 'delete'])->add($authMw);
};