<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Integration\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunOptions;
use DocbookCS\Runner\RunScopeResolver;
use DocbookCS\Sniff\SimparaSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityResolver::class)]
#[CoversClass(PathLoader::class)]
#[CoversClass(PathMatcher::class)]
#[CoversClass(RunCoordinator::class)]
#[CoversClass(RunScopeResolver::class)]
final class RunScopeTest extends TestCase
{
    private string $directory;
    private string $sourceFile;
    private string $targetFile;
    private string $entityFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/docbook-cs-run-scope-' . bin2hex(random_bytes(6));
        mkdir($this->directory);

        $this->sourceFile = $this->directory . '/source.xml';
        $this->targetFile = $this->directory . '/target.xml';
        $this->entityFile = $this->directory . '/entities.ent';

        file_put_contents($this->sourceFile, '<root>&target;</root>');
        file_put_contents($this->targetFile, '<target/>');
        file_put_contents($this->entityFile, '<!ENTITY target SYSTEM "target.xml">');
    }

    protected function tearDown(): void
    {
        @unlink($this->sourceFile);
        @unlink($this->targetFile);
        @unlink($this->entityFile);
        @rmdir($this->directory);
    }

    #[Test]
    public function itExpandsReferencedTargetsUnlessStrictScopeIsRequested(): void
    {
        $runner = new RunCoordinator();

        self::assertSame(2, $runner->run($this->config())->getFilesScanned());
        self::assertSame(
            1,
            $runner->run($this->config(), new RunOptions(strict: true))->getFilesScanned(),
        );
    }

    #[Test]
    public function itFixesExpandedXmlInItsTargetFileOnly(): void
    {
        file_put_contents($this->targetFile, '<para>Text</para>');
        $config = $this->config([
            new SniffEntry(SimparaSniff::class),
        ]);

        $report = new RunCoordinator()->run($config, new RunOptions(mode: RunMode::Fix));

        self::assertSame('<root>&target;</root>', file_get_contents($this->sourceFile));
        self::assertSame('<simpara>Text</simpara>', file_get_contents($this->targetFile));
        self::assertFalse($report->hasViolations());
    }

    /** @param list<SniffEntry> $sniffs */
    private function config(array $sniffs = []): ConfigData
    {
        return new ConfigData(
            projectRoots: [],
            sniffs: $sniffs,
            includePaths: [$this->sourceFile],
            excludePatterns: [],
            entityPaths: [$this->entityFile],
            basePath: $this->directory,
        );
    }
}
