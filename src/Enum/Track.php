<?php

namespace App\Enum;

/**
 * The tracks of the conferences, which the review assistant suggests for each proposal.
 */
enum Track: string
{
    case Architecture = 'architecture';
    case Performance = 'performance';
    case Security = 'security';
    case Testing = 'testing';
    case Frontend = 'frontend';
    case AI = 'ai';
    case Community = 'community';

    public function describe(): string
    {
        return match ($this) {
            self::Architecture => 'The design of applications: workflows, state machines, messaging, components and domain models',
            self::Performance => 'Profiling, the cache, the database and slow pages, from the server to the browser',
            self::Security => 'Authentication, authorization, cryptography and the protection of personal data',
            self::Testing => 'Tests, static analysis, continuous integration and the quality of code',
            self::Frontend => 'Forms, templates, assets, user interfaces and the browser',
            self::AI => 'Models, agents, embeddings and MCP servers in applications',
            self::Community => 'Open source, teams, careers and the ecosystem',
        };
    }
}
