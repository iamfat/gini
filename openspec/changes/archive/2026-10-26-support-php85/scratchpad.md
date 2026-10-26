# support-php85 调研基线（主 agent 已验证，禁止重复探索）

## 目标
Gini 框架（genee/gini 2.3.1）支持 PHP 8.5，同时保持 PHP 7.4 兼容（双绿）。CI 不动。vendor85 通过 docker 映射使用（用户惯例：按 PHP 版本分目录 vendor7/vendor85，不删 vendor7）。

## 已验证事实（主 agent 实测）

### 测试运行方式（无需 DB/网络）
- PHP 7.4：`docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:7.4-alpine sh -c "ln -sfn vendor7 vendor; vendor7/bin/phpunit"` → **53 tests / 547 assertions 全绿**（0.3s）
- PHP 8.5：`docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:8.5-alpine sh -c "ln -sfn vendor85 vendor; vendor85/bin/phpunit"` → 48/53，5 errors
- 前置：跑前须删 `cache/class_map.json`（含旧构建绝对路径 /opt/php/...，失效）；cache/ 目录 gitignore
- vendor85 已装好：phpunit 9.6.38、sabre/dav 4.7.1、sabre/event 5.1.9、doctrine/inflector 2.1.0、doctrine/annotations 1.14.4、cron-expression v3.6.1、phar-io/manifest 2.0.4+（implicit-nullable 已修）
- vendor7/8/81：手工并存的版本化依赖树，源码零引用；运行时入口 `Core.php:456` require `APP_PATH/vendor/autoload.php`
- 本机无 php/cli，一切验证走 docker 容器（镜像 amd64 架构，arm64 Mac 有 warning 但可跑）

### PHP 8.5 下 5 个测试错误归因
1. `ORMIterator.php:238` `$this->objects[$this->current_id]` current_id=null → "Using null as an array offset is deprecated" 被 error handler 转异常。框架真 bug。
2. `testWithOne2OneRelationship` 单跑 `Call to undefined function Gini\PHPUnit\Those\a()` —— 既有测试顺序依赖（PHP 7.4 单跑同样挂，非 8.5 回归）：`a()` 定义于 ORM.php:1443 全局块，依赖先前的测试加载 ORM.php
3. 其余 3 个（testGet$/testInjection/testWithMany/testMeetAnyOf 全套跑时 E、单跑过）——跨测试状态污染，在 8.5 下被 deprecated 异常放大；修复 P0 后需复核归类

### 工作区状态
用户有未提交改动（进行中功能，提案基于当前工作区，**不得回退**）：ORM.php +99 行（many via / add() / remove()）、Session/None.php（SessionHandlerInterface 返回类型，8.1+ 适配）、Those.php、IDE.php、Those 测试 2 文件更新。

## PHP 8.5 问题清单（ScoutPhp85 全量扫描，路径:行号）

### P0（不修则 8.5 不可用）
1. `Core.php:431 + :411-418`：error handler 把 E_DEPRECATED/E_USER_DEPRECATED 抛成 ErrorException（系统性放大器）。修法：handler 对这两类降级（记日志返回，不抛），或掩码排除。注意保持 7.4 行为兼容。
2. `API.php:43-44,57-58`：给 `Controller\API` 子类动态赋 `$o->app/$o->env`；`Controller/API.php` 是空抽象类。修法：声明 `public $app; public $env;`（与 Controller/CGI.php:34-49 一致）。
3. `Document/OpenRPC.php:8,76`：`use Doctrine\Common\Inflector\Inflector` 是 inflector 1.x 命名空间，2.x 无此类 → Class not found。修法：`\Doctrine\Inflector\InflectorFactory::create()->build()->camelize()`。
4. `HTTP.php:175,181`、`RPC.php:161,167`：`curl_close()` 8.5 弃用，正常返回路径必触发 → 删除调用（句柄自动释放）。
5. 依赖锁定 vendor85 集合；gini.json 补 php 约束（`"php": "^7.4 || ^8.5"` 或 planner 定夺）；双版本验证跑法固化。

