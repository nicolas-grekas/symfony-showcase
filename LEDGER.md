# Symfony 8.2 showcase ledger

What the app exercises, and what building it found.

## Setup

- Skeleton: `symfony new --webapp --version=next --docker`, Symfony 8.2.x-dev, PHP 8.5.
- Symfony sources: `php link` to the `showcase` branch of a symfony/symfony worktree
  (`~/Code/symfony-worktrees/showcase`): 8.2 plus the fixes the app needs that are not merged
  yet, cherry-picked with `-x` (`git log origin/8.2..showcase` lists them; none on 2026-10-12).
- The `symfony-8.1` branch is the same app on Symfony 8.1, compared in `docs/8.2-vs-8.1.md`.
- `composer require` of a non-Symfony package keeps the links; re-run `link` after
  requiring a Symfony package.
- Linked sources are not a vendor directory, so the dev container tracks every framework
  file: dev boot is slow, never benchmark this setup.

## Coverage

| Slice | Features | State |
|---|---|---|
| 0. Skeleton | component bundles at their root keys, `lint:yaml --check-schema`, `debug:config`, `config:dump-reference`, `debug:container --env-vars`, sub-commands (`cache pool list`) | done |
| 1. CFP | `#[AsWorkflow]` with enum places and in-class guard/completed listeners, `#[AsFormType]`/`#[FormField]` data classes, FormFlow with a step group, `PolymorphicCollectionType` with an entry type provider, `choice_help`, enum type guessing, `#[MapEntity]` closure and list, `DatePoint` and `day_point` columns, UUIDv7 (Doctrine entity auto-discovery, doctrine/DoctrineBundle#2277, dropped while it is not merged, so that the app installs from Packagist) | done |
| 2. Security | `oidc_login` against Keycloak (users provisioned from the claims through the entity provider, RP-initiated logout, token refresh, back-channel logout), `form_login`, `recent_authentication_lifetime` + `Vote::requestReAuthentication()` with the login form re-asking the password only, role hierarchy wildcard (`ROLE_REVIEWER_*`), `user_checker_on_refresh`, POST-only `logout_button()`, `impersonation_button()` and `impersonation_exit_button()`, `is_granted_for_user(null, ...)`, `#[IsGranted(statusCode: 404)]`, `#[IsCsrfTokenValid]`, `debug:roles` | done |
| 3. Async | outbox transport in front of RabbitMQ, identity stamps (correlation id quoted in emails), `#[AsMessageHandler(after:)]`, stamp arguments of handlers, `ChainStamp`, `DispatchOnFailureStamp`, `logging` and `doctrine_dbal_transaction` middleware, `FailedMessageRepository`, `#[AsCronTask(env:)]`, `scheduler.schedules` (stateful, last missed run), `use_messenger_routing`, rate-limited mailer transport, notifications sent from the global `From` header of the mailer, Postgres advisory locks on the Doctrine connection, `Valid(restrictGroups: false)`, `debug:scheduler` "Next Run In", `debug:messenger` routing, `MessengerAssertionsTrait` | done |
| 4. Tickets | `#[Lock]` keyed with a `{conference}` placeholder on a Postgres advisory lock (blocking), `#[RateLimit]` per user with exposed headers, `#[IsCsrfTokenValid(methods:)]`, Webhook sending side and `RemoteEvent` consumers with Standard Webhooks signatures (`signature_format: standard`, `whsec_` secrets), `#[AsRemoteEventConsumer]`, `UriSigner` expiring links + `#[IsSignatureValid]`, `UuidV7::createBoundaries()` and ids generated from the clock, `#[AsPeriodicTask]`, `#[MapQueryParameter]`, `ClockSensitiveTrait::mockTime()` in functional tests | done |
| 5. API | ObjectMapper DTOs with `#[Map(source:)]` and the enum to scalar conversion, `#[Serialize]` with groups and `enable_default_groups`, `#[Cache]`, `#[Cache(target: 'CDN')]` for the RFC 9213 `CDN-Cache-Control` header, RFC 9652 `Link-Template` through `addLink()`, `#[Serialize]` mapping results with the ObjectMapper (`map:`) and streaming them with JsonStreamer (`stream: true`), UUIDs streamed as strings, RFC 9727 api-catalog with `JsonLinksetSerializer`, `access_token` + `oidc` token handler with discovery (cached in `cache.security.discovery` by default) and `check_issuer`, `OAUTH2_SCOPE()` with the `insufficient_scope` challenge, RFC 9728 `resource_metadata`, `#[MapRequestPayload]` with a `+json` media type and violation paths, `DiscriminatorMap` extended by `#[DiscriminatorMapType]` on the subclasses | done |
| 6. CLI | class-level `#[AsCommand]` grouping method-level ones, docker-style sub-commands (`conference list`), `InputFile[]` arguments, enum list options from `@param list<Enum>` | done |
| 7. Tests | `configure_container` of `createClient()`, `RecorderHttpClient` with `#[UseRecord]` (HAR replayed offline), `ClockSensitiveTrait::mockTime()`, `MessengerAssertionsTrait` across kernel reboots, `sendWebhook()`, `loginUser()` on the `access_token` firewall with scopes, rate limiters and runtime caches reset between tests (`kernel.test_state`), `ApplicationTester` on sub-commands | done |
| 8. Prod | `cache:clear --env=prod` (no deprecation), `lint:container`, `lint:expressions`, `lint:twig`, `debug:container --env-vars`, `http_cache` with `cache_status` (RFC 9211), JsonStreamer and ObjectMapper cache warmers | done |
| 9. Back office | `BoundsType` ranges of `date_point` dates, `MoneyType`, `UniqueEntity` on a DTO with `entityClass` | done |
| 10. Privacy | KeyManagement: sodium KMS from a DSN, store of wrapped data keys, `EncryptedTypes` of the Doctrine DBAL bridge, schema of the key table picked up by migrations | done |
| 11. Showcase | the "Symfony 8.2" panel of the web profiler listing the 8.2 features of each request (`src/Showcase`), the `symfony-8.1` branch and `docs/8.2-vs-8.1.md` | done |

## Findings

Still open. The findings that are fixed or settled are in the [history](#history).

| # | Finding | Kind | Status |
|---|---|---|---|
| 1 | Recipes write `framework.cache`, `framework.messenger`, `framework.validation`... while 8.2 owns these keys at the root | recipe | symfony/recipes#1592; the webapp-pack and third-party recipes (DoctrineBundle...) keep `framework.*` |
| 6 | Schema validation needs `opis/json-schema`, which the webapp pack does not install, and is opt-in with `--check-schema` | DX, pack | symfony/webapp-pack#23 |
| 7 | Recipe text: `ux_turbo.yaml` reuses the comment of `csrf.yaml`, `notifier.yaml` mentions `sms/nexmo`, the mailer post-install text says `framework.messenger.routing` and `doc/master` | recipe | symfony/recipes#1591 |
| 17 | In a `#[MapEntity]` closure, `{slug:conference}` exposes the slug as the `conference` request attribute, not `slug` | doc | symfony/symfony-docs#23249 |
| 19 | `logout_form()`/`impersonation_form()` return `{action, fields}`: every app writes the same macro, and nothing tells which field is the CSRF token the `csrf-protection` Stimulus controller should handle | DX, 8.2 | symfony/symfony#66767 (merged) (phpdoc), symfony/symfony#66786 (merged) (`logout_button()`, `impersonation_button()`, `impersonation_exit_button()`); the buttons are documented in symfony/symfony-docs#23306, which replaces the macro of #23248 (closed) |
| 20 | A recent-authentication check on a POST action sends the user to the homepage once the password is confirmed (no target path is saved for unsafe methods): guard a GET page instead | doc | symfony/symfony-docs#23249 |
| 24 | The webapp pack routes urgent notifications to email, but `NotificationEmail` throws without `twig/cssinliner-extra` and `twig/inky-extra`, which it does not install | DX, pack | symfony/webapp-pack#24 |
| 25 | The in-memory transports are reset by the next request of the test client, so `MessengerAssertionsTrait` checks must run before it | doc | symfony/symfony-docs#23250 for 6.4 to 8.1; on 8.2 the transports keep their messages across reboots (symfony/symfony#66789 (merged)) |
| 26 | On 8.2 the scheduler recipe could drop its `Schedule` class, which hardcodes `stateful()`/`processOnlyLastMissedRun()`, for `scheduler.schedules` | recipe | symfony/recipes#1593 (with symfony/recipes#1573 for `use_messenger_routing`) |
| 29 | Limiters keep their state in `cache.rate_limiter`, a filesystem pool that outlives the tests: each test has to clear it | doc | symfony/symfony-docs#23250 for 6.4 to 8.1; on 8.2 the limiters are reset between tests (symfony/symfony#66801 (merged)) |
| 35 | The `oidc` token handler builds the discovery URL relatively to `base_uri`: `http://kc/realms/showcase` fetches `http://kc/realms/.well-known/openid-configuration`, only a trailing slash works around it, even with `check_issuer` (`oidc_user_info` too) | doc, 7.4 | symfony/symfony#66758 (merged) (the description of `base_uri`) and symfony/symfony-docs#23255 say it must end with a slash; fixing it in code was turned down |
| 36 | The Symfony CLI exports `DEFAULT_URI` with a trailing slash, the routing recipe writes it without one: `'%env(DEFAULT_URI)%/path'` is right with one and wrong with the other | DX, Symfony CLI | symfony-cli/symfony-cli#778 |
| 54 | `ChainStamp` and `DispatchOnFailureStamp` have no documentation; `sign_url`, `#[AsFormType]`, `PolymorphicCollectionType`, `#[Lock]`, `#[AsWorkflow]` and the identity stamps are documented in open pull requests only | doc, 8.2 | symfony/symfony-docs#23264, #22909, #22695, #23265, #23214, #23147; none for the two stamps |
| 57 | Resolving an env var on a `ContainerBuilder` before compilation (e.g. in a `prepend()` method) stores an empty `container.env_var_processors_locator` that hides the processors registered later: `compile(true)` then fails with "Unsupported env var prefix" for a custom processor (found while making `framework.test` resolve at compile time, symfony/symfony#66893) | bug, 6.4 | symfony/symfony#66894 |
| 58 | The API routes ran about as many instructions on 8.2 as on 7.4 while the other pages ran 15 to 36% fewer: the ObjectMapper sends every property through the PropertyAccessor (2 M of the 9.3 M instructions of the conference API), `PropertyAccessor::isReadable()` parses its path on every call, the `#[Serialize]` listener builds the serializer, the stream writer and the ObjectMapper whatever the response needs, and since 8.1 the polyfill of `deepclone_from_array()` rebuilds the cached metadata at 2.6 times the cost of the code 7.4 generated (the sources mapped in reverse also make the ObjectMapper walk the properties of the source instead of the target, left to its maintainers) | perf, 8.2 | symfony/symfony#66907 (ObjectMapper, -24% on the conference API), #66904 (`isReadable()`), #66906 (`#[Serialize]` listener), symfony/polyfill#716 (-27% on the rebuilt entries) |

## Ideas for 8.3

What would remove the workarounds, the "remember to" comments and the boilerplate still in the
app. What is done is in the [history](#history).

| # | Idea | Seen in | Status |
|---|---|---|---|
| 3 | A DomCrawler helper to add a collection entry from its `data-prototype` | `CfpControllerTest` posts the materials by hand with `getPhpValues()` | idea |
| 4 | Let a form flow validate only the object of its current step, so a plain `#[Assert\Valid]` works for both the flow and the API | `ProposalDraft` scopes each `Valid` to its step with `restrictGroups: false`, the API lists every step in `validationGroups` (finding 27) | no clean design in Form alone: the validator cannot skip the cascade into some properties, and a flow does not know where its other steps map. The scoped `Valid` is documented in symfony/symfony-docs#23271; the Validator API is idea 12 |
| 5 | MakerBundle: `#[AsFormType]` data classes in `make:form`, `scheduler.schedules` instead of a `Schedule` class in `make:schedule` | `make:form` generates an `AbstractType` only, `make:schedule` the class symfony/recipes#1593 drops | idea |
| 10 | Recipes adding `# yaml-language-server: $schema=../schema.json` to `config/packages/*.yaml`, `lint:yaml` checking the schema by default when a validator is installed, the `workflow: {<name>: ...}` shorthand accepted by the schema | `config/schema.json` is generated but only `services.yaml` and `routes.yaml` reference a schema (findings 6, 32). The SchemaStore catalog (SchemaStore/schemastore#6225) covers `config/services*`, `config/routes*`, `config/serializer` and `config/validator`, pinned to the 8.1 branch, but cannot cover `config/packages`: its schema depends on the bundles of each app | headers: symfony/recipes#1592 and this app; the rest is merged: symfony/symfony#66785, #66788, #66790, #66791, #66792, #66793, #66798, #66799. Left: the serializer mapping schema rejects null where its loader ignores it, not found in real files |
| 11 | Once 8.2 ships, move the DoctrineBundle recipe and the `messenger.yaml` of the webapp pack to the root keys, like symfony/recipes#1592 | finding 1 | symfony/recipes#1594, with the MongoDB ODM bundle, ux-lazy-image, Reprise and Encore; new recipe versions that conflict with `symfony/framework-bundle <8.2`, so there is no need to wait for 8.2 |
| 12 | A Validator API to validate an object without cascading into some of its properties, and form flows that know which property each step maps, so that a plain `#[Assert\Valid]` works for both a flow and an API | `ProposalDraft` (idea 4): today the flow cannot skip the objects of the other steps | for 8.3: needs new API in Validator and Form |
| 16 | Better defaults so that the recipes write less YAML, keyed on `kernel.debug` or on the explicit `framework.test` flag, never on an environment or env var name (rule from the user, 2026-10-13) | 239 of the 379 YAML lines of the app come from the 8.2 recipes; removing each block and comparing the container showed about 30 recipe lines with no effect (`cache.yaml`, `property_info.yaml` and `dama_doctrine_test_bundle.yaml` entirely, the default retry strategy and bus of the webapp `messenger.yaml`, 8 lines of `doctrine.yaml`...) | symfony/symfony#66876 (router `strict_requirements`), #66877 (asset mapper `missing_import_mode`), #66878 (access token `issuers` and `authorization_servers`), #66879 (outbox DSN), #66880 (test session storage), #66881 (merged) (one security route loader; symfony/recipes#1597 fixes the 8.2 recipe that imported a loader id it removed), #66882 (merged) (test hashing costs and HIBP), #66887 (merged) (HTTP recorder on with `framework.test`, which makes symfony/recipes#1590 unnecessary), #66876 to #66880 (all merged), symfony/symfony#66893 (`framework.test` resolved at compile time), documented in symfony/symfony-docs#23316 to #23323; the recipes trimmed in symfony/recipes#1598 (the app went from 379 to 318 YAML lines); then the recipe lines they make unnecessary, and the no-op lines, in symfony/recipes#1592 and #1594; Monolog defaults (52 lines) and the DoctrineBundle ones are for those bundles |

## Vision beyond 8.3

The direction 8.2 already takes, pushed further: you write the domain and its rules, Symfony
derives the rest and checks it. Each area starts from what the app shows.

| # | Area | Seed in 8.2 and in the app | Further |
|---|---|---|---|
| A | Classes declare the app, config only holds what differs per deployment | 27 files in `config/packages`, most copied by recipes; the 8.2 `cache.yaml` recipe is only comments; encrypting one column needs a `key_management.doctrine_dbal.types` entry mapping a type name to a key | What belongs to a class sits on it (`#[Encrypted(key: 'speaker.contact')]` on the property); `config/` keeps DSNs, secrets, hosts and the few app-wide choices; an installed package is enabled with its defaults and copies no file. Dropping the `framework.` level was step one, dropping the file is step two: the schema and IDE completion replace the commented examples. First step: `#[Encrypted(key:)]`, symfony/symfony#66806 (merged) (8.2), which the app now uses instead of the `doctrine_dbal.types` entry |
| B | One typed model, every edge derived from it | `ProposalDraft` feeds the form flow and the API but the step names are repeated as validation groups; `TalkResource` is mapped by the ObjectMapper and streamed by JsonStreamer; enum options come from `@param list<Enum>`; `/api/docs` is a hand-written Twig page; the api-catalog, `Link-Template` and resource metadata are wired by hand | TypeInfo as the spine: from the types and constraints of a class, derive the form, the payload mapping, the response, the command input and a JSON Schema, like 8.2 derives `config/schema.json` from the config tree. An app with JSON routes describes itself by default (catalog, problem details, auth metadata, schema). Core derives the schema of plain classes, API Platform keeps the resource layer |
| C | Cache warmup compiles everything declarative | Warmed or compiled already: the container, the router, Twig, translations, the metadata of the Validator, the Serializer, PropertyInfo and the ObjectMapper, the JsonStreamer readers and writers (generated code), the expressions of controller attributes, security and `#[MapEntity]`, Doctrine proxies; builds are reproducible with `SOURCE_DATE_EPOCH` (symfony/symfony#66738, #66739, #66741, symfony/ux#4060 merged; twigphp/Twig#4987, #4988 open). Forms are the exception: nothing is warmed, and a 3-field form costs 7.6 times a plain route in prod (render 54%, `getForm()` 25%, `createView()` 15%) | Forms: precompute what only depends on the type hierarchy (block prefix chains, static resolved options of `#[AsFormType]` fields) without sharing per-field state across collections (the constraint of 2266ed72136). Validation and object mapping walk cached metadata at runtime; generating code for them like JsonStreamer does is possible but unmeasured: measure first |
| D | Tests run a simulation of the world | The app ships a fake identity provider and a fake payment provider, uses recorded HTTP and `mockTime()`, places its assertions around transport resets, clears the limiter pool in each test, and drives the queues one `consumeQueuedMessages()` at a time | Every boundary has a double Symfony ships (clock, HTTP, identity provider, webhooks, KMS, mailer, transports); state is isolated per test without asking; `settle()` runs the queues and the scheduler on a controlled clock until nothing is left, so async code is as easy to test as a request. Done in 8.2: symfony/symfony#66787, #66789, #66800, #66801, #66805, the webhook helpers `$client->sendWebhook()` and `assertWebhookSent()` (#66820), the `kernel.test_state` tag (#66839) and the pools it resets (#66843 to #66846), and the clock audit below |
| E | The app is a model tools can query | Most bugs of this run came from `lint:yaml --check-schema`, `debug:config`, `debug:container --env-vars` and `lint:container`, not from reading code | One machine-readable description of the app (routes, services, config, messages, workflows, firewalls, schedules), stable JSON output from every debug command, one `lint` command checking it all in CI. Coding agents now write part of the code and work best when conventions can be checked mechanically; an IDE or MCP layer on top is then thin |

What to watch for:

- Too many attributes: attributes for what belongs to the class, config for what differs per
  deployment, and the debug commands show what each attribute produced.
- Breaking changes: each step needs a migration path like the `framework.*` aliases, alias first,
  deprecation in a later minor.
- Scope: derive the building blocks, don't take over what API Platform or Doctrine provide.

D comes first: it makes every other change safer, and the app had the most friction there. A
continues the decomposition of FrameworkBundle.

## MakerBundle next

Coding agents adapt a good example to the context better than a template with options can, and
the templates of MakerBundle fall behind every release: `make:form` and `make:schedule` still
generate pre-8.2 code (idea 5), and symfony/maker-bundle#1844 and #1845 had to fix stale output.
Every new feature needs its own maker.

What makers still do well: steps that edit existing files predictably (`make:entity` adding
fields, `make:user` wiring `security.yaml`, `make:registration-form`), and serving people who
don't use an agent.

Proposal:

- Stop adding makers for new features; keep MakerBundle in maintenance for the existing ones.
- Ship short guides (skills) per component, with example code tested in CI so it cannot fall
  behind like the templates did. The code of this app could be that tested example set.
- Version them with the component, and let Flex install them the way recipes install config.
- Point to them from `AGENTS.md` and keep them in plain markdown: there is no common skills
  format across tools yet.
- Reconsider MakerBundle once the guides cover what its makers do.

## Clock audit (vision D, step 2)

Audit of origin/8.2 on 2026-10-11: what `mockTime()` of `ClockSensitiveTrait` moves. The
`clock` service, `new Clock()`, `Clock::get()` and `DatePoint` follow it. Already on the clock:
recent authentication, the OIDC, OAuth2 and DPoP checks, `oidc_login`, `UriSigner` (expiring
links, `#[IsSignatureValid]`, `sign_url`), the Messenger worker, `DelayStamp`, the in-memory
transport, the Scheduler, the Standard Webhooks signatures, the HTTP recorder.

The fixes made during the audit are all merged, see the [history](#history).

Convention (user, 2026-10-12): `private ?ClockInterface $clock = null` with a `time()`/`microtime(true)` fallback, so that ClockMock
also works. Applied to the classes new in 8.2 (symfony/symfony#66837) and to `debug:scheduler` (#66815). Kept non-null on purpose: the Worker and Scheduler (they `sleep()` on the
clock), value objects built by users (stamps, Notifier options, triggers), released `public readonly` clocks (`Acknowledger`).

Decision (user, 2026-10-12): the rest is not moved to `ClockInterface`; tests move it with
`ClockMock` of the PHPUnit bridge, which overrides `time()`, `microtime()`, `hrtime()`... in the
namespaces it registers. Done: "now" is built from `time()` where code used `new \DateTimeImmutable()`
(symfony/symfony#66857), `ClockMock` follows the `MockClock` of `mockTime()` so that one call moves
both clocks (symfony/symfony#66858), and the namespaces an app registers are documented
(symfony/symfony-docs#23301). What each area reads is in the [history](#history).

Real time on purpose: Lock and Semaphore TTLs (the backend enforces them), the idle connection
middleware of Doctrine, SMTP and AMQP keepalives, the worker restart signal, broker-side delays,
memcached absolute TTLs, process timeouts, mtime comparisons. Traps worth documenting:
`$clock ?? Clock::get()` binds the clock that is global when the object is built (`new Clock()`
does not); `$container->set('clock', new MockClock())` moves the injected users only, not
`Clock::get()` or `DatePoint`; `ClockMock` only follows a `MockClock` set after it is enabled;
`mockTime()` does not reach other processes.

## History

Done or settled. The numbers stay, so that the references of the other sections still point to them.

### Findings

| # | Finding | Kind | Status |
|---|---|---|---|
| 2 | `debug:container --env-vars` reports `APP_ENV`, `APP_SHARE_DIR` (and `KERNEL_CLASS` in test) as unused | bug, 8.2 | symfony/symfony#66747 (merged) |
| 3 | `debug:config framework cache` fails, `config:dump-reference framework` shows `cache: ~` with no hint | DX, 8.2 | symfony/symfony#66748 (merged) |
| 4 | `lint:yaml config --check-schema` rejects 5 of the 24 files of the skeleton, all valid | bug, 8.2 and 7.4 | symfony/symfony#66746 (merged) (8.2), symfony/symfony#66745 (merged) (7.4) |
| 5 | `cache:warmup --env=prod` logs a deprecation: DoctrineBundle extends the deprecated FrameworkBundle cache warmer | bug, third party | doctrine/DoctrineBundle#2289 (merged) |
| 8 | `debug:roles` (new) and `debug:security:role-hierarchy` overlap; `debug:roles` is the only description ending with a period | DX, 8.2 | symfony/symfony#66749 (merged) |
| 9 | `make:auth` is described as "Create a Guard authenticator" | MakerBundle | symfony/maker-bundle#1844 (merged) |
| 10 | `composer require`/`remove` of a component fails its own `cache:clear` script: in debug mode the kernel reuses the cached list of bundles while rebuilding a stale container | bug, 8.1 | symfony/symfony#66750 (merged) |
| 11 | The Doctrine bridge maps `Clock\DayPoint` and `Clock\TimePoint` to Doctrine types, but these classes don't exist (the changelog too) | bug, 8.2 | symfony/symfony#66751 (merged) |
| 12 | `#[FormField]` relies on the type guessers, which ignore the declared PHP type: an `?int` property becomes a text field (enums are guessed) | DX, 8.2 | symfony/symfony#66772 (merged) |
| 13 | Neither FrameworkBundle nor DoctrineBundle registers the new `CollectionDenormalizer` | DX, 8.2 | doctrine/DoctrineBundle#2297 (merged), opened by the author of the class |
| 14 | `IterableToArrayCollection` wraps a `MapCollection` that builds its own `new ObjectMapper()` instead of using the app's | bug, 8.2 | symfony/symfony#66755 (merged) |
| 15 | On 8.2 the workflow recipe's `framework.workflows: null` file is useless: WorkflowBundle is enabled by default and `#[AsWorkflow]` needs no YAML | recipe | done in symfony/recipes#1592 |
| 16 | DomCrawler assertions wrapped in `LogicalAnd` print the exported `Crawler` object on failure (BrowserKit cookie and route assertions dump the response or the client) | DX, all branches | symfony/symfony#66765 (merged) (8.2) |
| 18 | A `User::__serialize()` that hashes the password the MakerBundle way logs out the users who have none (e.g. those of `oidc_login`): `hash('crc32c', '')` never matches a `null` password | app gotcha | symfony/symfony#66763 (merged) (the example of the interface), symfony/maker-bundle#1845 (merged) |
| 21 | `re_authentication_entry_point` only takes a service id, while `entry_point` also takes an authenticator key such as `form_login` | DX, 8.2 | symfony/symfony#66754 (merged) |
| 22 | `is_granted_for_user(null, 'IS_AUTHENTICATED_FULLY')` throws instead of answering no for a guest | bug, 8.2 | symfony/symfony#66764 (merged) |
| 23 | `AdminRecipientsProviderInterface` has no autowiring alias: sending to `notifier.admin_recipients` needs an `instanceof` check on `NotifierInterface` | DX, 8.2 | symfony/symfony#66752 (merged) |
| 27 | A form flow does not validate the objects of its steps unless the root data class adds `Valid(groups: [<step>], restrictGroups: false)` to each | bug, 7.4 | symfony/symfony#66776 (merged) (the app keeps the scoped `Valid` constraints for its API) |
| 28 | `webhook.routing.<type>.service` is required, even to use the generic `webhook.request_parser`: a routing entry with only a secret could default to it | DX, 8.2 | symfony/symfony#66753 (merged) |
| 30 | A `TemplatedEmail` queued through Messenger is rendered when the worker sends it: tests of queued emails read `getContext()`, and entities put in the context are serialized into the queue | doc | dropped: mailer.rst already documents the deferred rendering, and the mailer assertions see the rendered body; only emails read back from the transport lack it |
| 31 | No Twig helper signs a URL: signed links are built in PHP and passed to templates | DX, 8.2 | symfony/symfony#66774 (merged) |
| 32 | `lint:yaml --check-schema` rejects the documented shorthands `rate_limiter: {<name>: ...}` and `lock: {service_id: ..., advisory: true}`: the dumper cannot see the closures that wrap them into `limiters`/`resources` (`semaphore` too; `workflow: {<name>: ...}` remains) | bug, 8.2 | symfony/symfony#66760 (merged) |
| 33 | With the mapper of the container, a `#[Map(transform:)]` on a property of a `#[Map(source: X)]` target is ignored unless it names its `source`: the reverse class map skips it (a bare `new ObjectMapper()` applies it) | bug, 8.1 | symfony/symfony#66756 (merged) |
| 34 | With `enable_default_groups`, `groups: ['Default', 'detail']` drops the attributes without `#[Groups]`, and nested objects with none at all serialize as `[]`: the default groups only apply when no custom group is requested, unlike in the Validator | bug, 8.2 | symfony/symfony#66757 (merged) |
| 37 | JSON errors are RFC 9457 problem documents served as `application/json`, not `application/problem+json` | DX, 8.2 | symfony/symfony#66773 (merged) |
| 38 | `new ObjectMapper()` does not convert enums to scalars: only the mapper of the container does, through `EnumMappingMetadataFactory` | bug, 8.2 | symfony/symfony#66769 (merged) (after #66768) |
| 39 | `EnumMappingMetadataFactory::injectMapEnum()` rebuilds the `Mapping` without its `targetClass`: with a class map of several targets, a conversion made for one target may apply to another; it also overrode the user transforms of enum properties | bug, 8.2 | symfony/symfony#66768 (merged) |
| 40 | Importing a Keycloak realm that declares one client scope replaces all the default ones: the realm file has to list them (`profile`, `email`, `basic`...) | Keycloak | noted |
| 41 | Console errors are split at the terminal width in the middle of words ("ar e") | DX, all branches | symfony/symfony#66775 (merged) (8.2) |
| 42 | The help of an option mapped to a list of enum cases does not list the accepted values, which only the error message tells | DX, 8.2 | symfony/symfony#66770 (merged) |
| 43 | `BoundsType` renders neither the label nor the help of the range, only "From" and "To": two ranges in a form cannot be told apart | bug, 8.2 | symfony/symfony#66761 (merged) |
| 44 | A cacheable `StreamedResponse` gets `Cache-Status: ...; fwd=miss; stored` on every request: `Store::write()` skips the responses that cannot provide their content, but `HttpCache` traces them as stored | bug, 6.4 (header 8.2) | symfony/symfony#66759 (merged) |
| 45 | `php -S` with the usual `variables_order = "GPCS"` leaves the env vars out of `$_SERVER`, so the app reads `.env` instead of the vars of the Symfony CLI: run it with `-d variables_order=EGPCS` | environment | noted |
| 46 | Encrypting a column with KeyManagement needs an `EncryptedTypes` service declared by hand and a `Kernel::boot()` override calling `register()`: the bundle could register the types it is configured with when it boots | DX, 8.2 (experimental) | symfony/symfony#66766 (merged) |
| 47 | `Unique` compares the values it reads with `===`: with `fields` on objects, two equal `DatePoint` never collide, so a `normalizer` has to turn them into strings | DX, 8.2 | symfony/symfony#66771 (merged) (the app keeps its normalizer, which also keeps unscheduled talks from colliding) |
| 48 | `key_management.clients.<name>.dsn` refuses `%env(KMS_DSN)%`: the node combines `cannotBeEmpty()` with a `validate()` closure, on which Config refuses env vars | bug, 8.2 (experimental) | symfony/symfony#66762 (merged) |
| 49 | With a `data_class`, `BoundsType` replaces the object with `null` when both bounds are empty: a non-nullable property fails with a 500 instead of reporting a violation on each bound | bug, 8.2 | symfony/symfony#66794 (merged) |
| 50 | A middleware passing a stamp to a handler with `HandlerArgumentsStamp`, which works on 8.1, fails on 8.2 with "Named parameter overwrites previous argument": stamp-typed handler arguments are now filled from the envelope too | bug (BC break), 8.2 | symfony/symfony#66795 (merged), found by the 8.1 port |
| 51 | The email channel of the Notifier takes its sender from the envelope of the mailer, not from `mailer.headers.From`: without an envelope sender, the alerts to the admin recipients threw | app bug, DX | fixed in the app with `mailer.envelope.sender`, then symfony/symfony#66854 (merged) made the global `From` header enough (idea 14) |
| 52 | With an explicit type such as `MoneyType`, `#[FormField]` guesses no `empty_data`: an empty price was written as `null` to an `int` property | app bug | fixed in the app (`?int` and `NotNull`) |
| 53 | The `oidc` token handler caches the keys of the discovery forever when the JWKS response has no `max-age`, and fetches no new keys for an unknown `kid`: a key rotation at the identity provider gives 401s until the cache is cleared | bug, 7.4 | symfony/symfony#66796 (merged) |
| 55 | The `#[FormField]` properties of a parent class are ignored unless that parent also has `#[AsFormType]`; the type and `empty_data` guessers miss the private properties of parent classes (found while answering a review of `#[Encrypted]`, which scans private properties the same way) | bug, 8.2 | symfony/symfony#66860 (merged); side findings: `EnumFormTypeGuesser` has the same blind spot (symfony/symfony#66861 (merged), 7.4), and `ReflectionClassResource` ignores private properties, so editing their attributes does not rebuild the container in dev: fixed where attributes of private members are read at compile time, `FormPass` (third commit of #66860) and the Validator and Serializer passes with `enable_attributes: false` (symfony/symfony#66862 (merged), 7.4), not in Config, so that editing private `#[ORM\Column]` attributes keeps the dev container |
| 56 | On 7.4 and 8.0, the `usages` of `#[AsCommand]` are lost on an invokable command class registered as a service: the autoconfiguration of FrameworkBundle does not copy them to the `console.command` tag. On 8.1 and 8.2, the subclasses of `Command` get their usages twice (found by the 7.4 port) | bug, 7.4 | symfony/symfony#66863 (merged), with the 8.1 resolution of its description |

### Ideas

| # | Idea | Seen in | Status |
|---|---|---|---|
| 1 | Keep the in-memory transports across the kernel reboots of the test client and reset them between tests; store the rate limiters in an in-memory pool in the test env | `CfpControllerTest` asserts before following the redirect, `TicketControllerTest::setUp()` clears `cache.rate_limiter` (findings 25, 29) | transports in 8.2: symfony/symfony#66787 (merged) (`consumeQueuedMessages()` without a transport), symfony/symfony#66789 (messages kept across reboots); `MailerAssertionsTrait` and `NotificationAssertionsTrait` across reboots: symfony/symfony#66800 (8.2, builds on #66789); limiters reset per test: symfony/symfony#66801 (8.2); all three now use the `kernel.test_state` tag of symfony/symfony#66839 (merged) (split out of #66789 at the user's request) (`keep: true` copies a service before each reset until the test ends, `method:` is called when the test ends), noted on symfony/symfony#65947; the same tag now clears the pools that hold runtime state (#66843: scheduler, cached HTTP responses, used login links), the subsystems moved off `cache.app` to their own pools (#66844 SecurityBundle, #66845 Mailer and Translation; rule from the user: `cache.app` is only for apps), and the HTTP cache store replaces the hard-coded directory removal in `KernelTestCase` (#66846) |
| 2 | `$client->loginUser($user, 'api')` on an `access_token` firewall, sending a bearer token the firewall accepts (`OidcTokenGenerator` already exists) | `tests/Security/FakeIdentityProvider.php`: discovery, JWKS and token signing, wired with `configure_container` | symfony/symfony#66805 (merged) (8.2): the client passes its token with each request and a test-only decorator of the `access_token` authenticator reads it on stateless firewalls; scopes go through `[AccessTokenAuthenticator::SCOPE_ATTRIBUTE => [...]]` |
| 6 | `#[Serialize]` mapping the returned entities to a resource class with the ObjectMapper, and streaming a returned generator of `#[JsonStreamable]` objects | `ConferenceApiController` and `ProposalApiController` map by hand; `talks()` builds the `StreamedResponse`, the `StreamWriter` call and its `Type` | symfony/symfony#66803 (merged) (`map:`, 8.2) and #66804 (`stream: true`, builds on #66803); with `map:`, the stream writer gets `list<X>` or `X`, the two types the cache warmer generates for `#[JsonStreamable]` classes |
| 7 | Placeholders from the controller arguments in the keys of `#[Lock]` and `#[RateLimit]`, e.g. `key: 'ticket-sale-{conference}'` | `TicketController` needs an `Expression` for both | symfony/symfony#66784 (merged) (8.2, both attributes) |
| 8 | Twig helpers rendering the POST-only logout and impersonation buttons, e.g. `logout_button('Log out')` | the `post_form` macro of `templates/_macros.html.twig` (finding 19); `transition_button` is the same pattern with a CSRF token | symfony/symfony#66786 (8.2: `logout_button()`, `impersonation_button()`, `impersonation_exit_button()`) |
| 9 | Let the security token keep the hash of the password it compares, so `User` needs no `__serialize()` | `User::__serialize()`, written by MakerBundle and wrong for users without a password (finding 18); needs a spike, the token does not own the user | spike done (2026-10-11), no clean BC-safe design: only the user class controls how the user is written everywhere it appears; a substitute copy made by the token breaks `EquatableInterface` users comparing passwords, user classes with their own `__serialize()`, and above all providers that return the session user instead of reloading it (`getPassword()` would then return the crc32c, failing `UserPassword` and login links). Keep `__serialize()` in 8.x; a default could change in 9.0 with skip rules and an 8.x deprecation. Side fix for the users serialized with `hash('crc32c', null)`, logged out on every request: symfony/symfony#66802 (merged) (7.4) |
| 13 | `#[Cache]` setting the targeted cache directives of RFC 9213, e.g. `CDN-Cache-Control` | `ConferenceApiController::talks()` sets the header in `#[Serialize(headers:)]` since it no longer builds its response, where it used `Response::cacheControl('CDN')` | symfony/symfony#66853 (merged) (8.2, `#[Cache(target: 'CDN')]`), documented in symfony/symfony-docs#23304; the session listener gap found there: symfony/symfony#66855 (merged) |
| 14 | The email channel of the Notifier falling back to `mailer.headers.From` when no envelope sender is set | finding 51: the app sets both to the same address | symfony/symfony#66854 (merged) (8.2), documented in symfony/symfony-docs#23305 |
| 15 | `discovery.cache` of the `oidc` token handler defaulting to `cache.security.discovery`, the pool the `oauth2` handler and `oidc_login` use and that is reset between tests | `security.yaml` names that pool, which is required there | symfony/symfony#66852 (merged) (8.2, documented in symfony/symfony-docs#23302; leaving `cache` out crashed with a `TypeError`) |

### Clock audit

Fixed during the audit (easy, or about features new in 8.2), all merged: `security:oidc:generate-token
--not-before` (symfony/symfony#66808, 7.4), the DPoP replay and JWKS cache lifetimes (symfony/symfony#66811, with the RFC 9111 current age), login links and signature
remember-me cookies (symfony/symfony#66809), the MongoDB transport wiring and `--failed-after`/`--failed-before` (symfony/symfony#66824), the
Postgres NOTIFY listener in UTC (#66825, 8.1), relative `from`/`until` of the Scheduler and
`debug:scheduler --date` (symfony/symfony#66815), the `Date` header and DKIM timestamp of Mime (symfony/symfony#66816), Mailer throttling and the
retry period of round-robin transports (#66817), the replay window of the Mailer and Notifier webhook
parsers (symfony/symfony#66821), the PagerDuty payload (symfony/symfony#66814, 6.4 bug) and the PagerDuty/Pushy options (#66813), data key age and
references in KeyManagement (symfony/symfony#66812), relative input of the HttpKernel (symfony/symfony#66826, 6.4) and Console (#66827, 8.1) `DateTimeValueResolver`,
`ThrottlingHttpClient` without `pause_handler` (#66822, 7.4 bug), `CachingHttpClient` reading the request
time from `start_time` (#66823, 7.4 bug), `ArrayAdapter` ignoring its clock in `expiresAfter()` (symfony/symfony#66818, 7.4 bug),
the MongoDB cache adapters wiring (#66819), the session `MetadataBag` (symfony/symfony#66810).

| Area | What reads the time | Fix |
|---|---|---|
| Clock | `DatePoint` with a reference resolves a date plus a relative time (`2016-09-08 +1 hour`) against the time of day of the reference, unlike PHP; the clock-aware Validator comparisons of 8.1 use it | symfony/symfony#66831 (merged) (6.4; one `date_parse()` more per string resolved against a reference, +0.8 to +1.6 µs on a ~6 µs construction; on 8.2 the native clock path is untouched); the same agent found that `DatePoint` also moves a time in the first occurrence of a DST fall-back hour to the second one (symfony/symfony#66833, 6.4; zones west of UTC stay as is, PHP's parser cannot create their second occurrence) |

Side findings of the audit, all merged: `OidcTokenHandler`, `OidcTokenGenerator` and `Oauth2TokenHandler` default to `new Clock()` and fail without symfony/clock when used
standalone (symfony/symfony#66841 6.4, #66840 7.4, and a commit on #66837); `BlueskyTransport` writes `createdAt` in the clock's timezone with a `Z` suffix (symfony/symfony#66838, 7.4).

Left to `ClockMock` (decision of 2026-10-12): what each area reads.

| Area | What reads the time | Why it was not moved to the clock |
|---|---|---|
| HTTP | `Date` and `Age` of responses (`isFresh()`, `getTtl()`, the 8.2 `Cache-Status ttl=`), cookies of HttpFoundation and of the BrowserKit cookie jar, the session cookie expiry, `#[Cache(expires:)]`, `HttpCache` | one coherent change across HttpFoundation, HttpKernel and BrowserKit: with only some of them on the clock, a cookie or a freshness mixes two clocks |
| RateLimiter | every strategy, `RateLimit::wait()`, `Reservation`, month lengths; then the `Retry-After` of `#[RateLimit]`, the login throttling minutes and the Mailer retry delay | feature; making `getExpirationTime()` time-independent (`2 * interval`, `periodEnd - periodStart`) keeps the storages unchanged, which answers the blocker of #65077 |
| HttpClient | freshness of `CachingHttpClient`, HTTP-date `Retry-After` of `RetryableHttpClient`, the pause fallbacks | after the RateLimiter change and the two 7.4 fixes |
| Cache | `CacheItem::expiresAfter()`, lifetimes sent to backends, filesystem, PHP files, DBAL and PDO adapters, early expiration; wiring the clock of `ArrayAdapter` | hot path: a hit goes from 522 ns to 2409 ns with the clock service; decide between wiring it in the test env only and a fast path for the native clock |
| Security | persistent remember-me tokens (`PersistentRememberMeHandler`, the 60 s rotation window of `DoctrineTokenProvider`) | needs the cookies of the HTTP row first |
| Messenger | delays and redelivery of the Doctrine and Redis transports | feature, with the Postgres NOTIFY listener |
| Uid | `UuidFactory`, `TimeBasedUuidFactory`, `UlidFactory` (the app builds its UUIDv7 from `$orderedAt` because of this), message ids, `--time` of the Uid commands | feature; +2.7 µs per id |
| Misc | outbound auth timestamps and token caches (Azure mailer, Firebase, LightSms, Twitter, the KMS token providers), AMQP timestamp, year ranges of `BirthdayType`/`DateType`/`WeekType`, `CurrencyType` and Intl dates, the database session handlers | low value |
