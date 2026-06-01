<?php

namespace Ocular\Chatbot\Controller;

use Ocular\Chatbot\Service\ChatService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
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
    public function askAction(): ResponseInterface
    {
        if (!$this->request->hasArgument('question')) {
            return $this->htmlResponse($this->view->render());
        }

        $question = $this->request->getArgument('question');
        $rawHistory = $this->request->hasArgument('history') ? $this->request->getArgument('history') : '[]';
        $history = json_decode($rawHistory, true) ?? [];
        $result = $this->chatService->ask($question,$history);
        return $this->jsonResponse(json_encode(['answer' => $result]));
    }
}