### P1
6. `File.php:230` 删 `finfo_close`；`Controller/CLI/App.php:179`、`Controller/CLI/Index.php:61,63` 反引号 → `shell_exec()`。
7. null/未定义键守卫族：`CGI.php:252`（PATH_INFO null→trim）、`CGI.php:107`、`CLI.php:191,198,205`、`HTTP.php:103,108,124`、`CGI.php:253`、`RPC.php:17,20,65`、`URI.php:51`、`Controller/CGI/Error.php:5`。
8. `HTTP/Response.php:16` 构造器对 null 入参兜底（curl 失败路径 `new HTTP\Response(null, 500)`）。
9. `ORMIterator.php:238` null offset 修复（可归入 P0 级测试阻塞项）。

### P2（既有债务，本 change 可分离，planner 决定是否纳入）
- `Database/SQLite.php:230` fetchObject 假值判断；`Database.php:96` `?:` 优先级 bug；`Controller/CGI.php:147-166` form() fall-through + array_merge 未赋值；`App/Doctor.php:130` null 追加；`CGI/Router.php:33-36` `__sleep` → `__serialize`（soft-deprecated 无告警）。
- ORM 基类已有 `#[\AllowDynamicProperties]`（ORM.php:20），动态属性面基本安全。

## 验证策略 [R1 主 agent]
- 双容器对照：7.4（vendor7）与 8.5（vendor85）各跑全量 phpunit，须全绿（53/547 基线 + 新增测试）
- 单跑抽查：`--filter testWithOne2OneRelationship` 在两版本下行为一致（顺序依赖问题若本 change 修则单跑也绿）
- 已知环境性失败集：无（7.4 基线全绿）；cache/class_map.json 路径失效问题跑前清理即可

## 调研与设计 [R1 planner 补充]
- [R1 planner] 错误处理器推荐方案（design D1）：`Core::error` 内掩码判定 `!(error_reporting() & $errno)` 替代 `0 === error_reporting()`（PHP 8 下 @ 置掩码 4437 非零，旧判定会把被 @ 抑制的错误误抛为异常，如 CGI.php:257 `@json_decode` 模式）+ E_DEPRECATED/E_USER_DEPRECATED 经 `Logger::of('core')->debug` 降级返回 true；备选（注册掩码排除）不采纳：脱离框架日志管道且单独不修 @ 误抛。已检索 `ci/test` 无 `Core::error|ErrorException|set_error_handler` 引用，无测试钉住旧行为
- [R1 planner] scratchpad 行号漂移修正：P1 清单 URI.php:51 实际未定义键点为 `class/Gini/URI.php:60`（`$ui['pass']`，scheme 处 :54 已有 `??` 守卫）；Controller/CGI/Error.php:5 实际为 :10（`$_SERVER['HTTP_X_REQUESTED_WITH']`）
- [R1 planner] `composer.json` 是 gitignored 生成物（.gitignore 含 `composer.json`），生成源为 `gini.json` composer 段：php 约束落 gini.json 为主，composer.json 本地同步（design D9）
- [R1 planner] vendor7 与 vendor85 的 doctrine/inflector 均为 2.x 且均含 `Doctrine\Inflector\InflectorFactory`（vendor7: lib/Doctrine/Inflector/，vendor85: src/）——OpenRPC 迁移 2.x API 后双版本可用；当前 OpenRPC 的 1.x 命名空间在 7.4 下同为潜在 Class not found（未被测试加载而未暴露）
- [R1 planner] `vendor` 符号链接不被 .gitignore `/vendor/`（目录式）覆盖（`git check-ignore vendor` exit 1）——ci/dual-test.sh 设计为 trap 自清（`[ -L vendor ] && rm -f vendor`），避免工作区污染
- [R1 planner] 测试由根 phpunit.xml 驱动，bootstrap `lib/bootstrap.php` 执行 `Core::start()` 注册错误处理器——这是弃用通告在测试中被放大为异常的根因；新守护测试落 `ci/test/Core/Error.php`、`ci/test/Controller/API.php`、`ci/test/Document/OpenRPC.php`、`ci/test/HTTP/Response.php`、`ci/test/ORM/ORMIterator.php`（+7 用例，双绿预期 60 tests）
- [R1 planner] P2 债务全部剥离（design D11）：清单各项与兼容修复点均不同文件不同函数，无顺手修情形

## 提案审查关注点

- [R1 proposal-reviewer→coder] P2-1（建议采纳，已实现）：dual-test.sh 开头加真实 vendor 目录防御 `[ -e vendor ] && [ ! -L vendor ] && exit 1`
- [R1 proposal-reviewer→coder] P2-2（建议采纳，已实现）：新增测试类基类显式声明 `extends \Gini\PHPUnit\TestCase\CLI`（ErrorHandler/API/OpenRPC/Response 均显式）

