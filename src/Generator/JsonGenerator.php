<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Generator;

use phpDocumentor\Guides\Nodes\DocumentTree\DocumentEntryNode;
use phpDocumentor\Guides\Nodes\ProjectNode;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use SymfonyDocsBuilder\BuildConfig;
use SymfonyDocsBuilder\Twig\TocExtension;
use function Symfony\Component\String\u;

class JsonGenerator
{
    private $projectNode;

    private $buildConfig;

    public function __construct(ProjectNode $projectNode, BuildConfig $buildConfig)
    {
        $this->projectNode = $projectNode;
        $this->buildConfig = $buildConfig;
    }

    /**
     * Returns an array of each JSON file string, keyed by the input filename
     *
     * @param string $masterDocument The file whose toctree should be read first
     * @return string[]
     */
    public function generateJson(string $masterDocument = 'index'): array
    {
        $fs = new Filesystem();

        $progressBar = new ProgressBar(new NullOutput());
        $progressBar->setMaxSteps(\count($this->projectNode->getAllDocumentEntries()));

        $walkedFiles = [];
        $tocTreeHierarchy = $this->walkTocTreeAndReturnHierarchy(
            $masterDocument,
            $walkedFiles
        );
        // for purposes of prev/next/parents, the "master document"
        // behaves as if it's the first item in the toctree
        $tocTreeHierarchy = [$masterDocument => []] + $tocTreeHierarchy;
        $flattenedTocTree = $this->flattenTocTree($tocTreeHierarchy);

        $fJsonFiles = [];
        foreach ($this->projectNode->getAllDocumentEntries() as $filename => $documentEntry) {
            $parserFilename = $filename;
            $jsonFilename = $this->buildConfig->getOutputDir().'/'.$filename.'.fjson';

            $crawler = new Crawler(file_get_contents($this->buildConfig->getOutputDir().'/'.$filename.'.html'));

            // happens when some doc is a partial included in other doc an it doesn't have any titles
            $toc = $this->generateToc($documentEntry, $crawler);
            $next = $this->determineNext($parserFilename, $flattenedTocTree);
            $prev = $this->determinePrev($parserFilename, $flattenedTocTree);
            $data = [
                'title' => $documentEntry->getTitle()->toString(),
                'parents' => $this->determineParents($parserFilename, $tocTreeHierarchy) ?: [],
                'current_page_name' => $parserFilename,
                'toc' => $toc,
                'toc_options' => TocExtension::getOptions($toc),
                'next' => $next,
                'prev' => $prev,
                'body' => $crawler->filter('body')->html(),
            ];

            $fs->dumpFile(
                $jsonFilename,
                json_encode($data, JSON_PRETTY_PRINT)
            );
            $fJsonFiles[$filename] = $data;

            $progressBar->advance();
        }

        $progressBar->finish();

        return $fJsonFiles;
    }

    private function generateToc(DocumentEntryNode $documentEntry, Crawler $crawler): array
    {
        $flatTocTree = [];

        foreach ($crawler->filter('h2, h3') as $heading) {
            $headerId = $heading->getAttribute('id') ?? trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($heading->textContent)), '-');

            $url = $documentEntry->getFile() . '.html';

