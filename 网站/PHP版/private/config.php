<?php
if (!defined('OOT_APP')) { http_response_code(404); exit; }
return [
    // 留空表示不启用访问密码。上线前建议改为强密码；不要把实际密码提交到 Git。
    'password' => getenv('SITE_PASSWORD') ?: '',
    // 留空使用系统临时目录。自定义时必须在网站根目录之外，并允许 PHP 用户读写。
    'storage_dir' => '',
];
