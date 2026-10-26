# Tasks

前置依赖：`support-php85` 先落地（vendor85 可用、双版本绿、Document/OpenRPC inflector 2.x 迁移即其 design D4）。开工前先读 `openspec/changes/complete-unit-tests/scratchpad.md`（A/B 级清单带行号锚点、mock 范式、验证策略），禁止重复探索。

验收命令约定（本机无 php/cli，一律走 docker 双容器；源自 support-php85 scratchpad 已实测跑法）：

```bash
T74() { rm -f cache/class_map.json; docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:7.4-alpine sh -c "ln -sfn vendor7 vendor; vendor7/bin/phpunit $1"; }
T85() { rm -f cache/class_map.json; docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:8.5-alpine sh -c "ln -sfn vendor85 vendor; vendor85/bin/phpunit $1"; }
```

基线钉扎：开工时先跑 `T74 ''` 与 `T85 ''`，把实测 `N tests, M assertions` 记入 scratchpad「实现探索」区作为基线条目（support-php85 落地后预期 N=60）。下文「基线 N/M」均指该实测值；1.1–1.3 的验收是计数与基线**完全一致**（零回归）。迭代中只跑 scoped 目标（`T74 'ci/test/<路径>'` 或 `--filter`），交付前才跑 4.1 全量门槛。

## 1. 测试基建（先行；1.2/1.3 为 B 级与 A 级新文件的前置）

- [x] 1.1 修复全局函数顺序依赖（测试专用 bootstrap）：新建 `ci/bootstrap.php`（`require` lib/bootstrap.php 后 `require_once` `class/Gini/ORM.php` 与 `class/Gini/Those.php`，使尾部 `namespace { }` 块的 `a()/an()/those()/whose()/anyOf()/allOf()/whoAre()` 恒定义）；`phpunit.xml` 的 `bootstrap` 属性由 `./lib/bootstrap.php` 改为 `./ci/bootstrap.php`；**`lib/bootstrap.php` 零改动**。验收：`T74 '--filter testWithOne2OneRelationship'` 与 `T85 '--filter testWithOne2OneRelationship'` 均输出 `OK (1 test, …)`；`T74 ''` 与 `T85 ''` 均为 `OK (N tests, M assertions)`（与基线一致）；`git diff lib/bootstrap.php` 输出为空
- [x] 1.2 新增 `class/Gini/PHPUnit/TestCase/Unit.php`（extends `PHPUnit\Framework\TestCase`，无 setup/shutdown 副作用）与 `class/Gini/PHPUnit/MockDatabase.php` trait（`\Gini\Database` 替身构造：`disableOriginalConstructor` + `setMethods(['query','quote','quoteIdent','ident',…])` + IoC 绑定/清理辅助）；迁移现有样板文件（Those/Those.php、Those/Meet.php、Those/WhoAre.php、Those/SubField.php、Those/ORM.php、ORM/ORM.php、ORM/ORMIterator.php）中的 `\Gini\Database` mock 到 trait，SQL 断言字符串逐字不变；非 `\Gini\Database` 类的 mock（如 Those/Those.php:68 的 `\Gini\Database\Statement`、REST/Remote 的 `\Gini\HTTP`）保留原地。验收：`T74 ''` 输出 `OK (N tests, M assertions)`（与基线完全一致，等价重构）；`grep -rn "getMockBuilder('\\\\Gini\\\\Database')" ci/test/ | wc -l` 输出 `0`（精确匹配，不误伤 `\Gini\Database\Statement` 等）
- [x] 1.3 命名空间修复三处：`ci/test/CGI/General.php:4` 与 `ci/test/CGI/Middlewares.php:3` 的 `namespace Gini\PHPUnit\TestCase\CGI` → `namespace Gini\PHPUnit\CGI`（均保持 extends `\Gini\PHPUnit\TestCase\CLI`）；`ci/test/ORM/ORMIterator.php:3` 的 `namespace Gini\PHPUnit\Those` → `namespace Gini\PHPUnit\ORM`（与目录布局一致，类名与引用不变）。验收：`T74 '--filter testParameters'` 输出 `OK`；`T74 ''` 仍与基线 N/M 一致
- [x] 1.4 拆分 `ci/test/REST/Remote.php` 覆盖率导向巨型用例：`testDefault` 串行调用的 13 个 private `can*()` 拆为独立 public test 方法，`\Gini\HTTP` 的 IoC bind/clear 收敛到 setUp/tearDown。验收：`T74 ''` 输出 `OK (N+12 tests, M assertions)`（基线 − 1 + 13，断言数不变仅重组；基线 60 时为 72）；`T74 '--filter testForm'` 单跑 `OK`

