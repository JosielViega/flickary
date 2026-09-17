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

## Uploads futuros

A fundação apenas prepara `public/uploads` e bloqueia extensões PHP via Apache. Antes de aceitar arquivos, imponha tamanho máximo, use `finfo` no conteúdo, mapeie MIME a extensões permitidas, gere nomes com `random_bytes`, impeça sobrescrita e prefira armazenamento fora do Document Root quando downloads puderem passar por autorização.

## Processos periódicos

Tarefas críticas não devem rodar durante uma visita HTTP. Use cron chamando script CLI dedicado quando esse requisito surgir.

## Mirror de produção

O builder HostGator usa allowlist e falha se detectar configurações do servidor, secrets, certificados ou diretórios de dados no mirror. `.env`, `.htaccess`, uploads, logs e cache permanecem próprios de cada instalação. O builder não transmite arquivos e não executa migrations.
