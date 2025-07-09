import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    // auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export type Edition = {
    id: number,
    number: string,
    tag_line?: string,
    date?: string,
    poster_path?: string,
    notes?: string,
    livesets?: Liveset[],
}


export type LivesetFilesByQuality = {
    lq?: string,
    hq?: string,
    lossless?: string,
};

export type Liveset = {
    id: number,
    edition_id: number,
    edition?: Edition,
    title: string,
    artist_name: string,
    description?: string,
    bpm?: string,
    genre?: string,
    duration_in_seconds?: number,
    started_at?: string,
    lineup_order?: number,
    soundcloud_url?: string,
    audio_waveform_path?: string,
    audio_waveform_url?: string,
    tracks?: LivesetTrack[],
    files?: LivesetFilesByQuality
}

export type LivesetTrack = {
    id: number,
    liveset_id: number,
    title: string,
    // Timestamp in seconds from start of liveset
    timestamp?: number,
    order: number,
}

export type LivesetFile = {
    id: number,
    liveset_id: number,
    path: string,
    quality: string,
    original: boolean,
    exists: boolean,
    converting: boolean,
}


