<?php
// ============================================================
// NPATS — Database Configuration & Helpers
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'npats');
define('APP_NAME', 'NPATS');
define('APP_URL',  'http://localhost/npats');
define('UPLOAD_DIR', __DIR__ . '/../assets/photos/');
define('UPLOAD_URL', APP_URL . '/assets/photos/');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:2rem;color:#b92c2c;max-width:600px;margin:2rem auto;border:1px solid #f0b2b2;border-radius:10px;background:#fbeaea;">
                    <h2 style="margin-bottom:.5rem;">&#9888; Database Connection Failed</h2>
                    <p style="margin-bottom:.5rem;">'.htmlspecialchars($e->getMessage()).'</p>
                    <p style="font-size:.85rem;color:#666;">Check <code>includes/config.php</code> credentials and ensure MySQL is running in XAMPP.</p>
                 </div>');
        }
    }
    return $pdo;
}

function isLoggedIn(): bool   { return isset($_SESSION['user_id']); }

function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: '.APP_URL.'/login.php'); exit; }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        header('Location: '.APP_URL.'/unauthorized.php'); exit;
    }
}

function currentUser(): array {
    return ['id'=>$_SESSION['user_id']??null,'name'=>$_SESSION['user_name']??'',
            'role'=>$_SESSION['user_role']??'','email'=>$_SESSION['user_email']??''];
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

function statusBadge(string $status): string {
    $map = [
        'Pending'     => 'badge-pending',
        'In-Progress' => 'badge-in-progress',
        'Completed'   => 'badge-completed',
        'Rejected'    => 'badge-rejected',
    ];
    $cls = $map[$status] ?? 'badge-pending';
    return '<span class="badge '.$cls.'">'.e($status).'</span>';
}

function generateApplicationNumber(): string {
    return 'NPATS-'.date('Y').'-'.strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

function logActivity(int|null $userId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $db->prepare('INSERT INTO activity_log (user_id,action,details,ip_address) VALUES (?,?,?,?)')->execute([$userId,$action,$details,$ip]);
    } catch (Exception) {}
}

function addNotification(int|null $userId, int|null $appId, string $message): void {
    if (!$userId) return;
    try {
        getDB()->prepare('INSERT INTO notifications (user_id,application_id,message) VALUES (?,?,?)')->execute([$userId,$appId,$message]);
    } catch (Exception) {}
}

function unreadCount(): int {
    if (!isLoggedIn()) return 0;
    try {
        $s = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $s->execute([$_SESSION['user_id']]);
        return (int)$s->fetchColumn();
    } catch (Exception) { return 0; }
}

function redirect(string $url): never { header('Location: '.$url); exit; }

function flash(string $key, string $msg): void { $_SESSION['flash'][$key] = $msg; }

function getFlash(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $ini = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $ini .= strtoupper(substr(end($parts), 0, 1));
    return $ini;
}

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
