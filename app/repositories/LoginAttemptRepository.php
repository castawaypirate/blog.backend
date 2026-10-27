<?php

class LoginAttemptRepository
{
    private $dbConnection;
    private $table = 'LoginAttempts';

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function recordFailure(int $userId, string $ip): bool
    {
        try {
            $query = "INSERT INTO " . $this->table . " (user_id, ip) VALUES (:userId, :ip)";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            $statement->bindParam(':ip', $ip, PDO::PARAM_STR);
            return $statement->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function countRecentFailuresForUser(int $userId, int $seconds): int
    {
        try {
            // compared in SQL so PHP's time zone doesn't matter
            $query = "SELECT COUNT(*) FROM " . $this->table . " WHERE user_id = :userId AND created_at > NOW() - INTERVAL :seconds SECOND";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            $statement->bindParam(':seconds', $seconds, PDO::PARAM_INT);
            $statement->execute();
            return (int) $statement->fetchColumn();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return 0;
        }
    }

    public function countRecentFailuresForIp(string $ip, int $seconds): int
    {
        try {
            $query = "SELECT COUNT(*) FROM " . $this->table . " WHERE ip = :ip AND created_at > NOW() - INTERVAL :seconds SECOND";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':ip', $ip, PDO::PARAM_STR);
            $statement->bindParam(':seconds', $seconds, PDO::PARAM_INT);
            $statement->execute();
            return (int) $statement->fetchColumn();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return 0;
        }
    }
}
