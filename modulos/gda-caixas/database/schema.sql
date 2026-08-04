CREATE TABLE IF NOT EXISTS companies (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,gda_client_id VARCHAR(100) NULL UNIQUE,name VARCHAR(160) NOT NULL,identifier VARCHAR(100) NOT NULL UNIQUE,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,company_id INT UNSIGNED NULL,name VARCHAR(120) NOT NULL,email VARCHAR(160) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role VARCHAR(40) NOT NULL DEFAULT 'client_admin',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_users_company(company_id),FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cycles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,company_id INT UNSIGNED NULL,name VARCHAR(160) NOT NULL,modality ENUM('DIARIO','SEMANAL','QUINZENAL','MENSAL','PERSONALIZADO') NOT NULL DEFAULT 'DIARIO',start_date DATE NULL,year SMALLINT NOT NULL,month TINYINT NOT NULL,daily_amount DECIMAL(10,2) NOT NULL,admin_pays TINYINT(1) NOT NULL DEFAULT 0,status ENUM('active','archived','closed') NOT NULL DEFAULT 'active',notes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_cycles_company(company_id),FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS participants (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,company_id INT UNSIGNED NULL,name VARCHAR(160) NOT NULL,phone VARCHAR(40) NULL,email VARCHAR(160) NULL,pix_type VARCHAR(30) NULL,pix_key VARCHAR(255) NULL,notes TEXT NULL,access_token VARCHAR(64) NULL UNIQUE,active TINYINT(1) DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_participants_company(company_id),FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS app_settings (
 company_id INT UNSIGNED NOT NULL,setting_key VARCHAR(100) NOT NULL,setting_value TEXT NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(company_id,setting_key),INDEX idx_app_settings_company(company_id),FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mobile_access_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,company_id INT UNSIGNED NOT NULL,ip_address VARCHAR(64) NOT NULL,user_agent VARCHAR(500) NULL,last_seen DATETIME NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_mobile_access_logs_company(company_id),UNIQUE KEY uniq_mobile_company_ip(company_id,ip_address),FOREIGN KEY(company_id) REFERENCES companies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cycle_participants (
 cycle_id INT UNSIGNED NOT NULL,participant_id INT UNSIGNED NOT NULL,joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,access_token_hash CHAR(64) NULL,access_token_prefix VARCHAR(12) NULL,access_enabled TINYINT(1) NOT NULL DEFAULT 0,access_created_at DATETIME NULL,access_rotated_at DATETIME NULL,last_access_at DATETIME NULL,PRIMARY KEY(cycle_id,participant_id),UNIQUE KEY uniq_cycle_participant_access_hash(access_token_hash),INDEX idx_cycle_participant_access_enabled(access_enabled,last_access_at),FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cycle_points (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cycle_id INT UNSIGNED NOT NULL,day TINYINT NOT NULL,participant_id INT UNSIGNED NULL,UNIQUE KEY uniq_cycle_day(cycle_id,day),FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS contributions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cycle_id INT UNSIGNED NOT NULL,participant_id INT UNSIGNED NOT NULL,amount DECIMAL(10,2) NOT NULL,paid_at DATETIME NOT NULL,notes VARCHAR(255) NULL,created_by INT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(cycle_id) REFERENCES cycles(id),FOREIGN KEY(participant_id) REFERENCES participants(id),FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS payouts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cycle_id INT UNSIGNED NOT NULL,point_day TINYINT NOT NULL,participant_id INT UNSIGNED NOT NULL,amount DECIMAL(10,2) NOT NULL,status ENUM('pending','paid') DEFAULT 'pending',paid_at DATETIME NULL,notes VARCHAR(255) NULL,created_by INT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uniq_payout(cycle_id,point_day),FOREIGN KEY(cycle_id) REFERENCES cycles(id),FOREIGN KEY(participant_id) REFERENCES participants(id),FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS message_templates (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cycle_id INT UNSIGNED NULL,code VARCHAR(60) NOT NULL,name VARCHAR(120) NOT NULL,body TEXT NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uniq_template_cycle_code(cycle_id,code),FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS message_queue (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cycle_id INT UNSIGNED NOT NULL,participant_id INT UNSIGNED NOT NULL,template_code VARCHAR(60) NOT NULL,message_text TEXT NOT NULL,status ENUM('waiting','sent','cancelled','failed') NOT NULL DEFAULT 'waiting',scheduled_at DATETIME NULL,sent_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cycle_id INT UNSIGNED NOT NULL,
 participant_id INT UNSIGNED NOT NULL,
 amount DECIMAL(10,2) NOT NULL,
 paid_at DATETIME NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 stored_name VARCHAR(255) NOT NULL UNIQUE,
 mime_type VARCHAR(100) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 notes VARCHAR(500) NULL,
 status ENUM('pending','approved','rejected','resubmit') NOT NULL DEFAULT 'pending',
 review_notes VARCHAR(500) NULL,
 contribution_id BIGINT UNSIGNED NULL,
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_receipt_cycle_status(cycle_id,status),
 INDEX idx_receipt_participant(participant_id),
 FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,
 FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE,
 FOREIGN KEY(contribution_id) REFERENCES contributions(id) ON DELETE SET NULL,
 FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
