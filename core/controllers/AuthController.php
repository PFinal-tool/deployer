<?php
class AuthController extends BaseController {

    public function login(): void {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                RateLimiter::check('login');
            } catch (Exception $e) {
                $this->view('login', ['error' => $e->getMessage()]);
                return;
            }

            if (!CSRF::validate()) {
                $error = '安全验证失败，请重新提交';
            } else {
                try {
                    $username = Validator::validateUsername($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    if ($password === '') {
                        throw new InvalidArgumentException('密码不能为空');
                    }
                    if ($this->auth->login($username, $password)) {
                        AuditLogger::logLogin($username, true);
                        $this->redirect($this->auth->isDefaultPassword() ? 'change_password' : 'dashboard',
                            $this->auth->isDefaultPassword() ? ['required' => 1] : []);
                    }
                    AuditLogger::logLogin($username, false, 'Invalid credentials');
                    $error = '用户名或密码错误';
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }
        $this->view('login', ['error' => $error]);
    }

    public function logout(): void {
        AuditLogger::log('logout', ['username' => $this->auth->getUsername() ?? 'unknown']);
        $this->auth->logout();
        $this->redirect('login');
    }

    public function changePassword(): void {
        $this->requireLogin();
        $error = null;
        $success = false;
        $required = isset($_GET['required']) && $_GET['required'] == '1';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::validate()) {
                $error = '安全验证失败，请重新提交';
            } else {
                try {
                    $old = Validator::validatePassword($_POST['old_password'] ?? '', false);
                    $new = Validator::validatePassword($_POST['new_password'] ?? '', false);
                    $confirm = Validator::validatePassword($_POST['confirm_password'] ?? '', false);
                    if ($new !== $confirm) {
                        throw new InvalidArgumentException('新密码和确认密码不一致');
                    }
                    if (strlen($new) < 8) {
                        throw new InvalidArgumentException('新密码至少 8 个字符');
                    }
                    $userId = $this->auth->getUserId();
                    if ($this->auth->isDefaultPassword()) {
                        if ($old !== 'admin') {
                            $user = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
                            if (!$user || !password_verify($old, $user['password'])) {
                                throw new InvalidArgumentException('旧密码不正确');
                            }
                        }
                        if ($new === 'admin') {
                            throw new InvalidArgumentException('新密码不能使用默认密码');
                        }
                        $this->db->update('users', [
                            'password' => password_hash($new, PASSWORD_DEFAULT),
                            'is_default_password' => 0,
                        ], 'id = ?', [$userId]);
                        $_SESSION['is_default_password'] = false;
                    } else {
                        $this->auth->changePassword($userId, $old, $new);
                    }
                    CSRF::regenerateToken();
                    if ($required) {
                        $this->flash('success', '密码修改成功');
                        $this->redirect('dashboard');
                    }
                    $success = true;
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->view('change_password', compact('error', 'success', 'required'));
    }
}
