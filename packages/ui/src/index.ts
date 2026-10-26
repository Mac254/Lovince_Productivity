// Primitives — layout and text, no product meaning
export { default as Stack } from './primitives/Stack/Stack.vue';
export { default as Container } from './primitives/Container/Container.vue';
export { default as Text } from './primitives/Text/Text.vue';
export { default as Heading } from './primitives/Heading/Heading.vue';
export { default as Icon, type IconMotion } from './primitives/Icon/Icon.vue';
export { default as VisuallyHidden } from './primitives/VisuallyHidden/VisuallyHidden.vue';

// Icons — one set, addressed by meaning
export { icons, type IconName } from './icons/registry';

// Components
export { default as Button } from './components/Button/Button.vue';
export { default as IconButton } from './components/IconButton/IconButton.vue';
export { default as Field } from './components/Field/Field.vue';
export { default as Input } from './components/Input/Input.vue';
export { default as Textarea } from './components/Textarea/Textarea.vue';
export { default as Select } from './components/Select/Select.vue';
export { default as Checkbox } from './components/Checkbox/Checkbox.vue';
export { default as Badge } from './components/Badge/Badge.vue';
export type { BadgeTone } from './components/Badge/tones';
export { default as Card } from './components/Card/Card.vue';
export { default as CardHeader } from './components/CardHeader/CardHeader.vue';
export { default as Dialog } from './components/Dialog/Dialog.vue';
export { default as DropdownMenu } from './components/DropdownMenu/DropdownMenu.vue';
export { default as ChipGroup } from './components/ChipGroup/ChipGroup.vue';
export { default as ProgressBar } from './components/ProgressBar/ProgressBar.vue';
export { default as Skeleton } from './components/Skeleton/Skeleton.vue';
export { default as Avatar } from './components/Avatar/Avatar.vue';
export { default as Pagination } from './components/Pagination/Pagination.vue';
export { default as ToastRegion } from './components/Toast/ToastRegion.vue';
export { useToast, type Toast } from './components/Toast/useToast';
export type { MenuItem } from './components/DropdownMenu/types';
export type { ButtonSize, ButtonVariant } from './components/Button/variants';

// Patterns — product problems solved once
export { default as EmptyState } from './patterns/EmptyState/EmptyState.vue';
export { default as ErrorState } from './patterns/ErrorState/ErrorState.vue';
export { default as ConfirmDialog } from './patterns/ConfirmDialog/ConfirmDialog.vue';
export { default as PageHeader } from './patterns/PageHeader/PageHeader.vue';
export { default as SectionHeader } from './patterns/SectionHeader/SectionHeader.vue';
export { default as SearchField } from './patterns/SearchField/SearchField.vue';

// Composables
export { provideSurfaceTone, useSurfaceTone, type SurfaceTone } from './composables/useSurfaceTone';
