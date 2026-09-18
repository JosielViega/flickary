# Flickary

Flickary é uma plataforma web pessoal e social para acompanhar filmes, séries e animes.

Seu conceito central é **Passado · Presente · Futuro**: registrar o que já fez parte da jornada do usuário, acompanhar o que está em andamento e organizar o que ainda será descoberto.

O projeto está em desenvolvimento inicial. A aplicação atual possui fundação persistente de contas, login com Google e Facebook, onboarding inicial de username, conexão segura de um segundo provedor, perfil próprio autenticado, busca pública e páginas públicas de detalhes de filmes e séries no TMDB. Coleção, temporadas navegáveis, perfil público e recursos sociais ainda não foram implementados.

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
composer deploy:hostgator
composer port:status
composer port:release
```

`composer check` valida o manifesto Composer, executa o lint dos arquivos PHP do projeto e roda os testes automatizados.

`composer migrate` executa migrations SQL ainda não registradas. O schema atual contém somente a fundação de contas descrita abaixo.

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

As rotas públicas `GET /buscar`, `GET /filmes/{id}`, `GET /series/{id}` e `GET /sobre` usam essa integração para pesquisar e exibir detalhes básicos de filmes e séries, construir URLs de pôster e backdrop a partir da configuração real de imagens e apresentar os créditos obrigatórios. As consultas acontecem no servidor sob demanda; resultados, detalhes, consultas e imagens não são persistidos nem armazenados em cache.

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
- `GET /filmes/{id}` — detalhes públicos básicos de um filme TMDB;
- `GET /series/{id}` — detalhes públicos básicos de uma série TMDB;
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
