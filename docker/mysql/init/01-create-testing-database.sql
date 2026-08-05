-- The test suite runs against MySQL, not SQLite: this schema depends on MySQL
-- specifics (FULLTEXT, enum columns, ascii ULID columns, index selection), and
-- testing on a different engine than production is how engine-specific bugs ship.
CREATE DATABASE IF NOT EXISTS brator_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON brator_testing.* TO 'brator'@'%';
FLUSH PRIVILEGES;
