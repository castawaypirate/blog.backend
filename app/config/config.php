<?php
define('ROOT_DIR', realpath(__DIR__.'/../../'));

require_once ROOT_DIR.'/vendor/autoload.php';

use Dotenv\Dotenv;

// load the .env file
$dotenv = Dotenv::createImmutable(ROOT_DIR);
$dotenv->load();

// database configuration
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);
define('DB_NAME', $_ENV['DB_NAME']);


// JWT Configuration
define('JWT_SECRET', $_ENV['JWT_SECRET']);
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', $_ENV['JWT_EXPIRATION']);

define('USER_DELETION_DELAY', $_ENV['USER_DELETION_DELAY']);
?>