import type { IconName } from '../../icons/registry';

export interface MenuItem {
    label: string;
    icon?: IconName;
    destructive?: boolean;
    onSelect: () => void;
}
