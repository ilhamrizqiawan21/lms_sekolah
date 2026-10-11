<script setup lang="ts">
import type { PropType } from 'vue';

import { Head, Link } from '@inertiajs/vue3';
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
        <PageHeader
            eyebrow="Pembelajaran"
            title="Materi Pembelajaran"
            subtitle="Pilih mata pelajaran untuk melihat materi."
        />

        <div v-if="kelasMapel.length" class="row g-3">
            <div v-for="item in kelasMapel" :key="item.id" class="col-sm-6 col-lg-4">
                <Link :href="item.href" class="text-decoration-none">
                    <Card class="h-100" body-class="text-center py-4">
                        <div class="materi-initials mb-2">{{ item.initials }}</div>
                        <h2 class="h6 fw-bold text-strong mb-1">{{ item.mata_pelajaran }}</h2>
                        <div class="text-body-secondary small">{{ item.guru }}</div>
                    </Card>
                </Link>
            </div>
        </div>

        <Card v-else>
            <EmptyState title="Belum ada mata pelajaran." message="Mata pelajaran yang aktif akan muncul di sini." icon="bi-book" />
        </Card>
    </AppShell>
</template>

<style scoped>
.materi-initials {
    font-size: var(--fs-2xl);
    font-weight: 700;
    color: var(--text-brand);
}
</style>
