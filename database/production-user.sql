-- Run this as an administrative MySQL account.
-- Replace the password before execution. Do not commit the resulting secret.
CREATE USER IF NOT EXISTS 'ucsi_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_TO_A_LONG_RANDOM_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON ucsi_benefit_impact.* TO 'ucsi_app'@'localhost';
FLUSH PRIVILEGES;
