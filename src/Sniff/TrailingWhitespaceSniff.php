<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;

final class TrailingWhitespaceSniff extends AbstractSniff implements Fixable
{
    private const string TRAILING_WHITESPACE_PATTERN = '/[ \t]+$/';
    private const string MESSAGE = 'Trailing whitespace detected.';

    public static function getCode(): string
    {
        return 'DocbookCS.TrailingWhitespace';
    }

    public static function fixerClassName(): string
    {
        return TrailingWhitespaceFixer::class;
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];

        foreach (SourceLines::from($content) as $sourceLine) {
            if (
                !preg_match(
                    self::TRAILING_WHITESPACE_PATTERN,
                    $sourceLine['content'],
                    $matches,
                    PREG_OFFSET_CAPTURE,
                )
            ) {
                continue;
            }

            [$whitespace, $relativeOffset] = $matches[0];
            $beginOffset = $sourceLine['beginOffset'] + $relativeOffset;

            $violations[] = $this->createViolation(
                $filePath,
                $sourceLine['line'],
                $beginOffset,
                $beginOffset + strlen($whitespace),
                self::MESSAGE,
                $whitespace,
            );
        }

        return $violations;
    }
}
