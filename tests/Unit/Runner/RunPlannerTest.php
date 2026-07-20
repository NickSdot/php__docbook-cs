<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\DiffProviderInterface;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Runner\RunPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunPlanner::class)]
#[CoversClass(RunPlan::class)]
#[UsesClass(DiffParser::class)]
final class RunPlannerTest extends TestCase
{
    #[Test]
    public function itUsesTheContributionDiffWhenNoInputIsProvided(): void
    {
        $config = new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [],
            excludePatterns: [],
            entityPaths: [],
            basePath: getcwd() ?: '.',
        );

        $diffProvider = $this->createMock(DiffProviderInterface::class);
        $diffProvider
            ->expects(self::once())
            ->method('for')
            ->willReturn(<<<'DIFF'
diff --git a/nonexistent.xml b/nonexistent.xml
--- a/nonexistent.xml
+++ b/nonexistent.xml
@@ -1 +1 @@
-old
+new
DIFF);

        $planner = new RunPlanner($config, diffProvider: $diffProvider);

        self::assertSame([], $planner->plan([], null)->targets);
    }
}
