# Proposal

## Why

Gini 框架（2.3.1）需要在 PHP 8.5 运行时上部署（镜像 docker.genee.cn/php:8.5-alpine 已就绪），但当前代码存在系统性 8.5 阻塞：全局 error handler 把 E_DEPRECATED 升级为异常（放大器）、Document/OpenRPC 引用已不存在的 doctrine/inflector 1.x API、在抽象 `Controller\API` 上动态赋属性、以及一批 null/未定义键访问。实测 PHP 8.5 下测试套件 48/53（5 errors），PHP 7.4 基线 53 tests / 547 assertions 全绿。本变更目标是 7.4 与 8.5 双绿，同时保持 7.4 行为兼容。

## What Changes

- 错误处理策略：`\Gini\Core::error` 对 `E_DEPRECATED` / `E_USER_DEPRECATED` 降级为 debug 日志、不再抛 `ErrorException`；同时把 @-operator 抑制判定从 `0 === error_reporting()` 修正为掩码判定 `!(error_reporting() & $errno)`（PHP 8 起 @ 不再把 error_reporting 置 0，旧判定在 8.x 下会误抛被抑制的错误）
- `\Gini\Controller\API` 声明 `public $app; public $env;`（对齐 `Controller\CGI` 既有属性声明模式），消除 API dispatch 动态属性弃用
- `\Gini\Document\OpenRPC` 迁移到 doctrine/inflector 2.x API（`InflectorFactory::create()->build()->camelize()`，静态缓存实例；vendor7 与 vendor85 均为 2.x，均有 `Doctrine\Inflector\InflectorFactory`）
- 删除 `curl_close()` 调用（HTTP.php:175,181、RPC.php:161,167；8.0 起 no-op、8.5 弃用）与 `finfo_close()`（File.php:230）；`Controller/CLI/App.php:179`、`Controller/CLI/Index.php:61,63` 反引号运算符改为 `shell_exec()`
- null/未定义键守卫族修复：CGI.php（PATH_INFO/ORIG_PATH_INFO、CONTENT_TYPE、trace `class` 键）、CLI.php（trace `class`/`file` 键）、HTTP.php（HTTP_HOST/REQUEST_URI、`_header['content-type']`）、RPC.php（`$_RPCs[$name]`、`$conf[$name]['url']`、timeout 兜底）、URI.php（`$ui['pass']`）、Controller/CGI/Error.php（HTTP_X_REQUESTED_WITH）
- `HTTP\Response::__construct` 对 null 入参兜底（curl 失败路径 `new HTTP\Response(null, 500)`）；`ORMIterator::valid()` 对 `current_id === null` 守卫
- 依赖契约：gini.json（composer.json 的生成源，composer.json 本身 gitignored）`composer.require` 声明 `"php": "^7.4 || ^8.5"`，本地生成物 composer.json 同步
- 新增 `ci/dual-test.sh`：docker 双版本（7.4→vendor7、8.5→vendor85）全量 phpunit 回归脚本（跑前清 cache/class_map.json，跑后清理 vendor 符号链接）；README 增补双版本测试跑法
- 新增守护测试（延续 ci/test 现有 mock 范式）：error handler 策略、`Controller\API` 属性声明、OpenRPC camelize、`HTTP\Response` null 兜底、`ORMIterator::valid` 越界

明确剥离（out of scope）：P2 既有债务（Database.php:96 `?:` 优先级、Controller/CGI.php form() fall-through、Database/SQLite.php fetchObject 假值、App/Doctor.php null 追加、CGI/Router.php `__sleep`）——与本变更修复点不同文件不同函数，不顺手扩大；测试顺序依赖（`a()` 定义于 ORM.php 全局块，单跑 `testWithOne2OneRelationship` 挂）归 complete-unit-tests change；CI 配置不改动；vendor8/vendor81 依赖树不动。

## Capabilities

### New Capabilities
- `error-handling`: 框架全局错误升级策略——哪些错误级别升级为异常、哪些降级记录、@-operator 抑制语义在支持窗口内保持一致
- `dependency-contract`: PHP 支持窗口声明、依赖 API 版本约束（inflector 2.x）、废弃运行时调用禁用、双版本（vendorNN 映射）验证机制

### Modified Capabilities

（无——`openspec/specs/` 当前为空，无既有 capability 需要修改。）

## Impact

- 源码（14 文件）：`class/Gini/Core.php`、`class/Gini/CGI.php`、`class/Gini/CLI.php`、`class/Gini/HTTP.php`、`class/Gini/RPC.php`、`class/Gini/File.php`、`class/Gini/URI.php`、`class/Gini/Controller/API.php`、`class/Gini/Controller/CGI/Error.php`、`class/Gini/Controller/CLI/App.php`、`class/Gini/Controller/CLI/Index.php`、`class/Gini/Document/OpenRPC.php`、`class/Gini/HTTP/Response.php`、`class/Gini/ORMIterator.php`
- 清单与工具：`gini.json`、`composer.json`（gitignored 生成物，同步约束）、`ci/dual-test.sh`（新增）、`README.md`（测试跑法）
- 测试：`ci/test/Core/ErrorHandler.php`（新增）、`ci/test/Controller/API.php`（新增）、`ci/test/Document/OpenRPC.php`（新增）、`ci/test/HTTP/Response.php`（新增）、`ci/test/ORM/ORMIterator.php`（扩展）
- 不改动：CI 配置、vendor7/vendor8/vendor81/vendor85 依赖树内容、用户工作区未提交的功能改动（ORM.php many via / add() / remove()、Session/None.php 等——提案基于当前工作区状态，不得回退）

## Budget Estimate
- 涉及文件数: 23
- 预计审查轮次: 1
- 预计实现 token: 1840K
- 预计审查 token: 25K
- 编排开销: 120K（全流程）
- 建议 /goal budget: 2000K tokens
