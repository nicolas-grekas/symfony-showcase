# The review assistant

The back office has an assistant for the reviewers of the calls for papers. On the page of a
proposal, a reviewer sees:

- the most similar talks and proposals of all conferences, with their similarity. A talk given
  last year or a proposal sent twice stands out;
- notes written by a model, with a summary in two or three sentences, up to five tags and the track
  that fits best. The model reads the data of the app with tools: the proposal, the other proposals
  of its speakers, the similar talks and proposals, and the tracks.

The assistant never decides, and its tools only read. Accepting or rejecting a proposal stays a
reviewer action, behind the recent authentication of the reviewer (see "Human in the loop").

The same assistant is available to MCP clients, such as Claude Code, as two tools of the MCP server
of the app (see "MCP tools").

It uses [Symfony AI](https://github.com/symfony/ai) 1.0.0-BETA1, with the Platform, Agent and Store
components, the AI bundle, the Hugging Face platform bridge and the Doctrine store bridge.

## Running it

Without any configuration, the assistant uses a fake platform, so that the app runs and the tests
pass without an API key. `AI_PLATFORM` picks the platform:

| `AI_PLATFORM` | What answers | Needs |
|---|---|---|
| `fake` (the default) | `App\Assistant\FakePlatform`, which answers without calling any model | nothing |
| `huggingface` | [Hugging Face Inference Providers](https://huggingface.co/docs/inference-providers/index) | `HUGGINGFACE_API_KEY` |
| `generic.default` | any API compatible with the one of OpenAI, at `GENERIC_BASE_URL` | `GENERIC_BASE_URL`, the models |

The fake platform is not a model. The fake embeddings count the words of the texts. The fake notes
take the first sentence of the abstract as the summary, and the most frequent words as the tags.
The fake platform shows how the assistant works, not what a model writes.

### With Hugging Face

Create an [access token](https://huggingface.co/settings/tokens) with the "Make calls to Inference
Providers" permission, then set these variables in `.env.local`:

```bash
AI_PLATFORM=huggingface
HUGGINGFACE_API_KEY=hf_...
```

The default models are:

- `AI_CHAT_MODEL=openai/gpt-oss-120b`: Hugging Face picks one of the inference providers that serve
  it. To pick the provider yourself, add its name to the model, for example
  `AI_CHAT_MODEL=openai/gpt-oss-120b:cerebras`. The model must support tool calling and structured
  output with the provider.
- `AI_EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2`, which runs on the inference service
  of Hugging Face itself (`hf-inference`) and gives vectors of 384 dimensions.

The vectors of two models can't be compared. After a change of `AI_PLATFORM` or
`AI_EMBEDDING_MODEL`, embed the proposals again:

```bash
symfony console assistant:index
```

### With another provider

`generic.default` calls any API compatible with the one of OpenAI, such as a local
[Ollama](https://ollama.com), whose URL is the default of `GENERIC_BASE_URL`.
`config/packages/ai_generic_platform.yaml` configures this platform. The generic bridge treats a
model as an embedding model only when its name contains `embed`. Pick such a model with 384
dimensions, for example `AI_EMBEDDING_MODEL=snowflake-arctic-embed:33m` on Ollama. The chat model
must support tool calling and structured output.

For a provider that needs an API key, add an `api_key` to `ai_generic_platform.yaml`. Other
providers have a bridge of their own, for example `symfony/ai-mistral-platform`. Install the bridge,
configure the platform under `ai.platform` and set `AI_PLATFORM` to the name of the platform
(`mistral`).

The `embedding` column of the proposals has 384 dimensions. An embedding model of another size needs
a migration that changes the column (`ALTER TABLE proposal ALTER COLUMN embedding TYPE vector(N)`,
then the index again), and `assistant:index`.

## The database

The vectors are stored in PostgreSQL with the [pgvector](https://github.com/pgvector/pgvector)
extension, in the `embedding` column of the `proposal` table. `compose.yaml` runs the
`pgvector/pgvector:pg16` image, which is the PostgreSQL 16 image of Debian with the extension. The
migration `Version20261027150923` creates the extension, the column and an HNSW index for the cosine
distance.

A database container created from the `postgres:16-alpine` image of earlier versions of the app has
no pgvector. Create it again from the new image. The data directory works with both images, but
Alpine and Debian don't sort text the same way, so rebuild the indexes of each database:

```bash
docker compose up -d --wait database
for db in $(docker compose exec -T database psql -U app -d app -Atc "SELECT datname FROM pg_database WHERE datallowconn"); do
    docker compose exec -T database psql -U app -d "$db" -c "REINDEX DATABASE \"$db\""
done
symfony console doctrine:migrations:migrate -n
symfony console assistant:index
```

Run the same migration on the test database, e.g. `symfony console doctrine:migrations:migrate -n --env=test`.

## How the proposals are embedded

The model is never called while a speaker waits:

1. `App\Assistant\EmbedChangedProposals` is a Doctrine listener. It notes the new proposals and the
   proposals whose title or abstract changed. After the flush, it dispatches an `EmbedProposal`
   message for each.
2. The message goes to the `async` transport, which stores it in the same database, in the same
   transaction as the proposal.
3. A worker calls the embedding model and stores the vector in the `embedding` column. The Symfony
   CLI starts this worker with `symfony serve`, see `.symfony.local.yaml`.

Loading the fixtures queues a message per proposal too, so the worker embeds them. To embed all the
proposals at once, with one call to the model per 100 proposals, or only the ones that have no
vector yet:

```bash
symfony console assistant:index
symfony console assistant:index --missing
```

Some proposals of the fixtures for SymfonyLive Lille repeat talks of SymfonyCon Amsterdam. For
example, "Finding the slow parts of a Symfony app" is close to "Profiling a Symfony app end to end".

The proposals can also be searched by the meaning of a text, with the retriever of the AI bundle:

```bash
symfony console ai:store:retrieve proposals "the cache is cold after a deployment"
```

## The agent

`config/packages/ai.yaml` configures the platform, the model, the system prompt and the tools of the
`review_assistant` agent. `App\Assistant\ReviewAssistant` calls it with the id of the proposal and
asks for a `ReviewNotes` object as structured output. The notes are kept in the cache for the next
reviewers, until a reviewer asks for them again.

The tools are the methods of `App\Assistant\ReviewTools`:

| Tool | What it does |
|---|---|
| `get_proposal` | reads a proposal: its title, abstract, level, format, status, conference and speakers |
| `list_speaker_proposals` | lists the other proposals of the speakers of a proposal, at all conferences |
| `find_similar_proposals` | lists the closest talks and proposals of all conferences, with their similarity |
| `list_tracks` | lists the tracks of the conferences and what they are about |

The page of a proposal loads the assistant in a Turbo frame, from `/admin/proposals/{id}/assistant`,
so the page doesn't wait for it. The "Summarize" button calls the model.

In development, the AI panel of the web profiler shows the calls to the model and the tools that
the model called, for each request.

## Human in the loop

- The tools are marked read-only with `ToolEffectHints(readOnlyHint: true)`, and
  `App\Assistant\DenyWritingTools` denies any tool call whose tool is not marked read-only. A tool
  that changes data, added to the agent later, can't run without a change of this policy.
- Accepting or rejecting a proposal stays on the "Accept or reject" page. This page asks reviewers
  for their password when they didn't type it in the last 15 minutes
  (`recent_authentication_lifetime` in `security.yaml`). The assistant panel says so.
- The speakers write the proposals, so the system prompt tells the model not to follow the
  instructions of a proposal. The tools protect the decisions, not the prompt. A model that follows
  such instructions has no tool to act on them.

`tests/Assistant/ReviewAssistantTest.php` checks this with a model that tries to accept the
proposal that it reads. The agent has no such tool, the proposal stays in review, and the decision
page still asks for the password. `tests/Assistant/ReadOnlyToolsTest.php` checks the policy.

## MCP tools

The MCP server at `/mcp` (see [mcp.md](mcp.md)) has two tools for the reviewers:

| Tool | What it does |
|---|---|
| `list_proposals_to_review` | lists the proposals of a conference that are submitted or in review |
| `review_proposal` | gives the notes of the assistant and the similar talks and proposals |

Both need the `ROLE_REVIEWER` role and the `proposals:review` scope, checked with `#[IsGranted]`
like `submit_proposal`. A token without the scope gets the `insufficient_scope` challenge.

The Keycloak realm has a `reviewer` / `reviewer` user, with the email of the reviewer of the app,
and the `proposals:review` client scope. Keycloak imports the realm file when the container is
created. Create a container older than the file again
(`docker compose up -d --force-recreate --wait keycloak`). Then:

```bash
TOKEN=$(curl -s -d grant_type=password -d client_id=showcase-cli -d username=reviewer -d password=reviewer \
    -d 'scope=openid proposals:review' -d resource=http://localhost:8124/mcp \
    http://localhost:8180/realms/showcase/protocol/openid-connect/token | jq -r .access_token)
MCP="npx -y @modelcontextprotocol/inspector --cli http://localhost:8124/mcp --transport http --header"

$MCP "Authorization: Bearer $TOKEN" --method tools/call --tool-name list_proposals_to_review --tool-arg conference=symfonylive-lille
$MCP "Authorization: Bearer $TOKEN" --method tools/call --tool-name review_proposal --tool-arg proposal=<id>
```

## Tests

The tests use the fake platform (`AI_PLATFORM=fake` in `.env.test`). It is built with
`MockPlatformFactory`, the mock platform of Symfony AI, so the agent, the tools, the structured
output and the profiler run for real. A test replaces the platform with its own mock through the
`configure_container` option of `createClient()`, as `testTheAssistantCannotDecide()` does.
`AiAssertionsTrait` of the AI bundle asserts the calls to the model and to the tools.

The tests need pgvector in the test database.

## What Symfony AI provides

- The Platform component and its bridges give one API for the models of all providers. They turn
  PHP classes into JSON schemas for structured output and tools. `MockPlatformFactory` builds a
  platform for tests.
- The Agent component runs the loop that calls the model and the tools until the model answers. It
  provides `#[AsTool]`, the tool effect hints, and the `ToolCallRequested` event that can deny a call.
- The Store component and its Doctrine bridge provide the `vector` Doctrine type, the `#[Embedding]`
  attribute, `EntityEmbedder` and `EntityIndexer`. The Doctrine store queries entities by their
  cosine distance with pgvector.
- The AI bundle configures the platforms, agents, vectorizers, stores and retrievers. It provides the
  `ai:agent:call`, `ai:store:retrieve` and `ai:platform:invoke` commands, the profiler panel and
  `AiAssertionsTrait`.

## What the app adds

- `SelectedPlatform` is the platform that `AI_PLATFORM` selects among the platforms tagged
  `ai.platform`. The configuration of an agent gives the id of a platform service, which can't
  come from an env var.
- `FakePlatform`: a platform for development and tests, built on `MockPlatformFactory`.
- The asynchronous embedding. When the Doctrine store of the bundle has a vectorizer, it embeds the
  entities during the flush, which calls the model while the speaker waits. The app configures no
  vectorizer on the store, declares `EntityEmbedder` and `EntityIndexer` in `services.yaml`, and
  embeds the proposals with Messenger and the `assistant:index` command.
- `DenyWritingTools`, the cache of the notes, the panel of the back office and the MCP tools.

## Limits

- With the generic platform, `ai:store:retrieve` sends the text to embed as chat messages. The
  embedding models of the generic bridge accept messages, so the platform turns a single string into
  a message. The assistant is not affected. It embeds lists of texts, and compares the proposals by
  their stored vectors.
- `ai:store:retrieve --limit` returns 5 results at most. The command passes the limit as `maxItems`,
  and the Doctrine store reads `limit`.
- `ai.yaml` lists the tools of the agent under `services`, as the short list form fails
  `lint:yaml --check-schema`.
