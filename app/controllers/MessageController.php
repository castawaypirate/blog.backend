<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/MessageService.php';

class MessageController extends BaseController
{
    private $messageService;

    public function __construct($messageService)
    {
        $this->messageService = $messageService;
    }

    public function sendMessage($senderId, $request)
    {
        return $this->messageService->sendMessage($senderId, $request);
    }

    public function getInbox($userId)
    {
        $conversations = $this->messageService->getInbox($userId);
        return ['success' => true, 'conversations' => $conversations];
    }

    public function getConversation($userId)
    {
        if (!isset($_GET['otherUserId'])) {
            return ['success' => false, 'message' => 'otherUserId is required'];
        }
        $otherUserId = intval($_GET['otherUserId']);

        $messages = $this->messageService->getConversation($userId, $otherUserId);
        return ['success' => true, 'messages' => $messages];
    }
}
?>