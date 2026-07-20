<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\Diff;
use DocbookCS\Diff\FileChange;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Progress\ProgressInterface;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunOptions;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunCoordinator::class)]
#[CoversClass(ConfigData::class)]
#[CoversClass(PathLoader::class)]
#[CoversClass(PathMatcher::class)]
#[CoversClass(NullProgress::class)]
#[CoversClass(EntityPreprocessor::class)]
#[CoversClass(EntityResolver::class)]
#[CoversClass(RunMode::class)]
#[CoversClass(RunOptions::class)]
#[CoversClass(XmlFileProcessor::class)]
#[CoversClass(Report::class)]
#[CoversClass(SniffEntry::class)]
#[CoversClass(FileReport::class)]
#[CoversClass(Violation::class)]
final class SniffRunnerTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures/sniff_runner/default';

    /** @param list<SniffEntry> $sniffs */
    private function createConfig(array $sniffs = []): ConfigData
    {
        return new ConfigData(
            [],
            $sniffs,
            [self::FIXTURE_DIR],
            [],
            [],
            self::FIXTURE_DIR,
        );
    }

    #[Test]
    public function itProcessesFilesWithoutViolations(): void
    {
        $config = $this->createConfig();

        $runner = new RunCoordinator();
        $report = $runner->run($config);

        self::assertSame(2, $report->getFilesScanned());
        self::assertFalse($report->hasViolations());
        self::assertCount(0, $report->getFileReports());
    }

    #[Test]
    public function itUsesOverridePathsWhenProvided(): void
    {
        $config = $this->createConfig();

        $runner = new RunCoordinator();
        $report = $runner->run($config, new RunOptions(overridePaths: [self::FIXTURE_DIR . '/../override']));

        self::assertSame(1, $report->getFilesScanned());
    }

    #[Test]
    public function itCallsProgressMethods(): void
    {
        $progress = $this->createMock(ProgressInterface::class);

        $progress->expects($this->once())
            ->method('start')
            ->with(2);

        $progress->expects($this->exactly(2))
            ->method('advance');

        $progress->expects($this->once())
            ->method('finish');

        $config = $this->createConfig();

        $runner = new RunCoordinator($progress);
        $runner->run($config);
    }

    #[Test]
    public function itReportsFilesThatBecomeUnreadableBeforeProcessing(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        $xmlFilePath = $filePath . '.xml';
        rename($filePath, $xmlFilePath);
        file_put_contents($xmlFilePath, '<root/>');

        $progress = new class ($xmlFilePath) implements ProgressInterface {
            public function __construct(private string $filePath)
            {
            }

            public function start(int $totalFiles): void
            {
                @unlink($this->filePath);
            }

            public function advance(int $current, string $filePath, int $violations): void
            {
            }

            public function finish(): void
            {
            }
        };
        $config = new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [$xmlFilePath],
            excludePatterns: [],
            entityPaths: [],
            basePath: dirname($xmlFilePath),
        );

        $report = new RunCoordinator($progress)->run($config);

        self::assertTrue($report->hasViolations());
        self::assertSame('DocbookCS.Internal', $report->getAllViolations()[0]->sniffCode);
        self::assertStringContainsString('Could not read file', $report->getAllViolations()[0]->message);
    }

    #[Test]
    public function itKeepsUnreadableFileErrorsInDiffRuns(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        $xmlFilePath = $filePath . '.xml';
        rename($filePath, $xmlFilePath);
        file_put_contents($xmlFilePath, '<root/>');

        $progress = $this->createMock(ProgressInterface::class);
        $progress->expects($this->once())->method('start')->willReturnCallback(
            static function () use ($xmlFilePath): void {
                @unlink($xmlFilePath);
            },
        );
        $progress->expects($this->once())->method('advance');
        $progress->expects($this->once())->method('finish');
        $config = new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [$xmlFilePath],
            excludePatterns: [],
            entityPaths: [],
            basePath: dirname($xmlFilePath),
        );
        $diff = new Diff([new FileChange($xmlFilePath, [42])]);

        $report = new RunCoordinator($progress)->run($config, new RunOptions(diff: $diff));

        self::assertTrue($report->hasViolations());
        self::assertSame('DocbookCS.Internal', $report->getAllViolations()[0]->sniffCode);
    }

    #[Test]
    public function itAddsFileReportsForFilesWithViolations(): void
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.ViolatingSniff';
            }

            public function process(\DOMDocument $document, File $source): array
            {
                return [
                    new Violation(
                        sniffCode: 'Test.ViolatingSniff',
                        filePath: $source->path,
                        line: 1,
                        beginOffset: 0,
                        untilOffset: 0,
                        message: 'Test violation message',
                        severity: Severity::WARNING,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniff::class)]);

        $runner = new RunCoordinator();
        $report = $runner->run($config);

        self::assertSame(2, $report->getFilesScanned());
        self::assertCount(2, $report->getFileReports());
        self::assertTrue($report->hasViolations());
    }

    #[Test]
    public function itStoresAbsolutePathsInFileReports(): void
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.ViolatingSniff';
            }

            public function process(\DOMDocument $document, File $source): array
            {
                return [
                    new Violation(
                        sniffCode: 'Test.ViolatingSniff',
                        filePath: $source->path,
                        line: 1,
                        beginOffset: 0,
                        untilOffset: 0,
                        message: 'Test violation',
                        severity: Severity::WARNING,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniff::class)]);

        $runner = new RunCoordinator();
        $report = $runner->run($config);

        foreach ($report->getFileReports() as $fileReport) {
            self::assertTrue(
                str_starts_with($fileReport->filePath, '/'),
                'Expected absolute path, got: ' . $fileReport->filePath,
            );
        }
    }

    #[Test]
    public function itPassesPropertiesToSniffs(): void
    {
        $sniffClass = new class (RunMode::Sniff) implements SniffInterface {
            public static string $captured = '';
            public static RunMode $capturedMode = RunMode::Sniff;

            public function __construct(public RunMode $mode)
            {
                self::$capturedMode = $mode;
            }

            public function setProperty(string $name, string $value): void
            {
                self::$captured = $value;
            }

            public static function getCode(): string
            {
                return 'Test.ConfigurableSniff';
            }

            public function process(\DOMDocument $document, File $source): array
            {
                return [];
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniffClass::class, ['someProp' => 'someValue'])]);

        $runner = new RunCoordinator();
        $runner->run($config, new RunOptions(mode: RunMode::Fix));

        self::assertSame('someValue', $sniffClass::$captured);
        self::assertSame(RunMode::Fix, $sniffClass::$capturedMode);
    }

    #[Test]
    public function itThrowsWhenSniffClassDoesNotExist(): void
    {
        $config = $this->createConfig(sniffs: [new SniffEntry('NonExistent\\FakeSniff')]);

        $runner = new RunCoordinator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('does not exist');

        $runner->run($config);
    }

    #[Test]
    public function itThrowsWhenClassDoesNotImplementSniffInterface(): void
    {
        $config = $this->createConfig(sniffs: [new SniffEntry(\stdClass::class)]);

        $runner = new RunCoordinator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('does not implement');

        $runner->run($config);
    }

    #[Test]
    public function itFiltersFilesToOnlyThoseInTheDiff(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $diff = new Diff([new FileChange('sniff_runner/default/file_a.xml', [1])]);
        $report = $runner->run($config, new RunOptions(diff: $diff));

        self::assertSame(1, $report->getFilesScanned());
    }

    #[Test]
    public function itScansNoFilesWhenDiffContainsNoMatchingPaths(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $diff = new Diff([new FileChange('completely/different/file.xml', [1, 2, 3])]);
        $report = $runner->run($config, new RunOptions(diff: $diff));

        self::assertSame(0, $report->getFilesScanned());
    }

    #[Test]
    public function itMatchesWhenDiffPathEqualsDiscoveredPath(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $discoveredPath = self::FIXTURE_DIR . '/file_a.xml';

        $diff = new Diff([new FileChange($discoveredPath, [1])]);
        $report = $runner->run($config, new RunOptions(diff: $diff));

        self::assertSame(1, $report->getFilesScanned());
    }

    #[Test]
    public function itScansAllFilesWhenNoDiffIsGiven(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $report = $runner->run($config);

        self::assertSame(2, $report->getFilesScanned());
    }

    #[Test]
    public function itReportsNoViolationsForFilesInDiffWithoutAddedLines(): void
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.ViolatingSniff';
            }

            public function process(\DOMDocument $document, File $source): array
            {
                return [
                    new Violation(
                        sniffCode: 'Test.ViolatingSniff',
                        filePath: $source->path,
                        line: 1,
                        beginOffset: 0,
                        untilOffset: 0,
                        message: 'Test violation',
                        severity: Severity::WARNING,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniff::class)]);
        $runner = new RunCoordinator();

        $diff = new Diff([new FileChange('sniff_runner/default/file_a.xml', [])]);
        $report = $runner->run($config, new RunOptions(diff: $diff));

        self::assertSame(1, $report->getFilesScanned());
        self::assertFalse($report->hasViolations());
    }
}
