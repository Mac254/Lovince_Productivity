import type { IconName } from '../../icons/registry';

export interface TabItem<T extends string = string> {
    value: T;
    label: string;
    icon?: IconName;
    count?: number | null;
}
