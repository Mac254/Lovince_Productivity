export type TableAlign = 'start' | 'center' | 'end';

export const alignClasses: Record<TableAlign, string> = {
    start: 'text-left',
    center: 'text-center',
    end: 'text-right',
};
