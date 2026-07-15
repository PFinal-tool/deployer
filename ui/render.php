<?php
/**
 * 视图渲染（薄 I/O 层）
 */

function render_view(string $view, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    $path = fn_deployer_root() . '/ui/views/' . $view . '.php';
    if (!file_exists($path)) {
        echo 'View not found: ' . htmlspecialchars($view);
        return;
    }
    include $path;
}
