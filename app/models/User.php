<?php
class User
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function addUser($username, $email = null, $password)
    {
        // validate input data
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Invalid input data.'];
        }
        // hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // prepare the SQL statement
        $query = "INSERT INTO Users (username, email, password) VALUES (:username, :email, :password)";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->bindParam(':password', $hashedPassword, PDO::PARAM_STR);

        if ($email !== null) {
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
        } else {
            $statement->bindValue(':email', null, PDO::PARAM_NULL);
        }

        try {
            if ($statement->execute()) {
                return ['success' => true, 'message' => 'User added successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error adding the user.'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getUserByUsername($username)
    {
        try {
            $query = "SELECT * FROM Users WHERE username = :username";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':username', $username, PDO::PARAM_STR);
            $statement->execute();

            return $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // handle exception
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>