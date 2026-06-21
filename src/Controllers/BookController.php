<?php

namespace App\Controllers;

use App\Repositories\BookRepository;
use App\Repositories\AuditLogRepository;
use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Validation\Validator;

final class BookController
{
    private BookRepository $books;
    private AuditLogRepository $audit;

    public function __construct()
    {
        $this->books = new BookRepository(Database::get());
        $this->audit = new AuditLogRepository(Database::get());
    }

    public function index(Request $req, Response $res): Response
    {
        $params = $req->getQueryParams();
        $q = (string)($params['q'] ?? '');
        $limit = (int)($params['limit'] ?? 0);

        $items = $this->books->all($q,$limit);

        return $this->json($res,['count'=>count($items),'data'=>$items]);
    }

    public function show(Request $req, Response $res, array $args): Response
    {
        $book = $this->books->find((int)$args['id']);
        if (!$book) return $this->json($res,['error'=>'Book not found'],404);
        return $this->json($res,$book);
    }

    public function create(Request $req, Response $res): Response
    {
        $body = (array)($req->getParsedBody() ?? []);
        $auth = (array)$req->getAttribute('auth', []);

        $errors = (new Validator())
            ->required('title','author','year')
            ->field('title',Validator::nonEmptyString(200),'title must be 1-200 chars')
            ->field('author',Validator::nonEmptyString(150),'author must be 1-150 chars')
            ->field('year',Validator::intRange(1000,(int)date('Y')),'year must be 1000..now')
            ->field('genre',Validator::nonEmptyString(80),'genre must be ≤ 80 chars')
            ->validate($body);

        if ($errors) return $this->json($res,['errors'=>$errors],400);

        $id = $this->books->create([
            'title'=>$body['title'],
            'author'=>$body['author'],
            'year'=>(int)$body['year'],
            'genre'=>$body['genre'] ?? 'Uncategorised'
        ],(int)($auth['sub'] ?? 0));

        $this->audit->record((int)($auth['sub'] ?? 0),'book.create',"book:$id",$_SERVER['REMOTE_ADDR'] ?? null,null);

        return $this->json($res,['message'=>'Book created','id'=>$id],201);
    }

    public function update(Request $req, Response $res, array $args): Response
    {
        $id = (int)$args['id'];
        $existing = $this->books->find($id);
        if (!$existing) return $this->json($res,['error'=>'Book not found'],404);

        $auth = (array)$req->getAttribute('auth', []);
        $isOwner = (int)$existing['created_by'] === (int)($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';
        if (!$isOwner && !$isAdmin) return $this->json($res,['error'=>'Forbidden'],403);

        $body = (array)($req->getParsedBody() ?? []);
        $data = [
            'title'=>$body['title'] ?? $existing['title'],
            'author'=>$body['author'] ?? $existing['author'],
            'year'=>$body['year'] ?? $existing['year'],
            'genre'=>$body['genre'] ?? $existing['genre']
        ];

        $this->books->update($id,$data);
        $this->audit->record((int)($auth['sub'] ?? 0),'book.update',"book:$id",$_SERVER['REMOTE_ADDR'] ?? null,null);

        return $this->json($res,['message'=>'Book updated']);
    }

    public function delete(Request $req, Response $res, array $args): Response
    {
        $auth = (array)$req->getAttribute('auth', []);
        if (($auth['role'] ?? 'member')!=='admin') return $this->json($res,['error'=>'Admins only'],403);

        $id = (int)$args['id'];
        $this->books->delete($id);
        $this->audit->record((int)($auth['sub'] ?? 0),'book.delete',"book:$id",$_SERVER['REMOTE_ADDR'] ?? null,null);

        return $this->json($res,['message'=>'Book deleted']);
    }

    private function json(Response $r, $data, int $status=200): Response
    {
        $r->getBody()->write(json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
        return $r->withHeader('Content-Type','application/json; charset=utf-8')->withStatus($status);
    }
}