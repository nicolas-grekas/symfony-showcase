<?php

namespace App\Showcase;

/**
 * The features of Symfony 8.2 the app uses, listed by the "Symfony 8.2" panel of the web profiler.
 */
enum Feature: string
{
    #[Details(
        'Component bundles at their root keys',
        'Each component is configured at its own root key, e.g. messenger: or mailer:, instead of under framework:.',
        code: [['config/packages/messenger.yaml', 'messenger:']],
        docs: 'configuration',
        symfony81: ['9056441'],
    )]
    case ComponentBundles = 'component-bundles';

    #[Details(
        'JSON schema of the configuration',
        'Editors validate and complete the YAML files against config/schema.json, and lint:yaml checks them.',
        code: [['config/packages/messenger.yaml', '# yaml-language-server']],
        docs: 'configuration',
        symfony81: ['51b4c7a'],
    )]
    case ConfigSchema = 'config-schema';

    #[Details(
        'Forms built from data classes',
        'A data class is its own form type: #[FormField] declares its fields, whose types are guessed from the PHP types.',
        code: [['src/Cfp/SpeakerData.php', '#[AsFormType]'], ['src/Admin/SlotData.php', "#[FormField(options: ['widget'"]],
        docs: 'forms',
        symfony81: ['f5b986f', '655e14a'],
        routes: ['cfp_submit', 'admin_conference_new', 'admin_conference_edit', 'admin_schedule'],
    )]
    case FormsFromDataClasses = 'forms-from-data-classes';

    #[Details(
        'Step groups in form flows',
        'The talk and its materials are two steps of one group of the call for papers.',
        code: [['src/Form/ProposalFlowType.php', 'createStepGroup']],
        docs: 'form/form_flow',
        symfony81: ['fd54e50'],
        routes: ['cfp_submit'],
    )]
    case FormFlowStepGroups = 'form-flow-step-groups';

    #[Details(
        'Polymorphic collections',
        'PolymorphicCollectionType edits slides and videos in one collection, each with its own form type.',
        code: [['src/Form/ProposalFlowType.php', 'PolymorphicCollectionType::class'], ['src/Cfp/MaterialTypeProvider.php', 'class MaterialTypeProvider']],
        symfony81: ['2b11cba'],
        routes: ['cfp_submit'],
    )]
    case PolymorphicCollections = 'polymorphic-collections';

    #[Details(
        'Help for each choice',
        'The choice_help option describes each level of a talk.',
        code: [['src/Cfp/TalkData.php', 'choice_help']],
        docs: 'reference/forms/types/choice',
        symfony81: ['101bfba'],
        routes: ['cfp_submit'],
    )]
    case ChoiceHelp = 'choice-help';

    #[Details(
        'Valid in the current groups',
        'Valid(restrictGroups: false) validates the data of each step on its own step, against the Default group too.',
        code: [['src/Cfp/ProposalDraft.php', 'restrictGroups']],
        docs: 'reference/constraints/Valid',
        symfony81: ['e14e31e'],
        routes: ['cfp_submit', 'api_proposal_submit'],
    )]
    case ValidRestrictGroups = 'valid-restrict-groups';

    #[Details(
        'BoundsType',
        'The dates of a conference are one field with two bounds, checked to be in order.',
        code: [['src/Admin/ConferenceData.php', 'BoundsType::class']],
        docs: 'reference/forms/types/bounds',
        symfony81: ['c4633ea'],
        routes: ['admin_conference_new', 'admin_conference_edit'],
    )]
    case BoundsType = 'bounds-type';

    #[Details(
        'Collection entries named after a property',
        'The entry_name option names the slots of the schedule after their proposal.',
        code: [['src/Admin/ScheduleData.php', "'entry_name'"]],
        docs: 'reference/forms/types/collection',
        symfony81: ['9a8d68e'],
        routes: ['admin_schedule'],
    )]
    case CollectionEntryName = 'collection-entry-name';

    #[Details(
        'Workflows declared in a class',
        '#[AsWorkflow] declares the workflow of the proposals in a class, with enum places and its listeners.',
        code: [['src/Workflow/ProposalWorkflow.php', '#[AsWorkflow'], ['src/Workflow/ProposalWorkflow.php', '#[AsGuardListener']],
        docs: 'workflow',
        symfony81: ['12b119f', 'cd26d21'],
        routes: ['cfp_submit', 'proposal_withdraw', 'proposal_start_review', 'proposal_accept', 'proposal_reject'],
    )]
    case WorkflowInAClass = 'workflow-in-a-class';

    #[Details(
        'OpenID Connect login',
        'The oidc_login authenticator logs speakers in with Keycloak, refreshes their access token and ends their sessions on both sides.',
        code: [['config/packages/security.yaml', 'oidc_login:']],
        docs: 'security/oidc_login',
        symfony81: ['8f635e0'],
        symfony74: ['5d614f4'],
        routes: ['_oidc_login_start_main', '_oidc_login_callback_main', '_oidc_login_backchannel_logout_main', 'app_login'],
    )]
    case OidcLogin = 'oidc-login';

    #[Details(
        'Asking for the password again',
        'Reviewers confirm their password before deciding: the voter requests a re-authentication, and the login form asks for the password only.',
        code: [['config/packages/security.yaml', 'recent_authentication_lifetime'], ['src/Security/ProposalVoter.php', 'requestReAuthentication']],
        docs: 'security',
        symfony81: ['07ccd19'],
        routes: ['proposal_decide', 'proposal_accept', 'proposal_reject', 'app_login'],
    )]
    case RecentAuthentication = 'recent-authentication';

    #[Details(
        'Wildcards in the role hierarchy',
        'ROLE_REVIEWER_* grants ROLE_REVIEWER to the reviewers of each conference.',
        code: [['config/packages/security.yaml', 'ROLE_REVIEWER_*']],
        docs: 'security',
        symfony81: ['8df1381'],
    )]
    case RoleWildcards = 'role-wildcards';

    #[Details(
        'User checker on refresh',
        'Users disabled since they logged in are logged out on their next request.',
        code: [['config/packages/security.yaml', 'user_checker_on_refresh']],
        docs: 'security/user_checkers',
        symfony81: ['9fc2c3b'],
        loggedIn: true,
    )]
    case UserCheckerOnRefresh = 'user-checker-on-refresh';

    #[Details(
        'Logging out with a POST request',
        'logout_button() renders the form that logs the user out with a POST request, CSRF token included.',
        code: [['templates/base.html.twig', 'logout_button(']],
        docs: 'reference/twig_reference',
        symfony81: ['b53b5ec'],
        loggedIn: true,
    )]
    case LogoutForm = 'logout-form';

    #[Details(
        'Impersonation with POST requests',
        'impersonation_button() and impersonation_exit_button() render the forms that switch users with POST requests protected by a CSRF token.',
        code: [['templates/admin/users.html.twig', 'impersonation_button('], ['templates/base.html.twig', 'impersonation_exit_button('], ['config/packages/security.yaml', 'switch_user:']],
        docs: 'security/impersonating_user',
        symfony81: ['13f3c5a'],
        routes: ['admin_users', 'switch_user'],
        impersonating: true,
    )]
    case ImpersonationForms = 'impersonation-forms';

    #[Details(
        'Permissions of guests',
        'is_granted_for_user(null, ...) tells what visitors may see.',
        code: [['templates/admin/proposals.html.twig', 'is_granted_for_user(null']],
        docs: 'reference/twig_reference',
        symfony81: ['7904885'],
        routes: ['admin_proposals'],
    )]
    case GuestPermissions = 'guest-permissions';

    #[Details(
        'OAuth2 scopes',
        'OAUTH2_SCOPE() requires a scope of the access token, and a denial gets the RFC 6750 insufficient_scope challenge.',
        code: [['src/Controller/Api/ProposalApiController.php', 'OAUTH2_SCOPE(']],
        docs: 'security/access_token',
        symfony81: ['73cd99d'],
        routes: ['api_proposal_submit'],
    )]
    case OAuth2Scopes = 'oauth2-scopes';

    #[Details(
        'Protected resource metadata',
        'The API tells clients where to get a token (RFC 9728), at /.well-known/oauth-protected-resource and in its challenges.',
        code: [['config/packages/security.yaml', 'resource_metadata:']],
        docs: 'security/access_token',
        symfony81: ['10d4adf'],
        routes: ['_oauth_protected_resource_metadata_api', 'api_proposal_submit'],
    )]
    case ResourceMetadata = 'resource-metadata';

    #[Details(
        'Discovery bound to its issuer',
        'The keys of the discovery document only verify the access tokens of the issuer it announces.',
        code: [['config/packages/security.yaml', 'check_issuer: true']],
        docs: 'security/access_token',
        symfony81: ['d3563ca'],
        bearerToken: true,
    )]
    case DiscoveryIssuerCheck = 'discovery-issuer-check';

    #[Details(
        '#[Lock] on controllers',
        "The purchases of tickets for the same conference wait for each other, on a lock keyed with the 'ticket-sale-{conference}' placeholder.",
        code: [['src/Controller/TicketController.php', '#[Lock(']],
        symfony81: ['061be3b'],
        routes: ['ticket_buy'],
    )]
    case LockAttribute = 'lock-attribute';

    #[Details(
        'Rate limit headers',
        '#[RateLimit] tells the state of the limiter in X-RateLimit-* headers.',
        code: [['src/Controller/TicketController.php', 'exposeHeaders: true']],
        docs: 'rate_limiter',
        symfony81: ['c50d077'],
        routes: ['ticket_buy'],
        header: 'X-RateLimit-Limit',
    )]
    case RateLimitHeaders = 'rate-limit-headers';

    #[Details(
        'Advisory locks on the Doctrine connection',
        'The locks are Postgres advisory locks, taken on the connection of Doctrine.',
        code: [['config/packages/lock.yaml', 'advisory: true']],
        docs: 'lock',
        symfony81: ['c1dec86'],
        routes: ['ticket_buy'],
    )]
    case AdvisoryLocks = 'advisory-locks';

    #[Details(
        'sign_url filter',
        'Twig signs the links of the tickets, which expire the day after the conference.',
        code: [['templates/_macros.html.twig', 'sign_url(']],
        symfony81: ['a6e6c7b'],
        routes: ['me_tickets'],
    )]
    case SignUrl = 'sign-url';

    #[Details(
        '#[MapEntity] with a closure',
        'The accepted talks of a conference are loaded by a closure.',
        code: [['src/Controller/ConferenceController.php', 'expr: static function']],
        docs: 'doctrine',
        symfony81: ['9a10c8e'],
        routes: ['conference_show'],
    )]
    case MapEntityClosure = 'map-entity-closure';

    #[Details(
        '#[MapEntity] loading a list',
        'The proposals of a conference are loaded into an array argument.',
        code: [['src/Controller/Admin/DashboardController.php', 'array $proposals']],
        docs: 'doctrine',
        symfony81: ['c86404b'],
        routes: ['admin_proposals'],
    )]
    case MapEntityList = 'map-entity-list';

    #[Details(
        'Time ranges of UUIDv7',
        'UuidV7::createBoundaries() turns a time range into a range of ids.',
        code: [['src/Repository/TicketRepository.php', 'createBoundaries']],
        docs: 'components/uid',
        symfony81: ['67d6f72'],
        routes: ['admin_sales'],
    )]
    case UuidBoundaries = 'uuid-boundaries';

    #[Details(
        'DatePoint properties mapped by default',
        'Doctrine maps the DatePoint properties to the date_point type without an explicit type.',
        code: [['src/Entity/Ticket.php', 'DatePoint $heldUntil']],
        docs: 'components/clock',
        symfony81: ['5126041'],
    )]
    case DatePointMapping = 'date-point-mapping';

    #[Details(
        'Problem documents',
        'The errors of the API are RFC 9457 documents, served as application/problem+json.',
        symfony81: ['598c39c'],
        mediaType: 'application/problem+json',
    )]
    case ProblemDocuments = 'problem-documents';

    #[Details(
        'Enums mapped by the ObjectMapper',
        'Backed enums are mapped to their values without a transform.',
        code: [['src/Api/TalkResource.php', 'public string $level']],
        docs: 'object_mapper',
        symfony81: ['3e136f0'],
        routes: ['api_conference', 'api_conference_talks'],
    )]
    case ObjectMapperEnums = 'object-mapper-enums';

    #[Details(
        'UUIDs streamed by JsonStreamer',
        'UUIDs are streamed as strings without a value transformer.',
        code: [['src/Api/TalkResource.php', 'public Uuid $id']],
        docs: 'serializer/streaming_json',
        symfony81: ['f3919f7'],
        routes: ['api_conference_talks'],
    )]
    case JsonStreamerUuids = 'json-streamer-uuids';

    #[Details(
        'Link-Template header',
        'The templated links added with addLink() are sent in an RFC 9652 Link-Template header.',
        code: [['src/Controller/Api/ConferenceApiController.php', 'addLink']],
        docs: 'web_link',
        symfony81: ['c26325a'],
        symfony74: ['b4070fe'],
        header: 'Link-Template',
    )]
    case LinkTemplate = 'link-template';

    #[Details(
        'api-catalog',
        'JsonLinksetSerializer writes the RFC 9727 api-catalog of the API.',
        code: [['src/Controller/Api/ApiCatalogController.php', 'JsonLinksetSerializer']],
        docs: 'web_link',
        symfony81: ['b60c379'],
        routes: ['api_catalog'],
    )]
    case ApiCatalog = 'api-catalog';

    #[Details(
        'Results of controllers mapped by #[Serialize]',
        'The map option maps the returned entities to their API resource with the ObjectMapper, item by item for lists.',
        code: [['src/Controller/Api/ConferenceApiController.php', 'map: ConferenceResource::class'], ['src/Controller/Api/ProposalApiController.php', 'map: TalkResource::class']],
        docs: 'controller',
        symfony81: ['bde1a78'],
        symfony74: ['b4070fe'],
        routes: ['api_conferences', 'api_conference_talks', 'api_proposal'],
    )]
    case SerializeMap = 'serialize-map';

    #[Details(
        'Results of controllers streamed by #[Serialize]',
        'The stream option writes the talks with JsonStreamer as they are mapped, from the list the controller returns.',
        code: [['src/Controller/Api/ConferenceApiController.php', 'stream: true']],
        docs: 'controller',
        symfony81: ['384e665'],
        routes: ['api_conference_talks'],
    )]
    case SerializeStream = 'serialize-stream';

    #[Details(
        'Targeted cache directives in #[Cache]',
        "#[Cache(target: 'CDN')] sets the RFC 9213 CDN-Cache-Control header, so CDNs keep the talks longer than browsers do.",
        code: [['src/Controller/Api/ConferenceApiController.php', "target: 'CDN'"]],
        docs: 'http_cache/expiration',
        symfony81: ['7ddf2ef'],
        routes: ['api_conference_talks'],
        header: 'CDN-Cache-Control',
    )]
    case TargetedCacheDirectives = 'targeted-cache-directives';

    #[Details(
        'Cache-Status header',
        'The HTTP cache of the kernel tells what it did in an RFC 9211 Cache-Status header, in production.',
        code: [['config/packages/framework.yaml', 'cache_status:']],
        docs: 'http_cache',
        symfony81: ['1c96829'],
        header: 'Cache-Status',
    )]
    case CacheStatus = 'cache-status';

    #[Details(
        'Default serialization groups',
        'With enable_default_groups, the properties without #[Groups] are in every group.',
        code: [['config/packages/serializer.yaml', 'enable_default_groups']],
        docs: 'serializer',
        symfony81: ['8b17987'],
        routes: ['api_conferences', 'api_conference', 'api_proposal'],
    )]
    case DefaultGroups = 'default-groups';

    #[Details(
        '#[DiscriminatorMapType]',
        'Each kind of material declares its own type in the discriminator map of the API payload.',
        code: [['src/Cfp/SlidesData.php', '#[DiscriminatorMapType(']],
        docs: 'serializer',
        symfony81: ['5ce0cbc'],
        routes: ['api_proposal_submit'],
    )]
    case DiscriminatorMapType = 'discriminator-map-type';

    #[Details(
        'Outbox',
        'The domain events are stored in the database transaction that records them, then sent to RabbitMQ.',
        code: [['config/packages/messenger.yaml', "outbox: 'doctrine://"]],
        docs: 'messenger',
        symfony81: ['a946384'],
        routes: ['cfp_submit', 'api_proposal_submit', 'proposal_accept', 'proposal_reject'],
    )]
    case Outbox = 'outbox';

    #[Details(
        'Inbox',
        'The worker records each domain event in the transaction of its handlers, and skips the handlers of an event redelivered after they committed.',
        code: [['config/packages/messenger.yaml', 'inbox: true']],
        docs: 'messenger',
        symfony81: ['269b299'],
    )]
    case Inbox = 'inbox';

    #[Details(
        'Correlation of messages',
        'Every message carries correlation, message and causation ids, and a handler gets them as arguments.',
        code: [['config/packages/messenger.yaml', 'identity_stamps: true'], ['src/MessageHandler/SendSubmissionReceipt.php', 'CorrelationStamp $']],
        docs: 'messenger',
        symfony81: ['30d209c'],
        routes: ['cfp_submit', 'api_proposal_submit'],
    )]
    case IdentityStamps = 'identity-stamps';

    #[Details(
        'Ordered message handlers',
        '#[AsMessageHandler(after: ...)] runs the receipt of a proposal after the notification of the reviewers.',
        code: [['src/MessageHandler/SendSubmissionReceipt.php', '#[AsMessageHandler(after:']],
        docs: 'messenger',
        symfony81: ['a4c5d54'],
    )]
    case HandlerOrder = 'handler-order';

    #[Details(
        'Chained messages and alerts on failure',
        'ChainStamp sends the travel details once the booking succeeds, and DispatchOnFailureStamp alerts the organizers when it fails for good.',
        code: [['src/MessageHandler/HandleProposalDecision.php', 'ChainStamp::envelope'], ['src/MessageHandler/HandleProposalDecision.php', 'DispatchOnFailureStamp(']],
        symfony81: ['c6d2eb1', '2bbd665'],
    )]
    case ChainedMessages = 'chained-messages';

    #[Details(
        'logging and doctrine_dbal_transaction middleware',
        'The bus logs the duration and memory of each message, and wraps its handlers in a transaction of the connection.',
        code: [['config/packages/messenger.yaml', '- logging'], ['config/packages/messenger.yaml', '- doctrine_dbal_transaction']],
        docs: 'messenger',
        symfony81: ['cfabd16', '51ecbb3'],
    )]
    case MessengerMiddleware = 'messenger-middleware';

    #[Details(
        'FailedMessageRepository',
        'The back office lists, retries and removes the messages of the failure transport.',
        code: [['src/Controller/Admin/FailedMessageController.php', 'FailedMessageRepository $']],
        docs: 'messenger',
        symfony81: ['619ee1f'],
        routes: ['admin_failed_messages', 'admin_failed_message_retry', 'admin_failed_message_remove'],
    )]
    case FailedMessageRepository = 'failed-message-repository';

    #[Details(
        'Rate-limited mailer transport',
        'The mailer sends no more emails than the provider accepts, and the others are retried later.',
        code: [['config/packages/mailer.yaml', 'rate_limiter: mailer']],
        docs: 'mailer',
        symfony81: ['b6279dd'],
    )]
    case MailerRateLimiter = 'mailer-rate-limiter';

    #[Details(
        'Admin recipients of the Notifier',
        'The alerts go to the admin recipients of the Notifier, through an autowired AdminRecipientsProviderInterface.',
        code: [['src/MessageHandler/AlertOrganizersHandler.php', 'AdminRecipientsProviderInterface $']],
        docs: 'notifier',
        symfony81: ['3762427'],
    )]
    case AdminRecipients = 'admin-recipients';

    #[Details(
        'Notifications sent from the From header of the mailer',
        'The email channel of the Notifier takes its sender from the global From header of the mailer when no envelope sender is set.',
        code: [['config/packages/mailer.yaml', "From: 'Conferences"]],
        docs: 'notifier',
        symfony81: ['5e2e46a'],
    )]
    case NotifierFromHeader = 'notifier-from-header';

    #[Details(
        'Schedules configured in YAML',
        'The default schedule is stateful, locked, and catches up its last missed run only.',
        code: [['config/packages/scheduler.yaml', 'schedules:']],
        docs: 'scheduler',
        symfony81: ['cefbe27'],
    )]
    case ScheduleOptions = 'schedule-options';

    #[Details(
        'Tasks restricted to an environment',
        "#[AsCronTask(env: 'prod')] reminds the reviewers in production only.",
        code: [['src/Scheduler/RemindReviewers.php', "env: 'prod'"]],
        docs: 'scheduler',
        symfony81: ['40a4296'],
    )]
    case TaskEnvironments = 'task-environments';

    #[Details(
        'Standard Webhooks',
        'The payment webhooks are signed and verified with the Standard Webhooks scheme, both ways.',
        code: [['config/packages/webhook.yaml', 'signature_format: standard']],
        docs: 'webhook',
        symfony81: ['119befa', '6a2ff0a', 'b2a6c5d'],
        routes: ['_webhook_controller', 'fake_payment_checkout'],
    )]
    case StandardWebhooks = 'standard-webhooks';

    #[Details(
        'Columns encrypted at rest',
        '#[Encrypted] makes KeyManagement encrypt the phone numbers of the speakers with a data key wrapped by a key management service.',
        code: [['src/Entity/Speaker.php', "#[Encrypted(key: 'speaker.contact')]"], ['config/packages/key_management.yaml', 'key_management:']],
        symfony81: ['6367255'],
        routes: ['cfp_submit', 'api_proposal_submit'],
    )]
    case EncryptedColumns = 'encrypted-columns';

    #[Details(
        'Console sub-commands',
        'bin/console conference list runs a method of the class grouping the conference commands, and the help lists the values of enum options.',
        code: [['src/Command/ConferenceCommands.php', "#[AsCommand('conference'"], ['src/Command/ConferenceCommands.php', '@param list<TicketStatus>']],
        docs: 'console',
        symfony81: ['00aad11', '322908e', '35d5540'],
        symfony74: ['e6d6e4b', 'f2fcda6', 'b8ffa46'],
    )]
    case ConsoleSubCommands = 'console-sub-commands';

    #[Details(
        'Testing tools',
        'MessengerAssertionsTrait across kernel reboots, the configure_container option of createClient(), #[UseRecord] to replay recorded HTTP exchanges, sendWebhook(), loginUser() with scopes on access token firewalls, and the rate limiters and runtime caches reset between tests.',
        code: [['tests/Controller/CfpControllerTest.php', 'assertQueuedMessageCount'], ['tests/Api/ProposalApiTest.php', 'configure_container'], ['tests/Travel/TravelAgencyTest.php', '#[UseRecord]'], ['tests/Controller/TicketControllerTest.php', 'sendWebhook('], ['tests/Api/ProposalApiTest.php', 'AccessTokenAuthenticator::SCOPE_ATTRIBUTE']],
        docs: 'testing',
        symfony81: ['961389e', '4597ad2', '180e5e3'],
    )]
    case TestingTools = 'testing-tools';

    public function details(): Details
    {
        static $details = [];

        return $details[$this->name] ??= new \ReflectionEnumBackedCase(self::class, $this->name)->getAttributes(Details::class)[0]->newInstance();
    }
}
