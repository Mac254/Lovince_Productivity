# Design system

Decisions flow one way:

```text
Design decisions → @focus/tokens → @focus/ui primitives → components → patterns → apps/web
```

**Components consume decisions; they never make them.** No hex codes, arbitrary values, off-scale spacing or Tailwind defaults appear in `packages/ui` or `apps/web` — `packages/ui/src/__tests__/token-drift.test.ts` fails the build if they do.

## Principles

1. **Focus over decoration.** One highlight per view (the featured card, the primary action); everything else is quiet.
2. **Meaning is never colour alone.** Status, priority and overdue states always carry text and an icon or dot count.
3. **Dark by default, legible everywhere.** Every text and control pairing is contrast-tested at the token layer.
4. **Calm motion.** Transform and opacity only, short durations, nothing under reduced motion.

## Tokens (`packages/tokens`)

Single source: TypeScript in `packages/tokens/src`. `pnpm tokens` generates `css/variables.css` (plain custom properties) and `css/tailwind.css` (Tailwind v4 theme). A test fails if the committed CSS drifts from the source.

### Tiers

| Tier | Example | Used by |
|---|---|---|
| Primitive | `--focus-violet-500`, `--focus-neutral-850` | Semantic tokens only |
| Semantic | `--accent`, `--surface`, `--fg-muted`, `--focus-ring` | Components, apps — this is what themes remap |
| Component | `--control-md`, `--notch-radius`, `--rail-width` | Specific component families |

### Colour roles

Theme: **dark**. Black page, near-black cards with a 1px edge, charcoal raised surfaces, white text, neon green `#00FF4E` for action. Ramps: true-grey `neutral`, `green` (neon at 400), `red` (destructive only), `tone` (job colours, validated for dark surfaces).

