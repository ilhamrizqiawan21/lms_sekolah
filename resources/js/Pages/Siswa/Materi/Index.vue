<script setup lang="ts">
import type { PropType } from 'vue';

import { Head } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Card, EmptyState } from '../../../Components/UI';

defineProps({
    kelasMapel: { type: Array as PropType<{ id: number; href: string; initials: string; mata_pelajaran: string; guru: string }[]>, default: () => [] },
});
</script>

<template>
    <Head title="Materi Saya" />

    <AppShell title="Materi Saya">
        <PageHeader title="Materi Saya" icon="bi-file-earmark-text-fill" />

        <Card v-if="kelasMapel.length" title="Pilih Mata Pelajaran" icon="bi-book">
            <div class="row">
                <div v-for="item in kelasMapel" :key="item.id" class="col-md-4 mb-3">
                    <a :href="item.href" class="text-decoration-none">
                        <Card class="h-100 hover-shadow" body-class="text-center">
                            <div class="materi-initials">{{ item.initials }}</div>
                            <strong>{{ item.mata_pelajaran }}</strong>
                            <div class="text-body-secondary small">{{ item.guru }}</div>
                        </Card>
                    </a>
                </div>
            </div>
        </Card>

        <Card v-else>
            <EmptyState title="Belum ada mata pelajaran." icon="bi-book" />
        </Card>
    </AppShell>
</template>

<style scoped>
.materi-initials { font-size: var(--fs-2xl); color: var(--text-brand); }
</style>
