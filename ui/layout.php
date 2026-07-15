<?php
/**
 * 视图辅助函数（table 骨架 + 少量统一样式）
 */

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function ui_asset_url(string $file): string {
    return fn_me() . 'file=' . rawurlencode($file) . '&version=' . DEPLOYER_VERSION;
}

function ui_styles(): void {
    echo '<link rel="stylesheet" href="' . h(ui_asset_url('default.css')) . '">';
}

function ui_scripts(): void {
    echo '<script src="' . h(ui_asset_url('functions.js')) . '"></script>';
}

function ui_page_open(string $title, string $currentNav = ''): void {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' - ' . h(Lang::get('title')) . '</title>';
    ui_styles();
    echo '</head><body>';
    echo '<table class="page" cellpadding="0" cellspacing="0"><tr><td class="hdr">';
    ui_nav_header($currentNav);
    echo '</td></tr><tr><td class="main"><div class="wrap">';
}

function ui_page_close(bool $withJs = true): void {
    echo '</div></td></tr><tr><td class="ft">' . h(Lang::get('title'));
    echo ' &nbsp; <span class="note">v' . h(DEPLOYER_VERSION) . '</span></td></tr></table>';
    if ($withJs) {
        ui_scripts();
    }
    echo '</body></html>';
}

function ui_nav_header(string $current): void {
    $links = [
        'dashboard' => Lang::get('dashboard'),
        'projects' => Lang::get('projects'),
        'servers' => Lang::get('servers'),
        'deployments' => Lang::get('deployments'),
    ];
    echo '<table width="100%" cellpadding="12" cellspacing="0"><tr>';
    echo '<td width="220"><strong>Deployer</strong><br><span style="font-size:12px;color:#94a3b8">Git + SSH 部署</span></td>';
    echo '<td align="right">';
    foreach ($links as $action => $label) {
        if ($action === $current) {
            echo '<span class="active">' . h($label) . '</span> &nbsp; ';
        } else {
            echo '<a href="?action=' . h($action) . '">' . h($label) . '</a> &nbsp; ';
        }
    }
    echo '<a href="?action=logout">' . h(Lang::get('logout')) . '</a>';
    echo '</td></tr></table>';
}

function ui_flash(): void {
    if (empty($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = $flash['type'] ?? '';
    $cls = $type === 'success' ? 'flash flash-ok' : ($type === 'error' ? 'flash flash-err' : ($type === 'warning' ? 'flash flash-warn' : 'flash'));
    echo '<div class="' . $cls . '">' . h($flash['message'] ?? '') . '</div>';
}

/** sshpass 未安装时显示醒目提示 */
function ui_sshpass_alert(): void {
    $check = fn_sshpass_runtime_check();
    if ($check['installed']) {
        return;
    }
    echo '<div class="flash flash-warn"><b>sshpass 未安装</b> — 当前无法使用 SSH 密码登录。';
    echo ' macOS: <code>brew install sshpass</code>；';
    echo '或启动时设置 <code>DEPLOYER_SSHPASS_PATH=/path/to/sshpass</code>。';
    echo ' 也可改用 SSH 密钥认证。</div>';
}

function ui_panel_open(string $title, ?string $actionUrl = null, ?string $actionLabel = null): void {
    echo '<table class="panel" width="100%" cellpadding="0" cellspacing="0"><tr><td class="panel-hd"><table width="100%" cellpadding="0" cellspacing="0"><tr>';
    echo '<td>' . h($title) . '</td>';
    if ($actionUrl && $actionLabel) {
        echo '<td align="right"><a class="btn btn-sm btn-primary" href="' . h($actionUrl) . '">' . h($actionLabel) . '</a></td>';
    }
    echo '</tr></table></td></tr><tr><td class="panel-bd">';
}

function ui_panel_close(): void {
    echo '</td></tr></table>';
}

function ui_status_badge(string $status): void {
    $map = [
        'success' => 'badge-ok',
        'failed' => 'badge-err',
        'running' => 'badge-run',
        'pending' => 'badge-warn',
        'rolled_back' => 'badge-warn',
        'ok' => 'badge-ok',
        'warning' => 'badge-warn',
        'error' => 'badge-err',
    ];
    $cls = $map[$status] ?? 'badge-warn';
    if (in_array($status, ['ok', 'warning', 'error'], true)) {
        $labels = ['ok' => '正常', 'warning' => '警告', 'error' => '错误'];
        $label = $labels[$status];
    } else {
        $label = fn_status_label($status);
    }
    echo '<span class="badge ' . $cls . '">' . h($label) . '</span>';
}

function ui_btn(string $url, string $label, string $type = 'default'): void {
    $cls = 'btn btn-sm' . ($type === 'primary' ? ' btn-primary' : ($type === 'danger' ? ' btn-danger' : ''));
    echo '<a class="' . $cls . '" href="' . h($url) . '">' . h($label) . '</a>';
}

function ui_post_action(string $action, int $id, string $label, string $type = 'default', string $confirm = ''): void {
    echo '<form method="post" action="' . h(fn_me() . 'action=' . rawurlencode($action)) . '" style="display:inline">';
    echo CSRF::field();
    echo '<input type="hidden" name="id" value="' . (int)$id . '">';
    $cls = 'btn btn-sm' . ($type === 'primary' ? ' btn-primary' : ($type === 'danger' ? ' btn-danger' : ''));
    $onclick = $confirm !== '' ? ' onclick="return confirm(' . json_encode($confirm, JSON_UNESCAPED_UNICODE) . ')"' : '';
    echo '<button type="submit" class="' . $cls . '"' . $onclick . '>' . h($label) . '</button></form>';
}

function ui_deploy_form(int $projectId, string $defaultBranch): void {
    echo '<span class="deploy-inline"><form method="post" action="' . h(fn_me() . 'action=deploy') . '" style="display:inline" onsubmit="return confirm(\'确定部署此项目?\')">';
    echo CSRF::field();
    echo '<input type="hidden" name="id" value="' . (int)$projectId . '">';
    echo '<input type="text" name="branch" placeholder="' . h($defaultBranch) . '" title="留空使用默认分支">';
    echo ' <button type="submit" class="btn btn-sm btn-primary">' . h(Lang::get('deploy')) . '</button>';
    echo '</form></span>';
}

function ui_rollback_form(int $projectId, string $commitHash): void {
    echo '<form method="post" action="' . h(fn_me() . 'action=rollback') . '" style="display:inline" onsubmit="return confirm(\'确定回滚到此 commit?\')">';
    echo CSRF::field();
    echo '<input type="hidden" name="project_id" value="' . (int)$projectId . '">';
    echo '<input type="hidden" name="commit_hash" value="' . h($commitHash) . '">';
    echo '<button type="submit" class="btn btn-sm">' . h(Lang::get('rollback')) . '</button></form>';
}

function ui_login_page_open(): void {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h(Lang::get('login')) . ' - ' . h(Lang::get('title')) . '</title>';
    ui_styles();
    echo '</head><body><div class="login-wrap">';
    echo '<div class="login-hd"><strong style="font-size:20px">' . h(Lang::get('title')) . '</strong><br><span class="note">代码部署管理</span></div>';
}

function ui_login_page_close(): void {
    if (fn_is_dev()) {
        echo '<div class="ft note">开发模式默认账号 admin / admin，首次登录请修改密码</div>';
    }
    echo '</div></body></html>';
}
