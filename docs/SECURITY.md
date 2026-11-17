# Segurança

Estas regras são requisitos do projeto, não sugestões:

1. Nunca versionar secrets, credenciais, certificados privados ou dados reais.
2. Usar `.env` local e manter apenas `.env.example` no Git.
3. Nunca armazenar senha em texto puro.
4. Gerar hashes de senha com `password_hash()`.
5. Conferir senhas com `password_verify()`.
6. Usar prepared statements PDO para valores.
7. Nunca concatenar entrada em SQL; tabela, coluna e `ORDER BY` dinâmicos exigem whitelist.
8. Escapar saída HTML dinâmica com `e()` por padrão.
9. Exigir CSRF em toda ação que muda estado.
10. Validar autenticação e autorização no backend para cada recurso.
11. Validar upload por erro, limite de tamanho, MIME real via `finfo`, extensão permitida, nome aleatório e destino.
12. Nunca confiar apenas em validação JavaScript.
13. Não expor exceptions, stack traces, SQL ou caminhos internos em produção.
14. Usar cookies HttpOnly, SameSite e Secure sob HTTPS.
15. Não usar GET para criar, alterar ou excluir dados.
16. Instalar dependências PHP somente via Composer e manter um único `vendor/`.
17. Atualizar dependências conscientemente, revisar changelogs e versionar `composer.lock`.
18. Não expor `.env`; o Document Root deve ser `public/`.
19. Não versionar logs e evitar dados pessoais ou secrets neles.
20. Não versionar uploads de usuários e impedir execução de scripts no diretório.

## Sessão e autenticação

O núcleo de autenticação guarda na sessão somente o ID interno positivo de `users`. Ele não conhece provedores externos. Login e logout regeneram o identificador da sessão; logout remove apenas o estado autenticado, preservando CSRF e flash messages da sessão atual. Não guardar senha, segredo externo ou token reutilizável em cookie. Um futuro “remember me” deve usar token aleatório, armazenado de forma segura, com expiração e revogação.

Google e Facebook validam a identidade externa no servidor antes de estabelecer a sessão Flickary.

## Contas e identidades externas

A conta interna e as identidades externas permanecem separadas no banco. A fundação persiste somente o provedor e seu identificador de usuário; não armazena tokens OAuth, códigos, respostas brutas ou perfis sociais. A vinculação exige sessão autenticada, CSRF no início e estado OAuth vinculado ao mesmo usuário. Igualdade de e-mail, nome ou avatar nunca vincula contas.

## Google Login

Google Identity Services envia a credential por POST. Antes de verificar o ID token, o backend exige correspondência em tempo constante entre o cookie e o campo `g_csrf_token`. A biblioteca oficial valida assinatura, audience, issuer e expiração; somente o claim `sub` identifica a conta Google.

O ID token nunca é persistido ou registrado. O pending onboarding guarda apenas `sub`, e-mail verificado quando disponível, nome, avatar HTTPS e instante de criação, expirando em dez minutos. E-mails iguais não vinculam contas automaticamente. A criação de `users`, `user_profiles` e `user_external_identities` ocorre em uma única transação, com as constraints do banco como garantia final.

## Facebook Login

O Facebook usa Authorization Code Flow server-side na Graph API `v26.0`. O `state` contém 256 bits aleatórios, é armazenado na sessão somente como SHA-256, expira em dez minutos e é consumido uma única vez, inclusive em tentativas inválidas. No fluxo de conexão, o estado registra também a intenção e o ID do usuário autenticado; o callback recusa sessões ausentes ou alteradas.

O código é trocado no backend com timeout curto, validação TLS habilitada, redirects desabilitados, limite de resposta e tratamento fechado para status ou JSON inesperado. O token fica somente em memória durante a requisição, segue no header `Authorization` da consulta de perfil e nunca vai para sessão, banco ou logs. A consulta pede apenas `id,name,picture` e inclui `appsecret_proof` calculado por HMAC-SHA256.

O e-mail do Facebook é sempre tratado como ausente/não verificado, mesmo que o provedor o envie. Somente o `id` retornado pelo endpoint versionado identifica a conta. As constraints únicas `(provider, provider_user_id)` e `(user_id, provider)` são a garantia final contra corridas e duplicidade de conexão.

## Produção

Configure `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE=true`, HTTPS e permissões mínimas. O usuário recebe erro genérico com referência; detalhes ficam em `storage/logs`. Proteja também logs e backups no servidor.

## TMDB

O `TMDB_READ_ACCESS_TOKEN` é um segredo exclusivamente server-side. O cliente usa Bearer sobre HTTPS com validação TLS, host base fixo e redirects desabilitados. Token e header `Authorization` não podem ser enviados ao navegador, persistidos no banco, incluídos em exceptions ou registrados em logs.

