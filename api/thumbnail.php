<?php
/**
 * api/thumbnail.php — 读取 template/20260226.html，生成 JPG 预览图
 *
 * 访问方式：
 *   GET /api/thumbnail.php              直接输出 JPEG 图片
 *   GET /api/thumbnail.php?refresh=1   强制重新生成（忽略缓存）
 */

declare(strict_types=1);

// ── 路径配置 ──────────────────────────────────────────────────
$root      = dirname(__DIR__);
$htmlFile  = $root . '/template/20260226.html';
$outputDir = $root . '/template';
$outFile   = $outputDir . '/20260226.jpg';

// wkhtmltoimage 可执行路径
$binary  = '/usr/bin/wkhtmltoimage';
$width   = 1920;
$quality = 88;

// ── 安全：确认 HTML 文件存在 ──────────────────────────────────
if (!is_file($htmlFile)) {
    http_response_code(404);
    exit('Template file not found.');
}

// ── 创建输出目录（template 已存在，此处仅作兜底） ──────────────
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0750, true)) {
        http_response_code(500);
        exit('Cannot create output directory.');
    }
}

// ── 判断是否需要重新生成 ──────────────────────────────────────
$refresh   = isset($_GET['refresh']);
$needRegen = $refresh
    || !is_file($outFile)
    || filemtime($htmlFile) > filemtime($outFile);

if ($needRegen) {
    // 构造命令（所有参数均为白名单常量，无用户输入注入风险）
    $cmd = sprintf(
        '%s --quiet --format jpg --quality %d --width %d --disable-javascript %s %s',
        escapeshellcmd($binary),
        (int) $quality,
        (int) $width,
        escapeshellarg($htmlFile),
        escapeshellarg($outFile)
    );

    $output     = [];
    $returnCode = 0;
    exec($cmd . ' 2>&1', $output, $returnCode);

    // wkhtmltoimage 遇到字体/网络等非致命警告时也会返回非零退出码，
    // 以输出文件是否存在且有内容作为成功判断标准。
    if (!is_file($outFile) || filesize($outFile) === 0) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "wkhtmltoimage failed (exit {$returnCode}):\n";
        echo implode("\n", $output);
        exit;
    }
}

// ── 输出图片 ──────────────────────────────────────────────────
$size = filesize($outFile);
header('Content-Type: image/jpeg');
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($outFile)) . ' GMT');
readfile($outFile);
