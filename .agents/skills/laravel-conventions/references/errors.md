# Errors in Laravel

Applies the `error-handling` skill to Laravel.

## Base class

```php
// app/Exceptions/BusinessRuleException.php
use Symfony\Component\HttpFoundation\Response;

abstract class BusinessRuleException extends Exception
{
    public function render(Request $request): Response
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], 422)
            : back()->withErrors(['domain' => $this->getMessage()]);
    }
}
```

Do not name it `DomainException`; PHP already ships an SPL class with that name.

## Do not report business rule exceptions

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->dontReport(BusinessRuleException::class);
})
```

## Livewire: show the message on the component

The component method is the entry point, so it is the one place that catches.

```php
public function cancel(CancelOrder $cancelOrder): void
{
    try {
        $cancelOrder($this->order);
    } catch (BusinessRuleException $exception) {
        $this->addError('order', $exception->getMessage());

        return;
    }

    $this->dispatch('order-cancelled');
}
```

Use `addError` when the message belongs to a field or the form; use an error
toast when it belongs to the whole page.

## HTTP: render as 422

The base class renders itself (see above): JSON requests get a 422 with the
message, form posts go back with the message as an error. Controllers do not
catch business rule exceptions.
