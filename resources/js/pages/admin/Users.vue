<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/notes/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy, index, store, update } from '@/routes/admin/users';

type ManagedUser = {
    id: string;
    name: string;
    email: string;
    role: 'user' | 'admin';
    disabled: boolean;
};

defineProps<{ users: ManagedUser[] }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Users', href: index() }] } });

const currentUserId = usePage().props.auth.user.id;

const dialogOpen = ref(false);
const editingId = ref<string | null>(null);
const toDelete = ref<ManagedUser | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user' as 'user' | 'admin',
    disabled: false,
});

function openCreate(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(user: ManagedUser): void {
    editingId.value = user.id;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.password_confirmation = '';
    form.role = user.role;
    form.disabled = user.disabled;
    dialogOpen.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editingId.value) {
        form.patch(update.url(editingId.value), options);
    } else {
        form.post(store.url(), options);
    }
}

function remove(): void {
    if (toDelete.value) {
        router.delete(destroy.url(toDelete.value.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Users"
                description="Create accounts and decide who can sign in."
            />
            <Button @click="openCreate"><Plus /> New user</Button>
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead class="w-24" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="user in users"
                        :key="user.id"
                        :class="{ 'text-muted-foreground': user.disabled }"
                    >
                        <TableCell class="font-medium">
                            {{ user.name }}
                            <Badge
                                v-if="user.disabled"
                                variant="outline"
                                class="ml-2"
                                >Disabled</Badge
                            >
                        </TableCell>
                        <TableCell>{{ user.email }}</TableCell>
                        <TableCell
                            ><Badge
                                :variant="
                                    user.role === 'admin'
                                        ? 'default'
                                        : 'secondary'
                                "
                                >{{ user.role }}</Badge
                            ></TableCell
                        >
                        <TableCell class="text-right">
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                title="Edit"
                                @click="openEdit(user)"
                                ><Pencil
                            /></Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="size-8 text-destructive"
                                title="Delete"
                                :disabled="user.id === currentUserId"
                                @click="toDelete = user"
                            >
                                <Trash2 />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        editingId ? 'Edit user' : 'New user'
                    }}</DialogTitle>
                    <DialogDescription v-if="editingId"
                        >Leave the password empty to keep the current
                        one.</DialogDescription
                    >
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="user-name">Name</Label>
                    <Input id="user-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="user-email">Email</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="user-password">Password</Label>
                    <Input
                        id="user-password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :required="!editingId"
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="user-confirm">Confirm password</Label>
                    <Input
                        id="user-confirm"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Role</Label>
                    <Select v-model="form.role">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="user">User</SelectItem>
                            <SelectItem value="admin">Admin</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.role" />
                </div>
                <div
                    v-if="editingId"
                    class="flex items-center justify-between rounded-md border p-3"
                >
                    <Label for="user-disabled">Disabled (cannot sign in)</Label>
                    <Switch
                        id="user-disabled"
                        :model-value="form.disabled"
                        @update:model-value="form.disabled = $event"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="!!toDelete"
        title="Delete user?"
        :description="`${toDelete?.name ?? ''} and their private topics will be deleted. Their nodes are handed over to you.`"
        @update:open="(open) => !open && (toDelete = null)"
        @confirm="remove"
    />
</template>
