<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Path\PathMatcher;
use DocbookCS\Runner\RunScopeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathMatcher::class)]
#[CoversClass(RunScopeResolver::class)]
final class RunScopeResolverTest extends TestCase
{
    private string $directory;
    private string $sourceFile;
    private string $targetFile;
    private string $entityFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/docbook-cs-scope-' . bin2hex(random_bytes(6));
        mkdir($this->directory);

        $this->sourceFile = $this->directory . '/source.xml';
        $this->targetFile = $this->directory . '/target.xml';
        $this->entityFile = $this->directory . '/bridge.ent';

        file_put_contents($this->sourceFile, '<root>&bridge;</root>');
        file_put_contents($this->targetFile, '<target/>');
        file_put_contents($this->entityFile, '&target;');
    }

    protected function tearDown(): void
    {
        @unlink($this->sourceFile);
        @unlink($this->targetFile);
        @unlink($this->entityFile);
        @rmdir($this->directory);
    }

    #[Test]
    public function strictScopeKeepsOnlySelectedFilesAndDiffLines(): void
    {
        $resolver = $this->resolver();

        $targets = $resolver->resolve(
            [$this->sourceFile],
            ['source.xml' => [2, 3]],
            strict: true,
        );

        self::assertSame([$this->sourceFile => [2, 3]], $targets);
    }

    #[Test]
    public function expandedScopeFollowsReferencedTargetsWithoutWideningDiffLines(): void
    {
        $resolver = $this->resolver();

        $targets = $resolver->resolve(
            [$this->sourceFile],
            ['source.xml' => [2, 3]],
            strict: false,
        );

        self::assertSame([2, 3], $targets[$this->sourceFile]);
        self::assertNull($targets[$this->targetFile]);
        self::assertCount(2, $targets);
    }

    #[Test]
    public function expandedScopeHonorsTargetExclusions(): void
    {
        $resolver = new RunScopeResolver(
            new PathMatcher($this->directory, ['target.xml']),
            [
                'bridge' => $this->entityFile,
                'target' => $this->targetFile,
            ],
        );

        $targets = $resolver->resolve([$this->sourceFile], null, strict: false);

        self::assertSame([$this->sourceFile => null], $targets);
    }

    private function resolver(): RunScopeResolver
    {
        return new RunScopeResolver(
            new PathMatcher($this->directory, []),
            [
                'bridge' => $this->entityFile,
                'target' => $this->targetFile,
            ],
        );
    }
}