| Role | Purpose |
|---|---|
| `canvas` · `surface` · `surface-raised` · `surface-overlay` | Page (black) · cards (#171717) · hover/nested (#242424) · dialogs |
| `surface-inverse` (+ `fg-inverse`, `fg-inverse-muted`) | Light elements on the dark page: selected chips, secondary buttons, toasts |
| `chrome` (+ `chrome-hover`, `chrome-border`, `chrome-fg`, `chrome-fg-muted`) | Sidebar, timer and clock panels |
| `fg` · `fg-muted` · `fg-subtle` | Text hierarchy; subtle is placeholders only |
| `border` · `border-control` · `border-strong` | Card edges and dividers · control boundaries (≥3:1) · hover |
| `highlight` + `highlight-ink` | Neon with black text: primary actions, featured cards, active nav, counts |
| `highlight-fg` · `accent-fg` | Neon text on dark surfaces |
| `accent` · `accent-solid` + `accent-ink` | Green graphics (bars, rings) · charcoal surfaces with neon text (quote card) |
| `danger` · `danger-fg` | Destructive actions, overdue |
| `tone-green … tone-cyan` | Goal identity colours (CVD-safe, ≥3:1 on cards) |
| `chart-1` · `chart-2` · `chart-grid` · `heat-0…4` | Chart measure (green), context (grey), gridlines, heatmap from dark to bright |
| `focus-ring` · `selection` · `skeleton` · `scrim` | Utility roles |

### Interaction

Calm and consistent: colour and edge changes at `--duration-instant`, a 1px press on buttons, card hover sharpens the edge (no lift), icons move at most 10% / 2px / 8°, entrances rise 8px once. Layout spans the full window width; grids add a column on very wide screens.

### Contrast (enforced)

`packages/tokens/src/__tests__/tokens.test.ts` checks every legal pairing: 4.5:1 for text, 3:1 for subtle text, icons, control borders, the focus ring and accent graphics.

### Typography

| Role | Size / line height | Use |
|---|---|---|
| `caption` | 12 / 16 | Badges, meta |
| `label` | 14 / 20 | Labels, secondary text |
| `body` | 15 / 24 | Default |
| `lead` | 18 / 28 | Panel titles |
| `title` | 20 / 28 | Card titles |
| `heading` | 24 / 32 | Dialogs, section pills |
| `display-sm/md/lg` | 48 · 60 · 72 / 1 | Page titles, statistics |

Families: Outfit Variable (display), Inter Variable (body). Weights 500 / 600 / 700 only. Numbers use `tabular`.

### Spacing, radius, elevation, motion

- **Spacing:** base 4px; steps `0 1 2 3 4 6 8 12 16 20 24 32` (× 4px). Anything else fails the drift test.
- **Radius:** `sm` 12 · `md` 16 (controls, menus) · `lg` 28 (cards, dialogs) · `full` (pills, buttons).
- **Elevation:** lightness carries depth (canvas → surface → surface-raised); `shadow-floating` for menus/toasts, `shadow-overlay` for dialogs. Z-layers: `--z-nav` · `--z-dropdown` · `--z-modal` · `--z-toast` · `--z-skip`.
- **Motion:** `--duration-instant` 100 (hover/focus) · `fast` 150 · `normal` 200 · `slow` 300 (entrances) · `slower` 500 (progress). Easings `ease-out` (enter), `ease-in` (leave). `@focus/motion` reads the same tokens for GSAP.
- **Controls:** `--control-sm` 36 · `--control-md` 44 (minimum touch target) · `--control-lg` 56.

## Icons

Icons animate through the `motion` prop (`pop`, `nudge`, `nudge-back`, `lift`, `spin`, `wiggle`, `enter`) when their `group` parent is hovered or focused — transform only, token durations, off under reduced motion.


One set: **Solar Linear** from [Iconify](https://icon-sets.iconify.design/solar/), compiled by `unplugin-icons` into inline SVG components at build time — no runtime requests to the Iconify API, nothing for the CSP to allow.

- `packages/ui/src/icons/registry.ts` maps **semantic names** to glyphs: `add`, `edit`, `trash`, `calendar`, `check-circle`, `integration`, `server-error`, … Components only ever use these names (`<Icon name="calendar" />`, `<Button icon-start="add">`, `<IconButton icon="more" label="…">`), and `IconName` makes a typo a type error.
- Changing a glyph — or the whole set — is an edit to that one file. Browse alternatives at icon-sets.iconify.design and use `~icons/<set>/<icon>`.
- Sizes come from tokens: `sm` 16 · `md` 20 · `lg` 24 (`--icon-*`). Icons inherit `currentColor`, are `aria-hidden` unless given a `label`.

## UI package (`packages/ui/src`)

```text
styles/        index.css (tokens + base + utilities), base.css, utilities.css (notch, tabular)
icons/         registry.ts — the Solar icon set, by semantic name
primitives/    Stack · Container · Text · Heading · Icon · VisuallyHidden
components/    Button · IconButton · Field · Input · Textarea · Select · Checkbox · Badge · Card ·
               Dialog · DropdownMenu · ChipGroup · ProgressBar · Skeleton · Avatar · Pagination · Toast
patterns/      PageHeader · SectionHeader · SearchField · EmptyState · ErrorState · ConfirmDialog
composables/   useSurfaceTone
```

### Component APIs

| Component | API |
|---|---|
| `Button` | `variant` primary · secondary · outline · ghost · destructive; `size` sm · md · lg; `iconStart`, `iconEnd` (icon names), `loading`, `disabled`, `to` |
| `IconButton` | `icon` (icon name), `label` (required), `variant` outline · secondary · primary · ghost, `size`, `pressed`, `current`, `to` |
| `Card` | `tone` default · highlight · accent; `padding` md · lg; `interactive`; `#actions` slot = notched corner |
| `Badge` | `tone` neutral · success · warning · danger · info · accent · highlight · inverse; `variant` subtle · outline; `icon` or `dot`. Status tones are tinted fill + hairline ring + light text; they mean state (done, at risk, blocked, in flight) and always carry a label |
| `Text` | `role` caption · label · body · lead · title; `tone` default · muted · subtle · accent · highlight · danger · inverse |
| `Heading` | `level` 1–4; `role` display-lg · display-md · display-sm · heading · title · lead; `uppercase` |

**Surface tone.** `Card` provides its tone; `Text`, `Badge` and the app’s priority meter read it, so content on the Turbo featured card or the violet quote card switches to the paired `*-ink` automatically — no `inverted` props.

### States

Buttons: default · hover · focus-visible · active (1px press) · disabled · loading (label kept, `aria-busy`). Inputs: default · hover · focus · error (border + message + `aria-invalid` + `aria-describedby`) · disabled · readonly. Every data view ships loading (`Skeleton`), empty (`EmptyState` with an action) and error (`ErrorState` with retry). One focus ring for everything: 2px `focus-ring`, 2px offset, on `:focus-visible`.

## Adding to the system

1. Does it exist already, or can a variant of an existing component cover it?
2. Is it product-specific? Then it belongs in `apps/web/src/features`.
3. Needs a new value? Add a token first (`packages/tokens/src`), run `pnpm tokens`, then use it.
