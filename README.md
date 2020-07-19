# 野生工大助手后端

2020中国高校计算机大赛 微信小程序应用开发赛 决赛入围后被淘汰的垃圾之作

本程序在 [GNU Affero GPL v3.0](LICENSE) 下开源。若修改本程序并在网络上提供服务，必须使用相同协议公开修改后的完整源代码。

Copyright (C) 2020 郭泽宇 <ze3kr@icloud.com>

## 配置

+ 需要在 `config/Cache.php` 实现一个缓存类
+ 需要在 `config/Storage.php` 实现一个存储类
+ 需要配置 `config/Settings.php`

## 核心技术

### 后期运维

#### 新浪云 SAE – 国内领先 PaaS

+ 免运维、按使用量付费，无最低消费
+ 数据库使用云KVDB/Redis/Memcached，**每秒万次请求**
+ 自动扩容、负载均衡、数据冗余存储

#### 腾讯云 SCF (云函数)

+ 小程序直接适配自动鉴权，作为部分功能的中间件

### 后期维护

+ 使用模块化开发，**半个月从零到上线**，开发效率高
+ **前后端完全分离**，后端使用负载均衡，根据业务类型使用GoLang、Node.js、PHP，面向对象编程，**极易扩展**，通用 RESTful API
+ **异步任务队列**实现出分推送群发功能
+ 前端强类型 TypeScript 开发，**方便重构**

### 其他功能

+ 使用**原生**小程序开发，支持微信数据**预加载**、**周期性**更新，每月为用户节省1200小时
+ **爬虫**与学校官方对接实现数据**实时自动获取**；登录使用 OpenID 鉴权，其他小程序无法盗用 API
+ 第四版开始支持**校友认证**，学校网关账户绑定微信OpenID实现认证，毕业后可使用免密OpenID授权，100%保证用户真实，比学信网更简单易用；由于个人服务类目受限没有开发此功能
+ 用户直方图使用**Canvas2D**自动绘制，性能极强
+ 灰度功能**开关**；错误日志**自动上报**、微信群预警，方便紧急维护；用户反馈按钮随时接入客服
+ 使用了**端到端加密 (AES-256-GCM)**，中间人（包括微信）不可拿到任何用户数据；非选课周用户只能看到自己选择的课程；服务器**永不存储用户密码**；**撤销授权机制**，不想用了可以彻底删除所有用户数据，保证用户安全
+ 在企业微信、Mac微信、Windows微信等特殊客户端测试
+ DDOS防火墙、异地多活、WAF防火墙限频、等等……

## 开软软件使用

### GNU Affero GPL v3.0

+ [ZE3kr/BjutHelper-PHP](https://github.com/ZE3kr/BjutHelper-PHP)

### MIT License

+ [leoleoasd/zf_spider](https://github.com/leoleoasd/zf_spider)
+ [slimphp/Slim](https://github.com/slimphp/Slim)
+ [slimphp/Slim-Psr7](https://github.com/slimphp/Slim-Psr7)
