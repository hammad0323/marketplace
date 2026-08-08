-- ============================================================================
-- MediConnect — seed / demo data
-- Import AFTER schema.sql. Passwords below are bcrypt hashes; the plaintext
-- values are listed here and in README.md for local testing only — rotate
-- them before any real deployment.
--
--   admin@mediconnect.test    / Admin@12345
--   sarah.chen@mediconnect.test (doctor) / Doctor@12345   (all seeded doctors share this password)
--   patient@mediconnect.test  / Patient@12345
-- ============================================================================

USE mediconnect;
SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- Specializations
-- ----------------------------------------------------------------------------
INSERT INTO specializations (name, slug, icon, description, sort_order) VALUES
('Cardiology',        'cardiology',        'ri-heart-pulse-line',     'Heart & cardiovascular care', 1),
('Dermatology',       'dermatology',       'ri-leaf-line',            'Skin, hair & nail treatment', 2),
('Pediatrics',        'pediatrics',        'ri-parent-line',          'Child & infant health', 3),
('Neurology',         'neurology',         'ri-brain-line',           'Brain & nervous system', 4),
('Orthopedics',       'orthopedics',       'ri-walk-line',            'Bones, joints & muscles', 5),
('Gynecology',        'gynecology',        'ri-women-line',           'Women\'s health & maternity', 6),
('Dentistry',         'dentistry',         'ri-tooth-line',           'Dental & oral care', 7),
('General Physician', 'general-physician', 'ri-stethoscope-line',     'Everyday health concerns', 8),
('Psychiatry',        'psychiatry',        'ri-mental-health-line',   'Mental & emotional wellbeing', 9),
('ENT',               'ent',               'ri-ear-line',             'Ear, nose & throat', 10);

-- ----------------------------------------------------------------------------
-- Admin user
-- ----------------------------------------------------------------------------
INSERT INTO users (role, full_name, email, phone, password_hash, status, email_verified_at) VALUES
('admin', 'Platform Admin', 'admin@mediconnect.test', '+1-555-0100', '$2y$12$s/w4JL8JvFVf0fg1g9pei.R6tIBsSzuQHhz88oHOd4erpShfUoNLK', 'active', NOW());

-- ----------------------------------------------------------------------------
-- Doctors (users + doctors + privacy + availability)
-- All seeded doctors use the password: Doctor@12345
-- ----------------------------------------------------------------------------
INSERT INTO users (role, full_name, email, phone, password_hash, status, email_verified_at) VALUES
('doctor', 'Dr. Sarah Chen',      'sarah.chen@mediconnect.test',      '+1-555-0101', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'active', NOW()),
('doctor', 'Dr. Michael Rivera',  'michael.rivera@mediconnect.test',  '+1-555-0102', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'active', NOW()),
('doctor', 'Dr. Amara Okafor',    'amara.okafor@mediconnect.test',    '+1-555-0103', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'active', NOW()),
('doctor', 'Dr. Priya Nair',      'priya.nair@mediconnect.test',      '+1-555-0104', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'active', NOW()),
('doctor', 'Dr. James Whitfield', 'james.whitfield@mediconnect.test', '+1-555-0105', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'active', NOW()),
('doctor', 'Dr. Elena Petrova',   'elena.petrova@mediconnect.test',   '+1-555-0106', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'active', NOW()),
('doctor', 'Dr. Daniel Kim',      'daniel.kim@mediconnect.test',      '+1-555-0107', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'active', NOW()),
('doctor', 'Dr. Fatima Al-Sayed', 'fatima.alsayed@mediconnect.test',  '+1-555-0108', '$2y$12$IDukN3UR.t5zZXTuvHYLh.t6BMTK8bJFUgai6v65IiDGFr4Ybi2ya', 'pending', NULL);

