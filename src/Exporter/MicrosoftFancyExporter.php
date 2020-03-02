<?php

declare(strict_types=1);

namespace loophp\PhptreeAstGenerator\Exporter;

use Closure;
use loophp\phptree\Exporter\ExporterInterface;
use loophp\phptree\Modifier\Apply;
use loophp\phptree\Modifier\Filter;
use loophp\phptree\Node\AttributeNodeInterface;
use loophp\phptree\Node\NodeInterface;
use Microsoft\PhpParser\Node;

use function get_class;
use function in_array;

class MicrosoftFancyExporter implements ExporterInterface
{
    /**
     * @var \loophp\phptree\Exporter\ExporterInterface
     */
    private $exporter;

    /**
     * MicrosoftFancyGenerator constructor.
     *
     * @param \loophp\phptree\Exporter\ExporterInterface $exporter
     */
    public function __construct(ExporterInterface $exporter)
    {
        $this->exporter = $exporter;
    }

    /**
     * {@inheritdoc}
     */
    public function export(NodeInterface $node)
    {
        $applyShapeModifier = new Apply(Closure::fromCallable([$this, 'applyShape']));
        $applyLabelModifier = new Apply(Closure::fromCallable([$this, 'applyLabel']));
        $filterModifier = new Filter(Closure::fromCallable([$this, 'filterObsoleteNode']));
        $removeOriginalNode = new Apply(Closure::fromCallable([$this, 'removeOriginalNode']));

        $node = $applyLabelModifier->modify($node);
        $node = $applyShapeModifier->modify($node);
        $node = $filterModifier->modify($node);
        $node = $removeOriginalNode->modify($node);

        return $this->exporter->export($node);
    }

    private function applyLabel(AttributeNodeInterface $node): void
    {
        $astNode = $node->getAttribute('astNode');

        if (null === $astNode) {
            return;
        }

        $astTokens = iterator_to_array($astNode->getChildNodesAndTokens());

        switch (true) {
            case $astNode instanceof Node\ClassBaseClause:
            case $astNode instanceof Node\QualifiedName:
                $node->setAttribute(
                    'label',
                    sprintf('%s', (string) $astNode)
                );

                break;
            case $astNode instanceof Node\Statement\NamespaceDefinition:
                $node->setAttribute(
                    'label',
                    sprintf('Namespace %s', addslashes($node[0]->getAttribute('label')))
                );
                unset($node[0]);

                break;
            case $astNode instanceof Node\Statement\ClassDeclaration:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Class %s',
                        $astTokens['name']->getText((string) $astNode->getRoot())
                    )
                );

                break;
        }
    }

    private function applyShape(AttributeNodeInterface $node): void
    {
        $astNode = $node->getAttribute('astNode');

        if (null === $astNode) {
            return;
        }

        switch (true) {
            default:
                $node->setAttribute('shape', 'rect');

                break;
            case $astNode instanceof Node\Statement\NamespaceDefinition:
                $node->setAttribute('shape', 'house');

                break;
            case $astNode instanceof Node\Statement\ClassDeclaration:
                $node->setAttribute('shape', 'invhouse');

                break;
            case $astNode instanceof Node\MethodDeclaration:
                $node->setAttribute('shape', 'folder');

                break;
        }
    }

    private function filterObsoleteNode(AttributeNodeInterface $node): bool
    {
        $removeTypes = [
            Node\Statement\NamespaceUseDeclaration::class,
        ];

        return in_array(get_class($node->getAttribute('astNode')), $removeTypes, true);
    }

    private function removeOriginalNode(AttributeNodeInterface $node): void
    {
        $node->setAttribute('astNode', '');
    }
}
