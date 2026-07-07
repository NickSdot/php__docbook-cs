<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class SimparaFixer implements Fixer
{
    private const string PARA_PATTERN = '/^<para\b([^>]*)>(.*)<\/para>$/s';
    private const string SIMPARA_FORMAT = '<simpara%s>%s</simpara>';

    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (!preg_match(self::PARA_PATTERN, $violation->content, $matches)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            $violation->filePath,
            $violation->beginOffset,
            $violation->untilOffset,
            sprintf(self::SIMPARA_FORMAT, $matches[1], $matches[2]),
            $violation->sniffCode,
            $violation->line,
        );
    }
}
