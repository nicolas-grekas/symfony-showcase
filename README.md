# Conferences, the Symfony 8.2 showcase on Symfony 7.4

This branch is the app of the `symfony-8.1` branch ported back to Symfony 7.4, the long-term
support version: same features, same tests, plus the tests of the code 7.4 needs. Each Symfony
8.0 or 8.1 feature the 8.1 version uses is replaced in its own commit by what one would write
on 7.4, so `git log symfony-8.1..` lists them, `git diff symfony-8.1` shows what 8.0 and 8.1
save, and `git diff main` what the whole 8.x series saves. `composer.json` takes DoctrineBundle
from a local checkout of its 3.4 branch with its open pull requests
(`repositories.doctrine-bundle`); remove that entry to use the published 3.4.x-dev.

One behavior differs from 8.1: the Doctrine and AMQP transports of 7.4 delete a message they
cannot decode, where 8.1 retries it and then sends it to the failure transport. Such a message
never shows in the failed messages of the back office.

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
