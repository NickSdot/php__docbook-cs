<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Runner\XmlProcessingResult;
use DocbookCS\Source\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileReport::class)]
#[CoversClass(FixerException::class)]
#[CoversClass(File::class)]
#[CoversClass(XmlProcessingResult::class)]
final class XmlProcessingResultTest extends TestCase
{
    #[Test]
    public function itHasNoPendingFixesWithoutFixApplication(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root/>'),
            false,
        );

        self::assertFalse($result->hasPendingFixesToPersist());
    }

    #[Test]
    public function itHasPendingFixesWhenFixApplicationExists(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root/>'),
            true,
        );

        self::assertTrue($result->hasPendingFixesToPersist());
    }

    #[Test]
    public function itHasPendingFixesWhenFixesWereApplied(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root fixed="fixed"/>'),
            true,
        );

        self::assertTrue($result->hasPendingFixesToPersist());
    }

    #[Test]
    public function itThrowsWhenReadingFixedContentWithoutFixApplication(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root/>'),
            false,
        );

        $this->expectException(FixerException::class);
        $this->expectExceptionMessageIsOrContains('Cannot read fixed content when no fix application was attempted.');

        $result->fixedContent();
    }

    #[Test]
    public function itReturnsFixedContentWhenFixApplicationExists(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new File('input.xml', '<root fixed="fixed"/>'),
            true,
        );

        self::assertSame('<root fixed="fixed"/>', $result->fixedContent());
    }
}
