# Flickary

Flickary é uma plataforma web pessoal e social para acompanhar filmes, séries e animes.

Seu conceito central é **Passado · Presente · Futuro**: registrar o que já fez parte da jornada do usuário, acompanhar o que está em andamento e organizar o que ainda será descoberto.

O projeto está em desenvolvimento inicial. A aplicação atual possui contas, login com Google e Facebook, perfil próprio, busca e detalhes públicos no TMDB, descoberta pública de Anime, Minha Lista privada, temporadas navegáveis, progresso de episódios, histórico real de visualização, Agenda pessoal e Estatísticas pessoais. Favoritos, perfil público e recursos sociais ainda não foram implementados.

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

Minha Lista, Histórico, Agenda e Estatísticas ainda não filtram Anime porque seus snapshots locais não congelam essa classificação. Uma evolução futura poderá persistir a categoria caso exista necessidade real, sem alterar retroativamente a fonte ou o tipo da mídia.

## Minha Lista

`GET /minha-lista` é uma área privada. Ela guarda um snapshot mínimo local — identidade TMDB, títulos, data e paths de imagens — e um dos estados `planned`, `watching`, `paused`, `completed` ou `dropped`. O catálogo completo continua externo e a listagem não consulta detalhes individuais no TMDB. **Concluído é somente o estado atual e ainda não representa histórico de visualização.**

Séries possuem temporadas navegáveis em `GET /series/{id}/temporadas/{season}`. Usuários com a série na Minha Lista podem marcar episódios ou uma temporada inteira. As marcações persistem mesmo se a série for removida da lista e reaparecem ao adicioná-la novamente. Esse conjunto atual de episódios assistidos também não é histórico de visualização.

## Histórico de visualização

`GET /historico` é uma subárea privada de Minha Lista. Filmes e episódios podem ser registrados com `watched_on`, uma data real igual ou anterior a hoje. Cada registro é um evento independente: reassistidas do mesmo conteúdo, inclusive no mesmo dia, permanecem visíveis separadamente. O usuário pode corrigir a data ou remover um evento; a identidade e o snapshot mínimo do conteúdo permanecem server-side.

Registrar histórico não altera automaticamente Minha Lista nem progresso de episódios. Da mesma forma, mudar status, marcar progresso, remover uma mídia da lista ou limpar uma temporada não cria nem apaga eventos históricos.

## Agenda pessoal

`GET /agenda` organiza intenções futuras escolhidas explicitamente pelo usuário para filmes, séries e episódios, sempre com uma data diária. Itens que passam da data são preservados como atrasados até serem reagendados ou removidos. A Agenda usa snapshots mínimos locais e permanece independente de Minha Lista, progresso e Histórico: nenhuma ação em um desses domínios altera automaticamente os demais. Não há descoberta automática de lançamentos, horários ou notificações nesta etapa.

## Estatísticas pessoais

`GET /estatisticas` apresenta a jornada privada em números usando exclusivamente dados locais de Histórico, Minha Lista, progresso e Agenda. A leitura não consulta o TMDB. Novos eventos históricos congelam a duração conhecida do filme ou episódio; quando ela for desconhecida, a interface informa explicitamente que o tempo registrado é parcial.

Eventos antigos continuam válidos com duração `NULL`. O comando manual e idempotente `composer history:backfill-duration` consulta no máximo uma vez cada filme e cada temporada TMDB necessários, preenche somente `duration_minutes` e nunca exibe o token. Ele não possui retry ou espera automática.

A área **Sobre / Créditos** usa um logo oficial aprovado, menos proeminente que a marca Flickary, e inclui o aviso exigido: “This product uses the TMDB API but is not endorsed or certified by TMDB.” Uso e eventual monetização devem continuar obedecendo aos termos e ao licenciamento vigentes do TMDB.

## Porta local

Cada projeto recebe uma reserva por caminho absoluto. O registro continua em `~/.modeloPHP/ports.json` por compatibilidade com outros projetos derivados da mesma fundação. Esse nome é deliberadamente compartilhado: alterá-lo criaria uma segunda fonte de reservas e poderia reintroduzir conflitos de porta.

O registro é local, fica fora do Git e não contém credenciais. Consulte [desenvolvimento local](docs/LOCAL_DEVELOPMENT.md).

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
- `GET|POST /perfil` — exibe e atualiza display name, bio e preferência de privacidade do usuário autenticado;
- `POST /perfil/conexoes/facebook` — inicia a conexão segura do Facebook à conta autenticada;
- `GET /buscar` — pesquisa pública de filmes e séries no TMDB, com filtros e paginação específica;
- `GET /anime` — descoberta pública de filmes e séries classificados como Anime dentro do catálogo TMDB;
- `GET /filmes/{id}` — detalhes públicos básicos de um filme TMDB;
- `GET /series/{id}` — detalhes públicos básicos de uma série TMDB;
- `GET /series/{id}/temporadas/{season}` — temporada pública e episódios, com progresso e registro histórico autenticados;
- `POST /filmes/{id}/historico` — registra uma visualização de filme autenticada;
- `POST /series/{id}/temporadas/{season}/episodios/{episode}/historico` — registra uma visualização de episódio autenticada;
- `GET /minha-lista` — estado atual privado de filmes e séries;
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
