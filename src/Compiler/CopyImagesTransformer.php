<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Compiler;

use phpDocumentor\Guides\Compiler\CompilerContext;
use phpDocumentor\Guides\Compiler\NodeTransformer;
use phpDocumentor\Guides\Nodes\ImageNode;
use phpDocumentor\Guides\Nodes\Node;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use SymfonyDocsBuilder\BuildConfig;

class CopyImagesTransformer implements NodeTransformer
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly BuildConfig $buildConfig,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function enterNode(Node $node, CompilerContext $compilerContext): Node
    {
        if (!$node instanceof ImageNode) {
            return $node;
        }

        $sourceImage = $this->buildConfig->getContentDir().'/'.$node->getValue();

        if (!file_exists($sourceImage)) {
            $this->logger?->error(sprintf(
                'Missing image file "%s"',
                $node->getValue(),
            ));

            return $node;
        }

        $fileInfo = new \SplFileInfo($sourceImage);

        $newAbsoluteFilePath = $this->buildConfig->getImagesDir().'/'.$fileInfo->getFilename();
        $this->filesystem->copy($sourceImage, $newAbsoluteFilePath, true);

        if ('' === $this->buildConfig->getImagesPublicPrefix()) {
            $newUrlPath = '_images/'.$fileInfo->getFilename();
        } else {
            $newUrlPath = $this->buildConfig->getImagesPublicPrefix().'/'.$fileInfo->getFilename();
        }
        $node->setValue($newUrlPath);

        return $node;
    }

    public function leaveNode(Node $node, CompilerContext $compilerContext): Node|null
    {
        return $node;
    }

    public function supports(Node $node): bool
    {
        return $node instanceof ImageNode;
    }

    public function getPriority(): int
    {
        return 1000;
    }
}
