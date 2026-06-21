UPDATE users 
SET password_hash = '$2y$10$qFJjn8XB/DIA2y/y.7onUewpZ8kHXQHgcD5yr4R0cYASQ5donlo4y'
WHERE email = 'admin@books.test';

UPDATE users 
SET password_hash = '$2y$10$sTNhOVxbq3By.2VbTYmHVejwTRKmClhXIifhKlfCrHMcbNRF3FwRm'
WHERE email = 'member@books.test';