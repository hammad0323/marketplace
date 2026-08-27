-- ============================================================================
-- Dynamic Quality Tool Library - initial platform tool set.
-- These are GLOBAL tools (company_id IS NULL) built entirely through the
-- Dynamic Tool Engine (tools + tool_fields + tool_field_options) - no
-- dedicated one-off pages required. Run after schema.sql (before or without
-- seed_demo.sql - this is reference/config data, safe for empty installs too).
--
-- NOTE: MySQL's LAST_INSERT_ID() after a multi-row INSERT returns the id
-- generated for the FIRST row of that insert. Field option blocks below
-- reference "LAST_INSERT_ID() + offset" where offset = (row position - 1)
-- of the target field within its preceding tool_fields INSERT.
-- ============================================================================

USE `fmcg_qms`;

-- 1. 5 WHYS -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), '5 Whys', '5-whys',
 'Interactive five-level root cause analysis.', 'bi-question-circle', 'State the problem, then ask "why" up to five times to reach the root cause.',
 'on_demand', 'form', 'active', 'Suggest plausible contributing factors for each "why" level without declaring an unverified root cause as fact.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Problem Statement','problem_statement','long_text',1,1),
(@t,'Why 1','why_1','long_text',1,2),
(@t,'Why 2','why_2','long_text',0,3),
(@t,'Why 3','why_3','long_text',0,4),
(@t,'Why 4','why_4','long_text',0,5),
(@t,'Why 5 (Root Cause)','why_5','long_text',0,6);

-- 2. FISHBONE / ISHIKAWA --------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), 'Fishbone / Ishikawa Diagram', 'fishbone-ishikawa',
 'Interactive Cause & Effect diagram across Man, Machine, Method, Material, Measurement, Environment.', 'bi-diagram-2',
 'List potential causes under each category contributing to the effect (problem).', 'on_demand', 'form', 'active',
 'Suggest additional candidate causes per category based on similar historical issues.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Effect / Problem','effect','text',1,1),
(@t,'Man','man','long_text',0,2),
(@t,'Machine','machine','long_text',0,3),
(@t,'Method','method','long_text',0,4),
(@t,'Material','material','long_text',0,5),
(@t,'Measurement','measurement_cause','long_text',0,6),
(@t,'Environment','environment','long_text',0,7);

-- 3. PARETO DEFECT LOG ------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='basic-7-qc'), 'Pareto Defect Log', 'pareto-defect-log',
 'Log defect occurrences to build 80/20 Pareto analysis on the Reports page.', 'bi-bar-chart-steps',
 'Select the defect type observed and quantity for this record.', 'per_batch', 'form', 'active', NULL);
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Defect Type','defect_type','dropdown',1,1),
(@t,'Quantity','quantity','number',1,2),
(@t,'Notes','notes','text',0,3);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Underweight','underweight',1),(@f,'Overweight','overweight',2),(@f,'Seal Defect','seal_defect',3),
(@f,'Label Misprint','label_misprint',4),(@f,'Foreign Material','foreign_material',5),
(@f,'Contamination','contamination',6),(@f,'Packaging Damage','packaging_damage',7),
(@f,'Color Variation','color_variation',8),(@f,'Texture Issue','texture_issue',9),(@f,'Other','other',10);

-- 4. IS / IS NOT ANALYSIS ----------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), 'Is / Is Not Analysis', 'is-is-not-analysis',
 'Compare What/Where/When/Extent is vs is not to scope a problem.', 'bi-columns-gap', 'Complete both columns for each dimension.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'What Is','what_is','long_text',0,1),(@t,'What Is Not','what_is_not','long_text',0,2),
(@t,'Where','where_is','long_text',0,3),(@t,'Where Not','where_is_not','long_text',0,4),
(@t,'When','when_is','long_text',0,5),(@t,'When Not','when_is_not','long_text',0,6),
(@t,'Extent','extent_is','long_text',0,7),(@t,'Extent Not','extent_is_not','long_text',0,8);

-- 5. 8D PROBLEM SOLVING -------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), '8D Problem Solving', '8d-problem-solving',
 'Eight Disciplines structured problem solving.', 'bi-8-circle', 'Work through D1-D8 sequentially.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'D1 - Team','d1_team','text',1,1),(@t,'D2 - Problem Description','d2_problem','long_text',1,2),
