<?php
require_once __DIR__ . '/../ui/css.php';
require_once __DIR__ . '/../lang/zh.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Lang::get('login'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
    <div class="login-form">
        <h2><?php echo Lang::get('login'); ?></h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label><?php echo Lang::get('username'); ?></label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label><?php echo Lang::get('password'); ?></label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn"><?php echo Lang::get('login'); ?></button>
            </div>
        </form>
    </div>
</body>
</html>

