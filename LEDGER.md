# Symfony 8.2 showcase ledger

What the app exercises, and what building it found.

## Setup

- Skeleton: `symfony new --webapp --version=next --docker`, Symfony 8.2.x-dev, PHP 8.5.
- Symfony sources: `php link` to the `showcase` branch of a symfony/symfony worktree
  (`~/Code/symfony-worktrees/showcase`), which is 8.2 plus the pending fixes below.
- `composer require` of a non-Symfony package keeps the links; re-run `link` after
  requiring a Symfony package.
- Linked sources are not a vendor directory, so the dev container tracks every framework
  file: dev boot is slow, never benchmark this setup.

## Coverage

| Slice | Features | State |
|---|---|---|
| 0. Skeleton | component bundles at their root keys, `lint:yaml --check-schema`, `debug:config`, `config:dump-reference`, `debug:container --env-vars`, sub-commands (`cache pool list`) | done |
| 3. Async | outbox transport in front of RabbitMQ, identity stamps (correlation id quoted in emails), `#[AsMessageHandler(after:)]`, stamp arguments of handlers, `ChainStamp`, `DispatchOnFailureStamp`, `logging` and `doctrine_dbal_transaction` middleware, `FailedMessageRepository`, `#[AsCronTask(env:)]`, `scheduler.schedules` (stateful, last missed run), `use_messenger_routing`, rate-limited mailer transport, Postgres advisory locks on the Doctrine connection, `Valid(restrictGroups: false)`, `debug:scheduler` "Next Run In", `debug:messenger` routing, `MessengerAssertionsTrait` | done |
| 2. Security | `oidc_login` against Keycloak (users provisioned from the claims through the entity provider, RP-initiated logout, token refresh, back-channel logout), `form_login`, `recent_authentication_lifetime` + `Vote::requestReAuthentication()` with the login form re-asking the password only, role hierarchy wildcard (`ROLE_REVIEWER_*`), `user_checker_on_refresh`, POST-only `logout_form()`, `impersonation_form()` and `impersonation_exit_form()`, `is_granted_for_user(null, ...)`, `#[IsGranted(statusCode: 404)]`, `#[IsCsrfTokenValid]`, `debug:roles` | done |
| 4. Tickets | `#[Lock]` with an Expression key on a Postgres advisory lock (blocking), `#[RateLimit]` per user with exposed headers, `#[IsCsrfTokenValid(methods:)]`, Webhook sending side and `RemoteEvent` consumers with Standard Webhooks signatures (`signature_format: standard`, `whsec_` secrets), `#[AsRemoteEventConsumer]`, `UriSigner` expiring links + `#[IsSignatureValid]`, `UuidV7::createBoundaries()` and ids generated from the clock, `#[AsPeriodicTask]`, `#[MapQueryParameter]`, `ClockSensitiveTrait::mockTime()` in functional tests | done |
| 1. CFP | `#[AsWorkflow]` with enum places and in-class guard/completed listeners, `#[AsFormType]`/`#[FormField]` data classes, FormFlow with a step group, `PolymorphicCollectionType` with an entry type provider, `choice_help`, enum type guessing, `#[MapEntity]` closure and list, `DatePoint` and `day_point` columns, UUIDv7, Doctrine entity auto-discovery (DoctrineBundle 3.4) | done |

## Findings

