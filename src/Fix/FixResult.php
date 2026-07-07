<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

final readonly class FixResult
{
    public function __construct(
        public string $content,
        public int $applied = 0,
        public int $skipped = 0,
    ) {
    }
}
