<?php

require_once __DIR__.'/../config/config.php';

class Database{
 
    private $dbHost = DB_HOST;
    private $dbUsername = DB_USERNAME;
    private $dbPassword = DB_PASSWORD;
    private $dbName = DB_NAME;

    private $dbConnection;

    public function getConnection() {
        try {
            $dsn = "mysql:host=$this->dbHost;dbname=$this->dbName";
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );

            $this->dbConnection = new PDO($dsn, $this->dbUsername, $this->dbPassword, $options);

        } catch (PDOException $e) {
            throw new Exception('Connection failed: ' . $e->getMessage());
        }
        return $this->dbConnection;
    }
}

?>