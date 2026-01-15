<?php
/**
 * 认证控制器
 */
class AuthController extends BaseController {
    
    /**
     * 登录处理
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { try { if (class_exists('RateLimiter')) { RateLimiter::check('login'); } } catch (Exception $e) { $this->renderLogin($e->getMessage()); return; } if (!CSRF::validate()) { Logger::warning("CSRF validation failed on login"); $error = '安全验证失败，请重新提交'; } else { try { $username = Validator::validateUsername($_POST['username'] ?? ''); $password = $_POST['password'] ?? ''; if (empty($password)) { throw new InvalidArgumentException("密码不能为空"); } Logger::info("Login attempt: username={$username}"); if ($this->auth->login($username, $password)) { Logger::info("Login successful: username={$username}"); if (class_exists('AuditLogger')) { AuditLogger::logLogin($username, true); } CSRF::regenerateToken(); if ($this->auth->isDefaultPassword()) { $this->redirect('change_password', ['required' => 1]); } $this->redirect('dashboard'); } else { Logger::warning("Login failed: username={$username}"); if (class_exists('AuditLogger')) { AuditLogger::logLogin($username, false, 'Invalid credentials'); } $error = '用户名或密码错误'; } } catch (InvalidArgumentException $e) { Logger::warning("Login validation failed: " . $e->getMessage()); if (class_exists('AuditLogger') && isset($username)) { AuditLogger::logLogin($username ?? 'unknown', false, $e->getMessage()); } $error = $e->getMessage(); } } }
        $this->renderLogin($error ?? null);
    }
    
    /**
     * 登出处理
     */
    public function logout() {
        $username = $this->auth->getUsername() ?? 'unknown';
        Logger::info("User logout");
        if (class_exists('AuditLogger')) { AuditLogger::log('logout', ['username' => $username]); }
        $this->auth->logout();
        $this->redirect('login');
    }
    
    /**
     * 修改密码
     */
    public function changePassword() {
        $this->requireLogin();
        
        $error = null;
        $success = false;
        $required = isset($_GET['required']) && $_GET['required'] == '1';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!CSRF::validate()) { Logger::warning("CSRF validation failed on change password"); $error = '安全验证失败，请重新提交'; } else { try { $oldPassword = Validator::validatePassword($_POST['old_password'] ?? '', false); $newPassword = Validator::validatePassword($_POST['new_password'] ?? '', false); $confirmPassword = Validator::validatePassword($_POST['confirm_password'] ?? '', false); if (empty($oldPassword)) { throw new InvalidArgumentException("旧密码不能为空"); } if (empty($newPassword)) { throw new InvalidArgumentException("新密码不能为空"); } if (empty($confirmPassword)) { throw new InvalidArgumentException("确认密码不能为空"); } if ($newPassword !== $confirmPassword) { throw new InvalidArgumentException("新密码和确认密码不一致"); } if (strlen($newPassword) < 8) { throw new InvalidArgumentException("新密码长度至少需要 8 个字符"); } $userId = $this->auth->getUserId(); if ($this->auth->isDefaultPassword()) { if ($oldPassword !== 'admin') { $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]); if (!$user || !password_verify($oldPassword, $user['password'])) { throw new InvalidArgumentException("旧密码不正确"); } } if ($newPassword === 'admin') { throw new InvalidArgumentException("新密码不能使用默认密码"); } $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT); $this->db->update('users', ['password' => $hashedPassword, 'is_default_password' => 0], 'id = ?', [$userId]); $_SESSION['is_default_password'] = false; Logger::info("Password changed successfully for user ID: {$userId} (default password user)"); CSRF::regenerateToken(); if ($required) { $this->flash('success', '密码修改成功！'); $this->redirect('dashboard'); } $success = true; $error = null; } else { $this->auth->changePassword($userId, $oldPassword, $newPassword); Logger::info("Password changed successfully for user ID: {$userId}"); if (class_exists('AuditLogger')) { AuditLogger::logPasswordChange($userId, $this->auth->getUsername()); } CSRF::regenerateToken(); $success = true; $error = null; } } catch (InvalidArgumentException $e) { Logger::warning("Change password validation failed: " . $e->getMessage()); $error = $e->getMessage(); } catch (Exception $e) { Logger::error("Change password failed: " . $e->getMessage()); $error = '修改密码失败: ' . $e->getMessage(); } } }
        
        $this->renderChangePassword($error, $success, $required);
    }
    
    /**
     * 渲染登录页面
     */
    private function renderLogin($error = null) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('login', ['error' => $error]); } else { $viewPath = __DIR__ . '/../../ui/views/login.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: login.php"; } }
    }
    
    /**
     * 渲染修改密码页面
     */
    private function renderChangePassword($error = null, $success = false, $required = false) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('change_password', ['error' => $error, 'success' => $success, 'required' => $required]); } else { $viewPath = __DIR__ . '/../../ui/views/change_password.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: change_password.php"; } }
    }
}
