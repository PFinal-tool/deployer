<?php
/**
 * CSS 样式（内嵌）
 */
function getCSS() {
    return <<<'CSS'
<style>
body{font:13px/1.4 Arial,sans-serif;margin:0}
.header{background:#eee;border-bottom:1px solid #ddd}
.header table{width:100%}
.wrap{max-width:1100px;margin:0 auto;padding:10px}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #ddd;padding:6px;vertical-align:top}
thead th{background:#f7f7f7;text-align:left}
a.btn,button{padding:4px 8px;border:1px solid #aaa;background:#eee;text-decoration:none;color:#000;display:inline-block}
a.btn-danger{border-color:#c00;background:#fdd;color:#900}
a.btn-small{padding:2px 6px;font-size:12px}
.form{width:700px;margin:30px auto}
.form th{width:180px;white-space:nowrap;background:#fafafa}
.alert{border:1px solid #c00;background:#fee;color:#900;padding:6px;margin:8px 0}
.note{color:#777;font-size:12px}
.code{background:#f7f7f7;border:1px solid #eee;padding:8px;white-space:pre-wrap}
.badge{display:inline-block;padding:2px 6px;border:1px solid #aaa;background:#fafafa}
.badge-success{border-color:#0a0;background:#efe}
.badge-danger{border-color:#a00;background:#fee}
.badge-warning{border-color:#a60;background:#fffae5}
</style>
CSS;
}

