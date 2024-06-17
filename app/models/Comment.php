<?php

class Comment
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $this->dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function createComment($userId, $postId, $body) {
        // validate required fields and input data
        if (empty($postId)) {
            return ['success' => false, 'message' => 'Post ID is empty.'];
        }
        if (empty($body)) {
            return ['success' => false, 'message' => 'Body is empty.'];
        }
        
        // insert the post into the database within a transaction
        try {
            $this->dbConnection->beginTransaction();
            $checkPostSql = "SELECT * FROM Posts WHERE id = :postId";
            $checkPostStmt = $this->dbConnection->prepare($checkPostSql);
            $checkPostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
            $checkPostStmt->execute();
    
            $post = $checkPostStmt->fetch(PDO::FETCH_ASSOC);
            if ($post === false) {
                return ['success' => false, 'message' => 'Something\'s wrong with the post ID.'];
            }

            $sql = "INSERT INTO Comments (user_id, post_id, body) VALUES (:userId, :postId, :body)";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':postId', $postId, PDO::PARAM_STR);
            $stmt->bindParam(':body', $body, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $this->dbConnection->commit();
                return ['success' => true, 'message' => 'Comment created successfully.'];
            } else {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Error creating the comment.'];
            }
        } catch (PDOException $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getPostComments($postId) {
        // validate postId
        if (empty($postId)) {
            return ['success' => false, 'message' => 'Post ID is empty.'];
        }
        
        // insert the post into the database within a transaction
        try {
            $sql = "SELECT Comments.*, Users.username 
                    FROM Comments 
                    JOIN Users ON Comments.user_id = Users.id 
                    WHERE post_id = :postId
                    ORDER BY Comments.created_at DESC";

            $statement = $this->dbConnection->prepare($sql);
            $statement->bindParam(':postId', $postId, PDO::PARAM_INT);

            
            if ($statement->execute()) { 
                $results = $statement->fetchAll(PDO::FETCH_ASSOC);
                if (!$results) {
                    return ['success' => true, 'message' => 'No comments found', 'comments' => $results];
                } else {
                    return ['success' => true, 'message' => 'Everything\'s good.', 'comments' => $results];
                }

            } else {
                return ['success' => false, 'message' => 'Error fetching post comments.'];
            }
    
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function upvoteComment($userId, $postId, $commentId) {
        $this->dbConnection->beginTransaction();

        try {
            // check if the user has already upvoted or downvoted the post
            $existingVote = $this->getExistingVote($userId, $postId, $commentId);

            if ($existingVote === 1) {
                // voteType = 1 in order to delete the upvote and unvote the comment
                $this->deleteVote($userId, $commentId, 1);
                $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the comment.'];
            } elseif ($existingVote === -1) {
                // voteType = -1 in order to delete the downvote and upvote the comment
                $this->deleteVote($userId, $commentId, -1);
                // insert the upvote
                $this->insertVote($userId, $commentId, 1);
                $message = ['success' => true, 'action' => 'upvote', 'message' => 'User\'s downvote was deleted. User successfully upvoted the comment.'];
            } else {
                $this->insertVote($userId, $commentId, 1);
                $message = ['success' => true, 'action' => 'upvote', 'message' => 'User successfully upvoted the comment.'];
            }

            $this->dbConnection->commit();
            return $message;
        } catch (PDOException $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function downvoteComment($userId, $postId, $commentId) {
        $this->dbConnection->beginTransaction();

        try {
            // check if the user has already upvoted or downvoted the comment
            $existingVote = $this->getExistingVote($userId, $postId, $commentId);

            if ($existingVote === -1) {
                // voteType = 1 in order to delete the upvote and unvote the comment
                $this->deleteVote($userId, $commentId, -1);
                $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the comment.'];
            } elseif ($existingVote === 1) {
                // voteType = 1 in order to delete the upvote and downvote the comment
                $this->deleteVote($userId, $commentId, 1);
                // insert the upvote
                $this->insertVote($userId, $commentId, -1);
                $message = ['success' => true, 'action' => 'downvote', 'message' => 'User\'s upvote was deleted. User successfully downvoted the comment.'];
            } else {
                $this->insertVote($userId, $commentId, -1);
                $message = ['success' => true, 'action' => 'downvote', 'message' => 'User successfully downvoted the comment.'];
            }

            $this->dbConnection->commit();
            return $message;
        } catch (PDOException $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function getExistingVote($userId, $postId, $commentId) {
        // first, check if the post_id exists in the Posts table
        $checkCommentExistsSql = "SELECT COUNT(*) FROM Comments WHERE id = :commentId AND post_id = :postId";
        $checkCommentExistsStmt = $this->dbConnection->prepare($checkCommentExistsSql);
        $checkCommentExistsStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $checkCommentExistsStmt->bindParam(':postId', $postId, PDO::PARAM_INT);

        if (!$checkCommentExistsStmt->execute()) {
            throw new Exception('Error checking for existing post.');
        }

        $commentCount = $checkCommentExistsStmt->fetchColumn();

        // if the post does not exist, return early
        if ($commentCount == 0) {
            throw new Exception("Comment ID or Post ID does not exist.");
        }

        // then, proceed to check for the vote
        $checkVoteSql = "SELECT vote_type FROM CommentVotes WHERE user_id = :userId AND comment_id = :commentId";
        $checkVoteStmt = $this->dbConnection->prepare($checkVoteSql);
        $checkVoteStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $checkVoteStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);

        if (!$checkVoteStmt->execute()) {
            throw new Exception('Error checking for existing upvote.');
        }

        $voteType = $checkVoteStmt->fetchColumn();

        if ($checkVoteStmt->rowCount() > 0 && $voteType === 1) {
            return 1;
        } elseif ($checkVoteStmt->rowCount() > 0 && $voteType === -1) {
            return -1;
        }

        return 0;
    }


    private function deleteVote($userId, $commentId, $voteType) {
        $deleteVoteSql = "DELETE FROM CommentVotes WHERE user_id = :userId AND comment_id = :commentId AND vote_type = :voteType";
        $deleteVoteStmt = $this->dbConnection->prepare($deleteVoteSql);
        $deleteVoteStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $deleteVoteStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $deleteVoteStmt->bindParam(':voteType', $voteType, PDO::PARAM_INT); 

        if (!$deleteVoteStmt->execute()) {
            throw new Exception('Error deleting the downvote.');
        }

        if ($voteType === 1) {
            $decreaseVoteSql = "UPDATE Comments SET upvotes = upvotes - 1 WHERE id = :commentId";
            $decreaseVoteStmt = $this->dbConnection->prepare($decreaseVoteSql);
            $decreaseVoteStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        } else if ($voteType === -1) {
            $decreaseVoteSql = "UPDATE Comments SET downvotes = downvotes - 1 WHERE id = :commentId";
            $decreaseVoteStmt = $this->dbConnection->prepare($decreaseVoteSql);
            $decreaseVoteStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        }

        if (!$decreaseVoteStmt->execute()) {
            throw new Exception('Error updating the comment downvotes.');
        }
    }

    private function insertVote($userId, $commentId, $voteType) {
        $sql = "INSERT INTO CommentVotes (user_id, comment_id, vote_type) VALUES (:userId, :commentId, :voteType)";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmt->bindParam(':voteType', $voteType, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception('Error inserting the vote.');
        }

        if ($voteType === 1) {
            $updateSql = "UPDATE Comments SET upvotes = upvotes + 1 WHERE id = :commentId";
            $updateStmt = $this->dbConnection->prepare($updateSql);
            $updateStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        } elseif ($voteType === -1) {
            $updateSql = "UPDATE Comments SET downvotes = downvotes + 1 WHERE id = :commentId";
            $updateStmt = $this->dbConnection->prepare($updateSql);
            $updateStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        }

        if (!$updateStmt->execute()) {
            throw new Exception('Error updating the comments upvotes.');
        }
    }

    public function getUserVotes($userId) {
        try {
            $sql = "SELECT comment_id, vote_type FROM CommentVotes WHERE user_id = :userId";
            $stmt = $this->dbConnection->prepare($sql);

            $stmt->execute(['userId' => $userId]);

            $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // process the comment votes to separate upvotes and downvotes
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
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function editComment($userId, $commentId, $postId, $body) {
        try {
            // check if the comment exists and belongs to the user
            $checkCommentSql = "SELECT user_id FROM Comments WHERE id = :commentId AND post_id = :postId";
            $checkCommentStmt = $this->dbConnection->prepare($checkCommentSql);
            $checkCommentStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
            $checkCommentStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
            $checkCommentStmt->execute();
        
            $comment = $checkCommentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$comment) {
                return ['success' => false, 'message' => 'Something\'s wrong with the IDs.'];
            }

            if ($comment['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to edit this post.'];
            }
        
            // update the comment
            $updateCommentSql = "UPDATE Comments SET body = :body, updated_at = CURRENT_TIMESTAMP WHERE id = :commentId AND post_id = :postId";
            $updateCommentStmt = $this->dbConnection->prepare($updateCommentSql);
            $updateCommentStmt->bindParam(':body', $body, PDO::PARAM_STR);
            $updateCommentStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
            $updateCommentStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        
            if ($updateCommentStmt->execute()) {
                return ['success' => true, 'message' => 'Comment updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error updating the comment.'];
            }
        } catch (PDOException $e) {
            // log the error message and return a response indicating a database error
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    

    public function deleteComment($userId, $commentId) {
        try {
            $checkCommentSql = "SELECT user_id FROM Comments WHERE id = :commentId";
            $checkCommentStmt = $this->dbConnection->prepare($checkCommentSql);
            $checkCommentStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
            $checkCommentStmt->execute();
    
            $comment = $checkCommentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$comment) {
                return ['success' => false, 'message' => 'Comment does not exist.'];
            }

            if ($comment['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to delete this comment.'];
            }
    
            $deleteCommentSql = "DELETE FROM Comments WHERE id = :commentId";
            $deleteCommentStmt = $this->dbConnection->prepare($deleteCommentSql);
            $deleteCommentStmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
    
            if ($deleteCommentStmt->execute()) {
                return ['success' => true, 'message' => 'Comment deleted successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error deleting the comment.'];
            }
        } catch (PDOException $e) {
            // log the error message and return a response indicating a database error
            error_log('Database error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>