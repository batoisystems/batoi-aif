<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Prompts;

use Batoi\Aif\Exception\PromptRenderException;
use Batoi\Aif\Prompts\InMemoryPromptRegistry;
use Batoi\Aif\Prompts\PromptRenderer;
use Batoi\Aif\Value\PromptVersion;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class PromptGovernanceTest extends TestCase
{
    public function testLatestPromptUsesSemanticVersionOrdering(): void
    {
        $registry = new InMemoryPromptRegistry([
            new PromptVersion('summary', '1.9.0', 'old'),
            new PromptVersion('summary', '1.10.0', 'new'),
        ]);

        self::assertSame('1.10.0', $registry->get('summary')->version);
    }

    public function testPromptVersionsAreImmutable(): void
    {
        $registry = new InMemoryPromptRegistry([new PromptVersion('summary', '1.0.0', 'original')]);

        $this->expectException(LogicException::class);
        $registry->register(new PromptVersion('summary', '1.0.0', 'changed'));
    }

    public function testInvalidSemanticVersionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PromptVersion('summary', 'version-one', 'text');
    }

    public function testInputSchemaIsEnforced(): void
    {
        $prompt = new PromptVersion(
            code: 'count',
            version: '1.0.0',
            template: 'Count: {{count}}',
            inputSchema: [
                'required' => ['count'],
                'properties' => ['count' => ['type' => 'integer']],
                'additionalProperties' => false,
            ],
        );

        $this->expectException(PromptRenderException::class);
        (new PromptRenderer())->render($prompt, ['count' => 'one']);
    }
}
