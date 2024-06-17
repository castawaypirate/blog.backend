<?php
define('POSTS_PER_PAGE', 14);

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    header('Content-Type: text/plain');

    error_log('die');
    die();
}

header('Content-Type: application/json');

require_once('app/controllers/UserController.php');
require_once('app/middleware/JwtMiddleware.php');
require_once('app/controllers/PostController.php');
require_once('app/controllers/CommentController.php');
require_once('app/db/database.php');

$database = new Database();
$dbConnection = $database->getConnection();

$routes = [];
route('/', function () {
    echo 'Hello World!';
});

route('/api/users/access', function () use ($dbConnection){
    $request = validateRequest('POST', 'JSON', '', 'access');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->access($request);
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/validateUser', function () use ($dbConnection){
    $request = validateRequest('POST', 'JSON', 'Bearer', 'validateUser');
    if($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/getDashboardPosts', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', '', 'getDashboardPosts');
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
    $request = validateRequest('POST', 'JSON', 'Bearer', 'create');
    if($request){
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->createPost($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/upvote', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'upvote');
    if($request){
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postId = $request['postId'];
            $postController = new PostController($dbConnection);
            $result = $postController->upvotePost($userId, $postId);
        } 
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/downvote', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'downvote');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postId = $request['postId'];
            $postController = new PostController($dbConnection);
            $result = $postController->downvotePost($userId, $postId);
        } 
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/getUserVotes', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', 'Bearer', 'getUserVotes');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->getUserVotes($userId);
        } 
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/getUserPosts', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', 'Bearer', 'getUserPosts');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postsPerPage = POSTS_PER_PAGE;
            $postController = new PostController($dbConnection);
            $result = $postController->getUserPosts($userId, $postsPerPage);
        } 
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/getPost', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', '', 'getPost');
    if ($request) {
        $postController = new PostController($dbConnection);
        $result = $postController->getPost();
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/edit', function () use ($dbConnection) {
    $request = validateRequest('PUT', 'JSON', 'Bearer', 'edit');
    if($request){
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->editPost($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/delete', function () use ($dbConnection) {
    $request = validateRequest('DELETE', 'JSON', 'Bearer', 'delete');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->deletePost($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/create', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'create');
    if($request){
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->createComment($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/getPostComments', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', '', 'getPostComments');
    if ($request) {
        $commentController = new CommentController($dbConnection);
        $result = $commentController->getPostComments();
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/upvote', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'upvote');
    if($request){
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postId = $request['postId'];
            $commentId = $request['commentId'];
            $commentController = new CommentController($dbConnection);
            $result = $commentController->upvoteComment($userId, $postId, $commentId);
        } 
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/downvote', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'downvote');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postId = $request['postId'];
            $commentId = $request['commentId'];
            $commentController = new CommentController($dbConnection);
            $result = $commentController->downvoteComment($userId, $postId, $commentId);
        } 
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/getUserVotes', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', 'Bearer', 'getUserVotes');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->getUserVotes($userId);
        } 
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/edit', function () use ($dbConnection) {
    $request = validateRequest('PUT', 'JSON', 'Bearer', 'edit');
    if($request){
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->editComment($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/delete', function () use ($dbConnection) {
    $request = validateRequest('DELETE', 'JSON', 'Bearer', 'delete');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->deleteComment($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
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

function validateRequest($requestMethod, $contentType = '', $authorization = '', $endpoint = '') {
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
        if ($endpoint === 'validateUser') {
            return true;
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

    if ($requestMethod === 'PUT' && $contentType === 'JSON') {
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
        if ($endpoint === 'getDashboardPosts') {
            return true;
        }
        if ($endpoint === 'getUserVotes') {
            return true;
        }
        if ($endpoint === 'getPost') {
            return true;
        }
        if ($endpoint === 'getUserPosts') {
            return true;
        }
        if ($endpoint === 'getPostComments') {
            return true;
        }
    }

    if ($requestMethod === 'DELETE') {
        if ($endpoint === 'delete') {
            return true;
        }
    }

    return false;
}
?>