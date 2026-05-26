<?php

declare(strict_types=1);

namespace Batoi\Aif\Policy;

use Batoi\Aif\Contracts\PolicyEngineInterface;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\PolicyDecision;

final readonly class StaticPolicyEngine implements PolicyEngineInterface
{
    /**
     * @param list<string> $allowedProviders
     * @param list<string> $allowedModels
     * @param list<string> $allowedRoles
     */
    public function __construct(
        private array $allowedProviders = [],
        private array $allowedModels = [],
        private array $allowedRoles = [],
        private ?int $maxInputChars = null,
    ) {
    }

    public function decide(ExecutionContext $context, InferenceRequest $request): PolicyDecision
    {
        $reasons = [];
        $evidence = [
            'workspace_id' => $context->workspaceId,
            'provider' => $request->provider,
            'model' => $request->model,
            'input_chars' => strlen($request->input),
        ];

        if ($this->allowedRoles !== [] && !$this->hasAllowedRole($context->roles)) {
            $reasons[] = 'role_not_allowed';
        }

        if ($request->provider !== null && $this->allowedProviders !== [] && !in_array($request->provider, $this->allowedProviders, true)) {
            $reasons[] = 'provider_not_allowed';
        }

        if ($request->model !== null && $this->allowedModels !== [] && !in_array($request->model, $this->allowedModels, true)) {
            $reasons[] = 'model_not_allowed';
        }

        if ($this->maxInputChars !== null && strlen($request->input) > $this->maxInputChars) {
            $reasons[] = 'input_too_large';
        }

        if ($reasons !== []) {
            return new PolicyDecision(PolicyAction::Deny, $reasons, $evidence);
        }

        return new PolicyDecision(PolicyAction::Allow, ['allowed'], $evidence);
    }

    /**
     * @param list<string> $roles
     */
    private function hasAllowedRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, $this->allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
