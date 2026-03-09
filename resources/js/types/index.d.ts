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
    is_admin: boolean;
    listening_visibility?: ListeningVisibility;
    created_at: string;
    updated_at: string;
}

export type ListeningVisibility = 'everyone' | 'authenticated' | 'nobody';

export type BreadcrumbItemType = BreadcrumbItem;

export type Edition = {
    id: number,
    number: string,
    tag_line?: string,
    date?: string,
    notes?: string,
    livesets?: Liveset[],
    empty_note?: string,
    timetabler_mode: boolean,
    poster_url?: string,
    poster_srcset_urls?: {
        url: string,
        width: number,
    }[],
}


/**
 * Key is the quality, value is the URL to the file
 */
export type LivesetFilesByQuality = {
    lq?: string,
    hq?: string,
    lossless?: string,
};

export type LivesetQuality = keyof LivesetFilesByQuality;

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
    /** null = no timetable configured, false = invalid timetable (missing data), string = timetable slot (start to end times) */
    timeslot?: null|false|string,
    soundcloud_url?: string,
    audio_waveform_path?: string,
    audio_waveform_url?: string,
    tracks?: LivesetTrack[],
    files?: LivesetFilesByQuality,
    plays_count?: number,
    favorites_count?: number,
    is_favorited?: boolean,
}

export type LivesetTrack = {
    id: number,
    liveset_id: number,
    title: string,
    // Timestamp in seconds from start of liveset
    timestamp: number|null,
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

// User account types
export interface InviteCode {
    id: number;
    code: string;
    used_by?: { id: number; name: string } | null;
    used_at?: string | null;
    created_at: string;
}

export interface PlayHistoryItem {
    id: number;
    liveset_id: number;
    liveset?: Liveset | null;
    started_at_position: number;
    ended_at_position: number | null;
    duration_listened: number;
    quality: string | null;
    platform: string;
    created_at: string;
}

export interface PlaybackPosition {
    liveset_id: number;
    liveset?: Liveset | null;
    position: number;
    updated_at: string;
    device?: {
        client_id: string;
        device_type: DeviceType;
        device_name: string;
        device_nickname: string | null;
        display_name: string;
        is_current: boolean;
    } | null;
}

export type DeviceType = 'mobile' | 'desktop' | 'tablet' | 'car' | 'other';

export interface UserDevice {
    id: number;
    client_id: string;
    device_type: DeviceType;
    device_name: string;
    device_nickname: string | null;
    display_name: string;
    is_hidden: boolean;
    last_seen_at: string;
    created_at: string;
}

export interface Favorite {
    liveset_id: number;
    created_at: string;
}

export type PlaylistVisibility = 'private' | 'public' | 'unlisted';

export interface Playlist {
    id: number;
    name: string;
    description?: string | null;
    visibility: PlaylistVisibility;
    share_code?: string | null;
    items?: PlaylistItem[];
    items_count?: number;
    created_at: string;
    updated_at: string;
}

export interface PlaylistItem {
    id: number;
    liveset_id: number;
    liveset?: Liveset | null;
    position: number;
}

// Queue system types
export type QueueSource =
    | { type: 'playlist'; shareCode: string }
    | { type: 'favorites' }
    | { type: 'edition'; editionId: number };

export interface QueueItem {
    id: string;           // unique id for list key
    livesetId: number;
    source: QueueSource;
}
