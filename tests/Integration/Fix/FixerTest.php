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

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
