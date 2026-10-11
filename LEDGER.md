# Symfony 8.2 showcase ledger

What the app exercises, and what building it found.

## Setup

- Skeleton: `symfony new --webapp --version=next --docker`, Symfony 8.2.x-dev, PHP 8.5.
- Symfony sources: `php link` to the `showcase` branch of a symfony/symfony worktree
  (`~/Code/symfony-worktrees/showcase`), which is plain 8.2 now that all the Symfony fixes
  below are merged and merged up.
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
| 1 | Recipes write `framework.cache`, `framework.messenger`, `framework.validation`... while 8.2 owns these keys at the root | recipe | symfony/recipes#1592; the webapp-pack and third-party recipes (DoctrineBundle...) keep `framework.*` |
| 2 | `debug:container --env-vars` reports `APP_ENV`, `APP_SHARE_DIR` (and `KERNEL_CLASS` in test) as unused | bug, 8.2 | symfony/symfony#66747 (merged) |
| 3 | `debug:config framework cache` fails, `config:dump-reference framework` shows `cache: ~` with no hint | DX, 8.2 | symfony/symfony#66748 (merged) |
| 4 | `lint:yaml config --check-schema` rejects 5 of the 24 files of the skeleton, all valid | bug, 8.2 and 7.4 | symfony/symfony#66746 (merged) (8.2), symfony/symfony#66745 (merged) (7.4) |
| 5 | `cache:warmup --env=prod` logs a deprecation: DoctrineBundle extends the deprecated FrameworkBundle cache warmer | bug, third party | doctrine/DoctrineBundle#2289 (merged) |
| 6 | Schema validation needs `opis/json-schema`, which the webapp pack does not install, and is opt-in with `--check-schema` | DX, pack | symfony/webapp-pack#23 |
| 7 | Recipe text: `ux_turbo.yaml` reuses the comment of `csrf.yaml`, `notifier.yaml` mentions `sms/nexmo`, the mailer post-install text says `framework.messenger.routing` and `doc/master` | recipe | symfony/recipes#1591 |
| 8 | `debug:roles` (new) and `debug:security:role-hierarchy` overlap; `debug:roles` is the only description ending with a period | DX, 8.2 | symfony/symfony#66749 (merged) |
| 9 | `make:auth` is described as "Create a Guard authenticator" | MakerBundle | symfony/maker-bundle#1844 (merged) |
| 10 | `composer require`/`remove` of a component fails its own `cache:clear` script: in debug mode the kernel reuses the cached list of bundles while rebuilding a stale container | bug, 8.1 | symfony/symfony#66750 (merged) |
| 11 | The Doctrine bridge maps `Clock\DayPoint` and `Clock\TimePoint` to Doctrine types, but these classes don't exist (the changelog too) | bug, 8.2 | symfony/symfony#66751 (merged) |
| 12 | `#[FormField]` relies on the type guessers, which ignore the declared PHP type: an `?int` property becomes a text field (enums are guessed) | DX, 8.2 | symfony/symfony#66772 (merged) |
| 13 | Neither FrameworkBundle nor DoctrineBundle registers the new `CollectionDenormalizer` | DX, 8.2 | doctrine/DoctrineBundle#2297, opened by the author of the class |
| 14 | `IterableToArrayCollection` wraps a `MapCollection` that builds its own `new ObjectMapper()` instead of using the app's | bug, 8.2 | symfony/symfony#66755 (merged) |
| 15 | On 8.2 the workflow recipe's `framework.workflows: null` file is useless: WorkflowBundle is enabled by default and `#[AsWorkflow]` needs no YAML | recipe | done in symfony/recipes#1592 |
| 16 | DomCrawler assertions wrapped in `LogicalAnd` print the exported `Crawler` object on failure (BrowserKit cookie and route assertions dump the response or the client) | DX, all branches | symfony/symfony#66765 (merged) (8.2) |
| 18 | A `User::__serialize()` that hashes the password the MakerBundle way logs out the users who have none (e.g. those of `oidc_login`): `hash('crc32c', '')` never matches a `null` password | app gotcha | symfony/symfony#66763 (merged) (the example of the interface), symfony/maker-bundle#1845 (merged) |
| 19 | `logout_form()`/`impersonation_form()` return `{action, fields}`: every app writes the same macro, and nothing tells which field is the CSRF token the `csrf-protection` Stimulus controller should handle | DX, 8.2 | symfony/symfony-docs#23248 (a macro in the docs), symfony/symfony#66767 (merged) (phpdoc) |
| 20 | A recent-authentication check on a POST action sends the user to the homepage once the password is confirmed (no target path is saved for unsafe methods): guard a GET page instead | doc | symfony/symfony-docs#23249 |
| 21 | `re_authentication_entry_point` only takes a service id, while `entry_point` also takes an authenticator key such as `form_login` | DX, 8.2 | symfony/symfony#66754 (merged) |
| 22 | `is_granted_for_user(null, 'IS_AUTHENTICATED_FULLY')` throws instead of answering no for a guest | bug, 8.2 | symfony/symfony#66764 (merged) |
| 23 | `AdminRecipientsProviderInterface` has no autowiring alias: sending to `notifier.admin_recipients` needs an `instanceof` check on `NotifierInterface` | DX, 8.2 | symfony/symfony#66752 (merged) |
| 24 | The webapp pack routes urgent notifications to email, but `NotificationEmail` throws without `twig/cssinliner-extra` and `twig/inky-extra`, which it does not install | DX, pack | symfony/webapp-pack#24 |
| 25 | The in-memory transports are reset by the next request of the test client, so `MessengerAssertionsTrait` checks must run before it | doc | symfony/symfony-docs#23250 (6.4: the reset predates the 8.2 helpers) |
| 26 | On 8.2 the scheduler recipe could drop its `Schedule` class, which hardcodes `stateful()`/`processOnlyLastMissedRun()`, for `scheduler.schedules` | recipe | symfony/recipes#1593 (with symfony/recipes#1573 for `use_messenger_routing`) |
| 27 | A form flow does not validate the objects of its steps unless the root data class adds `Valid(groups: [<step>], restrictGroups: false)` to each | bug, 7.4 | symfony/symfony#66776 (merged) (the app keeps the scoped `Valid` constraints for its API) |
| 17 | In a `#[MapEntity]` closure, `{slug:conference}` exposes the slug as the `conference` request attribute, not `slug` | doc | symfony/symfony-docs#23249 |
| 28 | `webhook.routing.<type>.service` is required, even to use the generic `webhook.request_parser`: a routing entry with only a secret could default to it | DX, 8.2 | symfony/symfony#66753 (merged) |
| 29 | Limiters keep their state in `cache.rate_limiter`, a filesystem pool that outlives the tests: each test has to clear it | doc | symfony/symfony-docs#23250 |
| 30 | A `TemplatedEmail` queued through Messenger is rendered when the worker sends it: tests of queued emails read `getContext()`, and entities put in the context are serialized into the queue | doc | dropped: mailer.rst already documents the deferred rendering, and the mailer assertions see the rendered body; only emails read back from the transport lack it |
| 31 | No Twig helper signs a URL: signed links are built in PHP and passed to templates | DX, 8.2 | symfony/symfony#66774 (merged) |
| 32 | `lint:yaml --check-schema` rejects the documented shorthands `rate_limiter: {<name>: ...}` and `lock: {service_id: ..., advisory: true}`: the dumper cannot see the closures that wrap them into `limiters`/`resources` (`semaphore` too; `workflow: {<name>: ...}` remains) | bug, 8.2 | symfony/symfony#66760 (merged) |
| 33 | With the mapper of the container, a `#[Map(transform:)]` on a property of a `#[Map(source: X)]` target is ignored unless it names its `source`: the reverse class map skips it (a bare `new ObjectMapper()` applies it) | bug, 8.1 | symfony/symfony#66756 (merged) |
| 34 | With `enable_default_groups`, `groups: ['Default', 'detail']` drops the attributes without `#[Groups]`, and nested objects with none at all serialize as `[]`: the default groups only apply when no custom group is requested, unlike in the Validator | bug, 8.2 | symfony/symfony#66757 (merged) |
| 35 | The `oidc` token handler builds the discovery URL relatively to `base_uri`: `http://kc/realms/showcase` fetches `http://kc/realms/.well-known/openid-configuration`, only a trailing slash works around it, even with `check_issuer` (`oidc_user_info` too) | doc, 7.4 | symfony/symfony#66758 (the description of `base_uri`) and symfony/symfony-docs#23255 say it must end with a slash; fixing it in code was turned down |
| 36 | The Symfony CLI exports `DEFAULT_URI` with a trailing slash, the routing recipe writes it without one: `'%env(DEFAULT_URI)%/path'` is right with one and wrong with the other | DX, Symfony CLI | symfony-cli/symfony-cli#778 |
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
| 49 | With a `data_class`, `BoundsType` replaces the object with `null` when both bounds are empty: a non-nullable property fails with a 500 instead of reporting a violation on each bound | bug, 8.2 | symfony/symfony#66794 |
| 50 | A middleware passing a stamp to a handler with `HandlerArgumentsStamp`, which works on 8.1, fails on 8.2 with "Named parameter overwrites previous argument": stamp-typed handler arguments are now filled from the envelope too | bug (BC break), 8.2 | symfony/symfony#66795, found by the 8.1 port |
| 51 | The email channel of the Notifier takes its sender from the envelope of the mailer, not from `mailer.headers.From`: without an envelope sender, the alerts to the admin recipients threw | app bug, DX | fixed in the app (`mailer.envelope.sender`); the channel could fall back to the global From header |
| 52 | With an explicit type such as `MoneyType`, `#[FormField]` guesses no `empty_data`: an empty price was written as `null` to an `int` property | app bug | fixed in the app (`?int` and `NotNull`) |
| 53 | The `oidc` token handler caches the keys of the discovery forever when the JWKS response has no `max-age`, and fetches no new keys for an unknown `kid`: a key rotation at the identity provider gives 401s until the cache is cleared | bug, 7.4 | symfony/symfony#66796 |
| 54 | `ChainStamp` and `DispatchOnFailureStamp` have no documentation; `sign_url`, `#[AsFormType]`, `PolymorphicCollectionType`, `#[Lock]`, `#[AsWorkflow]` and the identity stamps are documented in open pull requests only | doc, 8.2 | symfony/symfony-docs#23264, #22909, #22695, #23265, #23214, #23147; none for the two stamps |

