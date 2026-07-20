<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Diff\Diff;

final readonly class RunOptions
{
    /**
     * @param list<string>|null $overridePaths
     */
    public function __construct(
        public RunMode $mode = RunMode::Sniff,
        public ?array $overridePaths = null,
        public ?Diff $diff = null,
        public bool $strict = false,
    ) {
    }
}
