# Design

## Context

见 proposal.md「Why」。硬约束：PHP 7.4 与 8.5 双绿（7.4 基线 53 tests / 547 assertions）；CI 不动；vendor85 以 docker 映射方式使用（用户惯例：vendorNN 目录按 PHP 版本并存，不删 vendor7）；提案基于当前工作区（用户未提交的 ORM.php many via / Session/None.php 等改动不回退）。本机无 php/cli，一切验证走 docker 容器（镜像为 amd64，arm64 Mac 有 warning 但可跑）。

关键结构事实：测试由根 `phpunit.xml` 驱动（bootstrap `lib/bootstrap.php`，后者执行 `Core::start()` 注册全局错误处理器——这是弃用通告在测试中被放大为异常的根因）；`composer.json` 是 gitignored 生成物，生成源为 `gini.json` 的 `composer` 段；vendor7 与 vendor85 的 doctrine/inflector 均为 2.x（分别位于 `lib/Doctrine/Inflector/` 与 `src/`），均含 `InflectorFactory`。

## Goals / Non-Goals

**Goals:**

- PHP 7.4 与 8.5 全量 phpunit 双绿（含新增守护测试）
- 错误处理器策略稳定且在两个版本下语义一致（含 @ 抑制语义）
- vendor85 双版本验证机制固化为可执行脚本，不污染工作区
- 守护测试延续 `ci/test/<Area>/<File>.php` + `\Gini\PHPUnit\TestCase\CLI` 既有范式

**Non-Goals:**

- P2 既有债务（Database.php:96 `?:` 优先级、Controller/CGI.php form() fall-through、Database/SQLite.php fetchObject 假值、App/Doctor.php null 追加、CGI/Router.php `__sleep`→`__serialize`）——与本变更修复点不同文件不同函数，全部剥离
- 测试顺序依赖（`a()` 定义于 ORM.php:1443 全局块，单跑 `testWithOne2OneRelationship` 挂，7.4 亦然）——归 complete-unit-tests change
- CI 配置改动、vendor8/vendor81 依赖树、支持 8.0–8.4 的声明（未验证）
- PHP 9 就绪（动态属性实移除、`#[\ReturnTypeWillChange]` 清理等）

## Decisions

### D1 错误处理器：handler 内路由（推荐）而非注册掩码排除

修改 `class/Gini/Core.php:411-418` 的 `Core::error()`：

```php
public static function error($errno, $errstr, $errfile, $errline)
{
    // error was suppressed with the @-operator
    // (PHP 7: error_reporting()==0; PHP 8: mask excludes the error)
    if (!(error_reporting() & $errno)) {
        return false;
    }
    // deprecations are advisory: log and continue
    if ($errno & (E_DEPRECATED | E_USER_DEPRECATED)) {
        Logger::of('core')->debug('DEPRECATED: {message} in {file} on line {line}', [
            'message' => $errstr, 'file' => $errfile, 'line' => $errline,
        ]);
        return true;
    }
    throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
}
```

两部分改动各有独立动因：

1. **掩码判定替代 `0 === error_reporting()`**：PHP 8 起 @ 不再把 error_reporting 置 0 而是置掩码 4437（不含 E_WARNING/E_DEPRECATED 等），旧恒等判定在 8.x 下会把被 @ 抑制的错误误抛为异常（例：`CGI.php:257` 的 `@json_decode` 一类模式）。掩码判定在 7.4 与 8.5 下均与引擎抑制语义一致。
2. **弃用降级**：`E_DEPRECATED | E_USER_DEPRECATED` 记日志后返回 true（已处理，不落默认错误输出）。vendor 与引擎层面的弃用通告（implicit nullable、null offset 等）在 8.5 大量存在，升级为异常会系统性放大。

备选方案（不采纳）：仅在 `Core.php:431` 注册处把 `E_DEPRECATED | E_USER_DEPRECATED` 从掩码排除——diff 更小，但弃用通告落回引擎默认处理、脱离框架日志管道；且单独这么做仍不修复 @ 误抛问题（被 @ 抑制的 E_USER_WARNING 仍在掩码内、仍会误抛），掩码判定修复无论如何都需要。

行为兼容性说明：7.4 下「非 @ 的弃用通告」从抛异常变为记 debug 日志——无测试钉住旧行为（已检索 `ci/test` 无 `Core::error|ErrorException|set_error_handler` 引用）；这是规格层面的有意变更（specs/error-handling）。

