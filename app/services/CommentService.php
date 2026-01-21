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

            // Rate limiting check
            $lastComment = $this->commentRepository->getLastCommentByUserId($userId);
            if ($lastComment) {
                $lastCommentTime = new DateTime($lastComment['created_at']);
                $currentTime = new DateTime();
                $timeDiff = $currentTime->diff($lastCommentTime);
                if ($timeDiff->i < 1 && $timeDiff->h == 0 && $timeDiff->days == 0) { // Check for less than 1 minute, ensuring hours and days are also 0
                    return ['success' => false, 'timeDiff' => $timeDiff, 'message' => 'Please wait at least 1 minute before posting a new comment.'];
                }
            }

            // Check post existence - In a pure separation, we might want a PostService, 
            // but checking DB directly via a query here or injecting PostRepository would be better.
            // For now, let's assume valid post ID or add a check if strict. 
            // The original code did: "SELECT * FROM Posts WHERE id = :postId"
            // We should replicate that check. Simple raw query here or duplicate repo logic?
            // Let's do it cleanly:
            $checkPostSql = "SELECT COUNT(*) FROM Posts WHERE id = :postId";
            $checkPostStmt = $this->dbConnection->prepare($checkPostSql);
            $checkPostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
            $checkPostStmt->execute();
            if ($checkPostStmt->fetchColumn() == 0) {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Something\'s wrong with the post ID.'];
            }

            $comment = new Comment(null, $userId, $postId, $body);
            if ($this->commentRepository->create($comment)) {
                $this->dbConnection->commit();
                return ['success' => true, 'message' => 'Comment created successfully.'];
            } else {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Error creating the comment.'];
            }

        } catch (Exception $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getPostComments($postId)
    {
        if (empty($postId)) {
            return ['success' => false, 'message' => 'Post ID is empty.'];
        }
        try {
            $comments = $this->commentRepository->getPostComments($postId);
            // Convert objects to arrays for response (or keep as objects if json_encode handles it)
            // Comment implements JsonSerializable so it should be fine.
            if (empty($comments)) {
                return ['success' => true, 'message' => 'No comments found', 'comments' => []];
            }
            return ['success' => true, 'message' => 'Everything\'s good.', 'comments' => $comments];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function editComment($userId, $request)
    {
        // Extraction logic from Controller needs to be passed in or handled. 
        // Controller passed $request.
        // Controller checked for commentID param. 

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
                return ['success' => true, 'message' => 'Comment updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error updating the comment.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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

            // Check existence
            $comment = $this->commentRepository->getByIdAndPostId($commentId, $postId);
            if (!$comment) {
                throw new Exception("Comment ID or Post ID does not exist.");
            }

            $currentVote = $this->commentRepository->getVote($userId, $commentId);

            $message = [];

            if ($action === 'upvote') {
                if ($currentVote === 1) { // Already upvoted, remove it
                    $this->commentRepository->deleteVote($userId, $commentId, 1);
                    $this->commentRepository->decrementUpvotes($commentId);
                    $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the comment.'];
                } elseif ($currentVote === -1) { // Was downvoted, switch to upvote
                    $this->commentRepository->deleteVote($userId, $commentId, -1);
                    $this->commentRepository->decrementDownvotes($commentId);
                    $this->commentRepository->addVote($userId, $commentId, 1);
                    $this->commentRepository->incrementUpvotes($commentId);
                    $message = ['success' => true, 'action' => 'delete/upvote', 'message' => 'User\'s downvote was deleted. User successfully upvoted the comment.'];
                } else { // No vote, add upvote
                    $this->commentRepository->addVote($userId, $commentId, 1);
                    $this->commentRepository->incrementUpvotes($commentId);
                    $message = ['success' => true, 'action' => 'upvote', 'message' => 'User successfully upvoted the comment.'];
                }
            } else { // downvote
                if ($currentVote === -1) { // Already downvoted, remove it
                    $this->commentRepository->deleteVote($userId, $commentId, -1);
                    $this->commentRepository->decrementDownvotes($commentId);
                    $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the comment.'];
                } elseif ($currentVote === 1) { // Was upvoted, switch to downvote
                    $this->commentRepository->deleteVote($userId, $commentId, 1);
                    $this->commentRepository->decrementUpvotes($commentId);
                    $this->commentRepository->addVote($userId, $commentId, -1);
                    $this->commentRepository->incrementDownvotes($commentId);
                    $message = ['success' => true, 'action' => 'delete/downvote', 'message' => 'User\'s upvote was deleted. User successfully downvoted the comment.'];
                } else { // No vote, add downvote
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
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>