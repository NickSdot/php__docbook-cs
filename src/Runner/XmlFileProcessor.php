<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

final readonly class XmlFileProcessor
{
    /** @var list<SniffInterface> */
    private array $sniffs;

    private EntityPreprocessor $preprocessor;

    private Report $report;

    /** @param list<SniffInterface> $sniffs */
    public function __construct(
        array $sniffs,
        ?EntityPreprocessor $preprocessor = null,
        ?Report $report = null,
    ) {
        $this->sniffs = $sniffs;
        $this->preprocessor = $preprocessor ?? new EntityPreprocessor([]);
        $this->report = $report ?? new Report();
    }

    /**
     * @param list<int>|null $changedLines
     * @throws FixerException
     */
    public function processFile(string $filePath, ?array $changedLines = null): FileReport
    {
        $fileReport = new FileReport($filePath);

        $content = @file_get_contents($filePath);
        if ($content === false) {
            $fileReport->addViolation(new Violation(
                sniffCode: 'DocbookCS.Internal',
                filePath: $fileReport->filePath,
                line: 0,
                beginOffset: 0,
                untilOffset: 0,
                message: 'Could not read file.',
                severity: Severity::ERROR,
            ));
            return $fileReport;
        }

        $result = $this->processContent($content, $fileReport->filePath, $fileReport, $changedLines);

        if ($result->hasPendingFixesToPersist() && @file_put_contents($filePath, $result->fixedContent()) === false) {
            throw FixerException::cannotPersist($filePath);
        }

        return $fileReport;
    }

    /**
     * Testing Harvest
     * @param list<int>|null $changedLines
     * @throws FixerException
     */
    public function processString(
        string $xmlContent,
        string $pseudoPath = 'input.xml',
        ?array $changedLines = null,
    ): FileReport {
        $fileReport = new FileReport($pseudoPath);

        $this->processContent($xmlContent, $pseudoPath, $fileReport, $changedLines);

        return $fileReport;
    }

    /**
     * @param list<int>|null $changedLines
     * @throws FixerException
     */
    private function processContent(
        string $sourceContent,
        string $filePath,
        FileReport $fileReport,
        ?array $changedLines = null,
    ): XmlProcessingResult {
        $processedContent = $this->preprocessor->process($sourceContent);

        $document = $this->parseXml($processedContent, $filePath, $fileReport);
        if ($document === null) {
            return new XmlProcessingResult($fileReport);
        }

        /** @var list<Fix> $fixes */
        $fixes = [];

        foreach ($this->sniffs as $sniff) {
            $start = microtime(true);

            $sniffViolations = $sniff->process($document, $sourceContent, $filePath);

            $this->report->addSniffTime($sniff::getCode(), microtime(true) - $start);

            $relevantViolations = $changedLines !== null
                ? $this->filterRelevantViolations($sniffViolations, $document, $changedLines)
                : $sniffViolations;

            $fileReport->addViolations($relevantViolations);

            if (!$sniff->mode->isFixMode() || !$sniff instanceof Fixable) {
                continue;
            }

            $fixer = new ($sniff::fixerClassName())();

            foreach ($relevantViolations as $violation) {
                $fixes[] = $fixer->process($violation);
            }
        }

        return new XmlProcessingResult(
            fileReport: $fileReport,
            fixResult: $fixes !== [] ? new FixApplier()->apply($sourceContent, $fixes) : null,
        );
    }

    private function parseXml(string $content, string $filePath, FileReport $fileReport): ?\DOMDocument
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = true;

        // LIBXML_NONET prevents network access.
        // No LIBXML_DTDLOAD needed since we stripped the DOCTYPE.
        $loaded = $document->loadXML($content, LIBXML_NONET);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if (!$loaded) {
            $message = $errors !== []
                ? trim($errors[0]->message)
                : 'Unknown XML parse error'; // @codeCoverageIgnore

            $fileReport->addViolation(new Violation(
                sniffCode: 'DocbookCS.Internal',
                filePath: $filePath,
                line: $errors !== [] ? $errors[0]->line : 0,
                beginOffset: 0,
                untilOffset: 0,
                message: 'XML parse error: ' . $message,
                severity: Severity::ERROR,
            ));
            return null;
        }

        return $document;
    }

    /**
     * @param list<Violation> $violations
     * @param list<int> $changedLines
     * @return list<Violation>
     */
    private function filterRelevantViolations(array $violations, \DOMDocument $document, array $changedLines): array
    {
        /** @var array<int, int> $changedSet */
        $changedSet = array_flip($changedLines);

        return array_values(array_filter(
            $violations,
            fn(Violation $v) => $this->isViolationRelevant($v, $document, $changedLines, $changedSet),
        ));
    }

    /**
     * @param list<int> $changedLines
     * @param array<int, int> $changedSet
     */
    private function isViolationRelevant(
        Violation $violation,
        \DOMDocument $document,
        array $changedLines,
        array $changedSet,
    ): bool {
        if (isset($changedSet[$violation->line])) {
            return true;
        }

        $violationElement = $this->firstElementOnLine($document, $violation->line);
        if ($violationElement === null) {
            return false;
        }

        $endLine = $this->computeElementEndLine($violationElement);

        foreach ($changedLines as $changed) {
            $owner = $this->innermostContaining($violationElement, $changed, $endLine);
            if ($owner === $violationElement) {
                return true;
            }

            if ($owner !== null && $owner->parentNode === $violationElement) {
                return true;
            }
        }

        return false;
    }

    private function firstElementOnLine(\DOMDocument $document, int $line): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->getLineNo() === $line) {
                return $element;
            }
        }

        return null;
    }

    private function innermostContaining(\DOMElement $element, int $line, int $endLine): ?\DOMElement
    {
        if ($line > $endLine || $line < $element->getLineNo()) {
            return null;
        }

        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        $count = count($children);
        foreach ($children as $i => $iValue) {
            $child = $iValue;

            $childEnd = $endLine;
            for ($j = $i + 1; $j < $count; $j++) {
                $nextLine = $children[$j]->getLineNo();
                if ($nextLine > $child->getLineNo()) {
                    $childEnd = $nextLine - 1;
                    break;
                }
            }
            $childEnd = min($childEnd, $this->computeElementEndLine($child));

            $deeper = $this->innermostContaining($child, $line, $childEnd);
            if ($deeper !== null) {
                return $deeper;
            }
        }

        return $element;
    }

    private function computeElementEndLine(\DOMElement $element): int
    {
        $max = $element->getLineNo();

        foreach ($element->childNodes as $child) {
            $line = $child->getLineNo();
            if ($line > $max) {
                $max = $line;
            }

            if ($child instanceof \DOMElement) {
                $childEnd = $this->computeElementEndLine($child);
                if ($childEnd > $max) {
                    $max = $childEnd;
                }
            }
        }

        return $max;
    }
}
