-- ============================================================================
-- DEMO DATA - realistic dummy dataset for evaluation/demo purposes.
-- Run AFTER schema.sql and tool_library.sql. Safe to skip entirely for a
-- clean/empty installation (do not run this file if you want zero business data).
-- Login: manager@goldenharvest.demo / Manager@123
--        priya.rao@goldenharvest.demo (and other employees) / Employee@123
-- ============================================================================

USE `fmcg_qms`;

-- ============================================================================
-- COMPANY 1: Golden Harvest Foods Ltd (rich demo data)
-- ============================================================================

INSERT INTO companies (name, code, industry, address, city, country, contact_person, email, phone, website,
  employee_limit, department_limit, tool_limit, storage_limit_mb, ai_usage_limit, subscription_plan_id,
  start_date, expiry_date, status)
VALUES ('Golden Harvest Foods Ltd', 'GHF-001', 'Dairy & Snacks Manufacturing', '42 Industrial Estate Road', 'Pune', 'India',
  'Rakesh Mehta', 'contact@goldenharvest.demo', '+91-20-55512300', 'https://goldenharvest.demo',
  75, 15, 60, 5000, 1000, (SELECT id FROM subscription_plans WHERE name='Professional'),
  DATE_SUB(CURDATE(), INTERVAL 8 MONTH), DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 'active');
SET @c1 := LAST_INSERT_ID();

INSERT INTO company_settings (company_id, setting_key, setting_value) VALUES
(@c1, 'submission_deadline', '17:00'),
(@c1, 'reminder_time', '15:00'),
(@c1, 'final_reminder_time', '16:30'),
(@c1, 'quality_target_defect_rate', '3'),
(@c1, 'quality_target_fpy', '95');

INSERT INTO departments (company_id, name, description) VALUES
(@c1, 'Production', 'Manufacturing operations'),
(@c1, 'Quality Assurance', 'Quality systems and compliance'),
(@c1, 'Quality Control', 'In-line and finished goods inspection'),
(@c1, 'Food Safety', 'HACCP, allergen and hygiene control'),
(@c1, 'Laboratory', 'Micro and chemical testing'),
(@c1, 'Warehouse', 'Raw material and finished goods storage'),
(@c1, 'Packaging', 'Packaging line operations'),
(@c1, 'Maintenance', 'Equipment reliability and upkeep');

INSERT INTO shifts (company_id, name, start_time, end_time) VALUES
(@c1, 'Morning', '06:00:00', '14:00:00'),
(@c1, 'Evening', '14:00:00', '22:00:00'),
(@c1, 'Night', '22:00:00', '06:00:00');

INSERT INTO production_lines (company_id, name, code) VALUES
(@c1, 'Dairy Line 1', 'DL1'),
(@c1, 'Snacks Line 2', 'SL2'),
(@c1, 'Packaging Line 3', 'PL3');

INSERT INTO machines (company_id, production_line_id, name, code, status) VALUES
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'), 'Pasteurizer PA-1', 'PA-1', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'), 'Filler FL-1', 'FL-1', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'), 'Fryer FR-2', 'FR-2', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='PL3'), 'Checkweigher CW-3', 'CW-3', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='PL3'), 'Metal Detector MD-3', 'MD-3', 'active');

-- Manager
INSERT INTO users (company_id, role, employee_code, name, email, phone, department_id, designation, joining_date, username, password, status)
VALUES (@c1, 'manager', 'GHF-MGR-001', 'Anita Sharma', 'manager@goldenharvest.demo', '+91-98220-11223',
  (SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Assurance'), 'Quality Manager',
  DATE_SUB(CURDATE(), INTERVAL 3 YEAR), 'anita.sharma', '$2y$12$uywqf3hC5mYeEPnKgRF3YuU5Uk7mzQ1S1RCweLNlbbST57JWSs5pS', 'active');
SET @mgr1 := LAST_INSERT_ID();

-- Employees (password for all: Employee@123)
INSERT INTO users (company_id, role, employee_code, name, email, phone, department_id, designation, shift_id, joining_date, username, password, status) VALUES
(@c1,'employee','GHF-EMP-001','Priya Rao','priya.rao@goldenharvest.demo','+91-98220-11001',(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),'QC Inspector',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 2 YEAR),'priya.rao','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-002','Vikram Singh','vikram.singh@goldenharvest.demo','+91-98220-11002',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),'Line Operator',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 18 MONTH),'vikram.singh','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-003','Sunita Patil','sunita.patil@goldenharvest.demo','+91-98220-11003',(SELECT id FROM departments WHERE company_id=@c1 AND name='Food Safety'),'Food Safety Officer',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 4 YEAR),'sunita.patil','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-004','Ramesh Kulkarni','ramesh.kulkarni@goldenharvest.demo','+91-98220-11004',(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),'Packaging Operator',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Evening'),DATE_SUB(CURDATE(), INTERVAL 1 YEAR),'ramesh.kulkarni','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-005','Neha Joshi','neha.joshi@goldenharvest.demo','+91-98220-11005',(SELECT id FROM departments WHERE company_id=@c1 AND name='Laboratory'),'Lab Technician',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 2 YEAR),'neha.joshi','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-006','Arjun Nair','arjun.nair@goldenharvest.demo','+91-98220-11006',(SELECT id FROM departments WHERE company_id=@c1 AND name='Maintenance'),'Maintenance Technician',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),DATE_SUB(CURDATE(), INTERVAL 3 YEAR),'arjun.nair','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-007','Kavita Deshmukh','kavita.deshmukh@goldenharvest.demo','+91-98220-11007',(SELECT id FROM departments WHERE company_id=@c1 AND name='Warehouse'),'Warehouse Supervisor',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 5 YEAR),'kavita.deshmukh','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-008','Suresh Iyer','suresh.iyer@goldenharvest.demo','+91-98220-11008',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),'Line Operator',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),DATE_SUB(CURDATE(), INTERVAL 9 MONTH),'suresh.iyer','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active');

