---
name: laravel-conventions
description: >
  Jeyker's Laravel and Livewire architecture conventions: where logic goes
  (models, Actions, Services, Form Objects, DTOs), REST-only controllers,
  boundary validation, authorization, enums, and collection pipelines. Use when
  creating or refactoring any PHP class, Livewire component, or route in a
  Laravel project. Complements Laravel Boost, which covers framework APIs.
metadata:
  author: Jeyker-Dev
  version: "1.0"
---

# Laravel Conventions

Framework APIs (Livewire, Flux, Pest, Tailwind syntax) come from Laravel Boost
and its docs search. This skill only holds decisions. It applies the rules of
the `clean-code` skill to Laravel.

## Where logic goes

| Need | Place |
|---|---|
| Change the state of one model | Model method with a domain verb (`$order->cancel()`) |
| Reusable query | Model scope (`Article::popular()`) |
| Validate and persist a Livewire form | Form Object in `app/Livewire/Forms/` |
| Validate an HTTP request | FormRequest |
| Typed data crossing layers | DTO (`readonly class CreateOrderData`) |
| One discrete operation, or anything touching 2+ models, mail, notifications, events, or external APIs | Action in `app/Actions/` |
| Several related operations with domain rules | Service in `app/Services/` |
| Validation rules shared by several forms | Trait in `app/Concerns/` |
| UI-only state (toggle, tabs, clipboard) | Alpine, no server trip |

## Models

- Allowed: attributes, casts, relationships, scopes, accessors, and methods
  that change only this model's own state.
- Not allowed: anything touching another model, sending mail, dispatching
  notifications, or calling services. That is an Action.
- Controllers and components never write attributes and save
  (`$order->status = ...; $order->save()`). They call the model method or an
  Action.
- Use `#[Fillable]` and `#[Hidden]` attributes, not `$fillable` arrays.
- Relationships always declare return types.

## Actions and Services

- Actions are invokable (`__invoke`), stateless, and receive dependencies
  through the constructor.
- An Action that writes to two or more models wraps the writes in
  `DB::transaction()`.
- Services are not invokable, are stateless, and are resolved from the
  container, never with `new`.

## Entry points stay thin

- HTTP controllers only coordinate HTTP. Public methods are limited to
  `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`. An action
  that does not fit becomes a sub-resource controller (`TeamMemberController`)
  or a single-action invokable controller.
- Do not create a controller where a Livewire page or `Route::view()` is enough.
- Livewire component methods are UI actions (`save`, `delete`) that delegate
  to a Form Object or an Action.
- Always use named routes; never hard-code URLs.

## Validation and authorization

- Each entry point validates in exactly one place: FormRequest (HTTP), Form
  Object with `#[Validate]` attributes (Livewire), or a DTO factory (external
  API, webhook, import). No inline validation arrays in controllers or
  components.
- After validation, pass a DTO or the validated model, not a raw array.
- Every write is authorized through a Policy: `$this->authorize()` in
  controllers and in Livewire actions.

## Values and collections

- States are backed enums (`OrderStatus::Pending`); fixed values are class
  constants.
- Transform in-memory data with collection pipelines (`filter`, `map`,
  `reject`, `sum`). Never accumulate with `foreach` (`$total += ...`).
- Filter in the query when the database can: never `->get()->filter()` for
  what a `where` does.
- Large result sets use `lazy()` or `chunkById()`.
- `foreach` is fine when the loop exists for side effects (save, dispatch).

## Naming

| Thing | Convention |
|---|---|
| Form Object | `XxxForm` |
| Action | verb + noun, `CreateInvoice` |
| Service | `XxxService` |
| DTO | `XxxData` |
| Livewire event | kebab-case, past tense: `task-created` |
| Route name | kebab-case with dots: `project.tasks` |

## References

- Livewire structure and component decomposition: [references/livewire.md](references/livewire.md)
- Pest conventions: [references/pest.md](references/pest.md)
- Static analysis, refactoring, architecture and mutation tests: [references/tooling.md](references/tooling.md)
- Business rule exceptions in Livewire and HTTP: [references/errors.md](references/errors.md)
