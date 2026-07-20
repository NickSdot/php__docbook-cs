<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Diff;

use DocbookCS\Diff\GitDiffProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GitDiffProvider::class)]
final class GitDiffProviderTest extends TestCase
{
    #[Test]
    public function itDiffsTheWorkingTreeFromTheUpstreamBranchPoint(): void
    {
        $commands = [];
        $provider = new GitDiffProvider(
            static function (array $command, string $directory) use (&$commands): array {
                $commands[] = [$command, $directory];

                return match ($command) {
                    ['git', 'rev-parse', '--show-toplevel'] => [
                        'exitCode' => 0,
                        'stdout' => "/repo\n",
                        'stderr' => '',
                    ],
                    ['git', 'symbolic-ref', '--quiet', 'refs/remotes/upstream/HEAD'] => [
                        'exitCode' => 0,
                        'stdout' => "refs/remotes/upstream/main\n",
                        'stderr' => '',
                    ],
                    ['git', 'rev-parse', '--verify', '--quiet', 'refs/remotes/upstream/main^{commit}'] => [
                        'exitCode' => 0,
                        'stdout' => "upstream-sha\n",
                        'stderr' => '',
                    ],
                    ['git', 'merge-base', 'HEAD', 'refs/remotes/upstream/main'] => [
                        'exitCode' => 0,
                        'stdout' => "base-sha\n",
                        'stderr' => '',
                    ],
                    ['git', 'diff', '--no-ext-diff', '--no-color', 'base-sha', '--'] => [
                        'exitCode' => 0,
                        'stdout' => "diff content\n",
                        'stderr' => '',
                    ],
                    default => ['exitCode' => 1, 'stdout' => '', 'stderr' => 'unexpected'],
                };
            },
        );

        self::assertSame("diff content\n", $provider->for('/work'));
        $lastCommand = array_pop($commands);
        self::assertIsArray($lastCommand);
        self::assertSame(
            [
                ['git', 'diff', '--no-ext-diff', '--no-color', 'base-sha', '--'],
                '/repo',
            ],
            $lastCommand,
        );
    }

    #[Test]
    public function itFailsClearlyWhenNoUpstreamDefaultBranchCanBeFound(): void
    {
        $provider = new GitDiffProvider(
            static fn(array $command, string $directory): array => match ($command) {
                ['git', 'rev-parse', '--show-toplevel'] => [
                    'exitCode' => 0,
                    'stdout' => "/repo\n",
                    'stderr' => '',
                ],
                default => ['exitCode' => 1, 'stdout' => '', 'stderr' => ''],
            },
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not determine the upstream default branch');

        $provider->for('/work');
    }
}