-- Product categories & products
INSERT INTO product_categories (company_id, name) VALUES (@c1,'Dairy'), (@c1,'Snacks'), (@c1,'Beverages');
INSERT INTO products (company_id, name, sku, product_code, category_id, specification, unit, production_line_id, status) VALUES
(@c1,'Fresh Yogurt 500g','SKU-YOG-500','GHF-P001',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Dairy'),'Fat 3.5%, pH 4.2-4.6','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'active'),
(@c1,'Cheese Slices 200g','SKU-CHS-200','GHF-P002',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Dairy'),'Moisture <45%','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'active'),
(@c1,'Potato Chips 150g','SKU-CHP-150','GHF-P003',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Snacks'),'Moisture <2%, Oil <35%','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),'active'),
(@c1,'Mixed Fruit Juice 1L','SKU-JUI-1L','GHF-P004',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Beverages'),'Brix 11-13, pH 3.6-3.9','L',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),'active'),
(@c1,'Salted Butter 100g','SKU-BUT-100','GHF-P005',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Dairy'),'Fat >80%','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'active');

-- Suppliers
INSERT INTO suppliers (company_id, name, code, contact_person, email, phone, material_category, status) VALUES
(@c1,'Deccan Dairy Farms','SUP-001','Mahesh Pawar','sales@deccandairy.demo','+91-98111-22001','Raw Milk','active'),
(@c1,'PackMat Industries','SUP-002','Rina Shah','orders@packmat.demo','+91-98111-22002','Packaging Material','active'),
(@c1,'FlavorTech Ingredients','SUP-003','Ajay Kapoor','sales@flavortech.demo','+91-98111-22003','Flavors & Additives','active'),
(@c1,'GreenLeaf Potato Suppliers','SUP-004','Farida Khan','contact@greenleaf.demo','+91-98111-22004','Raw Potato','active');

INSERT INTO supplier_scorecards (company_id, supplier_id, period, quality_score, delivery_score, cost_score, responsiveness_score, compliance_score, overall_score) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m'),96,94,88,90,97,93.0),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),95,95,89,92,96,93.4),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-002'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),90,85,92,80,88,87.0),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),78,70,75,65,72,72.0),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),88,91,85,86,90,88.0);

INSERT INTO supplier_audits (company_id, supplier_id, audit_date, auditor_id, score, max_score, status, findings) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),DATE_SUB(CURDATE(), INTERVAL 45 DAY),@mgr1,92,100,'completed','Minor documentation gap in CIP records; overall strong hygiene practices.'),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),DATE_SUB(CURDATE(), INTERVAL 20 DAY),@mgr1,68,100,'completed','Allergen segregation inadequate; corrective action requested.');

