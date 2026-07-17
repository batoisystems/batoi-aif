<?php

declare(strict_types=1);

namespace Batoi\Aif\Tests;

use Batoi\Aif\Aif;
use PHPUnit\Framework\TestCase;

final class AifVersionTest extends TestCase
{
    public function testPublicVersionMatchesRelease(): void
    {
        self::assertSame('1.0.1', Aif::VERSION);
    }
}
