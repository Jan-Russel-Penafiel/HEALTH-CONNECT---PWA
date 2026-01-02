<?php
require_once 'includes/config/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "=== Recent SMS Logs ===\n";
$stmt = $pdo->query('SELECT sms_id, recipient_number, SUBSTRING(message, 1, 80) as msg_preview, status, sent_at FROM sms_logs ORDER BY sms_id DESC LIMIT 10');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['sms_id']}\n";
    echo "To: {$row['recipient_number']}\n";
    echo "Msg: {$row['msg_preview']}...\n";
    echo "Status: {$row['status']}\n";
    echo "Sent: {$row['sent_at']}\n";
    echo "---\n";
}
