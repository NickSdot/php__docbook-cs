<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

final readonly class FixResult
{
    /** @var list<Fix> */
    public array $appliedFixes;

    /** @param list<Fix> $appliedFixes */
    public function __construct(
        public string $content,
        public int $applied = 0,
        public int $skipped = 0,
        array $appliedFixes = [],
    ) {
        $this->appliedFixes = $appliedFixes;
    }
}
