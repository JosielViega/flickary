# Arquitetura

O projeto usa uma arquitetura em camadas pequena. Cada classe deve existir por uma responsabilidade concreta; Services e Models não são obrigatórios para fluxos simples.

```text
Browser
   ↓
public/index.php
   ↓
Router
   ↓
Controller
   ↓
Service (quando houver regra que justifique)
   ├─→ Integration (serviço externo)
   └─→ Repository
          ↓
       PDO / MySQL
```

Sem regra intermediária, o Controller chama o Repository diretamente:

```text
Controller
   ↓
Repository
```

A resposta HTML segue:

```text
Controller
   ↓
View
   ↓
Layout
   ↓
HTML
```

## Responsabilidades

- `public/index.php`: front controller mínimo; inicializa e despacha.
- `bootstrap/app.php`: carrega Composer e ambiente, configura erros, timezone e sessão, e compõe dependências. Não contém negócio.
- `routes/web.php`: relaciona métodos/caminhos a actions e define o fallback 404.
- `app/Core`: infraestrutura reutilizável: Request, Response, Router, View, Session, CSRF, PDO, log e erros.
- `app/Controllers`: coordena cada caso HTTP, sem SQL ou HTML extenso.
- `app/Validation`: valida entradas no backend.
- `app/Repositories`: concentra consultas explícitas de cada domínio e seus prepared statements.
- `app/Integrations`: clientes de serviços externos, sem SQL, sessão de usuário ou lógica visual.
- `app/Services`: coordena regras ou integrações que realmente precisem de uma camada própria.
- `app/Models`: DTOs ou objetos simples quando o domínio os justificar; não é um ORM.
- `resources/views`: apresentação PHP, sempre escapando valores dinâmicos por padrão.
- `config`: arrays de configuração que leem o ambiente.
- `database`: evolução de schema em SQL versionado e seeds opcionais.

## Como evoluir um recurso

1. Defina a rota e o método HTTP.
2. Crie uma action pequena no Controller.
3. Valide dados recebidos e autorização no backend.
4. Adicione um Repository se houver SQL.
5. Adicione um Service apenas para regra ou coordenação significativa.
6. Retorne uma Response ou renderize uma View.
7. Cubra o comportamento fundamental com teste.

Dependências são montadas explicitamente em `bootstrap/app.php` ou `routes/web.php`. Se o projeto crescer muito, um container pode ser avaliado, mas não é necessário na fundação atual.

## Denúncias e autoridade administrativa

```text
profile / comment / direct message
              ↓
       ReportSubmission
              ↓
        social_reports
              ↓
   Admin moderation queue
```

`app/Moderation` centraliza tipos de alvo, motivos, status, validação textual e contratos de leitura/escrita. `SocialReportRepository` resolve comentários e mensagens com as regras sociais normais, grava o reporter da sessão e snapshots plain text, deduplica denúncias pendentes e pagina histórico/fila em SQL. O painel administrativo lê o snapshot; não impersona usuários nem ignora privacidade ou Block para abrir o recurso original.

`AdminAccess::isAdmin()` consulta `users.is_admin` no banco em cada decisão sensível. O flag não é uma role genérica e não é confiado à sessão. O provisionamento ocorre apenas em shell por `bin/admin.php`.

```text
report != sanction
review != automatic enforcement
```

Resolver ou arquivar registra classificação, reviewer, instante e nota interna. Não há banimento, suspensão, remoção de conteúdo, warning ou outra execução automática.

## Integrações externas e catálogo

```text
Browser
   ↓
Flickary
   ↓
Integration/TMDB
   ↓
TMDB API v3
```

`Repository` significa persistência MySQL; `Integration` significa comunicação com um serviço externo. A integração TMDB consulta o catálogo sob demanda e normaliza Movie e TV para os tipos internos `movie` e `series`. O TMDB permanece a única fonte para filmes, séries e Anime.

```text
TMDB Movie/TV
      ↓
TmdbMedia
      ↓
TmdbAnimeClassifier
      ↓
categoria visual Anime
```

Anime não é `media_type` e não é `source`. `TmdbAnimeClassifier` aplica a política central `original_language = ja` mais gênero Animation aos dados já normalizados, sem request adicional por card. `/anime` usa operações concretas de TMDB Discover para Movie e TV e continua levando a `/filmes/{id}` ou `/series/{id}`. O filtro não exige país de origem, keyword editorial ou popularidade mínima.

O TMDB não fornece uma flag oficial `is_anime`; a classificação é uma regra transparente do Flickary. O catálogo público continua classificando ao vivo. Para dados pessoais, a classificação é congelada no momento em que o snapshot é criado:

