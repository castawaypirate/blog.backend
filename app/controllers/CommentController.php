<?php
require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__.'/BaseController.php';

class CommentController extends BaseController
{
    private $commentModel;

    public function __construct($dbConnection) {
        $this->commentModel = new Comment($dbConnection);
    }

    public function createComment($userId, $request) {
        if (!isset($request['postId'])) {
            return ['success' => false, 'message' => 'Post ID is required.'];
        }
        if (!isset($request['body'])) {
            return ['success' => false, 'message' => 'Body is required.'];
        }
        
        $result = $this->commentModel->createComment($userId, $request['postId'], $request['body']);
        return $result;
    }

    public function getPostComments() {
        if(!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID.'];            
        }
        $postId = $_GET['postId'];
        $result = $this->commentModel->getPostComments($postId);
        return $result;
    }

    public function upvoteComment($userId, $postId, $commentId) {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        if (!is_int($commentId) || !filter_var($commentId, FILTER_VALIDATE_INT) || $commentId <= 0) {
            return ['success' => false, 'message' => 'Invalid comment ID.'];
        }
        $result = $this->commentModel->upvoteComment($userId, $postId, $commentId);
        return $result;
    }

    public function downvoteComment($userId, $postId, $commentId) {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        if (!is_int($commentId) || !filter_var($commentId, FILTER_VALIDATE_INT) || $commentId <= 0) {
            return ['success' => false, 'message' => 'Invalid comment ID.'];
        }
        $result = $this->commentModel->downvoteComment($userId, $postId, $commentId);
        return $result;
    }

    public function getUserVotes($userId) {
        $result = $this->commentModel->getUserVotes($userId);
        return $result;
    }

    public function getUserComments($userId) {
        if(!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID.'];            
        }
        $postId = $_GET['postId'];
        $result = $this->commentModel->getUserComments($userId, $postId);
        return $result;
    }
    
    public function editComment($userId, $request) {
        if(!isset($_GET['commentId']) || !filter_var($_GET['commentId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing comment ID parameter.'];            
        }
        if (!is_int($request['postId']) || !filter_var($request['postId'], FILTER_VALIDATE_INT) || $request['postId'] <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        if (!isset($request['body'])) {
            return ['success' => false, 'message' => 'Body is required.'];
        }
        
        $commentId = $_GET['commentId'];
        $result = $this->commentModel->editComment($userId, $commentId, $request['postId'], $request['body']);
        return $result;
    }

    public function deleteComment($userId) {
        if(!isset($_GET['commentId']) || !filter_var($_GET['commentId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing comment ID parameter.'];            
        }
        $commentId = $_GET['commentId'];
        $result = $this->commentModel->deleteComment($userId, $commentId);
        return $result;
    }
}
?>