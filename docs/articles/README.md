# Symfony in Practice — article index

This eight-part series follows one backend through HTTP handling, modern PHP, dependency injection, validation, persistence, security, background work, and operational tests. Read it alongside the Laravel companions to compare where each framework puts the same responsibilities.

Each manuscript is complete, includes its own reproducible experiment, and links to official documentation or pinned source. PHP's types, interfaces, attributes, enums, readonly properties, and generators are useful language features; Symfony demonstrates how libraries and applications build on them. These features are not exclusive to Symfony or to PHP among programming languages.

| Part | Manuscript | Runnable lab | Saved evidence |
|---|---|---|---|
| 1 | [Symfony request handling from PHP objects to kernel events](01-http-kernel.md) | [01-http-kernel.php](../../scripts/labs/01-http-kernel.php) | [HTTP results](../evidence/01-http-kernel.json) |
| 2 | [Modern PHP types and metadata in a Symfony application](02-modern-php.md) | [02-modern-php.php](../../scripts/labs/02-modern-php.php) | [Language results](../evidence/02-modern-php.json) |
| 3 | [Symfony dependency injection and the compiled service container](03-service-container.md) | [03-container.php](../../scripts/labs/03-container.php) | [Container results](../evidence/03-container.json) |
| 4 | [Symfony request mapping and validation at the API boundary](04-input-validation.md) | [04-validation.php](../../scripts/labs/04-validation.php) | [Input results](../evidence/04-validation.json) |
| 5 | [Doctrine entities and the boundary between objects and SQL](05-doctrine-unit-of-work.md) | [05-doctrine.php](../../scripts/labs/05-doctrine.php) | [SQL and transaction results](../evidence/05-doctrine.json) |
| 6 | [Symfony authentication and voters for account-owned orders](06-security-voters.md) | [06-security.php](../../scripts/labs/06-security.php) | [Authentication and access results](../evidence/06-security.json) |
| 7 | [Symfony Messenger retries and repeated delivery](07-messenger-workers.md) | [07-messenger.php](../../scripts/labs/07-messenger.php) | [Worker results](../evidence/07-messenger.json) |
| 8 | [Testing and operating the Symfony ordering backend](08-testing-and-operations.md) | [08-console-and-tests.php](../../scripts/labs/08-console-and-tests.php) | [Console results](../evidence/08-console-and-tests.json) |

Use the [root README](../../README.md) for dependencies, database setup, API requests, and verification commands. The labs use PHP 8.4.27, Symfony 7.4.22, and MySQL 8.4.10. The [verification record](../VERIFICATION.md) distinguishes in-process HTTP tests from actual TCP requests and separate worker processes.

The canonical manuscripts live in this public repository. Read the [published Symfony in Practice series](https://happykokoro.com/posts#collection-symfony-in-practice); the root README lists each public article and clone instructions.
