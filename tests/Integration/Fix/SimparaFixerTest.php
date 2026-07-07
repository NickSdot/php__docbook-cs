<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Integration\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fix::class)]
#[CoversClass(FixApplier::class)]
#[CoversClass(FixResult::class)]
#[CoversClass(RunMode::class)]
#[CoversClass(SimparaFixer::class)]
#[CoversClass(SimparaSniff::class)]
#[CoversClass(Violation::class)]
final class SimparaFixerTest extends TestCase
{
    #[Test]
    public function itReplacesSimpleParaTags(): void
    {
        $content = '<root><para>Text <emphasis>inline</emphasis></para></root>';
        $document = $this->createDocument($content);

        $violations = new SimparaSniff(RunMode::Fix)->process($document, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame('<para>Text <emphasis>inline</emphasis></para>', $violations[0]->content);

        $fix = new SimparaFixer()->process($violations[0]);

        $result = new FixApplier()->apply($content, [$fix]);

        self::assertSame('<root><simpara>Text <emphasis>inline</emphasis></simpara></root>', $result->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itPreservesParaAttributes(): void
    {
        $content = '<root><para xml:id="example">Text</para></root>';
        $document = $this->createDocument($content);

        $violations = new SimparaSniff(RunMode::Fix)->process($document, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame('<para xml:id="example">Text</para>', $violations[0]->content);

        $fix = new SimparaFixer()->process($violations[0]);

        $result = new FixApplier()->apply($content, [$fix]);

        self::assertSame('<root><simpara xml:id="example">Text</simpara></root>', $result->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itCanFixNestedInnerParas(): void
    {
        $content = '<root><para>Text<note><para>Inner</para></note></para></root>';
        $document = $this->createDocument($content);

        $violations = new SimparaSniff(RunMode::Fix)->process($document, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame('<para>Inner</para>', $violations[0]->content);

        $fix = new SimparaFixer()->process($violations[0]);

        $result = new FixApplier()->apply($content, [$fix]);

        self::assertSame('<root><para>Text<note><simpara>Inner</simpara></note></para></root>', $result->content);
        self::assertSame(1, $result->applied);
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
