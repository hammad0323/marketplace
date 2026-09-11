<?php
/**
 * crm_functions.php — shared helpers for the Sales CRM. Every function
 * here takes an explicit $businessId and scopes its query with it —
 * there is no implicit "current tenant" magic, so it's obvious at each
 * call site that tenant isolation is being enforced.
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

const CRM_PIPELINE_STAGES = [
    'new' => 'New Lead',
    'contacted' => 'Contacted',
    'interested' => 'Interested',
    'quotation_sent' => 'Quotation Sent',
    'negotiation' => 'Negotiation',
    'won' => 'Won',
    'lost' => 'Lost',
];

const CRM_DOC_TYPES = [
    'quotation' => ['label' => 'Quotation', 'prefix' => 'QT'],
    'invoice' => ['label' => 'Invoice', 'prefix' => 'INV'],
    'sales_order' => ['label' => 'Sales Order', 'prefix' => 'SO'],
    'delivery_challan' => ['label' => 'Delivery Challan', 'prefix' => 'DC'],
    'receipt' => ['label' => 'Receipt', 'prefix' => 'RCP'],
    'proforma_invoice' => ['label' => 'Proforma Invoice', 'prefix' => 'PI'],
    'credit_note' => ['label' => 'Credit Note', 'prefix' => 'CN'],
    'debit_note' => ['label' => 'Debit Note', 'prefix' => 'DN'],
];

/** Atomically allocates the next document number for a business+type, e.g. "INV-2026-0007". */
function crm_next_doc_number(int $businessId, string $docType): string
{
    $db = tp_db();
    $db->begin_transaction();
    try {
        $db->query('INSERT INTO crm_doc_counters (business_id, doc_type, last_number) VALUES (' . (int) $businessId . ", '" . $db->real_escape_string($docType) . "', 1)
                     ON DUPLICATE KEY UPDATE last_number = last_number + 1");
        $row = tp_query_one('SELECT last_number FROM crm_doc_counters WHERE business_id = ? AND doc_type = ?', 'is', [$businessId, $docType]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    $number = (int) ($row['last_number'] ?? 1);
    $prefix = CRM_DOC_TYPES[$docType]['prefix'] ?? 'DOC';
    return sprintf('%s-%s-%04d', $prefix, date('Y'), $number);
}

/** Customer's outstanding balance: total sales minus total payments recorded in the ledger. */
function crm_customer_balance(int $businessId, int $customerId): float
{
    $row = tp_query_one(
        "SELECT COALESCE(SUM(CASE WHEN entry_type = 'sale' THEN amount ELSE -amount END), 0) balance
         FROM crm_ledger_entries WHERE business_id = ? AND customer_id = ?",
        'ii',
        [$businessId, $customerId]
    );
    return (float) ($row['balance'] ?? 0);
}

/** Days since a customer's most recent ledger entry (sale or payment) — null if no entries. */
function crm_customer_days_since_activity(int $businessId, int $customerId): ?int
{
    $row = tp_query_one(
        'SELECT MAX(entry_date) last_date FROM crm_ledger_entries WHERE business_id = ? AND customer_id = ?',
        'ii',
        [$businessId, $customerId]
    );
    if (empty($row['last_date'])) {
        return null;
    }
    return (int) floor((time() - strtotime($row['last_date'])) / 86400);
}

/** Great-circle distance in km between two lat/lng points (Haversine) — same formula as the public Distance Calculator. */
function crm_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadiusKm = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** The symbol/prefix the CRM shows on money — defaults to "Rs " (Pakistan-first), overridable via the admin's currency_base setting. */
function crm_currency_symbol(): string
{
    $base = trim((string) tp_setting('currency_base'));
    return $base !== '' ? $base . ' ' : 'Rs ';
}

function crm_pipeline_stage_label(string $status): string
{
    return CRM_PIPELINE_STAGES[$status] ?? ucfirst($status);
}

function crm_doc_type_label(string $docType): string
{
    return CRM_DOC_TYPES[$docType]['label'] ?? ucfirst(str_replace('_', ' ', $docType));
}

/** wa.me link from any reasonably-formatted phone number (strips everything but digits). */
function crm_whatsapp_link(string $phone, string $message = ''): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    $url = 'https://wa.me/' . $digits;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}
