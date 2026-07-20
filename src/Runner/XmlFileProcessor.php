<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Diff\FileChange;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixerException;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Sniff\Fixable;
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

final readonly class XmlFileProcessor
{
    private const int MAX_FIX_PASSES = 20;

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

    /** @throws FixerException */
    public function process(File $initialFile, ?FileChange $fileChange = null): XmlProcessingResult
    {
        $fileReport = new FileReport($initialFile->path);
        $currentFile = $initialFile;
        $scope = $fileChange === null
            ? SourceScope::wholeFile()
            : SourceScope::changedLines($initialFile, $fileChange->lineNumbers);
        $seenContentHashes = [hash('sha256', $currentFile->content) => true];
        $fixPasses = 0;

        while (true) {
            $passReport = new FileReport($currentFile->path);

            $document = $this->parseXml($currentFile, $passReport);
            if ($document === null) {
                if ($currentFile->content !== $initialFile->content) {
                    throw FixerException::invalidFixedXml($currentFile->path);
                }

                break;
            }

            $fixes = $this->runSniffs(
                $document,
                $currentFile,
                $passReport,
                $scope,
            );

            if ($fixes === []) {
                break;
            }

            $fixResult = new FixApplier()->apply($currentFile, $fixes);

            if ($fixResult->applied === 0) {
                break;
            }

            $fixPasses++;
            $fixedContentHash = hash('sha256', $fixResult->file->content);

            if (
                $fixPasses > self::MAX_FIX_PASSES
                || $fixResult->file->content === $currentFile->content
                || isset($seenContentHashes[$fixedContentHash])
            ) {
                throw FixerException::didNotConverge($currentFile->path);
            }

            $seenContentHashes[$fixedContentHash] = true;
            $scope = $scope->after($fixResult->appliedFixes);
            $currentFile = $fixResult->file;
        }

        $fileReport->addViolations($passReport->getViolations());

        return new XmlProcessingResult(
            fileReport: $fileReport,
            file: $currentFile,
            modified: $currentFile !== $initialFile,
        );
    }

    /**
     * @return list<Fix|FixPlan>
     * @throws FixerException
     */
    private function runSniffs(
        \DOMDocument $document,
        File $file,
        FileReport $fileReport,
        SourceScope $scope,
    ): array {
        $fixes = [];
        $changedLines = $scope->isWholeFile()
            ? null
            : $scope->lineNumbers($file);

        foreach ($this->sniffs as $sniff) {
            $start = microtime(true);

            $sniffViolations = $sniff->process($document, $file);

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

            if (!$sniff->mode->isFixMode() || !$sniff instanceof Fixable) {
                continue;
            }

            $fixer = new ($sniff::fixerClassName());

            foreach ($relevantViolations as $violation) {
                $fixes[] = $fixer->process($violation);
            }
        }

        return $fixes;
    }

    private function parseXml(File $file, FileReport $fileReport): ?\DOMDocument
    {
        $content = $this->preprocessor->processForParsing($file->content);

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
                filePath: $file->path,
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
