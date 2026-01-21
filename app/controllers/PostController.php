<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/PostService.php';

class PostController extends BaseController
{
    private $postService;

    public function __construct($postService)
    {
        $this->postService = $postService;
    }

    public function createPost($userId, $request)
    {
        if (!isset($request['title']) || !isset($request['body'])) {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }

        return $this->postService->createPost($userId, $request);
    }

    public function getPosts($postsPerPage)
    {
        return $this->postService->getPosts($postsPerPage);
    }

    public function upvotePost($userId, $postId)
    {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        return $this->postService->upvotePost($userId, $postId);
    }

    public function downvotePost($userId, $postId)
    {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        return $this->postService->downvotePost($userId, $postId);
    }

    public function getUserVotes($userId)
    {
        return $this->postService->getUserVotes($userId);
    }

    public function getUserPosts($userId, $postsPerPage)
    {
        return $this->postService->getUserPosts($userId, $postsPerPage);
    }

    public function getPost()
    {
        if (!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID.'];
        }
        $postId = $_GET['postId'];
        return $this->postService->getPost($postId);
    }

    public function editPost($userId, $request)
    {
        if (!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID parameter.'];
        }
        if (!isset($request['title']) || !isset($request['body'])) {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }
        $postId = $_GET['postId'];
        $title = $request['title'];
        $body = $request['body'];

        return $this->postService->editPost($userId, $postId, $title, $body);
    }

    public function deletePost($userId)
    {
        if (!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID parameter.'];
        }
        $postId = $_GET['postId'];
        return $this->postService->deletePost($userId, $postId);
    }
}
?>