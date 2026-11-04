# Symfony Messenger retries and repeated delivery

An order has committed, and the application dispatches work to create a fulfillment task. Dispatch returns successfully, but the task does not exist yet. The message is waiting in a transport for a worker. Later, that worker may encounter a failure or receive equivalent work again.

Part 7 of **Symfony in Practice** follows those boundaries with a real MySQL-backed Messenger transport and separate PHP worker processes. We inject one failure, send three messages for the same order, and inspect the final effect. The measured packages are Symfony Messenger and Doctrine Messenger 7.4.22.

## Give the message a small contract

The message contains the identity of the order requiring work:

```php
final readonly class FulfillOrder
{
    public function __construct(public int $orderId) {}
}
```

A handler is a service selected for that message type. `FulfillOrderHandler` uses `AsMessageHandler`, which Symfony's container integration interprets during handler registration. The bus then coordinates middleware and the selected handler or transport.

Passing an ID avoids serializing an entire managed Doctrine entity with stale state and entity-manager assumptions. It also keeps bearer credentials out of the message. The handler's input remains untrusted as an application boundary: a supplied ID must correspond to valid persisted state.

The effect in this companion is a local `fulfillment_tasks` row. It records that work has been arranged for an order; it does not book a shipment with an external carrier. The [queue and worker background article](https://happykokoro.com/posts/02-2-06-load-balancing-queues-and-workers) explains the distinction between delivery and the operation being performed.

## Route the message to a transport

`config/packages/messenger.yaml` routes this message class to `async`:

```yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options: { queue_name: async, redeliver_timeout: 30 }
                retry_strategy: { max_retries: 2, delay: 10, multiplier: 1, jitter: 0 }
            failed:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options: { queue_name: failed, redeliver_timeout: 30 }
        routing:
            'App\Message\FulfillOrder': async
```

The DSN selects the Doctrine transport's named `queue` connection. The application and queue connections point to the same disposable schema but use separate DBAL connections. Their independent transactions are a deliberate part of the exercise.

The lab creates and commits an order before dispatching three messages for it. Immediately after dispatch, the database contains three queued messages and zero fulfillment tasks. Sending has succeeded; processing has not yet happened. Symfony's [Messenger documentation](https://symfony.com/doc/7.4/messenger.html) describes the bus, routing, transports, and workers as separate pieces.

The initial schema migration creates the domain tables, and the fixture runs `messenger:setup-transports` to create the transport table. Automatic transport setup is disabled in the DSN so a missing transport table is visible during setup.

## Let a worker consume the committed messages

The experiment launches a separate PHP process using the real console command:

```sh
php bin/console messenger:consume async --limit=4 --time-limit=5 --sleep=0.01 --no-interaction -v
```

The process receives the same test environment and database URL as the producer. `--limit` bounds processed delivery attempts, while `--time-limit` bounds the loop between work. The harness also gives the child process an external 15-second deadline.

The CLI `--sleep` value is in seconds. Here `0.01` requests a short idle poll interval. Internally the command converts that value for the worker; confusing the command's units with the worker API's units can make a retry appear stuck. The pinned [consume command](https://github.com/symfony/messenger/blob/v7.4.22/Command/ConsumeMessagesCommand.php) makes that conversion explicit.

These limits do not prove interruption of an arbitrarily hung database call. A deployed worker needs appropriate infrastructure timeouts, shutdown behavior, and a process manager. Native Windows PHP in this workspace also lacks Unix process-control extensions.

## Retry a controlled failure and preserve one effect

The test container includes `DeliveryProbe`, a middleware under `tests/Fixtures`. It counts received attempts and, when the worker's `LAB_FAIL_FIRST` flag is enabled, throws before the first handler execution. The production handler has no fault-injection branch.

Messenger catches the failure and schedules a retry according to the transport policy. `max_retries: 2` permits two retries after the initial attempt. The retry listener attaches delay and redelivery metadata before sending the message again. [Retry listener source](https://github.com/symfony/messenger/blob/v7.4.22/EventListener/SendFailedMessageForRetryListener.php)

The handler protects its effect with a unique `order_id` and a MySQL upsert:

```sql
INSERT INTO fulfillment_tasks (order_id)
VALUES (?)
ON DUPLICATE KEY UPDATE order_id = order_id
```

For this single-row effect, the database's unique key makes equivalent successful deliveries converge on one task. A foreign key also requires a real order. The three original messages plus one retry produce four observed attempts and one task.

This protects the local database effect. An external shipment or payment would need its own idempotency and reconciliation protocol. A database upsert cannot make an unrelated remote side effect atomic. The existing [distributed failure article](https://happykokoro.com/posts/01-06-client-server-communication-and-distributed-failure) explains why those boundaries remain separate.

## Retain work that exhausts retries

A second scenario dispatches a message for order `999999`, which does not exist. The fixture lets that message reach the real handler, whose foreign-key constraint rejects its insert. After the configured retries, Messenger transfers the message to the `failed` transport.

The final state is one failed message, no remaining asynchronous messages, and still one valid fulfillment task. Failure retention gives an operator something concrete to inspect. Repeatedly retrying the same missing-order input will not repair its meaning.

The worker logs are stored under ignored `.runtime/messenger-workers.log`. The [failure-transport listener](https://github.com/symfony/messenger/blob/v7.4.22/EventListener/SendFailedMessageToFailureTransportListener.php) explains how the final failed envelope reaches that transport.

## Run the complete delivery experiment

Use `symfony-complete`, PHP 8.4.27, and the root README's MySQL setup. No Redis or external broker is needed. This script resets `symfony_practice_test` and runs its workers sequentially:

```sh
php scripts/labs/07-messenger.php
```

`docs/evidence/07-messenger.json` records:

| Stage | Queue state | Business effect |
|---|---|---|
| After three dispatches | Three async messages | Zero tasks |
| After one injected failure and repeated delivery | Four attempts; async and failed queues empty | One task |
| After the unknown-order scenario | Async empty; one failed message | Still one task |

The injected failure occurs before the first effect. The repeated successful deliveries then exercise the handler's unique-key protection. The script checks actual table state, rather than treating a zero worker exit code as proof that every message succeeded.

The ordering endpoint itself commits an order without automatically enqueueing this task. The next article's reconciliation command discovers committed orders that still lack the effect and dispatches them. That supplies a concrete recovery path for this small workflow, including a producer that stops between commit and dispatch. More elaborate workflows may need an explicit outbox or another durable representation of pending operations.
