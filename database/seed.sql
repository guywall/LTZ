INSERT INTO roles (role_key, name, is_shareholder, is_admin) VALUES
('shareholder', 'Shareholder', 1, 1),
('barber', 'Barber Area User', 0, 0),
('training', 'Training Area User', 0, 0),
('social', 'Social Media Area User', 0, 0),
('hr', 'HR Area User', 0, 0);

INSERT INTO users (role_id, name, email, password_hash) VALUES
((SELECT id FROM roles WHERE role_key = 'shareholder'), 'Cosmin', 'cosmin@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'shareholder'), 'Mario', 'mario@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'shareholder'), 'Ravi', 'ravi@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'shareholder'), 'Luke', 'luke@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'shareholder'), 'Martin', 'martin@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'barber'), 'Barber Team', 'barbers@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'training'), 'Training Team', 'training@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'social'), 'Social Team', 'social@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.'),
((SELECT id FROM roles WHERE role_key = 'hr'), 'HR Team', 'hr@ltz.local', '$2y$10$k.w95Us4xdN8raCe6b4L2uJTF1ydi0SGVx99uItvUQj61JfJqVg6.');

INSERT INTO user_permissions (user_id, area, can_read, can_write)
SELECT id, 'barbers', 1, 1 FROM users WHERE email IN ('barbers@ltz.local', 'cosmin@ltz.local', 'mario@ltz.local', 'ravi@ltz.local', 'luke@ltz.local', 'martin@ltz.local')
UNION ALL SELECT id, 'training', 1, 1 FROM users WHERE email IN ('training@ltz.local', 'cosmin@ltz.local', 'mario@ltz.local', 'ravi@ltz.local', 'luke@ltz.local', 'martin@ltz.local')
UNION ALL SELECT id, 'social', 1, 1 FROM users WHERE email IN ('social@ltz.local', 'cosmin@ltz.local', 'mario@ltz.local', 'ravi@ltz.local', 'luke@ltz.local', 'martin@ltz.local')
UNION ALL SELECT id, 'hr', 1, 1 FROM users WHERE email IN ('hr@ltz.local', 'cosmin@ltz.local', 'mario@ltz.local', 'ravi@ltz.local', 'luke@ltz.local', 'martin@ltz.local')
UNION ALL SELECT id, 'leadership', 1, 1 FROM users WHERE email IN ('cosmin@ltz.local', 'mario@ltz.local', 'ravi@ltz.local', 'luke@ltz.local', 'martin@ltz.local')
UNION ALL SELECT id, 'strategy', 1, 1 FROM users WHERE email IN ('cosmin@ltz.local', 'mario@ltz.local', 'ravi@ltz.local', 'luke@ltz.local', 'martin@ltz.local')
UNION ALL SELECT id, 'admin', 1, 1 FROM users WHERE email IN ('cosmin@ltz.local', 'mario@ltz.local', 'ravi@ltz.local', 'luke@ltz.local', 'martin@ltz.local');

INSERT INTO sites (name) VALUES
('Soresby'), ('Woodseats'), ('F.AF Cavendish'), ('Broomhill'), ('Clay Cross'), ('Hasland');

INSERT INTO barbers (site_id, name) VALUES
((SELECT id FROM sites WHERE name = 'Soresby'), 'Alex'),
((SELECT id FROM sites WHERE name = 'Soresby'), 'Lewis'),
((SELECT id FROM sites WHERE name = 'F.AF Cavendish'), 'Luke'),
((SELECT id FROM sites WHERE name = 'Broomhill'), 'Aiden'),
((SELECT id FROM sites WHERE name = 'Soresby'), 'Leland'),
((SELECT id FROM sites WHERE name = 'Hasland'), 'Logan'),
((SELECT id FROM sites WHERE name = 'Woodseats'), 'Brendan'),
((SELECT id FROM sites WHERE name = 'Clay Cross'), 'Rossco');

