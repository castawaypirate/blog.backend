<?php
error_log($_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');

    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');

    header('Content-Type: text/plain');

    error_log("die");
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
    echo 'Hello World';
});

route('/api/users/login', function () use ($dbConnection){
    error_log('login');
    $request = validateRequest('POST');
    $userController = new UserController($dbConnection);
    $result = $userController->login($request);
    $jsonResult = json_encode($result);
    header('Content-Type: application/json; charset=utf-8');
    echo $jsonResult;
});

route('/api/users/register', function () use ($dbConnection){
    $request = validateRequest('POST');
    $userController = new UserController($dbConnection);
    $result = $userController->register($request);
    $jsonResult = json_encode($result);
    header('Content-Type: application/json; charset=utf-8');
    echo $jsonResult;
});

route('/api/users/access', function () use ($dbConnection){
    $request = validateRequest('POST');
    $userController = new UserController($dbConnection);
    $result = $userController->access($request);
    $jsonResult = json_encode($result);
    header('Content-Type: application/json; charset=utf-8');
    echo $jsonResult;
});

route('/api/users/validateUser', function () use ($dbConnection){
    $validated = validateRequest('GET');
    if($validated) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/showPosts', function () use ($dbConnection) {
    $validated = validateRequest('GET');
    if($validated){
        $postController = new PostController($dbConnection);
        $result = $postController->showPosts();
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
    
});

route('/api/posts/showPost', function () use ($dbConnection) {
    $validated = validateRequest('GET');
    if($validated) {
        $postController = new PostController($dbConnection);
        $postId = $_GET['postId']; // Assuming you pass the postId as a query parameter
        $result = $postController->showPost($postId);
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/update', function () use ($dbConnection) {
    $request = validateRequest("POST");
    $postController = new PostController($dbConnection);
    $postId = $request['postId'];
    $newContent = $request['newContent'];
    $result = $postController->updatePost($postId, $newContent);
    $jsonResult = json_encode($result);
    header('Content-Type: application/json; charset=utf-8');
    echo $jsonResult;
});

route('/api/posts/create', function () use ($dbConnection) {
    $request = validateRequest("POST");
    $postController = new PostController($dbConnection);
    $userId = $request['userId'];
    $content = $request['content'];
    $result = $postController->createPost($userId, $content);
    $jsonResult = json_encode($result);
    header('Content-Type: application/json; charset=utf-8');
    echo $jsonResult;
});

route('/api/posts/delete', function () use ($dbConnection) {
    $request = validateRequest("DELETE");
    $postController = new PostController($dbConnection);
    $postId = $request['postId'];
    $result = $postController->deletePost($postId);
    $jsonResult = json_encode($result);
    header('Content-Type: application/json; charset=utf-8');
    echo $jsonResult;
});

route('/404', function () {
    echo "Page not found";
});

function route(string $path, callable $callback) {
    global $routes;
    $routes[$path] = $callback;
}

run();

function run() {
    global $routes;
    $uri = $_SERVER['REQUEST_URI'];
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

function validateRequest(string $requestMethod){
    
    if ($_SERVER['REQUEST_METHOD'] === $requestMethod) {
        // Check the Content-Type header for JSON
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            // Get the raw JSON data from the request body
            $jsonData = file_get_contents('php://input');

            // Attempt to decode the JSON data into a PHP associative array
            $data = json_decode($jsonData, true);
    
            // Check if the JSON data was successfully decoded
            if ($data === null) {
                // JSON parsing failed, handle the error and return a response
                http_response_code(400); // Bad Request
                // echo json_encode(array('error' => 'Invalid JSON data')).PHP_EOL;
                exit();
            }
            // Respond with a success message
            http_response_code(200); // OK
            // echo json_encode(array('message' => 'Data received successfully')).PHP_EOL;
            return $data;
        } else if ($_SERVER['REQUEST_METHOD'] === 'GET' or $_SERVER['REQUEST_METHOD'] === 'PUT' or $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            if ( isset($_SERVER['HTTP_AUTHORIZATION']) === true) {
                // Handle GET requests without JSON data (You can add further validation if needed)
                http_response_code(200); // OK
                // echo json_encode(array('message' => 'GET request successful')).PHP_EOL;
                return true;
            } else {
                http_response_code(401);
                return false;
            }            
        } else {
            http_response_code(415); // Unsupported Media Type
            // echo json_encode(array('error' => 'Unsupported Media Type: Expecting application/json')).PHP_EOL;
            return false;
        }
    } else {
        // If the request method is not POST, return an error response
        http_response_code(405); // Method Not Allowed
        // echo json_encode(array('error' => 'Method not allowed')).PHP_EOL;
        return false;
    }
}
?>
