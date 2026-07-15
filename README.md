# Deployer - 单文件代码部署工具

一个类似 [Adminer](https://www.adminer.org/) 的单文件 PHP 代码部署工具，支持 Git + SSH 部署。

## 特性

- 单文件部署（`php compile.php` 编译）
- 用户认证（单用户，无角色）
- 多项目 / 多服务器管理
- Git 拉取部署 + Composer/Artisan 插件
- Webhook 自动部署（GitHub / GitLab / Gitee 签名）
- 部署历史与日志、commit 回滚
- 敏感字段 AES 加密存储

## 安装

### 开发模式

```bash
cd pfinal-deployer
php -S localhost:8090 deployer.php
```

访问 `http://localhost:8090`，默认账号 `admin` / `admin`（开发模式 `-dev` 版本会提示，首次登录请修改密码）。

### 生产模式（单文件）

```bash
php compile.php
# 将 deployer-single.php 与 storage/ 目录部署到服务器
php -S localhost:8090 deployer-single.php
```

## 环境变量（可选）

| 变量 | 说明 |
|------|------|
| `DEPLOYER_ENCRYPTION_KEY` | 加密密钥（默认 `storage/.encryption_key`） |
| `DEPLOYER_ALLOWED_IPS` | 逗号分隔 IP 白名单 |
| `DEPLOYER_SSHPASS_PATH` | sshpass 可执行文件路径 |

## Webhook

在项目编辑页启用 Webhook 后会生成 URL 和 token。请求须带平台签名头：

- GitHub: `X-Hub-Signature-256`
- GitLab: `X-Gitlab-Token`
- Gitee: `X-Gitee-Token`

## 目录结构

```
pfinal-deployer/
├── deployer.php              # 开发入口
├── compile.php               # Adminer 式编译（include 内联 + LZW 静态资源）
├── deployer-single.php       # 编译产物
├── core/
│   ├── bootstrap.inc.php     # 启动与模块加载
│   ├── functions.php         # 纯函数层
│   ├── version.inc.php       # 版本号（唯一来源）
│   └── ...
├── ui/
│   ├── static/               # CSS/JS（?file= 路由 + 缓存）
│   ├── file.inc.php
│   ├── layout.php
│   └── views/
└── storage/                  # SQLite 数据库与日志
```

## 安全建议

1. 修改默认密码，生产使用 HTTPS
2. 勿将 `storage/.encryption_key` 提交到 Git
3. 限制访问 IP（`DEPLOYER_ALLOWED_IPS`）
4. SSH 优先使用密钥认证

## 许可证

MIT License
