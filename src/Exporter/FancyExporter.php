<?php

declare(strict_types=1);

namespace loophp\PhptreeAstGenerator\Exporter;

use Closure;
use loophp\phptree\Exporter\ExporterInterface;
use loophp\phptree\Modifier\Apply;
use loophp\phptree\Modifier\Filter;
use loophp\phptree\Node\AttributeNodeInterface;
use loophp\phptree\Node\NodeInterface;
use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

use function get_class;
use function in_array;
use function is_string;

class FancyExporter implements ExporterInterface
{
    /**
     * @var \loophp\phptree\Exporter\ExporterInterface
     */
    private $exporter;

    /**
     * FancyGenerator constructor.
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
        $removeStmtExpressionModifier = new Apply(Closure::fromCallable([$this, 'cleanObsoleteNode']));
        $filterModifier = new Filter(Closure::fromCallable([$this, 'filterObsoleteNode']));

        $node = $removeStmtExpressionModifier->modify($node);
        $node = $applyLabelModifier->modify($node);
        $node = $applyShapeModifier->modify($node);
        $node = $filterModifier->modify($node);

        return $this->exporter->export($node);
    }

    protected function applyShape(AttributeNodeInterface $node): void
    {
        $astNode = $node->getAttribute('astNode');

        if (null === $astNode) {
            return;
        }

        switch (true) {
            default:
                $node->setAttribute('shape', 'rect');

                break;
            case $astNode instanceof Stmt\Namespace_:
                $node->setAttribute('shape', 'house');

                break;
            case $astNode instanceof Stmt\Class_:
                $node->setAttribute('shape', 'invhouse');

                break;
            case $astNode instanceof Stmt\ClassMethod:
                $node->setAttribute('shape', 'folder');

                break;
            case $astNode instanceof Expr\ConstFetch:
            case $astNode instanceof Scalar\DNumber:
            case $astNode instanceof Scalar\LNumber:
                $node->setAttribute('shape', 'circle');

                break;
        }
    }

    protected function cleanObsoleteNode(AttributeNodeInterface $node): void
    {
        $astNode = $node->getAttribute('astNode');

        switch (true) {
            case $astNode instanceof Stmt\Class_:
                /** @var AttributeNodeInterface $child */
                foreach ($node->children() as $key => $child) {
                    if (in_array($child->getAttribute('astNode'), $astNode->implements, true)) {
                        unset($node[$key]);
                    }

                    if (in_array($child->getAttribute('astNode'), [$astNode->extends], true)) {
                        unset($node[$key]);
                    }
                }

                break;
            case $astNode instanceof Stmt\Expression:
                if (null !== $parent = $node->getParent()) {
                    // Find the key of the current node in the parent.
                    foreach ($parent->children() as $key => $child) {
                        if ($child === $node) {
                            // Replace it with it's first child.
                            $parent[$key] = $node[0];

                            break;
                        }
                    }
                }

