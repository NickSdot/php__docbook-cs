<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixTarget;

interface Fixer
{
    public function process(FixTarget $target): ?Fix;
}
