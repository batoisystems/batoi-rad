<?php
namespace Core\App;

/**
 * Auth UI helper for embedding MFA, forgot-password, and change-password
 * screens inside custom microservice routes.
 *
 * Typical usage inside a route.* file:
 * $authUi = new \Core\App\AuthUi($this->runData);
 * if ($this->runData['request']->method === 'POST') {
 *     $result = $authUi->requestPasswordReset($_POST['s_username'] ?? '');
 * }
 * echo $authUi->renderForgotPassword([
 *     'action' => $this->runData['route']['base_url'] . '/my/forgot',
 *     'message' => $result['message'] ?? null,
 *     'status' => $result['status'] ?? null,
 * ]);
 */
class AuthUi {
    private array $runData;
    private $db;
    private $errorHandler;
    private UiTemplate $ui;

    public function __construct(array $runData, ?UiTemplate $ui = null) {
        $this->runData = $runData;
        $this->db = $runData['db'];
        $this->errorHandler = $runData['errorHandler'] ?? null;
        $this->ui = $ui ?: new UiTemplate($runData['config'] ?? []);
    }

    public function renderForgotPassword(array $vars = []): string {
        return $this->ui->render('auth/forgot-password', $vars);
    }

    public function renderChangePassword(array $vars = []): string {
        return $this->ui->render('auth/change-password', $vars);
    }

    public function renderMfaSettings(array $vars = []): string {
        return $this->ui->render('auth/mfa-settings', $vars);
    }

    /**
     * Send a reset link to the user's email.
     * Returns: ['status' => 'success'|'danger', 'message' => string]
     */
    public function requestPasswordReset(string $username): array {
        $username = trim($username);
        if ($username === '') {
            return ['status' => 'danger', 'message' => 'Username is required.'];
        }
        $ip = $this->runData['request']->ip ?? '';
        if (!$this->canRequestPasswordReset($ip)) {
            return ['status' => 'success', 'message' => 'If the account exists, you will receive a reset link shortly.'];
        }
        $userDetails = $this->db->query(
            "SELECT * FROM s_entity
             WHERE s_type = 'U'
               AND (s_identity = :identity OR s_email = :email)
               AND livestatus IN ('0','1')
             LIMIT 1",
            [':identity' => $username, ':email' => $username]
        );
        if (count($userDetails) === 1) {
            $user = $userDetails[0];
            $email = $user['s_email'] ?? '';
            if ($email === '' && strpos($user['s_identity'] ?? '', '@') !== false) {
                $email = $user['s_identity'];
            }
            if ($email !== '') {
                $token = $this->generateResetToken();
                $tokenHash = $this->hashResetToken($token);
                $expiresAt = $this->expiryTimestamp(30);
                $this->createResetRequest((int)$user['id'], $tokenHash, $expiresAt, $ip, $this->runData['request']->user_agent ?? '');
                $resetPasswordLink = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/') . '/login/forgotpassword/' . $token;
                $author = $this->runData['config']['sys']['author'] ?? 'Administrator';
                $subject = 'Reset Password at ' . ($this->runData['config']['sys']['project_title'] ?? 'RAD');
                $body = $this->generateResetPasswordEmailBody($user['s_name'] ?? '', $email, $resetPasswordLink, $author);
                $this->sendEmail($email, $subject, $body);
            }
        }
        return ['status' => 'success', 'message' => 'If the account exists, you will receive a reset link shortly.'];
    }

