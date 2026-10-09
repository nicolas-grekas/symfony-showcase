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

## Findings

| # | Finding | Kind | Status |
|---|---|---|---|
| 1 | Recipes write `framework.cache`, `framework.messenger`, `framework.validation`... while 8.2 owns these keys at the root | recipe | open: 8.2 recipe dirs with `conflict: framework-bundle <8.2`; the webapp-pack and third-party recipes (DoctrineBundle...) must keep `framework.*` |
| 2 | `debug:container --env-vars` reports `APP_ENV`, `APP_SHARE_DIR` (and `KERNEL_CLASS` in test) as unused | bug, 8.2 | symfony/symfony#66747 |
| 3 | `debug:config framework cache` fails, `config:dump-reference framework` shows `cache: ~` with no hint | DX, 8.2 | symfony/symfony#66748 |
| 4 | `lint:yaml config --check-schema` rejects 5 of the 24 files of the skeleton, all valid | bug, 8.2 and 7.4 | symfony/symfony#66746 (8.2), symfony/symfony#66745 (7.4) |
| 5 | `cache:warmup --env=prod` logs a deprecation: DoctrineBundle extends the deprecated FrameworkBundle cache warmer | bug, third party | upstream: doctrine/DoctrineBundle#2289, open since 2026-09-16 |
| 6 | Schema validation needs `opis/json-schema`, which the webapp pack does not install, and is opt-in with `--check-schema` | DX, pack | symfony/webapp-pack#23 |
| 7 | Recipe text: `ux_turbo.yaml` reuses the comment of `csrf.yaml`, `notifier.yaml` mentions `sms/nexmo`, the mailer post-install text says `framework.messenger.routing` and `doc/master` | recipe | symfony/recipes#1591 |
| 8 | `debug:roles` (new) and `debug:security:role-hierarchy` overlap; `debug:roles` is the only description ending with a period | DX, 8.2 | symfony/symfony#66749 |
| 9 | `make:auth` is described as "Create a Guard authenticator" | MakerBundle | open |
