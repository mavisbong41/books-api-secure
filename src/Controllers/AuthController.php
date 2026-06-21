<?php

namespace App\Controllers;

use App\Auth\JwtService;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(
        private UserRepository $users,
        private JwtService $jwt,
        private AuditLogRepository $audit
    ) {}

    public function register(Request $req, Response $res): Response
    {
        $body = (array)$req->getParsedBody();
        $errors = [];

        if (empty($body['name']) || mb_strlen($body['name']) < 2) $errors['name'] = 'min 2 chars';
        if (empty($body['email']) || !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'invalid email';
        if (empty($body['password']) || mb_strlen($body['password']) < 6) $errors['password'] = 'min 6 chars';

        if ($errors) return $this->json($res, ['errors'=>$errors], 400);
        if ($this->users->emailExists($body['email'])) return $this->json($res, ['error'=>'Email already registered'], 409);

        $id = $this->users->create(
            $body['name'],
            $body['email'],
            password_hash($body['password'], PASSWORD_DEFAULT)
        );

        $this->audit->record($id, 'register', "user:$id", $_SERVER['REMOTE_ADDR'] ?? null, null);

        return $this->json($res, ['message'=>'Registered','user'=>$this->users->findById($id)], 201);
    }

    public function login(Request $req, Response $res): Response
    {
        $body = (array)$req->getParsedBody();
        $user = $this->users->findByEmail($body['email'] ?? '');

        if (!$user || !password_verify($body['password'] ?? '', $user['password_hash'])) {
            if ($user) $this->audit->record((int)$user['id'], 'login.fail', "user:{$user['id']}", $_SERVER['REMOTE_ADDR'] ?? null, null);
            return $this->json($res, ['error'=>'Invalid credentials'], 401);
        }

        $token = $this->jwt->issue((int)$user['id'], ['role'=>$user['role'],'email'=>$user['email']]);
        $this->audit->record((int)$user['id'], 'login.success', "user:{$user['id']}", $_SERVER['REMOTE_ADDR'] ?? null, null);

        return $this->json($res, [
            'token_type'=>'Bearer',
            'expires_in'=>$this->jwt->ttl(),
            'access_token'=>$token,
            'user'=>[
                'id'=>(int)$user['id'],
                'name'=>$user['name'],
                'email'=>$user['email'],
                'role'=>$user['role'],
            ],
        ]);
    }

    public function me(Request $req, Response $res): Response
    {
        $auth = (array)$req->getAttribute('auth', []);
        $user = $this->users->findById((int)($auth['sub'] ?? 0));
        return $user ? $this->json($res,$user) : $this->json($res,['error'=>'Not found'],404);
    }

    private function json(Response $res, mixed $data, int $status=200): Response
    {
        $res->getBody()->write(json_encode(
            $data,
            JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT
        ));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus($status);
    }
}