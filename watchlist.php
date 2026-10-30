<?php
require_once __DIR__ . '/config.php';
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') go('account.php');
csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id !== false && $id !== null) {
    $query = db()->prepare('DELETE FROM watchlist WHERE user_id = ? AND title_id = ?');
    $query->execute([(int)$user['id'], $id]);
    if ($query->rowCount() === 1) {
        create_notification((int)$user['id'], 'Removed from your watchlist', 'A title was removed from your watchlist.');
    }
}
go('account.php');
