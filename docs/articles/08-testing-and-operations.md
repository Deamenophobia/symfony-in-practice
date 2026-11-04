# Testing and operating the Symfony ordering backend

A request succeeds, yet its background task is missing. An operator needs a way to find that condition, preview the proposed work, enqueue it, and verify the effect. The same operation should have tests that exercise its real command contract and worker behavior.

Part 8 completes **Symfony in Practice** with a reconciliation command, focused tests, and the runtime checks used for the companion. It also compares the responsibilities we have now implemented in both Symfony and Laravel. The examples use PHP 8.4.27, Symfony 7.4.22, and PHPUnit 12.5.43.

## Turn an operational action into a command

`src/Command/ReconcileOrders.php` declares its console entry point with an attribute:

```php
#[AsCommand(
    name: 'app:orders:reconcile',
    description: 'Enqueue orders missing their local fulfillment effect.',
)]
```

The container supplies a database connection and message bus. The command queries orders without a corresponding fulfillment task, in ID order, and applies a bounded limit. Every stored order in this small companion is eligible for that local task; a richer fulfillment workflow would include its approval or reservation criteria in the selection.

`--dry-run` returns the selected order IDs without sending messages. `--limit` must be an integer from one to one hundred. Invalid input returns the console invalid-input status, 2. A successful preview or dispatch returns 0.

The same query may select an order again before a previously queued message finishes. The Messenger handler's unique-key protection makes those equivalent deliveries safe for this local effect. The command therefore repairs missing work without relying on a fragile assumption that dispatch happens only once.

Symfony's [console documentation](https://symfony.com/doc/7.4/console.html) describes command registration, arguments, options, and exit status. The business selection and output contract remain application code.

## Test the command's observable contract

The console lab uses the real command through `CommandTester`:

```php
$application = new Application(labKernel());
$tester = new CommandTester(
    $application->find('app:orders:reconcile'),
);
$exit = $tester->execute(['--dry-run' => true]);
```

After creating one order, it verifies that the dry run reports ID 1, enqueues nothing, and leaves the transport table empty. It then executes the actual dispatch, starts a separate worker, and runs the preview again. The final preview reports no pending IDs because the task now exists.

`docs/evidence/08-console-and-tests.json` records:

| Operation | Exit or result |
|---|---|
| Invalid limit zero | Exit 2 |
| Preview pending order | Exit 0; ID 1; zero enqueued |
| Inspect queue after preview | Zero messages |
| Dispatch with limit one | Exit 0; one enqueued |
| Consume in separate worker | One fulfillment task |
| Preview again | No pending IDs; zero enqueued |

Run this complete experiment from the `symfony-complete` checkout after the root README's setup:

```sh
php scripts/labs/08-console-and-tests.php
```

The script recreates only `symfony_practice_test`. The standalone command can also be inspected with `php bin/console app:orders:reconcile --help` before using its preview mode.

## Choose tests by the boundary they exercise

The PHPUnit suite contains two groups. `DomainTest` constructs plain PHP objects and verifies money arithmetic, quantity bounds, overflow rejection, and the order confirmation rule. It does not boot Symfony or connect to MySQL.

`OrderApiTest` extends `WebTestCase`. It boots the configured application and uses `KernelBrowser` to submit requests through routing, authentication, mapping, validation, application code, and persistence. Those tests check server pricing, account isolation, rejected input, and credential limits against real MySQL.

The suite records 18 tests and 30 assertions. That count is only a description of this suite; the useful coverage is which behavior would fail if the implementation regressed. The separate labs add observations such as SQL, kernel traces, and worker state that are valuable for the articles. [Symfony testing guide](https://symfony.com/doc/7.4/testing.html)

The data providers also demonstrate another PHP feature used by ordinary tooling:

```php
public static function invalidQuantities(): iterable
{
    yield [0];
    yield [-1];
    yield [21];
}
```

`yield` creates a generator that produces the cases. PHPUnit reads the provider named by its attribute and decides when to iterate it. The language supplies the iterable behavior; the testing library supplies the test-registration behavior. [PHP generators](https://www.php.net/manual/en/language.generators.overview.php)

## Keep destructive fixtures isolated

`DatabaseFixture::reset()` checks both the kernel environment and the actual connected database name before replacing its known tables. It accepts only the `test` environment and `symfony_practice_test`. The reset then applies the migration, prepares Messenger's transport table, and seeds synthetic products and credentials.

The fixture is deliberately disposable. Tests and labs run sequentially because they share that schema. A parallel test runner would need separate databases and an adapted reset contract; changing the number of test processes alone would make them interfere with one another.

There are also different kinds of isolation. Kernel requests run inside the test process, while Messenger consumers are separate PHP processes with their own containers and database connections. A mocked transport could help test dispatch intent, but the delivery lab needs the real transport to establish retries and persisted effects.

## Verify configuration as well as behavior

The repository provides one PowerShell entry point:

```powershell
.\scripts\verify.ps1
```

It refreshes the test container, lints YAML and service wiring, validates Doctrine mappings, runs PHPUnit and all eight labs, checks PHP syntax, and warms the production container. New evidence goes under ignored `.runtime/verification`, preserving the checked-in article measurements.

Doctrine mapping validation runs with `--skip-sync`. The application has explicit migrations for tables beyond its ORM entity, including tokens and queue-related state; automatic ORM schema synchronization is not the schema-management path used by this repository.

The following inspection commands are useful when the application behaves differently from the code you expect:

```sh
php bin/console debug:router --env=test --no-debug
php bin/console debug:container 'App\Domain\PriceCatalog' --env=test --no-debug
php bin/console lint:container --env=test --no-debug
```

They identify the loaded routes and selected service implementation. A successful container compilation is a configuration check, while a successful API or worker assertion establishes behavior across its additional boundaries.

## Carry those boundaries into deployment

A deployment needs the intended PHP runtime and extensions, locked production dependencies, environment configuration, compatible database migrations, and a prepared application cache. Long-running consumers must load the intended code version, use the correct transport, and receive a bounded shutdown interval from their process manager. Symfony's [deployment guide](https://symfony.com/doc/7.4/deployment.html) describes the general sequence.

The companion's production-container warmup checks that its application services compile without the test-only delivery probe and SQL recorder. It does not deploy the fixture or turn its public synthetic credentials into a production authentication design.

The local development server binds to loopback and serves the `public` entry point. A deployed web server or PHP-FPM setup has a different concurrency and operational lifecycle. For workers, a stop request and process restart are part of loading new code; pending serialized messages and the schema they depend on must remain compatible during that change.

## Use both frameworks to identify the enduring responsibilities

The two companions now give concrete points of comparison:

| Responsibility | Laravel companion | Symfony companion |
|---|---|---|
| HTTP coordination | Router and middleware pipeline | Router, HttpKernel, and events |
| Object construction | Container bindings | Service definitions, aliases, compiled container |
| Request input | Form Request | Serializer, mapped DTO, Validator |
| Persistence | Eloquent model operations | Entity manager and unit of work |
| Resource authorization | Policy | Voter |
| Background processing | Jobs and workers | Messages, transports, handlers, workers |
| Operator entry point | Artisan command | Console command |

The comparison is useful because the same application questions survive a change of framework: where identity comes from, when input becomes trusted, when SQL commits, and how repeated work affects persisted state. PHP's types, interfaces, attributes, closures, enums, and generators support both implementations. Learning Symfony alongside Laravel gives you more opportunities to see which behavior comes from the language, which comes from a reusable component, and which rule your application must supply.
