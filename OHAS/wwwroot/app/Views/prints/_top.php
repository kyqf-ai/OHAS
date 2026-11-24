<?php
/**
 * 打印页公共骨架（上半部）：doctype / 样式 / 工具栏 / 纸张开始。
 * 变量：
 *   $no        表号（如 表2-1）
 *   $printBtn  主按钮文字（默认「打印」）
 *   $backUrl   返回链接（默认六类档案总览）
 *   $backLabel 返回按钮文字
 *   $orient    portrait|landscape（默认 portrait）
 *   $extraHead 额外页级样式（如表单类的行高）
 */
$orient   = $orient   ?? 'portrait';
$printBtn = $printBtn ?? '打印';
$backUrl  = $backUrl  ?? url('prints/index');
$backLabel = $backLabel ?? '返回打印中心';
$extraHead = $extraHead ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title><?= e($no ?? '') ?><?= isset($spec['title']) ? ' ' . e($spec['title']) : '' ?> - 打印</title>
<link rel="stylesheet" href="<?= asset('assets/print.css') ?>">
<?php if ($orient === 'landscape'): ?>
<style>@page { size: A4 landscape; margin: 12mm; }</style>
<?php endif; ?>
<?= $extraHead ?>
</head>
<body>
<div class="ptoolbar<?= $orient === 'landscape' ? ' ptoolbar-land' : '' ?>">
    <span class="phint">打印时请选择 A4 <?= $orient === 'landscape' ? '横向' : '纵向' ?>，边距默认；如需归档电子件可选择「另存为 PDF」</span>
    <button class="pbtn" onclick="window.print()"><?= e($printBtn) ?></button>
    <a class="pbtn pbtn-sub" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
</div>
<div class="psheet<?= $orient === 'landscape' ? ' psheet-land' : '' ?>">
