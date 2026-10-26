# dependency-contract Specification

## Purpose
约定框架的 PHP 运行时支持窗口、依赖包 API 版本约束、废弃运行时调用禁用清单，以及基于版本化依赖树（vendorNN）映射的双版本验证机制。

## Requirements

### Requirement: PHP 支持窗口声明

框架 SHALL 在依赖清单生成源（gini.json 的 composer 段 require）中声明 `"php": "^7.4 || ^8.5"`，并由其生成的 composer.json 保持同一约束；约束变更 MUST 同时落两处。

#### Scenario: 清单声明一致

- **WHEN** 检查 gini.json 与生成的 composer.json 的 require.php 字段
- **THEN** 两者均为 `^7.4 || ^8.5`

### Requirement: 依赖 API 版本契约

框架代码 SHALL 仅使用支持窗口内各依赖包已安装版本共同提供的 API；MUST NOT 引用依赖包已移除的历史版本命名空间（如 doctrine/inflector 1.x 的 `Doctrine\Common\Inflector`）。

#### Scenario: 文档生成器跨版本可用

- **WHEN** RPC 文档生成器对方法名做 camelCase 转换
- **THEN** 在 7.4 与 8.5 两套依赖树下均成功产出 camelCase 结果，无类未找到错误

#### Scenario: 源码无历史命名空间残留

- **WHEN** 在框架 class/ 源码中检索 `Doctrine\Common\Inflector` 引用
- **THEN** 匹配数为 0

### Requirement: 废弃运行时调用禁用

框架代码 MUST NOT 调用支持窗口内已弃用且无效果的运行时函数（curl_close、finfo_close）；外部命令执行 MUST NOT 使用反引号运算符，统一经由显式的 shell 执行函数。

#### Scenario: 源码无废弃调用残留

- **WHEN** 在框架 class/ 源码中检索 `curl_close(`、`finfo_close(` 与反引号命令执行
- **THEN** 匹配数均为 0

### Requirement: 双版本回归验证机制

全量测试套件 MUST 能以固定入口在 PHP 7.4 与 8.5 双版本下各执行一遍并全部通过；验证机制 SHALL 按目标 PHP 版本映射对应的版本化依赖树（vendorNN 目录）为 vendor，运行前 MUST 清理含陈旧绝对路径的类映射缓存，运行后 MUST 清理映射产生的 vendor 链接，不得污染工作区。

#### Scenario: 一键双版本回归通过

- **WHEN** 运行双版本回归脚本（ci/dual-test.sh）
- **THEN** 两个版本容器内的全量测试均通过（脚本退出码 0），结束后工作区无 vendor 符号链接残留

#### Scenario: 陈旧类映射缓存不参与

- **WHEN** 回归脚本启动
- **THEN** 脚本先删除 cache/class_map.json 再执行测试，避免旧构建绝对路径导致类加载失效