## 实现探索

- [R1 coder] ci/test/Core/Error.php → ErrorHandler.php 改名勘误（主 agent 裁决选 A）：PHPUnit 9.x（vendor7 9.5.21 / vendor85 9.6.38）文件直传以基名作类名，先 `class_exists('Error', false)` 命中 PHP 内建类 `\Error`，文件不会被加载（7.4 报 "Class 'Error' could not be found"、8.5 "No tests executed"）；类/文件改名 `ErrorHandler` 后双版本文件直传 OK。tasks.md 1.2 与 proposal.md Impact 字面量已同步，design 无该字面量；spec 未命名文件
- [R1 coder] supersede: design D10 脚本字面量 `vendor$v`（v=7.4 → vendor7.4）与实际版本化目录 vendor7/vendor85 不符——实现改为显式映射对 `"7.4 vendor7" "8.5 vendor85"`；同时采纳 proposal-reviewer P2-1 防御（真实 vendor 目录存在则拒跑）与 P2-2（新测试基类均显式 `extends \Gini\PHPUnit\TestCase\CLI`）
- [R1 coder] 验证命令经验：phpunit CLI 位置参数只有第一个是 path（第二个被当 filter），传目录会 "No tests executed"——scoped 验证必须逐文件跑（容器内 `for f in ci/test/<Area>/*.php`）或全量无参跑（directory suite 发现正常）。单次容器 phpunit 约 0.02-0.05s（amd64 模拟）
- [R1 coder] 6.1 守护测试有效性已实证：临时把 valid() 还原为单 isset 时，testValidBeyondEnd 在 8.5 捕获 3 条 "Using null as an array offset is deprecated"（foreach 收尾 valid + 2 次显式调用）失败；恢复守卫后双版本 OK
- [R1 coder] PHPUnit 自带 ErrorHandler 注册时发现已有处理器会自行让出（register() 见 oldErrorHandler!==null 即 restore），故测试期间生效的全局处理器就是 Gini Core::error，1.2 测试前提成立；Logger::of('core') 经 SysLog(level=warning) 构造，debug 被 isLoggable 拦截，测试无副作用
- [R1 coder] 8.1 终局验收实测（ci/dual-test.sh）：PHP 7.4 与 8.5 均 `OK (60 tests, 568 assertions)`，0 failures / 0 errors；容器内耗时各 0.13s，整脚本 wall 1.1s（docker 热启动）。基线 53 tests/547 assertions + 新增 7 用例（ErrorHandler 3 / API 1 / OpenRPC 1 / Response 1 / ORMIterator +1，合计 +21 assertions）。8.5 原 5 errors 清零，未出现新归因项；残留 grep：curl_close(/finfo_close( = 0、Doctrine.Common.Inflector = 0、CLI 两文件反引号 = 0（class/ 内其余反引号均为正则分隔符）；sh -n 通过、vendor 符号链接与 class_map.json trap 自清已验证
- [R1 coder] 基线: docker 容器 stderr 平台 warning（"The requested image's platform linux/amd64 does not match linux/arm64/v8"）→ amd64 镜像在 arm64 Mac 经 Rosetta 运行的架构提示，测试结果不受影响（对照计数 12+ scoped 跑 + 1 终局跑均同此 warning 且全绿），验证输出时以 grep -v 滤除

## 代码审查范围

- （待 code-reviewer 首轮报告回流）

## 实现探索追加 [R1 主 agent]（Loop 2 终验基线）
- 验证策略：终局门槛 = `ci/dual-test.sh`（仓库根调用，双容器全量）+ 残留 grep（curl_close/finfo_close/Doctrine.Common.Inflector/CLI 反引号执行归零）+ `openspec validate support-php85 --strict` + `sh -n ci/dual-test.sh`。单文件 scoped 验证须逐文件传参（phpunit CLI 多位置参数只有首个是 path，[R1 coder] 已记）。
- 基线条目：PHP 7.4（vendor7, PHPUnit 9.5.21）与 PHP 8.5（vendor85, PHPUnit 9.6.38）均 `OK (60 tests, 568 assertions)`，0 failures / 0 errors，脚本退出码 0；8.5 原 5 errors 清零。容器内单版本全量 ~0.13s。无环境性失败集；docker amd64-on-arm64 平台 warning 为噪音（已归因）。后续 change（complete-unit-tests）的基线钉扎应取 60/568。
