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


            $sessionVerified = $_SESSION['turnstile_verified'] ?? false;
            error_log('[Turnstile] Session already verified: ' . ($sessionVerified ? 'yes' : 'no'));

            if (!$sessionVerified) {
                // First message — must have a valid token
                $token = $this->request->hasArgument('turnstileToken')
                    ? $this->request->getArgument('turnstileToken')
                    : '';

                error_log('[Turnstile] Token received: ' . (empty($token) ? 'MISSING' : 'present'));


                if (!$this->verifyTurnstile($token)) {
                    error_log( '[Turnstile] Blocked request from IP: ' . $ip);
                    return $this->jsonResponse(json_encode([
                        'answer' => 'Verification failed. Please try again.'
                    ]));
                }

                $_SESSION['turnstile_verified'] = true;
                error_log('[Turnstile] Session marked as verified for IP: ' . $ip);

                $verified = true;
            } else {
                error_log('[Turnstile] Skipping verification — already verified this session');
                $verified = true;
            }

            if (!$this->request->hasArgument('question')) {
                return $this->htmlResponse($this->view->render());
            }

            $question = $this->request->getArgument('question');
            $rawHistory = $this->request->hasArgument('history') ? $this->request->getArgument('history') : '[]';
            $history = json_decode($rawHistory, true) ?? [];
            $result = $this->chatService->ask($question,$history);
            return $this->jsonResponse(json_encode([
                'answer' => $result,
                'verified' => $verified
                ]));
            
        } catch (\Throwable $e) {
            error_log('[Turnstile] ERROR: ' . $e->getMessage());
            return $this->jsonResponse(json_encode([
            'answer' => 'DEBUG: ' . $e->getMessage()
            ]));
        }
    }

    private function verifyTurnstile(string $token): bool
    {
        $secretKey = getenv('TURNSTILE_SECRET_KEY');

        if (empty($secretKey)) {
            error_log('[Turnstile] No secret key configured — skipping verification');
            return true;
        }

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
