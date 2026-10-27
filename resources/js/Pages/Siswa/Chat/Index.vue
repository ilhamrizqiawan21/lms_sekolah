<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import type { ChatRoomSummary } from '../../../types';
import { Card, EmptyState } from '../../../Components/UI';

interface Props { rooms?: ChatRoomSummary[]; emptyMessage?: string }
withDefaults(defineProps<Props>(), { rooms: () => [], emptyMessage: 'Belum ada mata pelajaran.' });
</script>

<template>
    <Head title="Chat Kelas" />

    <AppShell title="Chat Kelas">
        <PageHeader title="Chat Kelas" subtitle="Ruang diskusi mata pelajaran kelas Anda." />

        <Card v-if="rooms.length" title="Ruang Chat" icon="bi-chat-dots">
            <div class="student-chat-grid">
                <Link v-for="room in rooms" :key="room.id" :href="room.url" class="student-chat-room">
                    <span class="student-chat-icon" aria-hidden="true"><i class="bi bi-chat-square-text-fill" /></span>
                    <span class="student-chat-copy">
                        <strong>{{ room.title }}</strong>
                        <small v-if="room.subtitle">{{ room.subtitle }}</small>
                        <small v-else>Ruang diskusi kelas</small>
                    </span>
                    <span class="student-chat-action">Buka chat <i class="bi bi-arrow-right" aria-hidden="true" /></span>
                </Link>
            </div>
        </Card>
        <EmptyState v-else :title="emptyMessage" icon="bi-chat-dots" />
    </AppShell>
</template>

<style scoped>
.student-chat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr));
    gap: 0.75rem;
}

.student-chat-room {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.7rem 0.8rem;
    min-width: 0;
    padding: 0.9rem;
    border: 1px solid var(--border-soft);
    border-radius: var(--radius-lg);
    background: var(--surface-card);
    color: var(--text-body);
    text-decoration: none;
    transition: border-color 140ms ease, background-color 140ms ease, transform 140ms ease;
}

.student-chat-room:hover {
    transform: translateY(-1px);
    border-color: var(--primary-300);
    background: var(--surface-subtle);
    color: var(--text-strong);
}

.student-chat-room:focus-visible {
    outline: 2px solid var(--primary-500);
    outline-offset: 2px;
}

.student-chat-icon {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 12px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: 1.05rem;
}

.student-chat-copy {
    display: grid;
    min-width: 0;
    gap: 0.15rem;
}

.student-chat-copy strong,
.student-chat-copy small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.student-chat-copy strong {
    color: var(--text-strong);
    font-size: 0.9rem;
}

.student-chat-copy small {
    color: var(--text-muted);
    font-size: 0.76rem;
}

.student-chat-action {
    grid-column: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    color: var(--text-brand);
    font-size: 0.77rem;
    font-weight: 700;
}
</style>
