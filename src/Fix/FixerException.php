<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

final class FixerException extends \RuntimeException
{
    public static function cannotPersist(string $filePath): self
    {
        return new self(sprintf('Could not write fixed file: %s', $filePath));
    }

    public static function cannotFixMissingContent(): self
    {
        return new self('Violations cannot be content-less when passed to a fixer.');
    }

    public static function cannotReadFixedContent(): self
    {
        return new self('Cannot read fixed content when no fix application was attempted.');
    }
}
