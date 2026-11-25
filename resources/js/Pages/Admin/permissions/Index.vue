<script setup>
import AdminLayout from '../AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    roles: Array,
    permissions: Array,
    granted: Object,
});

const toast = useToast();
const form = useForm({ granted: props.granted });

const roleLabel = (role) => role.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

function save() {
    form.put(route('admin.permissions.update'), {
        preserveScroll: true,
        onSuccess: (res) => toast.add({ severity: 'success', summary: 'Saved', detail: res.props.flash.message, life: 3000 }),
        onError: () => toast.add({ severity: 'error', summary: 'Error', detail: 'Something went wrong, please try again.', life: 3000 }),
    });
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <DataTable :value="permissions" dataKey="value" scrollable tableStyle="min-width: 40rem" class="text-sm">
            <template #header>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">Role permissions</h2>
                    <Button icon="pi pi-check" size="small" label="Save" :loading="form.processing" @click="save" />
                </div>
            </template>

            <Column field="label" header="Permission" style="min-width: 22rem" />

            <Column header="Admin" style="width: 7rem">
                <template #body="{ data }">
                    <Checkbox :modelValue="true" binary disabled :ariaLabel="`Admin: ${data.label}`" />
                </template>
            </Column>

            <Column v-for="role in roles" :key="role" :header="roleLabel(role)" style="width: 9rem">
                <template #body="{ data }">
                    <Checkbox v-model="form.granted[role]" :value="data.value" :ariaLabel="`${roleLabel(role)}: ${data.label}`" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>
