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
                    <Card class="h-100 materi-card" body-class="materi-card-body">
                        <div class="materi-card-heading">
                            <span class="materi-initials" aria-hidden="true">{{ item.initials }}</span>
                            <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                        </div>
                        <span class="materi-card-label">Mata pelajaran</span>
                        <h2 class="materi-card-title">{{ item.mata_pelajaran }}</h2>
                        <div class="materi-card-teacher">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span>{{ item.guru }}</span>
                        </div>
                        <span class="materi-card-action">Lihat materi <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
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
    display: grid;
    width: 48px;
    height: 48px;
    place-items: center;
    border: 1px solid var(--border-soft);
    border-radius: 14px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: 1rem;
    font-weight: 750;
}

.materi-card :deep(.card-body) {
    display: flex;
    flex-direction: column;
    min-height: 190px;
}

.materi-card-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.materi-card-heading > .bi {
    padding: 0.45rem;
    border-radius: 9px;
    background: var(--surface-subtle);
    color: var(--text-muted);
}

.materi-card-label {
    margin-top: 1rem;
    color: var(--text-muted);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.materi-card-title {
    margin: 0.25rem 0 0;
    color: var(--text-strong);
    font-size: 1rem;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.materi-card-teacher {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.55rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.materi-card-action {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 1rem;
    color: var(--text-brand);
    font-size: 0.8rem;
    font-weight: 700;
}
</style>
