export type ProjectKind = 'board' | 'log';

export type ItemType = 'note' | 'subproject';

/** A node in the sidebar explorer. Sub-projects nest without limit. */
export type TreeNode = {
    id: number;
    name: string;
    kind: ProjectKind;
    children: TreeNode[];
};

export type BoardItem = {
    id: number;
    title: string;
    body: string | null;
    type: ItemType;
    position: number;
    board_column_id: number;
    child: { id: number; kind: ProjectKind } | null;
};

export type BoardColumn = {
    id: number;
    name: string;
    position: number;
    items: BoardItem[];
};

export type LogEntry = {
    id: number;
    logged_on: string;
    title: string;
    body: string | null;
};

export type ProjectPayload = {
    id: number;
    name: string;
    description: string | null;
    kind: ProjectKind;
    is_root: boolean;
    columns?: BoardColumn[];
    entries?: LogEntry[];
};

export type TrailStep = {
    id: number;
    name: string;
    kind: ProjectKind;
};

/** A draft produced by the assistant, editable before it is created. */
export type PlanEntry = {
    logged_on: string;
    title: string;
    body: string | null;
};

export type PlanItem = {
    title: string;
    body: string | null;
    column: string;
    type: ItemType;
    project: Plan | null;
};

export type Plan = {
    name: string;
    kind: ProjectKind;
    description: string | null;
    columns: string[];
    items: PlanItem[];
    entries: PlanEntry[];
};

export type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string | null;
    plan: Plan | null;
    applied_project_id: number | null;
};

export type ChatConversation = {
    id: number;
    title: string;
    messages: ChatMessage[];
};

export type BoardOption = {
    id: number;
    label: string;
};
