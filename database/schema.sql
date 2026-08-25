CREATE DATABASE IF NOT EXISTS ucsi_benefit_impact CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ucsi_benefit_impact;

CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','manager','field_officer','analyst','viewer') NOT NULL DEFAULT 'viewer',
 status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_users_role(role), INDEX idx_users_status(status)
) ENGINE=InnoDB;

CREATE TABLE beneficiaries (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 beneficiary_code VARCHAR(30) NOT NULL UNIQUE,
 first_name VARCHAR(80) NOT NULL,
 middle_name VARCHAR(80) NULL,
 last_name VARCHAR(80) NOT NULL,
 gender ENUM('male','female','other') NOT NULL,
 date_of_birth DATE NULL,
 phone VARCHAR(30) NULL,
 email VARCHAR(190) NULL,
 address VARCHAR(255) NULL,
 community VARCHAR(120) NOT NULL,
 lga VARCHAR(120) NULL,
 state VARCHAR(120) NOT NULL,
 household_size SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 vulnerability_status ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
 disability_status TINYINT(1) NOT NULL DEFAULT 0,
 employment_status ENUM('employed','self_employed','unemployed','student','retired','other') NULL,
 status ENUM('active','inactive','graduated','deceased') NOT NULL DEFAULT 'active',
 consent_given TINYINT(1) NOT NULL DEFAULT 0,
 registration_date DATE NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX idx_beneficiary_name(first_name,last_name), INDEX idx_beneficiary_location(state,lga,community), INDEX idx_beneficiary_status(status), INDEX idx_beneficiary_vulnerability(vulnerability_status)
) ENGINE=InnoDB;

CREATE TABLE programmes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(150) NOT NULL,
 description TEXT NULL,
 start_date DATE NOT NULL,
 end_date DATE NULL,
 budget DECIMAL(15,2) NOT NULL DEFAULT 0,
 status ENUM('planned','active','completed','suspended') NOT NULL DEFAULT 'planned',
 created_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE interventions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 programme_id INT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL,
 intervention_type VARCHAR(100) NOT NULL,
 description TEXT NULL,
 target_count INT UNSIGNED NOT NULL DEFAULT 0,
 unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
 start_date DATE NOT NULL,
 end_date DATE NULL,
 status ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
 FOREIGN KEY(programme_id) REFERENCES programmes(id) ON DELETE CASCADE,
 INDEX idx_intervention_programme(programme_id)
) ENGINE=InnoDB;

CREATE TABLE beneficiary_interventions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 beneficiary_id INT UNSIGNED NOT NULL,
 intervention_id INT UNSIGNED NOT NULL,
 enrollment_date DATE NOT NULL,
 exit_date DATE NULL,
 status ENUM('enrolled','completed','withdrawn','referred') NOT NULL DEFAULT 'enrolled',
 benefit_value DECIMAL(15,2) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 assigned_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_beneficiary_intervention(beneficiary_id,intervention_id),
 FOREIGN KEY(beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
 FOREIGN KEY(intervention_id) REFERENCES interventions(id) ON DELETE CASCADE,
 FOREIGN KEY(assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE indicators (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 programme_id INT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 description TEXT NULL,
 indicator_type ENUM('output','outcome','impact') NOT NULL,
 unit VARCHAR(50) NOT NULL,
 baseline DECIMAL(15,4) NULL,
 target DECIMAL(15,4) NULL,
 frequency ENUM('monthly','quarterly','biannual','annual','event') NOT NULL DEFAULT 'quarterly',
 active TINYINT(1) NOT NULL DEFAULT 1,
 FOREIGN KEY(programme_id) REFERENCES programmes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE indicator_values (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 indicator_id INT UNSIGNED NOT NULL,
 reporting_period DATE NOT NULL,
 value DECIMAL(15,4) NOT NULL,
 evidence_note TEXT NULL,
 recorded_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_indicator_period(indicator_id,reporting_period),
 FOREIGN KEY(indicator_id) REFERENCES indicators(id) ON DELETE CASCADE,
 FOREIGN KEY(recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE assessments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 beneficiary_id INT UNSIGNED NOT NULL,
 intervention_id INT UNSIGNED NULL,
 assessment_date DATE NOT NULL,
 assessor_id INT UNSIGNED NOT NULL,
 household_income DECIMAL(15,2) NULL,
 food_security_score DECIMAL(6,2) NULL,
 education_score DECIMAL(6,2) NULL,
 health_score DECIMAL(6,2) NULL,
 livelihood_score DECIMAL(6,2) NULL,
 overall_score DECIMAL(6,2) NULL,
 narrative TEXT NULL,
 FOREIGN KEY(beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
 FOREIGN KEY(intervention_id) REFERENCES interventions(id) ON DELETE SET NULL,
 FOREIGN KEY(assessor_id) REFERENCES users(id),
 INDEX idx_assessments_beneficiary_date(beneficiary_id,assessment_date)
) ENGINE=InnoDB;

CREATE TABLE documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 beneficiary_id INT UNSIGNED NULL,
 programme_id INT UNSIGNED NULL,
 original_name VARCHAR(255) NOT NULL,
 stored_name VARCHAR(255) NOT NULL UNIQUE,
 mime_type VARCHAR(100) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 uploaded_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
 FOREIGN KEY(programme_id) REFERENCES programmes(id) ON DELETE CASCADE,
 FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 message TEXT NOT NULL,
 type ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notifications_user_read(user_id,read_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 action VARCHAR(80) NOT NULL,
 entity VARCHAR(80) NOT NULL,
 entity_id INT UNSIGNED NOT NULL,
 metadata JSON NULL,
 ip_address VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_audit_entity(entity,entity_id), INDEX idx_audit_created(created_at)
) ENGINE=InnoDB;
