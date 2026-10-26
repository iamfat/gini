# Proposal

## Why

Gini 框架 2.3.x 的核心逻辑（ORM、Those 查询构建、路由、控制器链）长期只有 53 例测试守护零星入口，纯函数层（File/Util/Event/Config 等）几乎无直接单测；同时既有测试存在顺序依赖（`--filter` 单跑即挂）、数据库 mock 样板在 7 个文件中重复、覆盖率导向的巨型用例等质量瑕疵。support-php85 落地双版本绿（终态 60 例）后，是系统性补全单元测试并先固基测试基架的窗口。

## What Changes

- **A 级全补（纯函数零依赖）**：为 File、Util、Event、Config、CGI/Validator、Helper/TouchableArray、HTTP/Response、Database/SQL、Database/Unknown、Database::prepareSQL 扩展分支、I18N、Logger/Level+Handler、Session/None、URI、CLI/Env 新增/扩展直接单元测试（已有用例的文件一律扩展保留既有用例）。
- **B 级核心补测（mock 范式）**：ORM 剩余分支（delete/forceDelete/fetch 事件、casting、save() 事务、saveAll/removeAll 中间表，**含工作区未提交的 `add()`/`remove()` many-pivot 功能**）、ORMIterator、Those/SQLHelper+Whose 直接用例、CGI/Router、Controller/CGI+CLI execute 链、REST 异常分支、Logger handlers、View 渲染、App/Cache、Document/OpenAPI+OpenRPC、API dispatch 错误分支。
- **测试基架固基**：新增纯逻辑轻基类与 `MockDatabase` trait（样板去重）；新增测试专用 bootstrap `ci/bootstrap.php`（phpunit.xml 改指之，`lib/bootstrap.php` 零改动）恒加载 ORM.php/Those.php 全局函数块修复 `a()` 顺序依赖；修复 CGI/General.php 与 CGI/Middlewares.php 命名空间笔误、ORM/ORMIterator.php 命名空间归位；拆分 REST/Remote.php 一个 test 串 13 个 private can*() 的覆盖率导向用例。
- **依赖**：本 change 在 `support-php85` 落地后实施（双版本绿基础 + vendor85 可用 + 终态 60 例基线；Document/OpenRPC 用例直接依赖其 design D4 的 inflector 2.x 修复）。
- **范围排除（C 级，维持不做）**：真 PDO/MySQL、phpredis、HTTP/RPC curl 传输层（不为此重构注入）、活动 Session、I18N locale、CLI main @app 分支、内置 CLI 命令、App/Doctor。不为可测性重构生产代码（生产 `lib/bootstrap.php` 零改动），不引入新生产代码依赖。

## Capabilities

### New Capabilities

- `unit-test-infrastructure`：单元测试基架契约——用例基类分层、数据库 mock 范式与 IoC 清理、用例顺序无关性、testsuite 结构与外部依赖排除、双版本（PHP 7.4/8.5）一致性。

### Modified Capabilities

（无——项目当前没有任何既有 specs，`openspec list --specs` 为空。）

## Impact

- **测试文件**：新增 ~18 个测试文件、扩展 ~11 个既有测试文件。其中 `ci/test/HTTP/Response.php` 与 `ci/test/Document/OpenRPC.php` 由 support-php85（其 task 5.2/3.1）交割后存在，本 change 为扩展；`Core/Util.php`（2 例）、`Core/Event.php`（1 例）、`Core/Config.php`（1 例）已存在，本 change 扩展。根级类（I18N/View/API）测试按既有惯例归 `Core/` 子目录。
- **测试基架**：新增 `ci/bootstrap.php`（测试专用 bootstrap）、`class/Gini/PHPUnit/TestCase/Unit.php`、`class/Gini/PHPUnit/MockDatabase.php`（与既有 TestCase\CLI/CGI 同域）；`phpunit.xml` 仅改 bootstrap 属性；`lib/bootstrap.php` 零改动。
- **生产代码**：零改动。工作区未提交的 ORM.php `add()`/`remove()`（+99 行）作为被测对象纳入 B 级，不回退（同 support-php85 决策）。
- **依赖与顺序**：`support-php85` 先行落地；尤其其 design D4（OpenRPC inflector 2.x 命名空间迁移）是本 change OpenRPC 夹具用例的前置。
- **验证环境**：本机无 php/cli，全部验证走 docker 双容器（7.4+vendor7 / 8.5+vendor85），跑前清 `cache/class_map.json`（见 support-php85 scratchpad）。

## Budget Estimate

- 涉及文件数: 40
- 预计审查轮次: 2
- 预计实现 token: 6400K
- 预计审查 token: 50K
- 编排开销: 120K（全流程）
- 建议 /goal budget: 4300K tokens

> 口径说明：预计实现 token 为 F×80K×R 的原始展开；建议 budget 为 (F×80K + 120K 编排) × 轮次系数 1.3 的压缩口径，两者为同一估算的不同投影。
