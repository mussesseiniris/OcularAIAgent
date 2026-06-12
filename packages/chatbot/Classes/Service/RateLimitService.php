<?php

declare(strict_types=1);

namespace Ocular\Chatbot\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;

class RateLimitService
{
    // Maximum questions allowed per 24 hour window
    private const LIMIT = 200;
    
    // 24 hours in seconds
    private const WINDOW = 864000;

    private ConnectionPool $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * Returns true if the request is allowed, false if limit exceeded
     */
    public function isAllowed(string $ip): bool
    {
        // Hash the IP for privacy
        $ipHash = hash('sha256', $ip);
        $now = time();

        $connection = $this->connectionPool->getConnectionForTable('tx_chatbot_rate_limit');

        // Look up existing record for this IP
        $record = $connection->select(
            ['ip_hash', 'question_count', 'started_at'],
            'tx_chatbot_rate_limit',
            ['ip_hash' => $ipHash]
        )->fetchAssociative();

        // No record yet — first question from this IP
        if (!$record) {
            $connection->insert('tx_chatbot_rate_limit', [
                'ip_hash'        => $ipHash,
                'question_count' => 1,
                'started_at'     => $now,
            ]);
            return true;
        }

        // Record exists but window has expired — reset the count
        if (($now - $record['started_at']) >= self::WINDOW) {
            $connection->update(
                'tx_chatbot_rate_limit',
                [
                    'question_count' => 1,
                    'started_at'     => $now,
                ],
                ['ip_hash' => $ipHash]
            );
            return true;
        }

        // Within window — check if limit exceeded
        if ($record['question_count'] >= self::LIMIT) {
            return false;
        }

        // Within window and under limit — increment count
        $connection->update(
            'tx_chatbot_rate_limit',
            ['question_count' => $record['question_count'] + 1],
            ['ip_hash' => $ipHash]
        );

        return true;
    }
}