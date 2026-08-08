SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','support','financial','client_admin') NOT NULL DEFAULT 'super_admin',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_users_client (client_id),
  INDEX idx_users_role_status (role,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  administrator_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_clients_status (status),
  INDEX idx_clients_search (name, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  full_name VARCHAR(190) NULL,
  code VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  icon VARCHAR(50) NOT NULL DEFAULT 'grid',
  version VARCHAR(30) NOT NULL DEFAULT '0.1.0',
  url VARCHAR(500) NULL,
  admin_url VARCHAR(500) NULL,
  category VARCHAR(100) NULL,
  status ENUM('active','inactive','deploying') NOT NULL DEFAULT 'active',
  display_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_projects_status_order (status,display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  activated_at DATETIME NULL,
  deactivated_at DATETIME NULL,
  settings JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_client_project (client_id,project_id),
  CONSTRAINT fk_cp_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_cp_project FOREIGN KEY (project_id) REFERENCES projects(id),
  INDEX idx_cp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_connections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  module_instance_id VARCHAR(190) NOT NULL UNIQUE,
  protocol_version SMALLINT UNSIGNED NOT NULL,
  endpoint_url VARCHAR(1000) NOT NULL,
  connection_status ENUM('attached','verified','pending','error','revoked') NOT NULL DEFAULT 'attached',
  capabilities_json JSON NOT NULL,
  module_code VARCHAR(100) NULL,
  module_slug VARCHAR(100) NULL,
  module_type VARCHAR(100) NULL,
  module_status VARCHAR(50) NULL,
  api_version VARCHAR(30) NULL,
  health_json JSON NULL,
  compatibility_json JSON NULL,
  minimum_version VARCHAR(30) NULL,
  integration_status VARCHAR(30) NOT NULL DEFAULT 'nao_integrado',
  machine_credential_encrypted TEXT NULL,
  pairing_request_id CHAR(36) NULL,
  integrated_at DATETIME NULL,
  public_key_fingerprint VARCHAR(255) NULL,
  attached_by_user_id BIGINT UNSIGNED NULL,
  attached_at DATETIME NOT NULL,
  last_verified_at DATETIME NULL,
  last_communication_at DATETIME NULL,
  last_module_update_at DATETIME NULL,
  last_error_code VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_connection (project_id,module_instance_id),
  INDEX idx_connection_status (connection_status),
  CONSTRAINT fk_connection_project FOREIGN KEY (project_id) REFERENCES projects(id),
  CONSTRAINT fk_connection_user FOREIGN KEY (attached_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_pairing_receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id CHAR(36) NOT NULL UNIQUE,
  module_uuid VARCHAR(190) NOT NULL,
  pairing_token_hash CHAR(64) NOT NULL,
  module_url VARCHAR(500) NOT NULL,
  status ENUM('processing','validated','confirmed','failed') NOT NULL DEFAULT 'processing',
  error_code VARCHAR(100) NULL,
  received_at DATETIME NOT NULL,
  validated_at DATETIME NULL,
  confirmed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pairing_module_status (module_uuid,status),
  CONSTRAINT fk_pairing_module FOREIGN KEY (module_uuid) REFERENCES project_connections(module_instance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_access_issuances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_uuid VARCHAR(190) NOT NULL,
  jti_hash CHAR(64) NOT NULL UNIQUE,
  issued_by_user_id BIGINT UNSIGNED NOT NULL,
  issued_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  INDEX idx_access_module_expiry (module_uuid,expires_at),
  CONSTRAINT fk_access_module FOREIGN KEY (module_uuid) REFERENCES project_connections(module_instance_id),
  CONSTRAINT fk_access_user FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_identity (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  platform_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id VARCHAR(190) NOT NULL UNIQUE,
  private_key_pem TEXT NOT NULL,
  public_key_pem TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  type ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  result ENUM('success','failure','warning') NOT NULL DEFAULT 'success',
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_logs_created (created_at),
  INDEX idx_logs_action_result (action,result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_updates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  previous_version VARCHAR(30) NOT NULL,
  new_version VARCHAR(30) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  user_id BIGINT UNSIGNED NULL,
  status ENUM('analyzed','waiting','installing','completed','failed','restored') NOT NULL,
  summary TEXT NULL,
  backup_path VARCHAR(500) NULL,
  internal_error TEXT NULL,
  manifest JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_updates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_updates_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration_id VARCHAR(190) NOT NULL UNIQUE,
  version VARCHAR(30) NOT NULL,
  description VARCHAR(500) NULL,
  status ENUM('pending','executed','failed') NOT NULL DEFAULT 'pending',
  executed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempt_ip_date (ip_address,attempted_at),
  INDEX idx_attempt_email_date (email,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
