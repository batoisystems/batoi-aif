<?php

declare(strict_types=1);

namespace Batoi\Aif\Value;

use InvalidArgumentException;

final readonly class Document
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $uid,
        public string $workspaceId,
        public string $content,
        public array $metadata = [],
    ) {
        if (trim($this->uid) === '' || trim($this->workspaceId) === '' || trim($this->content) === '') {
            throw new InvalidArgumentException('Document UID, workspace, and content are required.');
        }
    }
}
