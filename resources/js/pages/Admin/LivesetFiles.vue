<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem as HeaderBreadrumbItem, type Liveset, LivesetFile} from '@/types';
import {Head, router, useForm} from '@inertiajs/vue3';
import {Button} from '@/components/ui/button';
import {ref, computed} from 'vue';
import {Edit, TrafficCone, Trash2, Save, ChevronRight, Plus} from 'lucide-vue-next'
import {
    Breadcrumb, BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbSeparator
} from "@/components/ui/breadcrumb";
import {Badge} from "@/components/ui/badge";
import ConvertLivesetFile from "@/components/ConvertLivesetFile.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";

type RemoteDir = { [name: string]: RemoteDir | string };

const props = defineProps<{
    liveset: Liveset;
    files: LivesetFile[],
    audioFiles: RemoteDir,
    qualities: { [key: string]: string },
    isGeneratingWaveform: boolean,
}>();

const form = useForm({
    liveset_id: props.liveset.id,
    name: '',
    path: '',
    quality: '',
    original: true as boolean,
});

const isEditing = ref(false);
const editingFileId = ref<number | null>(null);
const fileSelectorPath = ref<string[]>([]);

const fileSelectorVisibleFiles = computed<{ name: string, file?: string }[]>(() => {
    // Zoom down to the current path
    let visibleFiles: RemoteDir | string = props.audioFiles;
    for (const path of fileSelectorPath.value) {
        visibleFiles = (visibleFiles as RemoteDir)[path];
    }

    // Shouldn't happen
    if (typeof visibleFiles === 'string') {
        return [];
    }

    return Object.keys(visibleFiles).map(file => {
        const path = visibleFiles[file];

        return {
            name: file,
            file: typeof path === 'string' ? path : undefined,
        }
    })
});

const hasOriginalLossless = computed(() => props.files.find(file => file.original && file.quality === 'lossless'));
const originalFile = computed(() => props.files.find(file => file.original));
const hasHq = computed(() => props.files.find(file => file.quality === 'hq') !== undefined);
const hasLq = computed(() => props.files.find(file => file.quality === 'lq') !== undefined);
const showConvertButton = computed(() => hasOriginalLossless.value && (!hasHq.value || !hasLq.value));

const startEditing = (file: LivesetFile) => {
    form.path = file.path;
    form.name = file.path.split("/").reverse()[0];
    form.quality = file.quality;
    form.original = file.original;
    isEditing.value = true;
    editingFileId.value = file.id;
};

const cancelEditing = () => {
    form.reset();
    form.clearErrors();
    isEditing.value = false;
    editingFileId.value = null;
};

const submitForm = () => {
    if (isEditing.value && editingFileId.value) {
        form.patch(route('admin.livesets.files.edit', [props.liveset.id, editingFileId.value]), {
            onSuccess: () => {
                cancelEditing();
            }
        });
    } else {
        form.post(route('admin.livesets.files.import', props.liveset.id), {
            onSuccess: () => {
                form.reset();
                form.clearErrors();
            }
        });
    }
};

const generateAudiowaveform = () => {
    router.post(route('admin.livesets.files.audiowaveform.generate', [props.liveset.id]));
};

const deleteAudiowaveform = () => {
    router.delete(route('admin.livesets.files.audiowaveform.delete', [props.liveset.id]));
}

const deleteFile = (fileId: number) => {
    router.delete(route('admin.livesets.files.delete', [props.liveset.id, fileId]));
};

const selectFile = (file: string) => {
    form.path = file;
    form.name = file.replace('Unknown Album', '')
        .replaceAll(/[^a-zA-Z0-9\-_.]+/g, '-')
        .replaceAll(/-{2,}/g, '-')
        .replaceAll(/-\./g, '.');

    if (form.name.endsWith('.wav')) {
        form.quality = 'lossless';
    } else if (form.name.endsWith('.m4a')) {
        form.quality = 'lq';
    }
}

