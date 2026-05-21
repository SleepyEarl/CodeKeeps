-- CodeKeep sample data
USE codekeep;

INSERT INTO users (name, email, password, created_at) VALUES
('Sample Student', 'student@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaFgeka2preuZGQYix0cDl2Q3a.', NOW());

INSERT INTO folders (user_id, name, parent_id, created_at) VALUES
(1, 'Projects', NULL, NOW()),
(1, 'Photos', NULL, NOW());

INSERT INTO activity_logs (user_id, action, target_type, target_name, created_at) VALUES
(1, 'Created sample folders', 'folder', 'Projects', NOW()),
(1, 'Created sample folders', 'folder', 'Photos', NOW());

-- Use password: password
