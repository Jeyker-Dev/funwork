---
name: clean-code
description: >
  Applies Jeyker's opinionated clean-code rules: no magic values, no flag
  parameters, guard clauses, trust in type contracts (YAGNI), Tell-Don't-Ask,
  command-query separation, when to extract a private method versus a new
  class, and when to extract shared steps instead of copying a sibling class.
  Use when writing, refactoring, or reviewing code in any language, when
  creating a class modeled on an existing one, or when deciding whether a
  class or method is too large.
metadata:
  author: Jeyker-Dev
  version: "1.1"
---

# Clean Code

These rules are decisions, not suggestions. Tags in brackets name the principle
each rule applies; do not explain the principle, apply the rule. Examples use
PHP; translate the idea to the language at hand.

## Naming and values

- No generic names: `$data`, `$r`, `$temp`, `$x`, `$item`, `$result` are
  rejected. Name by meaning: `$pendingOrders`, `$invoiceTotal`.
- Type every parameter and return value.
- No magic numbers or strings in conditions. A fixed state is a backed enum;
  a fixed value is a named constant.

```php
// Rejected
if ($user->role_id == 2 && $order->status == 'p') { ... }

// Required
if ($user->role === Role::Admin && $order->status === OrderStatus::Pending) { ... }
const ORDER_EXPIRATION_HOURS = 48;
```

## Methods

- No boolean parameter that switches behavior [SRP]. Split into two methods
  with explicit names. A boolean that is the data itself is fine
  (`setActive(bool $active)`). More than two modes: an enum, never several
  booleans.

```php
// Rejected
registerUser($input, true);

// Required
registerUser($input);
registerVipUser($input);
```

- Guard clauses first, happy path last. Never write `else` after a branch that
  returns or throws. A value chosen by cases is a `match`, not an if/else chain
  [OCP].
- A method either changes state or returns data, never both [CQS].
  `publish(): void` is a command; `isPublished(): bool` is a query.

## Trust contracts (YAGNI)

- Validate only at the system boundary: HTTP requests, UI input, external APIs,
  webhooks, queued job payloads, CLI arguments, imported files, and nullable
  database columns.
- At the boundary, parse into a typed object (DTO, value object, enum) instead
  of passing arrays along [parse, don't validate].
- Inside the domain, trust the types. If a function takes `int $wheels` or a
  validated DTO, do not re-check null, type, or hypothetical cases the current
  business rules do not include. No "five-wheel car" code.
- Fail fast with a specific exception; never return a silent `null` for an
  error [fail fast]. Exceptions, `try/catch`, and logging follow the
  `error-handling` skill.

## Objects talk to neighbours

- Tell, don't ask: do not read an object's properties to decide and then write
  them back. Ask the object to do it (`$article->publish()`).
- Talk only to direct collaborators [Law of Demeter]. `$order->customer->address->city`
  becomes a method on the nearest object (`$order->shippingCity()`).
- Inject dependencies through the constructor; never `new` a service [DIP].
- Interfaces are small and role-based [ISP].
- Prefer composition; use inheritance or traits only for behavior that is truly
  shared by every user of it [composition over inheritance].

## Private method or new class?

| Criterion | Extract a private method | Extract a class (Action, Service, Query) |
|---|---|---|
| Purpose | Name a hard-to-read block (a 3-condition `if`, a loop body) | Isolate a whole business rule, a multi-step process, or data access |
| Reuse | Only this class needs it | A controller, command, job, or UI component also needs it |
| Dependencies | Uses the class's existing properties | Needs new constructor dependencies the rest of the class does not use |
| Testing | Tested through the public method | Critical enough for its own test |

Do not abstract until the third repetition; duplication is cheaper than the
wrong abstraction [rule of three].

- When you create a class by mirroring a sibling, count the siblings that
  share the same steps first. If yours makes three or more, extract the shared
  steps (base class with a template method, trait, or helper) before adding
  it; do not copy them again [DRY]. Extract only when the copies change for
  the same reason; code that merely looks alike stays duplicated.
- Serialize each resource through one presenter or resource class. Never build
  its array by hand in a second place [single source of truth].

## Size is a signal, not a limit

There is no correct line count. Around 80 lines for a UI component and 120 for
any other class, stop and check these causes [SRP]:

1. More than one reason to change (two different stakeholders would edit it).
2. Mixed abstraction levels (business intent next to array or SQL plumbing).
3. Low cohesion (groups of methods that use disjoint properties).
4. Five or more constructor dependencies.
5. A method with high cognitive complexity.

If none apply, the size is fine (large enums, test files, parsers). If one
applies, split along that cause.

## Delegate measurement to tools

Do not estimate complexity by reading. When the project has a linter or static
analyser configured, run it and obey its output over these heuristics. See the
stack skill (for example `laravel-conventions`) for the exact commands.
