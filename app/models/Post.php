<?php

class Post implements JsonSerializable
{
    private $id;
    private $userId;
    private $title;
    private $body;
    private $createdAt;
    private $upvotes;
    private $downvotes;
    private $username;

    public function __construct($id = null, $userId, $title, $body, $createdAt = null, $upvotes = 0, $downvotes = 0, $username = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = $title;
        $this->body = $body;
        $this->createdAt = $createdAt;
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
    public function getTitle()
    {
        return $this->title;
    }
    public function getBody()
    {
        return $this->body;
    }
    public function getCreatedAt()
    {
        return $this->createdAt;
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
            'title' => $this->title,
            'body' => $this->body,
            'created_at' => $this->createdAt,
            'upvotes' => $this->upvotes,
            'downvotes' => $this->downvotes,
            'username' => $this->username
        ];
    }
}
?>