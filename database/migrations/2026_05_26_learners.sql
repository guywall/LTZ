CREATE TABLE IF NOT EXISTS learners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL UNIQUE,
    status ENUM('Active', 'Paused', 'Completed', 'Withdrawn') NOT NULL DEFAULT 'Active',
    start_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO learners (name)
SELECT DISTINCT learner
FROM training_submissions
WHERE learner IS NOT NULL AND learner <> '';

ALTER TABLE training_submissions
    ADD COLUMN learner_id INT UNSIGNED NULL AFTER week_start;

UPDATE training_submissions ts
JOIN learners l ON l.name = ts.learner
SET ts.learner_id = l.id
WHERE ts.learner_id IS NULL;

ALTER TABLE training_submissions
    ADD INDEX idx_training_learner (learner_id);

ALTER TABLE training_submissions
    ADD CONSTRAINT fk_training_learner FOREIGN KEY (learner_id) REFERENCES learners(id);