```text
TMDB Details
     ↓
TmdbAnimeClassifier
     ↓
snapshot is_anime
     ↓
MySQL

Minha Lista / Histórico / Agenda / Favoritos / Avaliações / Estatísticas
     ↓
somente MySQL
```

`NULL`, `false` e `true` permanecem estados distintos: desconhecido, classificado como não Anime e classificado como Anime. Mudanças posteriores no TMDB não sincronizam automaticamente snapshots existentes. O backfill CLI é a única correção em massa desta etapa e deduplica identidades TMDB entre domínios antes de consultar detalhes.

O Flickary não mantém um espelho completo do TMDB. Quando uma mídia entra na Minha Lista, persiste somente o snapshot mínimo necessário para integridade, apresentação básica durante indisponibilidade temporária e referência estável por `source + media_type + source_id`.

### Descoberta e cache público TMDB

```text
/buscar
   ↓
SearchController
   ├─ q → Search
   └─ sem q → ContentDiscoveryService
                    ↓
             TmdbDiscoveryCatalog
                    ↓
                TmdbClient
                    ↓
          FileTmdbPayloadCache
                    ↓
                  TMDB
```

`ContentDiscoveryService` compõe prateleiras tipadas com `TmdbMedia`, interleave determinístico, deduplicação local, limite de 12 itens e falha isolada. `TmdbDiscoveryCatalog` separa as coleções editoriais do contrato histórico usado por busca e detalhes. O ano vem do timezone ativo da aplicação; Anime reutiliza `TmdbAnimeClassifier`.

O cache armazena somente JSON público validado. Uma entrada fresh retorna imediatamente; após fresh expirar, sucesso upstream renova a entrada; erro transitório usa stale ainda válido. O primeiro erro transitório abre um circuito somente para aquela instância/request: fresh e stale continuam disponíveis, mas misses seguintes falham rápido. Busca textual, detalhes, sessão e dados de usuário não passam por esse cache.

## Minha Lista

```text
Media Detail → UserMediaController → UserMediaRepository → MySQL
                         └─ primeira inclusão → TmdbCatalog → snapshot mínimo
```

A primeira inclusão obtém detalhes normalizados no servidor e persiste somente o snapshot necessário. Uma mudança de status ou remoção de item existente usa apenas MySQL, mantendo a lista funcional durante indisponibilidade do catálogo externo. `completed` é estado atual, não evento de histórico.

## Progresso de séries

`user_media` representa o estado atual de uma mídia na lista. `user_series_episode_progress` representa o conjunto atual de episódios marcados e referencia diretamente o usuário, não `user_media`; remover uma série da lista preserva seu progresso.

Detalhes de temporada vêm em uma única chamada `TmdbCatalog::seasonDetails()`. Novas marcações são validadas pelo catálogo; desmarcar e limpar progresso são operações locais para continuarem disponíveis durante indisponibilidade externa.

## Histórico de visualização

Os quatro domínios pessoais permanecem deliberadamente separados:

```text
user_media                       → estado atual da mídia
user_series_episode_progress     → progresso atual de episódios
watch_history                    → eventos reais de visualização
user_schedule                    → intenção futura explícita
user_media_preferences           → favorito e avaliação atual de Movie/Series
```

`user_media_preferences` é um domínio independente: não requer `user_media`, não representa evento histórico nem intenção de Agenda e não referencia episódios. A primeira preferência congela um snapshot mínimo validado pelo catálogo e classificado no servidor; atualizações de Favorito ou Sua nota preservam esse snapshot e usam somente MySQL. Uma linha sem favorito e sem avaliação é removida.

`watch_history` preserva um snapshot mínimo obtido server-side no momento do registro. A identidade e o conteúdo do evento não são editáveis; somente `watched_on` pode ser corrigido, e um evento inserido por engano pode ser excluído. `request_key` resolve reenvios técnicos do mesmo formulário sem bloquear reassistidas legítimas com chaves novas. A duração conhecida também é congelada no evento: filmes usam `runtime` dos detalhes e episódios usam `runtime` da temporada. Durações ausentes permanecem `NULL`.

A linha do tempo usa apenas MySQL para títulos, episódios e datas. Ela pode consultar uma única vez a configuração de imagens do TMDB por request, mas nunca busca detalhes por card e continua útil quando o catálogo está indisponível.

## Agenda pessoal

`user_schedule` preserva uma única intenção futura ativa por identidade de conteúdo e usuário, com snapshot mínimo obtido server-side. Novos itens consultam o catálogo para validar conteúdo e bloquear material adulto; reagendamento, remoção e listagem são locais. A página pode consultar a configuração de imagens uma vez, sem detalhes por card. Estados Hoje, Próximo e Atrasado são derivados da data configurada da aplicação e nunca persistidos.

