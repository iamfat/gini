# Spec Delta

## Purpose

Gini 框架单元测试基架契约：约束测试套件的用例基类分层、数据库测试替身范式、用例顺序无关性、testsuite 结构与 PHP 7.4/8.5 双版本一致性，使补测用例在统一基架上累积而非各自重复发明样板。

## ADDED Requirements

### Requirement: 纯逻辑用例不经环境副作用执行

测试套件 MUST 提供无环境副作用的纯逻辑用例基类；不依赖 CLI/CGI 运行环境的用例 MUST 使用该基类，而非绑定 CLI 环境的既有基类。既有环境依赖型用例不强制迁移。

#### Scenario: 纯逻辑用例独立执行

- **WHEN** 以 `phpunit --filter` 单独执行任一纯逻辑（A 级）用例
- **THEN** 该用例通过，且执行过程不触发 CLI 环境的 setup/shutdown 副作用

### Requirement: 数据库访问以测试替身替换

涉及 ORM/Those SQL 生成的用例 MUST 以测试替身替换数据库连接层，通过 IoC 绑定注入，并 MUST 断言送达替身的完整 SQL 语句文本；用例结束时 MUST 清理全部 IoC 绑定，不得泄漏到后续用例。替身构造逻辑 MUST 由共享的单一 mock 组件提供，不得在各用例文件内重复样板。

#### Scenario: SQL 断言

- **WHEN** 用例执行 ORM/Those 的查询构建或写操作
- **THEN** 断言送达替身的完整 SQL 字符串（含引号/占位符展开）与预期一致

#### Scenario: IoC 绑定清理

- **WHEN** 任一使用了 IoC 绑定替换的用例结束
- **THEN** 对应绑定被清除，后续用例在未自行绑定时取得真实实现

### Requirement: 用例顺序无关

任意用例 MUST 可单独执行且通过，不依赖其他用例先行加载的类文件或留下的全局状态；ORM/Those 提供的全局辅助函数（`a()`/`an()`/`those()`/`whose()`/`anyOf()`/`allOf()`/`whoAre()`）MUST 在测试引导阶段即恒定可用，而非依赖某用例间接触发其所在文件加载。

#### Scenario: 顺序依赖用例单跑

- **WHEN** 以 `--filter testWithOne2OneRelationship` 单独执行该用例
- **THEN** 该用例通过，且 PHP 7.4 与 8.5 下行为一致

#### Scenario: 任意测试文件独立执行

- **WHEN** 对 `ci/test` 下每个测试文件分别单独执行（不与其他文件同批）
- **THEN** 每个文件的全部用例通过，且 PHP 7.4 与 8.5 下行为一致

### Requirement: testsuite 结构与外部依赖排除

测试套件 MUST 维持单一 `gini` testsuite 覆盖 `ci/test` 下全部用例文件，MUST NOT 拆分无内容的分层 suite；全部用例 MUST NOT 依赖真实数据库连接、网络访问或活动会话存储。

#### Scenario: 全量执行无外部依赖

- **WHEN** 全量执行 `gini` testsuite
- **THEN** 过程中无任何真实 PDO 连接、HTTP 请求或会话存储读写发生

### Requirement: 双版本行为一致

全部用例（既有与新增）MUST 在 PHP 7.4（vendor7）与 PHP 8.5（vendor85）两套依赖树下行为一致。

#### Scenario: 双版本全量绿

- **WHEN** 分别以 vendor7 与 vendor85 依赖树全量执行测试套件
- **THEN** 两个版本均零失败、零错误
