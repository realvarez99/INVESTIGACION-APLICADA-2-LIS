<?php

use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

// ---------------------------------------------------------------------------
// Helpers de respuesta JSON
// ---------------------------------------------------------------------------

function jsonResponse($data, int $status = 200): Response
{
    return new Response(
        $status,
        [
            'Content-Type'                => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ],
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function errorResponse(string $message, int $status): Response
{
    return jsonResponse(['error' => $message], $status);
}

function validateBookData($data): ?string
{
    if (!is_array($data)) {
        return 'El cuerpo de la petición debe ser JSON válido';
    }

    $required = ['title', 'author', 'isbn', 'quantity'];
    foreach ($required as $field) {
        if (empty($data[$field]) && $data[$field] !== 0) {
            return "El campo '$field' es requerido";
        }
    }

    if (!is_numeric($data['quantity']) || (int) $data['quantity'] < 0) {
        return "El campo 'quantity' debe ser un número entero mayor o igual a 0";
    }

    if (isset($data['year']) && (!is_numeric($data['year']) || (int) $data['year'] < 1000)) {
        return "El campo 'year' debe ser un año válido (mayor a 1000)";
    }

    return null;
}

// ---------------------------------------------------------------------------
// Resolvedor de rutas dinámicas
// ---------------------------------------------------------------------------

function matchPattern(string $path, string $pattern): ?array
{
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';

    if (!preg_match($regex, $path, $matches)) {
        return null;
    }

    return array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
}

function matchRoute(ServerRequestInterface $request, array $routes)
{
    $method = $request->getMethod();
    $path   = $request->getUri()->getPath();

    foreach ($routes as $pattern => $handlers) {
        $params = matchPattern($path, $pattern);

        if ($params === null) {
            continue;
        }

        if (!isset($handlers[$method])) {
            return errorResponse("Método '$method' no permitido para '$path'", 405);
        }

        return $handlers[$method]($request, $params);
    }

    return null;
}

// ---------------------------------------------------------------------------
// Exportación de rutas
// ---------------------------------------------------------------------------

return function (BookRepository $repository) {

    $staticRoutes = [

        'GET /' => function (ServerRequestInterface $request): Response {
            $file = __DIR__ . '/public/index.html';
            if (!file_exists($file)) {
                return new Response(200, ['Content-Type' => 'text/html'],
                    '<html><body><h1>Biblioteca</h1><a href="/books">Ir al inventario</a></body></html>');
            }
            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], file_get_contents($file));
        },

        'GET /books' => function (ServerRequestInterface $request): Response {
            $file = __DIR__ . '/public/books.html';
            if (!file_exists($file)) {
                return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'],
                    '<html><body><h1>Inventario</h1><p>UI en construcción...</p></body></html>');
            }
            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], file_get_contents($file));
        },

        'GET /contact' => function (ServerRequestInterface $request): Response {
            $file = __DIR__ . '/public/contact.html';
            if (!file_exists($file)) return errorResponse('Archivo no encontrado', 404);
            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], file_get_contents($file));
        },

        'GET /api/books' => function (ServerRequestInterface $request) use ($repository): PromiseInterface {
            return $repository->getAll()
                ->then(function ($books) {
                    return jsonResponse([
                        'success' => true,
                        'data'    => $books,
                        'total'   => count($books),
                    ]);
                })
                ->otherwise(function (\Throwable $e) {
                    return errorResponse('Error al obtener libros: ' . $e->getMessage(), 500);
                });
        },

        'POST /api/books' => function (ServerRequestInterface $request) use ($repository) {
            $body = (string) $request->getBody();
            $data = json_decode($body, true);

            $validation = validateBookData($data);
            if ($validation !== null) {
                return errorResponse($validation, 422);
            }

            $insertData = [
                'title'    => trim($data['title']),
                'author'   => trim($data['author']),
                'isbn'     => trim($data['isbn']),
                'quantity' => (int) $data['quantity'],
                'year'     => isset($data['year']) ? (int) $data['year'] : null,
            ];

            return $repository->create($insertData)
                ->then(function ($newId) use ($insertData) {
                    $insertData['id'] = $newId;
                    return jsonResponse([
                        'success' => true,
                        'message' => 'Libro creado correctamente',
                        'data'    => $insertData,
                    ], 201);
                })
                ->otherwise(function (\Throwable $e) {
                    return errorResponse('Error al crear el libro: ' . $e->getMessage(), 500);
                });
        },
    ];

    $dynamicRoutes = [

        '/api/books/{id}' => [

            'GET' => function (ServerRequestInterface $request, array $params) use ($repository) {
                $id = (int) $params['id'];
                if ($id <= 0) return errorResponse('El ID debe ser un número entero positivo', 400);

                return $repository->getById($id)
                    ->then(function ($book) use ($id) {
                        if ($book === null) return errorResponse("No se encontró un libro con ID $id", 404);
                        return jsonResponse(['success' => true, 'data' => $book]);
                    })
                    ->otherwise(function (\Throwable $e) {
                        return errorResponse('Error al buscar el libro: ' . $e->getMessage(), 500);
                    });
            },

            'PUT' => function (ServerRequestInterface $request, array $params) use ($repository) {
                $id   = (int) $params['id'];
                $body = (string) $request->getBody();
                $data = json_decode($body, true);

                if ($id <= 0) return errorResponse('El ID debe ser un número entero positivo', 400);
                if (!is_array($data) || empty($data)) {
                    return errorResponse('El cuerpo de la petición está vacío o no es JSON válido', 400);
                }

                $allowedFields = ['title', 'author', 'isbn', 'quantity', 'year'];
                $updateData    = [];
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $updateData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                    }
                }

                if (empty($updateData)) {
                    return errorResponse('No se enviaron campos válidos para actualizar', 400);
                }

                return $repository->getById($id)
                    ->then(function ($book) use ($id, $updateData, $repository) {
                        if ($book === null) {
                            return errorResponse("No se encontró un libro con ID $id", 404);
                        }

                        $updatedBook = array_merge($book, $updateData);

                        return $repository->update($id, $updateData)
                            ->then(function () use ($updatedBook) {
                                return jsonResponse([
                                    'success' => true,
                                    'message' => 'Libro actualizado correctamente',
                                    'data'    => $updatedBook,
                                ]);
                            });
                    })
                    ->otherwise(function (\Throwable $e) {
                        return errorResponse('Error al actualizar el libro: ' . $e->getMessage(), 500);
                    });
            },

            'DELETE' => function (ServerRequestInterface $request, array $params) use ($repository) {
                $id = (int) $params['id'];
                if ($id <= 0) return errorResponse('El ID debe ser un número entero positivo', 400);

                return $repository->delete($id)
                    ->then(function ($affectedRows) use ($id) {
                        if ($affectedRows === 0) {
                            return errorResponse("No se encontró un libro con ID $id", 404);
                        }
                        return jsonResponse([
                            'success' => true,
                            'message' => "Libro con ID $id eliminado correctamente",
                        ]);
                    })
                    ->otherwise(function (\Throwable $e) {
                        return errorResponse('Error al eliminar el libro: ' . $e->getMessage(), 500);
                    });
            },
        ],
    ];

    return [
        'static'  => $staticRoutes,
        'dynamic' => $dynamicRoutes,
    ];
};