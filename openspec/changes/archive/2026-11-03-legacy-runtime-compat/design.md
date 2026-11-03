# Design

## Context

目标：老业务代码在 PHP 8.5 上"行为如常"，而非追求零弃用告警。实测兼容矩阵（PHP 7.4.33 vs 8.5.12）：

| 写法 | 7.4 | 8.5 | 归属 |
|---|---|---|---|
| `utf8_encode()` / `strftime()` | OK | OK（弃用） | 框架吸收 |
| `strlen(null)` / `str_replace(null)` / `array_key_exists(null,[])` / `number_format(null)` | OK | OK（弃用） | 框架吸收 |
| `${var}` / 动态属性 / `switch case 1;` / `[]` 反序 | OK | OK（弃用） | 框架吸收 |
| `count(null)` | `0` | TypeError | 业务必修 |
| `each()` / `create_function()` / `get_magic_quotes_gpc()` / `money_format()` | OK | undefined function | 业务必修 |
| `$str{0}` | OK | Parse error | 业务必修 |

## Decisions

### D1 弃用由 handler 吸收，掩码不得排除弃用位

`set_error_handler` 的注册掩码若不包含 `E_DEPRECATED`，handler 完全收不到引擎弃用，兜底失效（且 dev 下会落到 PHP 默认 handler 输出 stderr）。因此两环境掩码均保留弃用位，由 `Core::error()` 内部分流：记录 debug → `return true`。

### D2 不使用 error_reporting 快照区分 @ 抑制

`!(error_reporting() & $errno)` 已能正确区分：`@` 抑制时掩码位被清除 → 返回 false 交引擎静默；未抑制 → 进入 handler。无需额外基线快照。实测验证（8.5）：`utf8_encode`/`trigger_error(E_USER_DEPRECATED)` 未抑制均 no-throw，`E_USER_WARNING` 仍抛。

### D3 事务状态校验是防御而非版本兼容

实测 7.4 与 8.5 在无活动事务时 `commit()/rollBack()` 均抛 `PDOException: There is no active transaction`，不存在版本差异。`inTransaction()` 校验用于防御驱动与框架层级状态失配（DDL 隐式提交、begin 失败等），失配时记 debug 日志以免静默掩盖问题。

## Risks

- 保留弃用进 handler 会增加 debug 日志量（已由 logger 级别控制，非 warning 级不落盘）。
- `count(null)` 等类型收紧无法兜底，需业务侧迁移；已在 spec 中明确边界，避免误判。

## Supersede（实现期修正）

初版设计在 `Database\Driver` 接口上声明 `inTransaction()`。该做法会破坏向后兼容：任何已存在的第三方/模块驱动（`implements Driver`）在类声明阶段即 fatal「must implement abstract method」，且 7.4 与 8.5 同样失败——与本次"老代码双版本行为如常"的目标直接冲突；此外该接口本身并未声明 `beginTransaction/commit/rollBack`，单独补一个方法也不一致。

最终实现：**不在接口上声明**，改由 `Database::_finishTransaction()` 以 `method_exists($driver, 'inTransaction')` 探测——无该方法时沿用原有行为（直接下发 commit/rollBack），有该方法时做状态校验并记 debug。`Database\Unknown` 保留 `inTransaction()` 返回 false 作为防御性实现。
