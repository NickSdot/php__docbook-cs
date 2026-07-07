<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Report\Violation;
use DocbookCS\Sniff\ExceptionNameSniff;

final class ExceptionNameFixer implements Fixer
{
    private const string CLASSNAME_PATTERN = '/^<classname\b([^>]*)>([^<]*)<\/classname>$/';
    private const string EXCEPTION_NAME_FORMAT = '<exceptionname%s>%s</exceptionname>';

    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (!preg_match(self::CLASSNAME_PATTERN, $violation->content, $matches)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        $text = trim($matches[2]);

        if ($text === '' || !ExceptionNameSniff::looksLikeException($text)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            $violation->filePath,
            $violation->beginOffset,
            $violation->untilOffset,
            sprintf(self::EXCEPTION_NAME_FORMAT, $matches[1], $matches[2]),
            $violation->sniffCode,
            $violation->line,
        );
    }
}
