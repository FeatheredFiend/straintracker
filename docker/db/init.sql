-- Runs once, on first start of an empty database volume. The app database
-- itself comes from MYSQL_DATABASE; this adds the PHPUnit one (Doctrine's
-- when@test config appends "_test" to the database name).
CREATE DATABASE IF NOT EXISTS straintracker_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON straintracker_test.* TO 'symfony'@'%';
