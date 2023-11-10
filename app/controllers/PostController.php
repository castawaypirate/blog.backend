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

    public function createPost($userId, $request) {
        // Validate required fields
        if (!isset($request['title']) || !isset($request['content'])) {
            return ['success' => false, 'message' => 'Title and content are required.'];
        }

        // Create the user in the database
        $result = $this->postModel->createPost($userId, $request['title'], $request['content']);

        return $result;
    }

    public function getPosts($postsPerPage) {
        $pageNumber = isset($_GET['pageNumber']) ? intval($_GET['pageNumber']) : 1;
        $result = $this->postModel->getPosts($postsPerPage, $pageNumber);
    
        // Calculate total pages
        $totalPages = ceil($result['totalPosts'] / $postsPerPage);
    
        return [
            'posts' => $result['posts'],
            'pageNumber' => $pageNumber,
            'totalPages' => $totalPages
        ];
    }
}
?>