    /**
     * Reset password by token (same behavior as /login/forgotpassword/{token}).
     * Returns: ['status' => 'success'|'danger', 'message' => string]
     */
    public function resetPasswordWithToken(string $token, string $newPassword, string $confirmPassword): array {
        $token = trim($token);
        if ($token === '') {
            return ['status' => 'danger', 'message' => 'Reset link is invalid or expired.'];
        }
        if ($newPassword === '' || strlen($newPassword) < 8) {
            return ['status' => 'danger', 'message' => 'New password must be at least 8 characters.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['status' => 'danger', 'message' => 'Passwords do not match.'];
        }
        $resetRow = $this->findResetToken($token);
        if (!$resetRow) {
            return ['status' => 'danger', 'message' => 'Reset link is invalid or expired.'];
        }
        $userId = (int)($resetRow['s_entity_id'] ?? 0);
        if ($userId <= 0) {
            return ['status' => 'danger', 'message' => 'Invalid user.'];
        }
        $this->db->update('s_entity', [
            's_identity_secret' => password_hash($newPassword, PASSWORD_BCRYPT)
        ], ['id' => $userId]);
        $this->markResetUsed((int)$resetRow['id']);
        return ['status' => 'success', 'message' => 'Password updated successfully.'];
    }

    /**
     * Change password for the current user.
     * Returns: ['status' => 'success'|'danger', 'message' => string]
     */
    public function changePassword(int $entityId, string $currentPassword, string $newPassword): array {
        $currentPassword = (string)$currentPassword;
        $newPassword = (string)$newPassword;
        if ($newPassword === '' || strlen($newPassword) < 8) {
            return ['status' => 'danger', 'message' => 'New password must be at least 8 characters.'];
        }
        $userRow = $this->db->select('s_entity', ['id' => $entityId], true);
        if (count($userRow) < 1) {
            return ['status' => 'danger', 'message' => 'User not found.'];
        }
        $user = $userRow[0];
        if (!password_verify($currentPassword, $user['s_identity_secret'])) {
            return ['status' => 'danger', 'message' => 'Current password is incorrect.'];
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $ok = $this->db->update('s_entity', ['s_identity_secret' => $hash], ['id' => $entityId]);
        if (!$ok) {
            return ['status' => 'danger', 'message' => 'Error updating password.'];
        }
        return ['status' => 'success', 'message' => 'Password updated successfully.'];
    }

    /**
     * Get MFA state for a user and optional OTPAuth URL.
     */
    public function getMfaState(int $entityId): array {
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return ['status' => 'danger', 'message' => 'User not found.'];
        }
        $user = $rows[0];
        $secret = $user['s_mfa_secret'] ?? null;
        $issuer = $this->runData['config']['sys']['project_title'] ?? 'RAD';
        $identity = $user['s_identity'] ?? '';
        return [
            'status' => 'success',
            'enabled' => ($user['s_enable_mfa'] ?? 'N') === 'Y',
            'secret' => $secret,
            'backup_count' => $this->countBackupCodes($user['s_mfa_backup_codes'] ?? null),
            'otpauth' => $this->buildOtpAuth($issuer, $identity, $secret),
        ];
    }

    /**
     * Handle MFA actions: reset, verify, disable, regen-codes.
     * Returns: ['status' => 'success'|'danger'|'info', 'message' => string, 'secret' => string|null, 'backup_codes' => array|null]
     */
    public function handleMfaAction(int $entityId, string $action, array $data = []): array {
        $rows = $this->db->select('s_entity', ['id' => $entityId], true);
        if (empty($rows)) {
            return ['status' => 'danger', 'message' => 'User not found.'];
        }
        $user = $rows[0];
        $mfaService = new \Core\Sys\MfaService($this->runData['config'] ?? [], $this->errorHandler);

        if ($action === 'reset') {
            $secret = $this->generateSecret();
            $codes = $this->generateBackupCodes();
            $hashed = array_map(fn($c) => hash('sha256', $c), $codes);
            $this->db->update('s_entity', [
                's_mfa_secret' => $secret,
                's_enable_mfa' => 'N',
                's_mfa_backup_codes' => json_encode($hashed),
            ], ['id' => $entityId]);
            return [
                'status' => 'info',
                'message' => 'MFA secret regenerated. Verify to enable.',
                'secret' => $secret,
                'backup_codes' => $codes,
            ];
        }
        if ($action === 'verify') {
            $code = trim((string)($data['code'] ?? ''));
            $secret = $user['s_mfa_secret'] ?? '';
            if ($secret !== '' && $mfaService->totpVerify($secret, $code)) {
                $this->db->update('s_entity', ['s_enable_mfa' => 'Y'], ['id' => $entityId]);
                return ['status' => 'success', 'message' => 'MFA enabled.'];
            }
            return ['status' => 'danger', 'message' => 'Invalid code.'];
        }
        if ($action === 'disable') {
            $this->db->update('s_entity', [
                's_mfa_secret' => null,
                's_mfa_backup_codes' => null,
                's_enable_mfa' => 'N',
            ], ['id' => $entityId]);
            return ['status' => 'info', 'message' => 'MFA disabled.'];
        }
        if ($action === 'regen-codes') {
            $codes = $this->generateBackupCodes();
            $hashed = array_map(fn($c) => hash('sha256', $c), $codes);
            $this->db->update('s_entity', [
                's_mfa_backup_codes' => json_encode($hashed),
            ], ['id' => $entityId]);
            return [
                'status' => 'success',
                'message' => 'Backup codes regenerated.',
                'backup_codes' => $codes,
            ];
        }
        return ['status' => 'danger', 'message' => 'Unknown MFA action.'];
    }

    private function countBackupCodes($value): int {
        if (empty($value)) {
            return 0;
        }
        $decoded = is_array($value) ? $value : json_decode((string)$value, true);
        return is_array($decoded) ? count($decoded) : 0;
    }

    private function generateSecret(): string {
        $bytes = random_bytes(10);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        foreach (str_split(bin2hex($bytes), 2) as $pair) {
            $secret .= $alphabet[(hexdec($pair) % strlen($alphabet))];
        }
        return $secret;
    }

    private function generateBackupCodes(): array {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        return $codes;
    }

    private function buildOtpAuth(string $issuer, string $email, ?string $secret): ?string {
        if (!$secret) {
            return null;
        }
        $label = rawurlencode($issuer . ':' . $email);
        $issuerEnc = rawurlencode($issuer);
        return sprintf('otpauth://totp/%s?secret=%s&issuer=%s', $label, $secret, $issuerEnc);
    }

    private function generatePassword(int $length): string {
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        $max = strlen($pool) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $pool[random_int(0, $max)];
        }
        return $out;
    }

