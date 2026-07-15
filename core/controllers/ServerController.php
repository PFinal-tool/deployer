<?php
class ServerController extends BaseController {

    public function index(): void {
        $this->requireLogin();
        $servers = $this->db->fetchAll('SELECT * FROM servers ORDER BY id DESC');
        $this->view('servers', ['servers' => $servers]);
    }

    public function edit(): void {
        $this->requireLogin();
        $id = $_GET['id'] ?? null;
        $server = null;

        if ($id) {
            try {
                $server = $this->db->fetchOne('SELECT * FROM servers WHERE id = ?', [Validator::validateServerId($id)]);
                if ($server) {
                    unset($server['key_content']);
                }
            } catch (InvalidArgumentException $e) {
                $this->flash('error', '无效的服务器 ID');
                $this->redirect('servers');
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::validate()) {
                $this->flash('error', '安全验证失败');
                $this->redirect('servers');
            }
            try {
                $fullServer = $id ? $this->db->fetchOne('SELECT * FROM servers WHERE id = ?', [Validator::validateServerId($id)]) : null;
                $data = fn_server_form_data($_POST, $fullServer, $_FILES);
                $usesPassword = !empty($data['password']);
                if ($id) {
                    $validatedId = Validator::validateServerId($id);
                    $this->db->update('servers', $data, 'id = ?', [$validatedId]);
                    AuditLogger::logServer('updated', $validatedId, $data['name']);
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $newId = $this->db->insert('servers', $data);
                    AuditLogger::logServer('created', $newId, $data['name']);
                }
                CSRF::regenerateToken();
                $savedMsg = $id ? '服务器更新成功' : '服务器创建成功';
                if ($usesPassword && !fn_sshpass_installed()) {
                    $this->flash('warning', $savedMsg . '，但本机未安装 sshpass，密码登录暂不可用');
                } else {
                    $this->flash('success', $savedMsg);
                }
                $this->redirect('servers');
            } catch (Exception $e) {
                $this->flash('error', '保存失败: ' . $e->getMessage());
            }
        }

        $this->view('server_edit', ['server' => $server]);
    }

    public function delete(): void {
        $this->requireLogin();
        $this->requirePost();
        try {
            $this->requireCsrf();
            $id = Validator::validateServerId($_POST['id'] ?? null);
            $server = $this->db->fetchOne('SELECT name FROM servers WHERE id = ?', [$id]);
            $this->db->delete('servers', 'id = ?', [$id]);
            AuditLogger::logServer('deleted', $id, $server['name'] ?? null);
            $this->flash('success', '服务器删除成功');
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('servers');
    }

    public function test(): void {
        $this->requireLogin();
        $this->requirePost();
        try {
            $this->requireCsrf();
            $id = Validator::validateServerId($_POST['id'] ?? null);
            $server = $this->db->fetchOne('SELECT * FROM servers WHERE id = ?', [$id]);
            if (!$server) {
                throw new InvalidArgumentException('服务器不存在');
            }
            if (!empty($server['password']) && empty($server['key_path']) && empty($server['key_content'])) {
                fn_require_sshpass_for_password();
            }
            $exec = new SSHExecutor(fn_ssh_config($server));
            $exec->testConnection();
            $this->flash('success', '连接成功');
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('servers');
    }
}
