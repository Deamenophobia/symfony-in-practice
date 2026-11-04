# Symfony request handling from PHP objects to kernel events

An API receives an HTTP request and eventually returns an HTTP response. Between those two points, a framework selects a controller, supplies its arguments, runs application code, and handles failures. Symfony makes these steps visible through reusable components and named kernel events. Following them is a useful way to deepen both PHP and Laravel knowledge.

This begins **Symfony in Practice**, eight articles built around a small account-based ordering API. The example has its own repository and database. An order contains one SKU and quantity, receives its price from the server, and can produce a background fulfillment task. This smaller scope lets us inspect the framework mechanisms individually.

## Choose a reproducible baseline

The experiments use PHP 8.4.27, Symfony FrameworkBundle 7.4.22, and MySQL 8.4.10. Symfony 7.4 is the maintained LTS line, with a longer support window than the current 8.1 stable line. This series chooses that LTS baseline; it is not presenting 7.4 as the newest major release. The lockfile records each component's exact version. [Symfony 7.4 release](https://symfony.com/releases/7.4), [Symfony 8.1 release](https://symfony.com/releases/8.1)

The repository starts from Symfony's API-sized skeleton, with backend packages added through Composer. The `symfony-start` tag records the installed skeleton. The completed application, article manuscripts, and evidence are at `symfony-complete`.

Use the root README to install the locked packages and prepare the isolated `symfony_practice_test` database. Then run the first experiment:

```sh
composer install
php scripts/labs/01-http-kernel.php
```

Although this first example changes no database rows, the evidence harness connects to MySQL to record the runtime. Later labs reset the disposable schema. The [Symfony setup guide](https://symfony.com/doc/7.4/setup.html) explains the skeleton, Composer, and the optional Symfony CLI.

## Separate the language, component, and framework

PHP executes classes, functions, and expressions. Composer installs packages and generates their autoloader. HttpFoundation represents HTTP data as PHP objects. HttpKernel coordinates a request-response lifecycle. FrameworkBundle supplies the integration and configuration used by this Symfony application.

These boundaries explain an important connection with Laravel. The Laravel companion's installed dependencies include Symfony HttpFoundation, HttpKernel, Console, Process, and other components. Laravel still has its own application bootstrapping, router, service container, and middleware pipeline. Sharing components does not mean every framework lifecycle is interchangeable. Laravel's [package manifest](https://github.com/laravel/framework/blob/1449bf241017087fbd38c118ecaabef3f6797fdc/composer.json) makes the component dependencies concrete.

The Laravel companion locks several of those components at 8.1.10. This Symfony application uses the 7.4 line. We compare concepts and observable behavior across the two applications without mixing their vendor directories or rewriting either lockfile to force matching component versions.

## Begin with two ordinary PHP objects

The first part of the script uses HttpFoundation directly:

```php
$request = Request::create('/quote?quantity=2', 'GET');
$response = new JsonResponse([
    'quantity' => $request->query->get('quantity'),
]);
```

No controller or application container is needed to construct these objects. `Request::create()` builds a synthetic request for the experiment. A web entry point can instead construct a request from PHP's incoming server and request data. [HttpFoundation documentation](https://symfony.com/doc/7.4/components/http_foundation.html)

The saved response contains `"quantity": "2"`, and the observed PHP type is `string`. An HTTP query parameter does not become an integer because it contains decimal digits. A later boundary must parse or validate it before passing it to an integer-based operation.

`JsonResponse` supplies JSON encoding and response metadata. Application code returns an object expressing the response; sending its bytes belongs to the runtime handling the request. That distinction makes the same response inspectable in an in-process test.

## Follow a request through the actual application

The `/health` endpoint is a small controller with a route attribute:

```php
#[Route('/health', name: 'health', methods: ['GET'], format: 'json')]
public function __invoke(Request $request): JsonResponse
```

PHP stores the attribute as metadata. Symfony discovers the route, matches the incoming method and path, resolves the controller, and supplies its `Request` argument. The controller returns a `JsonResponse` containing `ok: true`.

`src/EventSubscriber/RequestTrace.php` listens to selected kernel events and stores a trace on the request itself. It generates a request ID early and adds that ID to the final response. A successful request records:

```text
request,controller,arguments,handler,response
```

This is a selected trace, not a list of every listener Symfony executes. Routing and security listeners run within the event lifecycle too. Listener priority controls order within an event; the request-ID listener uses a high priority so it can assign the ID before routing fails.

The event model provides explicit extension points. `kernel.request` can establish request context or return an early response. `kernel.controller` observes the selected callable. Argument resolution and `kernel.controller_arguments` prepare its inputs. `kernel.response` allows response work. The component's [lifecycle guide](https://symfony.com/doc/7.4/components/http_kernel.html) describes those stages, while the pinned [HttpKernel source](https://github.com/symfony/http-kernel/blob/v7.4.22/HttpKernel.php) shows the calls that connect them.

## Make a missing route part of the experiment

The lab also requests `/missing`. Routing raises a not-found exception before any controller is selected. The application's JSON error listener converts it to a response, and the trace becomes:

```text
request,exception,response
```

The response still has a request ID because that value was assigned before routing. This parallels the Laravel middleware article's correlation problem, but the extension mechanism is different: these hooks are event listeners rather than nested application middleware calls.

`docs/evidence/01-http-kernel.json` records:

| Observation | Result |
|---|---|
| Query parameter `quantity` | String `2` |
| Healthy route | 200 with controller and handler stages |
| Missing route | 404 with an exception stage |
| Request ID on both responses | Present |

The requests use Symfony's actual kernel through `KernelBrowser` in the PHP process. They verify routing and event behavior without opening a TCP connection. The repository also includes a loopback development-server command for manual API requests.

## Use the trace to locate a failure

If a controller breakpoint is never reached, first determine whether routing, authentication, or argument mapping ended the request earlier. If an error response has no request ID, inspect listener registration, priority, and whether the failure occurred before this kernel handled the request. Adding a controller log cannot observe a controller that never ran.

The [PHP and Laravel overview](https://happykokoro.com/posts/04-04-php-and-laravel) describes the same broad path from HTTP input to application work. Symfony gives us another concrete implementation to inspect. The next article moves below framework conventions and tests what PHP's types, readonly objects, enums, and attributes actually enforce.
