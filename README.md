<p align="center">
  <img src="https://pickoala.com/img/images/2026/01/01/S9FVrAhU.webp" alt="KoalaLink Logo" width="200">
</p>

# <p align="center">🐨 KoalaLink</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/SQLite-3-003B57?style=flat-square&logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white" alt="Bootstrap">
  <img src="https://img.shields.io/badge/Chart.js-3.9-FF6384?style=flat-square&logo=chartdotjs&logoColor=white" alt="Chart.js">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License">
</p>

**KoalaLink** 是一款专业级、轻量化的短链接中转与流量统计系统。基于原生 PHP 和 SQLite 架构，旨在为中小型项目提供私密、安全且易于管理的跳转解决方案。

[English](#english) | [中文](#chinese)

---

<a name="chinese"></a>
## 🇨🇳 中文说明

### ✨ 核心特性
- **多模式跳转**：支持别名 (Slug)、Base64 加密以及直接 URL 跳转。
- **品牌中转页**：内置倒计时、安全分级检测及品牌化 UI。
- **数据仪表盘**：全自动化流量统计，支持 24 小时趋势、来源 (Referer) 分析及热门动态跳转记录。
- **管理后台**：一键 CRUD、实时配置 Referer 白名单及信任域名。
- **全系统 i18n**：自动识别浏览器语言，支持中英双语切换。
- **极致轻量**：仅需 PHP 环境，无需传统 MySQL 数据库，秒级部署。

### 🚀 快速开始
1. **环境要求**：PHP 7.4+ (需开启 `pdo_sqlite` 扩展)。
2. **部署**：将所有文件上传至 Web 服务器目录。
3. **权限**：确保程序目录具有写权限（用于自动创建 `redirect.db`）。
4. **登录**：访问 `admin.php`，默认密码为 `admin`。
5. **修改密码**：登录后进入“全局设置”页面即可在线修改管理员密码，无需修改代码。

### 📂 文件结构
- `go.php`: 核心路由与中转引擎。
- `admin.php`: 管理后台与配置中心。
- `analytics.php`: 数据可视化分析。
- `404.php`: 品牌化错误提示页。
- `logo.png` / `Favicon.png`: 品牌资产文件。

### 📦 单页版 (Standalone)
在 `单页go/` 目录下提供了一个**简化版**的独立脚本：
- **极简体验**：专为不需要复杂管理功能的用户设计，轻量且高效。
- **零依赖**：无需数据库，所有配置均在 `go.php` 文件头部代码中修改。
- **功能集成**：单文件内同时包含 Base64 解密跳转与链接生成工具。
- **快速部署**：适用于临时项目或无需后台管理的纯静态跳转需求。

### 👥 SaaS 多租户版 (Multi-tenant)
在 `saas/` 目录下提供了一个完整的 **SaaS 平台级** 版本。除基础功能外，包含以下**企业级增强特性**：

- **用户体系**：支持用户自主注册、登录及会话管理。
- **数据隔离**：每个用户拥有独立的后台，仅能管理和统计自己的链接。
- **智能分流**：针对设备(iOS/Android)与地理位置(GeoIP)的自动路由，支持 A/B 测试。
- **安全中心**：集成 Google Safe Browsing 实时拦截恶意链接，支持防盗链 Referer 白名单。
- **品牌私有化**：支持去除 "KoalaLink" 品牌后缀 (White-label)，绑定自定义域名 HTTPS。
- **深度分析**：包含国家/地区分布地图、设备/浏览器占比及访问时段趋势图。
- **链接控制**：支持过期时间、最大点击限制及过期后的 Fallback 跳转地址。
- **运营模型**：内置等级限制（普通用户限 5 条链接，VIP 用户无限制），仪表盘实时显示配额进度条。
- **数据库升级**：原生支持 SQLite 与 **MySQL/MariaDB**，通过 `saas/config.php` 一键切换。
- **开发者 API**：支持通过 `X-API-KEY` 进行 RESTful 调用，实现链接自动化创建与数据查询。
- **默认超管账号**：用户名 `admin`，密码 `admin` (请登录后立即在个人面板修改)。

---

<a name="english"></a>
## 🇺🇸 English Description

### ✨ Key Features
- **Multi-mode Redirection**: Supports Custom Slugs, Base64 Encoding, and Direct URL parameters.
- **Branded Bridge**: Professional intermediate page with security assessment and countdown.
- **Analytics Dashboard**: Automatic traffic tracking with 24h trends, Referer insights, and dynamic link logs.
- **Control Panel**: User-friendly CRUD interface and real-time security configuration.
- **Full i18n Support**: Auto-detects browser language (English & Chinese).
- **Ultra Lightweight**: Pure PHP + SQLite architecture. No heavy database required.

### 🚀 Quick Start
1. **Requirements**: PHP 7.4+ with `pdo_sqlite` extension enabled.
2. **Deployment**: Upload all files to your web server.
3. **Permissions**: Ensure the directory has write access for the SQLite database (`redirect.db`).
4. **Login**: Access `admin.php`. Default password is `admin`.
5. **Change Password**: You can change the admin password directly on the "Settings" page after logging in. No code modification required.

### 🛠️ Technology Stack
- **Backend**: Native PHP & SQLite PDO.
- **Frontend**: Bootstrap 5, Chart.js, Bootstrap Icons.
- **Design**: Modern UI with Glassmorphism and Harmony color palette.

### 📦 Simplified Standalone Version
A **simplified version** of the redirect script is available in the `单页go/` directory:
- **Lightweight**: Designed for users who don't need the database, dashboard, or analytics.
- **Zero Dependencies**: No database required; all settings are manually configured in the file header.
- **All-in-One**: Integrated Base64 decoder and link generator in a single file.
- **Easy Setup**: Best for temporary projects or simple static site redirects.

### 👥 SaaS Multi-tenant Version
A complete **Platform-level** version is available in the `saas/` directory. Features include:

- **User System**: Supports independent user registration, login, and session management.
- **Data Isolation**: Each user has a private dashboard to manage and track their own links.
- **Smart Routing**: Auto-route by Device (iOS/Android) & Geo-location, with A/B Testing support.
- **Security Suite**: Real-time malware scanning via Google Safe Browsing & Anti-hotlink Referer whitelists.
- **White-labeling**: Option to remove "KoalaLink" branding suffixes and bind Custom Domains (HTTPS).
- **Deep Analytics**: Interactive Heatmaps, Device/Browser breakdown, and 7-day traffic trends.
- **Link Control**: Set Expiry dates, Max clicks, and Fallback URLs for expired links.
- **Admin Panel**: Dedicated `admin.php` for platform oversight, user authorization, and **VIP tier management**.
- **Monetization Ready**: Built-in quotas (Free: 5 links, VIP: Unlimited) with real-time usage progress bars.
- **Enterprise DB**: Supports both SQLite and **MySQL/MariaDB** via `saas/config.php`.
- **Developer API**: RESTful endpoints with `X-API-KEY` auth for automated link management.
- **Default Credentials**: Username `admin`, password `admin` (Please update via dashboard after login).

---

### 🛡️ Security Note
For security reasons, please log in and change the default `admin` password on the **Settings** page immediately after deployment.

© 2026 BitkoalaLab. Licensed under the MIT License.
