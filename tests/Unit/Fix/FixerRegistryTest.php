<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Fix\Fixer\MixedIndentationFixer;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
use DocbookCS\Fix\Fixer\WhitespaceFixer;
use DocbookCS\Fix\FixerRegistry;
use DocbookCS\Runner\RunMode;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\AttributeOrderSniff;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\MixedIndentationSniff;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Sniff\TrailingWhitespaceSniff;
use DocbookCS\Sniff\WhitespaceSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixerRegistry::class)]
final class FixerRegistryTest extends TestCase
{
    #[Test]
    public function itOwnsEveryBuiltInSniffToFixerMapping(): void
    {
        $registry = FixerRegistry::defaults();
        $mappings = [
            [new AttributeOrderSniff(), AttributeOrderFixer::class],
            [new ExceptionNameSniff(), ExceptionNameFixer::class],
            [new MixedIndentationSniff(), MixedIndentationFixer::class],
            [new SimparaSniff(), SimparaFixer::class],
            [new TrailingWhitespaceSniff(), TrailingWhitespaceFixer::class],
            [new WhitespaceSniff(), WhitespaceFixer::class],
        ];

        foreach ($mappings as [$sniff, $fixerClass]) {
            self::assertSame($fixerClass, $registry->fixerClassFor($sniff));
        }
    }

    #[Test]
    public function itAcceptsExplicitCustomMappings(): void
    {
        $sniff = new class (RunMode::Sniff) extends AbstractSniff {
            public static function getCode(): string
            {
                return 'Custom.Sniff';
            }

            public function process(\DOMDocument $document, string $content, string $filePath): array
            {
                return [];
            }
        };
        $registry = new FixerRegistry([
            $sniff::getCode() => AttributeOrderFixer::class,
        ]);

        self::assertSame(AttributeOrderFixer::class, $registry->fixerClassFor($sniff));
    }

    #[Test]
    public function itSupportsLegacySelfDescribingSniffs(): void
    {
        $sniff = new class (RunMode::Sniff) extends AbstractSniff implements Fixable {
            public static function getCode(): string
            {
                return 'Legacy.Sniff';
            }

            public static function fixerClassName(): string
            {
                return AttributeOrderFixer::class;
            }

            public function process(\DOMDocument $document, string $content, string $filePath): array
            {
                return [];
            }
        };

        self::assertSame(
            AttributeOrderFixer::class,
            new FixerRegistry([])->fixerClassFor($sniff),
        );
    }
}
