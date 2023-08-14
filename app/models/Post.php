<?php
class Post
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function createPost($userId, $content) {
        $sql = "INSERT INTO Posts (user_id, content) VALUES (:userId, :content)";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function getPosts() {
        $sql = "SELECT * FROM Posts";
        $stmt = $this->dbConnection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPostById($postId) {
        $sql = "SELECT * FROM Posts WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updatePost($postId, $newContent) {
        $sql = "UPDATE Posts SET content = :newContent WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':newContent', $newContent, PDO::PARAM_STR);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deletePost($postId) {
        $sql = "DELETE FROM Posts WHERE id = :postId";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>