INSERT INTO brands (name) VALUES
('LTZ Barbers'), ('F.AF'), ('Velvet Ash'), ('LTZ Training Academy');

INSERT INTO leaders (name, area) VALUES
('Cosmin', 'Operations'),
('Mario', 'Brand & Sites'),
('Ravi', 'Training'),
('Luke', 'People & Recruitment'),
('Martin', 'Executive Oversight');

INSERT INTO recruitment_roles (name) VALUES
('Senior Barber'), ('Junior Barber'), ('Apprentice'), ('Educator'), ('Site Manager'), ('Trainer');

INSERT INTO targets (kpi_key, area, kpi, target_value, amber_threshold, red_threshold, unit, notes) VALUES
('barber_rtb', 'Barber', 'RTB per barber/week', 500, 400, 350, 'GBP', 'Chair contribution target'),
('barber_days', 'Barber', 'Days worked', 5, 4, 3, 'days', 'Five days is standard'),
('barber_rebooking', 'Barber', 'Rebooking %', 0.8, 0.7, 0.69, '%', '80% or above is healthy'),
('barber_utilisation', 'Barber', 'Utilisation %', 0.75, 0.7, 0.69, '%', '70-85% expected healthy zone'),
('social_posts', 'Social', 'Posts per brand/week', 5, 3, 2, 'count', 'Minimum cadence by brand'),
('social_reels', 'Social', 'Reels per brand/week', 3, 2, 1, 'count', 'Growth/content visibility driver'),
('social_followup', 'Social', 'Lead follow-up %', 0.9, 0.75, 0.74, '%', 'Commercial discipline metric'),
('recruitment_pipeline', 'Recruitment', 'Senior barber pipeline', 3, 2, 1, 'count', 'Active candidates per role'),
('training_attendance', 'Training', 'Learner attendance %', 0.9, 0.8, 0.79, '%', 'Below 80% requires intervention'),
('training_safeguarding', 'Training', 'Safeguarding unresolved flags', 0, 1, 2, 'count', 'Any open flag must be reviewed'),
('governance_compliance', 'Governance', 'Submission compliance %', 1, 0.9, 0.89, '%', 'Expected full weekly submission'),
('strategy_weekly_rtb', '5x5', 'Weekly RTB', 12000, 10000, 8999, 'GBP', 'Required trajectory for 5x5'),
('strategy_occupied_chairs', '5x5', 'Occupied Chairs', 18, 14, 12, 'count', 'Across all active sites'),
('strategy_active_learners', '5x5', 'Active Learners', 40, 30, 24, 'count', 'Training growth target'),
('strategy_social_leads', '5x5', 'Social Leads', 60, 40, 29, 'count', 'Combined brand lead target'),
('strategy_senior_pipeline', '5x5', 'Senior Barber Pipeline', 3, 2, 1, 'count', 'Minimum active senior candidates');

INSERT INTO weekly_barber_submissions (week_start, site_id, barber_id, rtb_cash, rtb_card, total_sales, days_worked, rebooking_pct, utilisation_pct, notes, submitted_by) VALUES
('2026-05-18', (SELECT id FROM sites WHERE name = 'Soresby'), (SELECT id FROM barbers WHERE name = 'Alex'), 50, 50, 200, 5, 0.50, 0.50, 'TEST', (SELECT id FROM users WHERE email = 'mario@ltz.local')),
('2026-05-18', (SELECT id FROM sites WHERE name = 'Soresby'), (SELECT id FROM barbers WHERE name = 'Lewis'), 70, 60, 130, 6, 0.30, 0.40, 'test', (SELECT id FROM users WHERE email = 'cosmin@ltz.local')),
('2026-05-18', (SELECT id FROM sites WHERE name = 'Soresby'), (SELECT id FROM barbers WHERE name = 'Leland'), 6, 1000, 1006, 7, 0.75, 0.80, NULL, (SELECT id FROM users WHERE email = 'luke@ltz.local')),
('2026-05-18', (SELECT id FROM sites WHERE name = 'Soresby'), (SELECT id FROM barbers WHERE name = 'Aiden'), 500, 500, 1000, 3, 0.80, 0.75, NULL, (SELECT id FROM users WHERE email = 'ravi@ltz.local')),
('2026-05-18', (SELECT id FROM sites WHERE name = 'Woodseats'), (SELECT id FROM barbers WHERE name = 'Brendan'), 250, 250, 500, 5, 0.81, 0.78, NULL, (SELECT id FROM users WHERE email = 'cosmin@ltz.local')),
('2026-05-18', (SELECT id FROM sites WHERE name = 'Clay Cross'), (SELECT id FROM barbers WHERE name = 'Rossco'), 80, 70, 300, 2, 0.45, 0.55, NULL, (SELECT id FROM users WHERE email = 'cosmin@ltz.local'));

