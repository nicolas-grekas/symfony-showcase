# Conferences, the Symfony 8.2 showcase on Symfony 8.1

This branch is the app of the `main` branch ported back to Symfony 8.1: same features, same
tests, plus the tests of the code 8.1 needs and 8.2 provides. Each Symfony 8.2 feature the app
uses is replaced in its own commit by what one would write on 8.1, so `git log main..` lists
them and `git diff main` shows what 8.2 saves. `composer.json` takes DoctrineBundle from a
local checkout of its 3.4 branch with its open pull requests (`repositories.doctrine-bundle`);
remove that entry to use the published 3.4.x-dev.

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
with `RECORD_HAR=1` while the server runs.

## Where to look

| Feature | Code |
|---|---|
| Workflow declared in the configuration, listeners naming it | `config/packages/workflow.yaml`, `src/Workflow` |
| Form type classes, multi-step form flow, collection of slides and videos | `src/Form`, `src/Cfp` |
| Ranges of dates, schedule keyed by proposal | `src/Form/PeriodType.php`, `src/Controller/Admin/ScheduleController.php` |
| OpenID Connect login, recent authentication, POST logout and impersonation | `config/packages/security.yaml`, `src/Security` |
| Outbox, correlation ids, follow-up messages, alert on failure | `config/packages/messenger.yaml`, `src/Messenger`, `src/MessageHandler`, `src/EventListener` |
| Stateful schedule | `src/Schedule.php`, `src/Scheduler` |
| Lock, rate limit and signed links of the tickets | `src/Controller/TicketController.php`, `src/Ticket`, `config/packages/lock.yaml` |
| Standard Webhooks, both directions | `src/Webhook`, `src/RemoteEvent` |
| API resources, api-catalog, problem documents, `Cache-Status` | `src/Api`, `src/Controller/Api`, `src/HttpCache` |
| Access tokens with scopes, protected resource metadata | `config/packages/security.yaml`, `src/Security/AccessToken*`, `src/Controller/Api/ProtectedResourceMetadataController.php` |
| Encrypted phone numbers | `src/Encryption`, `src/Entity/Speaker.php` |
| Messenger assertions, replayed HTTP exchanges | `tests/MessengerAssertionsTrait.php`, `tests/HarRecorder.php` |
