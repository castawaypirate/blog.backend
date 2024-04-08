<?php
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__.'/BaseController.php';

class PostController extends BaseController
{
    private $postModel;

    public function __construct($dbConnection)
    {
        $this->postModel = new Post($dbConnection);
    }

    public function createPost($userId, $request) {
        // validate required fields
        if (!isset($request['title']) || !isset($request['body'])) {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }

        error_log("test");

        // create the user in the database
        $result = $this->postModel->createPost($userId, $request['title'], $request['body']);

        return $result;
    }

    public function getPosts($postsPerPage) {
        $pageNumber = isset($_GET['pageNumber']) ? intval($_GET['pageNumber']) : 1;
        $result = $this->postModel->getPosts($postsPerPage, $pageNumber);
    
        // calculate total pages
        $totalPages = ceil($result['totalPosts'] / $postsPerPage);
    
        return [
            'posts' => $result['posts'],
            'pageNumber' => $pageNumber,
            'totalPages' => $totalPages
        ];
    }

    public function upvotePost($userId, $postId) {
        // validate the post ID
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        // call the model method to upvote the post
        $result = $this->postModel->upvotePost($userId, $postId);
        return $result;
    }
    
    public function downvotePost($userId, $postId) {
        // validate the post ID
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        // call the model method to downvote the post
        $result = $this->postModel->downvotePost($userId, $postId);
        return $result;
    }
    

    public function getPost() {
        if(!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing postId parameter.'];            
        }
        $postId = $_GET['postId'];
        $result = $this->postModel->getPost($postId);
        return $result;
    }
}
?>