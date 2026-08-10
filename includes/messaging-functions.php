<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

function find_or_create_conversation($conn, $customerId, $providerId, $serviceId = null)
{
    $existing = db_select_one(
        $conn,
        'SELECT * FROM conversations WHERE customer_id = ? AND provider_id = ?' . ($serviceId ? ' AND (service_id = ? OR service_id IS NULL)' : ''),
        $serviceId ? [$customerId, $providerId, $serviceId] : [$customerId, $providerId]
    );
    if ($existing) {
        if ($serviceId && !$existing['service_id']) {
            db_execute($conn, 'UPDATE conversations SET service_id = ? WHERE id = ?', [$serviceId, (int) $existing['id']]);
        }
        return (int) $existing['id'];
    }
    return db_insert_get_id(
        $conn,
        'INSERT INTO conversations (customer_id, provider_id, service_id, last_message_at) VALUES (?, ?, ?, NOW())',
        [$customerId, $providerId, $serviceId]
    );
}