INSERT INTO doctors (user_id, slug, qualification, registration_number, experience_years, bio, consultation_fee_online, consultation_fee_physical, free_consultation, clinic_name, clinic_address, clinic_city, clinic_state, clinic_country, verification_status, is_premium, rating_avg, rating_count) VALUES
(2, 'dr-sarah-chen',      'MD, FACC — Cardiology',            'MED-CA-10234', 14, 'Dr. Sarah Chen is a board-certified cardiologist specializing in preventive cardiology and heart failure management. She combines evidence-based medicine with a warm, patient-first approach.', 60.00, 80.00, 0, 'Heartwell Cardiology Clinic', '221 Sunrise Ave, Suite 4', 'San Francisco', 'CA', 'USA', 'verified', 1, 4.80, 132),
(3, 'dr-michael-rivera',  'MD, Neurology',                    'MED-NE-10891', 9,  'Dr. Michael Rivera focuses on migraine management, epilepsy, and general neurological disorders, offering both in-person and telehealth consultations.', 55.00, 75.00, 0, 'Rivera Neuro Center', '88 Baker Street', 'Austin', 'TX', 'USA', 'verified', 1, 4.60, 87),
(4, 'dr-amara-okafor',    'MBBS, DCH — Pediatrics',           'MED-PE-11023', 11, 'Dr. Amara Okafor has spent over a decade caring for infants, children, and adolescents, with a focus on early developmental screening.', 40.00, 50.00, 1, 'Little Sparks Pediatric Clinic', '14 Maple Grove Rd', 'Chicago', 'IL', 'USA', 'verified', 1, 4.90, 210),
(5, 'dr-priya-nair',      'MD, DGO — Gynecology',             'MED-GY-11390', 13, 'Dr. Priya Nair provides comprehensive women\'s health services including prenatal care, family planning, and menopause management.', 50.00, 65.00, 0, 'Nair Women\'s Health Clinic', '56 Lotus Lane', 'Seattle', 'WA', 'USA', 'verified', 0, 4.70, 96),
(6, 'dr-james-whitfield', 'MD, Dermatology',                  'MED-DE-11577', 7,  'Dr. James Whitfield treats acne, eczema, psoriasis, and offers cosmetic dermatology consultations.', 45.00, 60.00, 0, 'Whitfield Skin Institute', '9 Cedar Court', 'Denver', 'CO', 'USA', 'verified', 0, 4.50, 54),
(7, 'dr-elena-petrova',   'MD, Orthopedic Surgery',           'MED-OR-11842', 16, 'Dr. Elena Petrova specializes in sports injuries, joint replacement, and non-surgical pain management.', 55.00, 90.00, 0, 'Petrova Bone & Joint Center', '302 Riverside Dr', 'Miami', 'FL', 'USA', 'verified', 1, 4.75, 143),
(8, 'dr-daniel-kim',      'MBBS — General Physician',         'MED-GP-12005', 5,  'Dr. Daniel Kim offers same-day consultations for common illnesses, chronic disease management, and preventive checkups.', 25.00, 35.00, 1, 'Kim Family Practice', '77 Willow St', 'Portland', 'OR', 'USA', 'verified', 0, 4.40, 61),
(9, 'dr-fatima-alsayed',  'MD, Psychiatry',                   'MED-PS-12271', 8,  'Dr. Fatima Al-Sayed provides confidential mental health consultations for anxiety, depression, and stress management. (Pending verification)', 50.00, 65.00, 0, 'Mindful Wellness Practice', '18 Harbor View', 'Boston', 'MA', 'USA', 'pending', 0, 0.00, 0);

-- Doctors can practice under more than one specialization; a few are given
-- a second one here to demonstrate it (specialization ids per the INSERT above).
INSERT INTO doctor_specializations (doctor_id, specialization_id) VALUES
(1, 1), (1, 8),   -- Dr. Sarah Chen: Cardiology + General Physician
(2, 4), (2, 9),   -- Dr. Michael Rivera: Neurology + Psychiatry
(3, 3), (3, 8),   -- Dr. Amara Okafor: Pediatrics + General Physician
(4, 6),           -- Dr. Priya Nair: Gynecology
(5, 2),           -- Dr. James Whitfield: Dermatology
(6, 5),           -- Dr. Elena Petrova: Orthopedics
(7, 8), (7, 10),  -- Dr. Daniel Kim: General Physician + ENT
(8, 9);           -- Dr. Fatima Al-Sayed: Psychiatry

-- Chat demo: Dr. Sarah Chen accepts messages 9am-6pm from logged-in patients
-- only; Dr. Amara Okafor also allows guests to see her chat/online status.
UPDATE doctors SET chat_enabled = 1, chat_start_time = '09:00:00', chat_end_time = '18:00:00' WHERE id = 1;
UPDATE doctors SET chat_enabled = 1, chat_visible_to_guests = 1, chat_start_time = '08:00:00', chat_end_time = '20:00:00' WHERE id = 3;
UPDATE users SET last_active_at = NOW() WHERE id IN (2, 4);

