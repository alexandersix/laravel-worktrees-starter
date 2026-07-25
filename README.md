# Laravel Worktrees Starter Kit

Laravel Worktrees Starter Kit uses [Laravel Sail](https://laravel.com/docs/13.x/sail) as its development runtime and [Worktrunk](https://worktrunk.dev/) as the first-party local worktree lifecycle. Every Worktrunk worktree gets an independent application container, PostgreSQL, Valkey, Typesense, RustFS, Mailpit, Vite port, and set of Docker volumes.

Worktrunk manages local worktrees and development services only. Continue to use normal Git commits, pushes, pull requests, and remote merges.

## Prerequisites

- macOS, Linux, or WSL2. Native Windows shells are not supported by `bin/worktree`.
- Docker with Docker Compose v2.
- Worktrunk 0.68 or newer.

Install and activate Worktrunk on macOS or Linux:

```sh
brew install worktrunk
wt config shell install
exec "$SHELL" -l
type wt
```

Cargo and other installation methods are documented in the [Worktrunk installation guide](https://worktrunk.dev/#install). Shell integration is required for `wt switch` to change the current directory.

## First checkout

Project hooks are committed in `.config/wt.toml`. Review and approve them once per clone, then provision the primary checkout:

```sh
wt config approvals add
wt setup
```

`wt setup` creates a Worktrunk-managed `.env`, starts the Sail stack, installs dependencies, generates an application key when needed, runs migrations without seeders, builds assets, and starts Vite. Re-run it after changing dependencies or recovering a stopped worktree.

## Daily workflow

Create a feature worktree from the current branch:

```sh
wt switch --create feature/example
```

The create hooks copy only `.env`, `vendor/`, and `node_modules/` from the primary worktree, reconcile dependencies, start an isolated Sail stack, run migrations, build assets, and start Vite. Use `wt list` to see every worktree and its application URL.

Common commands:

```sh
wt list                         # list worktrees and application URLs
wt stop                         # stop this Sail stack; keep containers and volumes
wt setup                        # start or repair this stack and launch Vite
wt ports                        # show this stack's service URLs and ports
wt destroy                      # destroy this stack and all of its local volumes
wt remove feature/example       # remove a merged worktree and run teardown
```

`wt remove` invokes the guarded teardown hook before deleting the worktree. Teardown permanently deletes that worktree's PostgreSQL, Valkey, Typesense, and RustFS volumes. It cannot affect another worktree because the helper verifies the managed branch and Compose project markers in `.env` before running `docker compose down --volumes`.

## Services and troubleshooting

The setup command prints all assigned ports. Run `wt ports` to show the same summary again. The application and Vite ports are deterministic per worktree; Docker assigns collision-free host ports to the remaining services. The application URL is also shown by `wt list`. You can inspect an individual service port with a command such as `vendor/bin/sail port pgsql 5432`.

Run application commands through Sail:

```sh
vendor/bin/sail artisan test --compact
vendor/bin/sail composer ci:check
vendor/bin/sail logs -f
```

Use `wt config state logs` to find output from background hooks such as Vite. If setup failed before `.env` was marked as managed, inspect the worktree and use `wt remove --no-hooks` only after confirming there is no stack to preserve. If a branch was renamed, run `wt setup` before stopping or removing it so the safety markers are refreshed.

Each developer must approve the project hooks in their own clone. Worktrunk asks for approval again when a project command changes; adding the future Git remote may also change the project identifier and require reapproval.

Codex activity markers are optional and user-scoped:

```sh
wt config plugins codex install
```

The plugin adds status tracking to `wt list`; Codex and other agents should still invoke the project commands above directly.
