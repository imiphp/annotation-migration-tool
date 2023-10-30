<?php

declare(strict_types=1);

namespace Imiphp\Tests\Stub;

use Imi\Enum\Annotation\EnumItem;
use Imi\Enum\BaseEnum;

class TestEnum extends BaseEnum
{
    /**
     * @EnumItem(text="甲", other="a1")
     */
    public const A = 1;

    /**
     * @EnumItem(text="乙", other="b2")
     */
    protected const B = 2;

    /**
     * @EnumItem(text="丙", other="c3")
     */
    const C = 3;
}