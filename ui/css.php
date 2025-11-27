<?php
/**
 * CSS 样式（内嵌）
 */
function getCSS() {
    return <<<'CSS'
<style>
body{font:13px/1.5 "Segoe UI", Roboto, Helvetica, Arial, sans-serif;margin:0;background:#f9f9f9;color:#333}
.header{background:#fff;border-bottom:1px solid #eee;box-shadow:0 2px 5px rgba(0,0,0,0.03);padding:10px 0}
.header table{width:100%}
.wrap{max-width:1200px;margin:0 auto;padding:20px}

/* 布局表格 */
.layout-table { width:100%; border:none; margin-bottom: 20px; }
.layout-table td { border:none; padding:0; vertical-align:top; }
.layout-col-left { width: 65%; padding-right: 20px !important; }
.layout-col-right { width: 35%; }

/* 数据表格 */
.data-table { width:100%; border-collapse:separate; border-spacing:0; background:#fff; border:1px solid #e0e0e0; border-radius:4px; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.02); }
.data-table th, .data-table td { border:none; border-bottom:1px solid #eee; padding:12px 15px; text-align:left; }
.data-table th { background:#f8f9fa; color:#666; font-weight:600; border-bottom:2px solid #eee; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:#fcfcfc; }

/* 统计卡片 */
.stat-card { background:#fff; border:1px solid #e0e0e0; border-radius:4px; padding:15px 20px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.02); display:flex; align-items:center; justify-content:space-between; }
.stat-card .title { color:#888; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:5px; }
.stat-card .value { font-size:24px; font-weight:600; color:#333; }
.stat-card .icon { font-size:24px; opacity:0.2; }

/* 按钮样式 */
.btn { display:inline-block; padding:6px 12px; border-radius:4px; text-decoration:none; font-size:13px; transition:all 0.2s; border:1px solid transparent; cursor:pointer; line-height:1.4; }
.btn-primary { background:#007bff; color:#fff; border-color:#007bff; }
.btn-primary:hover { background:#0069d9; border-color:#0062cc; }
.btn-default { background:#f8f9fa; color:#333; border-color:#ddd; }
.btn-default:hover { background:#e2e6ea; border-color:#dae0e5; }
.btn-danger { background:#dc3545; color:#fff; border-color:#dc3545; }
.btn-danger:hover { background:#c82333; border-color:#bd2130; }
.btn-sm, .btn-small { padding:4px 8px; font-size:12px; }

/* 徽章 */
.badge { display:inline-block; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:600; line-height:1; text-align:center; white-space:nowrap; vertical-align:baseline; }
.badge-success { background-color:#d4edda; color:#155724; }
.badge-warning { background-color:#fff3cd; color:#856404; }
.badge-danger { background-color:#f8d7da; color:#721c24; }
.badge-pending { background-color:#e2e3e5; color:#383d41; }

/* 辅助类 */
.text-right { text-align:right; }
.text-center { text-align:center; }
.mt-20 { margin-top:20px; }
.mb-20 { margin-bottom:20px; }
.text-muted { color:#6c757d; }

/* 日志输出 */
.log-output { background:#2d2d2d; color:#f8f8f2; padding:15px; border-radius:4px; max-height:500px; overflow:auto; font-family:Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace; font-size:12px; line-height:1.5; white-space: pre-wrap; word-break: break-all; }

/* 环境检测折叠面板 */
.env-check-summary { cursor:pointer; padding:10px 15px; background:#fff; border:1px solid #e0e0e0; border-radius:4px; display:flex; justify-content:space-between; align-items:center; margin-top:20px; }
.env-check-details { display:none; margin-top:10px; animation: slideDown 0.3s ease-out; }
.env-check-details table { width:100%; }

@keyframes slideDown {
    from { opacity:0; transform:translateY(-10px); }
    to { opacity:1; transform:translateY(0); }
}
</style>
CSS;
}