INSERT INTO doctor_privacy_settings (doctor_id, show_certificates, show_fees, show_availability, show_clinic_address, show_phone, show_email, show_free_consultation, show_store, show_reviews) VALUES
(1, 1, 1, 1, 1, 0, 0, 1, 1, 1),
(2, 1, 1, 1, 1, 0, 0, 1, 1, 1),
(3, 1, 1, 1, 1, 1, 0, 1, 1, 1),
(4, 1, 1, 1, 0, 0, 0, 1, 1, 1),
(5, 1, 1, 1, 1, 0, 0, 1, 1, 1),
(6, 1, 1, 1, 1, 0, 0, 1, 1, 1),
(7, 1, 1, 1, 1, 0, 0, 1, 1, 1),
(8, 1, 0, 1, 1, 0, 0, 1, 1, 1);

-- Weekly availability: Mon–Fri 09:00–17:00, online+physical, 30 min slots (for the first 7 verified doctors)
INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, slot_duration_mins, consultation_type) VALUES
(1,1,'09:00:00','17:00:00',30,'both'),(1,2,'09:00:00','17:00:00',30,'both'),(1,3,'09:00:00','17:00:00',30,'both'),(1,4,'09:00:00','17:00:00',30,'both'),(1,5,'09:00:00','13:00:00',30,'online'),
(2,1,'10:00:00','18:00:00',30,'both'),(2,2,'10:00:00','18:00:00',30,'both'),(2,3,'10:00:00','18:00:00',30,'both'),(2,4,'10:00:00','18:00:00',30,'both'),(2,5,'10:00:00','15:00:00',30,'both'),
(3,1,'09:00:00','16:00:00',20,'both'),(3,2,'09:00:00','16:00:00',20,'both'),(3,3,'09:00:00','16:00:00',20,'both'),(3,4,'09:00:00','16:00:00',20,'both'),(3,5,'09:00:00','16:00:00',20,'both'),(3,6,'10:00:00','13:00:00',20,'online'),
(4,1,'09:30:00','17:30:00',30,'both'),(4,2,'09:30:00','17:30:00',30,'both'),(4,3,'09:30:00','17:30:00',30,'both'),(4,4,'09:30:00','17:30:00',30,'both'),(4,5,'09:30:00','14:00:00',30,'both'),
(5,1,'11:00:00','19:00:00',30,'both'),(5,2,'11:00:00','19:00:00',30,'both'),(5,3,'11:00:00','19:00:00',30,'both'),(5,4,'11:00:00','19:00:00',30,'both'),(5,5,'11:00:00','16:00:00',30,'online'),
(6,1,'08:00:00','15:00:00',30,'both'),(6,2,'08:00:00','15:00:00',30,'both'),(6,3,'08:00:00','15:00:00',30,'both'),(6,4,'08:00:00','15:00:00',30,'both'),(6,5,'08:00:00','12:00:00',30,'both'),
(7,1,'09:00:00','18:00:00',20,'both'),(7,2,'09:00:00','18:00:00',20,'both'),(7,3,'09:00:00','18:00:00',20,'both'),(7,4,'09:00:00','18:00:00',20,'both'),(7,5,'09:00:00','18:00:00',20,'both'),(7,6,'09:00:00','12:00:00',20,'online');

INSERT INTO doctor_blocked_dates (doctor_id, blocked_date, reason) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Medical conference'),
(3, DATE_ADD(CURDATE(), INTERVAL 5 DAY),  'Personal leave');

-- ----------------------------------------------------------------------------
-- Patients
-- ----------------------------------------------------------------------------
INSERT INTO users (role, full_name, email, phone, password_hash, status, email_verified_at) VALUES
('patient', 'John Anderson', 'patient@mediconnect.test', '+1-555-0200', '$2y$12$EmxGYE4newY/d2uUNYZhpOpCbfm61oFXy6wlhl8mgAi4rRKujmGyu', 'active', NOW()),
('patient', 'Grace Lin',     'grace.lin@example.com',    '+1-555-0201', '$2y$12$EmxGYE4newY/d2uUNYZhpOpCbfm61oFXy6wlhl8mgAi4rRKujmGyu', 'active', NOW());

INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address, city, state, country) VALUES
(10, '1990-04-12', 'male',   'O+', '400 Pine St', 'San Francisco', 'CA', 'USA'),
(11, '1995-09-03', 'female', 'A+', '12 Oak Ave',  'Austin', 'TX', 'USA');

