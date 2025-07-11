-- Add approval columns to patients table
ALTER TABLE patients
ADD COLUMN is_approved TINYINT(1) DEFAULT 0,
ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL;

-- Update existing patients
UPDATE patients p
JOIN users u ON p.user_id = u.user_id
SET p.is_approved = CASE WHEN u.address IS NOT NULL AND u.address != '' THEN 1 ELSE 0 END,
    p.approved_at = CASE WHEN u.address IS NOT NULL AND u.address != '' THEN CURRENT_TIMESTAMP ELSE NULL END; 