(@t,'D3 - Containment','d3_containment','long_text',0,3),(@t,'D4 - Root Cause','d4_root_cause','long_text',0,4),
(@t,'D5 - Corrective Actions','d5_corrective','long_text',0,5),(@t,'D6 - Implementation','d6_implementation','long_text',0,6),
(@t,'D7 - Prevent Recurrence','d7_prevent','long_text',0,7),(@t,'D8 - Recognition / Closure','d8_closure','long_text',0,8);

-- 6. A3 PROBLEM SOLVING --------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'A3 Problem Solving', 'a3-problem-solving',
 'Single-page Lean storyboard for structured problem solving.', 'bi-file-earmark-text', 'Complete each A3 section concisely.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Background','background','long_text',1,1),(@t,'Current Condition','current_condition','long_text',0,2),
(@t,'Goal','goal','long_text',0,3),(@t,'Root Cause','root_cause','long_text',0,4),
(@t,'Countermeasures','countermeasures','long_text',0,5),(@t,'Implementation Plan','implementation','long_text',0,6),
(@t,'Follow-up','follow_up','long_text',0,7),(@t,'Results','results','long_text',0,8);

-- 7. TEMPERATURE / COLD CHAIN MONITORING ---------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Cold Chain Temperature Monitoring', 'cold-chain-temperature',
 'Record temperature readings for cold storage / cold chain locations.', 'bi-thermometer-snow', 'Record the reading exactly as shown on the calibrated device.',
 'per_shift', 'form', 'active', 'If a reading is outside 2-8C, suggest checking door seals, refrigeration unit status and recent door-open events.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, warning_threshold_low, warning_threshold_high, critical_threshold_low, critical_threshold_high, sort_order) VALUES
(@t,'Location','location','text',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1),
(@t,'Equipment','equipment','text',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2),
(@t,'Temperature Reading','temperature','measurement',1,'C',2,8,3,7,0,10,3);

-- 8. METAL DETECTION / X-RAY -----------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Metal Detection / X-Ray Check', 'metal-detection-xray',
 'Verify metal detector / x-ray performance using test pieces.', 'bi-magnet', 'Pass all three test standards (Fe, Non-Fe, SS) then run reject test.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Test Standard','test_standard','dropdown',1,1),
(@t,'Detection Result','detection_result','pass_fail',1,2),
(@t,'Reject Test Result','reject_test','pass_fail',1,3);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Ferrous 2.0mm','fe_2mm',1),(@f,'Non-Ferrous 2.5mm','nfe_2_5mm',2),(@f,'Stainless Steel 3.0mm','ss_3mm',3);

-- 9. CHECKWEIGHER -----------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Checkweigher Verification', 'checkweigher-verification',
 'Verify pack weight against target and tolerance.', 'bi-speedometer2', 'Weigh a sample pack and record against the configured target.', 'hourly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Target Weight','target_weight','number',1,'g',1),
(@t,'Actual Weight','actual_weight','measurement',1,'g',2),
(@t,'Tolerance (+/-)','tolerance','number',0,'g',3),
(@t,'Pass / Fail','pass_fail_result','pass_fail',1,NULL,4);

-- 10. ENVIRONMENTAL MONITORING PROGRAM ---------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Environmental Monitoring (EMP)', 'environmental-monitoring',
 'Environmental swab/air sampling program for pathogen and hygiene indicator control.', 'bi-droplet-half', 'Record sample location, organism tested and result against limit.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Area','area','text',1,1),(@t,'Sample Location','sample_location','text',1,2),
(@t,'Sample Type','sample_type','dropdown',1,3),(@t,'Organism Tested','organism','text',1,4),
(@t,'Result (CFU)','result_value','number',1,5),(@t,'Limit (CFU)','limit_value','number',0,6),
(@t,'Status','status_result','pass_fail',1,7);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Zone 1 - Product Contact','zone1',1),(@f,'Zone 2 - Near Product','zone2',2),
(@f,'Zone 3 - Environment','zone3',3),(@f,'Zone 4 - Remote','zone4',4);