## Ideas for 8.3

What would remove the workarounds, the "remember to" comments and the boilerplate still in the
app. Nothing is filed yet; 1, 2 and 4 matter most, 5 is the cheapest.

| # | Idea | Seen in | Status |
|---|---|---|---|
| 1 | Keep the in-memory transports across the kernel reboots of the test client and reset them between tests; store the rate limiters in an in-memory pool in the test env | `CfpControllerTest` asserts before following the redirect, `TicketControllerTest::setUp()` clears `cache.rate_limiter` (findings 25, 29) | transports in 8.2: symfony/symfony#66787 (`consumeQueuedMessages()` without a transport), symfony/symfony#66789 (messages kept across reboots); the limiter pool stays for 8.3, and `MailerAssertionsTrait` and `NotificationAssertionsTrait` lose their events across reboots the same way |
| 2 | `$client->loginUser($user, 'api')` on an `access_token` firewall, sending a bearer token the firewall accepts (`OidcTokenGenerator` already exists) | `tests/Security/FakeIdentityProvider.php`: discovery, JWKS and token signing, wired with `configure_container` | idea |
| 3 | A DomCrawler helper to add a collection entry from its `data-prototype` | `CfpControllerTest` posts the materials by hand with `getPhpValues()` | idea |
| 4 | Let a form flow validate only the object of its current step, so a plain `#[Assert\Valid]` works for both the flow and the API | `ProposalDraft` scopes each `Valid` to its step with `restrictGroups: false`, the API lists every step in `validationGroups` (finding 27) | idea |
| 5 | MakerBundle: `#[AsFormType]` data classes in `make:form`, `scheduler.schedules` instead of a `Schedule` class in `make:schedule` | `make:form` generates an `AbstractType` only, `make:schedule` the class symfony/recipes#1593 drops | idea |
| 6 | `#[Serialize]` mapping the returned entities to a resource class with the ObjectMapper, and streaming a returned generator of `#[JsonStreamable]` objects | `ConferenceApiController` and `ProposalApiController` map by hand; `talks()` builds the `StreamedResponse`, the `StreamWriter` call and its `Type` | idea |
| 7 | Placeholders from the controller arguments in the keys of `#[Lock]` and `#[RateLimit]`, e.g. `key: 'ticket-sale-{conference}'` | `TicketController` needs an `Expression` for both | symfony/symfony#66784 (8.2, both attributes) |
| 8 | Twig helpers rendering the POST-only logout and impersonation buttons, e.g. `logout_button('Log out')` | the `post_form` macro of `templates/_macros.html.twig` (finding 19); `transition_button` is the same pattern with a CSRF token | symfony/symfony#66786 (8.2: `logout_button()`, `impersonation_button()`, `impersonation_exit_button()`) |
| 9 | Let the security token keep the hash of the password it compares, so `User` needs no `__serialize()` | `User::__serialize()`, written by MakerBundle and wrong for users without a password (finding 18); needs a spike, the token does not own the user | idea |
| 10 | Recipes adding `# yaml-language-server: $schema=../schema.json` to `config/packages/*.yaml`, `lint:yaml` checking the schema by default when a validator is installed, the `workflow: {<name>: ...}` shorthand accepted by the schema | `config/schema.json` is generated but only `services.yaml` and `routes.yaml` reference a schema (findings 6, 32). The SchemaStore catalog (SchemaStore/schemastore#6225) covers `config/services*`, `config/routes*`, `config/serializer` and `config/validator`, pinned to the 8.1 branch, but cannot cover `config/packages`: its schema depends on the bundles of each app | headers in symfony/recipes#1592 and in this app; default check symfony/symfony#66788; workflow shorthand symfony/symfony#66785, `places`/`transitions` symfony/symfony#66792, empty mappings symfony/symfony#66791, services schema false positives symfony/symfony#66790 (7.4), workflow `metadata` crash symfony/symfony#66793 (6.4); still open: the 7.4 routing schema rejects route names with characters like `#$péß^a|`, and the validator mapping schema rejects two of its own fixtures |
| 11 | Once 8.2 ships, move the DoctrineBundle recipe and the `messenger.yaml` of the webapp pack to the root keys, like symfony/recipes#1592 | finding 1 | idea |

## Vision beyond 8.3

The direction 8.2 already takes, pushed further: you write the domain and its rules, Symfony
derives the rest and checks it. Each area starts from what the app shows.

| # | Area | Seed in 8.2 and in the app | Further |
|---|---|---|---|
| A | Classes declare the app, config only holds what differs per deployment | 27 files in `config/packages`, most copied by recipes; the 8.2 `cache.yaml` recipe is only comments; encrypting one column needs a `key_management.doctrine_dbal.types` entry mapping a type name to a key | What belongs to a class sits on it (`#[Encrypted(key: 'speaker.contact')]` on the property); `config/` keeps DSNs, secrets, hosts and the few app-wide choices; an installed package is enabled with its defaults and copies no file. Dropping the `framework.` level was step one, dropping the file is step two: the schema and IDE completion replace the commented examples |
| B | One typed model, every edge derived from it | `ProposalDraft` feeds the form flow and the API but the step names are repeated as validation groups; `TalkResource` is mapped by the ObjectMapper and streamed by JsonStreamer; enum options come from `@param list<Enum>`; `/api/docs` is a hand-written Twig page; the api-catalog, `Link-Template` and resource metadata are wired by hand | TypeInfo as the spine: from the types and constraints of a class, derive the form, the payload mapping, the response, the command input and a JSON Schema, like 8.2 derives `config/schema.json` from the config tree. An app with JSON routes describes itself by default (catalog, problem details, auth metadata, schema). Core derives the schema of plain classes, API Platform keeps the resource layer |
| C | Cache warmup compiles everything declarative | The container, the router, the JsonStreamer writers, the ObjectMapper metadata and the `#[MapEntity]` expressions are generated at warmup; builds are reproducible with `SOURCE_DATE_EPOCH`; forms are interpreted on each request, a 3-field form costing 7.6 times a plain route in prod | `#[AsFormType]` classes and validation metadata compiled the same way, the warmup acting as the compiler of the app, with byte-identical output. The form cost is Twig executing blocks, not waste, so this needs a design, not a patch |
| D | Tests run a simulation of the world | The app ships a fake identity provider and a fake payment provider, uses recorded HTTP and `mockTime()`, places its assertions around transport resets, clears the limiter pool in each test, and drives the queues one `consumeQueuedMessages()` at a time | Every boundary has a double Symfony ships (clock, HTTP, identity provider, webhooks, KMS, mailer, transports); state is isolated per test without asking; `settle()` runs the queues and the scheduler on a controlled clock until nothing is left, so async code is as easy to test as a request |
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
