<?php

namespace SymfonyDocsBuilder\Node;

use phpDocumentor\Guides\Nodes\CompoundNode;
use phpDocumentor\Guides\Nodes\Node;

/**
 * Holds tab content for the "tabs" directive. Each child should be a TabNode.
 *
 * @extends CompoundNode<Node>
 */
class TabsNode extends CompoundNode
{
    /** @param TabNode[] $tabs */
    public function __construct(
        private readonly string $title,
        array $tabs,
    ) {
        parent::__construct($tabs);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return TabNode[] */
    public function getTabs(): array
    {
        return $this->value;
    }
}
