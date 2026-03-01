<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Reference;

use phpDocumentor\Guides\Nodes\Inline\HyperLinkNode;
use phpDocumentor\Guides\Nodes\Inline\InlineNodeInterface;
use phpDocumentor\Guides\Nodes\Inline\PlainTextInlineNode;
use phpDocumentor\Guides\RestructuredText\Parser\DocumentParserContext;
use phpDocumentor\Guides\RestructuredText\TextRoles\TextRole;
use function Symfony\Component\String\u;

class PhpClassReference implements TextRole
{
    public function __construct(private readonly string $phpDocUrl)
    {
    }

    public function getName(): string
    {
        return 'phpclass';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function processNode(
        DocumentParserContext $documentParserContext,
        string $role,
        string $content,
        string $rawContent,
    ): InlineNodeInterface {
        $className = u($content)->replace('\\\\', '\\');

        return new HyperLinkNode(
            [new PlainTextInlineNode($className->afterLast('\\')->toString())],
            sprintf('%s/class.%s.php', $this->phpDocUrl, $className->replace('\\', '-')->lower()),
        );
    }
}
