<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem, type Liveset, type Edition, type LivesetTrack} from '@/types';
import {Head, Link, useForm} from '@inertiajs/vue3';
import {Button} from '@/components/ui/button';
import {Label} from '@/components/ui/label';
import {Input} from '@/components/ui/input';
import {computed, useTemplateRef} from 'vue';
import {File} from 'lucide-vue-next'
import axios from 'axios';
import {formatDuration, parseDuration} from "@/lib/utils";

interface Props {
    liveset: Liveset | null;
    editions: Edition[];
    fileCount: number,
}

const props = defineProps<Props>();
const isNewLiveset = !props.liveset;

// Format tracks for display in textarea
const formatTracksForTextarea = (tracks: LivesetTrack[] | undefined): string => {
    if (!tracks || tracks.length === 0) return '';

    return tracks.map(track => {
        // Convert timestamp from seconds to HH:MM:SS format
        const formattedTimestamp = formatDuration(track.timestamp, true, '--:--:--');
        return `${formattedTimestamp} | ${track.title}`;
    }).join('\n');
};

const form = useForm({
    edition_id: props.liveset?.edition_id ?? '',
    title: props.liveset?.title ?? '',
    artist_name: props.liveset?.artist_name ?? '',
    description: props.liveset?.description ?? '',
    bpm: props.liveset?.bpm ?? '',
    genre: props.liveset?.genre ?? '',
    duration_in_seconds: props.liveset?.duration_in_seconds as number | undefined,
    started_at: props.liveset?.started_at ? new Date(props.liveset.started_at).toISOString().slice(0, 16) : '',
    lineup_order: props.liveset?.lineup_order ?? '',
    soundcloud_url: props.liveset?.soundcloud_url ?? '',
    tracks_text: formatTracksForTextarea(props.liveset?.tracks),
});

const cueInput = useTemplateRef<HTMLInputElement>('cueInput');

const edition = computed<Edition | undefined>(() => {
    if (!form.edition_id) {
        return undefined;
    }

    return props.editions.find(edition => edition.id == form.edition_id);
});

const selectCueFile = () => {
    cueInput.value?.click();
}

const onCueFileSelected = async (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) {
        return
    }

    const text = await file.text();
    const result = await axios.post<{ recorded_at?: string, performer?: string, songs: string[] }>(route('util.cue'), {
        content: text,
    });

    form.tracks_text = result.data.songs.join("\n");

    if (!form.artist_name && result.data.performer) {
        form.artist_name = result.data.performer;
    }

    if (!form.started_at && result.data.recorded_at) {
        form.started_at = new Date(result.data.recorded_at).toISOString().slice(0, 16);
    }
}

const handleSubmit = () => {
    if (isNewLiveset) {
        form.post(route('admin.livesets.store'));
    } else {
        form.patch(route('admin.livesets.update', props.liveset?.id));
    }
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Editions',
        href: route('admin.editions'),
    },
    {
        title: 'TFW #' + (edition.value?.number ?? '?'),
        href: route('admin.editions.view', edition.value?.id ?? 0),
    },
    {
        title: 'Livesets',
        href: route('admin.livesets'),
    },
    {
        title: isNewLiveset ? 'New Liveset' : props.liveset?.title || 'Liveset',
        href: isNewLiveset ? route('admin.livesets.create') : route('admin.livesets.view', props.liveset?.id),
    },
]);
</script>

