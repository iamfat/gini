# complete-unit-tests 调研基线（主 agent 已验证，禁止重复探索）

## 目标
补全 Gini 框架单元测试。范围（用户已定）：**A 级全补 + B 级核心**，C 级（真 DB/网络）不做。依赖 support-php85 先行（双版本绿的基础上补测）。

## 已验证事实
- 测试跑法/前置见 `openspec/changes/support-php85/scratchpad.md`（docker 双容器 + vendor7/vendor85 映射，无需 DB 网络）
- 现状：`ci/test` 20 文件 53 例 547 断言；命名空间 `Gini\PHPUnit\<子目录>`；全部 extends `\Gini\PHPUnit\TestCase\CLI`；`TestCase\CGI` 零使用
- mock 范式（成熟，4 个文件重复）：`getMockBuilder('\Gini\Database')->disableOriginalConstructor()->setMethods(['query','quote','quoteIdent','ident'...])` + `IoC::bind('\Gini\ORM\User', fn($criteria=null)=>$mockBase)` + tearDown `IoC::clear()`；断言完整 SQL 字符串
- 已知测试质量瑕疵（补全时顺手纠正）：
  1. `CGI/General.php:6` 命名空间笔误（`\...\TestCase\CGI` 但 extends CLI 基类）
  2. `REST/Remote.php:7,32` 覆盖率导向用例（一个 test 串 13 个 private can*()）
  3. `Those/Those.php:322` `a()` 未定义顺序依赖：单跑 `--filter testWithOne2OneRelationship` 即挂（7.4 同样），根因是 `a()` 定义在 ORM.php:1443 全局块、依赖先前测试加载该文件。修法方向：测试 bootstrap 显式 require ORM.php/Those.php 全局函数块，或 a()/those() 挪到独立文件恒加载

## A 级补测清单（纯函数零依赖）
- `File.php`：humanReadableBytes `:43`、relativePath `:141`、inPaths `:172`、extension `:190`
- `Util.php`：arrayMergeDeep `:23`、arrayReplaceKeys `:9`、pathAndArgs `:68`、hyphenate `:208`、pluralize `:201`、randPassword `:43`
- `Event.php`：权重排序 `_sort:31`、abort/pass `:49/:54`、多事件 trigger `:172`、isBinded `:201`
- `Config.php`：get/set/append/import/export/clear
- `CGI/Validator.php:20`、`Helper/TouchableArray.php`、`HTTP/Response.php:11-37`（报文解析/status 正则）
- `Database/SQL.php`、`Database/Unknown.php:13`（quoteIdent）、`Database.php:191` prepareSQL 扩展分支
- `I18N.php` stripContext、`Logger/Level.php` + `Handler.php:30`（isLoggable）、`Session/None.php` 全方法
- `URI.php` URL/RURL 边界、`Logger.php` 占位符插值
- `CLI/Env.php`：normalize `:39`/stripQuote `:29` 扩展（现有 1 例）

## B 级核心（IoC/mock 可替换，延续现有范式）
- `ORM.php` 剩余分支：delete/reload/fetch 事件、casting、事务 `:299`、saveAll/removeAll 中间表 `:1380-1429`（注意用户未提交的 add()/remove()/via 改动也在此区，需为其补测）
- `ORMIterator.php`：count/offsetSet/totalCount/分页/setFetchFlag
- `Those/SQLHelper.php`、`Whose.php` 直接用例（现仅间接覆盖）
- `CGI/Router.php`：fromJSON `:90`、via `:78`、cleanUp `:295`、dispatch 404/参数 `:227`
- `Controller/CGI.php:114` 与 `Controller/CLI.php:32` execute 前后置链、`CGI.php:126` functionArguments/defaultEnv `:51`
- `REST.php` 异常分支（mock HTTP 返 4xx/5xx/坏 JSON）
- `Logger.php` handlers：虚拟 handler 或 ob 捕获 Console
- `View.php` + PHTML/Javascript：临时目录 + view_map 夹具
- `App/Cache.php`：临时目录夹具（_cacheClass/_cacheView/_cacheConfig）
- `Document/OpenAPI.php`/`OpenRPC.php`：反射+注解夹具类（依赖 support-php85 修好 inflector 2.x）
- `API.php` dispatch 错误分支补充