## Estatísticas pessoais

```text
StatisticsController → PersonalStatisticsRepository → MySQL
```

`GET /estatisticas` agrega os domínios pessoais com consultas locais e sempre filtradas por `user_id`, incluindo favoritos, avaliações e a média pessoal. Os doze meses são preenchidos em PHP a partir de um único agrupamento mensal, garantindo buckets vazios e ordem cronológica. Tempo assistido soma somente `watch_history.duration_minutes`; quando existem eventos sem duração, o resultado é explicitamente parcial. A rota não depende do cliente TMDB nem executa chamadas externas.

O backfill manual de duração é um fluxo CLI separado. Ele agrupa filmes por identidade e episódios por série/temporada antes de consultar o TMDB, atualiza exclusivamente eventos com duração `NULL` e não possui retry, `sleep()` ou execução automática em requests HTTP.

## Perfis públicos e relações sociais

```text
users + user_profiles
        ↓
PublicProfileReader
        ↓
/u/{username}

user_follows
        ↓
FollowStore
```

`PublicProfile` seleciona apenas identidade publicável e sanitiza URLs HTTPS. Estatísticas e favoritos usam readers de escopo público, sem entregar `PersonalStatistics` nem consultar detalhes do TMDB. `user_follows` mantém a relação simples com PK composta e cascata nas duas direções; contadores são calculados, nunca denormalizados. A regra central é `follow relationship != privacy authorization`: um follower continua sem acesso ao conteúdo de perfil privado.

## Compartilhamento público

```text
APP_URL
   ↓
PublicUrl
   ↓
PageMeta
   ↓
<head> canonical + Open Graph
   ↓
share-actions (Web Share / Clipboard / fallback manual)
```

`PublicUrl` é a única origem das URLs absolutas próprias e preserva um eventual path-base. `PageMeta` mantém somente os campos sociais necessários e normaliza descrições como texto simples. O componente recebe a canonical pronta do servidor e não consulta `window.location`; o JavaScript é enhancement opcional e não cria estado.

Metadados públicos são deliberadamente estáveis: `metadata pública != estado pessoal do viewer`. Perfil privado usa descrição genérica, enquanto Movie e Series ignoram lista, favorito, nota pessoal, progresso, agenda e histórico. Imagens de mídia seguem backdrop, poster e fallback estático; falha isolada da configuração TMDB mantém o fallback Flickary.

## Feed Social de visualizações

```text
user_follows
        +
user_profiles.share_watch_activity
        +
watch_history
        ↓
SocialFeedRepository
        ↓
/social
        ↓
social_activity_likes
        +
social_activity_comments
```

`Feed social = projeção de watch_history`: não existe tabela paralela, sincronização de eventos ou read receipt. A query parte de `user_follows`, junta apenas a identidade mínima do ator e aplica a regra `accepted follow AND sharing enabled AND event.created_at >= sharing_since AND event.created_at >= follow.created_at`. Assim, pending, evento anterior ao consentimento e evento anterior à relação aprovada nunca aparecem.

A paginação e o `COUNT` usam os mesmos filtros no MariaDB e a ordenação é `watch_history.created_at DESC, id DESC`; `watched_on` permanece a data humana exibida. O controller pode pedir somente a configuração de imagens do TMDB uma vez por request, sem details por card. Falha completa do TMDB mantém títulos, ator, episódio e datas locais disponíveis.

`social_activity_likes` referencia diretamente `watch_history.id`; não existe uma identidade paralela. Like não é activity e não muda `watch_history`. A projeção agrega a contagem e associa a curtida do viewer em uma única consulta, sem multiplicar o `COUNT` ou alterar a ordenação. A autorização de like reutiliza `SocialActivityVisibility`, portanto é exatamente a mesma regra de visibilidade do feed. A PK `(history_id, user_id)` torna a operação idempotente e a FK com cascade elimina as curtidas quando o evento é apagado.

`social_activity_comments` também referencia `watch_history.id` e guarda somente autor, texto simples e instante de criação. Comment não é activity e não cria outro evento no feed. A página `/social/atividades/{id}` reutiliza `SocialActivityVisibility` para followers e concede ao proprietário acesso ao arquivo da própria atividade. Criação autorizada ocorre por `INSERT ... SELECT`, combinando a verificação de visibilidade e a escrita; o proprietário só publica enquanto o evento está dentro do opt-in atual. A listagem junta identidade local do autor em uma consulta paginada, e o feed agrega somente a contagem, sem carregar corpos ou multiplicar atividades. FKs com cascade removem comentários quando o evento ou o autor é apagado.

