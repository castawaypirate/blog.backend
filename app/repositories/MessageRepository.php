<?php

require_once __DIR__ . '/../models/Message.php';

class MessageRepository
{
    private $dbConnection;
    private $table = 'Messages';

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function create(Message $message): bool
    {
        try {
            $query = "INSERT INTO " . $this->table . " (sender_id, receiver_id, content) VALUES (:sender_id, :receiver_id, :content)";
            $stmt = $this->dbConnection->prepare($query);

            $senderId = $message->getSenderId();
            $receiverId = $message->getReceiverId();
            $content = $message->getContent();

            $stmt->bindParam(':sender_id', $senderId);
            $stmt->bindParam(':receiver_id', $receiverId);
            $stmt->bindParam(':content', $content);

            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error or rethrow depending on error handling strategy. 
            // For now returning false to match previous behavior or we can throw.
            // But Service expects bool or we can let exception bubble up.
            // Let's log and return false for specific DB errors if needed, 
            // but standard PDO exception handling is usually better.
            // We will let the exception bubble up to be caught by the service or controller.
            throw $e;
        }
    }

}
?>