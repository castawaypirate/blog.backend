<?php

require_once __DIR__ . '/../models/Post.php';

class PostRepository
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function create(Post $post)
    {
        $sql = "INSERT INTO Posts (user_id, title, body, upvotes, downvotes) VALUES (:userId, :title, :body, 0, 0)";
        $stmt = $this->dbConnection->prepare($sql);
        $userId = $post->getUserId();
        $title = $post->getTitle();
        $body = $post->getBody();

        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':body', $body, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function getLastPostByUserId($userId)
    {
        $sql = "SELECT created_at FROM Posts WHERE user_id = :userId ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTotalPosts()
    {
        $sql = "SELECT COUNT(*) as totalPosts FROM Posts";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['totalPosts'];
    }

    public function getPosts($limit, $offset)
    {
        $sql = "SELECT Posts.*, Users.username FROM Posts 
                JOIN Users ON Posts.user_id = Users.id 
                ORDER BY Posts.created_at DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalUserPosts($userId)
    {
        $sql = "SELECT COUNT(*) as totalUserPosts FROM Posts WHERE user_id = :userId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['totalUserPosts'];
    }

    public function getUserPosts($userId, $limit, $offset)
    {
        $sql = "SELECT * FROM Posts
                WHERE user_id = :userId
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($postId)
    {
        $sql = "SELECT * FROM Posts WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return new Post(
                $row['id'],
                $row['user_id'],
                $row['title'],
                $row['body'],
                $row['created_at'],
                $row['upvotes'],
                $row['downvotes']
            );
        }
        return null;
    }

    public function update($postId, $title, $body)
    {
        $sql = "UPDATE Posts SET title = :title, body = :body, updated_at = CURRENT_TIMESTAMP WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':body', $body, PDO::PARAM_STR);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function delete($postId)
    {
        $sql = "DELETE FROM Posts WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getVote($userId, $postId)
    {
        $sql = "SELECT vote_type FROM PostVotes WHERE user_id = :userId AND post_id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int) $row['vote_type'];
        }
        return 0;
    }

    public function deleteVote($userId, $postId, $voteType)
    {
        $sql = "DELETE FROM PostVotes WHERE user_id = :userId AND post_id = :postId AND vote_type = :voteType";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->bindParam(':voteType', $voteType, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function addVote($userId, $postId, $voteType)
    {
        $sql = "INSERT INTO PostVotes (user_id, post_id, vote_type) VALUES (:userId, :postId, :voteType)";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->bindParam(':voteType', $voteType, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function incrementUpvotes($postId)
    {
        $sql = "UPDATE Posts SET upvotes = upvotes + 1 WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function decrementUpvotes($postId)
    {
        $sql = "UPDATE Posts SET upvotes = upvotes - 1 WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function incrementDownvotes($postId)
    {
        $sql = "UPDATE Posts SET downvotes = downvotes + 1 WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function decrementDownvotes($postId)
    {
        $sql = "UPDATE Posts SET downvotes = downvotes - 1 WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getAllUserVotes($userId)
    {
        $sql = "SELECT post_id, vote_type FROM PostVotes WHERE user_id = :userId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>