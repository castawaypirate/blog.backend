<?php

class Message implements JsonSerializable
{
    private $id;
    private $senderId;
    private $receiverId;
    private $content;
    private $createdAt;
    private $isRead;
    private $senderName;

    public function __construct($id = null, $senderId, $receiverId, $content, $createdAt = null, $isRead = 0, $senderName = null)
    {
        $this->id = $id;
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
        $this->content = $content;
        $this->createdAt = $createdAt;
        $this->isRead = $isRead;
        $this->senderName = $senderName;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getSenderId()
    {
        return $this->senderId;
    }
    public function getReceiverId()
    {
        return $this->receiverId;
    }
    public function getContent()
    {
        return $this->content;
    }
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
    public function isRead()
    {
        return $this->isRead;
    }
    public function getSenderName()
    {
        return $this->senderName;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->senderId,
            'receiver_id' => $this->receiverId,
            'content' => $this->content,
            'created_at' => $this->createdAt,
            'is_read' => $this->isRead,
            'sender_name' => $this->senderName
        ];
    }
}
?>