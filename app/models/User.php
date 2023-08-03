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
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO Users (username, email, password) VALUES (:username, :email, :password)";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->bindParam(':password', $hashedPassword, PDO::PARAM_STR);

        if ($email !== null) {
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
        } else {
            // If email is not provided, set it as null in the database
            $statement->bindValue(':email', null, PDO::PARAM_NULL);
        }

        return $statement->execute();
    }

    // Function to fetch user details by username
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

        // Fetch all matching users as an associative array
        $usersList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $usersList;
    }

    // Function to fetch user details by id
    public function getUserById($userId)
    {
        $query = "SELECT * FROM Users WHERE id = :userId";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindParam(':userId', $userId, PDO::PARAM_INT);

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    // Function to update user information
    public function updateUser($userId, $username, $email = null)
    {
        $query = "UPDATE Users SET username = :username";

        if ($email !== null) {
            $query .= ", email = :email";
        }

        $query .= " WHERE id = :userId";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindParam(':username', $username, PDO::PARAM_STR);

        if ($email !== null) {
            $statement->bindParam(':email', $email, PDO::PARAM_STR);
        }

        $statement->bindParam(':userId', $userId, PDO::PARAM_INT);

        return $statement->execute();
    }

    // Function to delete a user by ID
    public function deleteUser($userId)
    {
        $query = "DELETE FROM Users WHERE id = :userId";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindParam(':userId', $userId, PDO::PARAM_INT);

        return $statement->execute();
    }
}
?>