# Tasks

## 1. 错误处理兜底

- [x] 1.1 `class/Gini/Core.php` 回退 `error_reporting()` 与 `set_error_handler()` 的掩码：两环境均保留 `E_DEPRECATED`，确保 handler 能收到弃用并记 debug、不抛不落 stderr。验收：`ci/test/Core/DeprecationPipeline.php` 在 7.4/8.5 均绿
- [x] 1.2 验证老代码弃用场景（`utf8_encode`、`strlen(null)`、`str_replace(null)`、`${var}`、动态属性、`switch case;`）在框架 handler 下返回与 7.4 一致的结果。验收：兼容矩阵实测（见 design）

## 2. 事务与驱动契约

- [x] 2.1 `Driver` 接口声明 `inTransaction()`；`Unknown` 实现返回 false；`Database` 统一经 `_finishTransaction()` 收敛
- [x] 2.2 事务状态失配（无活动事务）时记 debug 日志，不静默、不抛。验收：`ci/test/ORM/Database.php` 新增 2 用例绿
- [x] 2.3 `SQLite` 驱动与 `MySQL` 一致设置 `ATTR_ERRMODE = ERRMODE_SILENT`（Database 层依赖 false 返回语义）

## 3. 配置化与清理

- [x] 3.1 请求体解析上限改为 `cgi.max_body_size`（默认 4MB），在 `raw/config/system.yml` 声明
- [x] 3.2 移除 `CLI::setup()` 中冗余的 `Config::setup()`（`Core::start()` 已执行）

## 4. 回归

- [x] 4.1 `ci/dual-test.sh` 双版本全绿（275 tests / 1145 assertions）
