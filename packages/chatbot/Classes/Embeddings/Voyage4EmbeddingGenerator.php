<?php

declare(strict_types=1);

namespace Ocular\Chatbot\Embeddings;

use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\AbstractVoyageAIEmbeddingGenerator;

final class Voyage4EmbeddingGenerator extends AbstractVoyageAIEmbeddingGenerator
{
    public function getEmbeddingLength(): int
    {
        return 1024;
    }

    public function getModelName(): string
    {
        return 'voyage-4';
    }
}