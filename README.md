# 迁移`imi`注解语法为`PHP 8`原生实现

# 用法

### 迁移注解语法

检查 src 目录下的 php 是否有传统注解并重写  
```shell
./vendor/bin/imi-migration --dir="src"
```

### 迁移注解定义

检查 src 目录下的 php 是否有传统定义并转换[构造器属性提升](https://www.php.net/manual/zh/language.oop5.decon.php#language.oop5.decon.constructor.promotion)语法
```shell
./vendor/bin/imi-migration --dir="src" --annotation-rewrite
```

### 初始化配置文件（可选）

配置文件务必是输出在项目根目录，可对`imi`注解语法解析器进行配置，以解决注解读取的冲突问题。

```shell
./vendor/bin/imi-migration --init-config
```

默认配置文件例子

```php
<?php
declare(strict_types=1);

return [
    'globalIgnoredName' => [
        // 'depends',
        // 'type',
        // 'testdox',
    ],
    'globalIgnoredNamespace' => [],
    'globalImports' => [
        // 'oa', 'OpenApi\Annotations',
    ],
];
```

### 共用参数选项说明

- `--dry-run` 尝试运行，预览哪些文件会受到影响
- `--no-catch-continue` 遇到异常时中断转换过程
- `--no-error-continue` 检查到错误时中断转换过程