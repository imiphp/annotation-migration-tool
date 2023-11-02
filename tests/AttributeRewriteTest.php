<?php

declare(strict_types=1);

namespace Imiphp\Tests;

use Imiphp\Tool\AnnotationMigration\AttributeRewriteGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class AttributeRewriteTest extends TestCase
{
    #[DataProvider(methodName: 'rewriteCodeDataProvider')]
    public function testRewriteCode(string $filename, string $expectCode): void
    {
        $logger = new class() extends AbstractLogger {
            protected array $logs = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = sprintf('[%s]: %s', $level, $message);
            }

            public function getLogs(): array
            {
                return $this->logs;
            }
        };

        $crg = new AttributeRewriteGenerator($logger, false);

        $handle = $crg->generate($filename);

        foreach ($logger->getLogs() as $log)
        {
            echo $log . \PHP_EOL;
        }

        // echo $handle->execPrintStmt(), PHP_EOL;

        $this->assertTrue(true);
        $this->assertEquals(
            $expectCode,
            $handle->execPrintStmt(),
        );
    }

    public static function rewriteCodeDataProvider(): \Generator
    {
        yield 'class1' => [
            __DIR__ . '/StubAttribute/TestConsumer.php',
            <<<PHP
            <?php
            
            declare (strict_types=1);
            namespace Imiphp\Tests\StubAttribute;
            
            use Imi\Bean\Annotation\Base;
            /**
             * 消费者.
             *
             *
             *
            */
            #[\Attribute(\Attribute::TARGET_CLASS)]
            class TestConsumer extends Base
            {
                
                public function __construct(
                    /**
                     * 消费者标签
                     */
                    public string \$tag = '',
                    /**
                     * 队列名称
                     * @var string|array
                     */
                    public \$queue = '',
                    /**
                     * 交换机名称
                     * @var string|string[]
                     */
                    public \$exchange = null,
                    /**
                     * 路由键
                     */
                    public string \$routingKey = '',
                    /**
                     * 消息类名
                     */
                    public string \$message = \Imi\AMQP\Message::class,
                    /**
                     * mandatory标志位；当mandatory标志位设置为true时，如果exchange根据自身类型和消息routeKey无法找到一个符合条件的queue，那么会调用basic.return方法将消息返还给生产者；当mandatory设为false时，出现上述情形broker会直接将消息扔掉。
                     */
                    public bool \$mandatory = false,
                    /**
                     * immediate标志位；当immediate标志位设置为true时，如果exchange在将消息route到queue(s)时发现对应的queue上没有消费者，那么这条消息不会放入队列中。当与消息routeKey关联的所有queue(一个或多个)都没有消费者时，该消息会通过basic.return方法返还给生产者。
                     */
                    public bool \$immediate = false,
                    public ?int \$ticket = null,
                    /**
                     * @var ?callable
                     */
                    public \$call1 = null,
                    /**
                     * @var callable
                     */
                    public \$call2 = null
                )
                {
                }
            }
            PHP,
        ];

        yield 'class_construct_warning' => [
            __DIR__ . '/StubAttribute/TestConstruct.php',
            <<<PHP
            <?php
            
            declare (strict_types=1);
            namespace Imiphp\Tests\StubAttribute;
            
            use Imi\Bean\Annotation\Base;
            /**
             * 回调注解.
             *
             *
             *
            */
            #[\Attribute(\Attribute::TARGET_PROPERTY)]
            class TestConstruct extends Base
            {
                
                public function __construct(
                    /**
                     * 类名，或者传入对象
                     * @var string|object
                     */
                    public \$class = null,
                    /**
                     * 方法名
                     */
                    public string \$method = ''
                )
                {
                    // parent::__construct(...\\func_get_args());
                    \$this->method = \$class . \$method;
                }
            }
            PHP,
        ];
    }
}
