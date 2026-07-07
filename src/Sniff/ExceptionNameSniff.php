<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\ExceptionNameFixer;

/**
 * Detects exception/error class names wrapped in <classname> that
 * should use <exceptionname> instead.
 *
 * DocBook provides <exceptionname> specifically for "the name of an
 * exception." When a <classname> element's text content matches a
 * known exception/error pattern, this sniff flags it.
 */
final class ExceptionNameSniff extends AbstractSniff implements Fixable
{
    /**
     * Default suffixes that indicate the class is an exception or error.
     * @var list<string>
     */
    private const array DEFAULT_SUFFIXES = [
        'Exception',
        'Error',
        'Throwable',
    ];

    private const string CLASSNAME_PATTERN = '/<classname\b[^>]*>([^<]*)<\/classname>/';

    public static function getCode(): string
    {
        return 'DocbookCS.ExceptionName';
    }

    public static function fixerClassName(): string
    {
        return ExceptionNameFixer::class;
    }

    /** @throws \LogicException */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        $sourceMatchIndex = 0;
        $isFixMode = $this->mode->isFixMode();

        $classnames = $document->getElementsByTagName('classname');
        if ($classnames->length === 0) {
            return [];
        }

        $sourceMatches = $isFixMode
            ? $this->sourceMatches($content)
            : [];

        /** @var \DOMElement $node */
        foreach ($classnames as $node) {
            $text = trim($node->textContent);
            $match = null;

            if ($isFixMode) {
                $match = $sourceMatches[$sourceMatchIndex] ?? null;
                $sourceMatchIndex++;
            }

            if ($text === '') {
                continue;
            }

            if ($node->parentNode instanceof \DOMElement && $node->parentNode->localName === 'ooclass') {
                continue;
            }

            if (!self::looksLikeException($text)) {
                continue;
            }

            if ($isFixMode && ($match === null || $match['text'] !== $text)) {
                throw new \LogicException('Could not map classname violation to source content.');
            }

            $violations[] = $this->createViolation(
                $filePath,
                $node->getLineNo(),
                sprintf(
                    '"%s" is wrapped in <classname> but should use <exceptionname>.',
                    $text,
                ),
                beginOffset: $match['beginOffset'] ?? 0,
                untilOffset: $match['untilOffset'] ?? 0,
                content: $match['content'] ?? null,
            );
        }

        return $violations;
    }

    public static function looksLikeException(string $text): bool
    {
        $parts = explode('\\', $text);
        $baseName = end($parts);

        return array_any(
            self::DEFAULT_SUFFIXES,
            static fn(string $suffix): bool => str_ends_with($baseName, $suffix),
        );
    }

    /**
     * @return list<array{beginOffset: int, untilOffset: int, content: string, text: string}>
     */
    private function sourceMatches(string $content): array
    {
        preg_match_all(self::CLASSNAME_PATTERN, $content, $matches, PREG_OFFSET_CAPTURE);

        $sourceMatches = [];
        foreach ($matches[0] as $i => [$fullMatch, $offset]) {
            $sourceMatches[] = [
                'beginOffset' => (int) $offset,
                'untilOffset' => (int) $offset + strlen($fullMatch),
                'content' => $fullMatch,
                'text' => trim($matches[1][$i][0]),
            ];
        }

        return $sourceMatches;
    }
}
