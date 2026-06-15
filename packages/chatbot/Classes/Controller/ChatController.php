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
        error_log('[ChatController] askAction called');
        error_log('[ChatController] token argument: ' . ($this->request->hasArgument('turnstileToken') ? 'present' : 'MISSING'));
        error_log('[ChatController] secret key set: ' . (empty(getenv('TURNSTILE_SECRET_KEY')) ? 'NO' : 'yes'));
        try {

            $secretKey = getenv('TURNSTILE_SECRET_KEY');
            if (empty($secretKey)) {
                error_log('[Turnstile] TURNSTILE_SECRET_KEY is not set — blocking all requests');
                return $this->jsonResponse(json_encode([
                    'answer' => 'Service configuration error. Please contact us at results@ocular.nz.'
                ]));
            }

            $token = $this->request->hasArgument('turnstileToken')
                ? $this->request->getArgument('turnstileToken')
                : '';


            if (!$this->verifyTurnstile($token, $secretKey)) {
                error_log('[Turnstile] Blocked request from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                return $this->jsonResponse(json_encode([
                    'answer' => 'Verification failed. Please try again.'
                ]));
            }

            if (!$this->request->hasArgument('question')) {
                return $this->htmlResponse($this->view->render());
            }
            
            //Get user IP as the rate limit key
            $ip = $this->request->getAttribute('normalizedParams')->getRemoteAddress();
            // error_log('[ChatController] IP resolved as: ' . $ip);
            if (!$this->rateLimitService->isAllowed($ip)) {
                return $this->jsonResponse(json_encode([
                    'answer' => 'You have reached the daily question limit. Please try again tomorrow or contact us at results@ocular.nz for further help.'
                ]));
            }

            $question = $this->request->getArgument('question');
            $rawHistory = $this->request->hasArgument('history') ? $this->request->getArgument('history') : '[]';
            $history = json_decode($rawHistory, true) ?? [];
            $result = $this->chatService->ask($question,$history);
            return $this->jsonResponse(json_encode(['answer' => $result]));
            
        } catch (\Throwable $e) {
            error_log('[ChatController] ERROR: ' . $e->getMessage());
            return $this->jsonResponse(json_encode([
            'answer' => 'Something went wrong. Please try again.'
            ]));
        }
    }

    private function verifyTurnstile(string $token, string $secretKey): bool
    {
        $secretKey = getenv('TURNSTILE_SECRET_KEY');

        if (empty($token)) {
            error_log('[Turnstile] Empty token — verification failed');
            return false; 
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'form_params' => [
                    'secret'   => $secretKey,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]
            ]);

            $body = $response->getBody()->getContents();
            $result = json_decode($body, true);

            error_log('[Turnstile] Cloudflare response: ' . $body);
            error_log('[Turnstile] Success: ' . ($result['success'] ? 'true' : 'false'));

            if (!empty($result['error-codes'])) {
                error_log('[Turnstile] Error codes: ' . implode(', ', $result['error-codes']));
            }

            return $result['success'] ?? false;

        } catch (\Throwable $e) {
            error_log('[Turnstile] HTTP request failed: ' . $e->getMessage());
            return false;
        }
    }


}
