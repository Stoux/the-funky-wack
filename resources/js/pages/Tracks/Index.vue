<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Disc3 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import UserMenu from '@/components/UserMenu.vue';
import type { TrackSummary } from '@/types';

const props = defineProps<{
    tracks: {
        data: (TrackSummary & { sets_count: number })[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    search: string;
    sort: 'sets' | 'name';
}>();

const search = ref(props.search);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function reload(params: { q?: string; sort?: string }) {
    router.get(route('tracks.index'), {
        q: (params.q ?? search.value) || undefined,
        sort: (params.sort ?? props.sort) === 'name' ? 'name' : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

watch(search, value => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => reload({ q: value }), 300);
});
</script>

<template>
    <Head title="Tracks" />

    <div class="p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Tracks</h1>
                </div>
                <UserMenu />
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <Input v-model="search" name="q" class="w-full sm:max-w-xs" placeholder="Search artist or title..." aria-label="Search tracks" />
                <div class="flex gap-2">
                    <Button :variant="sort === 'sets' ? 'default' : 'outline'" size="sm" @click="reload({ sort: 'sets' })">Most played</Button>
                    <Button :variant="sort === 'name' ? 'default' : 'outline'" size="sm" @click="reload({ sort: 'name' })">A-Z</Button>
                </div>
            </div>

            <div v-if="tracks.data.length" class="divide-y rounded-lg border">
                <Link
                    v-for="track in tracks.data"
                    :key="track.slug"
                    :href="route('tracks.show', track.slug)"
                    class="flex items-center gap-3 p-3 hover:bg-muted/50 transition-colors"
                >
                    <Disc3 class="h-5 w-5 shrink-0 text-muted-foreground" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">
                            {{ track.title }}
                            <span v-if="track.version" class="text-muted-foreground">({{ track.version }})</span>
                        </div>
                        <div class="truncate text-sm text-primary">{{ track.artist }}</div>
                    </div>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ track.sets_count }} {{ track.sets_count === 1 ? 'set' : 'sets' }}
                    </span>
                </Link>
            </div>
            <div v-else class="rounded-lg border py-8 text-center text-muted-foreground">No tracks found</div>

            <div v-if="tracks.last_page > 1" class="flex items-center justify-between">
                <Button variant="outline" size="sm" :disabled="!tracks.prev_page_url" :as="tracks.prev_page_url ? Link : 'button'" :href="tracks.prev_page_url ?? undefined" preserve-scroll>
                    Previous
                </Button>
                <span class="text-sm text-muted-foreground">Page {{ tracks.current_page }} of {{ tracks.last_page }}</span>
                <Button variant="outline" size="sm" :disabled="!tracks.next_page_url" :as="tracks.next_page_url ? Link : 'button'" :href="tracks.next_page_url ?? undefined" preserve-scroll>
                    Next
                </Button>
            </div>
        </div>
    </div>
</template>
