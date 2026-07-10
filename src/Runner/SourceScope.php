<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\Fix;
use DocbookCS\Source\SourceLines;
use DocbookCS\Violation\Violation;

final readonly class SourceScope
{
    /**
     * Null means the whole source file; an empty list means no source range.
     *
     * @param list<array{int, int}>|null $ranges
     */
    private function __construct(private ?array $ranges)
    {
    }

    public static function wholeFile(): self
    {
        return new self(null);
    }

    /** @param list<int> $lines */
    public static function changedLines(string $source, array $lines): self
    {
        $selectedLines = array_fill_keys($lines, true);
        $ranges = [];

        foreach (SourceLines::from($source) as $sourceLine) {
            if (!isset($selectedLines[$sourceLine['line']])) {
                continue;
            }

            self::appendRange(
                $ranges,
                $sourceLine['beginOffset'],
                $sourceLine['endOffset'],
            );
        }

        return new self($ranges);
    }

    public function includes(Violation $violation): bool
    {
        if ($this->ranges === null) {
            return true;
        }

        foreach ($this->ranges as [$beginOffset, $untilOffset]) {
            if (
                self::overlaps(
                    $beginOffset,
                    $untilOffset,
                    $violation->beginOffset,
                    $violation->untilOffset,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Fix> $fixes */
    public function after(array $fixes): self
    {
        if ($this->ranges === null || $this->ranges === [] || $fixes === []) {
            return $this;
        }

        usort(
            $fixes,
            static fn(Fix $a, Fix $b): int => [
                $a->beginOffset,
                $a->untilOffset,
            ] <=> [
                $b->beginOffset,
                $b->untilOffset,
            ],
        );

        $ranges = [];
        foreach ($this->ranges as [$beginOffset, $untilOffset]) {
            self::appendRange(
                $ranges,
                self::mapOffset($beginOffset, $fixes, endBoundary: false),
                self::mapOffset(
                    $untilOffset,
                    $fixes,
                    endBoundary: true,
                    includeInsertionAtOffset: $beginOffset === $untilOffset,
                ),
            );
        }

        return new self($ranges);
    }

    /**
     * @param list<array{int, int}> $ranges
     */
    private static function appendRange(array &$ranges, int $beginOffset, int $untilOffset): void
    {
        $lastIndex = count($ranges) - 1;
        if ($lastIndex >= 0 && $beginOffset <= $ranges[$lastIndex][1]) {
            $ranges[$lastIndex][1] = max($ranges[$lastIndex][1], $untilOffset);
            return;
        }

        $ranges[] = [$beginOffset, $untilOffset];
    }

    /** @param list<Fix> $fixes */
    private static function mapOffset(
        int $offset,
        array $fixes,
        bool $endBoundary,
        bool $includeInsertionAtOffset = false,
    ): int {
        $shift = 0;

        foreach ($fixes as $fix) {
            $isInsertion = $fix->beginOffset === $fix->untilOffset;

            if (
                $fix->untilOffset < $offset
                || (!$isInsertion && $fix->untilOffset === $offset)
                || ($includeInsertionAtOffset && $isInsertion && $fix->beginOffset === $offset)
            ) {
                $shift += self::delta($fix);
                continue;
            }

            if ($fix->beginOffset < $offset && $fix->untilOffset > $offset) {
                return $fix->beginOffset
                    + $shift
                    + ($endBoundary ? strlen($fix->replacement) : 0);
            }

            if ($fix->beginOffset >= $offset) {
                break;
            }
        }

        return $offset + $shift;
    }

    private static function delta(Fix $fix): int
    {
        return strlen($fix->replacement) - ($fix->untilOffset - $fix->beginOffset);
    }

    private static function overlaps(
        int $aBegin,
        int $aUntil,
        int $bBegin,
        int $bUntil,
    ): bool {
        if ($aBegin === $aUntil) {
            return $bBegin === $bUntil
                ? $aBegin === $bBegin
                : $aBegin >= $bBegin && $aBegin < $bUntil;
        }

        if ($bBegin === $bUntil) {
            return $bBegin >= $aBegin && $bBegin < $aUntil;
        }

        return max($aBegin, $bBegin) < min($aUntil, $bUntil);
    }
}
