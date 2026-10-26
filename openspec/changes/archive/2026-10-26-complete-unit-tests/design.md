# Design

## Context

现状基线（详见 scratchpad 与 proposal）：53 例 / 547 断言 / 20 文件（support-php85 落地后终态 60 例），命名空间 `Gini\PHPUnit\<子目录>`，全部 extends `\Gini\PHPUnit\TestCase\CLI`；数据库 mock 范式成熟（`getMockBuilder('\Gini\Database')` + `IoC::bind` + 完整 SQL 断言 + tearDown `IoC::clear()`）但样板在 7 个文件重复。`lib/bootstrap.php` 是**生产共享入口**（`bin/gini` → `lib/cli.php`、`lib/cgi.php` 均加载它，gini.json `build.copy` 含 bin/lib），phpunit.xml 也以它为 bootstrap。本机无 php/cli，一切验证走 docker 双容器（7.4+vendor7 / 8.5+vendor85），跑前需删 `cache/class_map.json`。本 change 依赖 `support-php85` 先落地（双绿基础、vendor85、终态 60 例基线、其 design D4 的 OpenRPC inflector 2.x 迁移）。工作区有未提交的 ORM.php `add()`/`remove()`（many-pivot，+99 行），作为被测对象纳入 B 级，不回退。

## Goals / Non-Goals

**Goals:**

- A 级纯函数全补 + B 级核心模块补测，全量用例数从 60（support-php85 终态）提升至 ≥100，双版本绿。
- 修复 4 类既有测试质量瑕疵：顺序依赖（`a()`）、mock 样板重复、CGI 目录命名空间笔误（General.php + Middlewares.php）与 ORMIterator.php 命名空间归位、REST/Remote.php 覆盖率导向巨型用例。
- 把测试基架契约（基类、mock 范式、testsuite 结构、顺序无关、双版本一致）固化为 spec，后续补测有统一范式可循。

**Non-Goals:**

- C 级不做：真 PDO/MySQL、phpredis、HTTP/RPC curl 传输层（不为此重构注入）、活动 Session、I18N locale、CLI main @app 分支、内置 CLI 命令、App/Doctor。
- 不为可测性重构任何生产代码：`lib/bootstrap.php` 零改动；不把 ORM.php/Those.php 全局函数挪独立文件。
- 不引入新生产代码依赖；不设覆盖率百分比门槛（以 scratchpad A/B 清单逐方法覆盖为准）。
- 不强制迁移既有 20 个测试文件到新轻基类（等价收益为零的 churn 不做）。

## Decisions

### D1 mock 样板去重：抽 `MockDatabase` trait（决策点 1 → 做）

- 位置 `class/Gini/PHPUnit/MockDatabase.php`，namespace `Gini\PHPUnit`，提供标准 `\Gini\Database` 替身构造（`disableOriginalConstructor` + `setMethods(['query','quote','quoteIdent','ident',…])`）与 IoC 绑定/清理辅助。
- 理由：7 个文件现含 setMethods 样板（Those/Those|Meet|WhoAre|SubField|Those-ORM、ORM/ORM、ORM/ORMIterator），B 级还会新增数十处；与既有 `TestCase\CLI`/`TestCase\CGI` 同目录同 namespace，是框架既有的测试支撑类落点，PHPUnit 依赖已被这些基类引用，不新增依赖面。
- 迁移为**等价重构**：`\Gini\Database` mock 样板归一到 trait，SQL 断言字符串逐字保持，双容器全量对照兜底。非 `\Gini\Database` 类的 mock（如 Those/Those.php:68 的 `\Gini\Database\Statement`、REST/Remote 的 `\Gini\HTTP`）保留原地不迁移。
- 被否方案：helper 基类——PHP 单继承下会占用/冲突既有 `TestCase\CLI` 继承位，trait 是唯一不占继承位的选择。

### D2 纯逻辑用例新增轻基类 `Gini\PHPUnit\TestCase\Unit`（决策点 2 → 新增）

- 直接 extends `\PHPUnit\Framework\TestCase`，无 `setUpBeforeClass`/`tearDownAfterClass` 环境副作用；A 级与无环境依赖的 B 级用例使用。位置 `class/Gini/PHPUnit/TestCase/Unit.php`。
- 理由：`\Gini\CLI::setup()` 有 chdir/env 副作用，纯函数断言不应承担该成本与隐性顺序耦合；若沿用 CLI 基类，会把副作用传播给几十个新用例。
- 既有用例不迁移（见 Non-Goals）。

### D3 `a()` 顺序依赖修复：新建测试专用 bootstrap `ci/bootstrap.php`（决策点 3 → 本 change）

