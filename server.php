<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/BookRepository.php'; 

use React\Http\HttpServer;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\Factory as MySQLFactory;

$mysqlFactory = new MySQLFactory();
$dbConnection = $mysqlFactory->createLazyConnection('root:@localhost/biblioteca');

$bookRepository = new BookRepository($dbConnection);

$routeMapGenerator = require 'routes.php';
$routeMap          = $routeMapGenerator($bookRepository); 
$staticRoutes      = $routeMap['static'];
$dynamicRoutes     = $routeMap['dynamic'];

$server = new HttpServer(function (ServerRequestInterface $request) use ($staticRoutes, $dynamicRoutes) {
    try {

    $method = $request->getMethod();
    $path   = $request->getUri()->getPath();

    $key = $method . ' ' . $path;
    if (isset($staticRoutes[$key])) {
        return $staticRoutes[$key]($request);
    }

    $dynamicResponse = matchRoute($request, $dynamicRoutes);
    if ($dynamicResponse !== null) {
        return $dynamicResponse;
    }

    $filePath = __DIR__ . '/public' . $path;

    if (file_exists($filePath) && is_file($filePath)) {
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'html' => 'text/html; charset=utf-8',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
        ];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        return new React\Http\Message\Response(
            200,
            ['Content-Type' => $mime],
            file_get_contents($filePath)
        );
    }

    return new React\Http\Message\Response(
        404,
        ['Content-Type' => 'application/json'],
        json_encode(['error' => '404 - Ruta no encontrada', 'path' => $path])
    );
    } catch (\Throwable $e) {
        echo "[ERROR] " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
        return new React\Http\Message\Response(
            500,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()])
        );
    }
});

$socket = new SocketServer('127.0.0.1:8080');
$server->listen($socket);

echo "Servidor corriendo en http://127.0.0.1:8080\n";
echo "Rutas disponibles:\n";
echo "  GET    /\n";
echo "  GET    /books\n";
echo "  GET    /api/books\n";
echo "  POST   /api/books\n";
echo "  GET    /api/books/{id}\n";
echo "  PUT    /api/books/{id}\n";
echo "  DELETE /api/books/{id}\n";