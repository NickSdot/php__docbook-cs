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
#[CoversClass(Violation::class)]
final class FixerTest extends TestCase
{
    #[Test]
    public function whitespaceFixerFixesOnlySniffedLines(): void
    {
        $content = "<root> \n \t<tag/>\n</root>";
        $document = $this->createDocument($content);

        $this->markTestIncomplete('Whitespace fixer is not wired in this pass.');

        // todo
        $result = (object) ['content' => 'foo', 'applied' => 1];

        self::assertSame("<root>\n  <tag/>\n</root>", $result->content);
        self::assertSame(2, $result->applied);
    }

    #[Test]
    public function attributeOrderFixerMovesXmlIdBeforeXmlns(): void
    {
        $content = '<root xmlns="urn:test" xml:id="root"/>';
        $document = $this->createDocument($content);

        $violations = new AttributeOrderSniff(RunMode::Fix)->process(
            $document,
            $content,
            'file.xml',
        );

        // fixer isn't yet applied
        self::assertCount(1, $violations);
        self::assertSame('<root xmlns="urn:test" xml:id="root"/>', $violations[0]->content);

        $fix = new AttributeOrderFixer()->process($violations[0]);

        self::assertInstanceOf(Fix::class, $fix);

        $result = new FixApplier()->apply($content, [$fix]);

        self::assertSame('<root xml:id="root" xmlns="urn:test"/>', $result->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function exceptionNameFixerReplacesSimpleClassnameTags(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $document = $this->createDocument($content);

        $this->markTestIncomplete('ExceptionName fixer is not wired in this pass.');

        // todo
        $result = (object) ['content' => 'foo', 'applied' => 1];

        self::assertSame('<root><exceptionname>RuntimeException</exceptionname></root>', $result->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function simparaFixerReplacesSimpleParaTags(): void
    {
        $content = '<root><para>Text <emphasis>inline</emphasis></para></root>';
        $document = $this->createDocument($content);

        $this->markTestIncomplete('Simpara fixer is not wired in this pass.');

        // todo
        $result = (object) ['content' => 'foo', 'applied' => 2];

        self::assertSame('<root><simpara>Text <emphasis>inline</emphasis></simpara></root>', $result->content);
        self::assertSame(2, $result->applied);
    }

    #[Test]
    public function simparaFixerPreservesParaAttributes(): void
    {
        $content = '<root><para xml:id="example">Text</para></root>';
        $document = $this->createDocument($content);

        $this->markTestIncomplete('Simpara fixer is not wired in this pass.');

        // todo
        $result = (object) ['content' => 'foo', 'applied' => 2];

        self::assertSame('<root><simpara xml:id="example">Text</simpara></root>', $result->content);
        self::assertSame(2, $result->applied);
    }

    #[Test]
    public function simparaFixerCanFixNestedInnerParas(): void
    {
        $content = '<root><para>Text<note><para>Inner</para></note></para></root>';
        $document = $this->createDocument($content);

        $this->markTestIncomplete('Simpara fixer is not wired in this pass.');

        // todo
        $result = (object) ['content' => 'foo', 'applied' => 2];

        self::assertSame('<root><para>Text<note><simpara>Inner</simpara></note></para></root>', $result->content);
        self::assertSame(2, $result->applied);
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
