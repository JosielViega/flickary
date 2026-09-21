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

`Repository` significa persistência MySQL; `Integration` significa comunicação com um serviço externo. A integração TMDB consulta o catálogo sob demanda e normaliza Movie e TV para os tipos internos `movie` e `series`. Ela não classifica animações como anime; uma futura integração AniList terá responsabilidade própria.

O Flickary não mantém um espelho completo do TMDB. Quando uma mídia entra na Minha Lista, persiste somente o snapshot mínimo necessário para integridade, apresentação básica durante indisponibilidade temporária e referência estável por `source + media_type + source_id`.

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

Os três domínios pessoais permanecem deliberadamente separados:

```text
user_media                       → estado atual da mídia
user_series_episode_progress     → progresso atual de episódios
watch_history                    → eventos reais de visualização
```

`watch_history` preserva um snapshot mínimo obtido server-side no momento do registro. A identidade e o conteúdo do evento não são editáveis; somente `watched_on` pode ser corrigido, e um evento inserido por engano pode ser excluído. `request_key` resolve reenvios técnicos do mesmo formulário sem bloquear reassistidas legítimas com chaves novas.

A linha do tempo usa apenas MySQL para títulos, episódios e datas. Ela pode consultar uma única vez a configuração de imagens do TMDB por request, mas nunca busca detalhes por card e continua útil quando o catálogo está indisponível.

## Ferramentas da fundação

Os scripts em `bin/` não fazem parte do fluxo HTTP nem das regras de negócio. `composer setup` prepara uma cópia local conservadoramente. `composer deploy:hostgator` gera, a partir de uma allowlist versionada, um espelho descartável de produção. O espelho nunca se torna uma segunda fonte de código.
