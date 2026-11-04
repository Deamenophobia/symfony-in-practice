# Symfony authentication and voters for account-owned orders

A caller presents a valid token and requests an order ID. Authentication can establish who the caller is, but it cannot by itself establish that the requested order belongs to the caller's account. The same distinction applies when a read-only integration attempts to create an order.

Part 6 of **Symfony in Practice** follows a bearer token through a stateless firewall, an access-token handler, and a resource voter. It verifies missing, expired, revoked, and valid credentials against the same account-scoped API. The measured Symfony Security version is 7.4.22.

## Establish the request's security boundary

`config/packages/security.yaml` defines a firewall for API paths:

```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            access_token:
                token_handler: App\Security\AccessTokenHandler
    access_control:
        - { path: ^/api, roles: ROLE_API }
```

The firewall selects authentication behavior for matching requests. Stateless operation means this API authenticates the credential on each request instead of using a browser login session. The `/health` endpoint sits outside this pattern and is intentionally public.

The access-control rule requires the role assigned to an authenticated API principal. It establishes an entry requirement for the route group. It does not inspect an individual order's account. Symfony's [access-token documentation](https://symfony.com/doc/7.4/security/access_token.html) describes the token-handler integration and its responsibility to validate credentials.

## Resolve an opaque token to a principal

`AccessTokenHandler` implements `AccessTokenHandlerInterface`. It receives the token extracted from the bearer header, hashes it, and queries `api_tokens`:

```sql
SELECT user_id, account_id, abilities
FROM api_tokens
WHERE token_hash = ?
  AND revoked_at IS NULL
  AND expires_at > UTC_TIMESTAMP()
```

The parameter is a SHA-256 hash of the supplied token. A missing matching row produces `BadCredentialsException`. A matching row becomes an `ApiUser`, returned through a `UserBadge` loader callback.

For this small API, that immutable request principal carries user identity, account identity, and the presented credential's abilities. Its `ROLE_API` role is an authentication marker. The example does not implement a separate organizational role-management model.

The fixture uses public synthetic strings such as `writer-one` and `reader-one` so requests can be repeated locally. They demonstrate the lookup path; their predictability is unsuitable for real credentials. A deployed issuance flow would need securely generated credentials, controlled delegation, and a lifecycle appropriate to its clients. This repository has no public token-issuance endpoint.

Hashing the stored credential does not make a leaked bearer token harmless. The handler also marks its parameter with PHP's `SensitiveParameter` attribute, which protects that argument in supported stack-trace representations. It does not automatically redact arbitrary log messages or other copies of the token. [PHP SensitiveParameter](https://www.php.net/manual/en/class.sensitiveparameter.php)

## Ask a voter about the concrete order

The detail controller loads the order, then asks:

```php
$this->denyAccessUnlessGranted('ORDER_VIEW', $order);
```

`OrderVoter::supports()` recognizes this operation and subject type. Its decision combines the credential's read ability with the account relationship:

```php
$user = $token->getUser();

return $user instanceof ApiUser
    && $user->can('orders:read')
    && $user->accountId === $subject->accountId();
```

An unsupported operation or subject lets the voter abstain. A supported operation returns the grant or denial that participates in Symfony's access decision. The application's other voters and decision strategy therefore matter too; adding a broad voter that grants the same operation can change the effective policy. This example has one custom voter responsible for `ORDER_VIEW`. [Symfony voters](https://symfony.com/doc/7.4/security/voters.html)

The account comparison uses the authenticated principal and the loaded entity. The caller cannot select a different account by adding a JSON field or query parameter. The framework runs the voter, while this account-ownership rule is application code.

The create controller checks `orders:write` before calling `PlaceOrder`. That service obtains the account's catalog price and creates the order for the authenticated account. Request validity and resource authorization remain separate decisions.

## Expire and revoke the credential

The fixture includes an expired token and a revoked token. Both fail authentication with `401`. It also includes a valid writer token. After a successful request, the lab updates that token's row to set `revoked_at`, then makes a new request using the same bearer value. The new request returns `401`.

This experiment creates a fresh kernel for each request so resolved authentication state from one synthetic request cannot hide a change in the database. It measures later authentication, not cancellation of an operation that has already passed its checks.

The package's [AccessTokenAuthenticator](https://github.com/symfony/security-http/blob/v7.4.22/Authenticator/AccessTokenAuthenticator.php) coordinates extraction and token handling. The application's SQL determines expiry and revocation semantics in this example. An OAuth or OpenID Connect integration would have a different credential-validation design even if it used the same framework entry point.

## Run the authorization matrix

Prepare the completed `symfony-complete` revision and the root README's MySQL environment. The script resets only `symfony_practice_test`:

```sh
php scripts/labs/06-security.php
```

The lab creates an account-one order and records these results in `docs/evidence/06-security.json`:

| Request | Status |
|---|---|
| No bearer token | 401 |
| Unknown token | 401 |
| Valid writer in the order's account | 200 |
| Valid writer in another account | 403 |
| Read-only token reads its account's order | 200 |
| Read-only token creates an order | 403 |
| Expired token | 401 |
| Previously revoked token | 401 |
| Writer token after revocation | 401 |

An existing forbidden order returns `403` in this API. Some applications choose `404` to conceal resource existence; that is an explicit response-contract choice and should be tested accordingly.

## Compare the checks with the Laravel companion

Laravel's Sanctum lab also separates credential validity, token abilities, and account ownership. Its policy is the natural comparison to this resource voter. Symfony's firewall and authenticator configuration supplies a different integration structure around the same underlying questions.

If a known credential returns `401`, inspect token extraction, expiry, revocation, and the principal loader. If it returns `403`, inspect the access rule, ability, voter, and resource relationship. If account two gets account one's order, treat the missing account boundary as a correctness failure even though authentication worked perfectly.

Learning both frameworks is valuable here because it makes the responsibility independent of a particular class name. The application still has to establish identity and then authorize the requested operation on the actual resource.
