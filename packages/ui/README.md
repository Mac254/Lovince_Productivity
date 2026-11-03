# @focus/ui: known weaknesses

The shared Vue components (`components/`, `patterns/`, `primitives/`) are built on `@lovince/ui-vue` and styled only with tokens from `@focus/tokens`. This file lists where they still fall short, so fixes can be planned instead of rediscovered. Each item says what goes wrong and a direction for the fix.

## Lovince already has the fix

Checked against `@lovince/ui-vue` 1.0.0, the version installed now. Many of the gaps below are in our wrappers, not in Lovince: the package ships the part, and we either don't use it or don't pass its props through. Fix these first; they need no new components.

| Gap in `@focus/ui`                                   | Lovince part to use                                                 |
| ---------------------------------------------------- | ------------------------------------------------------------------- |
| ConfirmDialog is a Dialog with `role="alertdialog"`  | `LvAlertDialogRoot` … `LvAlertDialogAction` / `LvAlertDialogCancel` |
| Sheet is a Dialog pinned to the right                | `LvSheetRoot` (`side`, `modal`), `LvSheetContent`, `LvSheetHandle`  |
| Toasts have no "Undo" button or second line          | `LvToastAction`, `LvToastDescription`, and the viewport's `hotkey`  |
| Select, Combobox and FacetFilter pick only one value | `multiple` on `LvSelectRoot` and `LvComboboxRoot`                   |
| An interactive Card isn't clickable as a whole       | `LvCardLink`                                                        |
| Table can't sort or select rows                      | `LvDataTable` (`sort`, `sortMode`, `selectable`)                    |
| There's no date range picker                         | `LvDateRangePickerRoot`                                             |
| Estimates and times are free-text inputs             | `LvTimeField`, `LvNumberField`                                      |

Lovince doesn't cover these, so they need work in our wrappers: Textarea growing with its content (`LvTextarea` only takes `modelValue`), a clear button and presets on the DatePicker, and toast placement next to the floating button.

## Dialog

The header and footer are pinned and only the body scrolls, in Lovince's scroll area with a thin thumb. The header and footer dividers show only while content is hidden behind them, and `busy` blocks Escape, outside clicks and the close button while a save runs. Modal widths are `sm` (28rem), `md` (42rem) and `lg` (60rem). What is still weak:

- **Closing loses edits.** Escape, the close button and clicks outside all close at once, and a half-filled form is lost (the task, goal, schedule and journal dialogs). Fix: an optional `dirty` prop that asks before closing, using `LvDialogContent`'s `escapeKeyDown` and `pointerDownOutside` events.
- **Fixed sizes only.** The three sizes don't adapt to their content. A dialog with a long form, like the schedule editor, gets tall instead of using its width. There is no full-screen layout on phones either: at 375px a dialog is a centred card with 1rem gutters, and the on-screen keyboard can push the footer out of view. Fix: a `fullscreen-mobile` behaviour below `sm`.
- **Inconsistent footers.** Most form dialogs put their actions in `#footer`. The Account dialogs (name, password) and the command palette keep their buttons inside the body, so those buttons scroll away and are spaced differently. Fix: move them into `#footer`.
- **One layer per dialog.** Popovers opened inside a dialog (Select, Combobox, DatePicker, DropdownMenu) mount in the dialog's `layer`. A dialog opened from inside another dialog works, but its overlay doesn't dim the first dialog's footer.

## Sheet

- It repeats Dialog's header markup instead of sharing it, so the two drift apart. Their close-button labels already differ ("Close dialog" in Dialog, "Close {title}" in Sheet).
- It has no `#footer` slot, so actions scroll away with the content.
- It has a single width (`max-w-form`) and always opens from the right.

## ConfirmDialog

- The confirm button is always `destructive`, even for actions that aren't, like "Replace plan" in the day planner.

## Toast

- **Covers the launcher button.** On phones the stack sits at `bottom-24`, and on desktop at `bottom-6` on the right. On desktop that is exactly where the floating quick-action button is, so toasts cover it.
- **Silent for screen readers?** Unconfirmed: we haven't checked that `loading` toasts and toasts updated in place are announced.

## Select and Combobox

- Items are keyed by their position in `options`. If the options change while the list is open (a type or goal created inline, or the goals store reloading), the highlighted item can shift to a different option.
- There is no search in Select. Long lists (task types, goals) need the Combobox, so the two kinds of field look and behave differently next to each other in the task form.
- Neither can choose more than one value.

## DatePicker

- It has no clear button. Removing a date means deleting the text by hand, and an optional date field doesn't say how to empty it.
- `min` and `max` are only checked in the calendar. A typed date outside the range is accepted until the API rejects it.
- It has no presets (Today, Tomorrow, Next week), although the app sets these dates most often.

## Field controls (Input, Textarea, Select, DatePicker)

- `inheritAttrs: false` sends `class` to the inner control, not the wrapper. `class="flex-1"` therefore resizes the input itself, and layouts need an extra wrapping `<div>`. Fix: a `wrapperClass` prop, or bind `class` to the root.
- Textarea doesn't grow with its content.
- Required fields have no visual marker; only optional ones are labelled "(optional)".

## Card

- `interactive` only changes the hover style. The card isn't a link or a button, so each product card adds its own `RouterLink` on the title. Clicking elsewhere on the card does nothing, though the hover suggests it does.
- `padding="none"` also sets `overflow-hidden`. That clips focus rings and popovers anchored near the card's edge.

## Tabs, ChipGroup and SegmentedControl

- These are three components for "pick one of a few". Their sizes and selected styles differ, so pages mix them inconsistently (Tasks uses chips for status, Suggestions uses tabs).
- On narrow screens Tabs scroll horizontally, with no fade or arrow to show that more tabs exist.

## FacetFilter

- Each facet picks one value. Filtering by two priorities or two goals isn't possible.
- Facets that are set have no count badge, so a long filter row doesn't show how much is filtered.

## Table

- It doesn't sort, select rows, or keep the header in view. Pages that need these (the goal table) build them on top.
- Wide tables scroll sideways on phones, with no column priority or stacked layout.

## General

- **Few component tests.** Only Alert, Button, ChipGroup and Section have tests here (plus the token drift test). Dialog, Select, DatePicker and the rest are only covered indirectly by `apps/web` tests.
- **Docs live in code comments.** There is no playground or story per component, so variants are found by reading source.
- **Icons are a hand-kept registry.** Adding one means editing `icons/registry.ts`, and an unused icon is still bundled.
