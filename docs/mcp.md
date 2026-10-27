# The MCP server

The app serves an MCP server at `/mcp`, so that AI clients such as Claude Code, Claude
Desktop or the MCP Inspector can search the program and submit proposals in the name of a
speaker. It uses the MCP bundle of [Symfony AI](https://github.com/symfony/ai)
(`symfony/mcp-bundle` 1.0.0-BETA1) and the official PHP SDK (`mcp/sdk` 0.9).

Keycloak issues the access tokens, and an `access_token` firewall of Symfony checks them, the
same way as for `/api/*`. The tokens of the MCP server are bound to its URL with the resource
indicators of RFC 8707, so they can't be used on the API, and the other way round. The MCP SDK
has its own OAuth layer, which the app doesn't use.

## Tools and resources

| Name | What it does | Needs |
|---|---|---|
| `search_talks` | searches the accepted talks of all conferences, in their title and abstract | a token |
| `get_schedule` | gives the time, room, title and speakers of each scheduled talk of a conference | a token |
| `list_my_proposals` | lists the proposals of the speaker who is logged in, with their status | a token |
| `submit_proposal` | submits a talk to an open call for papers, validated like the API does | the `proposals:write` scope |

The schedule is also a resource: `conference://{conference}/schedule`, e.g.
`conference://symfonylive-lille/schedule`.

The code is in `src/Mcp`. The tools are methods of `ConferenceTools` with the `#[McpTool]`
attribute of the SDK. They call the services of the app: the repositories, the
`ProposalSubmitter` of the call for papers, the validator and the object mapper.

## Running it

Start the app as the [README](../README.md) says, on the port 8124. Then:

```bash
curl -i -X POST http://localhost:8124/mcp
```

The answer is a `401` with `WWW-Authenticate: Bearer resource_metadata="http://localhost:8124/.well-known/oauth-protected-resource/mcp"`.
That document tells the client where to get a token, and for which resource:

```json
{"resource":"http://localhost:8124/mcp","authorization_servers":["http://localhost:8180/realms/showcase"],"scopes_supported":["proposals:write"],"bearer_methods_supported":["header"]}
```

Connect the clients to `http://localhost:8124/mcp`, not to `127.0.0.1`. Keycloak only issues
tokens for this URL, and the `mcp` firewall only accepts them.

## Keycloak

The compose file runs Keycloak 26.8 with two experimental features that MCP clients use, as
[the Keycloak guide for MCP](https://www.keycloak.org/securing-apps/mcp-authz-server) describes:

- `resource-indicators` (RFC 8707). When a client sends `resource=http://localhost:8124/mcp`,
  Keycloak checks that a client of the audience of the token has this URL as its `resource_url`
  attribute, and the token gets this URL as its only audience. The `showcase-api` client of the
  realm is that resource server: the `showcase-api` client scope already puts it in the audience
  of the tokens. Another `resource` gets an `invalid_target` error. Without `resource`, the token
  keeps the `showcase-api` audience, which only the API accepts.
- `cimd` (client ID metadata documents). Claude Code and Claude Desktop use the URL of a
  document on `claude.ai` as their client id, so they need no client of their own in the realm.
  The `mcp-cimd` client policy lets Keycloak fetch these documents, and Keycloak asks the user
  to consent to such a client on the first login. The policy also accepts documents served over
  HTTP from `host.docker.internal`, to test a client locally: don't keep that in production.

Clients that don't use a metadata document log in with the `showcase-mcp` client: a public
client with PKCE, whose redirect URIs are the ones of the MCP Inspector and of Claude Code.

Keycloak imports `docker/keycloak/showcase-realm.json` when its container is created. If your
Keycloak container is older than this file, create it again (this drops what was stored in
Keycloak since, such as sessions):

```bash
docker compose up -d --force-recreate --wait keycloak
```

## Testing with the MCP Inspector

Get a token for the MCP server with the `showcase-cli` client, which accepts a password, then
call the tools with the command-line mode of the
[MCP Inspector](https://github.com/modelcontextprotocol/inspector):

```bash
TOKEN=$(curl -s -d grant_type=password -d client_id=showcase-cli -d username=alice -d password=alice \
    -d scope=openid -d resource=http://localhost:8124/mcp \
    http://localhost:8180/realms/showcase/protocol/openid-connect/token | jq -r .access_token)
MCP="npx -y @modelcontextprotocol/inspector --cli http://localhost:8124/mcp --transport http --header"

$MCP "Authorization: Bearer $TOKEN" --method tools/list
$MCP "Authorization: Bearer $TOKEN" --method tools/call --tool-name search_talks --tool-arg query=workflow
$MCP "Authorization: Bearer $TOKEN" --method resources/read --uri conference://symfonylive-lille/schedule
```

The Inspector 2 needs Node.js 22.19 or later. With an older Node.js, use
`@modelcontextprotocol/inspector@1.0.3`.

This token doesn't have the `proposals:write` scope, so `submit_proposal` gets a `403` with
`WWW-Authenticate: Bearer error="insufficient_scope",scope="proposals:write",...`. Ask for the
scope (`-d 'scope=openid proposals:write'`) to submit a proposal:

```bash
$MCP "Authorization: Bearer $TOKEN" --method tools/call --tool-name submit_proposal \
    --tool-arg conference=symfonylive-lille 'title=MCP servers with Symfony' \
    'abstract=How the MCP bundle of Symfony AI exposes the services of an app, behind its firewall.' \
    level=intermediate format=talk 'speakerName=Alice Martin' 'bio=Alice builds Symfony apps.'
```

## Connecting Claude Code

Claude Code finds Keycloak through the `401` and the metadata document, and identifies itself
with its client ID metadata document:

```bash
claude mcp add --transport http conferences http://localhost:8124/mcp
```

Then run `/mcp` in Claude Code to log in, as `alice` / `alice`. With a version of Claude Code
that doesn't use metadata documents, give it the `showcase-mcp` client, and the port of the
redirect URI that Keycloak knows:

```bash
claude mcp add --transport http --client-id showcase-mcp --callback-port 8125 conferences http://localhost:8124/mcp
```

To submit proposals, Claude Code must ask for the `proposals:write` scope. Pin it in `.mcp.json`:

```json
{
    "mcpServers": {
        "conferences": {
            "type": "http",
            "url": "http://localhost:8124/mcp",
            "oauth": {"scopes": "proposals:write"}
        }
    }
}
```

Without the scope, `submit_proposal` fails with a message that names the missing scope. Add it
to `oauth.scopes`, then log in again with `/mcp`.

Claude Desktop connects to remote MCP servers through the connectors of claude.ai, which call
the server from the internet. The app must be deployed for that, with `mcp.servers.conferences.http.allowed_hosts`
set to its host name, and `MCP_RESOURCE` set as the next section says.

## Deploying under another origin

The URL of the MCP server is `http://localhost:8124/mcp` in two places. When the app runs under
another origin, change both:

- `MCP_RESOURCE`, for example `MCP_RESOURCE=https://conferences.example.com/mcp`: the `mcp`
  firewall only accepts the tokens issued for this URL;
- the `resource_url` attribute of the `showcase-api` client of the Keycloak realm: Keycloak only
  issues tokens for this URL.

The metadata document needs no change. Its `resource` is `/mcp`, which Symfony resolves against
the origin of the request.

## What Symfony 8.2 provides

- The `oidc` token handler of the `access_token` authenticator checks the signature, the
  issuer and the audience of the tokens, with the keys of the discovery document of Keycloak,
  and loads the user from the `email` claim. The `api` firewall expects the `showcase-api`
  audience, and the `mcp` firewall the URL of the MCP server, from `MCP_RESOURCE`. The two
  firewalls share the rest of their configuration through YAML anchors.
- `resource_metadata` serves the RFC 9728 document of each firewall, and advertises it in the
  `WWW-Authenticate` header of the `401`. The `api` firewall doesn't set its `resource`, which
  is then the origin of the request, served at `/.well-known/oauth-protected-resource`. The
  `mcp` firewall sets it to `/mcp`, which Symfony resolves against the origin of the request.
  Its document is served at `/.well-known/oauth-protected-resource/mcp`, the path RFC 9728
  derives from the resource.
- `OAUTH2_SCOPE()` checks the scopes of the token, and a denial gets the
  `insufficient_scope` challenge of RFC 6750, with the missing scope. The MCP authorization
  specification asks for that challenge, so that clients ask the user for the scope.
- `loginUser($user, 'mcp', [AccessTokenAuthenticator::SCOPE_ATTRIBUTE => [...]])` logs a user
  in on the stateless firewall in tests, with scopes: `tests/Mcp/McpServerTest.php` runs the
  MCP handshake and calls the tools with it, without Keycloak.

## What the MCP bundle and the SDK provide

- The Streamable HTTP transport: a route and a controller at `/mcp`, the JSON-RPC protocol, the
  sessions (`Mcp-Session-Id`, stored in files by default) and the protection against DNS
  rebinding, which only accepts the `localhost`, `127.0.0.1` and `[::1]` hosts unless
  `allowed_hosts` says otherwise.
- The `#[McpTool]`, `#[McpResource]`, `#[McpResourceTemplate]` and `#[McpPrompt]` attributes on
  autowired services. The input schema of a tool comes from the signature and the docblock of
  its method: backed enums become `enum` lists.
- Tool errors: a `ToolCallException` becomes a result with `isError: true`, which the model reads.
  The tools use it for the closed calls for papers, the unknown conferences and the validation
  errors.
- `bin/console debug:mcp` lists the tools, resources and prompts of each server.

## What the app adds

The MCP server catches every exception of the tools and turns it into a JSON-RPC error, so an
`AccessDeniedException` thrown by a tool never reaches the firewall, and the bundle doesn't read
`#[IsGranted]` on tools. `src/Mcp/IsGrantedToolListener.php` adds the `#[IsGranted]` attributes
of the tools that a request calls to the attributes of the MCP controller. Symfony checks them
before the controller runs, as for any controller, and the firewall answers a missing scope with
the challenge. That's why `submit_proposal` only needs this:

```php
#[McpTool(name: 'submit_proposal')]
#[IsGranted('OAUTH2_SCOPE(proposals:write)')]
public function submitProposal(string $conference, string $title, /* ... */): ProposalResult
```

The tools get the user from the `Security` service: `#[CurrentUser]` only works on controllers.

## Limits

- `#[IsGranted]` on a tool can't name a subject: the subjects of `#[IsGranted]` are the
  arguments of the controller, not the ones of the tool.
- The MCP sessions aren't bound to the user who opened them. A session opened with a token of
  `alice` accepts the token of `carol`. The tools still act for the user of the token of each
  request.
- The metadata document is served with `Access-Control-Allow-Origin: *`, but `/mcp` sends no
  CORS headers, and its `access_control` rule answers the preflight requests with a `401`. So
  the MCP clients that run in a browser can't connect. The command-line clients and Claude Code
  don't need CORS.
- When a token lacks a scope, the TypeScript MCP SDK (1.32) refreshes the token instead of
  asking the user for the scope, if it has a refresh token, and then gets the `403` again. The
  step-up only works for the clients that start a new authorization.
  modelcontextprotocol/typescript-sdk#3114 fixes it for 1.x.
