---
name: error-handling
description: >
  Defines Jeyker's error-handling rules: one named exception per business rule,
  fail fast, try/catch only to recover or translate, expected errors shown to
  the user and never logged, unexpected errors logged and never swallowed. Use
  when throwing, catching, reporting, or displaying errors, or when reviewing
  code with try/catch, null returns, or error messages.
metadata:
  author: Jeyker-Dev
  version: "1.0"
---

# Error Handling

Input validation at the boundary is covered by `clean-code`. This skill covers
what happens when a rule is broken after the input is already valid.

## Two kinds of errors

| | Expected (business rule) | Unexpected (bug, outage) |
|---|---|---|
| Example | Cancelling an order that already shipped | Database down, null where the type forbids it |
| Thrown as | A named exception for that rule | Whatever the runtime throws |
| Shown to user | Its own clear message | A generic error page or message |
| Logged | No | Yes, with context |
| Caught | At the entry point, to show the message | Not caught; the framework handler reports it |

## Business rule exceptions

- One exception class per broken rule, named after the rule in domain
  language: `OrderAlreadyShipped`, `InsufficientStock`. Never a generic
  `Exception('error')` or a string code.
- All of them extend one project base class (for example
  `BusinessRuleException`), so entry points and the error handler can treat
  them as a group.
- The exception owns its user-facing message. Callers do not rewrite it.
- The model or the Action that enforces the rule throws it. Entry points
  (controllers, UI components, commands) only translate it for their channel.

```php
final class OrderAlreadyShipped extends BusinessRuleException
{
    public static function for(Order $order): self
    {
        return new self("Order {$order->number} has already shipped and cannot be cancelled.");
    }
}

// In the model method or Action that owns the rule
if ($this->status === OrderStatus::Shipped) {
    throw OrderAlreadyShipped::for($this);
}
```

## try/catch

A `catch` must do exactly one of these:

1. Recover: retry, or use a defined fallback.
2. Translate: turn a low-level exception into a business rule exception, or a
   business rule exception into the channel's error response.

Rejected:

- Empty catch blocks, or catch-and-log-and-continue.
- Catching `Exception` or `Throwable` anywhere except the global handler.
- Returning `null`, `false`, or an empty value to hide a failure. Fail fast
  with an exception instead.
- Wrapping code in try/catch "just in case" without a recovery or translation
  in mind.

## Logging

- Do not log business rule exceptions; they are normal outcomes.
- Unexpected exceptions reach the global handler, which logs them once. Do not
  log and rethrow at every layer.
- When logging manually, add context (ids, not whole objects) and never log
  secrets or personal data.

## Stack specifics

How each channel shows the error (HTTP status, UI field error, toast) lives in
the stack skill, for example `laravel-conventions` (`references/errors.md`).