## 2. A 级：纯函数全补（新用例一律 extends `Gini\PHPUnit\TestCase\Unit`；既有文件一律追加，保留既有用例）

每项通用验收：`T74 '<文件路径>'` 与 `T85 '<文件路径>'` 均输出 `OK (n tests, m assertions)` 且无 warning；`grep -c "public function test" <文件>` ≥ 该项给出的 test 方法数下界；断言覆盖 scratchpad 清单列出的每个方法与分支（含边界：空输入/null/非法值）。

- [x] 2.1 新建 `ci/test/Core/File.php`：humanReadableBytes(`File.php:43`)、relativePath(`:141`)、inPaths(`:172`)、extension(`:190`)，4 方法 + 字节单位/越界路径边界。验收：通用验收（test 方法 ≥ 4）
- [x] 2.2 扩展 `ci/test/Core/Util.php`（既有 testGetOpt/testSingularize 保留）：arrayMergeDeep(`Util.php:23`)、arrayReplaceKeys(`:9`)、pathAndArgs(`:68`)、hyphenate(`:208`)、pluralize(`:201`)、randPassword(`:43`)。验收：通用验收（test 方法 ≥ 8 = 既有 2 + 新增 6；randPassword 断言长度/字符集而非具体值）
- [x] 2.3 扩展 `ci/test/Core/Event.php`（既有 testBind 保留）：权重排序 `_sort`(`Event.php:31`)、pass(`:49`)/abort(`:54`)、多事件 trigger(`:172`)、isBinded(`:201`)。验收：通用验收（test 方法 ≥ 6 = 既有 1 + 新增 5）
- [x] 2.4 扩展 `ci/test/Core/Config.php`（既有 testGet 保留）：get/set/append/import/export/clear 全方法。验收：通用验收（test 方法 ≥ 7 = 既有 1 + 新增 6）
- [x] 2.5 新建 `ci/test/CGI/Validator.php`（`CGI/Validator.php:20` 规则链）与 `ci/test/Helper/TouchableArray.php`（ArrayAccess 语义）。验收：通用验收（两文件合计 test 方法 ≥ 5）
- [x] 2.6 扩展 `ci/test/HTTP/Response.php`（support-php85 task 5.2 交割，既有 testConstructWithNullData 保留；`HTTP/Response.php:11-37` 报文解析/status 正则补全）、新建 `ci/test/Core/I18N.php`（stripContext 边界）、扩展既有 `ci/test/Core/URI.php`（URL/RURL 边界用例追加）。验收：通用验收（3 文件；扩展文件为追加后全文件绿）
- [x] 2.7 新建 `ci/test/Database/SQL.php` 与 `ci/test/Database/Unknown.php`（`Unknown.php:12` quoteIdent），扩展既有 `ci/test/ORM/Database.php` 补 `Database.php:191` prepareSQL 扩展分支。验收：通用验收（3 文件）
- [x] 2.8 新建 `ci/test/Logger/Level.php`（Level 全级 + `Handler.php:33` isLoggable + Logger 占位符插值）与 `ci/test/Session/None.php`（全方法：无操作但返回值契约）。验收：通用验收（2 文件，test 方法合计 12 ≥ 8）【主 agent 裁决：回退 `gc(): int|false` 联合类型为无声明，7.4 可解析、8.1 tentative 仅 E_DEPRECATED 已被 D1 降级；`close(): bool` 等其余标量类型保留，双版本绿】
- [x] 2.9 扩展既有 `ci/test/CLI/Env.php`：normalize(`Env.php:39`)/stripQuote(`:29`) 的扩展分支（现有 1 例基础上追加）。验收：通用验收（追加后全文件绿，test 方法 ≥ 3）

## 3. B 级：核心模块补测（mock 范式 = MockDatabase trait + IoC bind + 完整 SQL 断言 + tearDown clear）

