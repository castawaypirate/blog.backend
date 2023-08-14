<?php
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__.'/BaseController.php';

class PostController extends BaseController
{
    private $postModel;

    public function __construct($dbConnection)
    {
        $this->postModel = new Post($dbConnection);
    }

    public function showPosts() {
        $posts = $this->postModel->getPosts();
        return $posts;
    }

    public function showPost($postId) {
        $post = $this->postModel->getPostById($postId);
        return $post;
    }

    public function createPost($userId, $content) {
        $result = $this->postModel->createPost($userId, $content);
        return $result;
    }

    public function updatePost($postId, $newContent) {
        $result = $this->postModel->updatePost($postId, $newContent);
        return $result;
    }

    public function deletePost($postId) {
        $result = $this->postModel->deletePost($postId);
        return $result;
    }
}
?>