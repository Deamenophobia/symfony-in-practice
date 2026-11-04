# Symfony in Practice

Eight complete articles and runnable backend experiments for learning Symfony alongside Laravel and modern PHP. A small account-based ordering API connects the examples: authenticated customers create server-priced orders, read their own orders, and arrange a local fulfillment task through Messenger.

Start with the [article index](docs/articles/README.md). Each article includes a runnable lab, measured results, and primary references. The companion has no frontend application. The examples distinguish PHP language behavior, Symfony component behavior, and rules supplied by the application.

## Source and published articles

This is the public companion repository for the [Symfony articles on Happy Kokoro](https://happykokoro.com/posts#collection-symfony-in-practice). Clone the full repository to obtain the tutorial revision tags:

```sh
git clone https://github.com/Deamenophobia/symfony-in-practice.git
cd symfony-in-practice
git tag --list
```

The default branch includes the completed application and documentation. Each article names its starting or completed tag when a particular revision is needed. Install dependencies and prepare the disposable database using the setup instructions below. Public synthetic fixture credentials belong only to the local learning environment.

- [Symfony request handling from PHP objects to kernel events](https://happykokoro.com/posts/symfony-in-practice-http-kernel)
- [Modern PHP types and metadata in a Symfony application](https://happykokoro.com/posts/symfony-in-practice-modern-php)
- [Symfony dependency injection and the compiled service container](https://happykokoro.com/posts/symfony-in-practice-service-container)
- [Symfony request mapping and validation at the API boundary](https://happykokoro.com/posts/symfony-in-practice-input-validation)
- [Doctrine entities and the boundary between objects and SQL](https://happykokoro.com/posts/symfony-in-practice-doctrine)
- [Symfony authentication and voters for account-owned orders](https://happykokoro.com/posts/symfony-in-practice-security)
- [Symfony Messenger retries and repeated delivery](https://happykokoro.com/posts/symfony-in-practice-messenger)
- [Testing and operating the Symfony ordering backend](https://happykokoro.com/posts/symfony-in-practice-testing-and-operations)

See [the source release record](docs/SOURCE-RELEASE-2026-11-04.md) for the preserved revisions and verification limits.

## Reading order

| Part | Article | Main experiment |
|---|---|---|
| 1 | [HTTP objects and kernel events](docs/articles/01-http-kernel.md) | Trace successful and missing routes |
| 2 | [Modern PHP types and metadata](docs/articles/02-modern-php.md) | Strict callers, readonly objects, enums, and attributes |
| 3 | [The compiled service container](docs/articles/03-service-container.md) | Resolve an interface, replace its implementation, dump a container |
| 4 | [Request mapping and validation](docs/articles/04-input-validation.md) | Compare malformed, mistyped, invalid, and valid input |
| 5 | [Doctrine and the unit of work](docs/articles/05-doctrine-unit-of-work.md) | Observe SQL, identity, flush, and rollback |
| 6 | [Authentication and voters](docs/articles/06-security-voters.md) | Enforce credential abilities and account ownership |
| 7 | [Messenger workers and retries](docs/articles/07-messenger-workers.md) | Deliver duplicates, retry a fault, retain failed work |
| 8 | [Tests, console commands, and operations](docs/articles/08-testing-and-operations.md) | Preview, dispatch, and verify missing background work |

## Recorded runtime

| Dependency | Version used |
|---|---|
| PHP | 8.4.27 |
| Symfony FrameworkBundle, HttpKernel, Messenger | 7.4.22 |
| Doctrine ORM / DBAL | 3.7.6 / 4.5.0 |
| MySQL | 8.4.10 |
| PHPUnit | 12.5.43 |

Symfony 7.4 is the chosen LTS line. Each installed package's exact version is in `composer.lock`; some components have different patch versions. Run `composer install` to reproduce the dependency set. Composer's PHP platform setting assists dependency resolution; the real runtime must still supply PHP 8.4 and the required extensions, including `pdo_mysql`.

The repository tags are `symfony-start` (installed skeleton and dependencies) and `symfony-complete` (application, tests, labs, and manuscripts). Both are available in this public repository and retain their original commits. Use the article’s named revision when comparing recorded results.

## Prepare the disposable database

Use a local MySQL 8.4 server. The checked-in connection defaults to loopback port **3309**. From this repository, execute the following SQL file using a database administrator account:

```sh
mysql --host=127.0.0.1 --port=3309 --user=root --password --execute="SOURCE scripts/setup-database.sql"
```

The file creates the isolated `symfony_practice_test` schema and a user granted access only to that schema. Its password, the application secret, and all seeded bearer tokens are public synthetic learning values.

If your local server uses another port or credentials, put its `DATABASE_URL` in `.env.test.local` for PHPUnit and labs, and in `.env.local` for the development API. Keep the database name `symfony_practice_test`: both the fixture and migration enforce that name. Symfony deliberately skips `.env.local` in the test environment. Neither override file is tracked.

Install dependencies and seed the database:

```sh
composer install
php scripts/reset.php
```

The reset verifies the actual connected schema and test environment before dropping its known tables. It then runs the migration, prepares the Messenger transport, and seeds products and credentials. **Tests and labs 4–8 reset this disposable database. Run them sequentially.** They do not share tables or credentials with the Laravel companion. Labs 1–3 still connect to MySQL to record runtime metadata.

If an account with the same MySQL user name already exists, `CREATE USER IF NOT EXISTS` preserves its password; configure the matching local credentials before continuing.

## Make a real HTTP request

Start a loopback development server from the repository:

```sh
php -S 127.0.0.1:8089 -t public public/index.php
```

On this Windows workspace, `scripts/local.ps1 -Action Start` starts that server in the background with the PHP executable at `C:\dev\sdk\php-8.4.27\php.exe`. Use `-Php` to select another executable. `scripts/local.ps1 -Action Stop` stops only the process recorded for this repository. MySQL must already be running.

The API has three routes:

| Route | Contract |
|---|---|
| `GET /health` | Public JSON health response and lab trace headers |
| `POST /api/orders` | Bearer authentication and `orders:write`; server selects the account's price |
| `GET /api/orders/{id}` | Bearer authentication, `orders:read`, and ownership voter |

PowerShell request example:

```powershell
$headers = @{ Authorization = 'Bearer writer-one' }
$order = Invoke-RestMethod -Method Post -Uri 'http://127.0.0.1:8089/api/orders' `
    -Headers $headers -ContentType 'application/json' `
    -Body '{"sku":"COFFEE","quantity":2,"reference":"PO-100"}'
Invoke-RestMethod -Uri "http://127.0.0.1:8089/api/orders/$($order.data.id)" -Headers $headers
```

Account 1's unit price is 1,250 SGD cents, so this request produces a total of 2,500 cents. `reader-one` can read account 1's orders; `writer-two` belongs to account 2. A request with `writer-two` for the first account's order returns 403. The fixture also supplies expired and revoked tokens for rejection tests.

Creating an order persists it without dispatching a message. To arrange missing local tasks:

```sh
php bin/console app:orders:reconcile --dry-run
php bin/console app:orders:reconcile --limit=20
php bin/console messenger:consume async --limit=1 --time-limit=5 --sleep=0.01
```

The reconciliation command selects orders without a fulfillment task. A unique key and upsert make equivalent successful deliveries converge on one local task. This example does not call a shipping provider or claim an atomic transaction with an external service.

## Reproduce the article experiments

The [article index](docs/articles/README.md) links every script to its saved JSON evidence. Run a selected lab with PHP, for example:

```sh
php scripts/labs/05-doctrine.php
php scripts/labs/07-messenger.php
php bin/phpunit
```

An individual lab updates its corresponding file under `docs/evidence`. To preserve those recorded measurements, set `LAB_VERIFY_ONLY=1`; new evidence then goes under ignored `.runtime/verification`.

On Windows, one command checks the complete companion:

```powershell
.\scripts\verify.ps1
```

It refreshes the test container, checks YAML and dependency wiring, validates ORM mappings, runs PHPUnit and eight labs, checks PHP syntax, and compiles the production container. It automatically preserves the tracked evidence. Doctrine mapping validation uses `--skip-sync` because tokens, products, and operational tables are managed by explicit migrations rather than ORM schema synchronization.

Read [the verification record](docs/VERIFICATION.md) for measured results and their limits. The application is a learning companion: it has a compact one-SKU order model and synthetic authentication, with no token issuance UI, inventory reservation, payments, or production deployment. The Laravel comparison explains matching responsibilities without claiming feature parity.
