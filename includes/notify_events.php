<?php
// includes/notify_events.php
// Call these functions at key events throughout the system.
// Requires: $conn (mysqli), mailer.php already included

require_once __DIR__ . '/mailer.php';

function ms_notification_table_exists(mysqli $conn): bool {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $res = $conn->query("SHOW TABLES LIKE 'notification'");
    $exists = ($res && $res->num_rows > 0);
    return $exists;
}

/**
 * Log a notification to the DB and optionally send email.
 */
function ms_notify(mysqli $conn, string $type, string $title, string $message, ?string $toEmail = null): void {
    $sent = 0;
    if ($toEmail && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $html = ms_email_template($title, "<p style='color:#334155;'>{$message}</p>");
        $sent = ms_send_email($toEmail, $title, $html) ? 1 : 0;
    }
    if (!ms_notification_table_exists($conn)) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO notification (type, title, message, toEmail, emailSent, sentAt) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssi", $type, $title, $message, $toEmail, $sent);
    $stmt->execute();
    $stmt->close();
}

/**
 * Trigger: Repair job status changed to Finished
 */
function notify_repair_finished(mysqli $conn, array $job): void {
    $title   = "Repair Job Completed — Bike {$job['bikeNo']}";
    $message = "Your bike (No: {$job['bikeNo']}) has been repaired and is ready for collection. Issue: {$job['problemDescription']}";
    $email = null;
    if (!empty($job['bikeNo'])) {
        $hasCustomerEmailCol = false;
        $chkCustomerEmail = $conn->query("SHOW COLUMNS FROM customer LIKE 'email'");
        if ($chkCustomerEmail && $chkCustomerEmail->num_rows > 0) {
            $hasCustomerEmailCol = true;
        }
        if ($hasCustomerEmailCol) {
            $r = $conn->query("SELECT email FROM customer WHERE vehicleNo = '" . $conn->real_escape_string($job['bikeNo']) . "' LIMIT 1");
            if ($r && $r->num_rows > 0) {
                $email = $r->fetch_assoc()['email'];
            }
        }
    }
    ms_notify($conn, 'repair_finished', $title, $message, $email);
}

/**
 * Trigger: Part stock falls below minimum — sends summary email to owner
 */
function notify_low_stock_summary(mysqli $conn, array $parts): void {
    $ownerEmail = defined('OWNER_EMAIL') ? OWNER_EMAIL : null;
    $count  = count($parts);
    $title  = "Low Stock Summary — {$count} part(s) need reordering";

    // Build plain message for DB log
    $partNames = implode(', ', array_column($parts, 'partName'));
    $message   = "The following parts are below minimum stock: {$partNames}";

    $sent = 0;
    if ($ownerEmail && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        // Build HTML table
        $rows = '';
        foreach ($parts as $p) {
            $rows .= "<tr>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($p['partName']) . "</td>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#dc2626;font-weight:600;'>" . (int)$p['currentQuantity'] . "</td>
                <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . (int)$p['minQuantity'] . "</td>
            </tr>";
        }
        $body = "
            <p style='color:#334155;'>The following parts are currently below their minimum stock levels and require reordering:</p>
            <table style='width:100%;border-collapse:collapse;margin:12px 0;font-size:.9rem;'>
                <thead>
                    <tr style='background:#f1f5f9;'>
                        <th style='padding:8px 12px;text-align:left;border-bottom:2px solid #e2e8f0;'>Part Name</th>
                        <th style='padding:8px 12px;text-align:left;border-bottom:2px solid #e2e8f0;'>Current Qty</th>
                        <th style='padding:8px 12px;text-align:left;border-bottom:2px solid #e2e8f0;'>Min Qty</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            <p style='color:#334155;'>Please reorder these parts as soon as possible.</p>
        ";
        $html = ms_email_template($title, $body);
        $sent = ms_send_email($ownerEmail, $title, $html) ? 1 : 0;
    }

    if (ms_notification_table_exists($conn)) {
        $type = 'low_stock';
        $stmt = $conn->prepare("INSERT INTO notification (type, title, message, toEmail, emailSent, sentAt) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssi", $type, $title, $message, $ownerEmail, $sent);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Trigger: New sale completed
 */
function notify_sale_completed(mysqli $conn, int $saleID, string $customerName, float $total): void {
    $title   = "Sale Completed — Invoice #{$saleID}";
    $message = "A sale of Rs. " . number_format($total, 2) . " was completed for customer: {$customerName}.";
    ms_notify($conn, 'sale', $title, $message, null);
}

/**
 * Trigger: Service due reminder for a single customer
 */
function notify_service_due(mysqli $conn, array $customer): void {
    $title   = "Service Reminder — {$customer['name']}";
    $dueDate = date('d M Y', strtotime($customer['nextServiceDue']));
    $message = "Dear {$customer['name']}, your vehicle (No: {$customer['vehicleNo']}) was due for service on {$dueDate}. Please visit us at your earliest convenience.";
    ms_notify($conn, 'service_due', $title, $message, $customer['email'] ?? null);
}
