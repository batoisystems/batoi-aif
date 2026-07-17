<?php

declare(strict_types=1);

namespace Batoi\Aif\Providers;

use Batoi\Aif\Contracts\CapabilityAwareProviderInterface;
use Batoi\Aif\Contracts\EnumerableProviderRegistryInterface;
use Batoi\Aif\Contracts\ProviderRegistryInterface;
use Batoi\Aif\Contracts\MetricsCollectorInterface;
use Batoi\Aif\Exception\ProviderCapabilityException;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Value\ProviderRoute;
use Batoi\Aif\Value\MetricEvent;

final readonly class ProviderRouter
{
    public function __construct(
        private ProviderRegistryInterface $providers,
        private string $defaultProvider,
        private ?MetricsCollectorInterface $metrics = null,
    ) {
    }

    public function route(ExecutionOperation $operation, ?string $requestedProvider, ?string $requestedModel): ProviderRoute
    {
        if ($requestedProvider !== null) {
            $provider = $this->providers->get($requestedProvider);
            $healthy = $this->healthy($requestedProvider, $provider);

            if (!$healthy || !$this->supports($provider, $operation, $requestedModel)) {
                throw new ProviderCapabilityException(sprintf(
                    'Provider "%s" does not support operation "%s" for the requested model.',
                    $requestedProvider,
                    $operation->value,
                ));
            }

            return new ProviderRoute($requestedProvider, $requestedModel, 'explicit_request');
        }

        foreach ($this->candidateCodes() as $providerCode) {
            $provider = $this->providers->get($providerCode);

            if ($this->healthy($providerCode, $provider) && $this->supports($provider, $operation, $requestedModel)) {
                return new ProviderRoute(
                    $providerCode,
                    $requestedModel,
                    $providerCode === $this->defaultProvider ? 'healthy_default' : 'healthy_capability_fallback',
                );
            }
        }

        throw new ProviderCapabilityException(sprintf(
            'No healthy provider supports operation "%s" for the requested model.',
            $operation->value,
        ));
    }

    /** @return list<string> */
    private function candidateCodes(): array
    {
        $codes = [$this->defaultProvider];

        if ($this->providers instanceof EnumerableProviderRegistryInterface) {
            $additional = array_keys($this->providers->all());
            sort($additional, SORT_STRING);
            $codes = array_merge($codes, $additional);
        }

        return array_values(array_unique($codes));
    }

    private function supports(object $provider, ExecutionOperation $operation, ?string $model): bool
    {
        if (!$provider instanceof CapabilityAwareProviderInterface) {
            return true;
        }

        $capability = match ($operation) {
            ExecutionOperation::Infer => 'text',
            ExecutionOperation::Stream => 'stream',
            ExecutionOperation::Embed => 'embedding',
            ExecutionOperation::Moderate => 'moderation',
            ExecutionOperation::Retrieve => 'retrieval',
            ExecutionOperation::Tool => 'tool',
        };

        foreach ($provider->capabilities() as $providerCapability) {
            $modelMatches = $model === null
                || $providerCapability->model === $model
                || ($providerCapability->metadata['accepts_model_override'] ?? false) === true;

            if ($modelMatches && $providerCapability->supports($capability)) {
                return true;
            }
        }

        return false;
    }

    private function healthy(string $providerCode, object $provider): bool
    {
        $healthy = method_exists($provider, 'healthCheck') && $provider->healthCheck();
        $this->metrics?->record(new MetricEvent(
            name: 'aif.provider.health',
            value: $healthy ? 1.0 : 0.0,
            tags: ['provider' => $providerCode],
            occurredAt: gmdate(DATE_ATOM),
        ));

        return $healthy;
    }
}
