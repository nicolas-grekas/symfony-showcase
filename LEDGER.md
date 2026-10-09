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
| 10. Privacy | KeyManagement: sodium KMS from a DSN, store of wrapped data keys, `EncryptedTypes` of the Doctrine DBAL bridge, schema of the key table picked up by migrations | done |
| 2. Security | `oidc_login` against Keycloak (users provisioned from the claims through the entity provider, RP-initiated logout, token refresh, back-channel logout), `form_login`, `recent_authentication_lifetime` + `Vote::requestReAuthentication()` with the login form re-asking the password only, role hierarchy wildcard (`ROLE_REVIEWER_*`), `user_checker_on_refresh`, POST-only `logout_form()`, `impersonation_form()` and `impersonation_exit_form()`, `is_granted_for_user(null, ...)`, `#[IsGranted(statusCode: 404)]`, `#[IsCsrfTokenValid]`, `debug:roles` | done |
| 4. Tickets | `#[Lock]` with an Expression key on a Postgres advisory lock (blocking), `#[RateLimit]` per user with exposed headers, `#[IsCsrfTokenValid(methods:)]`, Webhook sending side and `RemoteEvent` consumers with Standard Webhooks signatures (`signature_format: standard`, `whsec_` secrets), `#[AsRemoteEventConsumer]`, `UriSigner` expiring links + `#[IsSignatureValid]`, `UuidV7::createBoundaries()` and ids generated from the clock, `#[AsPeriodicTask]`, `#[MapQueryParameter]`, `ClockSensitiveTrait::mockTime()` in functional tests | done |
| 5. API | ObjectMapper DTOs with `#[Map(source:)]` and the enum to scalar conversion, `#[Serialize]` with groups and `enable_default_groups`, `#[Cache]`, RFC 9652 `Link-Template` through `addLink()`, JsonStreamer `StreamWriterInterface` with UUIDs, `Response::cacheControl('CDN')`, RFC 9727 api-catalog with `JsonLinksetSerializer`, `access_token` + `oidc` token handler with discovery and `check_issuer`, `OAUTH2_SCOPE()` with the `insufficient_scope` challenge, RFC 9728 `resource_metadata`, `#[MapRequestPayload]` with a `+json` media type and violation paths, `DiscriminatorMap` extended by `#[DiscriminatorMapType]` on the subclasses | done |
| 6. CLI | class-level `#[AsCommand]` grouping method-level ones, docker-style sub-commands (`conference list`), `InputFile[]` arguments, enum list options from `@param list<Enum>` | done |
| 8. Prod | `cache:clear --env=prod` (no deprecation), `lint:container`, `lint:expressions`, `lint:twig`, `debug:container --env-vars`, `http_cache` with `cache_status` (RFC 9211), JsonStreamer and ObjectMapper cache warmers | done |
| 9. Back office | `BoundsType` ranges of `date_point` dates, `MoneyType`, `UniqueEntity` on a DTO with `entityClass` | done |
| 7. Tests | `configure_container` of `createClient()`, `RecorderHttpClient` with `#[UseRecord]` (HAR replayed offline), `ClockSensitiveTrait::mockTime()`, `MessengerAssertionsTrait`, `ApplicationTester` on sub-commands | done |
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
| 9 | `make:auth` is described as "Create a Guard authenticator" | MakerBundle | symfony/maker-bundle#1844 |
| 10 | `composer require`/`remove` of a component fails its own `cache:clear` script: in debug mode the kernel reuses the cached list of bundles while rebuilding a stale container | bug, 8.1 | symfony/symfony#66750 |
| 11 | The Doctrine bridge maps `Clock\DayPoint` and `Clock\TimePoint` to Doctrine types, but these classes don't exist (the changelog too) | bug, 8.2 | symfony/symfony#66751 |
| 12 | `#[FormField]` relies on the type guessers, which ignore the declared PHP type: an `?int` property becomes a text field (enums are guessed) | DX, 8.2 | open: a TypeInfo-based guesser would make `#[AsFormType]` classes much shorter |
| 13 | Neither FrameworkBundle nor DoctrineBundle registers the new `CollectionDenormalizer` | DX, 8.2 | open: DoctrineBundle should register it |
| 14 | `IterableToArrayCollection` wraps a `MapCollection` that builds its own `new ObjectMapper()` instead of using the app's | bug, 8.2 | symfony/symfony#66755 |
| 15 | On 8.2 the workflow recipe's `framework.workflows: null` file is useless: WorkflowBundle is enabled by default and `#[AsWorkflow]` needs no YAML | recipe | symfony/recipes#1592 should drop the file (see the script for the user) |
| 16 | DomCrawler assertions wrapped in `LogicalAnd` print the exported `Crawler` object on failure | DX, all branches | noted |
| 18 | A `User::__serialize()` that hashes the password the MakerBundle way logs out the users who have none (e.g. those of `oidc_login`): `hash('crc32c', '')` never matches a `null` password | app gotcha | symfony/symfony#66763 (the example of the interface), symfony/maker-bundle#1845 |
| 19 | `logout_form()`/`impersonation_form()` return `{action, fields}`: every app writes the same macro, and nothing tells which field is the CSRF token the `csrf-protection` Stimulus controller should handle | DX, 8.2 | noted |
| 20 | A recent-authentication check on a POST action sends the user to the homepage once the password is confirmed (no target path is saved for unsafe methods): guard a GET page instead | doc | noted, done that way in the app |
| 21 | `re_authentication_entry_point` only takes a service id, while `entry_point` also takes an authenticator key such as `form_login` | DX, 8.2 | symfony/symfony#66754 |
| 22 | `is_granted_for_user(null, 'IS_AUTHENTICATED_FULLY')` throws instead of answering no for a guest | bug, 8.2 | symfony/symfony#66764 |
| 23 | `AdminRecipientsProviderInterface` has no autowiring alias: sending to `notifier.admin_recipients` needs an `instanceof` check on `NotifierInterface` | DX, 8.2 | symfony/symfony#66752 |
| 24 | The webapp pack routes urgent notifications to email, but `NotificationEmail` throws without `twig/cssinliner-extra` and `twig/inky-extra`, which it does not install | DX, pack | symfony/webapp-pack#24 |
| 25 | The in-memory transports are reset by the next request of the test client, so `MessengerAssertionsTrait` checks must run before it | doc | noted |
| 26 | On 8.2 the scheduler recipe could drop its `Schedule` class, which hardcodes `stateful()`/`processOnlyLastMissedRun()`, for `scheduler.schedules` | recipe | draft PR in the script for the user (with symfony/recipes#1573 for `use_messenger_routing`) |
| 27 | A form flow does not validate the objects of its steps unless the root data class adds `Valid(groups: [<step>], restrictGroups: false)` to each | doc, 8.2 | noted |
| 17 | In a `#[MapEntity]` closure, `{slug:conference}` exposes the slug as the `conference` request attribute, not `slug` | doc | noted |
| 28 | `webhook.routing.<type>.service` is required, even to use the generic `webhook.request_parser`: a routing entry with only a secret could default to it | DX, 8.2 | symfony/symfony#66753 |
| 29 | Limiters keep their state in `cache.rate_limiter`, a filesystem pool that outlives the tests: each test has to clear it | doc | noted, cleared in `setUp()` |
| 30 | A `TemplatedEmail` queued through Messenger is rendered when the worker sends it: tests of queued emails read `getContext()`, and entities put in the context are serialized into the queue | doc | noted |
| 31 | No Twig helper signs a URL: signed links are built in PHP and passed to templates | DX | noted |
| 32 | `lint:yaml --check-schema` rejects the documented shorthands `rate_limiter: {<name>: ...}` and `lock: {service_id: ..., advisory: true}`: the dumper cannot see the closures that wrap them into `limiters`/`resources` (`semaphore` too; `workflow: {<name>: ...}` remains) | bug, 8.2 | symfony/symfony#66760 (draft, on top of #66746) |
| 33 | With the mapper of the container, a `#[Map(transform:)]` on a property of a `#[Map(source: X)]` target is ignored unless it names its `source`: the reverse class map skips it (a bare `new ObjectMapper()` applies it) | bug, 8.1 | symfony/symfony#66756 |
| 34 | With `enable_default_groups`, `groups: ['Default', 'detail']` drops the attributes without `#[Groups]`, and nested objects with none at all serialize as `[]`: the default groups only apply when no custom group is requested, unlike in the Validator | bug, 8.2 | symfony/symfony#66757 |
| 35 | The `oidc` token handler builds the discovery URL relatively to `base_uri`: `http://kc/realms/showcase` fetches `http://kc/realms/.well-known/openid-configuration`, only a trailing slash works around it, even with `check_issuer` (`oidc_user_info` too) | bug, 7.4 | symfony/symfony#66758 |
| 36 | The Symfony CLI exports `DEFAULT_URI` with a trailing slash, the routing recipe writes it without one: `'%env(DEFAULT_URI)%/path'` is right with one and wrong with the other | DX, CLI or recipe | noted |
| 37 | JSON errors are RFC 9457 problem documents served as `application/json`, not `application/problem+json` | DX | noted, long-standing |
| 38 | `new ObjectMapper()` does not convert enums to scalars: only the mapper of the container does, through `EnumMappingMetadataFactory` | DX, 8.2 | noted |
| 39 | `EnumMappingMetadataFactory::injectMapEnum()` rebuilds the `Mapping` without its `targetClass`: with a class map of several targets, a conversion made for one target may apply to another | to check, 8.2 | open |
| 40 | Importing a Keycloak realm that declares one client scope replaces all the default ones: the realm file has to list them (`profile`, `email`, `basic`...) | Keycloak | noted |
| 41 | Console errors are split at the terminal width in the middle of words ("ar e") | DX, all branches | noted |
| 42 | The help of an option mapped to a list of enum cases does not list the accepted values, which only the error message tells | DX, 8.2 | noted |
| 43 | `BoundsType` renders neither the label nor the help of the range, only "From" and "To": two ranges in a form cannot be told apart | bug, 8.2 | symfony/symfony#66761 |
| 44 | A cacheable `StreamedResponse` gets `Cache-Status: ...; fwd=miss; stored` on every request: `Store::write()` skips the responses that cannot provide their content, but `HttpCache` traces them as stored | bug, 6.4 (header 8.2) | symfony/symfony#66759 |
| 45 | `php -S` with the usual `variables_order = "GPCS"` leaves the env vars out of `$_SERVER`, so the app reads `.env` instead of the vars of the Symfony CLI: run it with `-d variables_order=EGPCS` | environment | noted |
| 46 | Encrypting a column with KeyManagement needs an `EncryptedTypes` service declared by hand and a `Kernel::boot()` override calling `register()`: the bundle could register the types it is configured with when it boots | DX, 8.2 (experimental) | noted |
| 47 | `Unique` compares the values it reads with `===`: with `fields` on objects, two equal `DatePoint` never collide, so a `normalizer` has to turn them into strings | DX | noted, worked around in `SlotData::place()` |
| 48 | `key_management.clients.<name>.dsn` refuses `%env(KMS_DSN)%`: the node combines `cannotBeEmpty()` with a `validate()` closure, on which Config refuses env vars | bug, 8.2 (experimental) | symfony/symfony#66762 |
