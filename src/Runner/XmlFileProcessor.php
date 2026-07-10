<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\Fix\FixerException;
use DocbookCS\Fix\FixerRegistry;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

final readonly class XmlFileProcessor
{
    private const int MAX_FIX_PASSES = 20;

    /** @var list<SniffInterface> */
    private array $sniffs;

    private EntityPreprocessor $preprocessor;

    private Report $report;

    private FixerRegistry $fixerRegistry;

    /** @param list<SniffInterface> $sniffs */
    public function __construct(
        array $sniffs,
        ?EntityPreprocessor $preprocessor = null,
        ?Report $report = null,
        ?FixerRegistry $fixerRegistry = null,
    ) {
        $this->sniffs = $sniffs;
        $this->preprocessor = $preprocessor ?? new EntityPreprocessor([]);
        $this->report = $report ?? new Report();
        $this->fixerRegistry = $fixerRegistry ?? FixerRegistry::defaults();
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
        string $originalContent,
        string $filePath,
        FileReport $fileReport,
        ?array $changedLines = null,
    ): XmlProcessingResult {
        $sourceContent = $originalContent;
        $seenContentHashes = [hash('sha256', $sourceContent) => true];
        $applied = 0;
        $skipped = 0;
        $fixPasses = 0;
        $scope = $changedLines !== null
            ? SourceScope::changedLines($sourceContent, $changedLines)
            : SourceScope::wholeFile();

        while (true) {
            $passReport = new FileReport($filePath);
            $processedContent = $this->preprocessor->processForParsing($sourceContent);

            $document = $this->parseXml($processedContent, $filePath, $passReport);
            if ($document === null) {
                if ($sourceContent !== $originalContent) {
                    throw FixerException::invalidFixedXml($filePath);
                }

                break;
            }

            $fixes = $this->runSniffs(
                $document,
                $sourceContent,
                $filePath,
                $passReport,
                $scope,
                $changedLines,
            );

            if ($fixes === []) {
                break;
            }

            $fixResult = new FixApplier()->apply($sourceContent, $fixes);
            $applied += $fixResult->applied;
            $skipped += $fixResult->skipped;

            if ($fixResult->applied === 0) {
                break;
            }

            $fixPasses++;
            $fixedContentHash = hash('sha256', $fixResult->content);

            if (
                $fixPasses > self::MAX_FIX_PASSES
                || $fixResult->content === $sourceContent
                || isset($seenContentHashes[$fixedContentHash])
            ) {
                throw FixerException::didNotConverge($filePath);
            }

            $seenContentHashes[$fixedContentHash] = true;
            $scope = $scope->after($fixResult->appliedFixes);
            $sourceContent = $fixResult->content;
        }

        return $this->finalizeResult(
            $originalContent,
            $sourceContent,
            $fileReport,
            $passReport,
            $applied,
            $skipped,
        );
    }

    /**
     * @param list<int>|null $changedLines
     * @return list<Fix|FixPlan>
     * @throws FixerException
     */
    private function runSniffs(
        \DOMDocument $document,
        string $sourceContent,
        string $filePath,
        FileReport $fileReport,
        SourceScope $scope,
        ?array $changedLines,
    ): array {
        $fixes = [];

        foreach ($this->sniffs as $sniff) {
            $start = microtime(true);

            $sniffViolations = $sniff->process($document, $sourceContent, $filePath);

            $this->report->addSniffTime($sniff::getCode(), microtime(true) - $start);

            $relevantViolations = $changedLines !== null
                ? $this->filterRelevantViolations(
                    $sniffViolations,
                    $document,
                    $scope,
                    $changedLines,
                )
                : $sniffViolations;

            $fileReport->addViolations($relevantViolations);

            if (!$sniff->mode->isFixMode()) {
                continue;
            }

            $fixerClass = $this->fixerRegistry->fixerClassFor($sniff);
            if ($fixerClass === null) {
                continue;
            }

            $fixer = new $fixerClass();

            foreach ($relevantViolations as $violation) {
                $fixes[] = $fixer->process($violation);
            }
        }

        return $fixes;
    }

    private function finalizeResult(
        string $originalContent,
        string $sourceContent,
        FileReport $fileReport,
        FileReport $passReport,
        int $applied,
        int $skipped,
    ): XmlProcessingResult {
        $fileReport->addViolations($passReport->getViolations());

        return new XmlProcessingResult(
            fileReport: $fileReport,
            fixResult: $sourceContent !== $originalContent
                ? new FixResult($sourceContent, $applied, $skipped)
                : null,
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
    private function filterRelevantViolations(
        array $violations,
        \DOMDocument $document,
        SourceScope $scope,
        array $changedLines,
    ): array {
        /** @var array<int, int> $changedSet */
        $changedSet = array_flip($changedLines);

        return array_values(array_filter(
            $violations,
            fn(Violation $violation) => $scope->includes($violation)
                || (
                    $violation->content === null
                    && $violation->beginOffset === $violation->untilOffset
                    && $this->isViolationRelevant($violation, $document, $changedLines, $changedSet)
                ),
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
