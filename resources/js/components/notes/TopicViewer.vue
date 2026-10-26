<script setup lang="ts">
import { Paperclip, Tag } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import type { TopicDetail } from '@/types/notes';

defineProps<{ topic: TopicDetail }>();

const emit = defineEmits<{ tag: [tag: string] }>();

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return bytes < 1024 * 1024
        ? `${(bytes / 1024).toFixed(1)} KB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <article class="space-y-4">
        <header class="space-y-1">
            <h2 class="text-xl font-semibold">{{ topic.title }}</h2>
            <p class="text-xs text-muted-foreground">
                {{ topic.owner }} ·
                {{ new Date(topic.updated_at).toLocaleString() }}
                <Badge v-if="topic.is_link" variant="secondary" class="ml-1"
                    >Link</Badge
                >
                <Badge v-if="topic.is_public" variant="outline" class="ml-1"
                    >Public</Badge
                >
            </p>
        </header>

        <!-- Content is sanitized on the server before it is stored. -->
        <div
            class="prose prose-sm max-w-none dark:prose-invert"
            v-html="topic.content"
        />

        <footer
            v-if="topic.tags.length || topic.attachments.length"
            class="space-y-2 border-t pt-3 text-sm"
        >
            <div
                v-if="topic.tags.length"
                class="flex flex-wrap items-center gap-1.5"
            >
                <Tag class="size-4 text-muted-foreground" />
                <button
                    v-for="tag in topic.tags"
                    :key="tag"
                    type="button"
                    @click="emit('tag', tag)"
                >
                    <Badge
                        variant="secondary"
                        class="cursor-pointer hover:bg-accent"
                        >{{ tag }}</Badge
                    >
                </button>
            </div>
            <div
                v-if="topic.attachments.length"
                class="flex flex-wrap items-center gap-x-3 gap-y-1"
            >
                <Paperclip class="size-4 text-muted-foreground" />
                <a
                    v-for="file in topic.attachments"
                    :key="file.id"
                    :href="file.url"
                    class="text-primary underline-offset-4 hover:underline"
                >
                    {{ file.name }}
                    <span class="text-xs text-muted-foreground"
                        >({{ formatSize(file.size) }})</span
                    >
                </a>
            </div>
        </footer>
    </article>
</template>
