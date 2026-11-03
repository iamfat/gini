## ADDED Requirements

### Requirement: 弃用类语义变化由框架吸收

对于在 PHP 8.x 中降级为弃用（E_DEPRECATED / E_USER_DEPRECATED）但语义未变化的引擎行为（如向内置函数传 null、`utf8_encode`/`utf8_decode`、`strftime`、`${var}` 字符串插值、动态属性、`switch case` 分号等），全局错误处理器 MUST 吸收该通告：程序 MUST 继续执行、MUST NOT 抛出异常、MUST NOT 输出到标准错误，仅在低噪声日志通道记录。

#### Scenario: 老业务代码传 null 参数仍可运行

- **WHEN** 业务代码在 PHP 8.5 下调用 `strlen(null)` 或 `str_replace('a','b',null)`（PHP 7.4 下正常返回 0 / 空串）
- **THEN** 调用成功返回与 7.4 一致的结果，不抛异常，且弃用通告进入框架日志而非 stderr

#### Scenario: 已弃用的运行时函数不再可用但语法有效的调用

- **WHEN** 业务代码调用 `utf8_encode()` 或 `strftime()`
- **THEN** 调用成功返回结果（7.4 与 8.5 行为一致）

### Requirement: 移除类与语法类变化不在框架兜底范围

对于 PHP 8.x 已移除的函数（`each`、`create_function`、`get_magic_quotes_gpc`、`money_format`）与已移除语法（`$str{0}` 花括号下标），框架 MUST NOT 试图模拟；其表现为致命错误或解析错误，属于业务代码必须修正的范围。框架文档与提案 MUST 明确该边界，避免误以为可无缝迁移。

#### Scenario: 已移除函数仍会致命

- **WHEN** 业务代码在 PHP 8.5 下调用 `each()`
- **THEN** 抛出 `Error: Call to undefined function each()`，框架不提供替代实现

#### Scenario: 已移除语法直接解析失败

- **WHEN** 业务代码包含 `$str{0}` 花括号下标
- **THEN** PHP 8.5 在解析阶段即失败，任何错误处理器都无法介入

### Requirement: 类型收紧类变化须由框架适配层显式处理

对于 PHP 8.x 参数类型收紧导致 TypeError 的场景（如 `count(null)`），框架无法通过错误处理器兜底；若框架自身代码受影响，MUST 在适配层显式处理（判空后再调用），MUST NOT 依赖全局错误处理器。

#### Scenario: 框架自身不依赖会收紧的调用

- **WHEN** 在框架 class/ 源码中检索以可能为 null 的变量调用 `count()`
- **THEN** 均为先判空/判类型后再调用，双版本行为一致
