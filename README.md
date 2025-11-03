# Deployer - 单文件代码部署工具

一个类似 Adminer.php 的单文件 PHP 代码部署工具，支持 Git + SSH 部署。

## 特性

- 🚀 **单文件部署** - 类似 Adminer，一个 PHP 文件即可运行
- 🔐 **用户认证** - 简单的用户名密码认证系统
- 📦 **多项目管理** - 支持管理多个项目
- 🖥️ **服务器管理** - 支持多个 SSH 服务器配置
- 🔄 **Git 部署** - 基于 Git 的代码拉取和部署
- 📝 **部署历史** - 完整的部署记录和日志查看
- 🔌 **插件系统** - 支持 Composer、Artisan 等插件
- 📊 **Web 界面** - 友好的 Web 管理界面

## 安装

### 开发模式

```bash
# 克隆或下载项目
cd pfinal-deployer

# 使用 PHP 内置服务器运行
php -S localhost:8090 deployer.php
```

访问 `http://localhost:8090`

默认用户名: `admin`  
默认密码: `admin`

### 生产模式（单文件）

```bash
# 编译成单文件
php compile.php

# 运行单文件
php deployer-single.php
# 或配置到 Nginx/Apache
```

## 使用说明

### 1. 添加服务器

1. 登录系统
2. 进入"服务器"页面
3. 添加 SSH 服务器信息：
   - 服务器名称
   - 主机地址
   - SSH 端口（默认 22）
   - 用户名
   - SSH 密钥路径或上传密钥文件

### 2. 添加项目

1. 进入"项目"页面
2. 点击"添加项目"
3. 填写项目信息：
   - 项目名称
   - Git 仓库地址
   - 分支（默认 master）
   - 部署路径（服务器上的路径）
   - 选择服务器
   - 部署前/后脚本（可选）

### 3. 执行部署

1. 在项目列表或仪表板点击"部署"按钮
2. 系统会自动：
   - 连接到服务器
   - 拉取最新代码
   - 执行部署脚本
   - 运行插件任务
   - 记录部署日志

### 4. 查看部署历史

在"部署历史"页面可以查看：
- 部署状态（成功/失败/进行中）
- 提交哈希和提交信息
- 详细的部署日志

## 目录结构

```
pfinal-deployer/
├── deployer.php              # 主入口文件（开发模式）
├── compile.php               # 打包脚本
├── deployer-single.php      # 编译后的单文件
├── core/                     # 核心类
│   ├── Logger.php
│   ├── Database.php
│   ├── Auth.php
│   ├── SSHExecutor.php
│   ├── GitDeployer.php
│   ├── Deployer.php
│   └── Router.php
├── drivers/                  # 驱动
│   └── SSHDriver.php
├── plugins/                  # 插件
│   ├── PluginInterface.php
│   ├── ComposerPlugin.php
│   └── ArtisanPlugin.php
├── lang/                     # 语言包
│   └── zh.php
├── ui/                       # UI 资源
│   ├── css.php
│   ├── js.php
│   └── views/               # 视图文件
│       ├── login.php
│       ├── dashboard.php
│       ├── projects.php
│       ├── project_edit.php
│       ├── servers.php
│       ├── server_edit.php
│       └── deployments.php
└── storage/                  # 存储目录
    ├── deployer.db          # SQLite 数据库
    └── logs/                # 日志文件
```

## 插件开发

实现 `PluginInterface` 接口即可创建自定义插件：

```php
class MyPlugin implements PluginInterface {
    public function shouldRun($project) {
        // 判断是否应该运行此插件
        return true;
    }
    
    public function execute($sshExecutor, $project) {
        // 执行插件逻辑
        $command = "cd {$project['deploy_path']} && your-command";
        return $sshExecutor->execute($command);
    }
}
```

## 技术要求

- PHP 7.4+
- SQLite 扩展
- SSH 客户端（用于执行 SSH 命令）
- Git（在目标服务器上）

## 安全建议

1. **修改默认密码** - 首次登录后立即修改默认密码
2. **SSH 密钥安全** - 妥善保管 SSH 密钥文件
3. **文件权限** - 确保 `storage/` 目录有正确的写入权限
4. **HTTPS** - 生产环境建议使用 HTTPS
5. **防火墙** - 限制访问 IP，避免公开暴露

## 许可证

MIT License

## 作者

基于 Adminer 的单文件打包思路开发