                break;
        }
    }

    protected function filterObsoleteNode(AttributeNodeInterface $node): bool
    {
        $removeTypes = [
            Stmt\Use_::class,
            Stmt\Declare_::class,
            Identifier::class,
        ];

        return in_array(get_class($node->getAttribute('astNode')), $removeTypes, true);
    }

    private function applyLabel(AttributeNodeInterface $node): void
    {
        $astNode = $node->getAttribute('astNode');

        if (null === $astNode) {
            return;
        }

        switch (true) {
            case $astNode instanceof Name:
                $node->setAttribute(
                    'label',
                    sprintf('%s', $astNode->toString())
                );

                break;
            case $astNode instanceof Stmt\Namespace_:
                $node->setAttribute(
                    'label',
                    sprintf('Namespace %s', addslashes($node[0]->getAttribute('label')))
                );
                unset($node[0]);

                break;
            case $astNode instanceof Stmt\Class_:
                $extends = '';

                if (null !== $extendNode = $astNode->extends) {
                    $extends = sprintf(' extends %s', $extendNode->toString());
                }

                $node->setAttribute(
                    'label',
                    sprintf(
                        'Class %s%s',
                        $node[0]->getAttribute('label'),
                        $extends
                    )
                );

                break;
            case $astNode instanceof Stmt\ClassMethod:
                $identifier = $node[0]->getAttribute('label');

                $returnType = '';

                /** @var AttributeNodeInterface $child */
                foreach ($node->children() as $keyVar => $child) {
                    if ($child->getAttribute('astNode') === $astNode->getReturnType()) {
                        unset($node[$keyVar]);

                        if ($astNode->getReturnType() instanceof NullableType) {
                            $returnType = sprintf(': null');
                        } else {
                            $returnType = $astNode->getReturnType();

                            if ($returnType instanceof Identifier) {
                                $returnType = $returnType->toString();
                            } else {
                                $returnType = 'void';
                            }

                            $returnType = sprintf(': %s', $returnType);
                        }
                    }
                }

                $node->setAttribute('label', sprintf('Method %s()%s', $identifier, $returnType));

                break;
            case $astNode instanceof Stmt\Function_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Definition of %s()',
                        $node[0]->getAttribute('label')
                    )
                );

                break;
            case $astNode instanceof Stmt\Property:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Property %s',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Param:
                $degree = $node->degree();
                $parameter = [];

                if (1 === $degree) {
                    $parameter[] = $node[0]->getAttribute('label');
                }

                if (2 === $degree) {
                    $parameter[] = $node[0]->getAttribute('label');
                    $parameter[] = $node[1]->getAttribute('label');
                }

                if (3 === $degree) {
                    $parameter[] = $node[0]->getAttribute('label');
                    $parameter[] = $node[1]->getAttribute('label');
                    $parameter[] = sprintf('= %s', mb_strtolower($node[2]->getAttribute('label')));
                }

                $node->setAttribute(
                    'label',
                    sprintf('Parameter %s', implode(' ', $parameter))
                );

                unset($node[0], $node[1], $node[2]);

                break;
            case $astNode instanceof NullableType:
            case $astNode instanceof Expr\ConstFetch:
            case $astNode instanceof Stmt\PropertyProperty:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '%s',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Stmt\Use_:
                $node->setAttribute(
                    'label',
                    sprintf('Use')
                );

                break;
            case $astNode instanceof Stmt\UseUse:
                $name = $node[0];

                $node->setAttribute(
                    'label',
                    sprintf('%s', $name->getAttribute('label'))
                );

                break;
            case $astNode instanceof Stmt\Foreach_:
                $keyVar = '';
                $expr = $node[0]->getAttribute('label');
                $valueVar = $node[1]->getAttribute('label');

                if (null !== $astNode->keyVar) {
                    $keyVar = sprintf('%s => ', $valueVar);
                    $valueVar = $node[2]->getAttribute('label');
                }

                $node->setAttribute(
                    'label',
                    sprintf('Foreach %s as %s%s', $expr, $keyVar, $valueVar)
                );

                unset($node[0], $node[1], $node[2]);

                break;
            case $astNode instanceof Stmt\If_:
                $name = 'If | Then';

                if (null !== $astNode->else) {
                    $name .= ' | Else';
                }

                $node->setAttribute(
                    'label',
                    sprintf('%s', $name)
                );

                break;
            case $astNode instanceof Stmt\Else_:
                $node->setAttribute(
                    'label',
                    sprintf('Else')
                );

                break;
            case $astNode instanceof Stmt\ElseIf_:
                $node->setAttribute(
                    'label',
                    sprintf('Elseif')
                );

                break;
            case $astNode instanceof Stmt\Return_:
                $node->setAttribute('label', sprintf('Return'));

                break;
            case $astNode instanceof Identifier:
                $node->setAttribute('label', sprintf('%s', $astNode->name));

                break;
            case $astNode instanceof Stmt\Declare_:
                $node->setAttribute('label', sprintf('Declare'));

                break;
            case $astNode instanceof Stmt\DeclareDeclare:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Key %s, Value %s',
                        $node[0]->getAttribute('label'),
                        $node[1]->getAttribute('label')
                    )
                );

                break;
            case $astNode instanceof Scalar\LNumber:
                $node->setAttribute('label', sprintf('Integer %s', $astNode->value));

                break;
            case $astNode instanceof Stmt\Unset_:
                $node->setAttribute(
                    'label',
                    sprintf('Unset')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\Concat:
                $node->setAttribute(
                    'label',
                    sprintf('Concatenation of')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\Identical:
                $node->setAttribute('label', 'strict equal');

                break;
            case $astNode instanceof Expr\AssignOp\Plus:
            case $astNode instanceof Expr\BinaryOp\Plus:
                $node->setAttribute('label', 'addition');

                break;
            case $astNode instanceof Expr\AssignOp\Mul:
            case $astNode instanceof Expr\BinaryOp\Mul:
                $node->setAttribute('label', 'multiplication');

                break;
            case $astNode instanceof Expr\AssignOp\Minus:
            case $astNode instanceof Expr\BinaryOp\Minus:
                $node->setAttribute('label', 'substraction');

                break;
            case $astNode instanceof Expr\AssignOp\Div:
            case $astNode instanceof Expr\BinaryOp\Div:
                $node->setAttribute('label', 'division');

                break;
            case $astNode instanceof Expr\BinaryOp\BooleanOr:
                $node->setAttribute('label', 'or');

                break;
            case $astNode instanceof Expr\BinaryOp\NotIdentical:
                $node->setAttribute('label', 'is not equal');

                break;
            case $astNode instanceof Expr\BooleanNot:
                $node->setAttribute('label', 'not');

                break;
            case $astNode instanceof Expr\MethodCall:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '%s->%s()',
                        $node[0]->getAttribute('label'),
                        $node[1]->getAttribute('label')
                    )
                );

                break;
            case $astNode instanceof Expr\Variable:
                $node->setAttribute(
                    'label',
                    sprintf('$%s', is_string($astNode->name) ? $astNode->name : 'ERROR')
                );

                break;
            case $astNode instanceof Expr\FuncCall:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '%s()',
                        $node[0]->getAttribute('label')
                    )
                );
                unset($node[0]);

                break;
            case $astNode instanceof Expr\PreInc:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '++%s',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\PostInc:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '%s++',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\Isset_:
                $variable = $node[0]->getAttribute('label');

                $node->setAttribute(
                    'label',
                    sprintf('Is %s set ?', $variable)
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\Array_:
                $name = 'Array of';

                if (0 === $node->degree()) {
                    $name = 'Empty array';
                }

                $node->setAttribute(
                    'label',
                    sprintf('%s', $name)
                );

                break;
            case $astNode instanceof Expr\ArrayItem:
                $node->setAttribute(
                    'label',
                    sprintf('Key | Value')
                );

                break;
            case $astNode instanceof Expr\ArrayDimFetch:
                $name = $node[0]->getAttribute('label');

                $dim = '';

                if (null !== $astNode->dim) {
                    $dim = $node[1]->getAttribute('label');
                }

                $node->setAttribute(
                    'label',
                    sprintf('%s[%s]', $name, $dim)
                );

                unset($node[0], $node[1]);

                break;
            case $astNode instanceof Expr\AssignOp\Plus:
                $valueVar = $node[0]->getAttribute('label');

                $node->setAttribute(
                    'label',
                    sprintf('Increment %s with', $valueVar)
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\PropertyFetch:
                $variable = $node[0]->getAttribute('label');
                $identifier = $node[1]->getAttribute('label');

                unset($node[0], $node[1]);

                $node->setAttribute('label', sprintf('%s->%s', $variable, $identifier));

                break;
            case $astNode instanceof Expr\Assign:
                $variable = $node[0]->getAttribute('label');

                $node->setAttribute(
                    'label',
                    sprintf('Assign to %s', $variable)
                );

                unset($node[0]);

                break;
            case $astNode instanceof Scalar\DNumber:
                $node->setAttribute('label', sprintf('Decimal %s', $astNode->value));

                break;
            case $astNode instanceof Stmt\Break_:
                $node->setAttribute(
                    'label',
                    sprintf('Break')
                );

                break;
            case $astNode instanceof Stmt\Continue_:
                $node->setAttribute(
                    'label',
                    sprintf('Continue')
                );

                break;
            case $astNode instanceof Stmt\TryCatch:
                $name = 'Try | Catch';

                if (null !== $astNode->finally) {
                    $name .= ' | Finally';
                }

                $node->setAttribute(
                    'label',
                    sprintf('%s', $name)
                );

                break;
            case $astNode instanceof Stmt\Catch_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Catch type %s in %s',
                        $node[0]->getAttribute('label'),
                        $node[1]->getAttribute('label')
                    )
                );

                unset($node[0], $node[1]);

                break;
            case $astNode instanceof Stmt\Finally_:
                $node->setAttribute(
                    'label',
                    sprintf('Finally')
                );

                break;
            case $astNode instanceof Stmt\Echo_:
                $node->setAttribute(
                    'label',
                    sprintf('Print')
                );

                break;
            case $astNode instanceof Scalar\Encapsed:
                $node->setAttribute(
                    'label',
                    sprintf('String')
                );

                break;
            case $astNode instanceof Scalar\EncapsedStringPart:
                $node->setAttribute(
                    'label',
                    sprintf('%s', $astNode->value)
                );

                break;
            case $astNode instanceof Expr\New_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'New %s',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\StaticCall:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Static call %s::%s()',
                        $node[0]->getAttribute('label'),
                        $node[1]->getAttribute('label')
                    )
                );

                unset($node[0], $node[1]);

                break;
            case $astNode instanceof Expr\YieldFrom:
                $node->setAttribute(
                    'label',
                    sprintf('Yield from')
                );

                break;
            case $astNode instanceof Expr\Yield_:
                $node->setAttribute(
                    'label',
                    sprintf('Yield')
                );

                break;
            case $astNode instanceof Expr\Clone_:
                $node->setAttribute(
                    'label',
                    sprintf('Clone of')
                );

                break;
            case $astNode instanceof Expr\Instanceof_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '%s is an instance of %s',
                        $node[0]->getAttribute('label'),
                        $node[1]->getAttribute('label')
                    )
                );

                unset($node[0], $node[1]);

                break;
            case $astNode instanceof Arg:
                $node->setAttribute(
                    'label',
                    sprintf('With argument')
                );

                break;
            case $astNode instanceof Expr\Closure:
                $node->setAttribute(
                    'label',
                    sprintf('Closure')
                );

                break;
            case $astNode instanceof Expr\Ternary:
                $node->setAttribute(
                    'label',
                    sprintf('Ternary')
                );

                break;
            case $astNode instanceof Stmt\While_:
                $node->setAttribute(
                    'label',
                    sprintf('While')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\Coalesce:
                $node->setAttribute(
                    'label',
                    sprintf('Unless is not null')
                );

                break;
            case $astNode instanceof Scalar\String_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '\'%s\'',
                        addslashes($astNode->value)
                    )
                );

                break;
            case $astNode instanceof Stmt\Nop:
                $node->setAttribute('label', sprintf('No operation'));

                break;
            case $astNode instanceof Stmt\Throw_:
                $node->setAttribute(
                    'label',
                    sprintf('Throw')
                );

                break;
            case $astNode instanceof Expr\Cast\Array_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Cast as array %s',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\Cast\String_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Cast as string %s',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\Cast\Int_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Cast as integer %s',
                        $node[0]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Expr\StaticPropertyFetch:
            case $astNode instanceof Expr\ClassConstFetch:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '%s::%s',
                        $node[0]->getAttribute('label'),
                        $node[1]->getAttribute('label')
                    )
                );

                break;
            case $astNode instanceof Expr\AssignOp\Concat:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Concat %s with',
                        $node[0]->getAttribute('label')
                    )
                );

                break;
            case $astNode instanceof Expr\BinaryOp\Smaller:
                $node->setAttribute(
                    'label',
                    sprintf('Is smaller')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\SmallerOrEqual:
                $node->setAttribute(
                    'label',
                    sprintf('Is smaller or equal')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\Greater:
                $node->setAttribute(
                    'label',
                    sprintf('Is greater')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\GreaterOrEqual:
                $node->setAttribute(
                    'label',
                    sprintf('Is greater or equal')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\BitwiseAnd:
                $node->setAttribute(
                    'label',
                    sprintf('Bitwise AND')
                );

                break;
            case $astNode instanceof Expr\BitwiseNot:
                $node->setAttribute(
                    'label',
                    sprintf('Bitwise not')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\BitwiseOr:
                $node->setAttribute(
                    'label',
                    sprintf('Bitwise OR')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\BitwiseXor:
                $node->setAttribute(
                    'label',
                    sprintf('Bitwise XOR')
                );

                break;
            case $astNode instanceof Expr\AssignOp\BitwiseOr:
                $node->setAttribute(
                    'label',
                    sprintf('Bitwise OR with')
                );

                break;
            case $astNode instanceof Expr\AssignOp\BitwiseAnd:
                $node->setAttribute(
                    'label',
                    sprintf('Bitwise AND with')
                );

                break;
            case $astNode instanceof Expr\BinaryOp\BooleanAnd:
                $node->setAttribute(
                    'label',
                    sprintf('and')
                );

                break;
            case $astNode instanceof Stmt\ClassConst:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Constant %s = %s',
                        $node[0][0]->getAttribute('label'),
                        $node[0][1]->getAttribute('label')
                    )
                );

                unset($node[0]);

                break;
            case $astNode instanceof Const_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        '%s',
                        $node[0]->getAttribute('label')
                    )
                );

                break;
            case $astNode instanceof Scalar\MagicConst\Class_:
                $node->setAttribute(
                    'label',
                    sprintf('%s', '__CLASS__')
                );
                unset($node[0], $node[1]);

                break;
            case $astNode instanceof Stmt\Case_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Case %s',
                        $node[0]->getAttribute('label')
                    )
                );
                unset($node[0]);

                break;
            case $astNode instanceof Stmt\Switch_:
                $node->setAttribute(
                    'label',
                    sprintf('Switch | Case')
                );

                break;
            case $astNode instanceof Scalar\MagicConst\Dir:
                $node->setAttribute(
                    'label',
                    sprintf('__DIR__')
                );

                break;
            case $astNode instanceof Expr\Include_:
                $node->setAttribute(
                    'label',
                    sprintf('Include')
                );

                break;
            case $astNode instanceof Expr\ClosureUse:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Closure %s',
                        $node[0]->getAttribute('label')
                    )
                );
                unset($node[0]);

                break;
            case $astNode instanceof Stmt\InlineHTML:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Inline HTML'
                    )
                );

                break;
            case $astNode instanceof Stmt\Interface_:
                $node->setAttribute(
                    'label',
                    sprintf(
                        'Interface %s',
                        $node[0]->getAttribute('label')
                    )
                );

                break;
        }
    }
}
