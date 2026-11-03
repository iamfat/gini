# 老业务代码在 PHP 8.5 的行为兼容边界

## Why

框架需支持在 PHP 7.4 下正常运行的老业务系统迁移到 8.5。老代码普遍使用 PHP 8.x 已弃用或已移除的语言特性。若框架层不兜底弃用类变化，这些弃用会以异常形式中断请求，导致"7.4 正常、8.5 报错"。

## What Changes

- 明确并固化兼容边界（新 capability `legacy-runtime-compat`）：
  - **框架吸收**：弃用类语义变化（传 null 给内置函数、`utf8_encode`、`strftime`、`${var}`、动态属性、`switch case;`）。
  - **业务必修**：已移除函数（`each`/`create_function`/`get_magic_quotes_gpc`/`money_format`）、已移除语法（`$str{0}`）、类型收紧致 TypeError（`count(null)`）。
- 全局错误处理器保持"弃用不升级、记录 debug、不写 stderr"的行为；注册掩码 MUST 放行弃用级别（否则 handler 根本收不到，兜底失效）。
- 补齐 `Driver::inTransaction()` 接口契约，事务状态失配时记录日志而非静默/抛异常。

## Impact

- 影响 capability：`legacy-runtime-compat`（新增）、`error-handling`（实现对齐，无契约变更）
- 代码：`class/Gini/Core.php`（掩码）、`class/Gini/Database.php`（事务收敛）、`class/Gini/Database/{Driver,Unknown,SQLite}.php`
- 验证：`ci/dual-test.sh` 双版本全绿；兼容矩阵实测记录于本变更

## Budget Estimate

涉及文件 6、审查轮次 1 → 建议 `budget: 600K tokens`
