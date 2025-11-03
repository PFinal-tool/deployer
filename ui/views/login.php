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
    <style>td input{width:260px;border:1px solid #ccc;padding:4px}</style>
</head>
<body>
<div class="header">
  <div class="wrap">
    <table><tr>
      <td><strong><?php echo Lang::get('title'); ?></strong></td>
      <td style="text-align:right"></td>
    </tr></table>
  </div>
</div>

<div class="wrap">
  <form method="POST" class="form">
    <table>
      <thead>
        <tr><th colspan="2"><?php echo Lang::get('login'); ?></th></tr>
      </thead>
      <tbody>
        <?php if (isset($error)): ?>
        <tr><td colspan="2"><div class="alert"><?php echo htmlspecialchars($error); ?></div></td></tr>
        <?php endif; ?>
        <tr>
          <th><?php echo Lang::get('username'); ?></th>
          <td><input type="text" name="username" required autofocus></td>
        </tr>
        <tr>
          <th><?php echo Lang::get('password'); ?></th>
          <td><input type="password" name="password" required></td>
        </tr>
        <tr>
          <td></td>
          <td><button type="submit" class="btn"><?php echo Lang::get('login'); ?></button></td>
        </tr>
      </tbody>
    </table>
  </form>
</div>
</body>
</html>

