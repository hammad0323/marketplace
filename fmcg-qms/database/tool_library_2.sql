-- ============================================================================
-- Dynamic Quality Tool Library - BATCH 2 (19 more tools, same pattern as
-- tool_library.sql). Run after tool_library.sql. Global tools (company_id NULL).
-- ============================================================================

USE `fmcg_qms`;

-- 25. FAULT TREE ANALYSIS (FTA) ---------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), 'Fault Tree Analysis', 'fault-tree-analysis',
 'Top event -> intermediate event -> basic event fault tree with AND/OR gates.', 'bi-diagram-3',
 'Log each branch of the fault tree as a row: top event, the intermediate event leading to it, the basic (root) event, and how they combine.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Top Event','top_event','text',1,1),
(@t,'Intermediate Event','intermediate_event','text',0,2),
(@t,'Basic Event','basic_event','text',1,3),
(@t,'Gate Type','gate_type','dropdown',1,4),
(@t,'Probability (0-1)','probability','decimal',0,5);
SET @f := LAST_INSERT_ID() + 3;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'AND Gate','and',1),(@f,'OR Gate','or',2);

-- 26. APQP TRACKER -----------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'APQP Tracker', 'apqp-tracker',
 'Track Advanced Product Quality Planning tasks across the five APQP phases.', 'bi-kanban', 'Log each task under its APQP stage with owner and target date.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Stage','stage','dropdown',1,1),
(@t,'Task','task','text',1,2),
(@t,'Status','status','dropdown',1,3),
(@t,'Target Date','target_date','date',0,4),
(@t,'Owner','owner','text',0,5);
SET @f1 := LAST_INSERT_ID();
SET @f2 := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Planning','planning',1),(@f1,'Product Design','product_design',2),(@f1,'Process Design','process_design',3),
(@f1,'Validation','validation',4),(@f1,'Feedback / Improvement','feedback_improvement',5);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Not Started','not_started',1),(@f2,'In Progress','in_progress',2),(@f2,'Complete','complete',3),(@f2,'At Risk','at_risk',4);

-- 27. PPAP SUBMISSION --------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'PPAP Submission', 'ppap-submission',
 'Production Part Approval Process element tracking and sign-off.', 'bi-file-earmark-check', 'Record the submission level, PPAP element and pass/fail status for the part.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Part Number','part_number','text',1,1),
(@t,'Part Name','part_name','text',1,2),
(@t,'Submission Level','submission_level','dropdown',1,3),
(@t,'PPAP Element','ppap_element','dropdown',1,4),
(@t,'Status','status_result','pass_fail',1,5),
(@t,'Notes','notes','long_text',0,6);
SET @f1 := LAST_INSERT_ID() + 2;
SET @f2 := LAST_INSERT_ID() + 3;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Level 1','level_1',1),(@f1,'Level 2','level_2',2),(@f1,'Level 3','level_3',3),(@f1,'Level 4','level_4',4),(@f1,'Level 5','level_5',5);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Design Records','design_records',1),(@f2,'Process Flow Diagram','process_flow',2),(@f2,'Control Plan','control_plan',3),
(@f2,'FMEA','fmea',4),(@f2,'Inspection Results','inspection_results',5),(@f2,'CoA','coa',6),(@f2,'Sample Approval','sample_approval',7),(@f2,'PSW','psw',8);

-- 28. CONTROL PLAN ENTRY -----------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'Control Plan Entry', 'control-plan-entry',
 'Document process control plan characteristics and reaction plans.', 'bi-list-check', 'One row per controlled characteristic.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Process','process_name','text',1,1),
(@t,'Operation','operation','text',0,2),
(@t,'Specification','specification','text',1,3),
(@t,'Measurement Method','measurement_method','text',0,4),
(@t,'Frequency','frequency_text','text',0,5),
(@t,'Responsible Person','responsible_person_text','text',0,6),
(@t,'Reaction Plan','reaction_plan','long_text',0,7);

-- 29. QFD - HOUSE OF QUALITY ENTRY --------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'QFD - House of Quality', 'qfd-house-of-quality',
 'Map customer requirements to technical requirements with relationship strength and priority.', 'bi-grid-1x2', 'One row per customer-to-technical requirement relationship.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Customer Requirement','customer_requirement','text',1,NULL,NULL,1),
(@t,'Technical Requirement','technical_requirement','text',1,NULL,NULL,2),
(@t,'Relationship Strength','relationship_strength','dropdown',1,NULL,NULL,3),
(@t,'Priority (1-5)','priority','rating',0,1,5,4);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Strong','strong',1),(@f,'Medium','medium',2),(@f,'Weak','weak',3),(@f,'None','none',4);

-- 30. DOE RUN LOG -------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'DOE Run Log', 'doe-run-log',
 'Design of Experiments run log: factors, levels and measured response.', 'bi-sliders', 'Record each experimental run with its factor settings and response.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Run Number','run_number','number',1,1),