-- 11. PEST CONTROL / IPM ------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Pest Control / IPM Inspection', 'pest-control-ipm',
 'Integrated Pest Management station inspection.', 'bi-bug', 'Inspect each station and record activity level and action taken.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Station / Location','location','text',1,1),(@t,'Pest Activity','pest_activity','dropdown',1,2),
(@t,'Action Taken','action_taken','long_text',0,3),(@t,'Chemical / Method Used','method_used','text',0,4),
(@t,'Status','status_result','pass_fail',1,5);
SET @f := LAST_INSERT_ID() + 1;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'None','none',1),(@f,'Low','low',2),(@f,'Medium','medium',3),(@f,'High','high',4);

-- 12. SENSORY EVALUATION -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Sensory Evaluation', 'sensory-evaluation',
 'Panel-based sensory evaluation of finished product.', 'bi-emoji-smile', 'Rate each attribute from 1 (poor) to 5 (excellent).', 'per_batch', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Taste','taste','rating',1,1,5,1),(@t,'Smell','smell','rating',1,1,5,2),
(@t,'Texture','texture','rating',1,1,5,3),(@t,'Appearance','appearance','rating',1,1,5,4),
(@t,'Overall Score','overall_score','rating',1,1,5,5);

-- 13. WATER ACTIVITY / pH TESTING ------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Water Activity / pH Testing', 'water-activity-ph',
 'Record pH and Aw readings against product specification.', 'bi-eyedropper', 'Calibrate meter before testing.', 'per_batch', 'form', 'active',
 'If pH is outside 6.5-7.5, suggest verifying calibration and checking for formulation or process drift.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, warning_threshold_low, warning_threshold_high, critical_threshold_low, critical_threshold_high, sort_order) VALUES
(@t,'pH','ph_value','decimal',1,'pH',6.5,7.5,6.6,7.3,6.4,7.6,1),
(@t,'Water Activity (Aw)','aw_value','decimal',0,'Aw',0.60,0.85,NULL,NULL,NULL,NULL,2);

-- 14. MICRO TESTING -----------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Micro Testing', 'micro-testing',
 'Microbiological testing of samples against limits.', 'bi-virus', 'Record organism tested and result vs specification limit.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Sample','sample_name','text',1,1),(@t,'Organism','organism','text',1,2),
(@t,'Result (CFU/g)','result_value','number',1,3),(@t,'Limit (CFU/g)','limit_value','number',0,4),
(@t,'Status','status_result','pass_fail',1,5);

-- 15. GMP AUDIT CHECKLIST ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'GMP Audit Checklist', 'gmp-audit-checklist',
 'Checklist-based Good Manufacturing Practice inspection.', 'bi-clipboard2-check', 'Mark each category Pass/Fail and add notes for any failures.', 'weekly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Personnel Hygiene','personnel_hygiene','pass_fail',1,1),(@t,'Facility','facility','pass_fail',1,2),
(@t,'Equipment','equipment_gmp','pass_fail',1,3),(@t,'Storage','storage','pass_fail',1,4),
(@t,'Production','production','pass_fail',1,5),(@t,'Documentation','documentation','pass_fail',1,6),
(@t,'Notes','notes','long_text',0,7);

-- 16. 5S AUDIT -----------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), '5S Audit', '5s-audit',
 'Workplace organization audit: Sort, Set in Order, Shine, Standardize, Sustain.', 'bi-grid-3x3-gap', 'Score each pillar 1 (poor) to 5 (excellent).', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Sort','sort_score','rating',1,1,5,1),(@t,'Set in Order','set_order_score','rating',1,1,5,2),
(@t,'Shine','shine_score','rating',1,1,5,3),(@t,'Standardize','standardize_score','rating',1,1,5,4),
(@t,'Sustain','sustain_score','rating',1,1,5,5);

-- 17. KAIZEN IDEA LOG ---------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Kaizen Idea Log', 'kaizen-idea-log',
 'Capture continuous improvement ideas from the shop floor.', 'bi-lightbulb', 'Describe the problem, your idea and the expected improvement.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Problem','problem','long_text',1,1),(@t,'Idea','idea','long_text',1,2),
