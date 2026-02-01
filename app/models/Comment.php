<?php

class Comment implements JsonSerializable
{
    private $id;
    private $userId;
    private $postId;
    private $body;
    private $createdAt;
    private $updatedAt;
    private $upvotes;
    private $downvotes;
    private $username; // Optional, for display purposes

    public function __construct($id = null, $userId, $postId, $body, $createdAt = null, $updatedAt = null, $upvotes = 0, $downvotes = 0, $username = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->postId = $postId;
        $this->body = $body;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->upvotes = $upvotes;
        $this->downvotes = $downvotes;
        $this->username = $username;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getUserId()
    {
        return $this->userId;
    }
    public function getPostId()
    {
        return $this->postId;
    }
    public function getBody()
    {
        return $this->body;
    }
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
    public function getUpvotes()
    {
        return $this->upvotes;
    }
    public function getDownvotes()
    {
        return $this->downvotes;
    }
    public function getUsername()
    {
        return $this->username;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'post_id' => $this->postId,
            'body' => $this->body,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'upvotes' => $this->upvotes,
            'downvotes' => $this->downvotes,
            'username' => $this->username
        ];
    }
}
?>