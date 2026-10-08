ALTER TABLE users
    ADD COLUMN consecutive_days INT NOT NULL DEFAULT 0,
    ADD COLUMN last_activity_date DATE NULL;