| # | Finding | Kind | Status |
|---|---|---|---|
| 1 | Recipes write `framework.cache`, `framework.messenger`, `framework.validation`... while 8.2 owns these keys at the root | recipe | symfony/recipes#1592 (draft until 8.2 ships); the webapp-pack and third-party recipes (DoctrineBundle...) keep `framework.*` |
| 2 | `debug:container --env-vars` reports `APP_ENV`, `APP_SHARE_DIR` (and `KERNEL_CLASS` in test) as unused | bug, 8.2 | symfony/symfony#66747 |
| 3 | `debug:config framework cache` fails, `config:dump-reference framework` shows `cache: ~` with no hint | DX, 8.2 | symfony/symfony#66748 |
| 4 | `lint:yaml config --check-schema` rejects 5 of the 24 files of the skeleton, all valid | bug, 8.2 and 7.4 | symfony/symfony#66746 (8.2), symfony/symfony#66745 (7.4) |
| 5 | `cache:warmup --env=prod` logs a deprecation: DoctrineBundle extends the deprecated FrameworkBundle cache warmer | bug, third party | upstream: doctrine/DoctrineBundle#2289, open since 2026-09-16 |
| 6 | Schema validation needs `opis/json-schema`, which the webapp pack does not install, and is opt-in with `--check-schema` | DX, pack | symfony/webapp-pack#23 |
| 7 | Recipe text: `ux_turbo.yaml` reuses the comment of `csrf.yaml`, `notifier.yaml` mentions `sms/nexmo`, the mailer post-install text says `framework.messenger.routing` and `doc/master` | recipe | symfony/recipes#1591 |
| 8 | `debug:roles` (new) and `debug:security:role-hierarchy` overlap; `debug:roles` is the only description ending with a period | DX, 8.2 | symfony/symfony#66749 |
| 9 | `make:auth` is described as "Create a Guard authenticator" | MakerBundle | open |
| 10 | `composer require`/`remove` of a component fails its own `cache:clear` script: in debug mode the kernel reuses the cached list of bundles while rebuilding a stale container | bug, 8.1 | symfony/symfony#66750 |
| 11 | The Doctrine bridge maps `Clock\DayPoint` and `Clock\TimePoint` to Doctrine types, but these classes don't exist (the changelog too) | bug, 8.2 | symfony/symfony#66751 |
| 12 | `#[FormField]` relies on the type guessers, which ignore the declared PHP type: an `?int` property becomes a text field (enums are guessed) | DX, 8.2 | open: a TypeInfo-based guesser would make `#[AsFormType]` classes much shorter |
| 13 | Neither FrameworkBundle nor DoctrineBundle registers the new `CollectionDenormalizer` | DX, 8.2 | open: to check in the API slice |
| 14 | `IterableToArrayCollection` wraps a `MapCollection` that builds its own `new ObjectMapper()` instead of using the app's | to check | open: API slice |
| 15 | On 8.2 the workflow recipe's `framework.workflows: null` file is useless: WorkflowBundle is enabled by default and `#[AsWorkflow]` needs no YAML | recipe | symfony/recipes#1592 should drop the file (see the script for the user) |
| 16 | DomCrawler assertions wrapped in `LogicalAnd` print the exported `Crawler` object on failure | DX, all branches | noted |
| 18 | A `User::__serialize()` that hashes the password the MakerBundle way logs out the users who have none (e.g. those of `oidc_login`): `hash('crc32c', '')` never matches a `null` password | app gotcha | fixed in the app; MakerBundle's `make:user` template could skip the hash for a null password |
| 19 | `logout_form()`/`impersonation_form()` return `{action, fields}`: every app writes the same macro, and nothing tells which field is the CSRF token the `csrf-protection` Stimulus controller should handle | DX, 8.2 | noted |
| 20 | A recent-authentication check on a POST action sends the user to the homepage once the password is confirmed (no target path is saved for unsafe methods): guard a GET page instead | doc | noted, done that way in the app |
| 21 | `re_authentication_entry_point` only takes a service id, while `entry_point` also takes an authenticator key such as `form_login` | DX, 8.2 | open: small fix in `RegisterEntryPointPass` |
| 22 | `is_granted_for_user(null, 'IS_AUTHENTICATED_FULLY')` throws instead of answering no for a guest | DX, 8.2 | noted |
| 23 | `AdminRecipientsProviderInterface` has no autowiring alias: sending to `notifier.admin_recipients` needs an `instanceof` check on `NotifierInterface` | DX, 8.2 | symfony/symfony#66752 |
| 24 | The webapp pack routes urgent notifications to email, but `NotificationEmail` throws without `twig/cssinliner-extra` and `twig/inky-extra`, which it does not install | DX, pack | open |
| 25 | The in-memory transports are reset by the next request of the test client, so `MessengerAssertionsTrait` checks must run before it | doc | noted |
| 26 | On 8.2 the scheduler recipe could drop its `Schedule` class, which hardcodes `stateful()`/`processOnlyLastMissedRun()`, for `scheduler.schedules` | recipe | open (with symfony/recipes#1573 for `use_messenger_routing`) |
| 27 | A form flow does not validate the objects of its steps unless the root data class adds `Valid(groups: [<step>], restrictGroups: false)` to each | doc, 8.2 | noted |
| 17 | In a `#[MapEntity]` closure, `{slug:conference}` exposes the slug as the `conference` request attribute, not `slug` | doc | noted |
| 28 | `webhook.routing.<type>.service` is required, even to use the generic `webhook.request_parser`: a routing entry with only a secret could default to it | DX, 8.2 | symfony/symfony#66753 |
| 29 | Limiters keep their state in `cache.rate_limiter`, a filesystem pool that outlives the tests: each test has to clear it | doc | noted, cleared in `setUp()` |
| 30 | A `TemplatedEmail` queued through Messenger is rendered when the worker sends it: tests of queued emails read `getContext()`, and entities put in the context are serialized into the queue | doc | noted |
| 31 | No Twig helper signs a URL: signed links are built in PHP and passed to templates | DX | noted |
| 32 | `lint:yaml --check-schema` rejects the documented shorthands `rate_limiter: {<name>: ...}` and `lock: {service_id: ..., advisory: true}`: the dumper cannot see the closures that wrap them into `limiters`/`resources` | bug, 8.2 | in progress |
| 33 | `IterableToArrayCollection` (Doctrine bridge, new in 8.2) is not `ObjectMapperAwareInterface`: the `MapCollection` it wraps maps the elements with a `new ObjectMapper()` instead of the app's | bug, 8.2 | in progress (was #14) |
