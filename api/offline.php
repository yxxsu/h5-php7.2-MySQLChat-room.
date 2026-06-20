<?php
define('IN_CHAT', true);
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM online_users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Offline error: " . $e->getMessage());
    }
}