### D2 弃用通告日志通道：`Logger::of('core')->debug(...)`

选 debug 级：默认静默（不产生输出噪声），开启调试（`Logger::isDebugging`）时可见，为后续 PHP 9 迁移保留观测面。不选 notice/warning：8.5 下弃用量大，会淹没正常日志。

### D3 `Controller\API` 显式声明属性，不加 `#[AllowDynamicProperties]`

`class/Gini/Controller/API.php`（当前为空抽象类）声明 `public $app;` / `public $env;`，docblock 风格对齐 `Controller/CGI.php:34-49`。赋值点 `API.php:43-44,57-58` 不动。不采纳在类上加 `#[\AllowDynamicProperties]`：会掩盖该类未来其他动态属性误用；ORM 基类已带该属性是历史面，不构成先例。PHP 8.5 下动态属性仍会存储（弃用未移除），此修复同时消除弃用噪声并为 PHP 9 就绪。

### D4 OpenRPC 迁移 inflector 2.x，静态缓存实例

`Document/OpenRPC.php:8` 的 `use Doctrine\Common\Inflector\Inflector;`（1.x 命名空间，2.x 无此类）替换为：

```php
use Doctrine\Inflector\InflectorFactory;
```

`OpenRPC.php:76` 的 `Inflector::camelize($matches[2])` 改为经静态缓存实例调用：

```php
private static $inflector;
// ...
$name = strtr($class, ['\\' => '.']) . '.' . self::inflector()->camelize($matches[2]);

private static function inflector(): \Doctrine\Inflector\Inflector
{
    return self::$inflector ??= InflectorFactory::create()->build();
}
```

`??=` 自 7.4 可用；vendor7（lib/Doctrine/Inflector）与 vendor85（src/）均有 `InflectorFactory`，双版本可用。该修复同时是 7.4 下的潜在 Class not found 修复（当前 vendor7 同为 2.x，只是 OpenRPC 未被测试加载而未暴露）。不采纳 `class_alias` 兼容层：无版本需要它。

### D5 废弃调用清理：删 `curl_close`/`finfo_close`，反引号改 `shell_exec()`

- `HTTP.php:175,181`、`RPC.php:161,167`：删除 4 处 `curl_close($ch)`。curl 句柄自 8.0 起为引用计数对象，close 为 no-op 且 8.5 弃用；删除在 7.4 下仅把释放时机从显式提前到作用域结束，请求级生命周期无实质差异。
- `File.php:230`：删除 `finfo_close($finfo)`（8.5 弃用面，资源由引擎释放）。
- `Controller/CLI/App.php:179`、`Controller/CLI/Index.php:61,63`：反引号执行改 `shell_exec('...')`，语义等价（返回 stdout 字符串、依赖同一 shell_exec 能力），消除 8.5 扫描标记面并获得可静态分析的调用形式。

### D6 null/未定义键守卫族（逐点修法）

| 位置 | 修法 |
|---|---|
| `CGI.php:252` | `trim($_SERVER['PATH_INFO'] ?? $_SERVER['ORIG_PATH_INFO'] ?? '', '/')` |
| `CGI.php:253` | `explode(';', $_SERVER['CONTENT_TYPE'] ?? '', 2)[0]` |
| `CGI.php:110` | `$t['class'] ?? ''` |
| `CLI.php:192` | `$t['class'] ?? null`（保持 `== 'Gini\Core'` 比较语义） |
| `CLI.php:195` | `$t['file'] ?? ''` |
| `CLI.php:205` | `$t['class'] ?? ''` |
| `HTTP.php:104` | `$_SERVER['HTTP_HOST'] ?? ''`、`$_SERVER['REQUEST_URI'] ?? ''` |
| `HTTP.php:108` | `$header['content-type'] ?? ''` |
| `RPC.php:18` | `self::$_RPCs[$name] ?? null` |
| `RPC.php:20` | `$conf[$name]['url'] ?? null`（config 缺失时不炸，交由后续 connect 报错） |
| `RPC.php:65` | `$rpcTimeout[$method] ?? ($rpcTimeout['default'] ?? 5)`（兜底对齐 `post()` 默认值 5） |
| `URI.php:60` | `if (!empty($ui['pass']))`（相对 URL 无 user/pass 时的未定义键） |
| `Controller/CGI/Error.php:10` | `if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']))` |

