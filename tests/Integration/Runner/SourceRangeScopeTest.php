<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Integration\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\SourceScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Source\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SimparaSniff::class)]
#[CoversClass(SourceScope::class)]
#[CoversClass(XmlFileProcessor::class)]
#[UsesClass(ViolationScopeFilter::class)]
final class SourceRangeScopeTest extends TestCase
{
    #[Test]
    public function itAppliesEveryRangeOfAViolationIntersectingAChangedLine(): void
    {
        $source = <<<'XML'
<root>
<para>
Text
</para>
</root>
XML;
        $expected = <<<'XML'
<root>
<simpara>
Text
</simpara>
</root>
XML;
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        file_put_contents($filePath, $source);

        try {
            $processor = new XmlFileProcessor([
                new SimparaSniff(RunMode::Fix),
            ]);

            $result = $processor->process(
                new File($filePath, $source),
                new FileChange($filePath, [3]),
            );
            file_put_contents($filePath, $result->fixedContent());
            $report = $result->fileReport;

            self::assertSame($expected, file_get_contents($filePath));
            self::assertFalse($report->hasViolations());
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itFixesAViolationCausedByADeletedLine(): void
    {
        $source = <<<'XML'
<root>
<para>
Text
</para>
</root>
XML;
        $expected = <<<'XML'
<root>
<simpara>
Text
</simpara>
</root>
XML;
        $processor = new XmlFileProcessor([
            new SimparaSniff(RunMode::Fix),
        ]);

        $result = $processor->process(
            new File('file.xml', $source),
            new FileChange('file.xml', [], deletionAnchors: [3]),
        );

        self::assertSame($expected, $result->fixedContent());
        self::assertFalse($result->fileReport->hasViolations());
    }
}