INSERT INTO training_submissions (week_start, learner, attendance_pct, progress_pct, epa_readiness, safeguarding_flags, risk_notes, submitted_by) VALUES
('2026-05-18', 'Learner 1', 0.92, 0.70, 'On Track', 0, NULL, (SELECT id FROM users WHERE email = 'ravi@ltz.local')),
('2026-05-18', 'Learner 2', 0.78, 0.45, 'At Risk', 1, 'Attendance intervention required', (SELECT id FROM users WHERE email = 'ravi@ltz.local'));

INSERT INTO brand_submissions (week_start, brand_id, posts, reels, reach, engagement, leads, follow_ups, conversion_pct, notes, submitted_by) VALUES
('2026-05-18', (SELECT id FROM brands WHERE name = 'LTZ Barbers'), 3, 1, 1200, 95, 4, 3, 0.75, NULL, (SELECT id FROM users WHERE email = 'mario@ltz.local')),
('2026-05-18', (SELECT id FROM brands WHERE name = 'F.AF'), 5, 3, 2200, 210, 8, 8, 1.00, NULL, (SELECT id FROM users WHERE email = 'mario@ltz.local')),
('2026-05-18', (SELECT id FROM brands WHERE name = 'Velvet Ash'), 1, 0, 300, 15, 0, 0, 0.00, 'Needs content cadence', (SELECT id FROM users WHERE email = 'mario@ltz.local')),
('2026-05-18', (SELECT id FROM brands WHERE name = 'LTZ Training Academy'), 4, 2, 900, 55, 3, 2, 0.67, NULL, (SELECT id FROM users WHERE email = 'ravi@ltz.local'));

INSERT INTO hr_recruitment_submissions (week_start, role_id, required_count, active_pipeline, interviews, offers, notes, submitted_by) VALUES
('2026-05-18', (SELECT id FROM recruitment_roles WHERE name = 'Senior Barber'), 3, 1, 1, 0, 'Needs stronger outreach', (SELECT id FROM users WHERE email = 'luke@ltz.local')),
('2026-05-18', (SELECT id FROM recruitment_roles WHERE name = 'Junior Barber'), 2, 2, 1, 0, NULL, (SELECT id FROM users WHERE email = 'luke@ltz.local')),
('2026-05-18', (SELECT id FROM recruitment_roles WHERE name = 'Apprentice'), 4, 3, 2, 1, NULL, (SELECT id FROM users WHERE email = 'luke@ltz.local'));

INSERT INTO risk_register (week_start, trigger_label, risk, owner_user_id, priority, status, due_date, notes) VALUES
('2026-05-18', 'RTB below target', 'Soresby output depends on few performers', (SELECT id FROM users WHERE email = 'cosmin@ltz.local'), 'High', 'Open', '2026-05-28', 'COO to review utilisation and chair plan');

INSERT INTO action_tracker (week_start, owner_user_id, action, due_date, status, priority, linked_area, notes) VALUES
('2026-05-18', (SELECT id FROM users WHERE email = 'cosmin@ltz.local'), 'Review Woodseats rebooking decline', '2026-05-23', 'Open', 'High', 'Operations', NULL);