### D7 `HTTP\Response::__construct` null 兜底

`HTTP/Response.php:13` 改为：

```php
list($header, $body) = array_pad(
    explode("\n\n", str_replace("\r", '', (string) $data), 2),
    2,
    ''
);
```

即先 `(string)` 化（curl 失败路径传 null），再用 `array_pad` 保证分隔符缺失/空串时 list 双元素齐备。覆盖三种输入：null/'' → header=''/body=''；无 `\n\n` → body=''；正常 → 原行为。7.4 等价安全。

### D8 `ORMIterator::valid()` null 守卫

`ORMIterator.php:238` 改为 `return null !== $this->current_id && isset($this->objects[$this->current_id]);`。`next()` 越界后 `key()` 返回 null 使 current_id=null，8.5 下 `isset($this->objects[null])` 触发「null 作数组偏移」弃用通告（实测错误点）。加 null 短路后结果集与原 isset 语义一致（`isset($arr[null])` 本就为 false）。

### D9 PHP 约束落 gini.json（生成源），同步 composer.json（生成物）

`gini.json` `composer.require` 增加 `"php": "^7.4 || ^8.5"`；根 `composer.json`（gitignored 生成物）手工同步同一约束，保证本地后续 composer 调用受平台约束。不声明 `^8.0`–`^8.4`：未经验证（双绿仅覆盖 7.4/8.5），诚实窗口就是 `^7.4 || ^8.5`。

### D10 vendor85 使用机制：`ci/dual-test.sh`

```sh
#!/bin/sh
# 双版本回归：PHP 7.4 (vendor7) 与 PHP 8.5 (vendor85) 各跑全量 phpunit
# 依赖 docker.genee.cn/php:{7.4,8.5}-alpine 镜像（amd64；arm64 主机可跑但有 warning）
set -e

cleanup() {
    [ -L vendor ] && rm -f vendor   # 仅清映射产生的符号链接，绝不动真实 vendor 目录
    rm -f cache/class_map.json      # 清掉容器内绝对路径的类映射缓存
}
trap cleanup EXIT

for v in 7.4 8.5; do
    echo "=== PHP $v (vendor$v) ==="
    docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:$v-alpine \
        sh -c "ln -sfn vendor$v vendor && vendor$v/bin/phpunit"
done
```

要点：容器内 `ln -sfn vendor$v vendor` 把版本化依赖树映射为 `Core.php:456` 期望的 `APP_PATH/vendor/autoload.php` 入口（用户惯例）；跑前跑后清 `cache/class_map.json`（旧构建含 /opt/php 绝对路径，失效致类加载崩）；`[ -L vendor ]` 保证只清理符号链接。单文件/单任务的验证命令同构：`docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:8.5-alpine sh -c "ln -sfn vendor85 vendor && vendor85/bin/phpunit <path-or-filter>"`（用后手工 `rm -f vendor cache/class_map.json`，或最后统一跑脚本）。README 增补「运行测试」节记录此跑法。

### D11 P2 债务全部剥离

P2 清单内各项与本次兼容修复点均不同文件不同函数，无「同文件同函数顺手修」情形，全部留待后续 change。

## Risks / Trade-offs

- **弃用降级可能掩盖真实迁移信号**：7.4/8.5 下弃用通告不再中断；缓解：debug 日志保留观测面，PHP 9 移除动态属性等属于独立变更。
- **掩码判定对 7.4 是行为收紧**：7.4 下若调用方把 error_reporting 设为非零但不含某错误位，旧行为抛异常、新行为抑制——新行为才与引擎默认语义一致，且无测试钉住旧行为。
- **删除 `curl_close`/`finfo_close` 在 7.4 下延迟资源释放**：请求级生命周期内无实质影响。
- **`ci/dual-test.sh` 依赖 docker 环境与内网镜像仓库**：环境不可达时属基础设施失败，不是代码回归；脚本以退出码区分。
- **8.5 下既有 5 个测试错误的归因复核**：`ORMIterator`（本变更修复）与顺序依赖（complete-unit-tests 范围）之外的 3 个错误预期随 D1 修复转绿；若仍有残留，按 scratchpad「实现探索」区基线流程归因后处置，不得静默放行。
