# Verification record

Recorded on 4 November 2026 using Windows, PHP 8.4.27, MySQL 8.4.10, Symfony FrameworkBundle 7.4.22, Doctrine ORM 3.7.6, DBAL 4.5.0, and PHPUnit 12.5.43. All database work used the isolated `symfony_practice_test` schema.

## Backend results

| Check | Observed result |
|---|---|
| PHPUnit | 18 tests, 30 assertions; passed |
| YAML and service-container lint | Passed |
| Doctrine mapping validation | Passed with `--skip-sync`; explicit migrations manage non-ORM tables |
| PHP source syntax | 41 files passed |
| Production container warmup | Passed without test-only services |
| Eight article labs | All assertions passed |
| Composer manifest / lock validation | Passed; expected advisory about the deliberate exact FrameworkBundle version pin |

The complete `scripts/verify.ps1` run saved its output to ignored `.runtime/verification.log` and fresh measurements to `.runtime/verification`. After refining the test-only delivery probe to let an unknown order reach the real handler's foreign-key check, the Messenger and console labs were rerun successfully. Their checked-in evidence reflects that final probe.

## Evidence by boundary

- HTTP: the real kernel returned 200 and 404 with the expected selected lifecycle traces and request IDs. These lab requests ran in process through `KernelBrowser`.
- PHP: strict and weak callers differed, readonly assignment failed, enum-backed order transitions were enforced, and validation attributes had an effect only when the Validator consumed them.
- Container: ambiguous interface wiring failed compilation; explicit aliases selected the requested implementation; the dumped PHP container produced the same quote.
- Input: valid JSON created one server-priced order. Invalid shape, type, quantity, media type, extra fields, and unknown SKU produced the asserted 400, 415, or 422 responses.
- Doctrine: recorded SQL and separate-connection observations established persist versus flush, identity-map behavior, change tracking, rollback, and a closed entity manager after the failed wrapper transaction.
- Security: actual database credentials exercised missing, invalid, expired, revoked, read-only, and cross-account access.
- Messenger: a real Doctrine transport and separate PHP workers processed three equivalent messages plus one retry into one task. An unknown order exhausted retries and entered the failed transport.
- Console: the real command's invalid-input status, dry-run output, queue state, successful dispatch, and final reconciliation result all matched the assertions.

The individual JSON files under [evidence](evidence/) include runtime versions and measured results. Deliberate bad requests and injected worker failures can produce error log lines while their corresponding assertion succeeds.

## What these checks establish

The tests verify this companion's local API, database, and worker contracts. The duplicate-delivery experiment injects a failure before the first effect and then checks repeated successful delivery; it does not simulate a worker dying after an external side effect. Production container compilation is a configuration check, not a deployment. Public synthetic credentials, trace headers, and the disposable reset are teaching facilities.

All tests and labs run sequentially against one schema.

## HTTP server smoke check

Started the loopback API with `scripts/local.ps1 -Action Start` and sent actual TCP requests to port 8089 after resetting the fixture. Health returned 200 with its lifecycle trace. Order creation returned 201, total 2500 cents, and a request ID. Reading the order returned 200 for its owner, 403 for the other account, and 401 without credentials. The local record is in ignored `.runtime/http-smoke.json`.

## Article and preview checks

All eight local preview routes returned 200. Browser checks confirmed one correct title, all 49 section headings, all 44 code fences reproduced exactly, nine tables, the next-article links, and entries in the reading library. Pages carried `noindex` and had no page-level horizontal overflow at desktop 1440 × 1000 or mobile 390 × 844. Desktop, table, and mobile screenshots were also inspected.

The preview copies are in the blog workspace's `content/publications/preview` directory, with manifest orders 65–72 under **Symfony in Practice**. The local reading entry point is `http://127.0.0.1:3000/preview/articles/symfony-in-practice-http-kernel`. No publishing action was performed.

Of 39 unique external references, 37 returned a successful response, including every official documentation and pinned source-code link. Two existing background-blog URLs timed out at the network connection stage on both the initial request and retry: `/posts/03-02-2-a-query-result-is-a-contract` and `/posts/02-2-06-load-balancing-queues-and-workers`. Both routes and their manuscripts exist in the blog workspace; their external availability was not verified. Browser and link-check records are under `C:\dev\scratch\symfony-article-preview`.