## 不做（C 级，用户已排除）
- Database/MySQL（真 PDO）、Lock/Redis（phpredis）、HTTP.php/RPC.php curl 传输层（不为此重构注入）、Session 活动会话、I18N locale、CLI main @app 分支、Controller/CLI/* 内置命令、App/Doctor

## 测试基建决策点（planner 定夺）
1. mock 样板去重：抽 `MockDatabase` trait（4 个 Those/ORM 文件重复），属测试内重构
2. 纯逻辑用例是否新增轻基类（不经 CLI::setup 副作用）vs 全部沿用 CLI 基类
3. 顺序依赖修复（a() 加载）放本 change 还是 support-php85 —— 建议本 change（属测试基建）
4. phpunit.xml 是否拆 testsuite（unit / integration）

## 验证策略 [R1 主 agent]
- 双容器全量 phpunit 全绿（7.4 vendor7 + 8.5 vendor85），新测试两版本行为一致
- `--filter` 单跑顺序依赖用例（testWithOne2OneRelationship 等）双版本绿
- 跑法与前置同 support-php85 scratchpad；无已知环境性失败
---

## 调研与设计
- [R1 planner] 基建决策定夺（详见 design.md Decisions）：D1 MockDatabase trait → `class/Gini/PHPUnit/MockDatabase.php`，迁移 7 个现有 mock 样板文件（SQL 断言逐字不变的等价重构）；D2 新增轻基类 `class/Gini/PHPUnit/TestCase/Unit.php`（无 CLI::setup 副作用），既有 20 文件不迁移；D3 a() 顺序依赖修在本 change：`lib/bootstrap.php` 在 Core::start() 后 require_once ORM.php/Those.php；D4 不拆 testsuite（维持单 gini suite，C 级排除后无 integration 层）
- [R1 planner] 精确锚点核验：a()/an() 在 ORM.php 尾部 `namespace { }` 全局块（function_exists die 守卫）；those()/whose()/anyOf()/allOf()/whoAre() 在 Those.php 尾部全局块；未提交 add()=ORM.php:1067、remove()=ORM.php:1112（三分支：object pivot / 无 object pivot 走 field_name / 普通 field，+BadMethodCallException 守卫）、removeAll()=ORM.php:1405；REST/Remote testDefault 串 13 个 can*；CGI/General.php 命名空间笔误在 :4（`Gini\PHPUnit\TestCase\CGI` → 应为 `Gini\PHPUnit\CGI`，scratchpad 原记 :6 为 class 行，锚点微修）
- [R1 planner] setMethods 样板分布（grep 计数）：Those/Meet=6、Those/WhoAre=7、Those/Those=5、Those/SubField=4、Those/ORM=3、ORM/ORM=3、ORM/ORMIterator=3，共 8 文件；`\Gini\Database` mock 样板以 grep 定位迁移，非 Database 类 mock（REST/Remote 的 HTTP 等）保留原地
- [R1 planner] 测试布局：ci/test/{Those,REST,ORM,CGI,Core,RPC,CLI}/ 共 20 文件；phpunit.xml 单 `gini` suite + bootstrap `lib/bootstrap.php`；Core/URI.php、CLI/Env.php、ORM/Database.php、ORM/ORM.php、ORM/ORMIterator.php、REST/Remote.php 为**扩展既有文件**，其余 A/B 级为新建；bootstrap.php 现逻辑：定位 SYS_PATH/APP_PATH → 加载 class_map/view_map → require Gini/Core.php → Core::start()
- [R1 planner] capability 定名 `unit-test-infrastructure`（项目 specs 清单为空，全为新增）；A/B 级覆盖枚举落 tasks.md，不进 spec（spec 只固基架契约）
- [R1 planner] supersede: 「setMethods 样板共 8 文件」（ORM/Database.php grep -c 为 0 不含 setMethods）→ 实测 `grep -rl setMethods ci/test/` = 7 文件；MockDatabase 迁移面 = 7 文件中全部 `\Gini\Database` mock（Those/Those|Meet|WhoAre|SubField|ORM、ORM/ORM、ORM/ORMIterator）
- [R1 planner] supersede: 「D3 改 lib/bootstrap.php」→ lib/bootstrap.php 为生产共享入口（bin/gini→lib/cli.php、lib/cgi.php 均加载），D3 改为新建测试专用 `ci/bootstrap.php`（require lib/bootstrap.php 后 require_once ORM.php/Those.php），phpunit.xml bootstrap 属性改指 ci/bootstrap.php，lib/bootstrap.php 零改动
- [R1 planner] supersede: 「Core/Util、Core/Event、Core/Config 为新建」→ 三文件已存在（Util 2 例 / Event 1 例 / Config 1 例），均改为扩展保留既有用例；HTTP/Response.php、Document/OpenRPC.php 为 support-php85 交割后的扩展项（其 5.2/3.1 各建 1 例）
- [R1 planner] 基线钉扎：support-php85 8.1 定终态 `OK (60 tests`（53+7）；本 change 全部验收计数改为「与开工实测基线一致（预期 60）」口径，1.4 拆分后 = 基线 +12
- [R1 planner] 锚点修正（R1 核验）：ORM fetch:192 / forceDelete:567 / delete:582 / add:1067 / remove:1112 / saveAll:1224 / removeAll:1405 / save():673（beginTransaction:686）/ __call orm[$name].$action:80-92；Event pass:49 / abort:54；Logger/Handler isLoggable:33；Database/Unknown quoteIdent:12；Controller/CLI execute:43
- [R1 planner] P1-4 裁决：ORM/ORMIterator.php:3 namespace `Gini\PHPUnit\Those` 修为 `Gini\PHPUnit\ORM`（与目录布局契约一致，纯 namespace 声明改动，类名与引用不变）；Middlewares.php:3 与 General.php:4 同修为 `Gini\PHPUnit\CGI`
- [R1 planner] P2-6 裁决：根级类测试归 `Core/` 子目录（既有惯例：Core/Util、Core/Config 等 root 类测试均在 Core/）——I18N→Core/I18N.php、View→Core/View.php、API→Core/API.php

## 提案审查关注点
- [R1 proposal-reviewer] P0-1：D3 前提「lib/bootstrap.php 非生产入口」为假——bin/gini:4→lib/cli.php:5→lib/bootstrap.php、lib/cgi.php:7（GINI_MUST_CACHE_AUTOLOAD）均加载它，gini.json build.copy 含 bin/lib；改生产 bootstrap 会急加载 ORM/Those 并把 function_exists die() 守卫变成每请求必评估。修复：新建测试专用 ci/bootstrap.php，phpunit.xml 改指之，lib/bootstrap.php 零改动
- [R1 proposal-reviewer] P1-1：任务 3.1 覆盖清单含不存在的 reload()（grep 零匹配）；实际符号 delete:582/forceDelete:567/fetch:192/__call orm[$name].$action 事件 ORM.php:80-92
- [R1 proposal-reviewer] P1-2：基线 53/547 与前置依赖矛盾——support-php85 落地后基线 60 tests（其 8.1 明确 53+7）；1.1/1.2/1.3 验收 53/547 与 1.4 的 65 全部过期
- [R1 proposal-reviewer] P1-3：「新建」错标 5 处：Core/Util.php（既有 2 例 testGetOpt/testSingularize）、Core/Event.php（testBind 1 例）、Core/Config.php（testGet 1 例）已存在；HTTP/Response.php 与 Document/OpenRPC.php 由 support-php85 task 5.2/3.1 交割后存在；应改「扩展/追加保留既有用例」并给下界；proposal Impact 计数改约 18 新建/11 扩展
- [R1 proposal-reviewer] P1-4：命名空间笔误只修一半——CGI/Middlewares.php:3 同为 Gini\PHPUnit\TestCase\CGI 笔误；ci/test/ORM/ORMIterator.php:3 namespace Gini\PHPUnit\Those 与 ORM/ 目录不符，需显式裁决
- [R1 proposal-reviewer] P2：P2-1 「P0-3」悬空（support-php85 无此编号，实为其 design D4）；P2-2 锚点漂移（事务 save():673-740、saveAll:1224、Event pass:49/abort:54 写反、Handler isLoggable:33、Unknown quoteIdent:12、Controller/CLI execute:43）；P2-3 mock 样板 8 文件应为 7；P2-4 1.2 验收 grep 会误伤 '\Gini\Database\Statement' mock（Those/Those.php:68）；P2-5 Budget 两口径未注明；P2-6 根级测试文件 I18N/View/API 打破子目录布局；P2-7 顺序无关终验仅抽查 1 个 filter

## 实现探索
- [R1 coder] 基线: T74 '' / T85 '' → `OK (60 tests, 568 assertions)`（PHPUnit 9.5.21@7.4 / 9.6.38@8.5，各 ~0.13s）；与 support-php85 终态一致，无已知环境性失败；docker amd64-on-arm64 platform warning 为噪音
- [R1 coder] 契约动摇候选（任务 2.8 Session/None）：工作区未提交的 `class/Gini/Session/None.php:15` 含 `gc($maxlifetime): int|false`（PHP 8.0+ 联合返回类型），PHP 7.4 下整个文件 parse error（实测：`php -r class_exists(...)` → `PHP Parse error: syntax error, unexpected '|' … None.php on line 15`）；tasks 2.8 要求 Session/None 全方法双版本绿，与「不得修改用户未提交改动功能语义」红线冲突。已 grep 确认未提交生产 diff 中联合类型仅此 1 处。处置：继续实现其余全部任务，该单点留待主 agent 裁决（用户回退签名 / 接受 7.4 skip / 授权 coder 去类型）
- [R1 coder] 批次 1 落地：ci/bootstrap.php + phpunit bootstrap 改指；TestCase/Unit.php + MockDatabase trait（createDatabaseMock/attachQueryHandler/bindIoC/clearMockIoC）；7 文件样板迁移（ORM/ORM 的 quote 回调多 `SQL` 对象分支，trait 用第二参 `$quoteSQLObjects=true` 覆盖）；3 处命名空间修复；REST/Remote 拆 13 测试。双版本 `OK (72 tests, 568 assertions)`，grep `getMockBuilder('\Gini\Database')` = 0，testWithOne2OneRelationship/testParameters/testForm 单跑均 OK
- [R1 coder] 【主 agent 裁决 2026-10-26】Session/None 契约动摇处置：回退 `gc($maxlifetime): int|false` 为无返回类型声明（仅 gc 一处；`close/destroy/open/read/write` 的 `: bool`/`: string` 保留）。依据：7.4 parse error 击穿生产；8.1 tentative 仅 E_DEPRECATED 且已被 support-php85 D1 降级 debug 日志。已实测 ci/test/Session/None.php 双版本 `OK (7 tests, 7 assertions)`。未来彻底离开 7.4 后可恢复联合类型。
- [R1 coder] 批次 2 落地（A 级纯函数，全部双版本逐文件绿）：Core/File.php 新建 4/25；Core/Util.php 14/72；Core/Event.php 8/14；Core/Config.php 7/13；CGI/Validator.php 新建 5/8；Helper/TouchableArray.php 新建 5/12；HTTP/Response.php 4/17；Core/I18N.php 新建 5/7；Core/URI.php 5/24；Database/SQL.php 新建 4/6；Database/Unknown.php 新建 3/11；ORM/Database.php 6/18；Logger/Level.php 新建 5/15；Session/None.php 新建 7/7；CLI/Env.php 4/16。钉扎的真实怪癖：humanReadableBytes 位移（1536000→'1MB'）、hyphenate('ÄÖ') 无 u 修饰符原样返回、Config::set('a.b.c') 只切首个点、URL('mailto:...') → 'mailto:@'、quote(float) 不转字符串。
- [R1 coder] 任务 3.1 落地：ci/test/ORM/ORM.php 6→32 测试、81 断言，双版本 OK。新增 forceDelete/delete 无 id 短路/fetch 数组条件+forUpdate 单次消耗/casting autocast 开/关/save insert/update/rollback 事务/saveAll 三分支（命名 object 中间表、多态 name+id、标量字段）/removeAll 含无 id false/add+remove 各三分支+未注册名 BadMethodCallException×2/__call 未绑定抛异常+继承链 orm[base].call[$m] 事件分发。关键实证怪癖：**`delete()` 的 `is_callable($this, '_delete')` 是死分支**——第二参数是 syntax_only 布尔位，对对象恒 false（实测 7.4/8.5 一致），故 `_delete()` 钩子永不被调用，delete 恒走 forceDelete；已写 testDeleteAlwaysUsesForceDeleteHookCheckIsDeadBranch 钉扎。夹具：setUp 双 IoC（ut/sample 全字段 + ut/poly 多态 thing/标量 tag/自引用 friend），makeOrmMock(...,hydrate=true/false) 区分「已水合」与「待 fetch」；factory 水合用 normalizeCriteria(criteria())，标量 criteria 须先转数组否则 (array)100 产生 [100]。saveAll 末次返回 statement 对象（真值非 bool），断言用 `(bool)` 强转。Event 回调首参为 Event 对象（其 name 私有不可断言），断言 object->name() 即可。
- [R1 coder] 任务 3.2 落地：ci/test/ORM/ORMIterator.php 2→7 测试、29 断言，双版本 OK。新增 count 反映已 fetch 行数/offsetSet 收编 object（id+dirty 标记）/totalCount/分页（pageRows 与 totalCount 分离）/setFetchFlag（含 `*` 通配清除）。关键实证：**count SQL 正则只收敛 `COUNT(t0.*)` 形态**——totalCount 剥离 LIMIT/OFFSET 依赖该形态，已钉扎 testTotalCountDerivesCountSQLWithoutPaging；FetchFlagIterator 夹具暴露 protected fetchFlag 供断言
- [R1 coder] 任务 3.3 落地：ci/test/Those/Whose.php 新建 6/28、ci/test/Those/SQLHelper.php 新建 12/28，双版本逐文件 OK。关键实证：**SQLHelper::finalize 的 select 列恒为 id**（无显式 select 时组装 `SELECT "id" FROM ...`）；**where/join/order/limit 一次性消费**（finalize 后再调用不残留）；**SQLHelper 别名计数器为静态共享**，须每用例 `\Gini\Those\SQLHelper::reset()` 否则跨用例串号；**Whose notLike 不做通配展开**（值原样进 SQL，与 like 不同）；**空 isIn 退化为 `1=0`**。Whose 同理每用例 `\Gini\Those::reset()`
- [R1 coder] 任务 3.4 落地：ci/test/CGI/Router.php 新建 13/37，双版本 OK。覆盖 fromJSON（含嵌套 router 水合）、via 全局中间件、cleanUp（清规则保留全局中间件）、__sleep 可序列化属性、魔术动词注册/any/未知方法静默 null、`::`→`@` 归一、group 前缀选项、dispatch 参数提取/默认 action/嵌套 group/全局+路由中间件合并去重/404 fallback。关键实证：**规则 key 的正则含字面反斜杠**（`get:api/users/([^\/]+)`，断言键名须逐字含 `\\/`）；**404 fallback 命中 `index` 路由且 params 首元素为空串**（`['']` 非 `[]`），已钉扎 testDispatchFallsBackToCgiIndexForUnknownRoute
- [R1 coder] 任务 3.5 落地：ci/test/Controller/CGI.php 新建 11 测试（execute 前中后链/preAction false 跳过/postAction 替换/params 派生 action+shift/404 无 __index/errorResponse 渲染真实视图/Response\Exception→errorResponse/普通异常透传/字符串中间件经 Middleware::of 解析/对象中间件直传/form 分模式）+ functionArguments 3 例（位置参数反射补默认值与透传/空参落命名分支/字符串键驼峰归一+form 合并）+ defaultEnv 2 例（方法覆盖+QUERY_STRING 重解析/缺省 GET）；ci/test/Controller/CLI.php 新建 6 测试（__index→__unknown 回退输出/pre/postAction 链/params 整体单参/preAction 返回值不跳过）。关键实证：**functionArguments 不接受 Closure**（is_string 分支，Closure 走 ReflectionMethod 即抛错，用具名函数夹具）；**Middleware::of 的 strtr 只去分隔符不补后缀**（注册名须与类名小写全等）；**error/http 视图仅一行 h1**（嵌套 V("error/$code") 不存在于该模板）；**PHP 7.4 call_user_func 非法回调为 E_WARNING+NULL，8.x 才 TypeError(\Error)**（未知 action 用例按 PHP_VERSION_ID 分支钉扎）；双版本 17/55
- [R1 coder] 任务 3.6-3.11 落地（全部双版本逐文件绿）：3.6 REST/Remote +5（404/500/400 异常携带 getData 解码体/2xx 坏 JSON 返 null/白名单外方法 null）18/26；3.7 Logger/Handler 新建 7/10（of 单例/双 handler 同发/disabled 非数组跳过+反射计数/default 兜底 ident 用实名/ErrorLog 嵌套 context 点展平/低于阈值短路/基类 log no-op）；3.8 Core/View 新建 6/14（PHTML 变量渲染/魔法属性+set/JS 引擎 IIFE+J() 导出/**渲染结果按实例缓存**（改模板旧实例仍返缓存）/缺视图空串/@locale 前缀优先）；3.9 App/Cache 新建 3/21（**经 Core::$MODULE_INFO 注入临时目录假模块**夹具化 pharFilePaths 扫描：class_map 键小写+连字符转下划线而值保留大小写/view_map 跨引擎/config 冻结快照+ORM plurals 仅当单数名已在预置值中才并入+Config::setup 从冻结文件重载）；3.10 OpenRPC 1→5（summary/description/params schema/**summary 以句号结尾则 description 恒丢失**（fetchPlainText 句号断行分支消费 lexer 位置）/snake 方法名 camelize/**Gini\Controller\API\ 前缀剥除**）+ OpenAPI 新建 2/10（@Response/@MediaType/@Schema 注解嵌套解析+path/query 参数分派/无注解基线），inflector 2.x 依赖已满足；3.11 Core/API 新建 10/22（-32600×2/-32601 未知与下划线前缀/action+命名参数/env 透传/__invoke 优先/嵌套命名空间/控制器异常透传/**成功且无 id 返回 null**（未定义变量，7.4 E_NOTICE/8.5 E_WARNING）
- [R1 coder] 顺序无关修复（全量暴露的两处跨文件污染）：① 既有 ci/test/Core/Config.php 的 clear()/import() 用例清空全局配置未恢复 → 新增 setUp/tearDown export()/import() 备份还原（受害者为后置 Core/View 的 view.engines 读取）；② Controller/CLI 未知 action 用例改为按 PHP_VERSION_ID 分支。另修 Logger/Handler 测试 error_log 直写 stderr 的输出污染（ini_set 重定向临时文件断言）
- [R1 coder] 【事故记录·已完全恢复】终验排障期间为二分复现，曾将 ci/test 下测试文件 mv 至临时目录，回移时平铺落盘导致同名文件覆盖（API.php=Controller 与 Core、ORM.php=ORM 与 Those、General.php=CGI 与 RPC 三对）。恢复结果：ORM.php 幸存内容实为 **3.1 的 32 测试完整版**（换回 ci/test/ORM/ORM.php ✓）；Those/ORM.php 从 git 基线重建并重放 MockDatabase trait 迁移（断言逐字保留，单跑验证绿）；Controller/API.php、CGI/General.php（含 1.3 命名空间修复重放）从 git/上下文恢复；Core/API.php 平铺幸存者为 Controller 版，按上下文终版重写。全量 270/1131 双绿实证无净损失
- [R2 coder] supersede: 4.1 终验「逐测试文件单跑 × 双版本 39 文件零 FAIL」
- [R2 coder] 文件数更正：`find ci/test -name '*.php' | wc -l` = **42**（R1 记 39 为误计数），4.1 的「全绿」结论不变；tasks 4.1 证据行已同步更正并补随机会序证据
- [R2 coder] 修复 P1（I18N 顺序依赖）：`ci/bootstrap.php:12-17` 在 ORM/Those 之后补 `require_once SYS_PATH . '/class/Gini/I18N.php';`（附注释说明理由）。根因：全局 `T()` 定义在 `class/Gini/I18N.php` 尾部 `namespace { }` 块，仅当 `\Gini\I18N` 被 autoload 时才存在；而 `ci/test/Core/I18N.php` 继承 `TestCase\Unit`（无 `CLI::setup` 顺带加载路径），先跑 T() 用例即 `Call to undefined function Gini\PHPUnit\Core\T()`。证据（修前→修后）：`--filter testTInterpolatesParams` 双版本 `Tests: 1, Errors: 1` → `OK (1 test, 1 assertion)`；`--order-by=random` seeds 1/42/999 双版本「1 error、1130 assertions」→ 双版本全部 `OK (270 tests, 1131 assertions)` 零 error
- [R2 coder] 修复 P2（隐式可空参数）：`class/Gini/PHPUnit/MockDatabase.php:99` `callable $recorder = null` → `?callable $recorder = null`。证据（PHP 8.5）：`php -d error_reporting=32767 -d display_errors=1 -r 'require "class/Gini/PHPUnit/MockDatabase.php";'` 修后无任何 Deprecated 输出；对照（同命令、旧签名临时副本）实测输出 `PHP Deprecated: Gini\PHPUnit\MockDatabase::attachQueryHandler(): Implicitly marking parameter $recorder as nullable is deprecated … on line 99`
- [R2 coder] 基线: ci/test 全量（`ci/dual-test.sh` 等价命令）→ `OK (270 tests, 1131 assertions)` 双版本，各 ~0.35-0.41s；`--order-by=random` 同值同断言数；docker amd64-on-arm64 platform warning 为噪音
## 代码审查范围
- [R1 code-reviewer → R2 coder 回流] P1：`ci/test/Core/I18N.php` 顺序依赖（T() 未预加载，违反本 change spec「任意用例可单独执行」）→ R2 已在 `ci/bootstrap.php` 补 I18N.php require_once（见「实现探索」R2 条目）
- [R1 code-reviewer → R2 coder 回流] P2：`class/Gini/PHPUnit/MockDatabase.php:99` 隐式可空参数 `callable $recorder = null` → R2 已改 `?callable`（见「实现探索」R2 条目）
- [R1 code-reviewer → R2 coder 回流] 记录口径：4.1 写「39 文件」而实测 42 文件 → R2 已按 supersede 更正
