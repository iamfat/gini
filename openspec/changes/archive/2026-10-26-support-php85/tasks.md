# Tasks

> **验收命令约定**：下文 `PHP74` / `PHP85` 为 docker 双版本跑法前缀，展开为
> `docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:7.4-alpine sh -c "ln -sfn vendor7 vendor; vendor7/bin/phpunit <参数>"`
> （`PHP85` 同构：镜像 `8.5-alpine`、映射 `vendor85`、执行 `vendor85/bin/phpunit`）。
> 每次验收跑完在仓库根执行 `rm -f vendor cache/class_map.json`，清理映射符号链接与容器路径类映射缓存（scratchpad 已验证的跑法）。

## 1. 错误处理器策略（design D1/D2）

- [x] 1.1 修改 `class/Gini/Core.php` 的 `Core::error()`：@ 抑制判定改掩码式 `!(error_reporting() & $errno)`；`E_DEPRECATED | E_USER_DEPRECATED` 经 `Logger::of('core')->debug(...)` 记录后返回 true；其余保持抛 `ErrorException`。验收：`docker run --rm -v "$PWD":/app -w /app docker.genee.cn/php:8.5-alpine php -l class/Gini/Core.php` 输出 `No syntax errors detected`（行为验收见 1.2）
- [x] 1.2 新增 `ci/test/Core/ErrorHandler.php`（namespace `Gini\PHPUnit\Core`，class `ErrorHandler extends \Gini\PHPUnit\TestCase\CLI`）三个用例：`testDeprecationNotEscalated`（直接调 `\Gini\Core::error(E_DEPRECATED, ...)` 与 `E_USER_DEPRECATED`，断言不抛异常；用例内先 `error_reporting(E_ALL & ~E_NOTICE)` 对齐 Core::start 的 dev 设定）、`testSuppressedErrorNotEscalated`（`@trigger_error(..., E_USER_DEPRECATED)` 与 `@trigger_error(..., E_USER_WARNING)` 均不抛异常）、`testUserWarningEscalated`（`trigger_error(..., E_USER_WARNING)` 断言抛 `ErrorException`）。验收：`PHP85 ci/test/Core/ErrorHandler.php` 与 `PHP74 ci/test/Core/ErrorHandler.php` 均输出 `OK (3 tests`

## 2. Controller\API 属性声明（design D3）

- [x] 2.1 `class/Gini/Controller/API.php` 声明 `public $app;` / `public $env;`（docblock 对齐 `Controller/CGI.php:34-49`）；新增 `ci/test/Controller/API.php`（namespace `Gini\PHPUnit\Controller`）用例 `testPropertiesDeclared`：对 `new class extends \Gini\Controller\API {}` 断言 `property_exists($o, 'app')` 与 `property_exists($o, 'env')` 为 true，且赋值读回一致。验收：`PHP85 ci/test/Controller/API.php` 与 `PHP74 ci/test/Controller/API.php` 均输出 `OK (1 test`

## 3. OpenRPC 迁移 inflector 2.x（design D4）

- [x] 3.1 `class/Gini/Document/OpenRPC.php`：`use Doctrine\Common\Inflector\Inflector;` 换为 `use Doctrine\Inflector\InflectorFactory;`，增加静态缓存 `self::inflector()`（`??=` 惰性构建），`Inflector::camelize($matches[2])` 改 `self::inflector()->camelize($matches[2])`；新增 `ci/test/Document/OpenRPC.php`（namespace `Gini\PHPUnit\Document`）用例 `testCamelizeMethodName`：`parseMethodItem(new ReflectionMethod($this, 'actionGetUserInfo'))` 返回数组且 `['name']` 以 `getUserInfo` 结尾。验收：`PHP85 ci/test/Document/OpenRPC.php` 与 `PHP74 ci/test/Document/OpenRPC.php` 均输出 `OK (1 test`；`grep -rn "Doctrine.Common.Inflector" class/` 无匹配

## 4. 废弃调用清理（design D5）

