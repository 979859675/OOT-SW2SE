# PHP 版开发测试

部署 PHP 网站不需要 Node.js；这里的 Node.js 只用于开发时生成原版 JavaScript 的黄金参考结果。

在仓库根目录运行：

```sh
node 网站/tests/php-golden.cjs
php 网站/tests/php-core.php .build/php-vectors.json .build/php-test.zip
node 网站/tests/php-http.cjs
```

HTTP 测试默认自动启动本机 `php -S 127.0.0.1:8123`，通过 `PHP_BIN` 指定 PHP 可执行文件。也可设置 `PHP_TEST_URL` 使用已运行的 PHP 测试服务。

黄金测试覆盖 38 个编辑场景：不变导出、钱包、生命步长、魔力/旧版转换、容量各等级与自动升级、瓶子增减、添加/移除/清除获取标记、冲突拒绝、两个时期装备保护、默认服装保护、非法数量与标记。逐字节比较 progress.sav 和 caption.sav，并检查只读字段、9 种语音设置、清单哈希和 ZIP。

HTTP 测试覆盖配置检测、私有源码直接访问保护、跨站请求/无效路径/不完整存档拒绝、导入、切槽、字段查询、ZIP 字节一致及清除会话。合成数据及测试输出仅写入被 Git 忽略的 `.build/`。
