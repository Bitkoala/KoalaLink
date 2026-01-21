<p align="center">
  <img src="https://ssl.shanku.lol/pickoala/KoalaLinPro2.png" alt="KoalaLink Logo" width="200">
</p>

# <p align="center">🐨 KoalaLink Series</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/SQLite-3-003B57?style=flat-square&logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white" alt="Bootstrap">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License">
</p>

**KoalaLink** 系列是为您量身打造的专业级短链接管理解决方案。从极致便携到企业级运营，我们提供三个版本以满足不同阶段的需求。

### 🚀 演示站点 (Live Demos)
- **KoalaLink 专业版**: [https://gokaola.top](https://gokaola.top)
- **KoalaLink 轻量版**: [https://go.dsi.mom](https://go.dsi.mom)
- **KoalaLink 单页版**: [https://go.shanku.lol](https://go.shanku.lol)


[English](#english) | [中文说明](#chinese)

---

<a name="chinese"></a>
## 🇨🇳 中文说明

### 💎 版本概览

| 版本 | 定位 | 核心架构 | 适用场景 | 快速前往 |
| :--- | :--- | :--- | :--- | :--- |
| **专业版 (Pro)** | 企业级多租户平台 | PHP + MySQL/SQLite | 商业运营、多用户管理 | [查看详情](#saas-pro) |
| **轻量版 (Lite)** | 个人/团队版 | PHP + SQLite | 稳定易用的自用中转站 | [查看详情](#lite-self-hosted) |
| **单页版 (Nano)** | 极致轻量工具 | 单 PHP 文件 | 临时分享、无需后台的跳转 | [查看详情](#nano-standalone) |

---

<a name="saas-pro"></a>
### � 1. KoalaLink 专业版 (Pro)
位于 `saas/` 目录，是功能最强大的多租户短链接平台。

- **核心特性**：
  - **多用户体系**：完整的注册、登录及权限管理，支持 VIP 等级与配额限制。
  - **智能分流**：支持按设备 (iOS/Android) 及地理位置 (GeoIP) 精准路由。
  - **品牌定制**：白标模式 (White-label) 允许去除官方品牌，绑定无限量自定义域名。
  - **深度分析**：交互式全球热力图、设备看板及详细的访问轨迹追踪。
  - **开发者 API**：全功能 RESTful API，方便集成到生产环境。
- **快速开始**：直接将 `saas/` 内容部署至子域名，配置 `config.php` 即可。

---

<a name="lite-self-hosted"></a>
### 🐨 2. KoalaLink 轻量版 (Lite)
位于 **根目录**，专为追求性能与简易性的个人用户打造。

- **核心特性**：
  - **轻量架构**：基于 SQLite 数据库，无需安装 MySQL，零配置秒级启动。
  - **管理面板**：直观的 CRUD 界面，实时配置 Referer 白名单与安全跳转。
  - **数据看板**：可视化 24 小时流量统计，清晰掌握分流动态。
  - **全系统 i18n**：自动识别浏览器语言，支持中英双语切换。
- **快速开始**：
  1. 上传根目录所有文件至服务器。
  2. 访问 `admin.php` (默认密码 `admin`) 进行管理。
  3. **进阶配置**：如需启用美化链接 (`domain.com/slug`)，请查阅 [伪静态配置指南](LITE_REWRITE_GUIDE.md)。

---

<a name="nano-standalone"></a>
### 📦 3. KoalaLink 单页版 (Nano)
位于 `单页go/` 目录，是将所有逻辑压缩至 10KB 左右的极致工具。

- **核心特性**：
  - **单文件逻辑**：无需数据库，配置、加密、跳转全部集成在一个 PHP 文件内。
  - **零依赖**：甚至不需要后台，全静态配置，安全性极高。
  - **内置工具**：自带 Base64 链接生成器。
- **快速开始**：将 `go.php` 丢入任何 PHP 环境即可直接运行。

---

<a name="english"></a>
## 🇺🇸 English Description

### 💎 Versions Overview

| Version | Positioning | Architecture | Use Case | Link |
| :--- | :--- | :--- | :--- | :--- |
| **Pro (SaaS)** | Multi-tenant Platform | PHP + MySQL/SQLite | Commercial, Multi-user | [Details](#en-pro) |
| **Lite (Normal)** | Professional Self-hosted | PHP + SQLite | Personal/Team Use | [Details](#en-lite) |
| **Nano (Single)** | Ultra Lightweight Tool | Single PHP File | Temporary, Static | [Details](#en-nano) |

---

<a name="en-pro"></a>
### 👥 1. KoalaLink Pro (SaaS)
Located in `saas/`, full-featured enterprise-grade redirection platform.
- **Features**: User registration, Smart Routing (Geo/Device), White-labeling, RESTful API, Deep Analytics.
- **Demo**: [https://go.bitekaola.com](https://go.bitekaola.com)

<a name="en-lite"></a>
### 🐨 2. KoalaLink Lite (Self-hosted)
The **default files** in the root directory. Balanced performance and usability.
- **Features**: SQLite-based, Management Panel, 24h Traffic Trends, i18n support.
- **Demo**: [https://go.dsi.mom](https://go.dsi.mom)

<a name="en-nano"></a>
### 📦 3. KoalaLink Nano (Standalone)
Located in `单页go/`, the minimalist single-file script (~10KB).
- **Features**: Single-file Logic, No database, Integrated Encoder/Decoder.
- **Demo**: [https://go.shanku.lol](https://go.shanku.lol)

---

### 🛡️ Security Note
For security reasons, please log in and change the default `admin` password on the **Settings** page immediately after deployment.

© 2026 BitkoalaLab. Licensed under the MIT License.
