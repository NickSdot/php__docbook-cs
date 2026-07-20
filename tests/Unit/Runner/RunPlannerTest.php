<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\DiffParser;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Runner\RunPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunPlanner::class)]
#[CoversClass(RunPlan::class)]
#[UsesClass(DiffParser::class)]
#[UsesClass(GitDiffProvider::class)]
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
        $planner = new RunPlanner(
            $config,
            gitDiffProvider: $this->gitDiffProvider(<<<'DIFF'
diff --git a/nonexistent.xml b/nonexistent.xml
--- a/nonexistent.xml
+++ b/nonexistent.xml
@@ -1 +1 @@
-old
+new
DIFF),
        );

        self::assertSame([], $planner->plan([], null)->targets);
    }

    private function gitDiffProvider(string $diff): GitDiffProvider
    {
        return new GitDiffProvider(
            static fn(array $command, string $directory): array => match (true) {
                $command === ['git', 'rev-parse', '--show-toplevel'] => [
                    'exitCode' => 0,
                    'stdout' => $directory . "\n",
                    'stderr' => '',
                ],
                $command === ['git', 'symbolic-ref', '--quiet', 'refs/remotes/upstream/HEAD'] => [
                    'exitCode' => 0,
                    'stdout' => "refs/remotes/upstream/main\n",
                    'stderr' => '',
                ],
                $command === [
                    'git',
                    'rev-parse',
                    '--verify',
                    '--quiet',
                    'refs/remotes/upstream/main^{commit}',
                ] => [
                    'exitCode' => 0,
                    'stdout' => "upstream-sha\n",
                    'stderr' => '',
                ],
                $command === ['git', 'merge-base', 'HEAD', 'refs/remotes/upstream/main'] => [
                    'exitCode' => 0,
                    'stdout' => "base-sha\n",
                    'stderr' => '',
                ],
                $command === [
                    'git',
                    'diff',
                    '--no-ext-diff',
                    '--no-color',
                    'base-sha',
                    '--',
                ] => [
                    'exitCode' => 0,
                    'stdout' => $diff,
                    'stderr' => '',
                ],
                default => ['exitCode' => 1, 'stdout' => '', 'stderr' => 'unexpected command'],
            },
        );
    }
}
