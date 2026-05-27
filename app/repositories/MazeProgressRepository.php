<?php

class MazeProgressRepository
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getLastProcessedId($userId)
    {
        $query = "SELECT last_processed_msg_id FROM MazeProgress WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['last_processed_msg_id'] : 0;
    }

    public function setLastProcessedId($userId, $msgId)
    {
        $query = "INSERT INTO MazeProgress (user_id, last_processed_msg_id) 
                  VALUES (:user_id, :msg_id) 
                  ON DUPLICATE KEY UPDATE last_processed_msg_id = :msg_id_update";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':msg_id', $msgId);
        $stmt->bindParam(':msg_id_update', $msgId);
        return $stmt->execute();
    }
}
