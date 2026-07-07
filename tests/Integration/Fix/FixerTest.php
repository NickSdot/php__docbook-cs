<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Integration\Fix;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
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
