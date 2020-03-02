<?php

declare(strict_types=1);

namespace spec\loophp\PhptreeAstGenerator\Command;

use loophp\PhptreeAstGenerator\Command\Generator;
use PhpSpec\ObjectBehavior;

class GeneratorSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType(Generator::class);
    }
}
