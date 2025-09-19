-- Modify salaries table to add new salary components
ALTER TABLE salaries 
ADD COLUMN hra DECIMAL(10,2) DEFAULT 0.00 AFTER basic_salary,
ADD COLUMN other_allowances DECIMAL(10,2) DEFAULT 0.00 AFTER hra,
ADD COLUMN gross_salary DECIMAL(10,2) DEFAULT 0.00 AFTER other_allowances,
ADD COLUMN special_allowance DECIMAL(10,2) DEFAULT 0.00 AFTER gross_salary,
ADD COLUMN total_salary DECIMAL(10,2) DEFAULT 0.00 AFTER special_allowance,

-- Deduction columns
ADD COLUMN epf_employee DECIMAL(10,2) DEFAULT 0.00 AFTER total_salary,
ADD COLUMN esi_employee DECIMAL(10,2) DEFAULT 0.00 AFTER epf_employee,
ADD COLUMN epf_employer DECIMAL(10,2) DEFAULT 0.00 AFTER esi_employee,
ADD COLUMN esi_employer DECIMAL(10,2) DEFAULT 0.00 AFTER epf_employer,
ADD COLUMN professional_tax DECIMAL(10,2) DEFAULT 0.00 AFTER esi_employer,
ADD COLUMN tds DECIMAL(10,2) DEFAULT 0.00 AFTER professional_tax,
ADD COLUMN gratuity DECIMAL(10,2) DEFAULT 0.00 AFTER tds,
ADD COLUMN ghi DECIMAL(10,2) DEFAULT 0.00 AFTER gratuity,
ADD COLUMN variable_bonus DECIMAL(10,2) DEFAULT 0.00 AFTER ghi;