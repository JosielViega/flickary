# Flickary

Flickary é uma plataforma web pessoal e social para acompanhar filmes, séries e animes.

Seu conceito central é **Passado · Presente · Futuro**: registrar o que já fez parte da jornada do usuário, acompanhar o que está em andamento e organizar o que ainda será descoberto.

O projeto está em desenvolvimento inicial. A aplicação atual possui contas, login com Google e Facebook, perfil próprio, busca e detalhes públicos no TMDB, descoberta pública de Anime, Minha Lista privada, temporadas navegáveis, progresso de episódios, histórico real de visualização, Agenda pessoal, Favoritos, avaliações, Estatísticas pessoais, perfis públicos, seguidores privados com aprovação, Feed Social opt-in com curtidas e comentários, e compartilhamento público com prévias sociais.

## Stack

- PHP 8.2 ou superior;
- MySQL 8+ ou MariaDB compatível;
- PDO MySQL;
- Composer 2;
- Apache com `mod_rewrite` e `.htaccess`;
- HTML5, CSS3 e JavaScript puro.

Não há framework web, ORM, frontend framework, Node.js como backend ou Docker obrigatório.

## Instalação local

```bash
git clone https://github.com/JosielViega/flickary.git
cd flickary
composer install
composer setup
```

`composer setup` cria `.env` a partir de `.env.example` somente quando necessário, escolhe uma porta local disponível e atualiza o autoload. Um `.env` existente é preservado.

Também é possível criar o arquivo manualmente:

```powershell
copy .env.example .env
```

No Linux/macOS:

```bash
cp .env.example .env
```

O `.env` contém configuração local e nunca deve ser versionado.

## Executar

```bash
composer serve
```

O servidor embutido é uma conveniência de desenvolvimento. Apache é o ambiente esperado em produção.

## Comandos úteis

```bash
composer install
composer setup
composer serve
composer test
composer lint
composer check
composer migrate
composer history:backfill-duration
composer anime:backfill-snapshots
composer deploy:hostgator
composer port:status
composer port:release
```

`composer check` valida o manifesto Composer, executa o lint dos arquivos PHP do projeto e roda os testes automatizados.

`composer migrate` executa migrations SQL ainda não registradas. O schema contém a fundação de contas, a lista pessoal, o progresso de episódios e o histórico descritos abaixo.

## Fundação de contas

As migrations definem a conta interna em `users`, o perfil 1:1 em `user_profiles` e as identidades de provedores em `user_external_identities`. A separação mantém a identidade Flickary independente de Google, Facebook ou qualquer outro provedor.

Google e Facebook resolvem contas exclusivamente pelo par `provider + provider_user_id` verificado e criam novos usuários somente após a escolha de username. Não existe login local por senha. Também não há usuários de demonstração ou persistência de tokens OAuth.

## Google Login

O fluxo usa Google Identity Services e valida o ID token no servidor com `google/apiclient`. Configure apenas o Client ID público do aplicativo Web:

```env
GOOGLE_CLIENT_ID=
```

Sem essa configuração, Home e `/health` continuam disponíveis e `/login` exibe um estado amigável. O e-mail não identifica a conta Google e nunca causa vinculação automática; somente o claim `sub` verificado é usado como identidade externa.

## Facebook Login

O fluxo usa Authorization Code server-side com Graph API `v26.0`, `state` aleatório de uso único por dez minutos e `appsecret_proof` nas consultas de perfil. Configure no app da Meta o URI de redirecionamento exato derivado de `APP_URL` e estas variáveis:

```env
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=
```

Para o ambiente local padrão, o callback é `http://localhost:8010/auth/facebook/callback`. Em outro host ou porta, ele acompanha `APP_URL`. O app solicita somente o perfil básico (`id`, `name`, `picture`); e-mail do Facebook é deliberadamente ignorado e nunca é usado para encontrar ou vincular contas.

Na área **Perfil → Contas conectadas**, um usuário autenticado pode conectar Facebook à conta Flickary atual. O estado OAuth fica vinculado ao ID interno da sessão; uma identidade já pertencente a outra conta é recusada. Não há desconexão nesta etapa.

Sem as duas credenciais Facebook, o botão aparece como indisponível de forma amigável e Google, Home, perfil e `/health` continuam funcionando.

## Fundação TMDB

O catálogo externo usa a TMDB API v3 por uma integração exclusivamente server-side. Configure o API Read Access Token somente no ambiente:

