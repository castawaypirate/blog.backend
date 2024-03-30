<?php
class Post
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function createPost($userId, $title, $body) {
        // validate required fields and input data
        if (empty($title) || empty($body)) {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }
        
        // insert the post into the database within a transaction
        try {
            $this->dbConnection->beginTransaction();
            $sql = "INSERT INTO Posts (user_id, title, body) VALUES (:userId, :title, :body)";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':body', $body, PDO::PARAM_STR);
            
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

    public function getPosts($postsPerPage, $pageNumber){
        try {
            $offset = ($pageNumber - 1) * $postsPerPage;
            $query = "SELECT * FROM Posts ORDER BY created_at DESC LIMIT :postsPerPage OFFSET :offset"; // Added ORDER BY statement here
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':postsPerPage', $postsPerPage, PDO::PARAM_INT);
            $statement->bindParam(':offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $posts = $statement->fetchAll(PDO::FETCH_ASSOC);
    
            // count the total number of posts
            $countQuery = "SELECT COUNT(*) as totalPosts FROM Posts";
            $countStatement = $this->dbConnection->prepare($countQuery);
            $countStatement->execute();
            $totalPosts = $countStatement->fetch(PDO::FETCH_ASSOC)['totalPosts'];
    
            return [
                'posts' => $posts,
                'totalPosts' => $totalPosts
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getPost($postId)
    {
        try {
            $query = "SELECT * FROM Posts WHERE id = :postId";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':postId', $postId, PDO::PARAM_STR);
            $statement->execute();

            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if ($result === false) {
                // postId not found in the database
                return ['success' => false, 'message' => 'postId not found'];
            } else {
                // postId found
                return $result;
            }
        } catch (PDOException $e) {
            // Log the error message and return a response indicating a database error
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

}
?>