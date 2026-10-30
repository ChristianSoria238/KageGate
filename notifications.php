<?php
require_once __DIR__ . '/config.php';
$user = require_user();
$userId = (int)$user['id'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'read-all') {
        $update = db()->prepare('UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND read_at IS NULL');
        $update->execute([$userId]);
    } elseif ($action === 'read-one') {
        $update = db()->prepare('UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND read_at IS NULL');
        $update->execute([(int)($_POST['id'] ?? 0), $userId]);
        if ($update->rowCount() !== 1) $error = 'That alert is no longer available.';
    } else {
        http_response_code(400);
        exit('Invalid notification action.');
    }
}
$query = db()->prepare('SELECT id, title, message, created_at, read_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100');
$query->execute([$userId]);
$notifications = $query->fetchAll();
page('Notifications');
?>
<section class="content-page notifications-page">
    <div class="section-title">
        <div><span class="kicker">ACCOUNT ALERTS</span><h1>Notifications</h1></div>
        <?php if (unread_notification_count($userId) > 0): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(token()) ?>">
                <input type="hidden" name="action" value="read-all">
                <button type="submit">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="page-lead">Sign-in and account-security activity for your KageGate account.</p>
    <?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <?php if (!$notifications): ?>
        <div class="content-panel"><h2>You're all caught up.</h2><p>New sign-ins and security changes will appear here.</p></div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <article class="notification-item <?= $notification['read_at'] === null ? 'unread' : '' ?>">
                    <div><h2><?= h($notification['title']) ?></h2><p><?= h($notification['message']) ?></p><time datetime="<?= h(date(DATE_ATOM, strtotime((string)$notification['created_at']))) ?>"><?= h(date('M j, Y · g:i A', strtotime((string)$notification['created_at']))) ?></time></div>
                    <?php if ($notification['read_at'] === null): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= h(token()) ?>">
                            <input type="hidden" name="action" value="read-one">
                            <input type="hidden" name="id" value="<?= (int)$notification['id'] ?>">
                            <button type="submit">Mark read</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endpage(); ?>