- 实现：新建 `ci/bootstrap.php`，内容为 `require lib/bootstrap.php`（复用既有环境定位与 `Core::start()`）后 `require_once` `class/Gini/ORM.php` 与 `class/Gini/Those.php`；`phpunit.xml` 的 `bootstrap` 属性由 `./lib/bootstrap.php` 改指 `./ci/bootstrap.php`。**`lib/bootstrap.php` 零改动**。
- 理由：属测试基建修复，support-php85 已收口为「运行时兼容」，塞入会模糊其边界；测试 bootstrap 是唯一被允许恒加载全局函数块的位置。
- 被否方案①：直接改 `lib/bootstrap.php`——它是生产共享入口（`bin/gini:4` → `lib/cli.php:5` → `lib/bootstrap.php`；`lib/cgi.php:7` 以 `GINI_MUST_CACHE_AUTOLOAD` 急求 autoload 缓存后加载它），改动会使生产每请求急加载 ORM.php/Those.php（破坏惰性 autoload 设计），并把 Those.php/ORM.php 的 `function_exists die()` 守卫从「用到才评估」变成「每请求必然评估」——下游模块定义同名全局函数即全线 die，且该回归测试验收测不出。
- 被否方案②：把 `a()`/`those()` 族挪独立恒加载文件——违反「不为可测性重构生产代码」红线。
- 安全性：require 仅发生在测试上下文；ORM.php 全局块的 `$app->register('orm',…)` 有 `is_subclass_of($app, '\Gini\Module\Prototype')` 守卫，测试环境 app 为空 `Gini\Application` 类，不触发；`require_once` 幂等。

### D4 phpunit.xml 不拆 testsuite（决策点 4 → 维持单 `gini` suite）

- 理由：C 级整体排除后 `ci/test` 全部用例皆 unit 级（60 例亚秒级），不存在 integration 层；拆出空 integration suite 不产生选择能力，纯增维护面。scoped 验证以 `--filter` 与测试文件路径参数实现。
- suite 结构契约同步固化进 spec：单 `gini` suite 覆盖 `ci/test`，suffix `.php`。
- 本 change 对 phpunit.xml 的唯一改动是 D3 的 bootstrap 属性指向。

### D5 质量瑕疵修复并入本 change（决策点 5）

- `ci/test/CGI/General.php:4` 与 `ci/test/CGI/Middlewares.php:3` 命名空间笔误同修：`Gini\PHPUnit\TestCase\CGI` → `Gini\PHPUnit\CGI`（均保持 extends CLI 基类——现状即绿，不引入环境切换）。
- `ci/test/ORM/ORMIterator.php:3` 命名空间归位：`Gini\PHPUnit\Those` → `Gini\PHPUnit\ORM`（与所在 ORM/ 目录的布局契约一致；纯 namespace 声明改动，类名与内部引用不变，PHPUnit 按路径加载不受影响）。
- `ci/test/REST/Remote.php`：`testDefault` 串行调用 13 个 private `can*()` 拆为 13 个独立 public test 方法，IoC bind/clear 收敛到 setUp/tearDown。
- 其余两类瑕疵即 D1（样板去重）与 D3（顺序依赖）。

### D6 补测批次与依赖标注

- 批次顺序：基建（1.x）→ A 级（2.x）→ B 级（3.x）→ 终验（4.x）；A 级不依赖基建完成即可并行开工，B 级依赖 D1 trait 与 D3 bootstrap 修复先行。
- `support-php85` 为整 change 前置（双绿基础、终态 60 例基线）；其中 3.10（Document/OpenAPI+OpenRPC 夹具）额外依赖其 design D4（OpenRPC inflector 2.x 命名空间迁移），若未落地仅阻塞该任务不阻塞其余批次。support-php85 交割的 `ci/test/HTTP/Response.php`（1 例）与 `ci/test/Document/OpenRPC.php`（1 例）在本 change 为**扩展**项，既有用例保留。
- 未提交 ORM `add()`/`remove()`：以工作区当前形态为基线，断言三分支（object pivot / 无 object pivot 走 `field_name` / 普通 field）完整 INSERT/DELETE SQL 与 `BadMethodCallException` 守卫；用户后续演进时 SQL 断言随动（测试守护新功能正是目的）。
- 基线钉扎：开工时先双容器全量实测 N/M 记入 scratchpad「实现探索」区作为基线条目（预期 60 tests），后续验收计数以该基线为对照。

## Risks / Trade-offs

- **测试 bootstrap 提前加载 ORM/Those**：仅测试上下文，生产零影响；若测试环境其他库定义同名全局函数，ORM.php 的 `function_exists` die() 守卫会在启动即显式失败——显式失败优于隐性顺序依赖，接受。
- **phpunit.xml bootstrap 指向变更**：CI 或本地脚本若硬编码 `vendor/bin/phpunit -c phpunit.xml` 以外的方式直跑 `lib/bootstrap.php`，需同步 awareness——以 `git diff phpunit.xml` 审查面覆盖。
- **trait 迁移触碰 7 个既有文件**：等价重构 + 双容器全量对照计数兜底；任何断言变化即视为回归。
- **行号锚点漂移**：A/B 清单的 `path:line` 锚点取自 scratchpad 基线（planner 已复核 add=ORM.php:1067、remove=:1112、saveAll=:1224、removeAll=:1405、delete=:582、fetch=:192、事务 save():673 起），工作区继续演进时以符号名定位为准，行号仅作参考。
- **用例数翻倍后的运行时长**：全部 unit 级 + 临时目录夹具，预期仍在秒级；不为此引入并行配置。
