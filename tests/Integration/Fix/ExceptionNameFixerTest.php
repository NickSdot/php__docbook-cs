<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Integration\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixResult;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Report\Violation;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\ExceptionNameSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExceptionNameFixer::class)]
#[CoversClass(ExceptionNameSniff::class)]
#[CoversClass(Fix::class)]
#[CoversClass(FixApplier::class)]
#[CoversClass(FixResult::class)]
#[CoversClass(RunMode::class)]
#[CoversClass(Violation::class)]
final class ExceptionNameFixerTest extends TestCase
{
    #[Test]
    public function itReplacesSimpleClassnameTags(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $document = $this->createDocument($content);

        $violations = new ExceptionNameSniff(RunMode::Fix)->process(
            $document,
            $content,
            'file.xml',
        );

        self::assertCount(1, $violations);
        self::assertSame('<classname>RuntimeException</classname>', $violations[0]->content);

        $fix = new ExceptionNameFixer()->process($violations[0]);

        $result = new FixApplier()->apply($content, [$fix]);

        self::assertSame('<root><exceptionname>RuntimeException</exceptionname></root>', $result->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itPreservesClassnameAttributes(): void
    {
        $content = '<root><classname linkend="runtime-exception">RuntimeException</classname></root>';
        $document = $this->createDocument($content);

        $violations = new ExceptionNameSniff(RunMode::Fix)->process(
            $document,
            $content,
            'file.xml',
        );

        self::assertCount(1, $violations);
        self::assertSame(
            '<classname linkend="runtime-exception">RuntimeException</classname>',
            $violations[0]->content,
        );

        $fix = new ExceptionNameFixer()->process($violations[0]);

        $result = new FixApplier()->apply($content, [$fix]);

        self::assertSame(
            '<root><exceptionname linkend="runtime-exception">RuntimeException</exceptionname></root>',
            $result->content,
        );
        self::assertSame(1, $result->applied);
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
