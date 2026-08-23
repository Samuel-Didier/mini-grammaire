ALTER TABLE users
    ADD COLUMN streak_count INT NOT NULL DEFAULT 0,
    ADD COLUMN last_activity_date DATE NULL,
    ADD COLUMN streak_freezes_available INT NOT NULL DEFAULT 0;
