# 野生助手后端 - PHP 版

本程序在 [GNU Affero GPL v3.0](LICENSE) 下开源。若修改本程序并在网络上提供服务，必须使用相同协议公开修改后的完整源代码。

Copyright (C) 2020 WildHelper

## 配置

+ 需要在 `config/Cache.php` 实现一个缓存类
+ 需要在 `config/Storage.php` 实现一个存储类
+ 需要配置 `config/Settings.php`
+ 需要将 `^/v2/` 重写到 `/index.php`

## 核心技术

+ **前后端完全分离**，通用 RESTful API
+ 前端强类型 TypeScript 开发，**方便重构**
+ 使用**原生**小程序开发
+ 第四版开始支持**校友认证**，学校网关账户绑定微信OpenID实现认证，毕业后可使用免密OpenID授权，100%保证用户真实，比学信网更简单易用
+ 用户直方图使用**Canvas2D**自动绘制，性能极强
+ 灰度功能**开关**；错误日志**自动上报**、微信群预警，方便紧急维护；用户反馈按钮随时接入客服
+ 使用了**端到端加密 (AES-256-GCM)**，中间人不可拿到任何用户数据；非选课周用户只能看到自己选择的课程；服务器**永不存储用户密码**；**撤销授权机制**，不想用了可以彻底删除所有用户数据，保证用户安全
+ 在企业微信、Mac微信、Windows微信等特殊客户端测试

## 开软软件使用

### GNU Affero GPL v3.0

+ [WildHelper/WildHelper-PHP](https://github.com/WildHelper/WildHelper-PHP)

### MIT License

+ [leoleoasd/zf_spider](https://github.com/leoleoasd/zf_spider)
+ [slimphp/Slim](https://github.com/slimphp/Slim)
+ [slimphp/Slim-Psr7](https://github.com/slimphp/Slim-Psr7)