```env
TMDB_READ_ACCESS_TOKEN=
```

O cliente envia o token como Bearer, usa inicialmente `pt-BR` e região `BR`, e nunca entrega a credencial ao navegador. O Flickary não mantém um espelho completo do catálogo TMDB: quando coleção, agenda e histórico forem implementados, somente um snapshot mínimo associado a `source + source_id` será persistido para integridade e exibição básica.

As rotas públicas `GET /buscar`, `GET /anime`, `GET /filmes/{id}`, `GET /series/{id}` e `GET /sobre` usam essa integração para pesquisar, descobrir e exibir detalhes básicos de filmes e séries, construir URLs de pôster e backdrop a partir da configuração real de imagens e apresentar os créditos obrigatórios.

O TMDB permanece a única fonte de catálogo também para Anime. Anime não é um provider nem um `media_type`: o Flickary classifica visualmente como Anime o Movie ou TV cujo idioma original é japonês (`ja`) e que contém o gênero Animation. A rota `/anime` usa TMDB Discover e preserva os tipos técnicos `movie` e `series`, inclusive nos links de detalhes. O TMDB não fornece uma flag oficial `is_anime`, portanto essa política é transparente e deliberadamente conservadora.

O catálogo público calcula essa classificação ao vivo a partir dos dados TMDB. Novas entradas pessoais congelam `is_anime` no snapshot local para que Minha Lista, Histórico, Agenda e Estatísticas reconheçam Anime usando somente MySQL. `NULL` significa snapshot antigo ainda não classificado; `0` significa não Anime e `1`, Anime. Os filtros Anime são transversais: Filmes e Séries continuam incluindo suas respectivas produções Anime.

`composer anime:backfill-snapshots` classifica manualmente apenas snapshots TMDB ainda `NULL`, deduplicando Movies e Series entre todas as tabelas. O comando não sincroniza registros já classificados, não usa outra API e não executa automaticamente em requests ou tarefas agendadas.

## Minha Lista

`GET /minha-lista` é uma área privada. Ela guarda um snapshot mínimo local — identidade TMDB, títulos, data e paths de imagens — e um dos estados `planned`, `watching`, `paused`, `completed` ou `dropped`. O catálogo completo continua externo e a listagem não consulta detalhes individuais no TMDB. **Concluído é somente o estado atual e ainda não representa histórico de visualização.**

Séries possuem temporadas navegáveis em `GET /series/{id}/temporadas/{season}`. Usuários com a série na Minha Lista podem marcar episódios ou uma temporada inteira. As marcações persistem mesmo se a série for removida da lista e reaparecem ao adicioná-la novamente. Esse conjunto atual de episódios assistidos também não é histórico de visualização.

## Histórico de visualização

`GET /historico` é uma subárea privada de Minha Lista. Filmes e episódios podem ser registrados com `watched_on`, uma data real igual ou anterior a hoje. Cada registro é um evento independente: reassistidas do mesmo conteúdo, inclusive no mesmo dia, permanecem visíveis separadamente. O usuário pode corrigir a data ou remover um evento; a identidade e o snapshot mínimo do conteúdo permanecem server-side.

Registrar histórico não altera automaticamente Minha Lista nem progresso de episódios. Da mesma forma, mudar status, marcar progresso, remover uma mídia da lista ou limpar uma temporada não cria nem apaga eventos históricos.

## Favoritos e avaliações pessoais

`GET /favoritos` e `GET /avaliacoes` são subáreas privadas de Minha Lista. Favorito e Sua nota inteira de 1 a 10 coexistem no snapshot mínimo local `user_media_preferences`; remover as duas preferências exclui a linha. A primeira gravação obtém identidade, título, imagem e classificação Anime no servidor, enquanto edições posteriores e as listagens funcionam somente com dados locais.

Essas preferências não exigem nem criam itens em Minha Lista, Histórico, Agenda ou progresso. Remover dados de qualquer um desses domínios também não remove favoritos ou avaliações. A nota pessoal não substitui a avaliação do catálogo TMDB.

## Agenda pessoal

`GET /agenda` organiza intenções futuras escolhidas explicitamente pelo usuário para filmes, séries e episódios, sempre com uma data diária. Itens que passam da data são preservados como atrasados até serem reagendados ou removidos. A Agenda usa snapshots mínimos locais e permanece independente de Minha Lista, progresso e Histórico: nenhuma ação em um desses domínios altera automaticamente os demais. Não há descoberta automática de lançamentos, horários ou notificações nesta etapa.

