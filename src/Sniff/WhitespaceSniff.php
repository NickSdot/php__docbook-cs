<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\WhitespaceFixer;

/**
 * Detects whitespace and indentation issues in DocBook source files.
 *
 * The following violations are detected:
 * - Trailing whitespace at the end of a line
 * - Spaces used before tabs in indentation
 * - Mixed use of tabs and spaces within indentation
 */
final class WhitespaceSniff extends AbstractSniff implements Fixable
{
    private const string LINE_ENDING_PATTERN = '/(\r\n|\n|\r)/';
    private const string WHITESPACE_PATTERN = '/([ \t]+$)|^(\t* +\t+|\t+ +\t*)|^( +)\t/';

    public static function getCode(): string
    {
        return 'DocbookCS.Whitespace';
    }

    public static function fixerClassName(): string
    {
        return WhitespaceFixer::class;
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        $offset = 0;
        $lineNo = 1;

        $lines = preg_split(self::LINE_ENDING_PATTERN, $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($lines === false) {
            throw new \LogicException('Could not split source content into lines.'); // @codeCoverageIgnore
        }

        for ($i = 0; $i < count($lines); $i += 2) {
            $line = $lines[$i];
            $lineEnding = $lines[$i + 1] ?? '';

            if (preg_match(self::WHITESPACE_PATTERN, $line, $matches)) {
                $message = match (true) {
                    !empty($matches[1]) => 'Trailing whitespace detected.',
                    !empty($matches[2]) || !empty($matches[3]) => 'Mixed tabs and spaces in indentation.',
                    default => 'Inconsistent indentation.', // @codeCoverageIgnore
                };

                $violations[] = $this->createViolation(
                    $filePath,
                    $lineNo,
                    $message,
                    beginOffset: $offset,
                    untilOffset: $offset + strlen($line),
                    content: $line,
                );
            }

            $offset += strlen($line) + strlen($lineEnding);
            $lineNo++;
        }

        return $violations;
    }
}
