<?php

require_once __DIR__ . '/../models/User.php';

class UserRepository
{
    private $dbConnection;
    private $table = 'Users';

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function findByUsername(string $username): ?User
    {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE username = :username";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':username', $username, PDO::PARAM_STR);
            $statement->execute();
            $data = $statement->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                return $this->mapRowToUser($data);
            }
            return null;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function findById(int $id): ?User
    {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':id', $id, PDO::PARAM_INT);
            $statement->execute();
            $data = $statement->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                return $this->mapRowToUser($data);
            }
            return null;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function create(User $user): bool
    {
        try {
            $query = "INSERT INTO " . $this->table . " (username, email, password) VALUES (:username, :email, :password)";
            $statement = $this->dbConnection->prepare($query);

            $username = $user->getUsername();
            $email = $user->getEmail();
            $password = $user->getPassword();

            $statement->bindParam(':username', $username, PDO::PARAM_STR);
            $statement->bindParam(':password', $password, PDO::PARAM_STR);

            if ($email !== null) {
                $statement->bindParam(':email', $email, PDO::PARAM_STR);
            } else {
                $statement->bindValue(':email', null, PDO::PARAM_NULL);
            }

            return $statement->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function updateLastLogin(int $id): bool
    {
        try {
            $updateQuery = "UPDATE " . $this->table . " SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
            $updateStatement = $this->dbConnection->prepare($updateQuery);
            $updateStatement->bindParam(':id', $id, PDO::PARAM_INT);
            return $updateStatement->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function undoDeletion(int $id): bool
    {
        try {
            $query = "UPDATE " . $this->table . " SET deleted_at = NULL WHERE id = :id";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':id', $id, PDO::PARAM_INT);
            return $statement->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function updateProfilePic(int $userId, string $path, string $type): bool
    {
        try {
            $updateQuery = "UPDATE " . $this->table . " SET profile_pic_path = :path, profile_pic_mime_type = :type WHERE id = :userId";
            $updateStatement = $this->dbConnection->prepare($updateQuery);
            $updateStatement->bindParam(':path', $path, PDO::PARAM_STR);
            $updateStatement->bindParam(':type', $type, PDO::PARAM_STR);
            $updateStatement->bindParam(':userId', $userId, PDO::PARAM_INT);
            return $updateStatement->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function removeProfilePic(int $userId): bool
    {
        try {
            $updateQuery = "UPDATE " . $this->table . " SET profile_pic_path = NULL, profile_pic_mime_type = NULL WHERE id = :userId";
            $updateStatement = $this->dbConnection->prepare($updateQuery);
            $updateStatement->bindParam(':userId', $userId, PDO::PARAM_INT);
            return $updateStatement->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function updateUsername(int $userId, string $username): bool
    {
        try {
            $updateUsernameSql = "UPDATE " . $this->table . " SET username = :username WHERE id = :userId";
            $updateUsernameStmt = $this->dbConnection->prepare($updateUsernameSql);
            $updateUsernameStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $updateUsernameStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            return $updateUsernameStmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        try {
            $updatePasswordSql = "UPDATE " . $this->table . " SET password = :hashedPassword WHERE id = :userId";
            $updatePasswordStmt = $this->dbConnection->prepare($updatePasswordSql);
            $updatePasswordStmt->bindParam(':hashedPassword', $hashedPassword, PDO::PARAM_STR);
            $updatePasswordStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            return $updatePasswordStmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function markAsDeleted(int $userId, string $deletionTimestamp): bool
    {
        try {
            $updateDeletedAtSql = "UPDATE " . $this->table . " SET deleted_at = :deletionTimestamp WHERE id = :userId";
            $updateDeletedAtStmt = $this->dbConnection->prepare($updateDeletedAtSql);
            $updateDeletedAtStmt->bindParam(':deletionTimestamp', $deletionTimestamp, PDO::PARAM_STR);
            $updateDeletedAtStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            return $updateDeletedAtStmt->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function usernameExists(string $username): bool
    {
        try {
            $checkUsernameSql = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE username = :username";
            $checkUsernameStmt = $this->dbConnection->prepare($checkUsernameSql);
            $checkUsernameStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $checkUsernameStmt->execute();
            return $checkUsernameStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    private function mapRowToUser(array $row): User
    {
        return new User(
            $row['id'],
            $row['username'],
            $row['email'],
            $row['password'],
            $row['created_at'],
            $row['last_login'],
            $row['profile_pic_path'],
            $row['profile_pic_mime_type'],
            $row['deleted_at']
        );
    }
}
