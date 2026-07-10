<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Violation\SourceRange;

final class SimparaSniff extends AbstractSniff
{
    private const string ELEMENT_NAME = 'para';
    private const string MESSAGE = '<para> contains only inline content and should be <simpara>.';
    private const string PARA_TAG_PATTERN = '/<\/?para\b[^>]*>/';

    private const array SIMPARA_ALLOWED = [
        'abbrev',
        'acronym',
        'action',
        'anchor',
        'application',
        'author',
        'authorinitials',
        'citation',
        'citerefentry',
        'citetitle',
        'classname',
        'cmdsynopsis',
        'command',
        'comment',
        'computeroutput',
        'constant',
        'corpauthor',
        'database',
        'email',
        'emphasis',
        'envar',
        'errorcode',
        'errorname',
        'errortype',
        'filename',
        'firstterm',
        'footnote',
        'footnoteref',
        'foreignphrase',
        'funcsynopsis',
        'function',
        'glossterm',
        'guibutton',
        'guiicon',
        'guilabel',
        'guimenu',
        'guimenuitem',
        'guisubmenu',
        'hardware',
        'indexterm',
        'inlineequation',
        'inlinegraphic',
        'inlinemediaobject',
        'interface',
        'interfacedefinition',
        'keycap',
        'keycode',
        'keycombo',
        'keysym',
        'link',
        'literal',
        'markup',
        'medialabel',
        'menuchoice',
        'modespec',
        'mousebutton',
        'msgtext',
        'olink',
        'option',
        'optional',
        'othercredit',
        'parameter',
        'phrase',
        'productname',
        'productnumber',
        'prompt',
        'property',
        'quote',
        'replaceable',
        'returnvalue',
        'revhistory',
        'sgmltag',
        'structfield',
        'structname',
        'subscript',
        'superscript',
        'symbol',
        'synopsis',
        'systemitem',
        'token',
        'trademark',
        'type',
        'ulink',
        'userinput',
        'varname',
        'wordasword',
        'xref',
    ];

    public static function getCode(): string
    {
        return 'DocbookCS.Simpara';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];
        $sourceMatchIndex = 0;

        $paras = $document->getElementsByTagName('para');
        if ($paras->length === 0) {
            return [];
        }

        $sourceMatches = $this->sourceMatches($content);
        $allowed = $this->getAllowedElements();

        /** @var \DOMElement $para */
        foreach ($paras as $para) {
            $match = $sourceMatches[$sourceMatchIndex] ?? null;
            $sourceMatchIndex++;

            if ($match === null) {
                throw new \LogicException('Could not map simpara violation to source content.');
            }

            $parent = $para->parentNode;
            if (
                $parent instanceof \DOMElement
                && strtolower($parent->localName ?? '') === 'formalpara'
            ) {
                continue;
            }

            if (!$this->isSimple($para, $allowed)) {
                continue;
            }

            $violations[] = $this->createViolation(
                $filePath,
                $para->getLineNo(),
                $match['beginOffset'],
                $match['untilOffset'],
                self::MESSAGE,
                $match['content'],
                affectedRanges: $match['affectedRanges'],
            );
        }

        return $violations;
    }

    /**
     * @param list<string> $allowed
     */
    private function isSimple(\DOMElement $node, array $allowed): bool
    {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName ?: '');

            if (!in_array($name, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function getAllowedElements(): array
    {
        $extra = $this->getProperty('additionalInlineElements');

        if ($extra === '') {
            return self::SIMPARA_ALLOWED;
        }

        $additional = array_map('trim', explode(',', $extra));
        $additional = array_filter($additional, static fn(string $s): bool => $s !== '');

        return array_values(array_unique(array_merge(self::SIMPARA_ALLOWED, $additional)));
    }

    /**
     * @return list<array{
     *     beginOffset: int,
     *     untilOffset: int,
     *     content: string,
     *     affectedRanges: non-empty-list<SourceRange>
     * }>
     */
    private function sourceMatches(string $content): array
    {
        preg_match_all(self::PARA_TAG_PATTERN, $content, $matches, PREG_OFFSET_CAPTURE);

        /** @var list<array{offset: int, range: SourceRange}> $stack */
        $stack = [];
        $sourceMatches = [];

        foreach ($matches[0] as [$tag, $offset]) {
            $offset = (int) $offset;

            if (!str_starts_with($tag, '</')) {
                $stack[] = [
                    'offset' => $offset,
                    'range' => new SourceRange(
                        $this->lineFromOffset($content, $offset),
                        $offset + 1,
                        $offset + 1 + strlen(self::ELEMENT_NAME),
                    ),
                ];
                continue;
            }

            if (null === $opening = array_pop($stack)) {
                continue;
            }

            $start = $opening['offset'];
            $untilOffset = $offset + strlen($tag);
            $sourceMatches[] = [
                'beginOffset' => $start,
                'untilOffset' => $untilOffset,
                'content' => substr($content, $start, $untilOffset - $start),
                'affectedRanges' => [
                    $opening['range'],
                    new SourceRange(
                        $this->lineFromOffset($content, $offset),
                        $offset + 2,
                        $offset + 2 + strlen(self::ELEMENT_NAME),
                    ),
                ],
            ];
        }

        usort(
            $sourceMatches,
            static fn(array $a, array $b): int => $a['beginOffset'] <=> $b['beginOffset'],
        );

        return $sourceMatches;
    }

    private function lineFromOffset(string $content, int $offset): int
    {
        return substr_count($content, "\n", 0, $offset) + 1;
    }
}