INSERT INTO incoming_inspections (company_id, supplier_id, material_name, batch_number, quantity, sample_size, accept_qty, reject_qty, result, inspector_id, created_at) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),'Raw Milk','RM-88231',5000,20,20,0,'accepted',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'), DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),'Raw Potato','RP-44120',3000,32,30,2,'conditional',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),'Fruit Flavor Concentrate','FC-77812',200,13,10,3,'rejected',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'), DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO coa_records (company_id, supplier_id, material_name, batch_number, specification, actual_result, result, reviewed_by, created_at) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),'Raw Milk','RM-88231','Fat >=3.2%, SNF >=8.3%','Fat 3.6%, SNF 8.5%','pass',@mgr1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),'Fruit Flavor Concentrate','FC-77812','Microbial count <100 CFU/g','Microbial count 340 CFU/g','fail',@mgr1, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Batches
INSERT INTO batches (company_id, batch_number, product_id, production_date, expiry_date, production_line_id, shift_id, supplier_id, quantity_produced, status, created_at) VALUES
(@c1,'B-YOG-0901',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),DATE_SUB(CURDATE(), INTERVAL 27 DAY),DATE_ADD(CURDATE(), INTERVAL 3 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),12000,'released',DATE_SUB(NOW(), INTERVAL 27 DAY)),
(@c1,'B-YOG-0912',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),DATE_SUB(CURDATE(), INTERVAL 16 DAY),DATE_ADD(CURDATE(), INTERVAL 14 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),11800,'released',DATE_SUB(NOW(), INTERVAL 16 DAY)),
(@c1,'B-CHS-0630',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),DATE_SUB(CURDATE(), INTERVAL 21 DAY),DATE_ADD(CURDATE(), INTERVAL 159 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Evening'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),6000,'released',DATE_SUB(NOW(), INTERVAL 21 DAY)),
(@c1,'B-CHP-1140',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),DATE_SUB(CURDATE(), INTERVAL 12 DAY),DATE_ADD(CURDATE(), INTERVAL 78 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),9000,'released',DATE_SUB(NOW(), INTERVAL 12 DAY)),
(@c1,'B-CHP-1155',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 85 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),9200,'hold',DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@c1,'B-JUI-0788',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),DATE_SUB(CURDATE(), INTERVAL 9 DAY),DATE_ADD(CURDATE(), INTERVAL 171 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Evening'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),7000,'released',DATE_SUB(NOW(), INTERVAL 9 DAY)),
(@c1,'B-BUT-0321',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-BUT-100'),DATE_SUB(CURDATE(), INTERVAL 3 DAY),DATE_ADD(CURDATE(), INTERVAL 87 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),4000,'in_production',DATE_SUB(NOW(), INTERVAL 3 DAY));

-- ============================================================================
-- TOOL ASSIGNMENTS (global tools -> employees)
-- ============================================================================
INSERT INTO tool_assignments (company_id, tool_id, user_id, assigned_by, status, assigned_at)
SELECT @c1, t.id, u.id, @mgr1, 'active', DATE_SUB(NOW(), INTERVAL 60 DAY)
FROM tools t JOIN users u ON u.company_id = @c1
WHERE t.company_id IS NULL AND (
  (t.slug='cold-chain-temperature' AND u.email='priya.rao@goldenharvest.demo') OR
  (t.slug='checkweigher-verification' AND u.email='ramesh.kulkarni@goldenharvest.demo') OR
  (t.slug='metal-detection-xray' AND u.email='ramesh.kulkarni@goldenharvest.demo') OR
  (t.slug='gmp-audit-checklist' AND u.email='sunita.patil@goldenharvest.demo') OR
  (t.slug='sensory-evaluation' AND u.email='neha.joshi@goldenharvest.demo') OR
  (t.slug='water-activity-ph' AND u.email='neha.joshi@goldenharvest.demo') OR
  (t.slug='micro-testing' AND u.email='neha.joshi@goldenharvest.demo') OR
  (t.slug='pareto-defect-log' AND u.email='priya.rao@goldenharvest.demo') OR
  (t.slug='5-whys' AND u.email='priya.rao@goldenharvest.demo') OR
  (t.slug='environmental-monitoring' AND u.email='sunita.patil@goldenharvest.demo') OR
  (t.slug='pest-control-ipm' AND u.email='kavita.deshmukh@goldenharvest.demo') OR
  (t.slug='allergen-verification-swab' AND u.email='sunita.patil@goldenharvest.demo') OR
  (t.slug='5s-audit' AND u.email='vikram.singh@goldenharvest.demo') OR
  (t.slug='oee-shift-entry' AND u.email='vikram.singh@goldenharvest.demo') OR
  (t.slug='scrap-rework-entry' AND u.email='vikram.singh@goldenharvest.demo') OR
  (t.slug='kaizen-idea-log' AND u.email='arjun.nair@goldenharvest.demo') OR
  (t.slug='layered-process-audit' AND u.email='sunita.patil@goldenharvest.demo')
);

-- ============================================================================
-- TOOL SUBMISSIONS (Cold Chain temperature - 6 days incl. 1 deviation)
-- ============================================================================
SET @tool_coldchain := (SELECT id FROM tools WHERE slug='cold-chain-temperature');
SET @field_temp := (SELECT id FROM tool_fields WHERE tool_id=@tool_coldchain AND field_name='temperature');
SET @field_loc := (SELECT id FROM tool_fields WHERE tool_id=@tool_coldchain AND field_name='location');
SET @priya := (SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo');
SET @dept_qc := (SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control');

INSERT INTO tool_submissions (company_id, tool_id, user_id, department_id, status, has_deviation, deviation_severity, submitted_at, created_at) VALUES
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 6 DAY),DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 5 DAY),DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',1,'critical',DATE_SUB(NOW(), INTERVAL 4 DAY),DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 3 DAY),DATE_SUB(NOW(), INTERVAL 3 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'missed',0,NULL,NULL,DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 1 DAY),DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO tool_submission_values (submission_id, tool_field_id, value_number, value_text)
SELECT id, @field_temp, v.temp, v.temp FROM tool_submissions
JOIN (SELECT DATE_SUB(NOW(), INTERVAL 6 DAY) dt, 4.8 temp UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 5 DAY),5.1
      UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 4 DAY),12.4 UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 3 DAY),5.6
      UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 1 DAY),4.9) v ON DATE(created_at)=DATE(v.dt)
WHERE tool_id=@tool_coldchain;
INSERT INTO tool_submission_values (submission_id, tool_field_id, value_text)
SELECT id, @field_loc, 'Cold Storage Room 1' FROM tool_submissions WHERE tool_id=@tool_coldchain;

-- Manually mirror what the Threshold/Issue Engine would auto-create for the 12.4C deviation
INSERT INTO quality_issues (company_id, issue_number, source_type, tool_id, department_id, product_id, severity, status, defect_type, description, ai_recommendation, detected_by, created_at)
VALUES (@c1,'QI-2026-0041','tool_submission',@tool_coldchain,@dept_qc,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),'critical','investigation','Temperature Reading',
 'Automatic deviation detected on Cold Chain Temperature Monitoring: 12.4C recorded in Cold Storage Room 1, exceeding the critical limit of 10C (spec 2-8C).',
 'Temperature is significantly outside the configured specification. Verify refrigeration unit status, door seals and recent access logs; consider relocating affected stock and re-checking calibration.',
 @priya, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- ============================================================================
