<?php

declare(strict_types=1);

namespace Imiphp\Tests\StubAttribute;

use Imi\Bean\Annotation\Base;

/**
 * 回调注解.
 *
 * @Annotation
 *
 * @Target({"PROPERTY", "ANNOTATION"})
 *
 * @property string|object $class  类名，或者传入对象
 * @property string        $method 方法名
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class TestConstruct extends Base
{
    /**
     * @param string|object $class
     */
    public function __construct(?array $__data = null, $class = null, string $method = '')
    {
        // parent::__construct(...\func_get_args());
        $this->method = $class . $method;
    }
}
