# Doctrine entities and the boundary between objects and SQL

An application calls `persist()` on a new order, but a second database connection still sees no row. Later, changing an object's reference also leaves the database unchanged until `flush()` runs. These observations are central to understanding Doctrine's persistence model.

Part 5 of **Symfony in Practice** follows an order through persistence, identity-map lookup, change tracking, and transaction rollback. It uses Doctrine ORM 3.7.6, DBAL 4.5.0, and MySQL 8.4.10. The existing Laravel application provides a useful comparison, while the SQL and database observations remain specific to this experiment.

## Keep the entity separate from persistence operations

`src/Entity/Order.php` is an ordinary PHP class with Doctrine mapping attributes. It has private fields and methods for operations such as changing a reference or confirming an open order. It does not inherit an ORM base model or call `save()` on itself.

The identity field is mapped as a generated integer:

```php
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column]
private ?int $id = null;
```

Doctrine interprets these attributes as mapping metadata. The MySQL schema supplies an auto-increment primary key. The entity manager coordinates the object's persistence, while the database supplies the stored identity and enforces its constraints.

The entity's `view()` method returns an explicit response array. It omits internal account identity and returns the backed enum's value for status. This keeps the API representation deliberate instead of serializing every persistence field automatically. For the broader importance of stable output, see [a query result is a contract](https://happykokoro.com/posts/03-02-2-a-query-result-is-a-contract).

## Observe persist and flush separately

The experiment creates an order and asks the entity manager to persist it:

```php
$order = new Order(1, 'COFFEE', 2, new Money(1250), 'BEFORE');
$entities->persist($order);
```

At this point, the order's ID is still `null`, and an independent DBAL connection counts zero rows. The unit of work knows about the new object, but the insertion has not yet been executed.

Calling `flush()` produces an actual statement captured by a DBAL logging middleware:

```sql
INSERT INTO orders
    (account_id, sku, quantity, unit_price_cents, total_cents, reference, status)
VALUES (?, ?, ?, ?, ?, ?, ?)
```

The bindings include account 1, SKU `COFFEE`, quantity 2, unit price 1250, total 2500, reference `BEFORE`, and status `open`. After this flush, the separate observer connection sees one row. The saved evidence includes the full SQL and bindings.

These observations apply to the mapping and MySQL identity strategy used here. They should not be generalized to every possible Doctrine identifier strategy. Doctrine's [object lifecycle documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/3.7/reference/working-with-objects.html) explains the relationship between managed objects and the unit of work.

## Find the same identity twice

With the entity still managed, the lab calls:

```php
$same = $entities->find(Order::class, $order->id());
```

The returned value is the same PHP object: `$same === $order` is true. The SQL recorder captures zero queries for that lookup. The entity manager's identity map already has an object for that class and primary key.

Next, the lab clears the entity manager and loads the ID again. This time it records a `SELECT`, and the returned entity is a different PHP object. Clearing detaches the earlier managed objects; it does not delete their database rows.

An identity map also means a managed entity is not automatically refreshed whenever another process updates the same row. A long-lived process must deliberately manage object lifetime and freshness. The map is scoped to the entity manager, rather than being a shared cache that keeps every worker synchronized. The pinned [UnitOfWork source](https://github.com/doctrine/orm/blob/4487f850401c0e1f27891cce5897fa90b3055c2a/src/UnitOfWork.php) contains the identity and change-tracking mechanisms.

## Detect a change without saving the entity directly

The freshly loaded entity receives a new reference:

```php
$fresh->changeReference('AFTER');
```

The independent database observer still reads `BEFORE`. After another `flush()`, the recorder captures:

```sql
UPDATE orders SET reference = ? WHERE id = ?
```

The observer now reads `AFTER`. The unit of work detects a changed managed field and schedules the update. This is why both the lifetime of the entity manager and the placement of `flush()` matter to application behavior.

In the Laravel companion, Eloquent's active-record model exposes persistence methods such as `save()` on the model. Doctrine's entity manager coordinates persistence around the objects. Both eventually execute SQL, and both remain subject to database constraints and transaction visibility. This experiment establishes lifecycle differences, not a performance ranking between the ORMs.

## Flush inside a transaction that later fails

The lab then starts `EntityManager::wrapInTransaction()`, persists another order, explicitly flushes it, and raises a synthetic exception before the transaction completes. The observer still counts only the original row after rollback.

Flushing inside an open transaction does not make that transaction's work durable. The outer transaction can still roll it back. The existing [transactions, isolation, locks, and MVCC article](https://happykokoro.com/posts/03-03-transactions-isolation-locks-and-mvcc) provides background on that visibility boundary.

In this ORM version, `wrapInTransaction()` also closes the entity manager when its operation fails. The lab checks `isOpen()` and records false. A closed manager should not be reused for another application operation; obtain a fresh manager through the framework's lifecycle or registry as appropriate. Merely catching the exception does not restore it. [EntityManager implementation](https://github.com/doctrine/orm/blob/4487f850401c0e1f27891cce5897fa90b3055c2a/src/EntityManager.php)

This is the behavior of that ORM transaction wrapper. A manual DBAL rollback has its own object-state considerations and should not be assumed to perform exactly the same lifecycle cleanup. [Doctrine transaction guidance](https://www.doctrine-project.org/projects/doctrine-orm/en/3.7/reference/transactions-and-concurrency.html)

## Reproduce the evidence

Use `symfony-complete` and the root README's PHP/MySQL setup. The lab replaces the disposable `symfony_practice_test` fixture:

```sh
php scripts/labs/05-doctrine.php
```

`docs/evidence/05-doctrine.json` contains:

| Observation | Result |
|---|---|
| After `persist()`, before flush | Zero rows; ID is null |
| After insertion flush | One row; INSERT recorded |
| Find the managed ID | Same object; zero queries |
| Clear and find again | Different object; SELECT recorded |
| Change reference before flush | Observer still reads `BEFORE` |
| Flush changed reference | UPDATE recorded; observer reads `AFTER` |
| Exception after an inner flush | New row rolled back |
| Manager after failed transaction wrapper | Closed |

If PHP state and SQL state disagree, first identify whether the object is managed, whether a flush occurred, and whether the transaction committed. Those questions explain far more than adding another `persist()` call at random.