(@t,'Factor A','factor_a','text',0,2),
(@t,'Factor B','factor_b','text',0,3),
(@t,'Factor C','factor_c','text',0,4),
(@t,'Response','response_value','number',1,5),
(@t,'Notes','notes','text',0,6);

-- 31. VALUE STREAM MAPPING ENTRY -----------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Value Stream Mapping Entry', 'value-stream-mapping',
 'Log process/wait time and inventory per process step to build a value stream map.', 'bi-arrow-left-right', 'One row per process step in the value stream.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Process Step','process_step','text',1,NULL,1),
(@t,'Process Time','process_time','number',0,'min',2),
(@t,'Wait Time','wait_time','number',0,'min',3),
(@t,'Inventory','inventory','number',0,'units',4),
(@t,'Value Added?','value_added','yes_no',0,NULL,5);

-- 32. SMED CHANGEOVER LOG ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'SMED Changeover Log', 'smed-changeover-log',
 'Single-Minute Exchange of Die: log changeover tasks and time before/after improvement.', 'bi-stopwatch', 'Classify each task as internal (machine stopped) or external (done while running).', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Changeover Task','changeover_task','text',1,NULL,1),
(@t,'Type','changeover_type','dropdown',1,NULL,2),
(@t,'Time Before','time_before','number',0,'min',3),
(@t,'Time After','time_after','number',0,'min',4);
SET @f := LAST_INSERT_ID() + 1;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Internal (machine stopped)','internal',1),(@f,'External (while running)','external',2);

-- 33. POKA-YOKE RECORD --------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Poka-Yoke Record', 'poka-yoke-record',
 'Mistake-proofing device verification record.', 'bi-shield-plus', 'Verify the poka-yoke device correctly prevents/detects the mistake.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Process Step','process_step','text',1,1),
(@t,'Mistake Prevented','mistake_prevented','text',1,2),
(@t,'Method','method','dropdown',0,3),
(@t,'Verification Result','verification_result','pass_fail',1,4);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Contact Method','contact',1),(@f,'Fixed Value Method','fixed_value',2),(@f,'Motion Step Method','motion_step',3);

-- 34. KANBAN CARD UPDATE ------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Kanban Card Update', 'kanban-card-update',
 'Visual pull-system card status update.', 'bi-kanban-fill', 'Update the stage of a Kanban card as work progresses.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Card ID','card_id','text',1,1),
(@t,'Stage','stage','dropdown',1,2),
(@t,'Item','item','text',0,3),
(@t,'Quantity','quantity','number',0,4);
SET @f := LAST_INSERT_ID() + 1;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'To Do','to_do',1),(@f,'In Progress','in_progress',2),(@f,'Done','done',3);

-- 35. TPM EQUIPMENT LOG -------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='maintenance-reliability'), 'TPM Equipment Log', 'tpm-equipment-log',
 'Total Productive Maintenance: log equipment availability, breakdowns and losses.', 'bi-gear-wide-connected', 'Record shift-level equipment availability and any breakdown/loss.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, sort_order) VALUES
(@t,'Equipment','equipment','text',1,NULL,NULL,NULL,1),
(@t,'Availability','availability','percentage',1,'%',0,100,2),
(@t,'Breakdowns','breakdowns','number',0,NULL,NULL,NULL,3),
(@t,'Maintenance Actions','maintenance_actions','long_text',0,NULL,NULL,NULL,4),
(@t,'Losses','losses','long_text',0,NULL,NULL,NULL,5);

-- 36. PREDICTIVE MAINTENANCE READING -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='maintenance-reliability'), 'Predictive Maintenance Reading', 'predictive-maintenance-reading',
 'Condition-based monitoring reading (vibration, temperature, etc.) with trend and risk assessment.', 'bi-activity', 'Record the condition parameter reading and assess trend/risk.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Equipment','equipment','text',1,1),
(@t,'Condition Parameter','condition_parameter','text',1,2),
(@t,'Measurement','measurement_value','number',1,3),
(@t,'Trend','trend','dropdown',0,4),
(@t,'Risk','risk_level','dropdown',0,5);
SET @f1 := LAST_INSERT_ID() + 3;
SET @f2 := LAST_INSERT_ID() + 4;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Stable','stable',1),(@f1,'Increasing','increasing',2),(@f1,'Decreasing','decreasing',3);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Low','low',1),(@f2,'Medium','medium',2),(@f2,'High','high',3);

-- 37. AUTONOMOUS MAINTENANCE CHECKLIST -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='maintenance-reliability'), 'Autonomous Maintenance Checklist', 'autonomous-maintenance-checklist',
 'Operator-performed Clean-Inspect-Lubricate-Tighten checklist.', 'bi-tools', 'Operator completes basic equipment care at shift start.', 'per_shift', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Equipment','equipment','text',1,1),
