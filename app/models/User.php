<?php

class User
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    // Function to add a new user to the database
    public function addUser($username, $email = null, $password)
    {
        // Validate input data
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Invalid input data.'];
        }
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Prepare the SQL statement
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
        $query = "SELECT * FROM Users WHERE username = :username";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function getUsersListByUsername($username)
    {
        $query = "SELECT * FROM Users WHERE username = :username";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $usersList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $usersList;
    }
}
?>