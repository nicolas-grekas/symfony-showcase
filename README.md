# Conferences, a Symfony 8.2 showcase

A conference app (call for papers, reviews, tickets, back office and API) written to
exercise the features of Symfony 8.2 the way an application would use them. The
`symfony-8.1` and `symfony-7.4` branches are the same app on Symfony 8.1 and 7.4, and
[docs/8.2-vs-8.1.md](docs/8.2-vs-8.1.md) shows what 8.2 saves, feature by feature, and what 8.0
and 8.1 saved before it. What building
it found, bugs and rough edges of 8.2 included, is listed in [LEDGER.md](LEDGER.md) with
the pull requests that fix them. The Symfony ones are all merged in 8.2.x-dev.

## Running it

Requirements: PHP 8.5 with the `intl`, `pdo_pgsql`, `amqp` and `sodium` extensions, the
[Symfony CLI](https://symfony.com/download) and Docker. The examples that get a token also need
`jq`, and the MCP Inspector needs Node.js with `npx`: Node.js 22.19 or later for the Inspector 2,
[docs/mcp.md](docs/mcp.md) tells which version to use with an older one.

```bash
composer install
docker compose up -d --wait   # Postgres with pgvector, Mailpit, Keycloak, RabbitMQ
symfony console doctrine:migrations:migrate -n
symfony console doctrine:fixtures:load -n
symfony serve -d --no-tls --port=8124
```

Use the port 8124: the Keycloak realm in `docker/keycloak` redirects there. The Symfony CLI
exposes the containers to the app and starts the Messenger worker declared in
`.symfony.local.yaml`; run the commands through it (`symfony console`, `symfony php`).
Mailpit shows the emails at the URL `symfony open:local:webmail` opens.

In the dev environment, the "Symfony 8.2" panel of the web profiler lists the 8.2 features each
request uses, with links to their code, to their documentation and to the commits of the
`symfony-8.1` and `symfony-7.4` branches that do without them.

| Who | Logs in with | Can |
|---|---|---|
| `admin@example.com` / `admin` | the login form | everything, impersonate users |
| `organizer@example.com` / `organizer` | the login form | edit conferences, see sales and failed messages |
| `reviewer@example.com` / `reviewer` | the login form | review and decide on proposals |
| `alice` / `alice`, `carol` / `carol` | Keycloak | submit proposals, buy tickets |

The API takes access tokens of Keycloak, e.g. with the `showcase-cli` client:

```bash
TOKEN=$(curl -s -d grant_type=password -d client_id=showcase-cli -d username=carol -d password=carol \
    -d 'scope=openid proposals:write' http://localhost:8180/realms/showcase/protocol/openid-connect/token | jq -r .access_token)
curl -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8124/api/conferences/symfonylive-lille
```

`/.well-known/api-catalog` lists the endpoints, and `/api/docs` describes them.

## MCP server

`/mcp` serves an MCP server, with the MCP bundle of Symfony AI: AI clients such as Claude Code
or the MCP Inspector can search the talks, read the schedules, list the proposals of a speaker
and submit new ones. Keycloak issues its tokens for its URL (RFC 8707), a firewall checks them,
and submitting needs the `proposals:write` scope. [docs/mcp.md](docs/mcp.md) tells how to
connect a client:

```bash
claude mcp add --transport http conferences http://localhost:8124/mcp
```

## Review assistant

The page of a proposal in the back office shows the most similar talks and proposals of all
conferences, and notes written by a model: a summary, tags and a track. It uses the Platform,
Agent and Store components of Symfony AI, with the vectors in PostgreSQL (pgvector), and
[Hugging Face Inference Providers](https://huggingface.co/docs/inference-providers/index) or any
other provider. The tools of the assistant only read. Accepting or rejecting a proposal stays a
reviewer action, which asks for the password of the reviewer. Without an API key, a fake platform
answers.
[docs/ai.md](docs/ai.md) tells how to run it with Hugging Face:

```bash
printf 'AI_PLATFORM=huggingface\nHUGGINGFACE_API_KEY=hf_...\n' >> .env.local
symfony console assistant:index
```

The database container needs the pgvector image of `compose.yaml`: [docs/ai.md](docs/ai.md)
tells how to create it again if it is older.

## Tests

```bash
symfony php bin/phpunit
```

The exchanges with the travel agency are replayed from `tests/Travel`; record them again
with `SYMFONY_HTTP_RECORDER=record` while the server runs.

## Where to look

| Feature | Code |
|---|---|
| Component bundles configured at their root keys, schema-checked configuration | `config/packages`, `config/schema.json` |
| Workflow declared with attributes, enum places, in-class listeners | `src/Workflow/ProposalWorkflow.php` |
| `#[AsFormType]` data classes, multi-step form flow, polymorphic collection | `src/Cfp`, `src/Form/ProposalFlowType.php` |
| `BoundsType` ranges of `DatePoint` dates | `src/Admin/ConferenceData.php` |
| Collection entries named with `entry_name`, `Unique` on objects | `src/Admin/ScheduleData.php` |
| OIDC login, recent authentication, POST logout and impersonation | `config/packages/security.yaml`, `src/Security` |
| Outbox, identity stamps, chained messages, dispatch on failure | `config/packages/messenger.yaml`, `src/MessageHandler` |
| Scheduler tasks, stateful schedule | `src/Scheduler`, `config/packages/scheduler.yaml` |
| `#[Lock]`, `#[RateLimit]`, signed and expiring links | `src/Controller/TicketController.php`, `templates/_macros.html.twig` |
| Standard Webhooks, remote event consumers | `src/RemoteEvent`, `src/Controller/FakePaymentProviderController.php` |
| ObjectMapper and JsonStreamer through `#[Serialize]`, `Link-Template`, api-catalog | `src/Api`, `src/Controller/Api` |
| Access tokens with scopes, protected resource metadata | `config/packages/security.yaml`, `src/Controller/Api/ProposalApiController.php` |
| MCP tools behind the API firewall, `#[IsGranted]` on tools | `src/Mcp`, `config/packages/mcp.yaml`, `docs/mcp.md` |
| Review assistant: an agent with read-only tools, embeddings in pgvector through Messenger | `src/Assistant`, `config/packages/ai.yaml`, `docs/ai.md` |
| Sub-commands, `InputFile[]`, enum list options | `src/Command/ConferenceCommands.php` |
| Columns encrypted with KeyManagement | `src/Entity/Speaker.php`, `config/packages/key_management.yaml` |
| HTTP cache with `Cache-Status` | `config/packages/framework.yaml` |
| `configure_container`, recorded HTTP exchanges, `MessengerAssertionsTrait`, `sendWebhook()`, `loginUser()` on the API firewall | `tests` |
