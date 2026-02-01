<?php

require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../models/Message.php';

class MessageService
{
    private $messageRepository;

    public function __construct($dbConnection)
    {
        $this->messageRepository = new MessageRepository($dbConnection);
    }

    public function sendMessage(int $senderId, array $request): array
    {
        if (!isset($request['receiverId']) || !isset($request['content'])) {
            return ['success' => false, 'message' => 'Receiver ID and content are required.'];
        }

        $receiverId = $request['receiverId'];
        $content = trim($request['content']);

        if (empty($content)) {
            return ['success' => false, 'message' => 'Message content cannot be empty.'];
        }

        if ($senderId == $receiverId) {
            return ['success' => false, 'message' => 'You cannot send a message to yourself.'];
        }

        try {
            $message = new Message(null, $senderId, $receiverId, $content, null);
            $success = $this->messageRepository->create($message);

            if ($success) {
                return ['success' => true, 'message' => 'Message sent successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to send message.'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function getInbox(int $userId): array
    {
        return $this->messageRepository->getRecentConversations($userId);
    }

    public function getConversation(int $userId, int $otherUserId): array
    {
        if ($userId == $otherUserId) {
        }

        $messages = $this->messageRepository->getConversation($userId, $otherUserId);

        $data = [];
        foreach ($messages as $message) {
            $data[] = [
                'id' => $message->getId(),
                'sender_id' => $message->getSenderId(),
                'receiver_id' => $message->getReceiverId(),
                'content' => $message->getContent(),
                'is_read' => $message->isRead(),
                'created_at' => $message->getCreatedAt()
            ];
        }

        return $data;
    }
}
?>