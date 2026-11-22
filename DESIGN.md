# FunWork Design System: Luminous Editorial

Source: Stitch project "FunWork" (`14584368472277721493`), design system
"Luminous Editorial". This file is the source of truth for visual decisions
in this repository. It adapts the Stitch spec to Flux UI and Tailwind v4 and
fixes the places where the Stitch spec fails the accessibility floor.

## 1. North Star

The interface reads like a calm editorial publication, not a generic SaaS
dashboard: generous whitespace, layered light-grey surfaces, one luminous
orange accent, and confident Manrope headlines. Structure comes from tone
and depth, not from lines.

## 2. Color

### Surfaces (light)

Layers stack like sheets of paper. Lower layers are darker; cards are white.

| Token | Hex | Use |
|---|---|---|
| `surface` | `#f6f6f8` | Page canvas |
| `surface-low` | `#f0f1f3` | Sidebar, header, secondary sections, row hover |
| `surface-card` | `#ffffff` | Cards, modals, inputs |
| `surface-high` | `#e1e2e5` | Insets, wells, selected chips |
| `surface-highest` | `#dbdde0` | Pressed state, progress tracks |

### Text

| Token | Hex | Contrast on `surface` |
|---|---|---|
| `ink` | `#2d2f31` | 12.4:1 (body, headings) |
| `ink-muted` | `#5a5c5d` | 6.2:1 (secondary text, labels) |
| `ink-subtle` | `#757779` | 4.2:1 (placeholders, large text only) |

### Brand

| Token | Hex | Use |
|---|---|---|
| `brand` | `#ff6d00` | Primary button gradient start, decorative accents, active bars, icons, focus glow |
| `brand-warm` | `#ff8a3d` | Primary button gradient end |
| `brand-hover` | `#f45f00` | Primary button hover gradient start |
| `brand-hover-warm` | `#ff7a24` | Primary button hover gradient end |
| `brand-ink` | `#431407` | Label text on bright orange primary buttons (5.2–7.1:1 across stops) |
| `brand-strong` | `#c2410c` | `--color-accent` for filled small controls with white marks, links (white text 5.2:1) |
| `brand-deep` | `#9b3f00` | Text on light tints (6.3:1 on `surface`) |
| `brand-tint` | `#fff0ea` | Badge and selection backgrounds |
| `brand-glow` | `#ffc96f` | Gradient end for top borders |

**Accessibility correction.** White text on `#ff6d00`–`#ff8a3d` (2.3–2.8:1) fails
the 4.5:1 floor, and so does the legacy `orange-500` accent (2.8:1). Rule:
bright orange never carries white text. Primary buttons pair the luminous
`brand` → `brand-warm` gradient with dark `brand-ink` (`#431407`, 5.2–7.1:1)
semibold text; smaller controls with white marks (checkboxes, radios,
switches) use `brand-strong` (`#c2410c`); and orange text on light surfaces
uses `brand-strong` or `brand-deep`.

### Status

Keep the existing `TaskStatus` and `TaskPriority` enum colors. Status is
always shown with a label or icon, never by color alone.

| Role | Hex |
|---|---|
| `danger` | `#b02500` |
| `danger-tint` | `#ffefec` |

### Dark mode

Stitch defines no dark theme, but the app supports one through
`@fluxAppearance`. Dark mode mirrors the same layering with zinc:

| Light token | Dark value |
|---|---|
| `surface` | `zinc-950` |
| `surface-low` | `zinc-900` |
| `surface-card` | `zinc-900` with a ghost border |
| `surface-high` | `zinc-800` |
| `ink` / `ink-muted` | `zinc-100` / `zinc-400` |
| `brand-strong` | unchanged for `--color-accent` fills (white text 5.2:1); primary buttons keep `brand` → `brand-warm` with `brand-ink` text; text uses `accent-content` = `orange-400` |

The surface and ink tokens are redefined under `.dark`, so `bg-surface`,
`text-ink`, and friends need no `dark:` variants.

## 2b. Implementation map

Tokens live in `resources/css/app.css` under `@theme`, so Tailwind exposes
them as utilities: `bg-surface`, `bg-surface-low`, `bg-surface-card`,
`bg-surface-high`, `text-ink`, `text-ink-muted`, `text-ink-subtle`,
`bg-brand`, `bg-brand-warm`, `bg-brand-hover`, `bg-brand-hover-warm`,
`text-brand-ink`, `bg-brand-strong`, `text-brand-deep`, `bg-brand-tint`,
`border-ghost`, `shadow-ambient`. Flux's `--color-accent` points at
`brand-strong`; `--color-accent-content` is the accessible orange for text.

## 3. Signature Gradient

- **Glow line:** a 4px top border on featured cards, auth cards, and modals,
  from `brand` to `brand-glow` at 90°. It replaces the current
  `#f59e42 → #facc15` gradient in `.form-line` and `.line-card`.
