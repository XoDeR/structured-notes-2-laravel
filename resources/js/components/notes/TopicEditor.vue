<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Paperclip, Upload, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/notes/RichTextEditor.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { destroy as destroyMedia } from '@/routes/media';
import { store, update } from '@/routes/topics';
import { store as storeAttachment } from '@/routes/topics/attachments';
import { store as storeImage } from '@/routes/topics/images';
import type { TopicDetail } from '@/types/notes';

const props = defineProps<{
    /** The topic being edited, or null to create a new one in `nodeId`. */
    topic: TopicDetail | null;
    nodeId: string;
    nodeIsPublic: boolean;
    knownTags: string[];
}>();

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    node_id: props.nodeId,
    title: props.topic?.title ?? '',
    content: props.topic?.content ?? '',
    is_public: props.topic?.is_public ?? false,
    tags: [...(props.topic?.tags ?? [])],
});

const tagDraft = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);

const suggestions = computed(() =>
    props.knownTags.filter((t) => !form.tags.includes(t)),
);

function addTag(): void {
    const value = tagDraft.value.trim().replace(/[;,]+$/, '');

    if (value && !form.tags.includes(value)) {
        form.tags.push(value);
    }

    tagDraft.value = '';
}

function onTagKey(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',' || event.key === ';') {
        event.preventDefault();
        addTag();
    } else if (event.key === 'Backspace' && !tagDraft.value) {
        form.tags.pop();
    }
}

function submit(): void {
    addTag();

    if (props.topic) {
        form.patch(
            update.url(props.topic.id, { query: { node: props.nodeId } }),
            {
                preserveScroll: true,
                onSuccess: () => emit('close'),
            },
        );
    } else {
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
}

function onPickFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file || !props.topic) {
        return;
    }

    router.post(
        storeAttachment.url(props.topic.id),
        { file },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            only: ['topic'],
            onStart: () => (uploading.value = true),
            onFinish: () => (uploading.value = false),
        },
    );
    input.value = '';
}

function removeFile(id: string): void {
    router.delete(destroyMedia.url(id), {
        preserveScroll: true,
        preserveState: true,
        only: ['topic'],
    });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="topic-title">Title</Label>
            <Input
                id="topic-title"
                v-model="form.title"
                maxlength="255"
                required
                autofocus
            />
            <InputError :message="form.errors.title" />
        </div>

        <RichTextEditor
            v-model="form.content"
            :image-upload-url="topic ? storeImage.url(topic.id) : null"
        />
        <InputError :message="form.errors.content" />

        <div class="grid gap-2">
            <Label for="topic-tags">Tags</Label>
            <div
                class="flex flex-wrap items-center gap-1.5 rounded-md border px-2 py-1.5"
            >
                <Badge
                    v-for="tag in form.tags"
                    :key="tag"
                    variant="secondary"
                    class="gap-1"
                >
                    {{ tag }}
                    <button
                        type="button"
                        :aria-label="`Remove ${tag}`"
                        @click="form.tags = form.tags.filter((t) => t !== tag)"
                    >
                        <X class="size-3" />
                    </button>
                </Badge>
                <input
                    id="topic-tags"
                    v-model="tagDraft"
                    list="known-tags"
                    class="min-w-32 flex-1 bg-transparent text-sm outline-none"
                    placeholder="Add a tag and press Enter"
                    @keydown="onTagKey"
                    @blur="addTag"
                />
                <datalist id="known-tags">
                    <option
                        v-for="tag in suggestions"
                        :key="tag"
                        :value="tag"
                    />
                </datalist>
            </div>
            <InputError :message="form.errors.tags" />
        </div>

        <div class="grid gap-2">
            <Label>Files</Label>
            <div v-if="topic" class="flex flex-wrap items-center gap-2">
                <span
                    v-for="file in topic.attachments"
                    :key="file.id"
                    class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-sm"
                >
                    <Paperclip class="size-3.5" />
                    {{ file.name }}
                    <button
                        type="button"
                        :aria-label="`Delete ${file.name}`"
                        class="text-destructive"
                        @click="removeFile(file.id)"
                    >
                        <X class="size-3.5" />
                    </button>
                </span>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="uploading"
                    @click="fileInput?.click()"
                >
                    <Upload /> Add file
                </Button>
                <input
                    ref="fileInput"
                    type="file"
                    class="hidden"
                    @change="onPickFile"
                />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Save the topic first, then you can attach files and add images.
            </p>
        </div>

        <div
            class="flex items-center justify-between gap-4 rounded-md border p-3"
        >
            <div class="space-y-0.5">
                <Label for="topic-public">Public</Label>
                <p class="text-xs text-muted-foreground">
                    Public topics are readable by everyone and get a shareable
                    link.
                    <template v-if="!nodeIsPublic"
                        >This node is private, so the topic must stay
                        private.</template
                    >
                </p>
            </div>
            <Switch
                id="topic-public"
                :model-value="form.is_public"
                :disabled="!nodeIsPublic && !form.is_public"
                @update:model-value="form.is_public = $event"
            />
        </div>
        <InputError :message="form.errors.is_public" />

        <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="emit('close')"
                >Cancel</Button
            >
            <Button type="submit" :disabled="form.processing">Save</Button>
        </div>
    </form>
</template>
