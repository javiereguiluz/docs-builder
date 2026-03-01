<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Directive;

use phpDocumentor\Guides\Nodes\CollectionNode;
use phpDocumentor\Guides\Nodes\Node;
use phpDocumentor\Guides\RestructuredText\Directives\SubDirective;
use phpDocumentor\Guides\RestructuredText\Parser\BlockContext;
use phpDocumentor\Guides\RestructuredText\Parser\Directive;
use phpDocumentor\Guides\RestructuredText\Parser\Productions\Rule;
use SymfonyDocsBuilder\Node\TabNode;

/**
 * Directive that only appears within the "tabs" directive.
 */
class TabDirective extends SubDirective
{
    public function __construct(protected Rule $startingRule)
    {
        parent::__construct($startingRule);
    }

    public function getName(): string
    {
        return 'tab';
    }

    protected function processSub(BlockContext $blockContext, CollectionNode $collectionNode, Directive $directive): Node|null
    {
        $tabName = $directive->getData();
        if (!$tabName) {
            throw new \RuntimeException(sprintf('The "tab" directive requires a tab name: ".. tab:: Tab Name".'));
        }

        return new TabNode($collectionNode->getChildren(), $tabName);
    }
}