-- OEE RECORDS (last 14 days, 2 lines) - explicit values, availability/performance/
-- quality/oee computed the same way calc_oee()/calc_availability() etc. would.
-- ============================================================================
SET @line_dl1 := (SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1');
SET @line_sl2 := (SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2');
SET @shift_morning := (SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning');

INSERT INTO oee_records (company_id, production_line_id, shift_id, record_date, planned_minutes, downtime_minutes, ideal_cycle_time, total_count, good_count, availability, performance, quality, oee) VALUES
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 13 DAY),480,35,0.42,940,905,92.71,82.25,96.28,73.44),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 12 DAY),480,40,0.42,915,880,91.67,81.90,96.17,72.24),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 11 DAY),480,28,0.42,960,930,94.17,83.20,96.88,75.87),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 10 DAY),480,50,0.42,890,845,89.58,84.90,94.94,72.19),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 9 DAY),480,32,0.42,955,922,93.33,83.94,96.55,75.63),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 8 DAY),480,45,0.42,905,860,90.63,84.75,95.03,71.94),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 7 DAY),480,38,0.42,930,900,92.08,82.65,96.77,73.61),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 6 DAY),480,30,0.42,965,935,93.75,83.36,96.89,76.24),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 5 DAY),480,55,0.42,875,820,88.54,86.32,93.71,71.60),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 4 DAY),480,42,0.42,920,878,91.25,83.18,95.43,72.44),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 3 DAY),480,33,0.42,948,918,93.13,84.01,96.84,75.72),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 2 DAY),480,29,0.42,972,942,93.96,83.55,96.91,76.13),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 1 DAY),480,36,0.42,935,905,92.50,83.15,96.79,74.44),
(@c1,@line_dl1,@shift_morning,CURDATE(),480,31,0.42,958,930,93.54,83.65,97.08,75.99),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 13 DAY),480,60,0.30,1040,975,87.50,74.29,93.75,60.94),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 11 DAY),480,52,0.30,1085,1010,89.17,76.10,93.09,63.14),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 9 DAY),480,70,0.30,995,915,85.42,72.80,91.96,57.21),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 7 DAY),480,48,0.30,1102,1040,90.00,76.53,94.37,65.00),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 5 DAY),480,65,0.30,1015,935,86.46,73.42,92.12,58.49),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 3 DAY),480,44,0.30,1120,1058,90.83,77.06,94.46,66.09),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 1 DAY),480,58,0.30,1050,975,87.92,74.65,92.86,60.94);

-- ============================================================================
-- SCRAP & REWORK RECORDS
-- ============================================================================
INSERT INTO scrap_records (company_id, product_id, batch_id, department_id, quantity, reason, cost, record_date) VALUES
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1140'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),120,'Process Deviation',3600,DATE_SUB(CURDATE(), INTERVAL 12 DAY)),
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),80,'Material Defect',1600,DATE_SUB(CURDATE(), INTERVAL 16 DAY)),
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHS-0630'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),45,'Machine Fault',900,DATE_SUB(CURDATE(), INTERVAL 21 DAY));

INSERT INTO rework_records (company_id, product_id, batch_id, department_id, quantity, reason, cost, record_date) VALUES
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1155'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),200,'Label Misprint',1200,DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),90,'Seal Defect',750,DATE_SUB(CURDATE(), INTERVAL 9 DAY));

INSERT INTO cost_of_quality (company_id, period, prevention_cost, appraisal_cost, internal_failure_cost, external_failure_cost) VALUES
(@c1, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'), 45000, 62000, 38000, 21000),
(@c1, DATE_FORMAT(CURDATE(),'%Y-%m'), 48000, 60000, 29000, 14000);

-- ============================================================================
-- QUALITY ISSUES (additional manual/varied)
-- ============================================================================
INSERT INTO quality_issues (company_id, issue_number, source_type, department_id, product_id, batch_id, severity, status, defect_type, description, detected_by, assigned_to, created_at, closed_at) VALUES
(@c1,'QI-2026-0032','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1140'),'medium','closed','Seal Defect','Intermittent weak seal observed on chip packets during visual inspection.',@priya,(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 11 DAY),DATE_SUB(NOW(), INTERVAL 8 DAY)),
(@c1,'QI-2026-0035','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),'high','closed','Underweight','Random weight check found 3 units below lower tolerance.',(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),@priya,DATE_SUB(NOW(), INTERVAL 15 DAY),DATE_SUB(NOW(), INTERVAL 10 DAY)),
(@c1,'QI-2026-0038','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Food Safety'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),'high','root_cause','Foreign Material','Trace plastic fragment reported by packaging operator during changeover.',(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 9 DAY),NULL),
(@c1,'QI-2026-0044','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1155'),'medium','containment','Overweight','Checkweigher trend showing consistent overfill on Line 2.',@priya,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 3 DAY),NULL),
(@c1,'QI-2026-0046','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Laboratory'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHS-0630'),'observation','detected','Texture Issue','Slight texture variation noted in routine sensory panel.',(SELECT id FROM users WHERE email='neha.joshi@goldenharvest.demo'),NULL,DATE_SUB(NOW(), INTERVAL 1 DAY),NULL);

-- ============================================================================
-- NCR
-- ============================================================================
INSERT INTO ncr (company_id, ncr_number, product_id, batch_id, department_id, process, severity, description, containment, root_cause, corrective_action, preventive_action, responsible_person, due_date, status, created_at, closed_at) VALUES
(@c1,'NCR-2026-0011',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),'Filling','high','3 units found below minimum fill weight during in-line check.','Segregated affected pallet; 100% weight re-check performed.','Filler valve calibration drift.','Recalibrated filler valve; re-verified with 30-unit sample.','Added filler calibration check to shift start-up checklist.',@priya,DATE_SUB(CURDATE(), INTERVAL 10 DAY),'closed',DATE_SUB(NOW(), INTERVAL 15 DAY),DATE_SUB(NOW(), INTERVAL 10 DAY)),
(@c1,'NCR-2026-0014',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Food Safety'),'Packaging Changeover','high','Foreign material (plastic fragment) reported by operator.','Batch placed on hold pending investigation.','Under investigation - suspected damaged conveyor guide.',NULL,NULL,(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 2 DAY),'root_cause',DATE_SUB(NOW(), INTERVAL 9 DAY),NULL),
(@c1,'NCR-2026-0016',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1155'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),'Checkweighing','medium','Consistent overfill trend beyond target+tolerance on Line 2.','Increased sampling frequency to every 30 minutes.',NULL,NULL,NULL,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 5 DAY),'containment',DATE_SUB(NOW(), INTERVAL 3 DAY),NULL);

-- ============================================================================
-- CAPA (incl. overdue)
-- ============================================================================
INSERT INTO capa (company_id, capa_number, source_type, problem_statement, root_cause, correction, corrective_action, preventive_action, responsible_person, due_date, status, created_at, closed_at) VALUES
(@c1,'CAPA-2026-0009','ncr','Filler valve calibration drift caused underweight units on Dairy Line 1.','Calibration interval too long relative to valve wear characteristics.','Recalibrated filler valve immediately.','Reduced calibration interval from monthly to bi-weekly.','Added automated calibration reminder in maintenance schedule.',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 8 DAY),'closed',DATE_SUB(NOW(), INTERVAL 14 DAY),DATE_SUB(NOW(), INTERVAL 8 DAY)),
(@c1,'CAPA-2026-0012','ncr','Foreign material contamination risk from damaged conveyor guide.',NULL,'Batch quarantined pending root cause confirmation.',NULL,NULL,(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 1 DAY),'in_progress',DATE_SUB(NOW(), INTERVAL 8 DAY),NULL),
(@c1,'CAPA-2026-0013','issue','Recurring overweight trend on Checkweigher CW-3.',NULL,NULL,NULL,NULL,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 2 DAY),'open',DATE_SUB(NOW(), INTERVAL 3 DAY),NULL),
(@c1,'CAPA-2026-0015','audit','Supplier FlavorTech allergen segregation inadequate.','Shared storage area for allergen and non-allergen additives.','Immediate physical segregation implemented.','Dedicated allergen storage cage installed and signed off.','Added quarterly allergen segregation check to supplier audit checklist.',(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 10 DAY),'pending_verification',DATE_SUB(NOW(), INTERVAL 18 DAY),NULL);

