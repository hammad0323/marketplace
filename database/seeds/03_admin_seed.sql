-- Default admin login: admin@marketplace.test / admin123
-- CHANGE THIS PASSWORD before deploying to any shared environment.
INSERT INTO admin_users (name, email, password_hash) VALUES
    ('Platform Admin', 'admin@marketplace.test', '$2y$12$Mx0ULFFE4UcnVa5zvui2UOiC1/R26pQYrlnfPUhcE.lWLpNVOFZZW')
ON DUPLICATE KEY UPDATE name = VALUES(name);
