<?php
class Post
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $this->dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function createPost($userId, $title, $body) {
        // validate required fields and input data
        if (empty($title) || empty($body)) {
            return ['success' => false, 'message' => 'Title and body are required.'];
        }
        
        // insert the post into the database within a transaction
        try {
            $this->dbConnection->beginTransaction();
            $sql = "INSERT INTO Posts (user_id, title, body, upvotes, downvotes) VALUES (:userId, :title, :body, 0, 0)";
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
            $sql = "SELECT Posts.*, Users.username FROM Posts 
                    JOIN Users ON Posts.user_id = Users.id 
                    ORDER BY Posts.created_at DESC 
                    LIMIT :postsPerPage OFFSET :offset";
            $statement = $this->dbConnection->prepare($sql);
            $statement->bindParam(':postsPerPage', $postsPerPage, PDO::PARAM_INT);
            $statement->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            if ($statement->execute()) { 
                $posts = $statement->fetchAll(PDO::FETCH_ASSOC);
        
                // count the total number of posts
                $countQuery = "SELECT COUNT(*) as totalPosts FROM Posts";
                $countStatement = $this->dbConnection->prepare($countQuery);
                if ($countStatement->execute()) { 
                    $totalPosts = $countStatement->fetch(PDO::FETCH_ASSOC)['totalPosts'];
                    return [
                        'success' => true,
                        'posts' => $posts,
                        'totalPosts' => $totalPosts,
                        'message' => 'Everything\'s good.'
                    ];
                } else {
                    return ['success' => false, 'message' => 'Error fetching total count of posts.'];
                }
            } else {
                return ['success' => false, 'message' => 'Error fetching posts.'];
            }
    
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function upvotePost($userId, $postId)
    {
        $this->dbConnection->beginTransaction();

        try {
            // check if the user has already upvoted or downvoted the post
            $existingVote = $this->getExistingVote($userId, $postId);

            if ($existingVote === 1) {
                // voteType = 1 in order to delete the upvote and unvote the post
                $this->deleteVote($userId, $postId, 1);
                $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the post.'];
            } elseif ($existingVote === -1) {
                // voteType = -1 in order to delete the downvote and upvote the post
                $this->deleteVote($userId, $postId, -1);
                // insert the upvote
                $this->insertVote($userId, $postId, 1);
                $message = ['success' => true, 'action' => 'upvote', 'message' => 'User\'s downvote was deleted. User successfully upvoted the post.'];
            } else {
                $this->insertVote($userId, $postId, 1);
                $message = ['success' => true, 'action' => 'upvote', 'message' => 'User successfully upvoted the post.'];
            }

            $this->dbConnection->commit();
            return $message;
        } catch (PDOException $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function downvotePost($userId, $postId)
    {
        $this->dbConnection->beginTransaction();

        try {
            // check if the user has already upvoted or downvoted the post
            $existingVote = $this->getExistingVote($userId, $postId);

            if ($existingVote === -1) {
                // voteType = 1 in order to delete the upvote and unvote the post
                $this->deleteVote($userId, $postId, -1);
                $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the post.'];
            } elseif ($existingVote === 1) {
                // voteType = 1 in order to delete the upvote and downvote the post
                $this->deleteVote($userId, $postId, 1);
                // insert the upvote
                $this->insertVote($userId, $postId, -1);
                $message = ['success' => true, 'action' => 'downvote', 'message' => 'User\'s upvote was deleted. User successfully downvoted the post.'];
            } else {
                $this->insertVote($userId, $postId, -1);
                $message = ['success' => true, 'action' => 'downvote', 'message' => 'User successfully downvoted the post.'];
            }

            $this->dbConnection->commit();
            return $message;
        } catch (PDOException $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getUserVotes($userId)
    {
        try {
            $sql = "SELECT post_id, vote_type FROM Votes WHERE user_id = :userId";
            $stmt = $this->dbConnection->prepare($sql);

            $stmt->execute(['userId' => $userId]);

            $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // process the votes to separate upvotes and downvotes
            $userVotes = [
                'upvotes' => [],
                'downvotes' => [],
            ];

            foreach ($votes as $vote) {
                if ($vote['vote_type'] == 1) {
                    $userVotes['upvotes'][] = $vote['post_id'];
                } elseif ($vote['vote_type'] == -1) {
                    $userVotes['downvotes'][] = $vote['post_id'];
                }
            }

            return ['success' => true, 'data' => $userVotes];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getUserPosts($userId, $postsPerPage, $pageNumber)
    {
        try {
            $offset = ($pageNumber - 1) * $postsPerPage;
            $sql = "SELECT * FROM Posts 
                    WHERE user_id = :userId
                    ORDER BY created_at DESC 
                    LIMIT :postsPerPage OFFSET :offset";
            $statement = $this->dbConnection->prepare($sql);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            $statement->bindParam(':postsPerPage', $postsPerPage, PDO::PARAM_INT);
            $statement->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            if ($statement->execute()) { 
                $posts = $statement->fetchAll(PDO::FETCH_ASSOC);
        
                // count the total number of user's posts
                $countQuery = "SELECT COUNT(*) as totalUserPosts FROM Posts WHERE user_id = :userId";
                $countStatement = $this->dbConnection->prepare($countQuery);
                $countStatement->bindParam(':userId', $userId, PDO::PARAM_INT);
                if ($countStatement->execute()) { 
                    $totalUserPosts = $countStatement->fetch(PDO::FETCH_ASSOC)['totalUserPosts'];
                    // check if any posts were found
                    if (count($posts) > 0) {
                        // return success with the posts
                        return [
                            'success' => true, 
                            'posts' => $posts,
                            'totalPosts' => $totalUserPosts,
                            'message' => 'Everything\'s good.'
                        ];
                    } else {
                        // return success with a message indicating no posts were found
                        return ['success' => true, 'message' => 'No posts found for this user.'];
                    }
                   
                } else {
                    return ['success' => false, 'message' => 'Error fetching total count of user\'s posts.'];
                }
            } else {
                return ['success' => false, 'message' => 'Error fetching user\'s posts.'];
            }
    
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function getExistingVote($userId, $postId)
    {
        $checkVoteSql = "SELECT vote_type FROM Votes WHERE user_id = :userId AND post_id = :postId";
        $checkVoteStmt = $this->dbConnection->prepare($checkVoteSql);
        $checkVoteStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $checkVoteStmt->bindParam(':postId', $postId, PDO::PARAM_INT);

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

    private function deleteVote($userId, $postId, $voteType)
    {
        $deleteVoteSql = "DELETE FROM Votes WHERE user_id = :userId AND post_id = :postId AND vote_type = :voteType";
        $deleteVoteStmt = $this->dbConnection->prepare($deleteVoteSql);
        $deleteVoteStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $deleteVoteStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $deleteVoteStmt->bindParam(':voteType', $voteType, PDO::PARAM_INT); 

        if (!$deleteVoteStmt->execute()) {
            throw new Exception('Error deleting the downvote.');
        }

        if ($voteType === 1) {
            $decreaseVoteSql = "UPDATE Posts SET upvotes = upvotes - 1 WHERE id = :postId";
            $decreaseVoteStmt = $this->dbConnection->prepare($decreaseVoteSql);
            $decreaseVoteStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        } else if ($voteType === -1) {
            $decreaseVoteSql = "UPDATE Posts SET downvotes = downvotes - 1 WHERE id = :postId";
            $decreaseVoteStmt = $this->dbConnection->prepare($decreaseVoteSql);
            $decreaseVoteStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        }

        if (!$decreaseVoteStmt->execute()) {
            throw new Exception('Error updating the post downvotes.');
        }
    }

    private function insertVote($userId, $postId, $voteType)
    {
        $sql = "INSERT INTO Votes (user_id, post_id, vote_type) VALUES (:userId, :postId, :voteType)";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        $stmt->bindParam(':voteType', $voteType, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception('Error inserting the vote.');
        }

        if ($voteType === 1) {
            $updateSql = "UPDATE Posts SET upvotes = upvotes + 1 WHERE id = :postId";
            $updateStmt = $this->dbConnection->prepare($updateSql);
            $updateStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        } elseif ($voteType === -1) {
            $updateSql = "UPDATE Posts SET downvotes = downvotes + 1 WHERE id = :postId";
            $updateStmt = $this->dbConnection->prepare($updateSql);
            $updateStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        }

        if (!$updateStmt->execute()) {
            throw new Exception('Error updating the post upvotes.');
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
                return ['success' => true, 'post' => $result];
            }
        } catch (PDOException $e) {
            // log the error message and return a response indicating a database error
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function editPost($userId, $postId, $title, $body) {
        try {
            // check if the post exists and belongs to the user
            $checkPostSql = "SELECT user_id FROM Posts WHERE id = :postId";
            $checkPostStmt = $this->dbConnection->prepare($checkPostSql);
            $checkPostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
            $checkPostStmt->execute();
        
            $post = $checkPostStmt->fetch(PDO::FETCH_ASSOC);
            if ($post === false) {
                return ['success' => false, 'message' => 'Post does not exist.'];
            }

            if ($post['user_id']!== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to edit this post.'];
            }
        
            // update the post
            $updatePostSql = "UPDATE Posts SET title = :title, body = :body WHERE id = :postId";
            $updatePostStmt = $this->dbConnection->prepare($updatePostSql);
            $updatePostStmt->bindParam(':title', $title, PDO::PARAM_STR);
            $updatePostStmt->bindParam(':body', $body, PDO::PARAM_STR);
            $updatePostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
        
            if ($updatePostStmt->execute()) {
                return ['success' => true, 'message' => 'Post updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error updating the post.'];
            }
        } catch (PDOException $e) {
            // log the error message and return a response indicating a database error
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    

    public function deletePost($userId, $postId)
    {
        try {
            $checkPostSql = "SELECT user_id FROM Posts WHERE id = :postId";
            $checkPostStmt = $this->dbConnection->prepare($checkPostSql);
            $checkPostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
            $checkPostStmt->execute();
    
            $post = $checkPostStmt->fetch(PDO::FETCH_ASSOC);
            if ($post === false || $post['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'Something\'s wrong with the post ID.'];
            }

            if ($post['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to delete this post.'];
            }

    
            $deletePostSql = "DELETE FROM Posts WHERE id = :postId";
            $deletePostStmt = $this->dbConnection->prepare($deletePostSql);
            $deletePostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
    
            if ($deletePostStmt->execute()) {
                return ['success' => true, 'message' => 'Post deleted successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error deleting the post.'];
            }
        } catch (PDOException $e) {
            // Log the error message and return a response indicating a database error
            error_log('Database error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>