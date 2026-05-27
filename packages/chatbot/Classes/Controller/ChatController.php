<?php

namespace Ocular\Chatbot\Controller;

use Ocular\Chatbot\Service\ChatService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class ChatController extends ActionController
{

    private ChatService $chatService;

    public function __construct(ChatService $chatService)
    {

        $this->chatService = $chatService;
    }

    /**
 * Receives the user's question and returns the AI-generated answer as JSON
 *
 * @return ResponseInterface
 */
    public function askAction():ResponseInterface
    {
        $question = $this->request->getArgument('question');
        $result = $this->chatService->ask($question);
        return $this->jsonResponse(json_encode(['answer' => $result]));
    }
}
