<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

final class SourceLines
{
    /**
     * @return \Generator<int, array{
     *     line: int,
     *     content: string,
     *     beginOffset: int,
     *     untilOffset: int
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

            yield [
                'line' => $line,
                'content' => substr($source, $beginOffset, $lineLength),
                'beginOffset' => $beginOffset,
                'untilOffset' => $untilOffset,
            ];

            if ($untilOffset === $sourceLength) {
                return;
            }

            $lineEndingLength = $source[$untilOffset] === "\r"
                && ($source[$untilOffset + 1] ?? null) === "\n"
                    ? 2
                    : 1;

            $beginOffset = $untilOffset + $lineEndingLength;
            $line++;
        }
    }
}
