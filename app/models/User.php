<?php
class User
{
    private $dbConnection;

    public function __construct($dbConnection) {
        $this->dbConnection = $dbConnection;
    }

    public function addUser($username, $email = null, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Invalid input data.'];
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

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

    public function getUserByUsername($username) {
        try {
            $query = "SELECT * FROM Users WHERE username = :username";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':username', $username, PDO::PARAM_STR);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $updateQuery = "UPDATE Users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
                $updateStatement = $this->dbConnection->prepare($updateQuery);
                $updateStatement->bindParam(':id', $user['id'], PDO::PARAM_INT);
                $updateStatement->execute();
            }

            return $user;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getUserByUserId($userId) {
        try {
            $query = "SELECT * FROM Users WHERE id = :userId";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            return $user;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getUserData($userId) {
        try {
            $query = "SELECT username FROM Users WHERE id = :userId;";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            return $user;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function uploadProfilePic($userId, $file) {
        $this->dbConnection->beginTransaction();
        $uploads = ROOT_DIR.'/uploads/';

        try {
            // select username , path of the profice pic and its type
            $query = "SELECT username, profile_pic_path, profile_pic_mime_type FROM Users WHERE id = :userId;";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            if (!$statement->execute()) {
                return ['success' => false, 'message' => 'Failed to select user\'s data.'];
            }
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            $username = $result['username'];
            $profilePicPath = $result['profile_pic_path'];
            $profilePicMimeType = $result['profile_pic_mime_type'];

            // will be needed for the renaming
            $newProfilePicFilename = pathinfo($file['name'], PATHINFO_FILENAME);
            $newProfilePicExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

            $prevProfilePicFilename = pathinfo($profilePicPath, PATHINFO_FILENAME);
            $prevProfilePicExtension = pathinfo($profilePicPath, PATHINFO_EXTENSION);

            $newPrevName = $newProfilePicFilename.'1.'.$prevProfilePicExtension;

            $oldPrevFilePath = $uploads. $profilePicPath;
            $newPrevFilePath = $uploads. $username.'/'.$newPrevName;

            // if profilePicPath is null create a new directory inside the uploads folder with the username as its name
            if(!$profilePicPath) {
                $userDirectory = $uploads . $username;
                if (!is_dir($userDirectory)) {
                    if (!mkdir($userDirectory, 0777, true)) {
                        return ['success' => false, 'message' => 'Failed to create user directory.'];
                    }
                }
            } else {
                // rename the existing profile pic
                if (!rename($oldPrevFilePath, $newPrevFilePath)) {
                    return ['success' => false, 'message' => 'Old to new process failure.'];
                }
            }

            // upload the new profile pic to the user's directory
            $shortPath = $username.'/'.$file['name'];
            $destination =  $uploads.$shortPath;
            // web server should have write permission to the destination folder
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                // undo the renaming of the previous profile pic
                if (!rename($newPrevFilePath, $oldPrevFilePath)) {
                    return ['success' => false, 'message' => 'New to old process failure.'];
                }

                return ['success' => false, 'message' => 'Can\'t move uploaded file.'];
            }

            $updateQuery = "UPDATE Users SET profile_pic_path = :path, profile_pic_mime_type = :type WHERE id = :userId";
            $updateStatement = $this->dbConnection->prepare($updateQuery);
            $updateStatement->bindParam(':path', $shortPath, PDO::PARAM_STR);
            $updateStatement->bindParam(':type', $file['type'], PDO::PARAM_STR);
            $updateStatement->bindParam(':userId', $userId, PDO::PARAM_INT);

            if ($updateStatement->execute()) {
                // delete the previous profile pic
                unlink($newPrevFilePath);

                $this->dbConnection->commit();
                return ['success' => true, 'message' => 'File is uploaded.'];
            } else {
                // rename the previous profile pic back to its original name
                if (!rename($newPrevFilePath, $oldPrevFilePath)) {
                    return ['success' => false, 'message' => 'Rolloback process failure.'];
                }

                $this->dbConnection->rollback();
                return ['success' => true, 'message' => 'Query execution failure.'];
            }
        } catch (PDOException $e) {
            $this->dbConnection->rollback();
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'You need to do it by hand now. Database error: ' . $e->getMessage()];
        }
    }

    public function getProfilePic($userId) {
        $uploads = ROOT_DIR . '/uploads/';
        try {
            $query = "SELECT profile_pic_path, profile_pic_mime_type FROM Users WHERE id = :userId;";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            $statement->execute();
            $profilePicData = $statement->fetch(PDO::FETCH_ASSOC);
    
            if (is_null($profilePicData['profile_pic_path'])) {
                return ['success' => false, 'message' => 'Profile picture path is null.'];
            }
    
            $shortPath = $profilePicData['profile_pic_path'];
            $fullPath = $uploads . $shortPath;
    
            if (!file_exists($fullPath)) {
                return ['success' => false, 'message' => 'Profile picture file does not exist.'];
            }
    
            $profilePicData['profile_pic_full_path'] = $fullPath;
            return ['success' => true, 'data' => $profilePicData];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    

    public function deleteProfilePic($userId) {
        $uploads = ROOT_DIR.'/uploads/';
        $this->dbConnection->beginTransaction();
    
        try {
            $query = "SELECT profile_pic_path FROM Users WHERE id = :userId;";
            $statement = $this->dbConnection->prepare($query);
            $statement->bindParam(':userId', $userId, PDO::PARAM_INT);
            $statement->execute();
            $user = $statement->fetch(PDO::FETCH_ASSOC);
    
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
    
            $profilePicPath = $user['profile_pic_path'];
            $fullPath = $uploads . $profilePicPath;
    
            if (!($profilePicPath && file_exists($fullPath))) {
                return ['success' => false, 'message' => 'Profile picture does not exist.'];
            }
    
            $updateQuery = "UPDATE Users SET profile_pic_path = NULL, profile_pic_mime_type = NULL WHERE id = :userId";
            $updateStatement = $this->dbConnection->prepare($updateQuery);
            $updateStatement->bindParam(':userId', $userId, PDO::PARAM_INT);
    
            if ($updateStatement->execute()) {
                $this->dbConnection->commit();
                if (unlink($fullPath)) {
                    return ['success' => true, 'message' => 'Profile picture deleted successfully.'];
                } else {
                    error_log('Failed to delete profile picture file after database update.');
                    return ['success' => false, 'message' => 'Database updated but failed to delete profile picture file.'];
                }
                return ['success' => true, 'message' => 'Profile picture deleted successfully.'];
            } else {
                $this->dbConnection->rollback();
                return ['success' => false, 'message' => 'Failed to update database.'];
            }
        } catch (PDOException $e) {
            $this->dbConnection->rollback();
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function changeUsername($userId, $username) {
        try {
            $checkUserSql = "SELECT username FROM Users WHERE id = :userId";
            $checkUserStmt = $this->dbConnection->prepare($checkUserSql);
            $checkUserStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $checkUserStmt->execute();
            
            $oldUsername = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldUsername) {
                return ['success' => false, 'message' => 'User doesn\'t exist. Weird.'];
            }

            if ($oldUsername['username'] === $username) {
                return ['success' => false, 'message' => 'Wait a minute. That\'s the same username.'];

            }
    
            $checkUsernameSql = "SELECT COUNT(*) as count FROM Users WHERE username = :username";
            $checkUsernameStmt = $this->dbConnection->prepare($checkUsernameSql);
            $checkUsernameStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $checkUsernameStmt->execute();
            
            $usernameCount = $checkUsernameStmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($usernameCount > 0) {
                return ['success' => false, 'message' => 'Username already exists.'];
            }
    
            $updateUsernameSql = "UPDATE Users SET username = :username WHERE id = :userId";
            $updateUsernameStmt = $this->dbConnection->prepare($updateUsernameSql);
            $updateUsernameStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $updateUsernameStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            
            if ($updateUsernameStmt->execute()) {
                return ['success' => true, 'message' => 'Username updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error updating the username.'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function changePassword($userId, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePasswordSql = "UPDATE Users SET password = :hashedPassword WHERE id = :userId";
            $updatePasswordStmt = $this->dbConnection->prepare($updatePasswordSql);
            $updatePasswordStmt->bindParam(':hashedPassword', $hashedPassword, PDO::PARAM_STR);
            $updatePasswordStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            
            if ($updatePasswordStmt->execute()) {
                return ['success' => true, 'message' => 'Password updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Error updating the password.'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function deleteUser($userId) {
        $uploads = ROOT_DIR.'/uploads/';
        // try {
        //     $deletePostSql = "DELETE FROM Posts WHERE id = :postId";
        //     $deletePostStmt = $this->dbConnection->prepare($deletePostSql);
        //     $deletePostStmt->bindParam(':postId', $postId, PDO::PARAM_INT);
    
        //     if ($deletePostStmt->execute()) {
        //         return ['success' => true, 'message' => 'Post deleted successfully.'];
        //     } else {
        //         return ['success' => false, 'message' => 'Error deleting the post.'];
        //     }
        // } catch (PDOException $e) {
        //     error_log('Database error: ' . $e->getMessage());
        //     return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        // }
    }
}
?>