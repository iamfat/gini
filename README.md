# Gini PHP Framework

**Gini** 是一个基于松散模块依赖的MVC PHP框架，深受Kohana, Symphony和Composer的影响。

[点击这里进入GitBook阅读具体文档](http://iamfat.gitbooks.io/gini-book/)

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