(@t,'Clean','clean','pass_fail',1,2),
(@t,'Inspect','inspect','pass_fail',1,3),
(@t,'Lubricate','lubricate','pass_fail',1,4),
(@t,'Tighten','tighten','pass_fail',1,5),
(@t,'Notes','notes','text',0,6);

-- 38. NPS SURVEY ENTRY --------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='voice-of-customer'), 'NPS Survey Entry', 'nps-survey-entry',
 'Net Promoter Score entry linked to a customer.', 'bi-emoji-heart-eyes', 'Record the 0-10 likelihood-to-recommend score and any comments.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Customer','customer_name','text',1,NULL,NULL,1),
(@t,'Score (0-10)','score','rating',1,0,10,2),
(@t,'Comments','comments','long_text',0,NULL,NULL,3);

-- 39. BRCGS COMPLIANCE CHECKLIST ------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'BRCGS Compliance Checklist', 'brcgs-compliance-checklist',
 'BRCGS Global Standard for Food Safety self-assessment checklist.', 'bi-patch-check', 'Assess each BRCGS fundamental area.', 'monthly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Senior Management Commitment','senior_management','pass_fail',1,1),
(@t,'Food Safety Plan (HACCP)','food_safety_plan','pass_fail',1,2),
(@t,'Site Standards','site_standards','pass_fail',1,3),
(@t,'Product Control','product_control','pass_fail',1,4),
(@t,'Process Control','process_control','pass_fail',1,5),
(@t,'Personnel','personnel','pass_fail',1,6),
(@t,'Notes','notes','long_text',0,7);

-- 40. SQF COMPLIANCE CHECKLIST --------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'SQF Compliance Checklist', 'sqf-compliance-checklist',
 'Safe Quality Food Program self-assessment checklist.', 'bi-patch-check-fill', 'Assess each SQF code element.', 'monthly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Food Safety Fundamentals','food_safety_fundamentals','pass_fail',1,1),
(@t,'Food Safety Plan','food_safety_plan','pass_fail',1,2),
(@t,'Food Safety System','food_safety_system','pass_fail',1,3),
(@t,'Site/Product Requirements','site_product_requirements','pass_fail',1,4),
(@t,'Notes','notes','long_text',0,5);

-- 41. ISO 22000 COMPLIANCE CHECKLIST ---------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'ISO 22000 Compliance Checklist', 'iso22000-compliance-checklist',
 'ISO 22000 Food Safety Management System self-assessment checklist.', 'bi-award', 'Assess each ISO 22000 core requirement.', 'monthly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Hazard Analysis','hazard_analysis','pass_fail',1,1),
(@t,'Prerequisite Programs (PRP)','prp','pass_fail',1,2),
(@t,'Traceability System','traceability','pass_fail',1,3),
(@t,'Emergency Preparedness','emergency_preparedness','pass_fail',1,4),
(@t,'Notes','notes','long_text',0,5);

-- 42. SHELF-LIFE TESTING -------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Shelf-Life Testing', 'shelf-life-testing',
 'Product shelf-life / expiry assessment under a given storage condition.', 'bi-hourglass-split', 'Record test results and assess whether the declared shelf life still holds.', 'monthly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Product','product_name','text',1,1),
(@t,'Batch','batch_number','text',1,2),
(@t,'Test Date','test_date','date',0,3),
(@t,'Storage Condition','storage_condition','dropdown',1,4),
(@t,'Result','result_text','text',0,5),
(@t,'Expiry Assessment','expiry_assessment','dropdown',1,6);
SET @f1 := LAST_INSERT_ID() + 3;
SET @f2 := LAST_INSERT_ID() + 5;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Ambient','ambient',1),(@f1,'Refrigerated','refrigerated',2),(@f1,'Frozen','frozen',3),(@f1,'Accelerated','accelerated',4);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Pass','pass',1),(@f2,'Extend','extend',2),(@f2,'Fail','fail',3);

-- 43. MOCK RECALL EXERCISE LOG --------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Mock Recall Exercise', 'mock-recall-exercise',
 'Simulated recall exercise to test traceability speed and effectiveness.', 'bi-arrow-counterclockwise',
 'Trace a batch end-to-end and record how long it took and what percentage of affected product was accounted for.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, sort_order) VALUES
(@t,'Recall Scenario','recall_scenario','text',1,NULL,NULL,NULL,1),
(@t,'Batch Traced','batch_traced','text',1,NULL,NULL,NULL,2),
(@t,'Time to Trace','time_to_trace','number',1,'min',NULL,NULL,3),
(@t,'Quantity Traced','quantity_traced','percentage',1,'%',0,100,4),
(@t,'Effectiveness','effectiveness','dropdown',1,NULL,NULL,NULL,5);
SET @f := LAST_INSERT_ID() + 4;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Pass (>95% traced, target time met)','pass',1),(@f,'Fail','fail',2);
