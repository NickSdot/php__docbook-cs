<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Integration\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixResult;
use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Report\Violation;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\AttributeOrderSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeOrderFixer::class)]
#[CoversClass(AttributeOrderSniff::class)]
#[CoversClass(Fix::class)]
#[CoversClass(FixApplier::class)]
#[CoversClass(FixResult::class)]
#[CoversClass(RunMode::class)]
#[CoversClass(Violation::class)]
final class AttributeOrderFixerTest extends TestCase
{
    #[Test]
    public function itMovesXmlIdBeforeXmlns(): void
    {
        $content = '<root xmlns="urn:test" xml:id="root"/>';
        $document = $this->createDocument($content);

        $violations = new AttributeOrderSniff(RunMode::Fix)->process(
            $document,
            $content,
            'file.xml',
        );

        $beginOffset = (int) strpos($content, '<root');
        $sourceContent = '<root xmlns="urn:test" xml:id="root"/>';

        self::assertCount(1, $violations);
        self::assertSame($sourceContent, $violations[0]->content);
        self::assertSame($beginOffset, $violations[0]->beginOffset);
        self::assertSame($beginOffset + strlen($sourceContent), $violations[0]->untilOffset);
        self::assertSame(1, $violations[0]->line);

        $fix = new AttributeOrderFixer()->process($violations[0]);

        $result = new FixApplier()->apply($content, [$fix]);

        self::assertSame('<root xml:id="root" xmlns="urn:test"/>', $result->content);
        self::assertSame(1, $result->applied);
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
