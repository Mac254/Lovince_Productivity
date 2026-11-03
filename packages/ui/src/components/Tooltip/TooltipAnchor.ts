import { LvSlot } from '@lovince/ui-vue';
import { defineComponent, h } from 'vue';

/**
 * Hands the tooltip trigger's behaviour to the slotted control without its `id`, `type` and `data-state`,
 * which belong to the control (a radio's checked state, a menu trigger's open state). Lovince adopts the
 * control's own id for `aria-describedby`.
 */
export default defineComponent({
    name: 'TooltipAnchor',
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        const owned = new Set(['id', 'type', 'data-state']);
        return () => h(LvSlot, Object.fromEntries(Object.entries(attrs).filter(([name]) => !owned.has(name))), slots);
    },
});