INSERT INTO capa_actions (capa_id, company_id, action_text, responsible_user, due_date, status, created_by, created_at) VALUES
((SELECT id FROM capa WHERE company_id=@c1 AND capa_number='CAPA-2026-0012'),@c1,'Inspect and replace damaged conveyor guide on Packaging Line 3.',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 1 DAY),'in_progress',@mgr1,DATE_SUB(NOW(), INTERVAL 7 DAY)),
((SELECT id FROM capa WHERE company_id=@c1 AND capa_number='CAPA-2026-0013'),@c1,'Recalibrate Checkweigher CW-3 and verify with 50-unit sample.',(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 2 DAY),'pending',@mgr1,DATE_SUB(NOW(), INTERVAL 3 DAY));

-- ============================================================================
-- AUDITS
-- ============================================================================
INSERT INTO audits (company_id, audit_type, title, department_id, auditor_id, score, max_score, status, scheduled_date, completed_date, created_at) VALUES
(@c1,'gmp','Monthly GMP Audit - Production Floor',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),@mgr1,88,100,'completed',DATE_SUB(CURDATE(), INTERVAL 20 DAY),DATE_SUB(CURDATE(), INTERVAL 20 DAY),DATE_SUB(NOW(), INTERVAL 20 DAY)),
(@c1,'iso9001','ISO 9001 Internal Audit Q3',(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Assurance'),@mgr1,91,100,'completed',DATE_SUB(CURDATE(), INTERVAL 35 DAY),DATE_SUB(CURDATE(), INTERVAL 35 DAY),DATE_SUB(NOW(), INTERVAL 35 DAY)),
(@c1,'layered_process','Layered Process Audit - Packaging Line 3',(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),76,100,'completed',DATE_SUB(CURDATE(), INTERVAL 6 DAY),DATE_SUB(CURDATE(), INTERVAL 6 DAY),DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,'internal','Internal Quality Audit - Warehouse',(SELECT id FROM departments WHERE company_id=@c1 AND name='Warehouse'),@mgr1,NULL,100,'scheduled',DATE_ADD(CURDATE(), INTERVAL 5 DAY),NULL,DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO audit_findings (audit_id, company_id, finding_text, severity, corrective_action, status, created_at) VALUES
((SELECT id FROM audits WHERE company_id=@c1 AND title='Monthly GMP Audit - Production Floor'),@c1,'Hairnet compliance at 92% - two operators found without proper hairnet coverage.','medium','Retraining conducted; spot-checks increased.','closed',DATE_SUB(NOW(), INTERVAL 20 DAY)),
((SELECT id FROM audits WHERE company_id=@c1 AND title='Layered Process Audit - Packaging Line 3'),@c1,'Changeover cleaning verification record incomplete for 2 of 5 changeovers reviewed.','high','CAPA-2026-0015 raised for allergen segregation and changeover verification.','open',DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ============================================================================
-- FMEA
-- ============================================================================
INSERT INTO fmea (company_id, title, fmea_type, product_id, department_id, created_by, status, created_at)
VALUES (@c1,'Process FMEA - Yogurt Filling Line','process',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),@mgr1,'active',DATE_SUB(NOW(), INTERVAL 40 DAY));
SET @fmea1 := LAST_INSERT_ID();
INSERT INTO fmea_items (fmea_id, process_step, failure_mode, effect, cause, current_controls, severity, occurrence, detection, rpn, action_recommended, responsible_person, target_date, status) VALUES
(@fmea1,'Filling','Underfill','Customer complaint, regulatory non-compliance','Valve calibration drift','Weekly calibration check',8,4,3,96,'Increase calibration frequency to bi-weekly',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 14 DAY),'in_progress'),
(@fmea1,'Sealing','Weak seal','Product leakage, shelf-life reduction','Seal bar temperature variance','Visual seal check per batch',7,5,4,140,'Install continuous seal temperature monitoring',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 30 DAY),'open'),
(@fmea1,'Cold Storage','Temperature excursion','Microbial growth risk','Door left open / refrigeration fault','Twice-daily temperature log',9,3,2,54,'Install continuous temperature data logger with alarm',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 21 DAY),'open');

