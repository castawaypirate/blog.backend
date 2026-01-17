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
require_once('app/controllers/MessageController.php');
require_once('app/db/database.php');

$database = new Database();
$dbConnection = $database->getConnection();

$routes = [];
route('/', function () {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Hello World!']);
});


route('/api/users/access', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', '', 'access');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->access($request);
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/validateUser', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'validateUser');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/create', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'create');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->createPost($userId, $request);
        }
        // this is outside the if, so if $success is false, it will return the $result from token validation
        // otherwise, it will return the $result of the controller function.
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/upvote', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'upvote');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postId = $request['postId'];
            $postController = new PostController($dbConnection);
            $result = $postController->upvotePost($userId, $postId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postId = $request['postId'];
            $postController = new PostController($dbConnection);
            $result = $postController->downvotePost($userId, $postId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->getUserVotes($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postsPerPage = POSTS_PER_PAGE;
            $postController = new PostController($dbConnection);
            $result = $postController->getUserPosts($userId, $postsPerPage);
        }
        $jsonResult = json_encode($result);
        if ($jsonResult === false) {
            $jsonResult = json_encode(['success' => false, 'message' => 'Error encoding response.']);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/posts/edit', function () use ($dbConnection) {
    $request = validateRequest('PUT', 'JSON', 'Bearer', 'edit');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->editPost($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $postController = new PostController($dbConnection);
            $result = $postController->deletePost($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/create', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'create');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->createComment($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/upvote', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'upvote');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
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
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
        if ($success) {
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
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->getUserVotes($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/getUserComments', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', 'Bearer', 'getUserComments');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->getUserComments($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/comments/edit', function () use ($dbConnection) {
    $request = validateRequest('PUT', 'JSON', 'Bearer', 'edit');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->editComment($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
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
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $commentController = new CommentController($dbConnection);
            $result = $commentController->deleteComment($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/getUserData', function () use ($dbConnection) {
    $request = validateRequest('GET', 'JSON', 'Bearer', 'getUserData');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $result = $userController->getUserData($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/uploadProfilePic', function () use ($dbConnection) {
    $request = validateRequest('POST', 'FORM_DATA', 'Bearer', 'uploadProfilePic');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $result = $userController->uploadProfilePic($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/getProfilePic', function () use ($dbConnection) {
    $request = validateRequest('GET', '', 'Bearer', 'getProfilePic');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $result = $userController->getProfilePic($userId);

            if ($result['success']) {
                $data = $result['data'];
                $profilePicFullPath = $data['profile_pic_full_path'];
                $profilePicMimeType = $data['profile_pic_mime_type'];
                header('Content-Type: ' . $profilePicMimeType);
                // header('Content-Disposition: inline');
                readfile($profilePicFullPath);
            } else {
                $jsonResult = json_encode($result);
                header('Content-Type: application/json; charset=utf-8');
                echo $jsonResult;
            }
        } else {
            $jsonResult = json_encode(['success' => false, 'message' => 'Token validaton failed.']);
            header('Content-Type: application/json; charset=utf-8');
            echo $jsonResult;
        }
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/deleteProfilePic', function () use ($dbConnection) {
    $request = validateRequest('DELETE', '', 'Bearer', 'deleteProfilePic');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $result = $userController->deleteProfilePic($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/changeUsername', function () use ($dbConnection) {
    $request = validateRequest('PUT', 'JSON', 'Bearer', 'changeUsername');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $result = $userController->changeUsername($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/changePassword', function () use ($dbConnection) {
    $request = validateRequest('PUT', 'JSON', 'Bearer', 'changePassword');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $result = $userController->changePassword($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/messages/send', function () use ($dbConnection) {
    $request = validateRequest('POST', 'JSON', 'Bearer', 'sendMessage');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $messageController = new MessageController($dbConnection);
            $result = $messageController->sendMessage($userId, $request);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/api/users/delete', function () use ($dbConnection) {
    $request = validateRequest('PUT', '', 'Bearer', 'delete');
    if ($request) {
        $userController = new UserController($dbConnection);
        $result = $userController->validateUser();
        $success = $result['success'];
        if ($success) {
            $user = $result['user'];
            $userId = $user->user_id;
            $result = $userController->deleteUser($userId);
        }
        $jsonResult = json_encode($result);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    } else {
        $jsonResult = json_encode(['success' => false, 'message' => 'Request validation failed.']);
        header('Content-Type: application/json; charset=utf-8');
        echo $jsonResult;
    }
});

route('/404', function () {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Page not found.']);
});

function route(string $path, callable $callback)
{
    global $routes;
    $routes[$path] = $callback;
}

run();

function run()
{
    global $routes;
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $found = false;
    foreach ($routes as $path => $callback) {
        if ($path !== $uri) {
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

function validateRequest($requestMethod, $contentType = '', $authorization = '', $endpoint = '')
{
    if ($_SERVER['REQUEST_METHOD'] !== $requestMethod) {
        // Method Not Allowed
        http_response_code(405);
        return false;
    }

    if ($contentType === 'JSON') {
        if (!isset($_SERVER['CONTENT_TYPE'])) {
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
        if ($endpoint === 'getUserComments') {
            return true;
        }
        if ($endpoint === 'getUserData') {
            return true;
        }
        if ($endpoint === 'getProfilePic') {
            return true;
        }
    }

    if ($requestMethod === 'POST') {
        if ($endpoint === 'sendMessage') {
            return $data ?? true; // Just return true or data for validation success
        }
        if ($endpoint === 'uploadProfilePic') {
            if ($contentType === 'FORM_DATA') {
                if (!isset($_SERVER['CONTENT_TYPE'])) {
                    // Bad Request
                    http_response_code(400);
                    return false;
                } else {
                    // === 0 ensures that multipart/form-data is found at the beginning
                    if (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === 0) {
                        return true;
                    } else {
                        // Unsupported Media Type
                        http_response_code(415);
                        return false;
                    }
                }
            }
        }
    }

    if ($requestMethod === 'PUT') {
        if ($endpoint === 'delete') {
            return true;
        }
    }

    if ($requestMethod === 'DELETE') {
        if ($endpoint === 'delete') {
            return true;
        }
        if ($endpoint === 'deleteProfilePic') {
            return true;
        }
    }

    return false;
}
?>