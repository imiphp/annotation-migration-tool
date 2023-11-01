<?php

declare(strict_types=1);

namespace Imiphp\Tests\Stub;

use Imi\Enum\Annotation\EnumItem;
use Imi\Enum\BaseEnum;

class TestEnum extends BaseEnum
{
    /**
     * @EnumItem(text="甲")
     */
    public const A = 1;

    /**
     * @EnumItem(text="乙")
     */
    protected const B = 2;

    /**
     * @EnumItem(text="丙")
     */
    const C = 3;
}
