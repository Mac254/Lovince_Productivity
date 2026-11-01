export interface ChecklistItem {
    id: number;
    task_id: number;
    title: string;
    done: boolean;
    position: number;
}

export type ChecklistItemInput = Partial<Pick<ChecklistItem, 'title' | 'done'>>;
