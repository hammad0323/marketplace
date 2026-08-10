<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

function recalculate_service_rating($conn, $serviceId)
{
    $row = db_select_one($conn, 'SELECT COUNT(*) AS c, COALESCE(AVG(rating),0) AS a FROM reviews WHERE service_id = ? AND status = "approved"', [(int) $serviceId]);
    db_execute($conn, 'UPDATE services SET review_count = ?, avg_rating = ? WHERE id = ?', [(int) $row['c'], round((float) $row['a'], 2), (int) $serviceId]);

    $providerId = db_select_one($conn, 'SELECT provider_id FROM services WHERE id = ?', [(int) $serviceId])['provider_id'] ?? null;
    if ($providerId) {
        recalculate_provider_rating($conn, $providerId);
    }
}

function recalculate_provider_rating($conn, $providerId)
{
    $row = db_select_one(
        $conn,
        'SELECT COUNT(*) AS c, COALESCE(AVG(r.rating),0) AS a FROM reviews r JOIN services s ON s.id = r.service_id WHERE s.provider_id = ? AND r.status = "approved"',
        [(int) $providerId]
    );
    db_execute($conn, 'UPDATE providers SET review_count = ?, avg_rating = ? WHERE id = ?', [(int) $row['c'], round((float) $row['a'], 2), (int) $providerId]);
}
