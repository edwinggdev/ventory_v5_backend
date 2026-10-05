<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

$basePath = '/ventory/newsys/backend/public';
$path = str_replace($basePath, '', $requestUri);
$path = strtok($path, '?');

$routes = [
    'GET' => [],
    'POST' => [],
    'PUT' => [],
    'DELETE' => []
];

function route($method, $path, $handler) {
    global $routes;
    $routes[$method][$path] = $handler;
}

require_once __DIR__ . '/../src/Routes/api.php';

$handlerFound = false;

if (isset($routes[$requestMethod][$path])) {
    call_user_func($routes[$requestMethod][$path]);
    $handlerFound = true;
} else {
    foreach ($routes[$requestMethod] as $routePath => $handler) {
        if (strpos($routePath, '{id}') === false) {
            continue;
        }

        $pattern = '#^' . str_replace('{id}', '([^/]+)', $routePath) . '$#';
        if (preg_match($pattern, $path, $matches)) {
            call_user_func($handler, $matches[1]);
            $handlerFound = true;
            break;
        }
    }
}

if (!$handlerFound) {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
