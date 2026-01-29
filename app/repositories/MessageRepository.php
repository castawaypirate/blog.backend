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

    public function getRecentConversations(int $userId): array
    {
        try {
            $query = "
                SELECT 
                    u.id as user_id,
                    u.username,
                    u.profile_pic_path,
                    m.content as last_message,
                    m.created_at as last_message_time,
                    m.is_read
                FROM Messages m
                JOIN Users u ON (
                    CASE 
                        WHEN m.sender_id = :uid1 THEN m.receiver_id 
                        ELSE m.sender_id 
                    END = u.id
                )
                WHERE (m.sender_id = :uid2 OR m.receiver_id = :uid3)
                AND m.id IN (
                    SELECT MAX(m2.id)
                    FROM Messages m2
                    WHERE (m2.sender_id = :uid4 OR m2.receiver_id = :uid5)
                    GROUP BY 
                        CASE 
                            WHEN m2.sender_id = :uid6 THEN m2.receiver_id 
                            ELSE m2.sender_id 
                        END
                )
                ORDER BY m.created_at DESC
            ";

            $stmt = $this->dbConnection->prepare($query);
            $stmt->bindParam(':uid1', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':uid2', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':uid3', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':uid4', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':uid5', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':uid6', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function getConversation(int $userId, int $otherUserId): array
    {
        try {
            $query = "
                SELECT 
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    m.content,
                    m.is_read,
                    m.created_at
                FROM " . $this->table . " m
                WHERE (m.sender_id = :uid1 AND m.receiver_id = :otherUid1)
                   OR (m.sender_id = :otherUid2 AND m.receiver_id = :uid2)
                ORDER BY m.created_at ASC
            ";

            $stmt = $this->dbConnection->prepare($query);
            $stmt->bindParam(':uid1', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':otherUid1', $otherUserId, PDO::PARAM_INT);
            $stmt->bindParam(':otherUid2', $otherUserId, PDO::PARAM_INT);
            $stmt->bindParam(':uid2', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $messages = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $messages[] = new Message(
                    $row['id'],
                    $row['sender_id'],
                    $row['receiver_id'],
                    $row['content'],
                    $row['created_at'],
                    $row['is_read']
                );
            }

            return $messages;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }
    public function hasSentMessages(int $userId): bool
    {
        try {
            $query = "SELECT 1 FROM " . $this->table . " WHERE sender_id = :userId LIMIT 1";
            $stmt = $this->dbConnection->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
?>