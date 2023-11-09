<?php
define('POSTS_PER_PAGE', 2);

error_log($_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');

    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');

    header('Content-Type: text/plain');

    error_log('die');
    die();
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once('app/controllers/UserController.php');
require_once('app/middleware/JwtMiddleware.php');
require_once('app/controllers/PostController.php');
require_once('app/db/database.php');

$database = new Database();
$dbConnection = $database->getConnection();

$routes = [];
route('/', function () {
    echo 'Hello World!';
});

route('/api/users/access', function () use ($dbConnection){
    $request = validateRequest('POST', 'JSON', '');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->access($request);
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/validateUser', function () use ($dbConnection){
    $validated = validateRequest('GET', '', 'Bearer');
    if($validated) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/getPosts', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', '');
    if ($request) {
        $postsPerPage = POSTS_PER_PAGE;
        $postController = new PostController($dbConnection);
        $result = $postController->getPosts($postsPerPage);
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/create', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer');
    if($request){
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success){
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->createPost($userId, $request);
            $jsonResult = json_encode($result);
            header('Content-Type: application/json; charset=utf-8');
            echo $jsonResult;
        }
    }
});

route('/404', function () {
    echo 'Page not found';
});

function route(string $path, callable $callback) {
    global $routes;
    $routes[$path] = $callback;
}

run();

function run() {
    global $routes;
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $found = false;
    foreach ($routes as $path => $callback) {
        if ($path !== $uri){
            continue;
        }

        $found = true;
        $callback();
    }

    if (!$found) {
        $notFoundCallback = $routes['/404'];
        $notFoundCallback();
    }
}

function validateRequest($requestMethod, $contentType = '', $authorization = '') {
    if ($_SERVER['REQUEST_METHOD'] !== $requestMethod) {
        // Method Not Allowed
        http_response_code(405);
        return false;
    }

    if ($contentType === 'JSON') {
        if (!isset($_SERVER['CONTENT_TYPE'])){
            // Bad Request
            http_response_code(400);
            return false;
        } else {
            if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
                // Unsupported Media Type
                http_response_code(415);
                return false;
            }
        }
    }

    if ($authorization === 'Bearer') {
        if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
            //Unauthorized
            http_response_code(401);
            return false;
        }
    }

    if ($requestMethod === 'POST' && $contentType === 'JSON') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if ($data === null) {
            // Bad Request (Invalid JSON data)
            http_response_code(400);
            return false;
        }
        return $data;
    }

    if ($requestMethod === 'GET') {
        return true;
    }
}
?>