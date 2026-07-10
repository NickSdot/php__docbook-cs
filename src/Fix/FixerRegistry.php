<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Fix\Fixer\Fixer;
use DocbookCS\Fix\Fixer\MixedIndentationFixer;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
use DocbookCS\Fix\Fixer\WhitespaceFixer;
use DocbookCS\Sniff\AttributeOrderSniff;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\MixedIndentationSniff;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Sniff\TrailingWhitespaceSniff;
use DocbookCS\Sniff\WhitespaceSniff;

final readonly class FixerRegistry
{
    /** @param array<string, class-string<Fixer>> $fixerClasses */
    public function __construct(private array $fixerClasses)
    {
    }

    public static function defaults(): self
    {
        return new self([
            AttributeOrderSniff::getCode() => AttributeOrderFixer::class,
            ExceptionNameSniff::getCode() => ExceptionNameFixer::class,
            MixedIndentationSniff::getCode() => MixedIndentationFixer::class,
            SimparaSniff::getCode() => SimparaFixer::class,
            TrailingWhitespaceSniff::getCode() => TrailingWhitespaceFixer::class,
            WhitespaceSniff::getCode() => WhitespaceFixer::class,
        ]);
    }

    /** @return class-string<Fixer>|null */
    public function fixerClassFor(SniffInterface $sniff): ?string
    {
        return $this->fixerClasses[$sniff::getCode()]
            ?? ($sniff instanceof Fixable ? $sniff::fixerClassName() : null);
    }
}
