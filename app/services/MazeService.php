<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/MazeChallengeRepository.php';
require_once __DIR__ . '/../models/Message.php';

// using native OpenSSL
class MazeService
{
    private $userRepository;
    private $messageRepository;
    private $mazeChallengeRepository;
    private $adminUsername;
    private $privateKeyPath;
    private $passphrase;
    private $bonusMessage;

    public function __construct(
        UserRepository $userRepository,
        MessageRepository $messageRepository,
        MazeChallengeRepository $mazeChallengeRepository
    ) {
        $this->userRepository = $userRepository;
        $this->messageRepository = $messageRepository;
        $this->mazeChallengeRepository = $mazeChallengeRepository;
        
        $this->adminUsername = defined('MAZE_ADMIN_USERNAME') ? MAZE_ADMIN_USERNAME : 'admin';
        $this->privateKeyPath = defined('MAZE_PRIVATE_KEY_PATH') ? MAZE_PRIVATE_KEY_PATH : '';
        $this->passphrase = defined('MAZE_PASSPHRASE') ? MAZE_PASSPHRASE : '';
        $this->bonusMessage = defined('MAZE_BONUS_MESSAGE') ? MAZE_BONUS_MESSAGE : 'random';
    }

    public function findPendingChallenges()
    {
        // Find messages TO the admin that look like PGP messages or public keys
        // This is a bit complex. The implementation plan suggests:
        // 1. Find the admin user ID
        $admin = $this->userRepository->findByUsername($this->adminUsername);
        if (!$admin) {
            return [];
        }
        $adminId = $admin->getId();

        // 2. Find messages to admin that are not processed yet
        // We'll use a query to find all senders who sent messages to admin
        // and check if we have a pending challenge for them.
        
        // Actually, let's simplify: the cron script will do the grouping.
        // This method should just return messages to admin.
        return $this->messageRepository->getRecentConversations($adminId);
    }

