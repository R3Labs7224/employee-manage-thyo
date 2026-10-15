-- Add aadhar_card_document column to employees table
ALTER TABLE employees ADD COLUMN aadhar_card_document VARCHAR(255) NULL AFTER aadhar_number;