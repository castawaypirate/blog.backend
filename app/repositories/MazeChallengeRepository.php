<?php

class MazeChallengeRepository
{
    private $dbConnection;
    private $table = 'MazeChallenges';

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function create($userId, $publicKey, $encryptedUsernameMsgId, $publicKeyMsgId)
    {
        try {
            $query = "INSERT INTO " . $this->table . " (user_id, public_key, encrypted_username_msg_id, public_key_msg_id) 
                      VALUES (:user_id, :public_key, :encrypted_username_msg_id, :publicKeyMsgId)";
            $stmt = $this->dbConnection->prepare($query);

            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':public_key', $publicKey);
            $stmt->bindParam(':encrypted_username_msg_id', $encryptedUsernameMsgId);
            $stmt->bindParam(':publicKeyMsgId', $publicKeyMsgId);

            if ($stmt->execute()) {
                return $this->dbConnection->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }



    public function updateStatus($id, $status, $decryptedUsername = null, $errorMessage = null): bool
    {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET status = :status, decrypted_username = :decrypted_username, error_message = :error_message 
                      WHERE id = :id";
            $stmt = $this->dbConnection->prepare($query);

            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':decrypted_username', $decryptedUsername);
            $stmt->bindParam(':error_message', $errorMessage);
            $stmt->bindParam(':id', $id);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function findByUserId($userId)
    {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->dbConnection->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function findPending()
    {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE status = 'pending'";
            $stmt = $this->dbConnection->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

}
