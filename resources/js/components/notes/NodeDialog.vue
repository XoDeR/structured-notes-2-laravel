<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { store, update } from '@/routes/nodes';

const props = defineProps<{
    open: boolean;
    /** Existing node being edited, or null when creating a child of `parentId`. */
    node: { id: string; title: string; is_public: boolean } | null;
    parentId: string | null;
    parentIsPublic: boolean;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({ parent_id: '', title: '', is_public: false });

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.parent_id = props.parentId ?? '';
        form.title = props.node?.title ?? '';
        form.is_public = props.node?.is_public ?? false;
    },
);

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.node) {
        form.patch(update.url(props.node.id), options);
    } else {
        form.post(store.url(), options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        node ? 'Edit node' : 'New node'
                    }}</DialogTitle>
                    <DialogDescription>
                        Nodes group topics and form the tree on the left.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="node-title">Title</Label>
                    <Input
                        id="node-title"
                        v-model="form.title"
                        maxlength="255"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div
                    class="flex items-center justify-between gap-4 rounded-md border p-3"
                >
                    <div class="space-y-0.5">
                        <Label for="node-public">Public</Label>
                        <p class="text-xs text-muted-foreground">
                            Everyone can see a public node. Private nodes are
                            visible only to you.
                        </p>
                    </div>
                    <Switch
                        id="node-public"
                        :model-value="form.is_public"
                        :disabled="!node && !parentIsPublic"
                        @update:model-value="form.is_public = $event"
                    />
                </div>
                <InputError
                    :message="form.errors.is_public ?? form.errors.parent_id"
                />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
