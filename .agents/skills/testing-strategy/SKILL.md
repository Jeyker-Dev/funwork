---
name: testing-strategy
description: >
  Defines Jeyker's testing strategy: tests as refactoring insurance that assert
  observable behavior instead of implementation, mutation testing to prove the
  tests protect, and architecture tests to enforce conventions. Use when writing
  or reviewing tests, before refactoring existing code, or when deciding what
  and how to test.
metadata:
  author: Jeyker-Dev
  version: "1.0"
---

# Testing Strategy

## Refactoring insurance

1. Before refactoring existing logic, find the tests that cover its output. If
   none exist, write them first and see them pass against the current code.
2. Those tests assert the result (return value, response, persisted data,
   dispatched event), never the internal steps.
3. While the signature and the result stay the same, refactor aggressively with
   modern language features. The tests are what make that safe.

## What a test asserts

- Go through the public interface: an HTTP route, a UI component, a public
  method. Never test a private method directly.
- Assert every observable effect of the rule: output, status code, database
  state, and dispatched events or jobs.
- One test per business rule, named after the rule in plain language:
  `it('rejects orders older than 48 hours')`.
- Do not mock your own classes. Fake only the boundaries you do not own
  (mail, queues, HTTP clients, time).
- When a bug is reported, write the failing test that reproduces it before
  fixing it.

## Prove the tests protect

- A passing suite does not prove the tests catch regressions. Use mutation
  testing on the code you changed: every surviving mutant is a behavior no test
  checks. Add the missing assertion, do not delete the mutant.
- Treat a surviving mutant in business logic as a defect; a surviving mutant in
  trivial glue code (getters, framework wiring) may be ignored.

## Enforce conventions with tests

When a convention can be checked mechanically (layer dependencies, naming,
allowed methods, forbidden functions), write an architecture test for it
instead of relying on review. Conventions that only live in prose drift.

## Stack specifics

The exact runners and commands live in the stack skill, for example
`laravel-conventions` (`references/pest.md` and `references/tooling.md`).
