# 自托管版本 (Lite) 伪静态配置指南

您可以配置 Web 服务器的伪静态规则，将 `domain.com/slug` 自动重写到 `go.php?to=slug`，从而实现美观的短链接。

## 1. Nginx 配置
在您的 Nginx 配置文件（通常是 `nginx.conf` 或站点配置文件）的 `server` 块中添加以下规则：

```nginx
location / {
    # 如果请求的文件或目录存在，则直接访问
    try_files $uri $uri/ @rewrite;
}

location @rewrite {
    # 将所有其他请求重写到 go.php
    rewrite ^/([a-zA-Z0-9_-]+)$ /go.php?to=$1 last;
}
```

或者更简单的写法：
```nginx
location / {
    if (!-e $request_filename){
        rewrite ^/([a-zA-Z0-9_-]+)$ /go.php?to=$1 last;
    }
}
```

## 2. Apache 配置 (.htaccess)
在根目录下创建一个 `.htaccess` 文件（如果已存在则添加以下内容）：

```apache
RewriteEngine On
RewriteBase /

# 如果不是现有文件或目录
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# 重写规则：将 /slug 映射到 go.php?to=slug
RewriteRule ^([a-zA-Z0-9_-]+)$ go.php?to=$1 [L,QSA]
```

## 3. 修改管理后台生成格式
配置好服务器后，您可能希望后台“复制链接”按钮也能生成美化后的链接。
请编辑 `admin.php` 文件：

找到 JavaScript 函数 `copyFullLink` (约第 515 行)：

**修改前：**
```javascript
const fullUrl = baseUrl + '?to=' + slug;
```

**修改后：**
```javascript
// 如果您已配置伪静态，请使用以下格式：
const serverPath = window.location.pathname.replace('admin.php', '');
const fullUrl = window.location.origin + serverPath + slug;
```

这样，点击复制时就会得到 `https://your-domain.com/slug` 格式的链接。