## Notificações sociais

```text
mutation social
      ↓
linha fonte de verdade + social_notifications
```

Notificação não é fonte de verdade. As mutations de follow, solicitação, aceite, curtida e comentário gravam o evento e sua notificação na mesma transação; duplicatas ou ações ocultas não publicam nada. A caixa junta a identidade pública do ator em uma consulta paginada e o contexto global do layout executa apenas um `COUNT` de não lidas para usuário autenticado.

O ciclo de vida acompanha o estado real: cancelar ou recusar remove `follow_request`; aceitar troca a notificação pela `follow_accepted`; unlike remove `activity_like`; exclusão de comentário ou histórico remove as notificações relacionadas por cascade. A mudança de perfil privado para público converte solicitações e notificações em lote dentro da transação de atualização do perfil.

## Mensagens privadas 1:1

```text
user_follows A → B + user_follows B → A
                    ↓
            permissão de envio
                    ↓
          direct_conversations
                    ↓
             direct_messages
```

O par de conversa é canônico (`user_low_id`, `user_high_id`) e único. A primeira mensagem cria a conversa e atualiza `last_message_at` na mesma transação; abrir apenas a tela não cria linha vazia. A caixa combina outro participante, última mensagem, não lidas e permissão atual numa consulta paginada, sem N+1.

`Acesso à conversa != permissão de envio`: participantes continuam lendo o histórico após unfollow, mas cada novo envio revalida as duas relações aceitas dentro da transação. Re-follow reutiliza o mesmo par. `DirectMessageBody` limita texto simples Unicode a 2.000 caracteres, enquanto `MessageLayoutContextProvider` compõe o contador global com o provider de notificações sem executar queries para guests.

## Controles sociais

```text
user_blocks -> UserSocialControlRepository -> fronteira de autorização do par
user_mutes  -> SocialFeedRepository        -> preferência unilateral de feed
```

`Block = authorization boundary`: a direção é persistida para gerenciamento, mas qualquer row em qualquer direção corta perfil e novas interações para ambos. O bloqueio é transacional e limpa relações derivadas, preservando conteúdo histórico.

`Mute = feed preference`: somente a projeção do Feed Social consulta `user_mutes`; `SocialActivityVisibility`, perfis, mensagens e notificações continuam inalterados. Listagens e contagens sensíveis aplicam os filtros no SQL antes da paginação.

## Ações administrativas e acesso da conta

```text
Report
  ↓
Admin decision
  ├─ resolve/dismiss
  ├─ suspend account
  └─ remove reported comment

ModerationAction = trilha de auditoria
```

`Report` preserva a denúncia e sua revisão; `ModerationAction` registra separadamente a ação efetiva. `ModerationActionService` possui as transações que bloqueiam report/usuário/comentário, aplicam a mudança, escrevem a auditoria e resolvem o report de forma atômica. Leituras globais e por target usam paginação SQL e um único `LEFT JOIN` para o administrador, sem N+1.

```text
Session user → AccountSuspensionGuard → Router
External identity → AccountAccessReader → Auth::login
```

Guests não consultam acesso de conta. Para usuário autenticado, o guard faz uma consulta indexada e encerra a identidade se `suspended_until > CURRENT_TIMESTAMP`. O contexto mínimo controlado pelo servidor alimenta `/conta/suspensa`. Google e Facebook consultam o mesmo `AccountAccessReader` antes de criar a sessão. Timestamp passado significa conta ativa e não provoca escrita.

## Ferramentas da fundação

Os scripts em `bin/` não fazem parte do fluxo HTTP nem das regras de negócio. `composer setup` prepara uma cópia local conservadoramente. `composer deploy:hostgator` gera, a partir de uma allowlist versionada, um espelho descartável de produção. O espelho nunca se torna uma segunda fonte de código.

## Solicitações de seguidores privados

O domínio social separa intenção pendente de relação aceita:

```text
public target  -> user_follows
private target -> user_follow_requests -> accept -> user_follows
```

Uma row em `user_follow_requests` significa apenas `pending`; não há status nem histórico de recusas. `user_follows` contém exclusivamente relações ativas usadas por contadores, listagens e pela `PrivateProfileAccessPolicy`. Assim, `pending != authorized` e `accepted follow == private profile access`, somente para os agregados já disponíveis em `/u/{username}`.

O aceite individual e a transição `private -> public` são transacionais. A transição inversa preserva followers aceitos. A migration `000010` é a exceção de legado: ela converte follows preexistentes para targets privados em requests antes que follows passem a autorizar acesso.