- **Primary button fill:** `brand` (`#ff6d00`) to `brand-warm` (`#ff8a3d`)
  at 90° (hover: `brand-hover` `#f45f00` to `brand-hover-warm` `#ff7a24`),
  with `brand-ink` (`#431407`) semibold text. White text on light orange
  fails the 4.5:1 floor (2.3–2.8:1); `brand-ink` passes across every stop
  (5.2–7.1:1 resting, 4.7–6.0:1 hover) in both light and dark themes.
- Use the gradient only for focal points: one primary CTA per view, plus
  the glow line on the main card.

## 4. Typography

Two families, already loaded in `resources/css/app.css`. Instrument Sans is
removed.

| Role | Family | Size / line height | Weight | Tracking |
|---|---|---|---|---|
| `display` | Manrope | 48 / 56 | 800 | -0.02em |
| `headline` | Manrope | 30 / 36 | 700 | -0.02em |
| `title` | Manrope | 20 / 28 | 700 | -0.01em |
| `body` | Inter | 15 / 24 | 400 | 0 |
| `body-sm` | Inter | 13 / 20 | 400 | 0 |
| `label` | Inter | 11 / 16 | 600 | +0.05em, uppercase |

- Manrope says *what* (headings, card titles, big numbers). Inter says *how*
  (body, forms, metadata). Never mix them inside one paragraph.
- Page titles align left. The current two-tone `x-title-text` hero
  ("All Your Projects, / Centralized Workspace.") stays as the brand voice
  for top-level pages.

## 5. Shape, Spacing, Depth

- **Radius:** inputs and buttons `rounded-lg` (8px); cards and modals
  `rounded-2xl` (16px); chips and avatars `rounded-full`.
- **Spacing:** 4px base. Sections separate with 32px (`gap-8`), headline
  blocks breathe with 48–64px (`py-12`–`py-16`).
- **No-Line rule:** no 1px opaque grey borders between sections or list
  rows. Separate with surface shifts, spacing, or row hover
  (`surface-low`). The global `gray-200` border default is replaced by the
  ghost border.
- **Ghost border:** when a container needs definition, use
  `outline-variant` `#acadaf` at 15–20% opacity.
- **Ambient shadow:** `0 20px 40px rgb(12 14 16 / 0.05)`. Never pure black,
  never stacked library drop shadows.
- **Glass:** floating overlays (dropdowns, flyouts, command bars) use
  `surface-card` at 85% opacity with a 20px backdrop blur.

## 6. Components (Flux)

Flux is the component library. These rules restyle it through tokens and a
small number of `data-flux-*` selectors in `app.css`; never fork the
components.

- **Button primary:** gradient fill (`brand` → `brand-warm`, hover `brand-hover` → `brand-hover-warm`) with `brand-ink` semibold text (section 3), `rounded-lg`, applied globally via `[data-flux-button]` in `app.css`.
- **Button secondary:** `surface-card` with ghost border.
- **Button ghost / tertiary:** no fill, `brand-strong` text.
- **Input:** `surface-card` fill, ghost border; on focus a solid
  `brand-strong` border plus a 4px `brand` glow at 15% opacity. Labels sit
  above the field in `label` style; placeholders are never labels.
- **Card:** `surface-card` on `surface`, `rounded-2xl`, no dividers inside;
  featured cards get the glow line.
- **Modal:** card rules plus the glow line, a `title` heading, and a
  `surface-low` footer holding Cancel (ghost) and the primary action.
- **Checkbox / radio:** unselected ghost border; selected `brand-strong`
  fill with a white mark; 200 ms ease-out.
- **Sidebar active item:** `surface-card` background, `brand-strong` text,
  and a 3px `brand` bar on the leading edge, replacing the current
  `bg-gray-300` with a 6px right border.
- **Empty states:** icon or illustration, a `title`, one sentence of help,
  and one primary action.

## 7. Motion

Per the `ui-design` skill: motion only answers a trigger, 150–250 ms for
small UI and up to 400 ms for sheets, ease-out on enter, ease-in on exit,
`transform` and `opacity` only, and `prefers-reduced-motion` respected.

## 8. Accessibility Floor

- Body text at least 4.5:1, large text and UI controls at least 3:1.
- Visible keyboard focus on every interactive element (the input focus
  glow doubles as the focus ring for buttons and links).
- Color is never the only signal for status, priority, or errors.
- Touch targets at least 44×44 px on mobile, 24×24 px on desktop.

## 9. Open Decisions

- **UI language.** The designs are in Spanish; the app mixes Spanish and
  English. Pick one before copy changes.
- **Dark mode fidelity.** Section 2 is a proposal, since Stitch has no dark
  theme.