-- Sample appointments
INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, consultation_type, status, fee, reason) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:00:00', '10:30:00', 'online', 'approved', 60.00, 'Follow-up on blood pressure medication'),
(1, 3, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:20:00', '11:40:00', 'physical', 'pending', 50.00, 'Annual child wellness checkup'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 20 DAY), '09:30:00', '10:00:00', 'online', 'completed', 60.00, 'Chest discomfort evaluation');

INSERT INTO reviews (appointment_id, patient_id, doctor_id, rating, comment) VALUES
(3, 2, 1, 5, 'Dr. Chen was thorough and explained everything clearly. Highly recommend.');

-- ----------------------------------------------------------------------------
-- Site content
-- ----------------------------------------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'MediConnect'),
('site_tagline', 'Trusted care, one click away'),
('contact_email', 'support@mediconnect.test'),
('contact_phone', '+1-555-0111'),
('contact_address', '500 Market Street, San Francisco, CA'),
('currency_symbol', '$'),
('maintenance_mode', '0'),
('email_notifications_enabled', '1'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', ''),
('smtp_from_name', 'MediConnect');

INSERT INTO cms_pages (slug, title, content, meta_title, meta_description) VALUES
('about', 'About MediConnect', '<p>MediConnect connects patients with verified, board-certified doctors for online and in-person consultations. Our mission is to make quality healthcare accessible, transparent, and fast — whether you need a five-minute follow-up or a same-day specialist referral.</p><p>Every doctor on our platform is manually verified by our medical credentialing team before they can accept patients.</p>', 'About Us | MediConnect', 'Learn about MediConnect''s mission to make verified healthcare accessible to everyone.'),
('privacy-policy', 'Privacy Policy', '<p>We collect only the information required to deliver our telemedicine services: your account details, appointment history, and any medical documents you choose to upload. Data is encrypted in transit and access is restricted to your care team and platform administrators.</p><p>We never sell patient data to third parties.</p>', 'Privacy Policy | MediConnect', 'How MediConnect collects, uses, and protects your personal and medical information.'),
('terms-conditions', 'Terms & Conditions', '<p>By using MediConnect you agree to provide accurate information when booking appointments and to attend or cancel/reschedule with reasonable notice. MediConnect is a booking and communication platform; all medical advice is provided solely by the independent, licensed practitioners on our platform.</p>', 'Terms & Conditions | MediConnect', 'The terms governing use of the MediConnect telemedicine platform.');

INSERT INTO faqs (question, answer, sort_order) VALUES
('How do I book an appointment?', 'Search for a doctor by specialization or name, open their profile, choose an available time slot, and confirm your booking. You will need a free patient account to complete the booking.', 1),
('Are online consultations as effective as in-person visits?', 'For many follow-ups, prescription renewals, and general consultations, online visits are just as effective and far more convenient. Your doctor will advise if an in-person visit is needed.', 2),
('How are doctors verified?', 'Every doctor submits their medical registration number and certificates during onboarding. Our team manually reviews and verifies each doctor before they appear as "Verified" on the platform.', 3),
('Can I cancel or reschedule an appointment?', 'Yes, from your patient dashboard you can cancel or reschedule an appointment any time before it is marked completed, subject to the doctor''s cancellation window.', 4),
('Is my medical information private?', 'Yes. All medical records, chat messages, and uploaded documents are only visible to you, your doctor, and authorized platform administrators.', 5);

INSERT INTO testimonials (name, role, content, rating, sort_order) VALUES
('Grace Lin', 'Patient', 'Booking an online consultation took less than two minutes and Dr. Chen called in exactly on time. This is how healthcare should work.', 5, 1),
('Marcus Webb', 'Patient', 'I found a verified pediatrician for my daughter within our budget and the whole family loves her now.', 5, 2),
('Dr. Elena Petrova', 'Orthopedic Surgeon', 'MediConnect''s scheduling tools cut my no-show rate in half. The platform is genuinely built for how clinicians work.', 5, 3);

-- ----------------------------------------------------------------------------
-- Membership plans (phase 2+, referenced by doctors.membership_plan_id)
-- ----------------------------------------------------------------------------
INSERT INTO membership_plans (name, slug, price, billing_cycle, features, priority_listing, store_unlocked) VALUES
('Free',         'free',         0.00,   'free',    '["Profile listing","Up to 10 appointments/mo"]', 0, 0),
('Professional', 'professional', 29.00,  'monthly', '["Unlimited appointments","Patient chat","Basic analytics"]', 0, 0),
('Premium',      'premium',      79.00,  'monthly', '["Priority listing","Medicine store access","Homepage featured"]', 1, 1),
('Enterprise',   'enterprise',   199.00, 'monthly', '["Multi-clinic management","Dedicated support","Custom branding"]', 1, 1);
