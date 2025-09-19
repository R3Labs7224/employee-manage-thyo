-- Setup Expenses System (Transform Petty Cash to Expenses)
-- This script migrates the petty cash system to a robust expenses system

-- 1. Create expense categories table
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Insert default expense categories
INSERT IGNORE INTO expense_categories (name, description) VALUES
('Food', 'Food and meal expenses'),
('Fuel', 'Vehicle fuel and transportation costs'),
('Travelling', 'Travel and accommodation expenses'),
('Office Supplies', 'Stationery and office materials'),
('Equipment', 'Tools and equipment purchases'),
('Maintenance', 'Repairs and maintenance costs'),
('Communication', 'Phone, internet, and communication expenses'),
('Other', 'Miscellaneous expenses not covered by other categories');

-- 3. Backup existing petty cash data (optional)
CREATE TABLE IF NOT EXISTS petty_cash_backup AS
SELECT * FROM petty_cash_requests;

-- 4. Rename petty_cash_requests to expenses
RENAME TABLE petty_cash_requests TO expenses;

-- 5. Add new columns to expenses table
ALTER TABLE expenses
ADD COLUMN category_id INT AFTER amount,
ADD COLUMN task_id INT AFTER category_id,
ADD COLUMN receipt_number VARCHAR(100) AFTER receipt_image,
ADD CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
ADD CONSTRAINT fk_expenses_task FOREIGN KEY (task_id) REFERENCES tasks(id);

-- 6. Create indexes for better performance
CREATE INDEX idx_expenses_category ON expenses(category_id);
CREATE INDEX idx_expenses_task ON expenses(task_id);
CREATE INDEX idx_expenses_employee_date ON expenses(employee_id, request_date);
CREATE INDEX idx_expenses_status ON expenses(status);

-- 7. Set default category for existing records (Other category)
UPDATE expenses
SET category_id = (SELECT id FROM expense_categories WHERE name = 'Other' LIMIT 1)
WHERE category_id IS NULL;

-- 8. Make category_id required for future records
ALTER TABLE expenses
MODIFY COLUMN category_id INT NOT NULL;

-- Display table structure
DESCRIBE expenses;
SELECT * FROM expense_categories;