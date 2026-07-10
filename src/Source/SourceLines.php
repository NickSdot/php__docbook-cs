<?php

declare(strict_types=1);

namespace DocbookCS\Source;

final class SourceLines
{
    /**
     * @return \Generator<int, array{
     *     line: int,
     *     content: string,
     *     beginOffset: int,
     *     untilOffset: int,
     *     endOffset: int
     * }>
     */
    public static function from(string $source): \Generator
    {
        $sourceLength = strlen($source);
        $beginOffset = 0;
        $line = 1;

        while ($beginOffset <= $sourceLength) {
            $lineLength = strcspn($source, "\r\n", $beginOffset);
            $untilOffset = $beginOffset + $lineLength;

            $lineEndingLength = 0;
            if ($untilOffset < $sourceLength) {
                $lineEndingLength = $source[$untilOffset] === "\r"
                    && ($source[$untilOffset + 1] ?? null) === "\n"
                        ? 2
                        : 1;
            }

            yield [
                'line' => $line,
                'content' => substr($source, $beginOffset, $lineLength),
                'beginOffset' => $beginOffset,
                'untilOffset' => $untilOffset,
                'endOffset' => $untilOffset + $lineEndingLength,
            ];

            if ($untilOffset === $sourceLength) {
                return;
            }

            $beginOffset = $untilOffset + $lineEndingLength;
            $line++;
        }
    }
}
