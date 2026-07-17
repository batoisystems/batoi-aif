<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Providers;

use Batoi\Aif\Exception\ProviderCapabilityException;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Providers\InMemoryProviderRegistry;
use Batoi\Aif\Providers\MockProvider;
use Batoi\Aif\Providers\OpenAICompatibleProvider;
use Batoi\Aif\Providers\ProviderRouter;
use PHPUnit\Framework\TestCase;

final class ProviderRouterTest extends TestCase
{
    public function testRouterFallsBackDeterministicallyByCapability(): void
    {
        $providers = new InMemoryProviderRegistry([
            'openai' => new OpenAICompatibleProvider('test-key'),
            'mock' => new MockProvider(),
        ]);

        $route = (new ProviderRouter($providers, 'openai'))->route(ExecutionOperation::Stream, null, null);

        self::assertSame('mock', $route->provider);
        self::assertSame('healthy_capability_fallback', $route->reason);
    }

    public function testExplicitUnsupportedCapabilityDoesNotFallbackSilently(): void
    {
        $providers = new InMemoryProviderRegistry([
            'openai' => new OpenAICompatibleProvider('test-key'),
            'mock' => new MockProvider(),
        ]);

        $this->expectException(ProviderCapabilityException::class);
        (new ProviderRouter($providers, 'mock'))->route(ExecutionOperation::Stream, 'openai', null);
    }
}
