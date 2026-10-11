# Conferences, a Symfony 8.2 showcase

A conference app (call for papers, reviews, tickets, back office and API) written to
exercise the features of Symfony 8.2 the way an application would use them. What building
it found, bugs and rough edges of 8.2 included, is listed in [LEDGER.md](LEDGER.md) with
the pull requests that fix them. The Symfony ones are all merged in 8.2.x-dev.
`composer.json` takes DoctrineBundle from a local checkout of its 3.4 branch with its open
pull requests (`repositories.doctrine-bundle`); remove that entry to use the published
3.4.x-dev.

## Running it

Requirements: PHP 8.5 with the `intl`, `pdo_pgsql`, `amqp` and `sodium` extensions, the
[Symfony CLI](https://symfony.com/download) and Docker.

```bash
composer install
docker compose up -d --wait   # Postgres, Mailpit, Keycloak, RabbitMQ
symfony console doctrine:migrations:migrate -n
symfony console doctrine:fixtures:load -n
symfony serve -d --no-tls --port=8124
```

Use the port 8124: the Keycloak realm in `docker/keycloak` redirects there. The Symfony CLI
exposes the containers to the app and starts the Messenger worker declared in
`.symfony.local.yaml`; run the commands through it (`symfony console`, `symfony php`).
Mailpit shows the emails at the URL `symfony open:local:webmail` opens.

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
| `#[Lock]`, `#[RateLimit]`, signed and expiring links | `src/Controller/TicketController.php`, `src/Ticket` |
| Standard Webhooks, remote event consumers | `src/RemoteEvent`, `src/Controller/FakePaymentProviderController.php` |
| ObjectMapper, `#[Serialize]`, JsonStreamer, `Link-Template`, api-catalog | `src/Api`, `src/Controller/Api` |
| Access tokens with scopes, protected resource metadata | `config/packages/security.yaml`, `src/Controller/Api/ProposalApiController.php` |
| Sub-commands, `InputFile[]`, enum list options | `src/Command/ConferenceCommands.php` |
| Columns encrypted with KeyManagement | `src/Entity/Speaker.php`, `config/packages/key_management.yaml` |
| HTTP cache with `Cache-Status` | `config/packages/framework.yaml` |
| `configure_container`, recorded HTTP exchanges, `MessengerAssertionsTrait` | `tests` |
