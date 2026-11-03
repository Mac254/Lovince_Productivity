export interface EmojiRecord {
    readonly emoji: string;
    readonly label: string;
    readonly group: string;
    readonly tags?: readonly string[];
}

export interface EmojiGroup {
    readonly key: string;
    readonly label: string;
}
