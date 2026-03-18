# Server Split

Self-service plugin for Pelican with aggregated per-user quotas. The user gets a shared resource pool and can split it across multiple servers without exceeding the defined total.

## What this plugin does

* creates per-user quotas for `servers`, `CPU`, `memory`, `disk`, `databases`, `backups`, and `allocations`
* blocks server creation in the backend, including when the action comes from another screen that resolves `App\Services\Servers\ServerCreationService`
* adds an admin screen to configure each user's individual quota
* adds an app screen where the user can create servers while respecting their remaining quota
* adds a shortcut in the app panel topbar to the provisioning screen

## Installation

1. Place the `server-split` folder inside the panel's `plugins/` directory.
2. Import or enable the plugin through Pelican's native plugin manager.
3. Install the plugin from the panel to run the migration.
4. Configure the quotas in `Admin > Server Split`.

## How the quota works

* `max_servers`: maximum number of servers the user can own.
* `max_cpu`: total sum of the `cpu` field across all of the user's servers.
* `max_memory`: total memory sum in MiB.
* `max_disk`: total disk sum in MiB.
* `max_databases`: total sum of `database_limit` across all of the user's servers.
* `max_backups`: total sum of `backup_limit` across all of the user's servers.
* `max_allocations`: total sum of `allocation_limit` across all of the user's servers.

Empty fields mean unlimited.

If a finite quota exists for CPU, memory, or disk, the plugin rejects requests with an unlimited resource value (`0`), because that would break the shared resource pool model.

## Configuration

The [`config/server-split.php`](./config/server-split.php) file allows you to:

* define default quotas for users without their own record
* adjust technical defaults for self-service creation
* enable or disable enforcement of `ServerCreationService`

## Current limitations

* the self-service flow only uses an existing primary allocation; it does not create new allocations
* databases, backups, and allocations are still defined by default through configuration in the self-service flow