As operações públicas do cliente representam endpoints TMDB conhecidos; entrada HTTP não escolhe host nem URL completa. Respostas possuem limite de tamanho e falham de forma controlada. Erros podem registrar futuramente somente path, status HTTP e categoria, nunca headers ou respostas integrais.

`is_anime` nunca é aceito como decisão do navegador. Novos snapshots recebem a classificação exclusivamente de detalhes TMDB validados no servidor pelo `TmdbAnimeClassifier`; updates locais preservam o valor congelado. O backfill Anime é exclusivamente CLI, respeita `source = tmdb`, não altera ownership e não imprime credenciais.

## Minha Lista

As mutations exigem autenticação e CSRF, e o ownership deriva exclusivamente de `Auth::id()`. IDs externos são validados antes de qualquer consulta, e UPDATE/DELETE sempre incluem `user_id`. O browser envia apenas estado e CSRF: títulos, imagens, provider, tipo e usuário do snapshot vêm do servidor. O repository usa prepared statements e a constraint única é a defesa final contra double-submit.

Mutations de progresso de séries seguem o mesmo ownership e CSRF. Uma nova marcação só aceita episódios presentes no payload de temporada consultado server-side; números enviados na URL não bastam. Queries de progresso sempre incluem o usuário autenticado, e a constraint única torna marcações repetidas idempotentes.

## Favoritos e avaliações pessoais

As mutations exigem autenticação e CSRF; ownership deriva exclusivamente de `Auth::id()` e todas as leituras e escritas incluem `user_id`. A rota fornece a identidade TMDB, e o browser controla somente `favorite` com allowlist exata e `rating` inteira de 1 a 10 ou vazia. Título, imagem, tipo, usuário e classificação Anime do primeiro snapshot vêm de detalhes server-side. A constraint única protege contra double-submit, e preferências existentes são editadas localmente sem recalcular o snapshot.

## Histórico de visualização

Criação, correção de data e remoção exigem autenticação e CSRF. Ownership vem exclusivamente de `Auth::id()` e toda busca, atualização ou exclusão inclui `user_id`; recursos de outro usuário respondem como não encontrados. O browser controla apenas `watched_on` e a `request_key` técnica: provider, tipo, IDs, títulos, episódio, imagem e usuário são validados ou obtidos server-side.

Datas usam o formato estrito `YYYY-MM-DD`, calendário real e nunca podem estar no futuro. A chave aleatória de 32 caracteres hexadecimais é única por usuário e evita double-submit sem impedir reassistidas com chaves novas. Snapshots persistidos continuam sendo dados externos não confiáveis e são escapados na view. `duration_minutes` nunca é aceito do browser: ele é normalizado e congelado exclusivamente a partir dos detalhes TMDB obtidos server-side.

## Agenda pessoal

Criação, reagendamento e remoção exigem autenticação e CSRF; ownership deriva exclusivamente de `Auth::id()` e UPDATE/DELETE incluem `user_id`. O browser controla somente `scheduled_on`: identidade, títulos e imagem vêm da rota e do catálogo server-side. Datas são reais, estritas e iguais ou posteriores ao dia atual; snapshots continuam não confiáveis e são escapados na view.

## Estatísticas pessoais e backfill

As estatísticas exigem autenticação, aceitam somente GET e aplicam `user_id` em todas as leituras. Elas não recebem IDs de recurso do browser, não alteram estado e não consultam serviços externos. Durações desconhecidas não são estimadas nem ocultadas: a interface identifica o total como parcial.

O comando manual de backfill lê a credencial TMDB apenas da configuração server-side, nunca a imprime e encerra de forma controlada diante de falhas de autenticação, limite ou rede. Ele só preenche durações nulas, usa prepared statements e não modifica identidade, data ou snapshot de qualquer evento.

## Perfis públicos e seguidores

Perfis são resolvidos somente por `users.username` validado pela política canônica; consultas públicas não selecionam e-mail, IDs de provedores, tokens ou estado de sessão. Avatar e capa aceitam apenas URL HTTPS válida e limitada. Todo conteúdo persistido é escapado na renderização.

Follow e unfollow são POST protegidos por CSRF. O follower sempre vem de `Auth::id()` e o target é resolvido pelo username da rota, nunca por ID enviado pelo navegador. Self-follow é recusado e duplicatas são idempotentes. A privacidade é aplicada no servidor: seguir um perfil privado não libera favoritos, coleção, avaliações, histórico, progresso, Agenda ou estatísticas pessoais.

## Compartilhamento e metadados sociais

