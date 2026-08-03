-- The platform's own Official Store: pre-approved, verified, and
-- attached to the 'official' marketplace type. Demo login:
-- store@marketplace.test / admin123 (change before going live).
INSERT INTO vendors (marketplace_type_id, store_name, slug, email, password_hash, status, is_verified, is_featured, approved_at)
SELECT mt.id, 'Official Store', 'official-store', 'store@marketplace.test',
       '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW',
       'approved', 1, 1, NOW()
FROM marketplace_types mt
WHERE mt.slug = 'official'
ON DUPLICATE KEY UPDATE store_name = VALUES(store_name);

INSERT INTO business_profiles (vendor_id, business_info, contact_email)
SELECT v.id, 'The official platform-operated store, verified and curated directly by our team.', v.email
FROM vendors v
WHERE v.slug = 'official-store'
ON DUPLICATE KEY UPDATE business_info = VALUES(business_info);
