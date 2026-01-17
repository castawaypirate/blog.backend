<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/MessageService.php';

class MessageController extends BaseController
{
    private $messageService;

    public function __construct($dbConnection)
    {
        $this->messageService = new MessageService($dbConnection);
    }

    public function sendMessage($senderId, $request)
    {
        return $this->messageService->sendMessage($senderId, $request);
    }
}
?>