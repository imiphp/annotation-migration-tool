<?php

declare(strict_types=1);

namespace Imiphp\Tests\Stub;

use Imi\Bean\Annotation\Partial;

/**
 * @Partial(TestClass1::class)
 */
trait TestTPartialClassA1
{
    public int $test2Value = 2;

    public function test2(): int
    {
        return $this->test2Value;
    }
}