- [x] 4.1 删除 `class/Gini/HTTP.php:175,181` 与 `class/Gini/RPC.php:161,167` 的 `curl_close($ch)`、`class/Gini/File.php:230` 的 `finfo_close($finfo)`；`class/Gini/Controller/CLI/App.php:179`、`class/Gini/Controller/CLI/Index.php:61,63` 反引号改 `shell_exec()`（语义等价）。验收：`grep -rn "curl_close(\|finfo_close(" class/` 无输出；`grep -c "shell_exec" class/Gini/Controller/CLI/App.php class/Gini/Controller/CLI/Index.php` 分别 ≥1、≥2；`PHP85 ci/test/RPC` 与 `PHP74 ci/test/RPC` 均输出 `OK`

## 5. null/未定义键守卫族与 Response 兜底（design D6/D7）

- [x] 5.1 按 design D6 表逐点加守卫（CGI.php:110,252,253；CLI.php:192,195,205；HTTP.php:104,108；RPC.php:18,20,65；URI.php:60；Controller/CGI/Error.php:10），不改任何正常路径行为。验收：`PHP85 ci/test/Core`、`PHP85 ci/test/CGI`、`PHP85 ci/test/RPC` 与 7.4 对应三者均输出 `OK`
- [x] 5.2 `class/Gini/HTTP/Response.php:13` 构造器按 D7 加 `(string)` 化与 `array_pad(..., 2, '')`；新增 `ci/test/HTTP/Response.php`（namespace `Gini\PHPUnit\HTTP`）用例 `testConstructWithNullData`：`new \Gini\HTTP\Response(null, 500)` 不抛异常且 `(string)` 化为 `''`、header 为空数组；`new \Gini\HTTP\Response("HTTP/1.0 200 OK\n\nbody")` 保持原解析行为。验收：`PHP85 ci/test/HTTP/Response.php` 与 `PHP74 ci/test/HTTP/Response.php` 均输出 `OK (1 test`

## 6. ORMIterator null 守卫（design D8）

- [x] 6.1 `class/Gini/ORMIterator.php:238` `valid()` 改为 `null !== $this->current_id && isset($this->objects[$this->current_id])`；在既有 `ci/test/ORM/ORMIterator.php`（延续其 mock 范式）追加用例 `testValidBeyondEnd`：迭代器耗尽后再调 `valid()` 返回 false 且不触发任何通告升级。验收：`PHP85 ci/test/ORM/ORMIterator.php` 与 `PHP74 ci/test/ORM/ORMIterator.php` 均输出 `OK`（用例数较基线 +1）

## 7. 依赖契约与双版本机制（design D9/D10）

- [x] 7.1 `gini.json` `composer.require` 增 `"php": "^7.4 || ^8.5"`，根 `composer.json`（gitignored 生成物）同步同一约束。验收：`grep -c '"php": "\^7\.4 || \^8\.5"' gini.json composer.json` 两文件各输出 `1`
- [x] 7.2 新增可执行 `ci/dual-test.sh`（内容见 design D10：双版本全量、trap 清理符号链接与 class_map.json；注意 design D10 代码块中的 `vendor$v` 字面量已被取代，以 scratchpad「实现探索」区 [R1 coder] 的 supersede 记录与实际脚本为准）并保持 `chmod +x`；`README.md` 增「运行测试」节记录双版本跑法与单文件跑法。验收：`sh -n ci/dual-test.sh` 无输出（语法通过）；`ls -l ci/dual-test.sh` 含 `x` 位；`ci/dual-test.sh` 双版本全量通过且退出码 0，结束后 `[ ! -e vendor ] && echo clean` 输出 `clean`

## 8. 双版本全量回归（集成）

- [x] 8.1 跑 `ci/dual-test.sh`：PHP 7.4 与 8.5 输出均以 `OK (60 tests` 开头（基线 53 + 新增 7 用例），0 failures / 0 errors；确认 8.5 下原 5 errors 清零（ORMIterator 项由 6.1 修复，3 个跨测试污染项预期随 1.1 转绿；若仍有残留，按 scratchpad「实现探索」区归因流程处置并记录，不得静默放行）；将实测计数与耗时 append 进 scratchpad「实现探索」区（标注 `[R<n> coder]`）

## Workflow follow-up

- 归档前确认 `openspec validate support-php85 --strict` 通过
- 实现全部完成后按项目评审要求执行 `openspec archive support-php85`（delta 合入主 specs）
