# Modern PHP types and metadata in a Symfony application

A constructor accepts an integer quantity. A validation attribute says the quantity must be between one and twenty. Does calling that constructor with zero fail immediately? In this application it does not: PHP accepts zero as an integer, and the attribute waits for a validator to interpret it.

That distinction is the subject of part 2 of **Symfony in Practice**. We use a money value object, an order status enum, and a request DTO to separate language rules from framework behavior. These features are available in Laravel and other PHP programs too. Symfony provides a useful setting in which to see their responsibilities separately.

## Represent a value with a typed object

`src/Domain/Money.php` starts with a readonly class and promoted constructor properties:

```php
final readonly class Money
{
    public function __construct(
        public int $cents,
        public Currency $currency = Currency::SGD,
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }
}
```

Constructor property promotion combines a constructor parameter and property declaration. The integer type expresses a representation; the explicit condition expresses the nonnegative-money rule. PHP's type system does not infer that rule from the name `Money`. [Constructor promotion](https://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.constructor.promotion)

The example stores minor currency units as integers. Two units priced at 1250 cents produce 2500 cents without binary floating-point arithmetic. This representation still needs a currency and a defined rounding policy if later operations introduce taxes, percentages, or exchange rates.

`times()` returns a new `Money`, checks the quantity range, and rejects multiplication that would exceed PHP's integer range. The original value remains 1250. Its bound of twenty is the ordering application's rule, not a universal property of money.

## Observe readonly behavior

The experiment attempts to assign a different amount after construction:

```php
$price = new Money(1250);
$price->cents = 1;
```

PHP raises `Error`. The language enforces the readonly property rule even though no Symfony validator or container participates. A readonly class also restricts how its properties may be declared and initialized. The [PHP properties documentation](https://www.php.net/manual/en/language.oop5.properties.php) describes those rules.

Readonly properties do not make an arbitrary object graph deeply immutable. If a readonly property refers to another mutable object, that object's internal state can still change through its methods. Our money value contains an integer and an enum case, which keeps its value semantics straightforward.

Persistence entities have a different job in this example. `Order` changes its reference and status over time, and Doctrine tracks those changes. Its mutable fields are private and updated through methods that enforce the relevant rule. Applying `readonly` to every application class would conflict with that lifecycle.

## Check strictness at the call site

The main lab file declares `strict_types=1` and calls:

```php
new Money('1250');
```

That call raises `TypeError`. The separate `scripts/labs/weak-caller.php` file deliberately uses PHP's default scalar coercion mode and makes the same constructor call. It produces a `Money` containing integer 1250.

The important boundary is the caller's scalar typing mode. Putting `declare(strict_types=1)` in the class's file does not force every external PHP caller to behave as a strict caller. The experiment executes both paths so the distinction is visible. [PHP type declarations and strict typing](https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict)

Strict typing also does not transform HTTP data into validated domain values. The preceding article observed a query parameter as a string. Request mapping and validation must make an explicit decision about that representation before the application relies on it.

## Use an enum for the set and a method for the transition

Order status is represented by a string-backed enum:

```php
enum OrderStatus: string
{
    case Open = 'open';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
```

The enum supplies a finite set of cases with stable external values. `OrderStatus::tryFrom('missing')` returns `null`, which the lab records. An application must decide how to handle that absent result at its input boundary. [PHP backed enumerations](https://www.php.net/manual/en/language.enumerations.backed.php)

The enum alone does not express a legal state transition. `Order::confirm()` checks that the current status is `Open` before changing it to `Confirmed`. The first call succeeds; the second raises `DomainException`. The enum represents the available states, while the method enforces the operation's precondition.

This is a useful pattern across both framework companions: a framework can load an object and call its method, while the object keeps the transition rule close to the state it controls.

## Read an attribute without confusing it with execution

The request DTO declares this promoted property:

```php
#[Assert\Range(min: 1, max: 20)]
public int $quantity
```

The constructor can still create `new CreateOrder('COFFEE', 0)`. The resulting property is zero. Reflection finds one `Range` attribute on that property, and invoking Symfony's validator produces one violation.

PHP attributes attach structured metadata to declarations. Reflection exposes that metadata; a consumer decides what behavior to implement from it. Symfony's validator consumes constraints, its routing system consumes route metadata, and Doctrine consumes entity mappings. The language does not itself perform those three jobs. [PHP attributes](https://www.php.net/manual/en/language.attributes.overview.php)

The DTO carries input through a boundary. The domain object still checks its own invariants because other callers, including a console command or a future import, may construct it without this request validator.

## Reproduce the language boundaries

Use PHP 8.4.27 and the completed `symfony-complete` checkout. The root README supplies dependency and MySQL setup for the evidence harness:

```sh
php scripts/labs/02-modern-php.php
```

The script writes `docs/evidence/02-modern-php.json`:

| Attempt | Observed result |
|---|---|
| Multiply 1250 cents by two | New value 2500; original 1250 |
| Pass string amount from strict file | `TypeError` |
| Pass string amount from coercive file | Integer 1250 |
| Reassign readonly amount | `Error` |
| Construct negative money | `InvalidArgumentException` |
| Construct quantity-zero DTO | Object is created |
| Validate that DTO | One violation |
| Confirm the same order twice | Second call raises `DomainException` |
| Resolve an unknown backed-enum value | `null` |

If an attribute appears to have no effect, identify the component expected to read it and whether that component ran. If a numeric string is unexpectedly accepted, inspect the call site and mapping code. These are different failures, and the distinction helps explain why a PHP program can have detailed type declarations while still needing careful input handling.

The next article applies the same precision to dependency injection: an interface states the dependency's contract, and the container configuration chooses the object that will satisfy it.
