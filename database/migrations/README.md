# Migrations

Add versioned SQL files using sortable names such as `2026_01_01_000001_create_users.sql`.
Run them once, in filename order, with `composer migrate`. The runner records completed filenames in the `migrations` table. Keep table/column names static and use prepared statements for application data.

MySQL DDL can commit implicitly. Make each migration small and test backups before production changes.

`2026_11_16_000013_create_social_activity_comments.sql` cria os comentários do Feed Social ligados a `watch_history` e `users`, ambos com `ON DELETE CASCADE`, além dos índices de paginação por atividade e autor.