const breadcrumbs: HeaderBreadrumbItem[] = [
    {
        title: 'Editions',
        href: route('admin.editions'),
    },
    {
        title: 'TFW #' + (props.liveset?.edition?.number ?? '?'),
        href: route('admin.editions.view', props.liveset?.edition?.id ?? 0),
    },
    {
        title: 'Livesets',
        href: route('admin.livesets'),
    },
    {
        title: props.liveset.title,
        href: route('admin.livesets.view', props.liveset?.id),
    },
    {
        title: 'Files',
        href: route('admin.livesets.files', props.liveset?.id),
    }
];
</script>

<template>
    <Head :title="liveset.title + '\'s files'"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ liveset.title }}'s Files</h1>
            </div>

            <!-- Files Table -->
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-xl font-semibold mb-4">Files</h2>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                        <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <th class="py-2 px-4 text-left">Path</th>
                            <th class="py-2 px-4 text-left">Quality</th>
                            <th class="py-2 px-4 text-left">Original</th>
                            <th class="py-2 px-4 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-if="files.length === 0">
                            <td colspan="5" class="py-4 px-4 text-center text-gray-500">
                                No files found for this liveset
                            </td>
                        </tr>
                        <tr v-for="file in files" :key="file.id"
                            class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <td class="py-2 px-4">
                                {{ file.path }}
                                <Badge variant="destructive" v-if="!file.exists && !file.converting">
                                    Missing!
                                </Badge>
                                <Badge variant="secondary" v-if="file.converting">
                                    Converting...
                                </Badge>
                            </td>
                            <td class="py-2 px-4">{{ qualities[file.quality] }}</td>
                            <td class="py-2 px-4">{{ file.original ? 'Yes' : 'No' }}</td>
                            <td class="py-2 px-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <ConvertLivesetFile
                                        v-if="file.original && file.quality === 'lossless' && showConvertButton"
                                        :file="file" :has-hq="hasHq" :has-lq="hasLq"/>

                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8"
                                        title="Edit file"
                                        @click="startEditing(file)"
                                    >
                                        <Edit class="h-4 w-4"/>
                                    </Button>

                                    <ConfirmDialog title="Delete this file?"
                                                   description="This will remove this entry from the database & delete the file from disk."
                                                   confirm-label="Delete"
                                                   @confirm="deleteFile(file.id)">
                                        <Button variant="destructive" size="icon" class="h-8 w-8" title="Delete file">
                                            <Trash2 class="h-4 w-4"/>
                                        </Button>
                                    </ConfirmDialog>

                                </div>
                            </td>
                        </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-sidebar-border dark:border-sidebar-border"
                               style="margin-top: 1rem;"
                               v-if="files.length || liveset.audio_waveform_path">
                        <tr class="bg-gray-100 dark:bg-gray-800 font-medium">
                            <td class="py-3 px-4">
                                <span v-if="liveset.audio_waveform_path">{{ liveset.audio_waveform_path }}</span>
                                <Badge variant="destructive" v-else>
                                    Missing!
                                </Badge>
                                <Badge variant="secondary" v-if="isGeneratingWaveform">
                                    Generating...
                                </Badge>
                            </td>
                            <td class="py-3 px-4">Audiowaveform</td>
                            <td class="py-3 px-4"></td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <ConfirmDialog title="Generate audiowaveform from original?"
                                                   description="Use the original file to generate a audiowaveform JSON peaks file, to prevent users from having to generate locally."
                                                   confirm-label="Generate"
                                                   v-if="!liveset.audio_waveform_path && !isGeneratingWaveform && originalFile !== undefined"
                                                   @confirm="generateAudiowaveform">
                                        <Button variant="default" size="icon" class="h-8 w-8"
                                                title="Generate audiowaveform from original">
                                            <TrafficCone class="h-4 w-4"/>
                                        </Button>
                                    </ConfirmDialog>

                                    <ConfirmDialog title="Delete audiowaveform?" confirm-label="Delete"
                                                   @confirm="deleteAudiowaveform"
                                                   v-if="liveset.audio_waveform_path && !isGeneratingWaveform">
                                        <Button variant="destructive" size="icon" class="h-8 w-8"
                                                title="Delete audiowaveform">
                                            <Trash2 class="h-4 w-4"/>
                                        </Button>
                                    </ConfirmDialog>
                                </div>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Add/Edit File Form -->
            <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="text-xl font-semibold mb-4">
                    {{ isEditing ? 'Edit File' : 'Add New File' }}
                    <Button
                        v-if="isEditing"
                        variant="outline"
                        size="sm"
                        class="ml-2"
                        @click="cancelEditing"
                    >
                        Cancel
                    </Button>
                </h2>

                <form @submit.prevent="submitForm" class="flex flex-col gap-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label for="path" class="font-medium">Path</label>
                            <input
                                id="path"
                                v-model="form.path"
                                class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border"
                                readonly
                                required/>

                            <p class="text-sm text-muted-foreground" v-if="!isEditing">
                                The file under private/audio/ that we're going to import.
                            </p>

                            <div v-if="form.errors.path" class="text-sm text-red-500">
                                {{ form.errors.path }}
                            </div>

                            <div
                                class="border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border flex flex-col"
                                v-if="!isEditing">
                                <Breadcrumb>
                                    <BreadcrumbList>
                                        <BreadcrumbItem @click="fileSelectorPath = []" class="cursor-pointer">
                                            Audio
                                        </BreadcrumbItem>

                                        <template v-for="(path, index) in fileSelectorPath" :key="index">
                                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1"/>
                                            <BreadcrumbItem class="cursor-pointer"
                                                            @click="fileSelectorPath = fileSelectorPath.splice(0, index + 1)">
                                                {{ path }}
                                            </BreadcrumbItem>
                                        </template>
                                    </BreadcrumbList>
                                </Breadcrumb>


                                <div v-for="file in fileSelectorVisibleFiles" :key="file.name"
                                     class="flex items-center justify-between pl-2 py-0.5 rounded-lg hover:bg-muted/50 transition-colors"
                                     :class="{ 'cursor-pointer': !file.file }"
                                     @click="file.file ? selectFile(file.file) : fileSelectorPath.push(file.name)">
                                    <div>
                                        {{ file.name.split("/").reverse()[0] }}
                                    </div>
                                    <div>
                                        <Button variant="ghost" size="icon"
                                                v-if="!file.file" type="button">
                                            <ChevronRight/>
                                        </Button>
                                        <Button variant="outline" v-if="file.file" type="button" class="cursor-pointer">
                                            Use
                                            <Plus/>
                                        </Button>
                                    </div>
                                </div>


                            </div>


                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label for="name" class="font-medium">Name</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                :readonly="isEditing"
                                class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border"
                                required
                            />
                            <p class="text-sm text-muted-foreground" v-if="!isEditing">
                                The name as the file will be renamed to under public/ files.
                            </p>
                            <div v-if="form.errors.name" class="text-sm text-red-500">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label for="quality" class="font-medium">Quality</label>
                            <select
                                id="quality"
                                v-model="form.quality"
                                class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border"
                                required>
                                <option value="" disabled>Select a quality</option>
                                <option :value="quality" v-for="(label, quality) of qualities" :key="quality">
                                    {{ label }}
                                </option>
                            </select>
                            <div v-if="form.errors.quality" class="text-sm text-red-500">
                                {{ form.errors.quality }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-6">
                            <input
                                id="original"
                                v-model="form.original"
                                type="checkbox"
                                class="rounded border border-sidebar-border/70 bg-transparent dark:border-sidebar-border"
                            />
                            <label for="original" class="font-medium">Original</label>
                            <div v-if="form.errors.original" class="text-sm text-red-500">
                                {{ form.errors.original }}
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <span class="text-green-600" v-if="form.recentlySuccessful">
                            Saved...
                        </span>
                        <Button type="submit" :disabled="form.processing">
                            <Save class="h-4 w-4 mr-2"/>
                            {{ isEditing ? 'Update File' : 'Import File' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
