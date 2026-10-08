-- Runs once, only when the mysql data volume is created for the first time.
-- The database itself is created by MYSQL_DATABASE in docker-compose.yml; this
-- file makes the collation explicit and is the place to add seed data.
--
-- NOTE: the name below is hard-coded to match the compose default. If you
-- override DB_NAME, change it here too (or the statement fails harmlessly).
--
-- To re-run it: docker compose down -v && docker compose up -d

ALTER DATABASE `simple`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- The application account lives in .env (root/root) for local development, so
-- nothing else is granted here. Add GRANT statements if you introduce a
-- dedicated user for the app.
