<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Listener;

use phpDocumentor\Guides\Event\PostRenderProcess;
use Symfony\Component\Filesystem\Filesystem;

final class AssetsCopyListener
{
    public function __construct(private readonly string $targetDir)
    {
    }

    public function __invoke(PostRenderProcess $event): void
    {
        $fs = new Filesystem();
        $fs->mirror(
            sprintf('%s/../Templates/rtd/assets', __DIR__),
            sprintf('%s/assets', $this->targetDir)
        );
    }
}
