<?php

require_once __DIR__ . '/../repositories/PostRepository.php';

class PostService
{
    private $postRepository;
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $this->postRepository = new PostRepository($dbConnection);
    }

    public function createPost($userId, $request)
    {
        $title = $request['title'];
        $body = $request['body'];

        if (empty($title) || empty($body)) {
            return ['success' => false, 'message' => 'Title or body are empty.'];
        }

        try {
            $this->dbConnection->beginTransaction();

            // Rate limiting check (10 minutes)
            $lastPost = $this->postRepository->getLastPostByUserId($userId);
            if ($lastPost) {
                $lastPostTime = new DateTime($lastPost['created_at']);
                $currentTime = new DateTime();
                $timeDiff = $currentTime->diff($lastPostTime);
                if ($timeDiff->i < 10 && $timeDiff->h == 0 && $timeDiff->days == 0) {
                    return ['success' => false, 'timeDiff' => $timeDiff, 'message' => 'Please wait at least 10 minutes before creating a new post.'];
                }
            }

            $post = new Post(null, $userId, $title, $body);
            if ($this->postRepository->create($post)) {
                $this->dbConnection->commit();
                return ['success' => true, 'message' => 'Post created successfully.'];
            } else {
                $this->dbConnection->rollBack();
                return ['success' => false, 'message' => 'Error creating the post.'];
            }

        } catch (Exception $e) {
            $this->dbConnection->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getPosts($postsPerPage)
    {
        $pageNumber = isset($_GET['pageNumber']) ? intval($_GET['pageNumber']) : 1;
        $pageNumber = $pageNumber <= 0 ? 1 : $pageNumber;

        try {
            $totalPosts = $this->postRepository->getTotalPosts();
            $totalPages = intval(ceil($totalPosts / $postsPerPage));

            if ($pageNumber > $totalPages) {
                $pageNumber = $totalPages > 0 ? $totalPages : 1;
            }

            $offset = ($pageNumber - 1) * $postsPerPage;
            $posts = $this->postRepository->getPosts($postsPerPage, $offset);

            $data = [];
            if (!empty($posts)) {
                $data = $posts;
                $message = 'Everything\'s good.';
            } else {
                $message = 'No posts found.';
            }

            return [
                'success' => true,
                'posts' => $data,
                'pageNumber' => $pageNumber,
                'totalPages' => $totalPages,
                'message' => $message
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getUserPosts($userId, $postsPerPage)
    {
        $pageNumber = isset($_GET['pageNumber']) ? intval($_GET['pageNumber']) : 1;
        $pageNumber = $pageNumber <= 0 ? 1 : $pageNumber;

        try {
            $totalPosts = $this->postRepository->getTotalUserPosts($userId);
            $totalPages = intval(ceil($totalPosts / $postsPerPage));

            if ($pageNumber > $totalPages) {
                $pageNumber = $totalPages > 0 ? $totalPages : 1;
            }

            $offset = ($pageNumber - 1) * $postsPerPage;
            $posts = $this->postRepository->getUserPosts($userId, $postsPerPage, $offset);

            $data = [];
            if (!empty($posts)) {
                $data = $posts;
                $message = 'Everything\'s good.';
            } else {
                $message = 'No posts found for this user.';
            }

            return [
                'success' => true,
                'posts' => $data,
                'pageNumber' => $pageNumber,
                'totalPages' => $totalPages,
                'message' => $message
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getPost($postId)
    {
        if (empty($postId)) {
            return ['success' => false, 'message' => 'Post ID is empty.'];
        }
        try {
            $post = $this->postRepository->getById($postId);
            if ($post) {
                // Return array representation of the post DTO
                return ['success' => true, 'post' => $post->jsonSerialize()];
            } else {
                return ['success' => false, 'message' => 'postId not found'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function editPost($userId, $postId, $title, $body)
    {
        try {
            $post = $this->postRepository->getById($postId);

            if (!$post) {
                return ['success' => false, 'message' => 'Post does not exist.'];
            }

            if ($post->getUserId() !== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to edit this post.'];
            }

            if ($this->postRepository->update($postId, $title, $body)) {
                return ['success' => true, 'message' => 'Post updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error updating the post.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function deletePost($userId, $postId)
    {
        try {
            $post = $this->postRepository->getById($postId);
            if (!$post) {
                return ['success' => false, 'message' => 'Post does not exist.'];
            }

            if ($post->getUserId() !== $userId) {
                return ['success' => false, 'message' => 'You do not have permission to delete this post.'];
            }

            if ($this->postRepository->delete($postId)) {
                return ['success' => true, 'message' => 'Post deleted successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error deleting the post.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function upvotePost($userId, $postId)
    {
        return $this->handleVote($userId, $postId, 'upvote');
    }

    public function downvotePost($userId, $postId)
    {
        return $this->handleVote($userId, $postId, 'downvote');
    }

    private function handleVote($userId, $postId, $action)
    {
        try {
            $this->dbConnection->beginTransaction();

            // Should check existence of post first? 
            // The logic in getExistingVote (in repo) or getById can verify post existence.
            $post = $this->postRepository->getById($postId);
            if (!$post) {
                throw new Exception("Post ID does not exist.");
            }

            $currentVote = $this->postRepository->getVote($userId, $postId);
            $message = [];

            if ($action === 'upvote') {
                if ($currentVote === 1) {
                    $this->postRepository->deleteVote($userId, $postId, 1);
                    $this->postRepository->decrementUpvotes($postId);
                    $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the post.'];
                } elseif ($currentVote === -1) {
                    $this->postRepository->deleteVote($userId, $postId, -1);
                    $this->postRepository->decrementDownvotes($postId);
                    $this->postRepository->addVote($userId, $postId, 1);
                    $this->postRepository->incrementUpvotes($postId);
                    $message = ['success' => true, 'action' => 'delete/upvote', 'message' => 'User\'s downvote was deleted. User successfully upvoted the post.'];
                } else {
                    $this->postRepository->addVote($userId, $postId, 1);
                    $this->postRepository->incrementUpvotes($postId);
                    $message = ['success' => true, 'action' => 'upvote', 'message' => 'User successfully upvoted the post.'];
                }
            } else { // downvote
                if ($currentVote === -1) {
                    $this->postRepository->deleteVote($userId, $postId, -1);
                    $this->postRepository->decrementDownvotes($postId);
                    $message = ['success' => true, 'action' => 'unvote', 'message' => 'User successfully unvoted the post.'];
                } elseif ($currentVote === 1) {
                    $this->postRepository->deleteVote($userId, $postId, 1);
                    $this->postRepository->decrementUpvotes($postId);
                    $this->postRepository->addVote($userId, $postId, -1);
                    $this->postRepository->incrementDownvotes($postId);
                    $message = ['success' => true, 'action' => 'delete/downvote', 'message' => 'User\'s upvote was deleted. User successfully downvoted the post.'];
                } else {
                    $this->postRepository->addVote($userId, $postId, -1);
                    $this->postRepository->incrementDownvotes($postId);
                    $message = ['success' => true, 'action' => 'downvote', 'message' => 'User successfully downvoted the post.'];
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
            $votes = $this->postRepository->getAllUserVotes($userId);
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
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>