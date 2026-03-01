<?php

namespace SymfonyDocsBuilder\Node;

use phpDocumentor\Guides\Nodes\CompoundNode;
use phpDocumentor\Guides\Nodes\Node;

/**
 * Wraps nodes + options in a TabDirective.
 *
 * @extends CompoundNode<Node>
 */
class TabNode extends CompoundNode
{
    private string $tabName;

    public function __construct(array $children, string $tabName)
    {
        $this->tabName = $tabName;

        parent::__construct($children);
    }

    public function getTabName(): string
    {
        return $this->tabName;
    }
}
