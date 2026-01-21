<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/CommentService.php';

class CommentController extends BaseController
{
    private $commentService;

    public function __construct($commentService)
    {
        $this->commentService = $commentService;
    }

    public function createComment($userId, $request)
    {
        if (!isset($request['postId'])) {
            return ['success' => false, 'message' => 'Post ID is required.'];
        }
        if (!isset($request['body'])) {
            return ['success' => false, 'message' => 'Body is required.'];
        }

        return $this->commentService->createComment($userId, $request);
    }

    public function getPostComments()
    {
        if (!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID.'];
        }
        $postId = $_GET['postId'];
        return $this->commentService->getPostComments($postId);
    }

    public function upvoteComment($userId, $postId, $commentId)
    {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        if (!is_int($commentId) || !filter_var($commentId, FILTER_VALIDATE_INT) || $commentId <= 0) {
            return ['success' => false, 'message' => 'Invalid comment ID.'];
        }
        return $this->commentService->upvoteComment($userId, $postId, $commentId);
    }

    public function downvoteComment($userId, $postId, $commentId)
    {
        if (!is_int($postId) || !filter_var($postId, FILTER_VALIDATE_INT) || $postId <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        if (!is_int($commentId) || !filter_var($commentId, FILTER_VALIDATE_INT) || $commentId <= 0) {
            return ['success' => false, 'message' => 'Invalid comment ID.'];
        }
        return $this->commentService->downvoteComment($userId, $postId, $commentId);
    }

    public function getUserVotes($userId)
    {
        return $this->commentService->getUserVotes($userId);
    }

    public function getUserComments($userId)
    {
        return $this->commentService->getUserComments($userId);
    }

    public function editComment($userId, $request)
    {
        if (!is_int($request['postId']) || !filter_var($request['postId'], FILTER_VALIDATE_INT) || $request['postId'] <= 0) {
            return ['success' => false, 'message' => 'Invalid post ID.'];
        }
        if (!isset($request['body'])) {
            return ['success' => false, 'message' => 'Body is required.'];
        }

        return $this->commentService->editComment($userId, $request);
    }

    public function deleteComment($userId)
    {
        return $this->commentService->deleteComment($userId);
    }
}
?>