Canonical e URLs compartilháveis são construídas exclusivamente a partir de `APP_URL`; `Host`, `X-Forwarded-Host`, query string e `window.location` não participam da decisão. Todos os campos Open Graph são escapados como atributos HTML, descrições viram texto simples limitado e imagens aceitam somente URLs HTTP/HTTPS já validadas.

Perfis privados publicam descrição genérica sem bio protegida, favoritos ou estatísticas. Metadados de mídia não recebem estado pessoal do visitante. A URL compartilhada é a própria rota pública, sem ID interno de usuário, token, assinatura ou tracking; nenhuma credencial é enviada ao navegador e nenhuma ação de share cria persistência ou cookie.

## Uploads futuros

A fundação apenas prepara `public/uploads` e bloqueia extensões PHP via Apache. Antes de aceitar arquivos, imponha tamanho máximo, use `finfo` no conteúdo, mapeie MIME a extensões permitidas, gere nomes com `random_bytes`, impeça sobrescrita e prefira armazenamento fora do Document Root quando downloads puderem passar por autorização.

## Processos periódicos

Tarefas críticas não devem rodar durante uma visita HTTP. Use cron chamando script CLI dedicado quando esse requisito surgir.

## Mirror de produção

O builder HostGator usa allowlist e falha se detectar configurações do servidor, secrets, certificados ou diretórios de dados no mirror. `.env`, `.htaccess`, uploads, logs e cache permanecem próprios de cada instalação. O builder não transmite arquivos e não executa migrations.

## Privacidade social

- A migration social converte follows legados de perfis privados em pendências para impedir escalada silenciosa de acesso.
- Solicitação pendente nunca é critério de autorização; somente um follow aceito libera os agregados da página pública.
- Accept/reject usam o usuário autenticado como target e o username validado da rota como requester; IDs do POST são ignorados.
- Todas as mutations sociais exigem CSRF e usam PRG.
- Curtir exige autenticação, CSRF, ID interno positivo e a mesma visibilidade do Feed Social; atividade inexistente ou oculta responde 404.
- A autorização e o INSERT da curtida ocorrem na mesma operação SQL e não consultam o TMDB. Descurtir remove somente a linha do usuário autenticado, inclusive após perda de visibilidade.
- Detalhe e criação de comentário exigem autenticação, ID interno positivo e a mesma visibilidade temporal do Feed Social; atividade inexistente ou oculta responde 404.
- Comentários aceitam somente texto UTF-8 simples, normalizado e limitado a 500 caracteres. NUL, controles inválidos e UTF-8 inválido são rejeitados; toda saída é escapada.
- Criar e remover exigem CSRF. O `INSERT ... SELECT` revalida a autorização atomicamente; remover é permitido somente ao autor do comentário ou ao proprietário da atividade e redirects usam uma allowlist de contextos internos.
- O histórico privado permite ao autor apagar seu comentário após unfollow ou perda de visibilidade sem reabrir o conteúdo social oculto.
- `/perfil/comentarios` exige autenticação. O detalhe usa apenas snapshots locais e, assim como o feed, não executa chamadas de detalhes ao TMDB.
- Metadata de perfil privado é genérica até para owner e follower aprovado.
- Agenda, histórico detalhado e lista completa não são expostos pela autorização social.

## Notificações sociais

- `/notificacoes` é privada e toda leitura ou mutation filtra pelo `recipient_user_id` autenticado; IDs alheios respondem 404.
- Abrir e marcar todas como lidas exigem POST e CSRF. O destino é derivado no servidor de tipo e IDs persistidos, nunca de POST, query, Referer ou Host.
- A tabela não persiste mensagem, HTML, URL ou payload. A UI monta texto conhecido por enum e escapa username e display name.
- As consultas selecionam somente identidade pública do ator, sem e-mail, provider, token ou sessão; recursos ocultos não são revelados.
- Publicação, caixa e badge são locais e não consultam o TMDB. Não existem push, e-mail, polling, WebSocket ou SSE.

## Mensagens privadas

- Caixa, conversa e mutations exigem autenticação. O sender vem exclusivamente de `Auth::id()` e o target do username canônico da rota; IDs enviados pelo navegador não selecionam usuário ou conversa.
- Novo envio exige follows aceitos nas duas direções. A verificação e a escrita usam a mesma transação com lock das relações; pending e follow unilateral nunca autorizam.
- A leitura exige participação no par canônico. Histórico existente permanece acessível após unfollow, mas somente em leitura.
- Envio e marcação de leitura exigem POST e CSRF; GET nunca altera `read_at`. Nenhum redirect arbitrário é aceito.
- Mensagens são texto simples UTF-8 limitado a 2.000 caracteres, sem HTML, Markdown ou auto-link, e toda renderização é escapada.
- A funcionalidade é local, não consulta TMDB e não cria `social_notifications`. Não há anexos, realtime, polling, push ou e-mail.