    public function processChallenge($userId, $encryptedUsername, $userPublicKeyBlock, $encryptedMsgId = null, $publicKeyMsgId = null, $isOverrideMsg = false, $isOverrideKey = false)
    {
        try {
            $prefix = $this->getOverridePrefix($isOverrideMsg, $isOverrideKey);

            // Step 1: Validate ciphertext
            $decryptedUsername = $this->decryptMessage($encryptedUsername);
            $ciphertextOk = ($decryptedUsername !== null);

            // Step 2: Validate identity (only possible if decryption succeeded)
            $identityOk = false;
            $cleanedUsername = null;
            if ($ciphertextOk) {
                $cleanedUsername = trim($decryptedUsername);
                $targetUser = $this->userRepository->findByUsername($cleanedUsername);
                $identityOk = ($targetUser && $targetUser->getId() == $userId);
            }

            // Step 3: Validate public key (always, independent of ciphertext)
            $keyOk = $this->validatePublicKey($userPublicKeyBlock);

            // Step 4: All good — encrypt and send the bonus reply
            if ($ciphertextOk && $identityOk && $keyOk) {
                $encryptedResponse = $this->encryptResponse($this->bonusMessage, $userPublicKeyBlock);
                if (!$encryptedResponse) {
                    // Shouldn't happen if validatePublicKey passed, but handle gracefully
                    $msg = $prefix . "something went wrong encrypting my reply. Try sending your Public Key again!";
                    if (!$prefix) $msg = ucfirst($msg);
                    $this->sendPlaintextReply($userId, $msg);
                    return $this->recordFailure($userId, 'invalid', 'Encryption failed despite valid key', $userPublicKeyBlock, $encryptedMsgId, $publicKeyMsgId, $cleanedUsername);
                }

                $admin = $this->userRepository->findByUsername($this->adminUsername);
                $this->sendReply($admin->getId(), $userId, $encryptedResponse);

                $newRecordId = $this->mazeChallengeRepository->create($userId, $userPublicKeyBlock, $encryptedMsgId, $publicKeyMsgId);
                if ($newRecordId) {
                    $this->mazeChallengeRepository->updateStatus($newRecordId, 'replied', $cleanedUsername);
                }

                return true;
            }

            // Step 5: Something failed — compose a comprehensive error message
            $msg = ucfirst($this->composeChallengeError($ciphertextOk, $identityOk, $keyOk, $isOverrideMsg, $isOverrideKey));
            $this->sendPlaintextReply($userId, $msg);

            $errorDetails = [];
            if (!$ciphertextOk) $errorDetails[] = 'Bad ciphertext';
            if ($ciphertextOk && !$identityOk) {
                $errorDetails[] = $cleanedUsername
                    ? "Identity mismatch: decrypted '$cleanedUsername' but sender ID is $userId"
                    : 'Identity check failed';
            }
            if (!$keyOk) $errorDetails[] = 'Bad public key';

            return $this->recordFailure(
                $userId, 'invalid', implode('; ', $errorDetails),
                $userPublicKeyBlock, $encryptedMsgId, $publicKeyMsgId,
                $ciphertextOk ? $cleanedUsername : null
            );
        } catch (Exception $e) {
            error_log('MazeService Error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendMissingKeyHint($userId, $encryptedMsgId = null, $isOverride = false, $identityMatch = true, $isDecryptable = true)
    {
        if (!$isDecryptable) {
            if ($isOverride) {
                $msg = "I've updated your secret message, but it is unreadable. Also, I don't have your Public Key yet!";
            } else {
                $msg = "I tried to decrypt your message but it's unreadable. Also, I don't have your Public Key yet!";
            }
        } else {
            // Message is decryptable
            if ($isOverride) {
                if ($identityMatch) {
                    $msg = "I've updated your secret message (it decrypted successfully and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?";
                } else {
                    $msg = "I've updated your secret message; it decrypted successfully, but identity verification failed! Also, I still don't have your Public Key to reply securely anyway!";
                }
            } else {
                // First time
                if ($identityMatch) {
                    $msg = "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?";
                } else {
                    $msg = "I decrypted your secret, but identity verification failed! Is it really you? AND I still don't have your Public Key to reply securely anyway!";
                }
            }
        }
        
        $this->sendPlaintextReply($userId, $msg);
        $status = $isDecryptable && $identityMatch ? 'pending_key' : 'invalid';
        return $this->recordFailure($userId, $status, $isOverride ? 'Message updated' : 'Missing public key', null, $encryptedMsgId);
    }

    public function sendMissingMessageHint($userId, $publicKey = null, $publicKeyMsgId = null, $isOverride = false)
    {
        $keyOk = $publicKey ? $this->validatePublicKey($publicKey) : false;

        if ($keyOk && $isOverride) {
            $msg = "New key, who dis? Just kidding—I've swapped your old key for this one. Still waiting on that encrypted message, though!";
        } elseif ($keyOk && !$isOverride) {
            $msg = "Nice key! Now, are you going to send me that encrypted username or what?";
        } elseif (!$keyOk && $isOverride) {
            $msg = "I've swapped your old key for this new one, but it doesn't look right either—generate a new one or make sure you copied it correctly. Still waiting on that encrypted message too!";
        } else {
            $msg = "That Public Key doesn't look right—generate a new one or make sure you copied it correctly. And I'm still waiting on that encrypted message!";
        }

        $this->sendPlaintextReply($userId, $msg);
        $error = ($isOverride ? 'Key updated' : 'Missing encrypted message') . ($keyOk ? '' : '; Bad public key');
        return $this->recordFailure($userId, 'pending_message', $error, $publicKey, null, $publicKeyMsgId);
    }

    private function recordFailure($userId, $status, $error, $publicKey = null, $encId = null, $pubId = null, $username = null)
    {
        // Capture the newly created row ID
        $newRecordId = $this->mazeChallengeRepository->create($userId, $publicKey, $encId, $pubId);
        
        // Safety check in case the database insert failed
        if (!$newRecordId) {
            return false; 
        }
        
        // Update the exact row we just created
        return $this->mazeChallengeRepository->updateStatus($newRecordId, $status, $username, $error);
    }

    public function decryptMessage($encryptedText)
    {
        if (!file_exists($this->privateKeyPath)) {
            error_log('Private key file not found at: ' . $this->privateKeyPath);
            return null;
        }

        $privateKeyArmored = file_get_contents($this->privateKeyPath);
        
        try {
            $privateKey = $this->getAdminPrivateKey($privateKeyArmored, $this->passphrase);
            if (!$privateKey) return null;
            
            $b64 = $this->unarmor($encryptedText, 'CIPHERTEXT');
            if (!$b64) return null;
            
            $ciphertext = base64_decode($b64);
            if (openssl_private_decrypt($ciphertext, $decrypted, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
                return trim($decrypted);
            }
            return null;
        } catch (Exception $e) {
            error_log('Decryption failed: ' . $e->getMessage());
            return null;
        }
    }

    public function validatePublicKey($userPublicKeyArmored): bool
    {
        try {
            $b64 = $this->unarmor($userPublicKeyArmored, 'PUBLIC KEY');
            if (!$b64) return false;
            $spki = base64_decode($b64);
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
            $pubKey = openssl_pkey_get_public($pem);
            return $pubKey !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function encryptResponse($plaintext, $userPublicKeyArmored)
    {
        try {
            $b64 = $this->unarmor($userPublicKeyArmored, 'PUBLIC KEY');
            if (!$b64) return null;
            
            $spki = base64_decode($b64);
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
            $pubKey = openssl_pkey_get_public($pem);
            
            if (!$pubKey) return null;
            
            if (openssl_public_encrypt($plaintext, $ciphertext, $pubKey, OPENSSL_PKCS1_OAEP_PADDING)) {
                return $this->armor(base64_encode($ciphertext), 'CIPHERTEXT');
            }
            return null;
        } catch (Exception $e) {
            error_log('Encryption failed: ' . $e->getMessage());
            return null;
        }
    }

    private function getAdminPrivateKey($encryptedKey, $passphrase)
    {
        $b64 = $this->unarmor($encryptedKey, 'PRIVATE KEY');
        if (!$b64) return false;
        
        $raw = base64_decode($b64);
        if (strlen($raw) < 16 + 12 + 16) return false;
        
        $salt = substr($raw, 0, 16);
        $iv = substr($raw, 16, 12);
        $tag = substr($raw, -16);
        $ciphertext = substr($raw, 28, -16);
        
        $aesKey = hash_pbkdf2('sha256', $passphrase, $salt, 100000, 32, true);
        $pkcs8 = openssl_decrypt($ciphertext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if (!$pkcs8) return false;
        
        $pem = "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode($pkcs8), 64, "\n") . "-----END PRIVATE KEY-----\n";
        return openssl_pkey_get_private($pem);
    }

    private function unarmor($text, $label)
    {
        $lines = explode("\n", str_replace("\r", "", $text));
        $b64 = '';
        $inBlock = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === "-----BEGIN $label-----") {
                $inBlock = true;
                continue;
            }
            if ($line === "-----END $label-----") {
                break;
            }
            if ($inBlock && $line !== '') {
                $b64 .= $line;
            }
        }
        return $b64 ? $b64 : false;
    }
    
    private function armor($b64, $label)
    {
        $chunks = str_split($b64, 64);
        $body = implode("\n", $chunks);
        return "-----BEGIN $label-----\n" . $body . "\n-----END $label-----\n";
    }

    private function sendReply($senderId, $receiverId, $content)
    {
        $message = new Message($senderId, $receiverId, $content);
        return $this->messageRepository->create($message);
    }

    private function sendPlaintextReply($userId, $text)
    {
        $admin = $this->userRepository->findByUsername($this->adminUsername);
        if ($admin) {
            return $this->sendReply($admin->getId(), $userId, $text);
        }
        return false;
    }

    private function getOverridePrefix(bool $isOverrideMsg, bool $isOverrideKey): string
    {
        if ($isOverrideMsg && $isOverrideKey) {
            return "I've updated both your secret message and your Public Key, but ";
        } elseif ($isOverrideMsg) {
            return "I've updated your secret message, but ";
        } elseif ($isOverrideKey) {
            return "I've updated your Public Key, but ";
        }
        return "";
    }

    private function composeChallengeError(bool $ciphertextOk, bool $identityOk, bool $keyOk, bool $isOverrideMsg, bool $isOverrideKey): string
    {
        // Case 1: Both ciphertext and key are bad
        if (!$ciphertextOk && !$keyOk) {
            if ($isOverrideMsg && $isOverrideKey) {
                return "I've updated both your secret message and your Public Key, but your new secret message is unreadable and your new Public Key looks broken too. Double-check both on the cryptography site!";
            } elseif ($isOverrideMsg) {
                return "I've updated your secret message, but it is unreadable, and your Public Key looks broken too. Double-check both on the cryptography site!";
            } elseif ($isOverrideKey) {
                return "I've updated your Public Key, but it looks broken, and your encrypted message is unreadable too. Double-check both on the cryptography site!";
            } else {
                return "your encrypted message is unreadable and your Public Key looks broken too. Double-check both on the cryptography site!";
            }
        }

        // Case 2: Ciphertext bad, key is fine
        if (!$ciphertextOk && $keyOk) {
            if ($isOverrideMsg && $isOverrideKey) {
                return "I've updated both your secret message and your Public Key; your new Public Key looks good, but I couldn't decrypt your new secret message. Re-encrypt your username using my public key and try again!";
            } elseif ($isOverrideMsg) {
                return "I've updated your secret message, but I couldn't decrypt it. Re-encrypt your username using my public key and try again!";
            } elseif ($isOverrideKey) {
                return "I've updated your Public Key (it looks good!), but your encrypted message is unreadable—I couldn't decrypt it. Re-encrypt your username using my public key and try again!";
            } else {
                return "your encrypted message is unreadable—I couldn't decrypt it. Re-encrypt your username using my public key and try again!";
            }
        }

        // Case 3: Identity failed and key is bad
        if ($ciphertextOk && !$identityOk && !$keyOk) {
            if ($isOverrideMsg && $isOverrideKey) {
                return "I've updated both your secret message and your Public Key; your new secret message decrypted successfully, but identity verification failed, and your new Public Key looks broken too. Make sure you encrypt your actual username, and generate a new key or be certain that you correctly copied it here!";
            } elseif ($isOverrideMsg) {
                return "I've updated your secret message; it decrypted successfully, but identity verification failed! Also, your Public Key looks broken too. Make sure you encrypt your actual username, and generate a new key or be certain that you correctly copied it here!";
            } elseif ($isOverrideKey) {
                return "I've updated your Public Key, but it looks broken, and identity verification failed too! Make sure you encrypt your actual username, and generate a new key or be certain that you correctly copied it here!";
            } else {
                return "identity verification failed and your Public Key looks broken too. Make sure you encrypt your actual username, and generate a new key or be certain that you correctly copied it here!";
            }
        }

        // Case 4: Identity failed, key is fine
        if ($ciphertextOk && !$identityOk && $keyOk) {
            if ($isOverrideMsg && $isOverrideKey) {
                return "I've updated both your secret message and your Public Key; your new Public Key looks good and your new secret message decrypted successfully, but identity verification failed! The decrypted content doesn't match your account username. Make sure you encrypt exactly your username—the one you use on this site!";
            } elseif ($isOverrideMsg) {
                return "I've updated your secret message; it decrypted successfully, but identity verification failed! The decrypted content doesn't match your account username. Make sure you encrypt exactly your username—the one you use on this site!";
            } elseif ($isOverrideKey) {
                return "I've updated your Public Key (it looks good!), but identity verification failed! The decrypted content doesn't match your account username. Make sure you encrypt exactly your username—the one you use on this site!";
            } else {
                return "identity verification failed! The decrypted content doesn't match your account username. Make sure you encrypt exactly your username—the one you use on this site!";
            }
        }

        // Case 5: Identity passed, key is bad
        if ($ciphertextOk && $identityOk && !$keyOk) {
            if ($isOverrideMsg && $isOverrideKey) {
                return "I've updated both your secret message and your Public Key; your new secret message decrypted successfully and your identity checked out, but I couldn't encrypt my reply with your new Public Key. It looks broken—generate a new one or be certain that you correctly copied your public key to here.";
            } elseif ($isOverrideMsg) {
                return "I've updated your secret message (it decrypted successfully and your identity checked out!), but I couldn't encrypt my reply with your Public Key. It looks broken—generate a new one or be certain that you correctly copied your public key to here.";
            } elseif ($isOverrideKey) {
                return "I've updated your Public Key, but I couldn't encrypt my reply with it. It looks broken—generate a new one or be certain that you correctly copied your public key to here.";
            } else {
                return "I couldn't encrypt my reply with your Public Key. It looks broken—generate a new one or be certain that you correctly copied your public key to here.";
            }
        }

        // Fallback (shouldn't reach here if called correctly)
        return "something went wrong processing your challenge. Try again!";
    }
}
