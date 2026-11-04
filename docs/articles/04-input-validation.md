# Symfony request mapping and validation at the API boundary

An order request includes a product, a quantity, and an optional reference. The server must reject malformed input, refuse client-supplied prices, and calculate the total using the authenticated account's catalog. A typed PHP constructor handles only part of that work.

Part 4 of **Symfony in Practice** follows JSON through the Serializer, Validator, controller argument resolver, and application service. We test actual status codes and persisted rows, including an unknown-field exception that needs an explicit application error mapping. The measured framework version is 7.4.22.

## Define the input the endpoint accepts

`src/Dto/CreateOrder.php` is a readonly input object:

```php
final readonly class CreateOrder
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public string $sku,
        #[Assert\Range(min: 1, max: 20)]
        public int $quantity,
        #[Assert\Length(max: 80)]
        public ?string $reference = null,
    ) {}
}
```

The constructor states the data shape. Constraints state additional input rules. The preceding PHP article showed that directly constructing this DTO with quantity zero still succeeds; something must ask the validator to evaluate its attributes.

The DTO contains neither an account ID nor a price. Account identity comes from authentication, while pricing comes from the catalog. An input object is also separate from a Doctrine entity: its fields describe what a caller may submit, rather than every property that happens to be stored internally.

## Connect mapping to a controller argument

The create action uses an attribute on its DTO parameter:

```php
#[MapRequestPayload(
    acceptFormat: 'json',
    serializationContext: ['allow_extra_attributes' => false],
)]
CreateOrder $input
```

The route declares JSON format, and the request must supply the expected content type. The argument resolver reads the payload, asks the Serializer to construct the DTO, and runs validation before the controller action executes. This is the point at which metadata from the previous article becomes behavior. [Mapping request payloads](https://symfony.com/doc/7.4/controller.html#mapping-request-payload)

These are distinguishable failures. An undecodable JSON body cannot become an object. A missing or incorrectly typed constructor argument cannot satisfy the DTO shape. A quantity of zero has the correct integer type but violates a constraint. The resolver translates several of these failures into HTTP exceptions. [RequestPayloadValueResolver source](https://github.com/symfony/http-kernel/blob/v7.4.22/Controller/ArgumentResolver/RequestPayloadValueResolver.php)

JSON type checking is also distinct from PHP's ordinary coercive call behavior. In this experiment, both `"two"` and the numeric string `"2"` are rejected for the integer quantity. A JSON number `2` is accepted. The Serializer is enforcing the boundary before application code receives the DTO.

## Give unknown fields an explicit response

Setting `allow_extra_attributes` to false rejects an incoming `unit_price_cents` field. This makes the accepted input contract narrow and prevents a caller from mistaking an ignored price for an accepted one.

In the pinned implementation, that rejection raises `ExtraAttributesException`. The payload resolver does not translate that particular exception into a client-error status. Our application's error listener therefore includes a specific mapping:

```php
$status = match (true) {
    $error instanceof HttpExceptionInterface => $error->getStatusCode(),
    $error instanceof ExtraAttributesException => 400,
    $error instanceof DomainException => 422,
    default => 500,
};
```

The listener is `src/EventSubscriber/ApiErrors.php`. It returns a small JSON response, preserves headers on HTTP exceptions, and includes field violations when the underlying error supplies them. Unexpected exceptions retain `500`; the listener does not turn every application bug into invalid input.

This mapping is application behavior. The observed `400` for an extra price field should not be attributed to `MapRequestPayload` alone. The [ObjectNormalizer implementation](https://github.com/symfony/serializer/blob/v7.4.22/Normalizer/AbstractObjectNormalizer.php) contains the extra-attribute rejection that reaches the listener.

## Cross the authorization and business boundaries

Once mapping succeeds, the controller verifies that the credential permits `orders:write`. It passes the authenticated user's account ID and validated DTO to `PlaceOrder`.

The service asks `PriceCatalog` for the account's SKU price, constructs the domain order, persists it, and completes its transaction. The domain object checks quantity and currency rules again so non-HTTP callers cannot bypass them by skipping the request mapper.

For the fixture, account one's `COFFEE` price is 1250 cents. Quantity two creates an order totaling 2500 cents. Account two has its own catalog row and price. The SQL lookup includes both account and SKU.

Input validation does not establish authorization, and an authorization decision does not establish that a SKU exists in the account's catalog. Keeping those checks at their respective boundaries makes the error path easier to explain. The [PHP and Laravel overview](https://happykokoro.com/posts/04-04-php-and-laravel) describes the same progression through validated input, authorized application work, persistence, and explicit representation.

## Run the input matrix

Use the `symfony-complete` checkout, PHP 8.4.27, and the root README's MySQL setup. This lab resets only `symfony_practice_test`:

```sh
php scripts/labs/04-validation.php
```

The script submits each request through the real Symfony kernel and records `docs/evidence/04-validation.json`:

| Submitted input | Observed status |
|---|---|
| Valid SKU and numeric quantity two | 201 |
| Quantity zero | 422 |
| Quantity string `two` | 422 |
| Quantity numeric string `2` | 422 |
| Missing quantity | 422 |
| Malformed JSON | 400 |
| Unsupported text media type | 415 |
| Additional price field | 400 through the application mapping |
| SKU unavailable in the account's catalog | 422 |

Only the valid request creates an order. The persisted row count is one, and its total is 2500 cents. That database assertion matters: a response-status test alone could miss an operation that wrote data before rejecting the request.

The quantity-zero response identifies the `quantity` field and its range violation. The text label for HTTP 422 in this installed version is `Unprocessable Content`; clients should use the documented status and structured fields rather than parsing an English status phrase.

## Compare the endpoint with a Laravel Form Request

The Laravel series uses a Form Request to organize authorization and validation. This Symfony endpoint uses a mapped DTO and constraint metadata, with credential and resource authorization handled separately. Both need server-owned pricing and account-scoped data access.

If a request never reaches the controller, inspect content type, deserialization, and constraint failures before changing application logic. If an unknown field produces `500`, inspect the exception type and the application's mapping. If a rejected request leaves an order behind, inspect the transaction and the order of boundary checks. Each observation points to a different responsibility.
