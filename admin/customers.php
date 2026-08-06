<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();

$customers = mp_all_customers_admin();

$pageTitle = 'Customers';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Customers</h1>
<p style="color:var(--ink-500); margin-top:-.5rem;"><?= count($customers) ?> registered customer(s).</p>

<div class="admin-panel">
    <?php if (!$customers): ?>
        <p>No customers yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Verified</th><th>Orders</th><th>Total Spent</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= mp_e($customer['name']) ?></td>
                    <td><?= mp_e($customer['email']) ?></td>
                    <td><?= mp_e($customer['phone'] ?? '—') ?></td>
                    <td><?= $customer['email_verified_at'] ? '✔' : '—' ?></td>
                    <td><?= (int) $customer['order_count'] ?></td>
                    <td><?= mp_currency((float) $customer['total_spent']) ?></td>
                    <td><?= mp_e(date('M j, Y', strtotime($customer['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
