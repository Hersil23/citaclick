<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/plan.php';

handleCors();

header('Content-Type: application/json; charset=utf-8');

function sendJson(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getRouteParam(string $path, string $pattern): ?array
{
    $patternRegex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $patternRegex = '#^' . $patternRegex . '$#';

    if (preg_match($patternRegex, $path, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^/api#', '', $uri);
$uri = rtrim($uri, '/') ?: '/';

$routes = [
    'POST /auth/login'          => ['AuthController', 'login'],
    'POST /auth/register'       => ['AuthController', 'register'],
    'POST /auth/google'         => ['AuthController', 'google'],
    'POST /auth/phone'          => ['AuthController', 'phone'],
    'POST /auth/phone/verify'   => ['AuthController', 'phoneVerify'],
    'POST /auth/refresh'        => ['AuthController', 'refresh'],
    'DELETE /auth/logout'        => ['AuthController', 'logout'],

    'GET /business/profile'     => ['BusinessController', 'show', true],
    'PUT /business/profile'     => ['BusinessController', 'update', true],

    'GET /appointments'         => ['AppointmentController', 'index', true],
    'POST /appointments'        => ['AppointmentController', 'store', true],
    'GET /appointments/slots'   => ['AppointmentController', 'availableSlots', true],
    'GET /appointments/{id}'    => ['AppointmentController', 'show', true],
    'PUT /appointments/{id}'    => ['AppointmentController', 'update', true],
    'DELETE /appointments/{id}' => ['AppointmentController', 'destroy', true],

    'GET /clients'              => ['ClientController', 'index', true],
    'POST /clients'             => ['ClientController', 'store', true],
    'GET /clients/{id}'         => ['ClientController', 'show', true],
    'PUT /clients/{id}'         => ['ClientController', 'update', true],
    'DELETE /clients/{id}'      => ['ClientController', 'destroy', true],

    'GET /services'             => ['ServiceController', 'index', true],
    'POST /services'            => ['ServiceController', 'store', true],
    'GET /services/{id}'        => ['ServiceController', 'show', true],
    'PUT /services/{id}'        => ['ServiceController', 'update', true],
    'DELETE /services/{id}'     => ['ServiceController', 'destroy', true],

    'GET /service-categories'         => ['ServiceController', 'categories', true],
    'POST /service-categories'        => ['ServiceController', 'storeCategory', true],

    'GET /providers'            => ['ProviderController', 'index', true],
    'POST /providers'           => ['ProviderController', 'store', true],
    'GET /providers/{id}'       => ['ProviderController', 'show', true],
    'PUT /providers/{id}'       => ['ProviderController', 'update', true],
    'GET /providers/{id}/schedule'  => ['ProviderController', 'schedule', true],
    'PUT /providers/{id}/schedule'  => ['ProviderController', 'updateSchedule', true],
    'POST /providers/{id}/block'    => ['ProviderController', 'block', true],
    'GET /providers/{id}/dashboard' => ['ProviderController', 'dashboard', true],

    'GET /catalog/{slug}'       => ['CatalogController', 'show', false],
    'POST /catalog/{slug}/book' => ['CatalogController', 'book', false],

    'GET /reports'              => ['ReportController', 'index', true],

    'GET /notifications'        => ['NotificationController', 'index', true],

    'GET /admin/stats'          => ['AdminController', 'stats', true, 'superadmin'],
    'GET /admin/businesses'     => ['AdminController', 'businesses', true, 'superadmin'],
    'PUT /admin/businesses/{id}'   => ['AdminController', 'updateBusiness', true, 'superadmin'],
    'POST /admin/businesses/{id}/suspend'  => ['AdminController', 'suspend', true, 'superadmin'],
    'POST /admin/businesses/{id}/activate' => ['AdminController', 'activate', true, 'superadmin'],
];

$matched = false;

foreach ($routes as $route => $config) {
    [$routeMethod, $routePattern] = explode(' ', $route, 2);

    if ($method !== $routeMethod) {
        continue;
    }

    $params = getRouteParam($uri, $routePattern);
    if ($params === null && $uri !== $routePattern) {
        continue;
    }

    $controllerName = $config[0];
    $action = $config[1];
    $requiresAuth = $config[2] ?? false;
    $requiredRole = $config[3] ?? null;

    $user = null;
    if ($requiresAuth) {
        $user = authenticateRequest();
        if (!$user) exit;

        if ($requiredRole && !requireRole($user, [$requiredRole])) {
            exit;
        }
    }

    $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';
    if (!file_exists($controllerFile)) {
        sendJson(500, ['success' => false, 'message' => 'Controller no encontrado']);
    }

    require_once $controllerFile;
    $controller = new $controllerName();

    $args = [
        'user' => $user,
        'params' => $params ?? [],
        'body' => getJsonBody(),
        'query' => $_GET,
    ];

    $controller->$action($args);
    $matched = true;
    break;
}

if (!$matched) {
    sendJson(404, ['success' => false, 'message' => 'Ruta no encontrada']);
}
