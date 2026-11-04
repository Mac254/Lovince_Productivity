import { useAttrs } from 'vue';

/**
 * Splits a field's fallthrough attributes: `class` and `style` place the whole field in its layout,
 * everything else (`maxlength`, `autocomplete`, `aria-*`, listeners) reaches the control inside.
 * Call both in the template so they track the latest attributes on every render.
 */
export function useFieldAttrs() {
    const attrs = useAttrs();

    return {
        root: () => ({ class: attrs.class, style: attrs.style }),
        control: () => Object.fromEntries(Object.entries(attrs).filter(([key]) => key !== 'class' && key !== 'style')),
    };
}
