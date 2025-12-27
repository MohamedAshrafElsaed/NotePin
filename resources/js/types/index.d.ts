export interface Auth {
    user: User | null;
    isAuthenticated: boolean;
    recordingCount: number;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    auth: Auth;
    flash?: {
        success?: string;
        error?: string;
        auth_success?: boolean;
    };
};

export interface User {
    id: number;
    name: string | null;
    email: string | null;
    avatar?: string | null;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
}
// NotePin Types
export interface ActionItem {
    id: number;
    text: string;
    completed: boolean;
    assignee?: string;
    due_date?: string;
    created_at: string;
    updated_at: string;
}

export interface Note {
    id: number;
    user_id: number;
    title: string;
    summary: string;
    transcript?: string;
    action_items: ActionItem[];
    participants?: string[];
    duration: string;
    audio_url?: string;
    share_token?: string;
    is_public: boolean;
    created_at: string;
    updated_at: string;
}

export interface NoteListItem {
    id: number;
    title: string;
    summary: string;
    action_items_count: number;
    completed_count: number;
    duration: string;
    created_at: string;
}

export interface SharedNote {
    id: number;
    title: string;
    summary: string;
    action_items: ActionItem[];
    duration: string;
    created_at: string;
    shared_by: string;
}

// Recording Types
export interface RecordingState {
    is_recording: boolean;
    duration: number;
    audio_level: number;
}

// API Response Types
export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface ApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
}
