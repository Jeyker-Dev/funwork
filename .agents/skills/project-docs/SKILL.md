---
name: project-docs
description: >
  Defines the standard docs/ structure for Jeyker's personal projects and how
  to keep it current: vision, architecture, areas, ADRs, roadmap, backlog,
  sprint, changelog, and a thin AGENTS.md. Use when starting a project, before
  implementing a task, after finishing one, when an architecture decision is
  made, or when creating or editing AGENTS.md. Not for client projects that
  have their own documentation rules.
metadata:
  author: Jeyker-Dev
  version: "1.0"
---

# Project Docs

`docs/` is the project's source of truth: what is not written there does not
exist. Feature-level working notes (specs, tasks, log of one feature) live
outside it, for example in `odd/tasks/<feature>.md`.

## Structure

```
AGENTS.md                 Thin entry point for agents
docs/
├── README.md             Map of docs/ and the project's work cycle
├── 00-vision.md          What it is, for whom, problem, scope, out of scope
├── 01-architecture.md    Stack, layers, modules, critical conventions
├── roadmap.md            Phases, priorities, and why
├── areas/<area>.md       Current state and business rules per area
├── decisions/ADR-NNN-<kebab-title>.md
├── tasks/backlog.md      Candidate tasks
├── tasks/sprint-actual.md  Tasks in progress and done this sprint
├── changelog.md          What was done and when
└── archive/              Old material, read-only
```

Areas are the project's own split (for example `backend`, `frontend`,
`database`, `design`). Write docs in the language `docs/` already uses; when
creating `docs/` for the first time, ask which language to use.

## Before implementing a task

1. Read `AGENTS.md`, `00-vision.md`, `01-architecture.md`, and the
   `areas/*.md` of every area the task touches.
2. Take the task from `tasks/sprint-actual.md`; if it is only in the backlog,
   move it to the sprint first.

## After finishing a task

1. Mark it `[x]` in `tasks/sprint-actual.md` and append the commit:
   `(commit abc1234)`.
2. Add an entry to `changelog.md` under today's date heading. One date heading
   per day; append to it if it already exists. Say what changed for the user
   and where (component, route, command), not how.
3. If a business rule or convention changed, update its `areas/*.md`.
4. If an architecture decision was made, write an ADR.

## Task format

```
- [ ] [AREA] Title — acceptance criterion — depends on: X
```

The acceptance criterion is observable ("user B gets 404 on user A's
notebook"), never "works" or "done". `depends on: -` when there is none.

## ADRs

- Write one when a choice is hard to reverse, affects several areas, or
  rejects a reasonable alternative (a library, a storage model, an auth flow,
  a workflow).
- One decision per file, numbered in sequence, using
  [assets/adr-template.md](assets/adr-template.md).
- Never edit an accepted ADR's decision. Supersede it with a new ADR and set
  the old one's status to `Superseded by ADR-NNN`.

## AGENTS.md

Keep it thin and use [assets/agents-template.md](assets/agents-template.md).
It holds only what is true for this project alone: stack and versions,
commands, project-only rules, and pointers to `docs/` and the installed skills.
General conventions belong to skills, and framework knowledge to official
tools (for example Laravel Boost). Never restate a skill's rules in
`AGENTS.md`; when they disagree, the agent cannot know which one wins.
