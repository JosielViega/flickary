# Changelog

## Etapa 33 — Polimento visual da Home

- A Home agora abre com destaque cinematográfico real, jornada visual e prateleiras de tendências e lançamentos do TMDB.
- Contas autenticadas recebem atalhos compactos para itens em andamento, agenda e histórico usando apenas snapshots locais.
- Falhas do catálogo são isoladas e preservam uma Home funcional, sem manifesto institucional ou grandes estados vazios.

## Etapa 32 — Polimento visual da experiência de mídia

- Detalhes de filmes e séries ganharam hero cinematográfico, ações progressivas, temporadas visuais e uma prateleira contextual resiliente.
- Temporadas agora exibem progresso compacto e episódios com stills do TMDB, estados assistido/próximo e ações sob demanda.
- A configuração de imagens TMDB passou a aceitar `still_sizes` sem quebrar payloads legados.

Todas as mudanças relevantes deste projeto serão documentadas aqui.

As versões `0.1.0` a `0.3.0` abaixo registram a evolução da fundação técnica herdada do `modeloPHP`, anterior à identidade própria do Flickary.

## [Unreleased]

### Navigation and performance

- Navegação interna progressiva com App Shell persistente, History API e fallback para HTML completo.
- Cache curto de fragments somente em memória, indicador de carregamento e proteção contra requests concorrentes.
- Cache TMDB fresh/stale para detalhes e temporadas, mantendo busca textual sem cache.
- Instrumentação `Server-Timing` e cache imutável versionado para assets estáticos.

### Content discovery

- Busca pública agora alterna entre pesquisa textual e Descoberta, com filtros para Todos, Filmes, Séries e Anime.
- Prateleiras TMDB de tendências, lançamentos, populares, melhores do ano, Anime e próximos lançamentos.
- Cache filesystem fresh/stale para coleções públicas TMDB, com fallback transitório e circuit breaker por request.

### Reporting and moderation

- Suspensões administrativas temporárias de 24h, 7d ou 30d, com invalidação da sessão e bloqueio de login OAuth.
- Reativação antecipada auditada e expiração automática de suspensões.
- Remoção administrativa transacional somente de comentários denunciados.
- Trilha paginada de ações de moderação com snapshots preservados.
- Denúncias privadas de perfil, comentário e mensagem recebida, com snapshot textual e deduplicação enquanto pendentes.
- Histórico privado em `/perfil/denuncias`, fila administrativa paginada e revisão como resolvida ou arquivada.
- Administrador persistido por `users.is_admin` e provisionado somente pelo CLI `bin/admin.php`, sem promoção web.
- Revisão não aplica sanção, não remove conteúdo e não notifica a pessoa denunciada.

### Social controls

- Bloqueio bilateral de usuários, gerenciamento de bloqueados e integração com follows, perfis, busca, interações e mensagens.
- Silenciamento unilateral de atividades no Feed Social; Block é fronteira de autorização e Mute é somente preferência de feed.

### Added

- Mensagens privadas 1:1 para follows mútuos, com caixa paginada, histórico e texto simples
- contagens de não lidas por conversa e badge global de mensagens
- histórico somente leitura após unfollow e reativação da mesma conversa após re-follow
- Notificações internas para solicitações, novos seguidores, aceites, curtidas e comentários
- badge global de notificações não lidas e caixa privada paginada com marcação individual ou em lote
- Comentários de texto simples nas atividades do Feed Social, com detalhe privado, paginação, moderação e histórico pessoal
- Curtidas em atividades do Feed Social, com contagem, estado pessoal e autorização pela mesma visibilidade do feed
- Feed Social cronológico de visualizações reais, derivado de `watch_history` e ativado na navegação autenticada
- opt-in privado de atividade, com cutoff de consentimento sem publicação retroativa
- cutoff de início do follow aprovado, excluindo backlog e solicitações pendentes

- solicitações para seguir perfis privados, com aceitar, recusar, cancelar e acesso aprovado ao conteúdo público do perfil
- área paginada de solicitações recebidas e contador no perfil autenticado
- migration segura que converte follows sociais antigos de targets privados em solicitações pendentes

- compartilhamento nativo progressivo de perfis e títulos, com copiar link e fallback manual acessível
- URLs canônicas derivadas de `APP_URL` e prévias Open Graph server-side com imagem Flickary estática

- perfil público cinematográfico por username, com agregados locais e até seis favoritos
- seguidores e seguindo com mutations CSRF, idempotência, contadores e páginas paginadas
- busca pública local de usuários por username e nome de exibição

### Security

- Envio de mensagem exige autenticação, CSRF e follows aceitos nas duas direções, revalidados dentro da transação
- sender deriva da sessão, target do username da rota e todo corpo é renderizado como texto escapado
- Leitura e criação de comentários reutilizam os cutoffs do Feed Social; recursos ocultos e remoções não autorizadas respondem 404
- Criação e remoção de comentários exigem autenticação e CSRF, com HTML sempre escapado e redirects limitados a contextos internos conhecidos
- pending nunca autoriza conteúdo privado; somente um follow aceito libera a página pública do perfil
- accept e reject derivam o target de `Auth::id()`, resolvem requester pela rota e exigem CSRF
- metadata social de perfil privado permanece genérica e independente do viewer

### Changed

- cards da busca agora levam às rotas públicas reais de filmes e séries
- identidade técnica, configuração ativa, documentação e home inicial adaptadas para Flickary
- layout base ajustado para português do Brasil e navegação mínima do produto
- validação do mirror diferencia código instalado pelo Composer de arquivos sensíveis da aplicação
- páginas de detalhes autenticadas agora permitem adicionar, atualizar e remover itens da Minha Lista

### Removed

- formulário e rota demonstrativos `POST /example` do starter

### Added

- favoritos privados e avaliação pessoal inteira de 1 a 10, independentes da Minha Lista
- páginas locais e paginadas de Favoritos e Avaliações, com filtros Filme, Série e Anime
- métricas de favoritos, títulos avaliados e nota média pessoal nas Estatísticas
- classificação Anime persistida como snapshot nullable em Minha Lista, Histórico e Agenda
- filtros locais Anime nas três áreas pessoais e métricas Anime com cobertura transparente nas Estatísticas
- backfill manual e idempotente de snapshots antigos, com deduplicação global de Movies e Series
- descoberta pública de Anime em `/anime` usando TMDB Discover para Movie e TV, com filtros e paginação próprios
- classificação Anime transparente baseada em Animation + idioma original japonês, preservando Movie/Series como tipos técnicos
- badges Anime na busca e nos detalhes, sem chamadas adicionais por resultado
- Estatísticas pessoais com tempo assistido, duração histórica congelada, gráfico mensal e visão Passado · Presente · Futuro
- Agenda pessoal explícita para filmes, séries e episódios, com reagendamento, filtros, paginação e itens atrasados preservados

- histórico real de filmes e episódios por data, com reassistidas, filtros e paginação cronológica
- correção de data e remoção de eventos históricos próprios, sem acoplamento à Minha Lista ou ao progresso

- temporadas públicas navegáveis com episódios reais do TMDB
- progresso persistente e idempotente de episódios, incluindo marcação e limpeza em lote por temporada

- Minha Lista privada com cinco estados atuais, filtros, paginação SQL e snapshot mínimo local de filmes e séries
- persistência `user_media` isolada por usuário, com FK, unicidade de identidade externa e proteção CSRF

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