## Estatísticas pessoais

`GET /estatisticas` apresenta a jornada privada em números usando exclusivamente dados locais de Histórico, Minha Lista, progresso, Agenda e preferências. A leitura não consulta o TMDB e inclui totais de favoritos, títulos avaliados e média pessoal. Novos eventos históricos congelam a duração conhecida do filme ou episódio; quando ela for desconhecida, a interface informa explicitamente que o tempo registrado é parcial.

Eventos antigos continuam válidos com duração `NULL`. O comando manual e idempotente `composer history:backfill-duration` consulta no máximo uma vez cada filme e cada temporada TMDB necessários, preenche somente `duration_minutes` e nunca exibe o token. Ele não possui retry ou espera automática.

A área **Sobre / Créditos** usa um logo oficial aprovado, menos proeminente que a marca Flickary, e inclui o aviso exigido: “This product uses the TMDB API but is not endorsed or certified by TMDB.” Uso e eventual monetização devem continuar obedecendo aos termos e ao licenciamento vigentes do TMDB.

## Porta local

Cada projeto recebe uma reserva por caminho absoluto. O registro continua em `~/.modeloPHP/ports.json` por compatibilidade com outros projetos derivados da mesma fundação. Esse nome é deliberadamente compartilhado: alterá-lo criaria uma segunda fonte de reservas e poderia reintroduzir conflitos de porta.

O registro é local, fica fora do Git e não contém credenciais. Consulte [desenvolvimento local](docs/LOCAL_DEVELOPMENT.md).

## Perfis públicos e seguidores

`GET /u/{username}` apresenta identidade pública, contadores sociais e, quando permitido, agregados locais e até seis favoritos. Perfis privados exibem somente identidade, bio, data de entrada e contadores; seguir alguém não concede acesso ao conteúdo privado. O proprietário pode conferir a prévia completa e editar a visibilidade em `/perfil`.

As páginas `/u/{username}/seguidores` e `/u/{username}/seguindo` usam paginação SQL. A descoberta local de pessoas fica em `/usuarios`, pesquisando username e nome de exibição sem consultar o TMDB. Perfis privados recebem solicitações que precisam ser aceitas; pending nunca equivale a follow ativo. Ainda não existem comentários, mensagens ou notificações.

## Feed Social de visualizações

`GET /social` é privado e apresenta, em ordem cronológica de registro, somente eventos reais de `watch_history` compartilhados pelas pessoas que o usuário segue. O feed é uma projeção local desse event log, não uma segunda fonte de eventos: corrigir `watched_on` atualiza o card, apagar o Histórico remove a atividade e reassistidas continuam separadas. Agenda, Minha Lista, progresso, favoritos e avaliações nunca entram no feed.

O compartilhamento nasce desativado e exige opt-in em `/perfil`. A ativação grava um cutoff; nenhum Histórico anterior é publicado retroativamente. O início do follow aprovado cria um segundo cutoff, de modo que novos followers também não recebem backlog. A visibilidade é exatamente `accepted follow AND sharing enabled AND event.created_at >= sharing_since AND event.created_at >= follow.created_at`. Requests pendentes de perfis privados não autorizam nada.

Desativar a preferência oculta imediatamente todas as atividades, pois o feed não persiste cópias. Reativá-la cria um novo cutoff e não restaura eventos anteriores. A listagem usa SQL paginado e snapshots locais; pode consultar `/configuration` uma vez para pôsteres, mas permanece funcional quando o TMDB está offline.

Cada atividade visível pode receber uma curtida por usuário. A mesma regra do feed autoriza `POST /social/atividades/{id}/curtir`; a PK composta de `social_activity_likes` garante idempotência e o card traz contagem e estado pessoal sem N+1. A curtida não cria atividade, não altera `watch_history`, não gera notificação nem expõe lista de pessoas. Apagar o evento no Histórico remove suas curtidas por cascade.

