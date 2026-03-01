<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Listener;

use phpDocumentor\Guides\Event\PostCollectFilesForParsingEvent;
use phpDocumentor\Guides\Event\PostParseDocument;
use phpDocumentor\Guides\Event\PreRenderProcess;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuildProgressListener
{
    private ProgressBar $progressBar;
    private array $parsedFiles = [];

    public function __construct(private readonly SymfonyStyle $io)
    {
        $this->progressBar = new ProgressBar($io);
    }

    public function onFilesCollected(PostCollectFilesForParsingEvent $event): void
    {
        $fileCount = count($event->getFiles());
        $this->io->note(sprintf('Start parsing %d rst files', $fileCount));
        $this->progressBar->setMaxSteps($fileCount);
    }

    public function onPostParseDocument(PostParseDocument $event): void
    {
        $file = $event->getFileName();
        if (!\in_array($file, $this->parsedFiles, true)) {
            $this->parsedFiles[] = $file;
            $this->progressBar->advance();
        }
    }

    public function onPreRender(PreRenderProcess $event): void
    {
        $this->progressBar->finish();

        $this->io->newLine(2);
        $this->io->note('Rendering the HTML files...');
    }
}
