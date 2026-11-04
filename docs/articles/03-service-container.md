# Symfony dependency injection and the compiled service container

The order service needs a product price. It should not need to know whether that price comes from MySQL or a controlled test fixture. A PHP interface can state the dependency, but an interface cannot choose which concrete object the application should construct. That choice belongs in the application's wiring.

Part 3 of **Symfony in Practice** follows that wiring from a constructor type to an explicit alias and a compiled container. We deliberately leave an interface ambiguous, select two different implementations, and run the container dumped as PHP code. The experiment uses Symfony DependencyInjection 7.4.22 on PHP 8.4.27.

## Start with a dependency that means something

The domain declares a price catalog:

```php
interface PriceCatalog
{
    public function price(int $accountId, string $sku): Money;
}
```

The account argument matters because the same SKU may have different prices for different wholesale accounts. The production-shaped implementation, `SqlPriceCatalog`, queries the product table with both account and SKU. Its constructor receives a Doctrine DBAL connection.

`PlaceOrder` asks for the interface and an entity manager:

```php
public function __construct(
    private PriceCatalog $catalog,
    private EntityManagerInterface $entities,
) {}
```

The class does not construct either dependency. Receiving collaborators through a constructor also makes them available in a plain PHP test, without requiring the class itself to reach into a global container. Symfony's [service container guide](https://symfony.com/doc/7.4/service_container.html) introduces that construction model.

## Distinguish discovery, autowiring, and autoconfiguration

The application's `config/services.yaml` establishes three related choices:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
    App\:
        resource: '../src/'
        exclude: '../src/{Domain,Dto,Entity,Message,Kernel.php,Security/ApiUser.php}'
    App\Domain\PriceCatalog: '@App\Infrastructure\SqlPriceCatalog'
```

Resource discovery turns suitable application classes into service definitions. Autowiring uses constructor and argument types to find compatible service dependencies. Autoconfiguration applies registered conventions such as tags for message handlers or event listeners.

The exclusions are meaningful. A `CreateOrder` DTO needs values from a particular request. An `Order` entity represents particular persisted state. Treating either as an ordinary automatically constructed service would ask the container to invent those values. They are created or hydrated at their own boundaries.

The alias explicitly selects `SqlPriceCatalog` for `PriceCatalog`. That selection belongs to this application's configuration. A different application or a focused test can use the same interface with a different implementation.

An attribute such as `AsMessageHandler` is useful only because the framework integration registers the corresponding autoconfiguration and compilation behavior. A bare container and a full FrameworkBundle application have different sets of integrations available; recognizing the attribute syntax alone is insufficient.

## Reproduce an ambiguous interface

The lab creates a standalone `ContainerBuilder` with `FixedCatalog`, `DiscountCatalog`, and a small `Quote` service. Both catalogs implement `PriceCatalog`. The quote constructor asks for that interface.

Without an alias, container compilation raises a DependencyInjection `RuntimeException` naming the unresolved interface. This is a useful failure: the container cannot choose between two valid business implementations from the type declaration alone.

The configured version adds an alias before compiling:

```php
$container->setAlias(PriceCatalog::class, FixedCatalog::class);
$container->compile();
$quote = $container->get(Quote::class);
```

The fixed catalog returns 1250 cents per unit, so the quote for two units is 2500. Changing only the alias to `DiscountCatalog` produces 2000. The fixture implementations live under `tests/Fixtures`; their fixed prices and simplified account behavior are limited to this wiring exercise.

Autowiring resolves service dependencies while the container is compiled. The pinned [AutowirePass implementation](https://github.com/symfony/dependency-injection/blob/v7.4.22/Compiler/AutowirePass.php) is the relevant source when an error proposes aliases or reports that a constructor argument cannot be resolved.

## Inspect what compilation changes

Compilation analyzes and transforms service definitions. Private services that are only needed as dependencies can be inlined or removed as independent lookup targets. In the lab, the quote remains public while its catalog remains private.

The quote receives its catalog successfully, but directly requesting `FixedCatalog` from the compiled container raises `ServiceNotFoundException`. Private service visibility describes how other application code obtains the service; it is unrelated to whether an HTTP user is authorized to read a product.

The experiment also dumps the container:

```php
$php = (new PhpDumper($container))->dump([
    'class' => 'PricingLabContainer',
]);
```

It writes that trusted generated code under `.runtime`, loads it, constructs the generated container, and obtains the same 2500-cent quote. The resulting artifact makes the connection between configuration and executable PHP visible. [PhpDumper source](https://github.com/symfony/dependency-injection/blob/v7.4.22/Dumper/PhpDumper.php)

This does not mean every runtime value is baked into generated code. Environment processors and services can still resolve values during execution. It also does not imply a measured performance advantage over another framework; the experiment checks construction behavior and output.

## Run the experiment and inspect the application

Use the `symfony-complete` revision and the root README's environment setup. The script does not reset the database, but its evidence harness records the configured MySQL version:

```sh
php scripts/labs/03-container.php
php bin/console debug:container 'App\Domain\PriceCatalog' --env=test --no-debug
php bin/console lint:container --env=test --no-debug
```

`docs/evidence/03-container.json` records:

| Container choice | Result |
|---|---|
| Two implementations, no interface alias | Compilation exception |
| Fixed catalog alias | 2500-cent quote |
| Discount catalog alias | 2000-cent quote |
| Direct lookup of private fixed catalog | Service-not-found exception |
| Reloaded dumped container | 2500-cent quote |

The application container lint additionally checks that its configured services can be injected with compatible types. That check cannot establish that the database is reachable or that a selected catalog implements the intended pricing policy; those need different evidence.

## Compare the responsibility with Laravel

In the Laravel companion, the service container also resolves constructor dependencies and uses bindings to select implementations. Symfony's compiled service graph, aliases, and tags make particular wiring decisions especially visible through its debugging commands. The shared lesson is to keep the business dependency explicit and inspect how the application selects its implementation.

When autowiring fails, start with the reported parameter, interface alias, and service-discovery exclusions. When a configuration edit appears to have no effect, check the environment and its compiled cache. These checks locate construction problems before they become misleading database or controller investigations.
