# Gini PHP Framework

![Gini Logo](raw/assets/icon/gini.png)

**Gini** 是一个基于松散模块依赖的MVC PHP框架，深受Kohana, Symphony和Composer的影响。

[点击这里进入GitBook阅读具体文档](http://github.com/iamfat/gini-book/)

[可用的Gini模块](https://github.com/gini-modules)

## 易用的CLI
Gini 在命令行下提供了一个 `gini` 命令, 和 `composer` 与 `npm` 类似. 你能够很容易的通过少数几条命令创建CLI的应用，然后通过 `gini foo bar` 的方式调用.

## 面向对象
Gini PHP framework 基于 PHP 5 OO. 现在难道还有 PHP 框架不是 OO 的吗?

## 兼容 Composer
你可以通过 Composer 来加装 Gini, 也可以独立使用 Gini, 又或者在 Gini 框架中直接使用各种 Composer 第三方模块.

## Those ORM
这是一个内建的 ORM 实现, 方便大家采用 OO 的方式访问数据库. Database SQL 层完全被封装成了对象。 你可以像定义类一样的方式定义你的数据库表结构。当你实例化对象并赋值属性，然后使用 `save` 方法时，系统会自动将对象的属性保存在数据表中。

同时， **Those ORM** 提供了有趣的符合自然语义的语法来完成原本枯燥的SQL实现 (实验中...)。
以下是个示例:
```php
// 查询所有名字以'J'开头, 爸爸的email中存在genee的用户
$users = those('users')
    ->whose('name')->beginWith('J')
    ->andWhose('father')->isIn(
        those('users')->whose('email')->contains('genee')
    );
```

## 内建 JSON-RPC 和 REST 的 API 与远程调用支持
以下是个示例:
1. JSON-RPC
```php
// Client
$rpc = new \Gini\RPC('http://gini/api');
$sum = $rpc->hello->add(1, 2);

//Server
class Hello extends \Gini\Controller\API {
    public function actionAdd($a, $b) {
        return $a + $b;
    }
}
```
2. REST
```php
// Client
$rest = new \Gini\REST('http://localhost/rest');
$sum = $rest->post('add', ['a'=>1, 'b'=>2]);

// Server
class Hello extends \Gini\Controller\REST {
    public function postAdd($a, $b) {
        return $a + $b;
    }
}
```

## 运行测试

本机无需安装 PHP / Composer，测试通过 docker 容器运行，依赖树按 PHP 版本分目录并存（`vendor7`、`vendor85`），容器内把对应目录映射为 `vendor`：

```sh
# 一键双版本回归（PHP 7.4 + PHP 8.5 全量 phpunit）
ci/dual-test.sh
```

脚本运行前会清理含陈旧绝对路径的 `cache/class_map.json`，退出时自动清理映射产生的 `vendor` 符号链接。

单文件 / 带 filter 的手动跑法（结束后记得 `rm -f vendor cache/class_map.json`）：

```sh
# PHP 7.4
docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:7.4-alpine \
    sh -c "ln -sfn vendor7 vendor && vendor7/bin/phpunit ci/test/ORM/ORMIterator.php"

# PHP 8.5
docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:8.5-alpine \
    sh -c "ln -sfn vendor85 vendor && vendor85/bin/phpunit --filter testFetch"

rm -f vendor cache/class_map.json
```