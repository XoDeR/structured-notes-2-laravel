<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ClipboardPaste,
    Copy,
    ExternalLink,
    FilePlus2,
    FolderPlus,
    Link2,
    Lock,
    Pencil,
    Scissors,
    Search,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/notes/ConfirmDialog.vue';
import NodeDialog from '@/components/notes/NodeDialog.vue';
import NodeTreeItem from '@/components/notes/NodeTreeItem.vue';
import TopicEditor from '@/components/notes/TopicEditor.vue';
import TopicViewer from '@/components/notes/TopicViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { destroy as destroyNode, move as moveNode } from '@/routes/nodes';
import { index } from '@/routes/notes';
import {
    destroy as destroyTopic,
    favorite,
    paste as pasteTopic,
} from '@/routes/topics';
import type {
    Clipboard,
    SearchState,
    TopicDetail,
    TopicSummary,
    TreeNode,
} from '@/types/notes';

type Branch = TreeNode & { children: Branch[] };

const props = defineProps<{
    tree: TreeNode[];
    nodeId: string | null;
    topics: TopicSummary[];
    topic: TopicDetail | null;
    search: SearchState | null;
    knownTags?: string[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Notes', href: index() }] } });

/* ---------- tree ---------- */

const byId = computed(() => new Map(props.tree.map((n) => [n.id, n])));

const branches = computed<Branch[]>(() => {
    const nodes = new Map<string, Branch>(
        props.tree.map((n) => [n.id, { ...n, children: [] }]),
    );
    const roots: Branch[] = [];

    for (const node of nodes.values()) {
        const parent = node.parent_id ? nodes.get(node.parent_id) : null;
        (parent ? parent.children : roots).push(node);
    }

    return roots;
});

const selectedNode = computed(() =>
    props.nodeId ? (byId.value.get(props.nodeId) ?? null) : null,
);

const expanded = ref(new Set<string>());

function expandPathTo(id: string | null): void {
    const next = new Set(expanded.value);
    let node = id ? byId.value.get(id) : undefined;

    while (node) {
        next.add(node.id);
        node = node.parent_id ? byId.value.get(node.parent_id) : undefined;
    }

    expanded.value = next;
}

function toggle(id: string): void {
    const next = new Set(expanded.value);
    if (!next.delete(id)) {
        next.add(id);
    }
    expanded.value = next;
}

watch(() => props.nodeId, expandPathTo, { immediate: true });

/* ---------- navigation ---------- */

function visit(
    query: Record<string, string | number | boolean | undefined>,
): void {
    router.get(
        index.url({ query }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function selectNode(id: string): void {
    editing.value = null;
    visit({ node: id });
}

function selectTopic(id: string, nodeId: string | null = props.nodeId): void {
    editing.value = null;
    visit({ node: nodeId ?? undefined, topic: id });
}

/* ---------- search ---------- */

const query = ref(props.search?.q ?? '');
const titleOnly = ref(props.search?.title_only ?? false);
const mode = ref<'phrase' | 'all'>(props.search?.mode ?? 'phrase');

watch(
    () => props.search,
    (search) => {
        query.value = search?.q ?? '';
        titleOnly.value = search?.title_only ?? false;
        mode.value = search?.mode ?? 'phrase';
    },
);

function runSearch(): void {
    if (!query.value.trim()) {
        return clearSearch();
    }

    editing.value = null;
    visit({
        node: props.nodeId ?? undefined,
        q: query.value.trim(),
        mode: mode.value,
        title_only: titleOnly.value ? 1 : undefined,
    });
}

function searchTag(tag: string): void {
    editing.value = null;
    visit({ node: props.nodeId ?? undefined, tag });
}

function searchFavorites(): void {
    editing.value = null;
    visit({ node: props.nodeId ?? undefined, fav: 1 });
}

function clearSearch(): void {
    visit({ node: props.nodeId ?? undefined, topic: props.topic?.id });
}

/* ---------- clipboard ---------- */

const clipboard = ref<Clipboard | null>(null);

const cutNodeId = computed(() =>
    clipboard.value?.kind === 'node' ? clipboard.value.id : null,
);

function cutNode(): void {
    if (selectedNode.value) {
        clipboard.value = {
            kind: 'node',
            id: selectedNode.value.id,
            title: selectedNode.value.title,
        };
    }
}

function cutTopic(): void {
    stash('move');
}

function copyTopic(): void {
    stash('copy');
}

function linkTopic(): void {
    stash('link');
}

function stash(mode: 'move' | 'copy' | 'link'): void {
    if (props.topic && props.nodeId) {
        clipboard.value = {
            kind: 'topic',
            id: props.topic.id,
            title: props.topic.title,
            mode,
            fromNodeId: props.nodeId,
        };
    }
}

function pasteNode(): void {
    if (clipboard.value?.kind !== 'node' || !selectedNode.value) {
        return;
    }

    router.patch(
        moveNode.url(clipboard.value.id),
        { parent_id: selectedNode.value.id },
        { preserveScroll: true, onSuccess: () => (clipboard.value = null) },
    );
}

function pasteIntoNode(): void {
    const item = clipboard.value;

    if (item?.kind !== 'topic' || !props.nodeId) {
        return;
    }

    router.post(
        pasteTopic.url(item.id),
        {
            mode: item.mode,
            node_id: props.nodeId,
            from_node_id: item.fromNodeId,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (item.mode === 'move') {
                    clipboard.value = null;
                }
            },
        },
    );
}

/* ---------- node dialog / deletion ---------- */

const nodeDialog = ref<{ open: boolean; editing: boolean }>({
    open: false,
    editing: false,
});
const confirm = ref<{ open: boolean; kind: 'node' | 'topic' }>({
    open: false,
    kind: 'node',
});

function openNodeDialog(isEdit: boolean): void {
    nodeDialog.value = { open: true, editing: isEdit };
}

function deleteSelected(): void {
    if (confirm.value.kind === 'node' && selectedNode.value) {
        router.delete(destroyNode.url(selectedNode.value.id), {
            preserveScroll: true,
        });
    } else if (confirm.value.kind === 'topic' && props.topic && props.nodeId) {
        router.delete(
            destroyTopic.url(props.topic.id, { query: { node: props.nodeId } }),
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Topic removed.'),
            },
        );
    }
}

function askDelete(kind: 'node' | 'topic'): void {
    confirm.value = { open: true, kind };
}

/* ---------- topics ---------- */

const editing = ref<'new' | 'edit' | null>(null);

function toggleFavorite(topic: TopicSummary): void {
    router.post(
        favorite.url(topic.id),
        {},
        { preserveScroll: true, preserveState: true, only: ['topics'] },
    );
}

const canAddTopic = computed(() => !!selectedNode.value);
const pasteTopicLabel = computed(() => {
    const item = clipboard.value;

    if (item?.kind !== 'topic') {
        return '';
    }

    return {
        move: 'Paste (move)',
        copy: 'Paste as copy',
        link: 'Paste as link',
    }[item.mode];
});

const topicsSelectedId = computed(() => props.topic?.id ?? null);
</script>

<template>
    <Head title="Notes" />

    <div class="flex h-[calc(100svh-4rem)] flex-col gap-3 p-4">
        <!-- search -->
        <form
            class="flex flex-wrap items-center gap-2"
            @submit.prevent="runSearch"
        >
            <div class="relative w-full max-w-sm">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    class="pl-8"
                    placeholder="Search topics"
                />
            </div>
            <Select v-model="mode">
                <SelectTrigger class="w-44"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="phrase">Exact phrase</SelectItem>
                    <SelectItem value="all">All words</SelectItem>
                </SelectContent>
            </Select>
            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    :model-value="titleOnly"
                    @update:model-value="titleOnly = $event === true"
                />
                Titles only
            </label>
            <Button type="submit" size="sm">Search</Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                title="Favorites"
                @click="searchFavorites"
            >
                <Star /> Favorites
            </Button>
            <Button
                v-if="search"
                type="button"
                size="sm"
                variant="ghost"
                @click="clearSearch"
            >
                <X /> Clear
            </Button>
        </form>

        <div
            class="grid min-h-0 flex-1 gap-3 lg:grid-cols-[18rem_20rem_minmax(0,1fr)]"
        >
            <!-- tree -->
            <section class="flex min-h-0 flex-col rounded-lg border">
                <div class="flex items-center gap-1 border-b p-1.5">
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8"
                        title="New node"
                        :disabled="!selectedNode"
                        @click="openNodeDialog(false)"
                        ><FolderPlus
                    /></Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8"
                        title="Edit node"
                        :disabled="!selectedNode?.can_edit"
                        @click="openNodeDialog(true)"
                        ><Pencil
                    /></Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8"
                        title="Cut node"
                        :disabled="!selectedNode?.can_edit"
                        @click="cutNode"
                        ><Scissors
                    /></Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8"
                        title="Paste node here"
                        :disabled="clipboard?.kind !== 'node' || !selectedNode"
                        @click="pasteNode"
                        ><ClipboardPaste
                    /></Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 text-destructive"
                        title="Delete node"
                        :disabled="!selectedNode?.can_edit"
                        @click="askDelete('node')"
                        ><Trash2
                    /></Button>
                </div>
                <ScrollArea class="min-h-0 flex-1">
                    <ul role="tree" class="p-1.5">
                        <NodeTreeItem
                            v-for="branch in branches"
                            :key="branch.id"
                            :node="branch"
                            :selected-id="nodeId"
                            :expanded="expanded"
                            :cut-id="cutNodeId"
                            @select="selectNode"
                            @toggle="toggle"
                        />
                    </ul>
                </ScrollArea>
            </section>

            <!-- topics / search results -->
            <section class="flex min-h-0 flex-col rounded-lg border">
                <template v-if="search">
                    <div
                        class="flex items-center gap-2 border-b px-3 py-2 text-sm"
                    >
                        <Search class="size-4 text-muted-foreground" />
                        <span class="truncate">
                            <template v-if="search.fav">Favorites</template>
                            <template v-else-if="search.tag"
                                >Tag: {{ search.tag }}</template
                            >
                            <template v-else>“{{ search.q }}”</template>
                        </span>
                        <Badge variant="secondary" class="ml-auto">{{
                            search.results.length
                        }}</Badge>
                    </div>
                    <ScrollArea class="min-h-0 flex-1">
                        <p
                            v-if="!search.results.length"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            Nothing found.
                        </p>
                        <ul class="p-1.5">
                            <li
                                v-for="r in search.results"
                                :key="r.topic_id + r.node_id"
                            >
                                <button
                                    type="button"
                                    :class="
                                        cn(
                                            'w-full rounded-md px-2 py-1.5 text-left hover:bg-accent',
                                            r.topic_id === topicsSelectedId &&
                                                'bg-accent',
                                        )
                                    "
                                    @click="selectTopic(r.topic_id, r.node_id)"
                                >
                                    <div class="truncate text-sm font-medium">
                                        {{ r.title }}
                                    </div>
                                    <div
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ r.path }}
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </ScrollArea>
                </template>

                <template v-else>
                    <div
                        class="flex flex-wrap items-center gap-1 border-b p-1.5"
                    >
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            title="New topic"
                            :disabled="!canAddTopic"
                            @click="editing = 'new'"
                            ><FilePlus2
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            title="Edit topic"
                            :disabled="!topic?.can_edit"
                            @click="editing = 'edit'"
                            ><Pencil
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            title="Cut topic"
                            :disabled="!topic?.can_edit"
                            @click="cutTopic"
                            ><Scissors
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            title="Copy topic (paste as a copy)"
                            :disabled="!topic || topic.is_link"
                            @click="copyTopic"
                            ><Copy
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            title="Copy as link (paste as a link)"
                            :disabled="!topic || topic.is_link"
                            @click="linkTopic"
                            ><Link2
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            :title="pasteTopicLabel || 'Paste'"
                            :disabled="
                                clipboard?.kind !== 'topic' || !selectedNode
                            "
                            @click="pasteIntoNode"
                            ><ClipboardPaste
                        /></Button>
                        <Button
                            v-if="topic?.share_url && topic.can_edit"
                            as-child
                            size="icon"
                            variant="ghost"
                            class="size-8"
                            title="Open public page"
                        >
                            <a
                                :href="topic.share_url"
                                target="_blank"
                                rel="noopener"
                                ><ExternalLink
                            /></a>
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8 text-destructive"
                            title="Delete topic"
                            :disabled="
                                !topic || (!topic.can_edit && !topic.is_link)
                            "
                            @click="askDelete('topic')"
                            ><Trash2
                        /></Button>
                    </div>
                    <ScrollArea class="min-h-0 flex-1">
                        <p
                            v-if="!topics.length"
                            class="p-4 text-sm text-muted-foreground"
                        >
                            No topics in this node.
                        </p>
                        <ul class="p-1.5">
                            <li
                                v-for="t in topics"
                                :key="t.id"
                                class="group flex items-center"
                            >
                                <button
                                    type="button"
                                    :class="
                                        cn(
                                            'flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent',
                                            t.id === topicsSelectedId &&
                                                'bg-accent font-medium',
                                        )
                                    "
                                    @click="selectTopic(t.id)"
                                >
                                    <Link2
                                        v-if="t.is_link"
                                        class="size-3.5 shrink-0 text-muted-foreground"
                                    />
                                    <Lock
                                        v-else-if="!t.is_public"
                                        class="size-3.5 shrink-0 text-muted-foreground"
                                    />
                                    <span class="truncate">{{ t.title }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="p-1.5 text-muted-foreground hover:text-foreground"
                                    :aria-label="
                                        t.is_favorite
                                            ? 'Remove from favorites'
                                            : 'Add to favorites'
                                    "
                                    @click="toggleFavorite(t)"
                                >
                                    <Star
                                        class="size-4"
                                        :class="
                                            t.is_favorite &&
                                            'fill-amber-400 text-amber-400'
                                        "
                                    />
                                </button>
                            </li>
                        </ul>
                    </ScrollArea>
                </template>
            </section>

            <!-- viewer / editor -->
            <section class="min-h-0 overflow-y-auto rounded-lg border p-4">
                <TopicEditor
                    v-if="editing === 'new' && selectedNode"
                    :key="`new-${selectedNode.id}`"
                    :topic="null"
                    :node-id="selectedNode.id"
                    :node-is-public="selectedNode.is_public"
                    :known-tags="knownTags ?? []"
                    @close="editing = null"
                />
                <TopicEditor
                    v-else-if="editing === 'edit' && topic && selectedNode"
                    :key="`edit-${topic.id}`"
                    :topic="topic"
                    :node-id="selectedNode.id"
                    :node-is-public="selectedNode.is_public"
                    :known-tags="knownTags ?? []"
                    @close="editing = null"
                />
                <TopicViewer
                    v-else-if="topic"
                    :topic="topic"
                    @tag="searchTag"
                />
                <p v-else class="text-sm text-muted-foreground">
                    Select a topic to read it, or create a new one in the
                    selected node.
                </p>
            </section>
        </div>
    </div>

    <NodeDialog
        v-model:open="nodeDialog.open"
        :node="nodeDialog.editing ? selectedNode : null"
        :parent-id="selectedNode?.id ?? null"
        :parent-is-public="selectedNode?.is_public ?? false"
    />

    <ConfirmDialog
        v-model:open="confirm.open"
        :title="
            confirm.kind === 'node'
                ? 'Delete node?'
                : topic?.is_link
                  ? 'Remove link?'
                  : 'Delete topic?'
        "
        :description="
            confirm.kind === 'node'
                ? 'The node, everything below it and the topics stored in it will be deleted. Linked topics are only unlinked.'
                : topic?.is_link
                  ? 'The topic stays where it originally lives; only this link is removed.'
                  : 'The topic and its files will be deleted permanently.'
        "
        @confirm="deleteSelected"
    />
</template>