`GET /social/atividades/{id}` abre a conversa de uma atividade visível. Comentários são texto simples de até 500 caracteres, paginados do mais recente para o mais antigo, e usam `watch_history.id` como identidade da atividade. Followers só podem ler e publicar enquanto a atividade permanece visível; o autor da atividade conserva acesso de arquivo, mas só comenta quando seu compartilhamento atual inclui o evento. O autor do comentário pode removê-lo mesmo após perder acesso, e o autor da atividade pode moderar sua própria conversa. Unfollow e desativação do compartilhamento não apagam comentários; apagar a atividade ou o autor do comentário remove as linhas relacionadas por cascade. `/perfil/comentarios` reúne o histórico privado do usuário. Comentários não viram eventos. Não há replies, rich text, notificações ou chamadas de detalhes ao TMDB.

## Compartilhamento e prévias sociais

Perfis em `/u/{username}` e detalhes em `/filmes/{id}` e `/series/{id}` publicam canonical e Open Graph no HTML server-side. A base absoluta vem exclusivamente de `APP_URL`, inclusive quando a aplicação usa um path-base; por isso essa variável deve representar o endereço público correto em produção.

As páginas oferecem Web Share nativo quando suportado, cópia via Clipboard API e um link readonly para cópia manual sem JavaScript. O comportamento é progressive enhancement: nenhuma plataforma social, SDK externo, token, contador ou parâmetro de tracking é integrado diretamente. Perfis privados recebem descrição social genérica, e metadados de mídia nunca incluem o estado pessoal do visitante.

## Arquitetura

```text
Browser
   ↓
public/index.php
   ↓
Router
   ↓
Controller
   ↓
Service (quando necessário)
   ├─→ Integration (serviço externo)
   └─→ Repository (persistência)
          ↓
       PDO / MySQL
```

Services são opcionais e devem coordenar regras ou integrações reais. Repositories concentram SQL explícito quando a persistência existir. Models só devem ser criados quando trouxerem valor concreto; não há ORM.

Estrutura principal:

```text
app/                 Núcleo, controllers e futuro código de domínio
bootstrap/app.php    Inicialização e composição da aplicação
config/              Configuração derivada do ambiente
database/            Migrations SQL e seeds opcionais
Designer/            Referência oficial de identidade visual
docs/                Arquitetura, segurança e desenvolvimento local
public/              Único Document Root público
resources/views/     Layouts e páginas PHP
routes/web.php       Rotas HTTP explícitas
storage/             Cache e logs locais
tests/               Testes automatizados
bin/                 Ferramentas de desenvolvimento e deploy
```

Leia [arquitetura](docs/ARCHITECTURE.md) e o [plano de estudo e revisão](docs/PLANO_DE_ESTUDO_E_REVISAO.md) para conhecer a fundação herdada e suas decisões.

## Rotas atuais

