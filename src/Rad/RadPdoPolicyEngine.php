<?php

declare(strict_types=1);

namespace Batoi\Aif\Rad;

use Batoi\Aif\Contracts\OperationAwarePolicyEngineInterface;
use Batoi\Aif\Policy\ExecutionOperation;
use Batoi\Aif\Policy\PolicyAction;
use Batoi\Aif\Value\ExecutionContext;
use Batoi\Aif\Value\InferenceRequest;
use Batoi\Aif\Value\PolicyDecision;
use Batoi\Aif\Value\PolicySubject;
use PDO;

final readonly class RadPdoPolicyEngine implements OperationAwarePolicyEngineInterface
{
    public function __construct(private PDO $pdo, private PolicyAction $defaultAction = PolicyAction::Deny)
    {
    }

    public function decide(ExecutionContext $context, InferenceRequest $request): PolicyDecision
    {
        return $this->decideForOperation($context, new PolicySubject(ExecutionOperation::Infer, $request));
    }

    public function decideForOperation(ExecutionContext $context, PolicySubject $subject): PolicyDecision
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT
    p.uid AS policy_uid,
    p.versioncode AS policy_version,
    p.space_id,
    r.uid AS rule_uid,
    r.a_rule_key,
    r.a_action,
    r.a_match_json,
    r.a_weight
FROM a_aif_policy p
INNER JOIN a_aif_policy_rule r
    ON r.a_policy_id = p.id
    AND r.space_id = p.space_id
    AND r.livestatus = '0'
    AND r.a_is_active = '1'
WHERE p.space_id IN (0, :space_id)
  AND p.livestatus = '0'
  AND p.a_status = 'active'
SQL);
        $statement->execute(['space_id' => $context->workspaceId]);

        /** @var list<array<string, mixed>> $rules */
        $rules = $statement->fetchAll(PDO::FETCH_ASSOC);
        usort($rules, static function (array $left, array $right): int {
            $scopeOrder = ((int) $right['space_id']) <=> ((int) $left['space_id']);

            return $scopeOrder !== 0
                ? $scopeOrder
                : ((int) $right['a_weight']) <=> ((int) $left['a_weight']);
        });

        foreach ($rules as $rule) {
            $match = $this->jsonObject($rule['a_match_json'] ?? null);
            if (!$this->matches($match, $context, $subject)) {
                continue;
            }

            $action = PolicyAction::tryFrom((string) $rule['a_action']) ?? PolicyAction::Deny;

            return new PolicyDecision(
                action: $action,
                reasons: [(string) $rule['a_rule_key']],
                evidence: [
                    'policy_uid' => (string) $rule['policy_uid'],
                    'policy_version' => (string) $rule['policy_version'],
                    'rule_uid' => (string) $rule['rule_uid'],
                    'operation' => $subject->operation->value,
                ],
                obligations: $this->obligations($match, $subject),
            );
        }

        return new PolicyDecision(
            action: $this->defaultAction,
            reasons: ['no_policy_rule_matched'],
            evidence: ['operation' => $subject->operation->value],
        );
    }

    /**
     * @param array<string, mixed> $match
     */
    private function matches(array $match, ExecutionContext $context, PolicySubject $subject): bool
    {
        if (!$this->matchesList($subject->operation->value, $match['operations'] ?? null)) {
            return false;
        }

        if (!$this->matchesList($subject->request->provider, $match['providers'] ?? null)) {
            return false;
        }

        if (!$this->matchesList($subject->request->model, $match['models'] ?? null)) {
            return false;
        }

        $rolesAny = $match['roles_any'] ?? null;
        if (is_array($rolesAny) && array_intersect($context->roles, $rolesAny) === []) {
            return false;
        }

        $maxInputChars = $match['max_input_chars'] ?? null;
        if (is_int($maxInputChars) && strlen($subject->request->input) > $maxInputChars) {
            return false;
        }

        return true;
    }

    private function matchesList(?string $actual, mixed $expected): bool
    {
        if (!is_array($expected) || $expected === []) {
            return true;
        }

        return $actual !== null && in_array($actual, $expected, true);
    }

    /**
     * @param array<string, mixed> $match
     * @return array<string, mixed>
     */
    private function obligations(array $match, PolicySubject $subject): array
    {
        $replacement = $match['redact_pattern'] ?? null;
        if (!is_string($replacement) || $replacement === '') {
            return [];
        }

        $redacted = preg_replace($replacement, '[REDACTED]', $subject->request->input);

        return is_string($redacted) ? ['redacted_input' => $redacted] : [];
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
