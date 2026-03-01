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
use SymfonyDocsBuilder\Node\TabsNode;

class TabsDirective extends SubDirective
{
    public function __construct(protected Rule $startingRule)
    {
        parent::__construct($startingRule);
    }

    public function getName(): string
    {
        return 'tabs';
    }

    protected function processSub(BlockContext $blockContext, CollectionNode $collectionNode, Directive $directive): Node|null
    {
        $tabsTitle = $directive->getData();
        if (!$tabsTitle) {
            throw new \RuntimeException(sprintf('The "tabs" directive requires a title: ".. tabs:: Title".'));
        }

        $tabs = [];
        foreach ($collectionNode->getChildren() as $tabNode) {
            if (!$tabNode instanceof TabNode) {
                throw new \RuntimeException(sprintf('Only ".. tab::" content can appear within the "tabs" directive.'));
            }

            $tabs[] = $tabNode;
        }

        return new TabsNode($tabsTitle, $tabs);
    }
}
