<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests\Audit;

use Batoi\Aif\Audit\KeyNameSensitiveDataClassifier;
use PHPUnit\Framework\TestCase;

final class SensitiveDataClassifierTest extends TestCase
{
    public function testItReportsLabelsAndPathsWithoutSensitiveValues(): void
    {
        $classification = (new KeyNameSensitiveDataClassifier())->classify([
            'variables' => [
                'contact_email' => 'person@example.test',
                'api_key' => 'must-not-appear',
            ],
        ]);
        $evidence = $classification->evidence();

        self::assertSame(['personal_data', 'credential'], $evidence['labels']);
        self::assertSame(['variables.contact_email', 'variables.api_key'], $evidence['paths']);
        self::assertStringNotContainsString('must-not-appear', json_encode($evidence, JSON_THROW_ON_ERROR));
    }
}
