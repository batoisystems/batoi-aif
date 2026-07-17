<?php

declare(strict_types=1);

namespace Batoi\Aif\Rag;

use InvalidArgumentException;

final readonly class TextChunker
{
    public function __construct(private int $chunkCharacters = 1200, private int $overlapCharacters = 150)
    {
        if ($this->chunkCharacters < 1 || $this->overlapCharacters < 0 || $this->overlapCharacters >= $this->chunkCharacters) {
            throw new InvalidArgumentException('Chunk size must be positive and overlap must be smaller than the chunk.');
        }
    }

    /** @return list<string> */
    public function chunk(string $content): array
    {
        $content = trim($content);
        $chunks = [];
        $offset = 0;
        $step = $this->chunkCharacters - $this->overlapCharacters;

        while ($offset < strlen($content)) {
            $chunk = trim(substr($content, $offset, $this->chunkCharacters));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
            $offset += $step;
        }

        return $chunks;
    }
}
