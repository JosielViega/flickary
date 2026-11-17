# Migrations

`2026_11_17_000016_create_user_social_controls.sql` cria `user_blocks` e `user_mutes` com PKs compostas, índices reversos e duas FKs `ON DELETE CASCADE` em cada tabela. Não existe backfill; self-control é impedido pela aplicação.

Add versioned SQL files using sortable names such as `2026_01_01_000001_create_users.sql`.
Run them once, in filename order, with `composer migrate`. The runner records completed filenames in the `migrations` table. Keep table/column names static and use prepared statements for application data.

MySQL DDL can commit implicitly. Make each migration small and test backups before production changes.

`2026_11_16_000013_create_social_activity_comments.sql` cria os comentários do Feed Social ligados a `watch_history` e `users`, ambos com `ON DELETE CASCADE`, além dos índices de paginação por atividade e autor.

`2026_11_16_000014_create_social_notifications.sql` cria a caixa de notificações sociais com FKs em cascade para recipient, actor, histórico e comentário. A migration faz backfill somente de solicitações de follow ainda pendentes; follows, curtidas e comentários antigos não são retroativos.

`2026_11_17_000015_create_direct_messages.sql` cria um par canônico e único em `direct_conversations` e as mensagens de texto em `direct_messages`. Participantes, sender e conversa usam FKs com `ON DELETE CASCADE`; `read_at` nullable controla não lidas sem duplicar recipient na mensagem.