-- ============================================================================
-- HACCP
-- ============================================================================
INSERT INTO haccp_plans (company_id, product_id, title, team, scope, status, created_at)
VALUES (@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),'HACCP Plan - Fresh Yogurt 500g','A. Sharma, S. Patil, N. Joshi','Raw milk receipt through finished goods dispatch','active',DATE_SUB(NOW(), INTERVAL 90 DAY));
SET @haccp1 := LAST_INSERT_ID();
INSERT INTO haccp_hazards (haccp_plan_id, process_step, hazard_type, hazard_description, severity, likelihood, risk_level, is_ccp, control_measure, critical_limit) VALUES
(@haccp1,'Pasteurization','biological','Survival of pathogenic bacteria',9,3,'critical',1,'Time-temperature controlled pasteurization','85C for 30 min'),
(@haccp1,'Cold Storage','biological','Microbial growth due to temperature abuse',8,4,'high',1,'Continuous refrigeration monitoring','2-8C'),
(@haccp1,'Metal Detection','physical','Metal fragment contamination',7,2,'medium',1,'100% metal detection before packing','Fe 2.0mm / Non-Fe 2.5mm / SS 3.0mm');

INSERT INTO ccp_monitoring (company_id, haccp_hazard_id, ccp_name, critical_limit, actual_reading, unit, reading_time, operator_id, result, corrective_action, batch_id, created_at) VALUES
(@c1,(SELECT id FROM haccp_hazards WHERE haccp_plan_id=@haccp1 AND process_step='Pasteurization'),'Pasteurization Temp/Time','85C / 30min','86.2C / 31min','C/min',DATE_SUB(NOW(), INTERVAL 27 DAY),(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),'pass',NULL,(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0901'),DATE_SUB(NOW(), INTERVAL 27 DAY)),
(@c1,(SELECT id FROM haccp_hazards WHERE haccp_plan_id=@haccp1 AND process_step='Cold Storage'),'Cold Storage Temp','2-8C','12.4C','C',DATE_SUB(NOW(), INTERVAL 4 DAY),@priya,'fail','Product relocated to backup chiller; refrigeration unit inspected and repaired.',(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,(SELECT id FROM haccp_hazards WHERE haccp_plan_id=@haccp1 AND process_step='Metal Detection'),'Metal Detection','Fe 2.0/NFe 2.5/SS 3.0mm','All test pieces detected','mm',DATE_SUB(NOW(), INTERVAL 1 DAY),(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),'pass',NULL,(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO allergen_controls (company_id, allergen, product_id, area, risk_level, cleaning_procedure, verification_result, verified_at) VALUES
(@c1,'Milk',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),'Snacks Line 2 shared equipment','medium','Full CIP wash + allergen swab test','Pass - below detection limit',DATE_SUB(NOW(), INTERVAL 9 DAY)),
(@c1,'Peanut',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),'Snacks Line 2 seasoning station','high','Dry clean + full wash before allergen-free run','Pass',DATE_SUB(NOW(), INTERVAL 12 DAY));

-- ============================================================================
-- CUSTOMER COMPLAINTS
-- ============================================================================
INSERT INTO customer_complaints (company_id, complaint_number, customer_name, product_id, batch_id, complaint_type, severity, description, investigation, root_cause, action_taken, status, nps_score, region, created_at, closed_at) VALUES
(@c1,'CMP-2026-021','Modern Retail Mart',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1140'),'Packaging',	'medium','Packet seal not intact upon delivery.','Reviewed CCTV and seal bar temperature logs for the batch.','Seal bar temperature marginally below target during changeover window.','Adjusted seal bar temperature profile.','closed',7,'West',DATE_SUB(NOW(), INTERVAL 18 DAY),DATE_SUB(NOW(), INTERVAL 12 DAY)),
(@c1,'CMP-2026-024','QuickMart Superstores',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),'Quality',	'high','Sour taste reported by end consumer.','Investigating cold chain logs for the batch.','Suspected correlation with cold storage temperature excursion.',NULL,'investigation',3,'West',DATE_SUB(NOW(), INTERVAL 5 DAY),NULL),
(@c1,'CMP-2026-026','FreshBasket Retail',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),'Foreign Material',	'critical','Consumer reported small plastic fragment in product.','Linked to NCR-2026-0014 investigation.',NULL,NULL,'open',2,'North',DATE_SUB(NOW(), INTERVAL 4 DAY),NULL),
(@c1,'CMP-2026-018','Value Grocers',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHS-0630'),'Packaging',	'low','Label slightly misaligned.','Minor cosmetic issue, no food safety impact.','Label applicator alignment drift.','Applicator realigned and verified.','closed',8,'South',DATE_SUB(NOW(), INTERVAL 25 DAY),DATE_SUB(NOW(), INTERVAL 22 DAY)),
(@c1,'CMP-2026-028','Metro Wholesale',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-BUT-100'),NULL,'Quality',	'low','Texture slightly softer than expected.','Under review.',NULL,NULL,'open',6,'East',DATE_SUB(NOW(), INTERVAL 2 DAY),NULL);

-- ============================================================================
-- SPC / CAPABILITY
-- ============================================================================
INSERT INTO spc_records (company_id, product_id, department_id, chart_type, parameter_name, subgroup_size, usl, lsl, target, created_by, created_at)
VALUES (@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),'xbar_r','Pack Weight (g)',5,158,142,150,@priya,DATE_SUB(NOW(), INTERVAL 20 DAY));
SET @spc1 := LAST_INSERT_ID();
INSERT INTO control_chart_data (spc_record_id, subgroup_no, value1, value2, value3, value4, value5, mean_value, range_value, recorded_at) VALUES
(@spc1,1,149.2,150.1,148.9,150.5,149.8,149.70,1.6,DATE_SUB(NOW(), INTERVAL 20 DAY)),
(@spc1,2,150.3,149.7,151.0,150.2,149.5,150.14,1.5,DATE_SUB(NOW(), INTERVAL 18 DAY)),
(@spc1,3,148.8,149.9,150.4,149.1,150.0,149.64,1.6,DATE_SUB(NOW(), INTERVAL 16 DAY)),
(@spc1,4,151.5,152.0,150.8,151.2,151.9,151.48,1.2,DATE_SUB(NOW(), INTERVAL 14 DAY)),
(@spc1,5,149.5,150.0,149.8,150.3,149.9,149.90,0.8,DATE_SUB(NOW(), INTERVAL 12 DAY)),
(@spc1,6,150.1,149.6,150.9,150.2,149.7,150.10,1.3,DATE_SUB(NOW(), INTERVAL 10 DAY)),
(@spc1,7,156.2,155.8,157.1,156.5,156.0,156.32,1.3,DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@spc1,8,150.0,149.8,150.5,150.1,149.9,150.06,0.7,DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO capability_studies (company_id, spc_record_id, product_id, parameter_name, usl, lsl, mean_value, std_dev, sample_size, cp, cpk, pp, ppk, created_at)
VALUES (@c1,@spc1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),'Pack Weight (g)',158,142,150.4,1.85,40,1.44,1.32,1.30,1.19,DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO gauge_rr (company_id, title, part_count, operator_count, trial_count, repeatability, reproducibility, grr_percent, study_variation_percent, distinct_categories, created_at)
VALUES (@c1,'Checkweigher CW-3 Gauge R&R',10,3,2,0.42,0.18,12.5,18.3,7,DATE_SUB(NOW(), INTERVAL 30 DAY));

-- ============================================================================
-- LEAN / VISUAL MANAGEMENT
-- ============================================================================
INSERT INTO kaizen_events (company_id, title, problem, idea, team, current_state, improvement, result, savings, status, created_by, created_at) VALUES
(@c1,'Reduce Changeover Time on Line 2','Changeover between flavors takes 45 minutes, limiting line utilization.','Pre-stage tooling and use quick-release clamps (SMED principles).','Vikram Singh, Arjun Nair','45 min average changeover','Reduced to 26 min average','42% changeover time reduction, +65 min/day capacity',185000,'completed',@mgr1,DATE_SUB(NOW(), INTERVAL 40 DAY)),
(@c1,'Reduce Cold Storage Door Open Time','Frequent door openings contributing to temperature fluctuation risk.','Install strip curtains and staging area for outgoing pallets.','Priya Rao, Kavita Deshmukh',NULL,NULL,NULL,NULL,'in_progress',@mgr1,DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO five_s_audits (company_id, area, sort_score, set_in_order_score, shine_score, standardize_score, sustain_score, total_score, auditor_id, audit_date, created_at) VALUES
(@c1,'Production Floor - Dairy Line 1',4,4,3,4,3,72.0,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 14 DAY),DATE_SUB(NOW(), INTERVAL 14 DAY)),
(@c1,'Packaging Line 3',3,3,4,3,3,64.0,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 7 DAY),DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO gemba_walks (company_id, area, observer_id, finding, issue_id, action, responsible_person, due_date, status, created_at) VALUES
(@c1,'Packaging Line 3',@mgr1,'Observed inconsistent glove changing frequency between changeovers.',NULL,'Reinforce glove-change SOP at shift briefing',(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 3 DAY),'open',DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO andon_events (company_id, production_line_id, status, event_type, description, raised_by, resolved_by, created_at, resolved_at) VALUES
(@c1,(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),'green','line_stop','Scheduled changeover',(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 2 DAY),DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@c1,(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'red','quality_alert','Cold storage temperature excursion',@priya,@priya,DATE_SUB(NOW(), INTERVAL 4 DAY),DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,(SELECT id FROM production_lines WHERE company_id=@c1 AND code='PL3'),'yellow','maintenance_alert','Checkweigher drift under investigation',(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),NULL,DATE_SUB(NOW(), INTERVAL 3 DAY),NULL);

INSERT INTO preventive_maintenance (company_id, machine_id, maintenance_type, frequency, due_date, responsible_person, status) VALUES
(@c1,(SELECT id FROM machines WHERE company_id=@c1 AND code='PA-1'),'Calibration','Bi-weekly',DATE_ADD(CURDATE(), INTERVAL 4 DAY),(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),'scheduled'),
(@c1,(SELECT id FROM machines WHERE company_id=@c1 AND code='CW-3'),'Calibration','Weekly',DATE_SUB(CURDATE(), INTERVAL 1 DAY),(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),'overdue'),
(@c1,(SELECT id FROM machines WHERE company_id=@c1 AND code='MD-3'),'Sensitivity Test','Daily',CURDATE(),(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),'scheduled');

-- ============================================================================
-- NOTIFICATIONS / AI LOGS / ACTIVITY
-- ============================================================================
INSERT INTO notifications (company_id, user_id, type, title, message, link, severity, is_read, created_at) VALUES
(@c1,@mgr1,'quality_issue','Critical Quality Issue Detected','QI-2026-0041 - Cold Chain Temperature Monitoring: Temperature Reading out of specification.','manager/issue-view',	'danger',0,DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@mgr1,'capa','CAPA Overdue','CAPA-2026-0013 is overdue.','manager/capa-view',	'warning',0,DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@c1,@mgr1,'complaint','New Critical Complaint','CMP-2026-026 - Foreign material reported.','manager/complaints',	'danger',0,DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@priya,'action_assigned','Issue Assigned To You','QI-2026-0044 has been assigned to you.','employee/my-issues',	'info',1,DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO ai_logs (company_id, user_id, feature, prompt, response, tokens_used, created_at) VALUES
(@c1,@priya,'form_assistance','Cold Chain Temperature Monitoring reading 12.4C','Temperature is significantly outside the configured specification (2-8C). Verify refrigeration unit status and door seals before continuing production.',0,DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@mgr1,'chat','Which department has the highest defect rate?','Production has the highest number of recorded issues this period, driven mainly by weight-related deviations on the filling line.',0,DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO activity_logs (company_id, user_id, action, module, record_id, description, ip_address, created_at) VALUES
(@c1,@mgr1,'login','admin',NULL,'Manager logged in','203.0.113.24',DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@c1,@priya,'create','quality_issue',NULL,'Auto-created issue QI-2026-0041 (severity: critical)','203.0.113.55',DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@mgr1,'create','capa',NULL,'Created CAPA-2026-0015','203.0.113.24',DATE_SUB(NOW(), INTERVAL 18 DAY));

-- ============================================================================
-- COMPANY 2: Everfresh Beverages Inc (lightweight, proves multi-tenant isolation)
-- ============================================================================
INSERT INTO companies (name, code, industry, address, city, country, contact_person, email, phone,
  employee_limit, department_limit, tool_limit, storage_limit_mb, ai_usage_limit, subscription_plan_id,
  start_date, expiry_date, status)
VALUES ('Everfresh Beverages Inc', 'EFB-002', 'Beverage Manufacturing', '18 Riverside Business Park', 'Austin', 'USA',
  'Laura Bennett', 'contact@everfresh.demo', '+1-512-555-0142',
  15, 5, 15, 500, 100, (SELECT id FROM subscription_plans WHERE name='Starter'),
  DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 'active');
SET @c2 := LAST_INSERT_ID();

INSERT INTO departments (company_id, name) VALUES (@c2,'Production'), (@c2,'Quality Assurance'), (@c2,'Warehouse');
INSERT INTO shifts (company_id, name, start_time, end_time) VALUES (@c2,'Day','08:00:00','16:00:00');
INSERT INTO production_lines (company_id, name, code) VALUES (@c2,'Bottling Line 1','BL1');

INSERT INTO users (company_id, role, employee_code, name, email, department_id, designation, joining_date, username, password, status)
VALUES (@c2,'manager','EFB-MGR-001','Laura Bennett','manager@everfresh.demo',(SELECT id FROM departments WHERE company_id=@c2 AND name='Quality Assurance'),'Quality Manager',DATE_SUB(CURDATE(), INTERVAL 1 YEAR),'laura.bennett','$2y$12$uywqf3hC5mYeEPnKgRF3YuU5Uk7mzQ1S1RCweLNlbbST57JWSs5pS','active');
SET @mgr2 := LAST_INSERT_ID();

INSERT INTO users (company_id, role, employee_code, name, email, department_id, designation, shift_id, joining_date, username, password, status) VALUES
(@c2,'employee','EFB-EMP-001','Carlos Mendez','carlos.mendez@everfresh.demo',(SELECT id FROM departments WHERE company_id=@c2 AND name='Production'),'Line Operator',(SELECT id FROM shifts WHERE company_id=@c2 AND name='Day'),DATE_SUB(CURDATE(), INTERVAL 8 MONTH),'carlos.mendez','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c2,'employee','EFB-EMP-002','Emily Zhao','emily.zhao@everfresh.demo',(SELECT id FROM departments WHERE company_id=@c2 AND name='Quality Assurance'),'QA Analyst',(SELECT id FROM shifts WHERE company_id=@c2 AND name='Day'),DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'emily.zhao','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active');

INSERT INTO products (company_id, name, sku, product_code, unit, production_line_id, status) VALUES
(@c2,'Sparkling Lemon Water 500ml','SKU-SLW-500','EFB-P001','ml',(SELECT id FROM production_lines WHERE company_id=@c2 AND code='BL1'),'active');

INSERT INTO batches (company_id, batch_number, product_id, production_date, expiry_date, production_line_id, quantity_produced, status) VALUES
(@c2,'B-SLW-2201',(SELECT id FROM products WHERE company_id=@c2 AND sku='SKU-SLW-500'),DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 175 DAY),(SELECT id FROM production_lines WHERE company_id=@c2 AND code='BL1'),8000,'released');

INSERT INTO suppliers (company_id, name, code, material_category, status) VALUES (@c2,'Texas Spring Water Co','SUP-201','Water Source','active');

INSERT INTO quality_issues (company_id, issue_number, source_type, department_id, product_id, severity, status, defect_type, description, detected_by, created_at) VALUES
(@c2,'QI-2026-0002','manual',(SELECT id FROM departments WHERE company_id=@c2 AND name='Quality Assurance'),(SELECT id FROM products WHERE company_id=@c2 AND sku='SKU-SLW-500'),'low','closed','Carbonation','Slightly low carbonation on one sample.',@mgr2,DATE_SUB(NOW(), INTERVAL 10 DAY));

INSERT INTO notifications (company_id, user_id, type, title, message, severity, is_read, created_at) VALUES
(@c2,@mgr2,'quality_issue','Quality Issue Logged','QI-2026-0002 recorded.','info',1,DATE_SUB(NOW(), INTERVAL 10 DAY));
