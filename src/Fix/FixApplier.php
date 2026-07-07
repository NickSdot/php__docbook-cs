<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

final class FixApplier
{
    /**
     * @param list<Fix> $fixes
     */
    public function apply(string $content, array $fixes): FixResult
    {
        if ($fixes === []) {
            return new FixResult($content);
        }

        usort(
            $fixes,
            static fn(Fix $a, Fix $b): int => $a->beginOffset <=> $b->beginOffset
        );

        $accepted = [];
        $lastUntil = -1;
        $skipped = 0;
        $length = strlen($content);

        foreach ($fixes as $fix) {
            if (
                $fix->beginOffset < 0
                || $fix->untilOffset < $fix->beginOffset
                || $fix->untilOffset > $length
                || $fix->beginOffset < $lastUntil
            ) {
                $skipped++;
                continue;
            }

            if (substr($content, $fix->beginOffset, $fix->untilOffset - $fix->beginOffset) === $fix->replacement) {
                $skipped++;
                continue;
            }

            $accepted[] = $fix;
            $lastUntil = $fix->untilOffset;
        }

        for ($i = count($accepted) - 1; $i >= 0; $i--) {
            $fix = $accepted[$i];

            $prefix = substr($content, 0, $fix->beginOffset);
            $suffix = substr($content, $fix->untilOffset);

            $content = "$prefix{$fix->replacement}$suffix";
        }

        return new FixResult(
            content: $content,
            applied: count($accepted),
            skipped: $skipped,
        );
    }
}