(@t,'Expected Result','expected_result','long_text',0,3);

-- 18. SCRAP / REWORK ENTRY ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Scrap & Rework Entry', 'scrap-rework-entry',
 'Log scrap and rework quantities with reason and cost.', 'bi-recycle', 'Record quantity, reason and estimated cost impact.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Quantity Scrapped','quantity_scrapped','number',0,1),(@t,'Quantity Reworked','quantity_reworked','number',0,2),
(@t,'Reason','reason','dropdown',1,3),(@t,'Estimated Cost','cost','number',0,4);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Process Deviation','process_deviation',1),(@f,'Material Defect','material_defect',2),
(@f,'Machine Fault','machine_fault',3),(@f,'Operator Error','operator_error',4),(@f,'Other','other',5);

-- 19. SUPPLIER SCORECARD ENTRY -------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='supplier-incoming-quality'), 'Supplier Scorecard Entry', 'supplier-scorecard-entry',
 'Monthly supplier performance scoring across five dimensions.', 'bi-star-half', 'Score each dimension 0-100 for the period.', 'monthly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Quality Score','quality_score','percentage',1,0,100,1),(@t,'Delivery Score','delivery_score','percentage',1,0,100,2),
(@t,'Cost Score','cost_score','percentage',1,0,100,3),(@t,'Responsiveness Score','responsiveness_score','percentage',1,0,100,4),
(@t,'Compliance Score','compliance_score','percentage',1,0,100,5);

-- 20. OEE SHIFT ENTRY ------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'OEE Shift Entry', 'oee-shift-entry',
 'Record shift production data to calculate Availability, Performance, Quality and OEE.', 'bi-speedometer', 'Enter planned time, downtime, cycle time and counts for the shift.', 'per_shift', 'calculator', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Planned Minutes','planned_minutes','number',1,'min',1),(@t,'Downtime Minutes','downtime_minutes','number',1,'min',2),
(@t,'Ideal Cycle Time','ideal_cycle_time','decimal',1,'min/unit',3),(@t,'Total Count','total_count','number',1,'units',4),
(@t,'Good Count','good_count','number',1,'units',5);

-- 21. CHECK SHEET (Basic 7 QC) ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='basic-7-qc'), 'Check Sheet', 'check-sheet',
 'Simple tally check sheet for defect/event frequency data collection.', 'bi-ui-checks', 'Select category and record tally count.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Category','category','dropdown',1,1),(@t,'Tally Count','tally_count','number',1,2);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Machine','machine',1),(@f,'Operator','operator',2),(@f,'Material','material',3),(@f,'Method','method',4);

-- 22. STRATIFICATION ------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='basic-7-qc'), 'Stratification', 'stratification',
 'Break down a data set by machine, operator, shift or material to isolate a source of variation.', 'bi-layers', 'Select the stratification category and record the observed value.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Stratification Factor','factor','dropdown',1,1),(@t,'Value','value','number',1,2);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Machine','machine',1),(@f,'Operator','operator',2),(@f,'Shift','shift',3),(@f,'Material Lot','material_lot',4);

-- 23. LAYERED PROCESS AUDIT (LPA) ------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'Layered Process Audit', 'layered-process-audit',
 'Different management layers perform short, frequent process observations.', 'bi-layers-half', 'Record observation and immediate result.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Audit Layer','layer','dropdown',1,1),(@t,'Area','area','text',1,2),
(@t,'Observation','observation','long_text',0,3),(@t,'Result','result','pass_fail',1,4);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Operator','operator',1),(@f,'Supervisor','supervisor',2),(@f,'Plant Manager','manager',3),(@f,'Executive','executive',4);

-- 24. ALLERGEN VERIFICATION SWAB -------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Allergen Verification Swab', 'allergen-verification-swab',
 'Post-cleaning allergen swab verification before changeover to an allergen-free product.', 'bi-exclamation-octagon',
 'Swab equipment contact surfaces after cleaning and before running an allergen-sensitive product.', 'per_batch', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Area / Equipment','area','text',1,1),(@t,'Allergen Tested','allergen','text',1,2),
(@t,'Swab Result','swab_result','pass_fail',1,3);
