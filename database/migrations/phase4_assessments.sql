USE ucsi_benefit_impact;

ALTER TABLE assessments
  ADD COLUMN assessment_type ENUM('baseline','follow_up','midline','endline','exit') NOT NULL DEFAULT 'follow_up' AFTER assessment_date,
  ADD INDEX idx_assessments_type(beneficiary_id, assessment_type, assessment_date);

ALTER TABLE assessments
  ADD CONSTRAINT chk_assessment_scores CHECK (
    (food_security_score IS NULL OR food_security_score BETWEEN 0 AND 100) AND
    (education_score IS NULL OR education_score BETWEEN 0 AND 100) AND
    (health_score IS NULL OR health_score BETWEEN 0 AND 100) AND
    (livelihood_score IS NULL OR livelihood_score BETWEEN 0 AND 100) AND
    (overall_score IS NULL OR overall_score BETWEEN 0 AND 100)
  );
