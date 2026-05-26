CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_shareholder TINYINT(1) NOT NULL DEFAULT 0,
    is_admin TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    area VARCHAR(40) NOT NULL,
    can_read TINYINT(1) NOT NULL DEFAULT 1,
    can_write TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_user_area (user_id, area),
    CONSTRAINT fk_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE barbers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_barbers_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leaders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    area VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recruitment_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE targets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kpi_key VARCHAR(80) NOT NULL UNIQUE,
    area VARCHAR(80) NOT NULL,
    kpi VARCHAR(160) NOT NULL,
    target_value DECIMAL(12,4) NOT NULL,
    amber_threshold DECIMAL(12,4) NOT NULL,
    red_threshold DECIMAL(12,4) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    notes VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weekly_barber_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    barber_id INT UNSIGNED NOT NULL,
    rtb_cash DECIMAL(10,2) NOT NULL DEFAULT 0,
    rtb_card DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_sales DECIMAL(10,2) NOT NULL DEFAULT 0,
    days_worked DECIMAL(4,1) NOT NULL DEFAULT 0,
    rebooking_pct DECIMAL(6,4) NOT NULL DEFAULT 0,
    utilisation_pct DECIMAL(6,4) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_barber_week (week_start),
    CONSTRAINT fk_wbs_site FOREIGN KEY (site_id) REFERENCES sites(id),
    CONSTRAINT fk_wbs_barber FOREIGN KEY (barber_id) REFERENCES barbers(id),
    CONSTRAINT fk_wbs_user FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    learner VARCHAR(140) NOT NULL,
    attendance_pct DECIMAL(6,4) NOT NULL DEFAULT 0,
    progress_pct DECIMAL(6,4) NOT NULL DEFAULT 0,
    epa_readiness VARCHAR(80) NOT NULL,
    safeguarding_flags INT UNSIGNED NOT NULL DEFAULT 0,
    risk_notes TEXT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_training_week (week_start),
    CONSTRAINT fk_training_user FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brand_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    posts INT UNSIGNED NOT NULL DEFAULT 0,
    reels INT UNSIGNED NOT NULL DEFAULT 0,
    reach INT UNSIGNED NOT NULL DEFAULT 0,
    engagement INT UNSIGNED NOT NULL DEFAULT 0,
    leads INT UNSIGNED NOT NULL DEFAULT 0,
    follow_ups INT UNSIGNED NOT NULL DEFAULT 0,
    conversion_pct DECIMAL(6,4) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_brand_week (week_start),
    CONSTRAINT fk_brand_submission_brand FOREIGN KEY (brand_id) REFERENCES brands(id),
    CONSTRAINT fk_brand_submission_user FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hr_recruitment_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    required_count INT UNSIGNED NOT NULL DEFAULT 0,
    active_pipeline INT UNSIGNED NOT NULL DEFAULT 0,
    interviews INT UNSIGNED NOT NULL DEFAULT 0,
    offers INT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_week (week_start),
    CONSTRAINT fk_hr_role FOREIGN KEY (role_id) REFERENCES recruitment_roles(id),
    CONSTRAINT fk_hr_user FOREIGN KEY (submitted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE risk_register (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    trigger_label VARCHAR(180) NOT NULL,
    risk TEXT NOT NULL,
    owner_user_id INT UNSIGNED NOT NULL,
    priority ENUM('High', 'Medium', 'Low') NOT NULL DEFAULT 'Medium',
    status ENUM('Open', 'In Progress', 'Closed') NOT NULL DEFAULT 'Open',
    due_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_risk_week (week_start),
    CONSTRAINT fk_risk_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE action_tracker (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    owner_user_id INT UNSIGNED NOT NULL,
    action TEXT NOT NULL,
    due_date DATE NULL,
    status ENUM('Open', 'In Progress', 'Closed') NOT NULL DEFAULT 'Open',
    priority ENUM('High', 'Medium', 'Low') NOT NULL DEFAULT 'Medium',
    linked_area VARCHAR(80) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_week (week_start),
    CONSTRAINT fk_action_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    meta JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

