<?php
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__.'/BaseController.php';

class PostController extends BaseController
{
    private $postModel;

    public function __construct($dbConnection) {
        $this->postModel = new Post($dbConnection);
    }

    public function createPost($userId, $request) {
        if (!isset($request['title']) || !isset($request['body'])) {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }
        
        $result = $this->postModel->createPost($userId, $request['title'], $request['body']);
        return $result;
    }

    public function getPosts($postsPerPage) {
        $pageNumber = isset($_GET['pageNumber']) ? intval($_GET['pageNumber']) : 1;
        $pageNumber = $pageNumber <= 0? 1 : $pageNumber;
        $result = $this->postModel->getPosts($postsPerPage, $pageNumber);
    
        $totalPages = ceil($result['totalPosts'] / $postsPerPage);
    
        return [
            'success' => $result['success'],
            'posts' => $result['posts'],
            'pageNumber' => $pageNumber,
            'totalPages' => $totalPages,
            'message' => $result['message']
        ];
    }

    public function upvotePost($userId, $postId) {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        $result = $this->postModel->upvotePost($userId, $postId);
        return $result;
    }
    
    public function downvotePost($userId, $postId) {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        $result = $this->postModel->downvotePost($userId, $postId);
        return $result;
    }

    public function getUserVotes($userId) {
        $result = $this->postModel->getUserVotes($userId);
        return $result;
    }

    public function getUserPosts($userId, $postsPerPage) {
        // assign the value 1 if the pageNumber is not present or zero
        $pageNumber = isset($_GET['pageNumber'])? intval($_GET['pageNumber']) : 1;
        $pageNumber = $pageNumber <= 0? 1 : $pageNumber;
        
        $result = $this->postModel->getUserPosts($userId, $postsPerPage, $pageNumber);
    
        // calculate total pages
        $totalPages = ceil($result['totalPosts'] / $postsPerPage);
    
        return [
            'success' => $result['success'],
            'posts' => $result['posts'],
            'pageNumber' => $pageNumber,
            'totalPages' => $totalPages,
            'message' => $result['message']
        ];
    }

    public function getPost() {
        if(!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID.'];            
        }
        $postId = $_GET['postId'];
        $result = $this->postModel->getPost($postId);
        return $result;
    }

    public function editPost($userId, $request) {
        if(!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID parameter.'];            
        }
        if (!isset($request['title']) || !isset($request['body'])) {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }
        $postId = $_GET['postId'];
        $result = $this->postModel->editPost($userId, $postId, $request['title'], $request['body']);
        return $result;
    }

    public function deletePost($userId) {
        if(!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID parameter.'];            
        }
        $postId = $_GET['postId'];
        $result = $this->postModel->deletePost($userId, $postId);
        return $result;
    }
}
?>