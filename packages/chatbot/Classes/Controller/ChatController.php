<?php

namespace Ocular\Chatbot\Controller;

use Ocular\Chatbot\Service\ChatService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Ocular\Chatbot\Service\RateLimitService;

class ChatController extends ActionController
{

    private ChatService $chatService;
    private RateLimitService $rateLimitService;

    public function __construct(ChatService $chatService, RateLimitService $rateLimitService)
    {

        $this->chatService = $chatService;
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Receives the user's question and returns the AI-generated answer as JSON
     *
     * @return ResponseInterface
     */
    public function askAction(): ResponseInterface
    {   
        try {
            //Get user IP as the rate limit key
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!$this->rateLimitService->isAllowed($ip)) {
                return $this->jsonResponse(json_encode([
                    'answer' => 'You have reached the daily question limit. Please try again tomorrow or contact us at results@ocular.nz for further help.'
                ]));
            }

            if (!$this->request->hasArgument('question')) {
                return $this->htmlResponse($this->view->render());
            }

            $question = $this->request->getArgument('question');
            $rawHistory = $this->request->hasArgument('history') ? $this->request->getArgument('history') : '[]';
            $history = json_decode($rawHistory, true) ?? [];
            $result = $this->chatService->ask($question,$history);
            return $this->jsonResponse(json_encode(['answer' => $result]));
            
        } catch (\Throwable $e) {
            return $this->jsonResponse(json_encode([
            'answer' => 'DEBUG: ' . $e->getMessage()
            ]));
        }
    }
}
