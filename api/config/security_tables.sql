-- Security tables for CitaClick
-- Run this migration on your production database

-- Rate limiting (hybrid banking-style)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_rate_lookup (identifier, action, created_at),
    INDEX idx_rate_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Token blacklist (logout invalidation)
CREATE TABLE IF NOT EXISTS token_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE INDEX idx_token_hash (token_hash),
    INDEX idx_token_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
