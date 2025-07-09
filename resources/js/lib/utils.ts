import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Format duration from seconds to HH:MM:SS for display
 *
 * @param seconds The number of seconds
 * @param withHours True = force hours, false = force minutes, undefined = use hours if duration is longer than 1 hour
 * @param emptyResponse
 */
export function formatDuration(seconds: number | undefined | null, withHours?: boolean, emptyResponse: string = ''): string {
    if (seconds === null || seconds === undefined) {
        return emptyResponse;
    }
    const hours = Math.floor(seconds / 3600);
    const secondsLeft = withHours === false ? seconds : seconds % 3600;
    const minutes = Math.floor(secondsLeft / 60);
    const remainingSeconds = seconds % 60;

    if (withHours === true || ( hours > 0 && withHours === undefined) ) {
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
};

export function parseDuration(durationString: string): number | undefined {
    if (!durationString) {
        return undefined;
    }

    const parts = durationString.split(':').map(Number);

    if (parts.length === 3) {
        // HH:MM:SS format
        return parts[0] * 3600 + parts[1] * 60 + parts[2];
    } else if (parts.length === 2) {
        // MM:SS format
        return parts[0] * 60 + parts[1];
    }

    return undefined;
}
