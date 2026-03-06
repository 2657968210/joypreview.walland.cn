# joypreview.walland.cn

电子请柬在线编辑器，支持实时预览、自定义婚礼信息并下载定制后的 HTML 请柬。

## 项目结构

```
├── index.php                   # 编辑器主界面（双栏布局，2步表单）
├── preview.php                 # 后端：接收 POST 数据，返回渲染后的请柬 HTML
├── download.php                # 后端：接收 POST 数据，触发 HTML 文件下载
└── template/
    ├── 20260226.html           # 原始模板（保留备份，勿直接修改）
    └── 20260226.tpl.html       # 带 {{占位符}} 的模板（供 PHP 渲染替换）
```

## 功能

- **双栏布局**：左侧实时预览请柬效果，右侧填写信息表单
- **分步引导**：2步完成信息填写（基本信息 → 仪式与 RSVP 详情）
- **实时预览**：每次输入后防抖 600ms 自动刷新预览，支持全屏预览
- **一键下载**：下载填写完毕、可直接部署的 HTML 请柬文件

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
2. 确保字体文件已部署至服务器的 `/fonts/` 目录（或修改模板中的字体路径为 CDN 绝对 URL）：
   - `ITCEDSCR.TTF`
   - `Aegean.ttf`
   - `trajan.ttf`
   - `TrajanPro-Bold.otf`
3. 访问 `index.php` 即可使用编辑器

## 安全说明

- 所有文本输入均通过 `htmlspecialchars()` 转义，防止 XSS
- URL 字段通过 `parse_url()` 验证，仅允许 `http` / `https` 协议
- `preview.php` 和 `download.php` 仅接受 POST 请求
