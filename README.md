# joypreview.walland.cn

电子请柬在线编辑器，支持预览模板、自定义婚礼信息并下载定制后的 HTML 请柬。

## 项目结构

```
├── index.php                   # 编辑器主界面（双栏布局，2步表单）
├── api/
│   ├── render.php              # 核心渲染引擎：读取 JSON schema，替换模板占位符
│   ├── preview.php             # 接收 POST，返回渲染后的请柬 HTML（供调试/扩展用）
│   ├── download.php            # 接收 POST，触发定制化 HTML 文件下载
│   └── thumbnail.php           # 生成模板 JPG 预览图（依赖 wkhtmltoimage）
└── template/
    ├── 20260226.html           # 原始模板（参考备份，勿修改）
    ├── 20260226.tpl.html       # 带 {{占位符}} 的渲染模板
    ├── 20260226.json           # 表单字段与占位符规则 schema
    └── 20260226.jpg            # 生成的 JPG 预览图（运行时产物，不纳入 Git）
```

## 功能

- **双栏布局**：左侧展示模板预览图，右侧填写信息表单
- **分步引导**：2步完成信息填写（基本信息 → 仪式与 RSVP 详情）
- **一键下载**：下载填写完毕、可直接部署的 HTML 请柬文件
- **预览图生成**：通过 `api/thumbnail.php` 使用 wkhtmltoimage 将模板渲染为 JPG

## 可编辑字段

| 字段 | 说明 |
|---|---|
| 新人姓名 | 新娘 / 新郎的姓名，自动合并显示 |
| 仪式日期 | 显示格式如 `MAY 10, 2026` |
| 仪式时间 | 如 `4 O'CLOCK IN THE AFTERNOON` |
| 仪式场地 | 场地名称 + 详细地址（支持多行） |
| 招待会时间 | 如 `5:30 PM IN THE EVENING` |
| 招待会场地 | 场地名称 + 详细地址（支持多行） |
| 地图链接 | Google Maps 等外部链接（可选） |
| RSVP 截止日期 | 如 `BY APRIL 1ST` |
| RSVP 表单链接 | Google Forms 等外部链接（可选） |

## 模板占位符

模板文件 `20260226.tpl.html` 中使用以下占位符，PC 端与移动端各替换一次：

`{{COUPLE_NAMES}}` · `{{CEREMONY_DATE}}` · `{{CEREMONY_TIME}}` · `{{CEREMONY_VENUE_HTML}}`  
`{{RECEPTION_TIME}}` · `{{RECEPTION_VENUE_HTML}}` · `{{MAP_LINK}}` · `{{RSVP_DEADLINE}}` · `{{RSVP_LINK}}`

## 部署

1. 将项目文件上传至支持 PHP 7.4+ 的 Web 服务器
2. 安装 wkhtmltoimage（用于生成预览图）：
   ```bash
   sudo apt-get install -y wkhtmltopdf
   ```
3. 确保字体文件已部署至服务器的 `/fonts/` 目录（或修改模板中的字体路径为 CDN 绝对 URL）：
   - `ITCEDSCR.TTF`、`Aegean.ttf`、`trajan.ttf`、`TrajanPro-Bold.otf`
4. 访问 `api/thumbnail.php` 生成预览图，再访问 `index.php` 使用编辑器

## 本地开发

```bash
php -S 0.0.0.0:8000
```

## 安全说明

- 所有文本输入均通过 `htmlspecialchars()` 转义，防止 XSS
- URL 字段通过 `parse_url()` 验证，仅允许 `http` / `https` 协议
- `api/download.php` 仅接受 POST 请求
- `api/thumbnail.php` 所有命令参数均为常量，无用户输入注入风险
