<?php
/**
 * 应用布局（Tabler UI 版）
 * 结构：顶部拉通标题栏（品牌 + 当前用户）· 下方左右分栏（左菜单 / 右工作区）
 * 全局组件：删除确认弹窗（app-modal，由 app.js 驱动）
 */
$authUser = \App\Core\Auth::user();
$active   = $active ?? '';
$flash    = flash_pull();

// 功能页 → 所属档案类别/表样（侧栏高亮与页头面包屑共用同一套定位）
$loc        = \App\Helpers\ArchiveCatalog::locate($active, $_GET);
$locGroup   = $loc['group'] !== '' ? \App\Helpers\ArchiveCatalog::group($loc['group']) : null;
$grpSystem  = str_starts_with($active, 'system_');

// 官方目录项直连台账页（?filing=组key:序号）：标题与面包屑回显目录项名称，
// 让用户「点项目看到的是项目」，而页面内容仍是对应的样表/台账。
$filingEntry = null;
$filingParam = (string)($_GET['filing'] ?? '');
if ($filingParam !== '' && str_contains($filingParam, ':')) {
    [$fgKey, $fgSeq] = explode(':', $filingParam, 2);
    $filingEntry = \App\Helpers\ArchiveCatalog::filingItem($fgKey, $fgSeq);
}
if ($filingEntry !== null) {
    $title = (string)$filingEntry[1];
}
$filingCrumb = $filingEntry !== null
    ? '第 ' . $filingEntry[0] . ' 项 · ' . $filingEntry[1]
    : '';