- `GET /` — Home visual inicial do Flickary;
- `GET /login` — entrada explícita com Google Identity Services e Facebook Login;
- `POST /auth/google` — valida a resposta server-side do Google;
- `GET /auth/facebook` — inicia o Authorization Code Flow do Facebook para visitantes;
- `GET /auth/facebook/callback` — valida `state`, troca o código no servidor e resolve a identidade Facebook;
- `GET|POST /onboarding/username` — conclui uma nova conta com username;
- `GET|POST /perfil` — exibe e atualiza display name, bio, privacidade e opt-in do Feed Social do usuário autenticado;
- `GET /u/{username}` — perfil público resolvido exclusivamente pelo username;
- `GET /u/{username}/seguidores` e `GET /u/{username}/seguindo` — conexões sociais paginadas;
- `POST /u/{username}/seguir`, `POST /u/{username}/cancelar-solicitacao` e `POST /u/{username}/deixar-de-seguir` — follow público imediato ou solicitação privada, sempre com CSRF;
- `GET /perfil/solicitacoes` — solicitações pendentes recebidas, paginadas em SQL;
- `POST /perfil/solicitacoes/{username}/aceitar|recusar` — decisão do proprietário autenticado sobre uma solicitação;
- `GET /usuarios` — busca pública local por username ou nome de exibição;
- `GET /social` — feed privado e cronológico de visualizações compartilhadas por follows aceitos;
- `POST /social/atividades/{id}/curtir|descurtir` — interação idempotente sobre atividade visível do feed;
- `GET /social/atividades/{id}` e `POST /social/atividades/{id}/comentarios` — detalhe privado e criação de comentário em atividade autorizada;
- `POST /social/comentarios/{id}/remover` — remoção pelo autor do comentário ou moderação pelo autor da atividade;
- `GET /perfil/comentarios` — histórico privado e paginado dos comentários do usuário autenticado;
- `POST /perfil/conexoes/facebook` — inicia a conexão segura do Facebook à conta autenticada;
- `GET /buscar` — pesquisa pública de filmes e séries no TMDB, com filtros e paginação específica;
- `GET /anime` — descoberta pública de filmes e séries classificados como Anime dentro do catálogo TMDB;
- `GET /filmes/{id}` — detalhes públicos básicos de um filme TMDB;
- `GET /series/{id}` — detalhes públicos básicos de uma série TMDB;
- `POST /filmes/{id}/preferencias` e `POST /series/{id}/preferencias` — salva Favorito e Sua nota autenticados;
- `GET /series/{id}/temporadas/{season}` — temporada pública e episódios, com progresso e registro histórico autenticados;
- `POST /filmes/{id}/historico` — registra uma visualização de filme autenticada;
- `POST /series/{id}/temporadas/{season}/episodios/{episode}/historico` — registra uma visualização de episódio autenticada;
- `GET /minha-lista` — estado atual privado de filmes e séries;
- `GET /favoritos` — favoritos privados, filtráveis e paginados;
- `GET /avaliacoes` — avaliações pessoais privadas, filtráveis e paginadas;
- `GET /historico` — linha do tempo privada, filtrável e paginada;
- `GET /agenda` — agenda pessoal privada, filtrável e paginada de filmes, séries e episódios;
- `GET /estatisticas` — estatísticas pessoais privadas calculadas somente com dados locais;
- `POST /filmes/{id}/agenda`, `POST /series/{id}/agenda` e `POST /series/{id}/temporadas/{season}/episodios/{episode}/agenda` — cria ou reagenda uma intenção futura;
- `POST /agenda/{id}` e `POST /agenda/{id}/remover` — reagenda ou remove um item próprio;
- `POST /historico/{id}` — corrige somente a data de um evento próprio;
- `POST /historico/{id}/remover` — remove um evento próprio;
- `GET /sobre` — apresentação do Flickary e créditos da integração TMDB;
- `POST /logout` — encerra a sessão com proteção CSRF;
- `GET /health` — liveness check simples com `{"status":"ok"}`;
- demais combinações de método e caminho — página 404.

O endpoint `/health` não consulta banco nem expõe ambiente, servidor, caminhos ou configurações internas.

## Seguidores e perfis privados

Perfis públicos recebem follows imediatamente. Perfis privados recebem uma solicitação pendente, que não conta como follower e não autoriza conteúdo; somente a aceitação cria `user_follows` e libera os agregados já exibidos por `/u/{username}`. A página `/perfil/solicitacoes` permite aceitar ou recusar, e o requester pode cancelar antes da decisão.

Mudar de público para privado preserva followers já aceitos. Mudar de privado para público converte, na mesma transação da atualização do perfil, todas as solicitações pendentes em follows. A migration `000010` trata o legado de forma distinta: follows antigos direcionados a perfis privados viram solicitações, evitando concessão silenciosa de acesso.

## Identidade visual

A pasta `Designer/` contém a referência oficial da identidade visual do Flickary, incluindo o conceito **Cinematic Dark — Deep Blue**.

Ela é documentação de design, não uma pasta de assets de produção. Seus arquivos não são servidos por `public/` e não fazem parte do mirror HostGator.

## Segurança

A fundação mantém:

- secrets somente em `.env`;
- prepared statements PDO e proibição de concatenar input em SQL;
- escape de saída dinâmica com `e()`;
- CSRF e validação backend disponíveis para futuras ações mutáveis;
- cookies HttpOnly, SameSite e Secure sob HTTPS;
- tratamento de erros apropriado para produção;
- logs fora do Document Root e sem secrets;
- ações mutáveis fora de GET;
- uploads fora do Git e protegidos contra execução de PHP;
- `public/` como único Document Root.

Leia a política completa em [docs/SECURITY.md](docs/SECURITY.md).

## Produção e HostGator

Em produção, use pelo menos:

```env
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
```

O comando abaixo gera um mirror local baseado em allowlist:

```bash
composer deploy:hostgator
```

O mirror fica em `deploy/hostgator/mirror/`, fora do Git. Ele não inclui `.env`, arquivos de servidor, testes, documentação, `Designer/`, uploads, logs ou cache; também não transmite arquivos nem executa migrations.

Consulte [deploy para HostGator/cPanel](deploy/hostgator/README.md).
