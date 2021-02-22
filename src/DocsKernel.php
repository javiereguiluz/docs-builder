<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder;

use Doctrine\Common\EventManager;
use Doctrine\RST\Builder;
use Doctrine\RST\Configuration;
use Doctrine\RST\ErrorManager;
use Doctrine\RST\Event\PostBuildRenderEvent;
use Doctrine\RST\Event\PreNodeRenderEvent;
use Doctrine\RST\Kernel;
use SymfonyDocsBuilder\Listener\AssetsCopyListener;
use SymfonyDocsBuilder\Listener\CopyImagesListener;

class DocsKernel extends Kernel
{
    private $buildContext;

    public function __construct(?Configuration $configuration = null, $directives = [], $references = [], BuildContext $buildContext)
    {
        parent::__construct($configuration, $directives, $references);

        $this->buildContext = $buildContext;
    }

    public function initBuilder(Builder $builder): void
    {
        $this->initializeListeners(
            $builder->getConfiguration()->getEventManager(),
            $builder->getErrorManager()
        );
    }

    private function initializeListeners(EventManager $eventManager, ErrorManager $errorManager)
    {
        $eventManager->addEventListener(
           PreNodeRenderEvent::PRE_NODE_RENDER,
           new CopyImagesListener($this->buildContext, $errorManager)
       );

        if (!$this->buildContext->getParseSubPath()) {
            $eventManager->addEventListener(
               [PostBuildRenderEvent::POST_BUILD_RENDER],
               new AssetsCopyListener($this->buildContext->getOutputDir())
           );
        }
    }
}
