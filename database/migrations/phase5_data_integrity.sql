USE ucsi_benefit_impact;

-- Phase 5: enforce common data-integrity constraints without redesigning existing entities.
ALTER TABLE beneficiaries
  ADD INDEX idx_beneficiaries_phone(phone),
  ADD INDEX idx_beneficiaries_email(email),
  ADD INDEX idx_beneficiaries_identity(first_name,last_name,date_of_birth);

ALTER TABLE programmes
  ADD UNIQUE KEY uq_programmes_code(code);

ALTER TABLE indicators
  ADD INDEX idx_indicators_programme_active(programme_id,active);

ALTER TABLE beneficiary_interventions
  ADD INDEX idx_beneficiary_interventions_status(beneficiary_id,status,intervention_id);

ALTER TABLE assessments
  ADD INDEX idx_assessments_beneficiary_date(beneficiary_id,assessment_date);
