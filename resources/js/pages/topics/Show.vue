<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TopicViewer from '@/components/notes/TopicViewer.vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { index } from '@/routes/notes';
import type { TopicDetail } from '@/types/notes';

const props = defineProps<{
    topic: Pick<
        TopicDetail,
        | 'id'
        | 'title'
        | 'content'
        | 'owner'
        | 'updated_at'
        | 'tags'
        | 'attachments'
    >;
}>();

const page = usePage();
const signedIn = computed(() => !!page.props.auth.user);

const detail = computed<TopicDetail>(() => ({
    ...props.topic,
    is_public: false,
    is_link: false,
    can_edit: false,
    share_url: null,
}));
</script>

<template>
    <Head :title="topic.title" />

    <div class="min-h-svh bg-background">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3"
            >
                <span class="font-semibold">{{ page.props.name }}</span>
                <Button as-child size="sm" variant="outline">
                    <Link :href="signedIn ? index() : login()">{{
                        signedIn ? 'Open notes' : 'Sign in'
                    }}</Link>
                </Button>
            </div>
        </header>
        <main class="mx-auto max-w-3xl px-4 py-8">
            <TopicViewer :topic="detail" />
        </main>
    </div>
</template>