- [x] 3.1 扩展 `ci/test/ORM/ORM.php`：delete(`ORM.php:582`)/forceDelete(`:567`)/fetch(`:192`) 的 `__call orm[$name].$action` 事件分发（`:80-92`）、casting、save() 内事务（`:673` 起，beginTransaction `:686`）、saveAll(`:1224`)/removeAll(`:1405`) 中间表；**工作区未提交的 `add()`(`:1067`)/`remove()`(`:1112`) 三分支**（object pivot INSERT / 无 object pivot 走 `field_name` / 普通 field many）+ 未注册 many 名的 `BadMethodCallException` 守卫，断言完整 INSERT/DELETE SQL。验收：`T74 'ci/test/ORM/ORM.php'` 与 `T85 'ci/test/ORM/ORM.php'` 均 `OK (32 tests, 81 assertions)`；add/remove 各 3 用例 + 异常各 1 例（grep 实证）
- [x] 3.2 扩展 `ci/test/ORM/ORMIterator.php`：count/offsetSet/totalCount/分页/setFetchFlag。验收：`T74 'ci/test/ORM/ORMIterator.php'`、`T85` 同跑均 `OK (7 tests, 29 assertions)`（+5 用例；附 FetchFlagIterator 夹具暴露 protected 标志位）
- [x] 3.3 新建 `ci/test/Those/Whose.php` 与 `ci/test/Those/SQLHelper.php` 直接用例（现仅间接覆盖；SQL 断言复用 trait）。验收：双版本逐文件均 `OK`（Whose 6 tests/28 assertions；SQLHelper 12 tests/28 assertions；phpunit 不接受目录位置参数，Those/ 全量在 4.1 跑）
- [x] 3.4 新建 `ci/test/CGI/Router.php`：fromJSON(`Router.php:90`)、via(`:78`)、cleanUp(`:295`)、dispatch 404 与参数(`:227`)。验收：`T74 'ci/test/CGI/Router.php'`、`T85` 同跑均 `OK (13 tests, 37 assertions)`（含 404 fallback 断言 + 路由参数 + 嵌套 group + 中间件合并去重）
- [x] 3.5 新建 `ci/test/Controller/CGI.php` 与 `ci/test/Controller/CLI.php`：execute 前后置链（`Controller/CGI.php:114`、`Controller/CLI.php:43`）、functionArguments(`CGI.php:126`)、defaultEnv(`:51`)。验收：两文件双版本 `OK`（夹具 controller 置于同文件）
- [x] 3.6 `ci/test/REST/Remote.php` 补强 + `REST.php` 异常分支：mock `\Gini\HTTP` 返 4xx/5xx/坏 JSON，断言异常类型与消息。验收：`T74 'ci/test/REST/Remote.php'` 双版本 `OK`（新增异常用例 ≥ 3）
- [x] 3.7 新建 `ci/test/Logger/Handler.php`：handlers 分发（虚拟 handler 或 ob_start 捕获 Console 输出断言）。验收：双版本该文件 `OK`
- [x] 3.8 新建 `ci/test/Core/View.php`：临时目录 + view_map 夹具（PHTML/Javascript 两引擎渲染输出断言，测试后清理临时目录）。验收：双版本 `OK`；夹具目录在 tearDown 后不存在（`test ! -e <tmpdir>` 或用例内 assert）
- [x] 3.9 新建 `ci/test/App/Cache.php`：临时目录夹具覆盖 _cacheClass/_cacheView/_cacheConfig。验收：双版本 `OK`
- [x] 3.10 扩展 `ci/test/Document/OpenRPC.php`（support-php85 task 3.1 交割，既有 testCamelizeMethodName 保留）与新建 `ci/test/Document/OpenAPI.php`：反射 + 注解夹具类生成文档断言【硬依赖 support-php85 design D4 inflector 2.x 迁移；未落地时本项 STATUS: blocked，不阻塞其余批次】。验收：双版本两文件 `OK`（OpenAPI ≥ 2 夹具类断言，OpenRPC 在既有 1 例上追加）
- [x] 3.11 新建 `ci/test/Core/API.php`：dispatch 错误分支补充（未知 method/异常路径）。验收：双版本 `OK`

## 4. 终验（交付门槛）

- [x] 4.1 双容器全量绿 + 顺序无关验证：`T74 ''` 输出 `OK (N tests, M assertions)` 且 N ≥ 100；`T85 ''` 输出 `OK (N tests, …)` 且两版本 N 完全一致、零 failure/error/warning；`T74 '--filter testWithOne2OneRelationship'` 与 `T85 '--filter testWithOne2OneRelationship'` 均单跑 `OK (1 test, …)`；逐测试文件单跑 × 双版本全绿：`for f in $(find ci/test -name '*.php'); do T74 "$f" >/dev/null || echo "FAIL74 $f"; T85 "$f" >/dev/null || echo "FAIL85 $f"; done` 输出为空（无 FAIL 行）；把实测 N/M 与耗时 append 进 scratchpad「实现探索」区（基线条目格式）【实测：双版本 `OK (270 tests, 1131 assertions)` 各 ~0.4s；filter 双绿；42 文件逐个双版本零 FAIL（`find ci/test -name '*.php' | wc -l` = 42，原记 39 为误计数）；`--order-by=random` seeds 1/42/999 双版本均 `OK (270 tests, 1131 assertions)`、零 error（R2 为 Core/I18N 的全局 `T()` 补预加载后测得）；validate --strict 通过；详见 scratchpad 4.1 条目】