            // this tocTree stores items sequentially (h2, h2, h3, h3, h2, h3, etc.)
            $flatTocTree[] = [
                'level' => 'h2' === $heading->tagName ? 1 : 2,
                'url' => sprintf('%s#%s', $url, $headerId),
                'page' => u($url)->beforeLast('.html')->toString(),
                'fragment' => $headerId,
                'title' => $heading->textContent,
                'children' => [],
            ];
        }

        // this tocTree stores items nested by level (h2, h2[h3, h3], h2[h3], etc.)
        $nestedTocTree = [];
        foreach ($flatTocTree as $tocItem) {
            if (1 === $tocItem['level']) {
                $nestedTocTree[] = $tocItem;
            } elseif ([] !== $nestedTocTree) {
                $nestedTocTree[\count($nestedTocTree) - 1]['children'][] = $tocItem;
            }
        }

        return $nestedTocTree;
    }

    private function determineNext(string $parserFilename, array $flattenedTocTree): ?array
    {
        $index = array_flip($flattenedTocTree);

        if (!isset($index[$parserFilename])) {
            return null;
        }

        $nextIndex = $index[$parserFilename] + 1;
        if (!isset($flattenedTocTree[$nextIndex])) {
            return null;
        }

        return $this->makeRelativeLink($parserFilename, $flattenedTocTree[$nextIndex]);
    }

    private function determinePrev(string $parserFilename, array $flattenedTocTree): ?array
    {
        $index = array_flip($flattenedTocTree);

        if (!isset($index[$parserFilename])) {
            return null;
        }

        $prevIndex = $index[$parserFilename] - 1;
        if ($prevIndex < 0) {
            return null;
        }

        return $this->makeRelativeLink($parserFilename, $flattenedTocTree[$prevIndex]);
    }

    private function getDocumentEntry(string $parserFilename, bool $throwOnMissing = false): ?DocumentEntryNode
    {
        $documentEntry = $this->projectNode->findDocumentEntry($parserFilename);

        // this is possible if there are invalid references
        if (null === $documentEntry) {
            $message = sprintf('Could not find DocumentEntryNode for file "%s"', $parserFilename);

            if ($throwOnMissing) {
                throw new \Exception($message);
            }
        }

        return $documentEntry;
    }

    /**
     * Creates a hierarchy of documents by crawling the toctree's
     *
     * This looks at the
     * toc tree of the master document, following the first entry
     * like a link, then repeating the process on the next document's
     * toc tree (if it has one). When it hits a dead end, it would
     * go back to the master document and click the second link.
     * But, it skips any links that have been seen before. This
     * is the logic behind how the prev/next parent information is created.
     *
     * Example result:
     *      [
     *          'dashboards' => [],
     *          'design' => [
     *              'crud' => [],
     *              'design/sub-page' => [],
     *          ],
     *          'fields' => []
     *      ]
     *
     * See the JsonIntegrationTest for a test case.
     */
    private function walkTocTreeAndReturnHierarchy(string $filename, array &$walkedFiles): array
    {
        $hierarchy = [];

        // happens in edge-cases such as empty or not found documents
        if (null === $documentEntry = $this->getDocumentEntry($filename)) {
            return $hierarchy;
        }

        $tocs = [array_map(fn(DocumentEntryNode $child) => $child->getFile(), $documentEntry->getChildren())];

        foreach ($tocs as $toc) {
            foreach ($toc as $tocFilename) {
                // only walk a file one time, the first time you see it
                if (isset($walkedFiles[$tocFilename])) {
                    continue;
                }

                $walkedFiles[$tocFilename] = true;

                $hierarchy[$tocFilename] = $this->walkTocTreeAndReturnHierarchy($tocFilename, $walkedFiles);
            }
        }

        return $hierarchy;
    }

    /**
     * Takes the structure from walkTocTreeAndReturnHierarchy() and flattens it.
     *
     * For example:
     *
     *      [dashboards, design, crud, design/sub-page, fields]
     *
     * @return string[]
     */
    private function flattenTocTree(array $tocTreeHierarchy): array
    {
        $files = [];

        foreach ($tocTreeHierarchy as $filename => $tocTree) {
            $files[] = $filename;

            array_push($files, ...$this->flattenTocTree($tocTree));
        }

        return $files;
    }

    private function determineParents(string $parserFilename, array $tocTreeHierarchy, array $parents = []): ?array
    {
        foreach ($tocTreeHierarchy as $filename => $tocTree) {
            if ($filename === $parserFilename) {
                return $parents;
            }

            $subParents = $this->determineParents($parserFilename, $tocTree, $parents + [$this->makeRelativeLink($parserFilename, $filename)]);

            if (null !== $subParents) {
                // the item WAS found and the parents were returned
                return $subParents;
            }
        }

        // item was not found
        return null;
    }

    private function makeRelativeLink(string $currentFilename, string $filename): array
    {
        // happens in edge-cases such as empty or not found documents
        if (null === $entry = $this->getDocumentEntry($filename)) {
            return ['title' => '', 'link' => ''];
        }

        return [
            'title' => $entry->getTitle()->toString(),
            'link' => str_repeat('../', substr_count($currentFilename, '/')) . $entry->getFile() . '.html',
        ];
    }
}
