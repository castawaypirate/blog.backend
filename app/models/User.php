<?php

class User implements JsonSerializable
{
    private $id;
    private $username;
    private $email;
    private $password;
    private $createdAt;
    private $lastLogin;
    private $profilePicPath;
    private $profilePicMimeType;
    private $deletedAt;

    public function __construct($id, $username, $email, $password, $createdAt = null, $lastLogin = null, $profilePicPath = null, $profilePicMimeType = null, $deletedAt = null)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
        $this->createdAt = $createdAt;
        $this->lastLogin = $lastLogin;
        $this->profilePicPath = $profilePicPath;
        $this->profilePicMimeType = $profilePicMimeType;
        $this->deletedAt = $deletedAt;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getUsername()
    {
        return $this->username;
    }
    public function getEmail()
    {
        return $this->email;
    }
    public function getPassword()
    {
        return $this->password;
    }
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
    public function getLastLogin()
    {
        return $this->lastLogin;
    }
    public function getProfilePicPath()
    {
        return $this->profilePicPath;
    }
    public function getProfilePicMimeType()
    {
        return $this->profilePicMimeType;
    }
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'created_at' => $this->createdAt,
            'last_login' => $this->lastLogin,
            'profile_pic_path' => $this->profilePicPath,
            'deleted_at' => $this->deletedAt
        ];
    }
}