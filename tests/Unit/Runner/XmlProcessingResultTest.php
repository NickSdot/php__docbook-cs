<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Fix\FixerException;
use DocbookCS\Fix\FixResult;
use DocbookCS\Report\FileReport;
use DocbookCS\Runner\XmlProcessingResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileReport::class)]
#[CoversClass(FixerException::class)]
#[CoversClass(FixResult::class)]
#[CoversClass(XmlProcessingResult::class)]
final class XmlProcessingResultTest extends TestCase
{
    #[Test]
    public function itHasNoPendingFixesWithoutFixApplication(): void
    {
        $result = new XmlProcessingResult(new FileReport('input.xml'));

        self::assertFalse($result->hasPendingFixesToPersist());
    }

    #[Test]
    public function itHasPendingFixesWhenFixApplicationExists(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new FixResult('<root/>'),
        );

        self::assertTrue($result->hasPendingFixesToPersist());
    }

    #[Test]
    public function itHasPendingFixesWhenFixesWereApplied(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new FixResult('<root fixed="fixed"/>', applied: 1),
        );

        self::assertTrue($result->hasPendingFixesToPersist());
    }

    #[Test]
    public function itThrowsWhenReadingFixedContentWithoutFixApplication(): void
    {
        $result = new XmlProcessingResult(new FileReport('input.xml'));

        $this->expectException(FixerException::class);
        $this->expectExceptionMessageIsOrContains('Cannot read fixed content when no fix application was attempted.');

        $result->fixedContent();
    }

    #[Test]
    public function itReturnsFixedContentWhenFixApplicationExists(): void
    {
        $result = new XmlProcessingResult(
            new FileReport('input.xml'),
            new FixResult('<root fixed="fixed"/>', applied: 1),
        );

        self::assertSame('<root fixed="fixed"/>', $result->fixedContent());
    }
}