<template>
    <Head :title="isNewLiveset ? 'New Liveset' : liveset?.title || 'Liveset'"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ isNewLiveset ? 'New Liveset' : liveset?.title || 'Liveset' }}</h1>
                <Link :href="route('admin.livesets.files', liveset.id)" v-if="liveset">
                    <Button variant="outline" class="cursor-pointer">
                        View files ({{ fileCount }})
                    </Button>
                </Link>
            </div>

            <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="edition_id">Edition</Label>
                            <select
                                id="edition_id"
                                v-model="form.edition_id"
                                class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border"
                                required :disabled="form.processing"
                            >
                                <option value="" disabled>Select an edition</option>
                                <option v-for="edition in editions" :key="edition.id" :value="edition.id">
                                    Edition #{{ edition.number }} - {{ edition.tag_line }}
                                </option>
                            </select>
                            <div v-if="form.errors.edition_id" class="text-sm text-red-500">
                                {{ form.errors.edition_id }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="artist_name">Artist Name</Label>
                            <Input
                                id="artist_name"
                                v-model="form.artist_name"
                                type="text"
                                required :disabled="form.processing"
                            />
                            <div v-if="form.errors.artist_name" class="text-sm text-red-500">{{
                                    form.errors.artist_name
                                }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                type="text"
                                required :disabled="form.processing"
                            />
                            <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="genre">Genre</Label>
                            <Input
                                id="genre"
                                v-model="form.genre"
                                type="text" :disabled="form.processing"
                            />
                            <div v-if="form.errors.genre" class="text-sm text-red-500">{{ form.errors.genre }}</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="bpm">BPM</Label>
                            <Input
                                id="bpm"
                                v-model="form.bpm" :disabled="form.processing"
                            />
                            <div v-if="form.errors.bpm" class="text-sm text-red-500">{{ form.errors.bpm }}</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="duration">Duration (MM:SS)</Label>
                            <Input
                                id="duration"
                                :model-value="formatDuration(form.duration_in_seconds)"
                                @update:model-value="form.duration_in_seconds = parseDuration($event.target.value)"
                                type="text"
                                placeholder="00:00"
                                :disabled="form.processing"
                            />
                            <div v-if="form.errors.duration_in_seconds" class="text-sm text-red-500">
                                {{ form.errors.duration_in_seconds }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="started_at">Recording started at</Label>
                            <Input
                                id="started_at"
                                v-model="form.started_at"
                                type="datetime-local"
                                :disabled="form.processing"
                            />
                            <div v-if="form.errors.started_at" class="text-sm text-red-500">{{
                                    form.errors.started_at
                                }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="lineup_order">Lineup Order</Label>
                            <Input
                                id="lineup_order"
                                v-model="form.lineup_order"
                                type="number"
                                :disabled="form.processing"
                            />
                            <div v-if="form.errors.lineup_order" class="text-sm text-red-500">
                                {{ form.errors.lineup_order }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="soundcloud_url">Soundcloud URL</Label>
                            <Input
                                id="soundcloud_url"
                                v-model="form.soundcloud_url"
                                type="text"
                                :disabled="form.processing"
                            />
                            <div v-if="form.errors.soundcloud_url" class="text-sm text-red-500">
                                {{ form.errors.soundcloud_url }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border"
                                :disabled="form.processing"
                            ></textarea>
                            <div v-if="form.errors.description" class="text-sm text-red-500">{{
                                    form.errors.description
                                }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2">
                            <div class="flex justify-between">
                                <div class="flex flex-col gap-2">
                                    <Label for="tracks_text">Tracks</Label>
                                    <div class="text-sm text-gray-500 mb-1">
                                        Format: [hh]:[mm]:[ss] | {title/name} (one track per line)
                                    </div>
                                </div>
                                <div>
                                    <Button size="icon" variant="outline" class="w-8 h-8" type="button"
                                            title="Select a .cue file" @click.prevent="selectCueFile"
                                            :disabled="form.processing">
                                        <File class="h-4 w-4"/>
                                    </Button>
                                    <input type="file" style="display: none;" ref="cueInput" accept=".cue"
                                           @change="onCueFileSelected"/>
                                </div>
                            </div>
                            <textarea
                                id="tracks_text"
                                v-model="form.tracks_text"
                                rows="6"
                                class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border font-mono"
                                placeholder="00:00:00 | Intro&#10;00:03:45 | Track Name&#10;00:07:22 | Another Track"
                                :disabled="form.processing"
                            ></textarea>
                            <div v-if="form.errors.tracks_text" class="text-sm text-red-500">{{
                                    form.errors.tracks_text
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <span class="text-green-600" v-if="form.recentlySuccessful">
                        Saved...
                    </span>
                    <Button type="submit" :disabled="form.processing">
                        {{ isNewLiveset ? 'Create Liveset' : 'Update Liveset' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