$flashIcons = ['success' => 'circle-check', 'danger' => 'alert-triangle', 'warning' => 'alert-triangle', 'info' => 'info-circle'];
$fi = $flashIcons[$flash['type'] ?? ''] ?? 'info-circle';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '首页') ?> - <?= e(cfg('app.name')) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset('assets/logo.svg') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/tabler.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/tabler-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
</head>
<body class="app">
<div class="page">

    <!-- ========== 顶部标题栏（拉通：品牌 + 当前用户） ========== -->
    <header class="app-header d-print-none">
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#sidebar-menu" aria-controls="sidebar-menu"
                aria-expanded="false" aria-label="展开菜单">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="app-brand" href="<?= url('dashboard/index') ?>">
            <img src="<?= asset('assets/logo.svg') ?>" width="30" height="30" alt="Logo">
            <span class="app-brand-text">
                <span class="app-brand-name"><?= e(cfg('app.name')) ?></span>
                <span class="app-brand-sub">职业卫生档案 · 内网版</span>
            </span>
            <span class="app-brand-ver"><?= e(cfg('app.version')) ?></span>
        </a>
        <div class="app-header-actions">
            <form class="d-none d-md-flex me-2 align-items-center gap-2" method="get" action="<?= url('search/index') ?>" role="search">
                <?php if (isset($_GET['r'])): ?><input type="hidden" name="r" value="search/index"><?php endif; ?>
                <div class="input-icon app-search">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>"
                           placeholder="姓名 / 身份证号 / 档案号" aria-label="全局搜索劳动者">
                </div>
                <button type="submit" class="btn btn-primary btn-sm app-search-btn" aria-label="搜索">
                    <i class="ti ti-search me-1"></i>搜索
                </button>
            </form>
            <div class="dropdown">
                <a class="app-header-user dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                   aria-expanded="false" aria-label="用户菜单">
                    <span class="avatar avatar-sm bg-primary-lt"><i class="ti ti-user"></i></span>
                    <span class="lh-sm d-none d-md-block text-truncate">
                        <span class="d-block text-truncate"><?= e($authUser['real_name'] ?: $authUser['username']) ?></span>
                        <span class="d-block small">管理员</span>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header">
                        <div class="text-truncate"><?= e($authUser['real_name'] ?: $authUser['username']) ?></div>
                        <div class="small text-secondary">管理员</div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= url('account/password') ?>">
                        <i class="dropdown-item-icon ti ti-key"></i>修改密码
                    </a>
                    <!-- 登出使用 POST 表单（带 CSRF），防跨站伪造强制登出 -->
                    <form method="post" action="<?= url('auth/logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="dropdown-item-icon ti ti-logout"></i>退出登录
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- ========== 左侧菜单 ========== -->
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-xl app-container">
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-2">
                    <li class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= url('dashboard/index') ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-layout-dashboard"></i></span>
                            <span class="nav-link-title">工作台</span>
                        </a>
                    </li>
                    <li class="nav-item <?= $active === 'archive' && $loc['group'] === '' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= url('archive/index') ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-folders"></i></span>
                            <span class="nav-link-title">档案总览</span>
                        </a>
                    </li>

                    <li class="nav-item my-2 px-3 d-none d-lg-block"><div class="sidebar-divider"></div></li>
                    <?php
                    foreach (\App\Helpers\ArchiveCatalog::groups() as $g):
                        $gOpen = $loc['group'] === $g['key'];
                        $gid = 'sidebar-g' . (int)$g['no'];
                        // 菜单仅显示档案简称（2026-11-18 用户要求：去掉「一、~六、」序号，页面标题/面包屑仍保留类别序号）
                        $gTitle = $g['short'];
                    ?>
                    <li class="nav-item dropdown <?= $gOpen ? 'active' : '' ?>">
                        <div class="nav-group">
                            <a class="nav-link nav-group-title <?= $gOpen && $loc['item'] === '' ? 'active' : '' ?>"
                               href="<?= url('archive/show', ['group' => $g['key']]) ?>"
                               title="<?= e($gTitle) ?>（栏目概览）">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-<?= e(\App\Helpers\ArchiveCatalog::icon((int)$g['no'])) ?>"></i></span>
                                <span class="nav-link-title"><?= e($gTitle) ?></span>
                            </a>
                            <a class="nav-link nav-group-caret dropdown-toggle" href="#<?= $gid ?>" data-bs-toggle="collapse"
                               role="button" aria-expanded="<?= $gOpen ? 'true' : 'false' ?>"
                               aria-label="展开<?= e($g['short']) ?>子栏目"></a>
                        </div>
                        <div class="dropdown-menu <?= $gOpen ? 'show' : '' ?>" id="<?= $gid ?>">
                            <?php
                            // 官方目录项统一入口（合并去重）：doc 类进资料管理页，form 类直进台账页
                            // 但标题/面包屑回显目录项名称，不再单列一组重复的「规范表样」。
                            $filingEntries = \App\Helpers\ArchiveCatalog::filingCatalog()[$g['key']] ?? [];
                            if ($filingEntries): ?>
                            <?php foreach ($filingEntries as $fe):
                                $fMode = \App\Helpers\ArchiveCatalog::filingMode($fe);
                                $fUrl = \App\Helpers\ArchiveCatalog::filingTargetUrl($g['key'], $fe[0]);
                                $fActive = $gOpen && $loc['item'] === 'filing:' . $fe[0];
                                $fIcon = $fMode === 'form' ? 'table' : ($fMode === 'external' ? 'upload' : 'pencil');
                            ?>
                            <a class="dropdown-item <?= $fActive ? 'active' : '' ?>" href="<?= e($fUrl) ?>" title="<?= e($fe[1]) ?>">
                                <span class="nav-item-no text-secondary"><?= e($fe[0]) ?></span>
                                <span class="nav-item-text"><?= e(mb_substr($fe[1], 0, 14)) ?><?= mb_strlen($fe[1]) > 14 ? '…' : '' ?></span>
                                <i class="ti ti-<?= $fIcon ?> ms-auto text-secondary small"></i>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <?php // 系统扩充表样（表2-10/2-11/4-4）不在官方目录48项内：侧栏不再单列，入口在资料清单 C17/C18/C25 的「去台账」及总览页 ?>
                            <?php if (!empty($g['extras'])): ?>
                                <div class="dropdown-divider"></div>
                                <?php foreach ($g['extras'] as $ex): ?>
                                <a class="dropdown-item <?= $gOpen && $loc['item'] === 'extra:' . $ex['key'] ? 'active' : '' ?>" href="<?= url($ex['route']) ?>">
                                    <i class="dropdown-item-icon ti ti-settings-2"></i><span class="nav-item-text"><?= e($ex['nav']) ?></span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>

                    <li class="nav-item my-2 px-3 d-none d-lg-block"><div class="sidebar-divider"></div></li>
                    <?php
                    // 基础数据设置：初期建档四步，独立于六类档案；结构与六类分组一致（标题 + 独立箭头）
                    $basics = \App\Helpers\ArchiveCatalog::basics();
                    $basicKeys = array_column($basics, 'key');
                    $basicOpen = in_array($active, $basicKeys, true);
                    ?>
                    <li class="nav-item dropdown <?= $basicOpen ? 'active' : '' ?>">
                        <div class="nav-group">
                            <a class="nav-link nav-group-title <?= $basicOpen ? 'active' : '' ?>"
                               href="<?= url($basics[0]['route']) ?>" title="基础数据设置（单位信息）">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-adjustments-horizontal"></i></span>
                                <span class="nav-link-title">基础数据设置</span>
                            </a>
                            <a class="nav-link nav-group-caret dropdown-toggle" href="#sidebar-basics" data-bs-toggle="collapse"
                               role="button" aria-expanded="<?= $basicOpen ? 'true' : 'false' ?>" aria-label="展开基础数据设置子项"></a>
                        </div>
                        <div class="dropdown-menu <?= $basicOpen ? 'show' : '' ?>" id="sidebar-basics">
                            <?php foreach ($basics as $b): ?>
                            <a class="dropdown-item <?= $active === $b['key'] ? 'active' : '' ?>" href="<?= url($b['route']) ?>">
                                <i class="dropdown-item-icon ti ti-<?= $b['key'] === 'company' ? 'building' : ($b['key'] === 'posts' ? 'briefcase' : 'list') ?>"></i><?= e($b['nav']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </li>

                    <li class="nav-item <?= $active === 'prints' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= url('prints/index') ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-printer"></i></span>
                            <span class="nav-link-title">表样打印中心</span>
                        </a>
                    </li>

                    <li class="nav-item mt-2 px-3 small text-secondary text-uppercase d-none d-lg-block">原件与系统</li>
                    <li class="nav-item <?= $active === 'checklist' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= url('archive/checklist') ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-list-check"></i></span>
                            <span class="nav-link-title">资料清单</span>
                        </a>
                    </li>
                    <li class="nav-item <?= $active === 'documents' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= url('documents/index') ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-file-upload"></i></span>
                            <span class="nav-link-title">分类文档（原件）</span>
                        </a>
                    </li>

                    <li class="nav-item dropdown <?= $grpSystem ? 'active' : '' ?>">
                        <a class="nav-link dropdown-toggle" href="#sidebar-system" data-bs-toggle="collapse" role="button"
                           aria-expanded="<?= $grpSystem ? 'true' : 'false' ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-settings"></i></span>
                            <span class="nav-link-title">系统管理</span>
                        </a>
                        <div class="dropdown-menu <?= $grpSystem ? 'show' : '' ?>" id="sidebar-system">
                            <a class="dropdown-item <?= $active === 'system_users' ? 'active' : '' ?>" href="<?= url('users/index') ?>">
                                <i class="dropdown-item-icon ti ti-users"></i>账号管理
                            </a>
                            <a class="dropdown-item <?= $active === 'system_logs' ? 'active' : '' ?>" href="<?= url('system/logs') ?>">
                                <i class="dropdown-item-icon ti ti-history"></i>操作日志
                            </a>
                            <a class="dropdown-item <?= $active === 'system_backup' ? 'active' : '' ?>" href="<?= url('system/backup') ?>">
                                <i class="dropdown-item-icon ti ti-database-export"></i>数据备份
                            </a>
                        </div>
                    </li>

                    <li class="nav-item <?= $active === 'standards' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= url('standards/index') ?>">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-book"></i></span>
                            <span class="nav-link-title">标准依据</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </aside>

    <!-- ========== 页面主体 ========== -->
    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl app-container">
                <div class="page-header d-print-none mb-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h2 class="page-title mb-1"><?= e($title ?? '') ?></h2>
                            <div class="page-pretitle page-subtitle">
                                <?php if ($locGroup): ?>
                                    <a class="text-secondary text-decoration-none" href="<?= url('archive/show', ['group' => $locGroup['key']]) ?>"><?= e(\App\Helpers\ArchiveCatalog::numeral((int)$locGroup['no'])) ?>、<?= e($locGroup['short']) ?></a>
                                    <?php
                                    $locItem = null;
                                    foreach ($locGroup['items'] as $it) { if ($it['no'] === $loc['item']) { $locItem = $it; } }
                                    if ($locItem): ?> / <?= e($locItem['no']) ?> <?= e($locItem['nav']) ?>
                                    <?php elseif ($filingCrumb !== ''): ?> / <?= e($filingCrumb) ?><?php endif; ?>
                                <?php elseif (str_starts_with($loc['item'], 'basic:')):
                                    $bk = substr($loc['item'], 6);
                                    $basicNav = '';
                                    foreach (\App\Helpers\ArchiveCatalog::basics() as $b) { if ($b['key'] === $bk) { $basicNav = $b['nav']; break; } }
                                    ?>基础数据设置 / <?= e($basicNav) ?>
                                <?php else: ?>职业健康监护档案<?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible" role="alert">
                    <i class="ti ti-<?= $fi ?> alert-icon"></i>
                    <div><?= e($flash['msg']) ?></div>
                    <a class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></a>
                </div>
                <?php endif; ?>

                <?= $content ?? '' ?>
            </div>

            <footer class="footer footer-transparent d-none d-lg-flex d-print-none">
                <div class="container-xl app-container text-center">
                    <div class="small text-secondary">
                        <?= e(cfg('app.name')) ?> · <?= e(cfg('app.version')) ?> · 六类职业卫生档案
                    </div>
                </div>
            </footer>
        </div>
    </div>
</div>

<!-- ========== 全局确认弹窗（删除等危险操作） ========== -->
<div class="modal modal-blur fade" id="app-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-status bg-warning"></div>
            <div class="modal-body text-center py-4">
                <i class="ti ti-alert-triangle text-warning" style="font-size: 3rem;"></i>
                <h3 class="mt-2" id="app-modal-title">确认操作</h3>
                <div class="text-secondary" id="app-modal-text"></div>
            </div>
            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col"><button type="button" class="btn w-100" data-bs-dismiss="modal">取消</button></div>
                        <div class="col"><button type="button" class="btn btn-danger w-100" id="app-modal-ok">确认</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('assets/hazard-picker.js') ?>"></script>
<script src="<?= asset('assets/app.js') ?>"></script>
</body>
</html>
