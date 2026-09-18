# Changelog

Todas as mudanças relevantes deste projeto serão documentadas aqui.

As versões `0.1.0` a `0.3.0` abaixo registram a evolução da fundação técnica herdada do `modeloPHP`, anterior à identidade própria do Flickary.

## [Unreleased]

### Changed

- cards da busca agora levam às rotas públicas reais de filmes e séries
- identidade técnica, configuração ativa, documentação e home inicial adaptadas para Flickary
- layout base ajustado para português do Brasil e navegação mínima do produto
- validação do mirror diferencia código instalado pelo Composer de arquivos sensíveis da aplicação

### Removed

- formulário e rota demonstrativos `POST /example` do starter

### Added

- páginas públicas de detalhes básicos de filmes e séries TMDB, com hero cinematográfico, gêneros, metadados, pôster e backdrop
- busca pública de filmes e séries no TMDB, com seções separadas, filtros, paginação específica, pôsteres responsivos e estados de falha seguros
- rota pública `/sobre`, atribuição obrigatória e asset oficial do TMDB, além de acesso real à busca pela topbar e navegação desktop/mobile
- fundação interna da integração TMDB API v3 com Bearer server-side, cliente HTTP seguro, configuração de imagens e normalização de Movie e TV/Series
- fundação visual Cinematic Dark — Deep Blue com tokens, shell responsivo e navegação mobile/desktop
- Home conceitual baseada em Passado · Presente · Futuro e página 404 integrada à identidade
- fundação persistente de contas com usuário interno, perfil 1:1 e identidades externas desacopladas
- núcleo de autenticação por sessão independente de provedores, com rotação de ID no login e logout
- Google Login server-side com onboarding de username, criação transacional de conta e logout protegido por CSRF
- perfil próprio autenticado com identidade visual cinematográfica, edição de display name, bio e preferência de privacidade
- Facebook Login server-side com Graph API v26.0, state de uso único, appsecret_proof e onboarding compartilhado entre provedores
- conexão segura de Facebook à conta autenticada, com idempotência, detecção de conflito e seção de contas conectadas no perfil
- Guia permanente `docs/PLANO_DE_ESTUDO_E_REVISAO.md` para estudo ativo, revisão por fluxo e evolução segura do modelo.
- Atalho no README para iniciar o estudo estruturado do projeto.

## [0.3.0] - 2026-09-08

### Added

- registro persistente e concorrente de portas em `~/.modeloPHP/ports.json`
- reservas por caminho absoluto normalizado do projeto
- comandos `composer port:status` e `composer port:release`
- limpeza conservadora de reservas claramente antigas
- testes de colisões entre projetos, corrupção e idempotência

## [0.2.0] - 2026-09-08

### Added

- `composer setup` com preservação de `.env` e seleção automática de porta livre
- builder portátil `composer deploy:hostgator`
- manifesto allowlist e validações contra secrets/configurações do servidor
- `vendor` exclusivo de produção no mirror gerado
- documentação de primeira instalação e atualizações HostGator/cPanel
- validação do mirror na CI

## [0.1.0] - 2026-09-08

### Added

- Composer obrigatório e autoload PSR-4
- bootstrap e configuração por ambiente
- Router, Request, Response, Controllers, Views e layout
- PDO e estrutura para Repositories e Services
- Validation, CSRF, Sessions, flash messages e escape HTML
- Error Handler e logs
- migrations SQL e diretório de seeds
- testes, lint e comando de validação completa
- CI do GitHub
- documentação de arquitetura, segurança e desenvolvimento local
- porta local independente e configurável
