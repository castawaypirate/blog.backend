<?php

require_once __DIR__ . '/../models/Comment.php';

class CommentRepository
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function create(Comment $comment)
    {
        $sql = "INSERT INTO Comments (user_id, post_id, body) VALUES (:userId, :postId, :body)";
        $stmt = $this->dbConnection->prepare($sql);
        $userId = $comment->getUserId();
        $postId = $comment->getPostId();
        $body = $comment->getBody();

        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->bindParam(':body', $body, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function getLastCommentByUserId($userId)
    {
        $sql = "SELECT created_at FROM Comments WHERE user_id = :userId ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPostComments($postId)
    {
        $sql = "SELECT Comments.*, Users.username 
                FROM Comments 
                JOIN Users ON Comments.user_id = Users.id 
                WHERE post_id = :postId
                ORDER BY Comments.created_at DESC";

        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $comments = [];
        foreach ($results as $row) {
            $comments[] = new Comment(
                $row['id'],
                $row['user_id'],
                $row['post_id'],
                $row['body'],
                $row['created_at'],
                $row['updated_at'],
                $row['upvotes'],
                $row['downvotes'],
                $row['username']
            );
        }
        return $comments;
    }

    public function getById($commentId)
    {
        $sql = "SELECT * FROM Comments WHERE id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return new Comment(
                $row['id'],
                $row['user_id'],
                $row['post_id'],
                $row['body'],
                $row['created_at'],
                $row['updated_at'],
                $row['upvotes'],
                $row['downvotes']
            );
        }
        return null;
    }

    public function getByIdAndPostId($commentId, $postId)
    {
        $sql = "SELECT * FROM Comments WHERE id = :commentId AND post_id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return new Comment(
                $row['id'],
                $row['user_id'],
                $row['post_id'],
                $row['body'],
                $row['created_at'],
                $row['updated_at'],
                $row['upvotes'],
                $row['downvotes']
            );
        }
        return null;
    }

    public function update($commentId, $body)
    {
        $sql = "UPDATE Comments SET body = :body, updated_at = CURRENT_TIMESTAMP WHERE id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':body', $body, PDO::PARAM_STR);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function delete($commentId)
    {
        $sql = "DELETE FROM Comments WHERE id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getUserComments($userId, $postId)
    {
        $sql = "SELECT id FROM Comments WHERE user_id = :userId AND post_id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVote($userId, $commentId)
    {
        $sql = "SELECT vote_type FROM CommentVotes WHERE user_id = :userId AND comment_id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int) $row['vote_type'];
        }
        return 0;
    }

    public function deleteVote($userId, $commentId, $voteType)
    {
        $sql = "DELETE FROM CommentVotes WHERE user_id = :userId AND comment_id = :commentId AND vote_type = :voteType";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmt->bindParam(':voteType', $voteType, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function addVote($userId, $commentId, $voteType)
    {
        $sql = "INSERT INTO CommentVotes (user_id, comment_id, vote_type) VALUES (:userId, :commentId, :voteType)";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmt->bindParam(':voteType', $voteType, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function incrementUpvotes($commentId)
    {
        $sql = "UPDATE Comments SET upvotes = upvotes + 1 WHERE id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function decrementUpvotes($commentId)
    {
        $sql = "UPDATE Comments SET upvotes = upvotes - 1 WHERE id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function incrementDownvotes($commentId)
    {
        $sql = "UPDATE Comments SET downvotes = downvotes + 1 WHERE id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function decrementDownvotes($commentId)
    {
        $sql = "UPDATE Comments SET downvotes = downvotes - 1 WHERE id = :commentId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getAllUserVotes($userId)
    {
        $sql = "SELECT comment_id, vote_type FROM CommentVotes WHERE user_id = :userId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>