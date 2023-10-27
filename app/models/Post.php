<?php
class Post
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function createPost($userId, $title, $content) {
        // Validate required fields and input data
        if (empty($title) || empty($content)) {
            return ['success' => false, 'message' => 'Title and content are required.'];
        }
        
        // Insert the post into the database within a transaction
        try {
            $this->dbConnection->beginTransaction();
            $sql = "INSERT INTO Posts (user_id, title, content) VALUES (:userId, :title, :content)";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':content', $content, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $this->dbConnection->commit();
                return ['success' => true, 'message' => 'Post created successfully.'];
            } else {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Error creating the post.'];
            }
        } catch (PDOException $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>