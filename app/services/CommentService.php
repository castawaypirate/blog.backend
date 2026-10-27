<?php

require_once __DIR__ . '/../repositories/CommentRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class CommentService
{
    private $commentRepository;
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $this->commentRepository = new CommentRepository($dbConnection);
    }

    public function createComment($userId, $request)
    {
        $postId = $request['postId'];
        $body = $request['body'];

        if (empty($postId)) {
            return ['success' => false, 'message' => 'Post ID is empty.'];
        }
        if (empty($body)) {
            return ['success' => false, 'message' => 'Body is empty.'];
        }

        try {
            $this->dbConnection->beginTransaction();

            if ($this->commentRepository->hasRecentCommentByUserId($userId, 60)) {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Please wait at least 1 minute before posting a new comment.'];
            }

            $checkPostSql = "SELECT COUNT(*) FROM Posts WHERE id = :postId";
            $checkPostStmt = $this->dbConnection->prepare($checkPostSql);
            $checkPostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
            $checkPostStmt->execute();
            if ($checkPostStmt->fetchColumn() == 0) {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Something\'s wrong with the post ID.'];
            }

            $comment = new Comment($userId, $postId, $body);
            if ($this->commentRepository->create($comment)) {
                $this->dbConnection->commit();
                return ['success' => true, 'message' => 'Comment created successfully.'];
            } else {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Error creating the comment.'];
            }

        } catch (Exception $e) {
            $this->dbConnection->rollBack();
            error_log(__METHOD__ . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong. Please try again.'];
        }
    }

    public function getPostComments($postId)
    {
        if (empty($postId)) {
            return ['success' => false, 'message' => 'Post ID is empty.'];
        }
        try {
            $comments = $this->commentRepository->getPostComments($postId);
            if (empty($comments)) {
                return ['success' => true, 'message' => 'No comments found', 'comments' => []];
            }
            return ['success' => true, 'message' => 'Everything\'s good.', 'comments' => $comments];
        } catch (Exception $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong. Please try again.'];
        }
    }

    public function getUserComments($userId)
    {
        if (!isset($_GET['postId']) || !filter_var($_GET['postId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing post ID.'];
        }
        $postId = $_GET['postId'];

        try {
            $comments = $this->commentRepository->getUserComments($userId, $postId);
            $ids = array_column($comments, 'id');
            return ['success' => true, 'data' => $ids];
        } catch (Exception $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong. Please try again.'];
        }
    }

    public function editComment($userId, $request)
    {
        if (!isset($_GET['commentId']) || !filter_var($_GET['commentId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing comment ID parameter.'];
        }
        $commentId = $_GET['commentId'];
        $postId = $request['postId'];
        $body = $request['body'];

        try {
            $comment = $this->commentRepository->getByIdAndPostId($commentId, $postId);

            if (!$comment) {
                return ['success' => false, 'message' => 'Something\'s wrong with the IDs.'];
            }

            if ($comment->getUserId() !== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to edit this post.'];
            }

            if ($this->commentRepository->update($commentId, $body)) {
                $updatedComment = $this->commentRepository->getById($commentId);
                return [
                    'success' => true,
                    'message' => 'Comment updated successfully.',
                    'updated_at' => $updatedComment ? $updatedComment->getUpdatedAt() : null
                ];
            } else {
                return ['success' => false, 'message' => 'Error updating the comment.'];
            }
        } catch (Exception $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong. Please try again.'];
        }
    }

    public function deleteComment($userId)
    {
        if (!isset($_GET['commentId']) || !filter_var($_GET['commentId'], FILTER_VALIDATE_INT)) {
            return ['success' => false, 'message' => 'Invalid or missing comment ID parameter.'];
        }
        $commentId = $_GET['commentId'];

        try {
            $comment = $this->commentRepository->getById($commentId);
            if (!$comment) {
                return ['success' => false, 'message' => 'Comment does not exist.'];
            }

            if ($comment->getUserId() !== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to delete this comment.'];
            }

            if ($this->commentRepository->delete($commentId)) {
                return ['success' => true, 'message' => 'Comment deleted successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error deleting the comment.'];
            }

        } catch (Exception $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong. Please try again.'];
        }
    }

    public function upvoteComment($userId, $postId, $commentId)
    {
        return $this->handleVote($userId, $postId, $commentId, 'upvote');
    }

    public function downvoteComment($userId, $postId, $commentId)
    {
        return $this->handleVote($userId, $postId, $commentId, 'downvote');
    }

    private function handleVote($userId, $postId, $commentId, $action)
    {
        try {
            $this->dbConnection->beginTransaction();

            $comment = $this->commentRepository->getByIdAndPostId($commentId, $postId);
            if (!$comment) {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Comment ID or Post ID does not exist.'];
            }

            $currentVote = $this->commentRepository->getVote($userId, $commentId);

            $message = [];

            if ($action === 'upvote') {
                if ($currentVote === 1) {
                    if (!$this->commentRepository->deleteVote($userId, $commentId, 1)) {
                        // a parallel request already changed this vote; the catch below rolls back
                        throw new RuntimeException('Vote changed by another request.');
                    }
                    $this->commentRepository->decrementUpvotes($commentId);
                    $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the comment.'];
                } elseif ($currentVote === -1) {
                    if (!$this->commentRepository->deleteVote($userId, $commentId, -1)) {
                        // a parallel request already changed this vote; the catch below rolls back
                        throw new RuntimeException('Vote changed by another request.');
                    }
                    $this->commentRepository->decrementDownvotes($commentId);
                    $this->commentRepository->addVote($userId, $commentId, 1);
                    $this->commentRepository->incrementUpvotes($commentId);
                    $message = ['success' => true, 'action' => 'delete/upvote', 'message' => 'User\'s downvote was deleted. User successfully upvoted the comment.'];
                } else {
                    $this->commentRepository->addVote($userId, $commentId, 1);
                    $this->commentRepository->incrementUpvotes($commentId);
                    $message = ['success' => true, 'action' => 'upvote', 'message' => 'User successfully upvoted the comment.'];
                }
            } else {
                if ($currentVote === -1) {
                    if (!$this->commentRepository->deleteVote($userId, $commentId, -1)) {
                        // a parallel request already changed this vote; the catch below rolls back
                        throw new RuntimeException('Vote changed by another request.');
                    }
                    $this->commentRepository->decrementDownvotes($commentId);
                    $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the comment.'];
                } elseif ($currentVote === 1) {
                    if (!$this->commentRepository->deleteVote($userId, $commentId, 1)) {
                        // a parallel request already changed this vote; the catch below rolls back
                        throw new RuntimeException('Vote changed by another request.');
                    }
                    $this->commentRepository->decrementUpvotes($commentId);
                    $this->commentRepository->addVote($userId, $commentId, -1);
                    $this->commentRepository->incrementDownvotes($commentId);
                    $message = ['success' => true, 'action' => 'delete/downvote', 'message' => 'User\'s upvote was deleted. User successfully downvoted the comment.'];
                } else {
                    $this->commentRepository->addVote($userId, $commentId, -1);
                    $this->commentRepository->incrementDownvotes($commentId);
                    $message = ['success' => true, 'action' => 'downvote', 'message' => 'User successfully downvoted the comment.'];
                }
            }

            $this->dbConnection->commit();
            return $message;

        } catch (Exception $e) {
            if ($this->dbConnection->inTransaction()) {
                $this->dbConnection->rollBack();
            }
            error_log(__METHOD__ . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong. Please try again.'];
        }
    }

    public function getUserVotes($userId)
    {
        try {
            $votes = $this->commentRepository->getAllUserVotes($userId);
            $userVotes = [
                'upvotes' => [],
                'downvotes' => [],
            ];

            foreach ($votes as $vote) {
                if ($vote['vote_type'] == 1) {
                    $userVotes['upvotes'][] = $vote['comment_id'];
                } elseif ($vote['vote_type'] == -1) {
                    $userVotes['downvotes'][] = $vote['comment_id'];
                }
            }
            return ['success' => true, 'data' => $userVotes];
        } catch (Exception $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());
            return ['success' => false, 'message' => 'Something went wrong. Please try again.'];
        }
    }
}
?>