    private function generateResetToken(): string {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hashResetToken(string $token): string {
        return hash('sha256', $token);
    }

    private function expiryTimestamp(int $minutes): string {
        $dt = new \DateTime('now', new \DateTimeZone('UTC'));
        $dt->modify('+' . $minutes . ' minutes');
        return $dt->format('Y-m-d H:i:s');
    }

    private function canRequestPasswordReset(string $ip): bool {
        $ip = trim($ip);
        if ($ip === '') {
            return true;
        }
        $windowMinutes = 15;
        $limit = 5;
        $since = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->modify('-' . $windowMinutes . ' minutes')
            ->format('Y-m-d H:i:s');
        $rows = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM s_password_reset WHERE s_ip = :ip AND createstamp >= :since",
            [':ip' => $ip, ':since' => $since]
        );
        $count = (int)($rows[0]['cnt'] ?? 0);
        return $count < $limit;
    }

    private function createResetRequest(int $entityId, string $tokenHash, string $expiresAt, string $ip, string $userAgent): void {
        $this->db->insert('s_password_reset', [
            's_entity_id' => $entityId,
            's_token_hash' => $tokenHash,
            's_expires_at' => $expiresAt,
            's_ip' => $ip,
            's_user_agent' => $userAgent
        ]);
    }

    private function findResetToken(string $token): ?array {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $hash = $this->hashResetToken($token);
        $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $rows = $this->db->query(
            "SELECT * FROM s_password_reset
             WHERE s_token_hash = :hash
               AND livestatus = '1'
               AND s_used_at IS NULL
               AND s_expires_at >= :now
             LIMIT 1",
            [':hash' => $hash, ':now' => $now]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    private function markResetUsed(int $id): void {
        $this->db->update('s_password_reset', [
            's_used_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'livestatus' => '2',
            'updatedby' => 0
        ], ['id' => $id]);
    }

    private function generateResetPasswordEmailBody(string $fullname, string $email, string $link, string $author): string {
        $safeName = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $safeAuthor = htmlspecialchars($author, ENT_QUOTES, 'UTF-8');
        return '<p>Hello ' . $safeName . ',</p>'
            . '<p>If you have requested to reset the password for your username ' . $safeEmail . ', please click on the link below or copy/paste the link in your browser to proceed.</p>'
            . '<p><a href="' . $safeLink . '">' . $safeLink . '</a></p>'
            . '<p>Regards,<br>' . $safeAuthor . '</p>';
    }

    private function generateEmailBody(string $fullname, string $email, string $newPassword, string $author): string {
        $safeName = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8');
        $safeAuthor = htmlspecialchars($author, ENT_QUOTES, 'UTF-8');
        return '<p>Hello ' . $safeName . ',</p>'
            . '<p>Your password has been reset for the username ' . $safeEmail . '.</p>'
            . '<p>New password: <strong>' . $safePassword . '</strong></p>'
            . '<p>Please change your password after login.</p>'
            . '<p>Regards,<br>' . $safeAuthor . '</p>';
    }

    private function sendEmail(string $to, string $subject, string $body): array {
        if (!class_exists('\\Mailgun\\Mailgun')) {
            return ['ok' => false, 'message' => 'Mailgun library is not available.'];
        }
        $apiKey = $this->runData['config']['app']['Mailgun_API_Key'] ?? '';
        $server = $this->runData['config']['app']['Email_Server'] ?? '';
        $from = $this->runData['config']['app']['Email_From_Address'] ?? '';
        if ($apiKey === '' || $server === '' || $from === '') {
            return ['ok' => false, 'message' => 'Email configuration is incomplete.'];
        }
        $mgClient = \Mailgun\Mailgun::create($apiKey);
        $mgClient->messages()->send($server, [
            'from' => 'Application Administrator <' . $from . '>',
            'to' => $to,
            'subject' => $subject,
            'text' => strip_tags($body),
            'html' => $body,
        ]);
        return ['ok' => true, 'message' => 'sent'];
    }
}
