<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Card, EmptyState } from '../../../Components/UI';
import type { ChatRoomSummary } from '../../../types';

interface Props { rooms?: ChatRoomSummary[]; emptyMessage?: string }
const props = withDefaults(defineProps<Props>(), { rooms: () => [], emptyMessage: 'Anda belum memiliki penugasan.' });

const search = ref('');
const filteredRooms = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('id-ID');
    if (!keyword) {
        return props.rooms;
    }

    return props.rooms.filter((room) => `${room.title} ${room.subtitle ?? ''}`.toLocaleLowerCase('id-ID').includes(keyword));
});
</script>

<template>
    <Head title="Chat Kelas" />

    <AppShell title="Chat Kelas">
        <PageHeader
            eyebrow="Komunikasi"
            title="Chat Kelas"
            subtitle="Pilih ruang diskusi dari kelas dan mata pelajaran yang Anda ampu."
            icon="bi-chat-dots-fill"
        />

        <div v-if="rooms.length" class="teacher-chat-workspace">
            <Card title="Pilih Kelas" icon="bi-people" class="teacher-chat-rooms">
                <template #actions>
                    <span class="teacher-chat-count">{{ rooms.length }} ruang</span>
                </template>

                <label class="teacher-chat-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <span class="visually-hidden">Cari kelas atau mata pelajaran</span>
                    <input v-model="search" type="search" class="form-control" placeholder="Cari kelas atau mapel">
                </label>

                <nav v-if="filteredRooms.length" class="teacher-chat-room-list" aria-label="Ruang chat kelas">
                    <Link
                        v-for="room in filteredRooms"
                        :key="room.id"
                        :href="room.url"
                        class="teacher-chat-room-link"
                    >
                        <span class="teacher-chat-room-mark" aria-hidden="true">{{ room.title.slice(0, 2).toUpperCase() }}</span>
                        <span class="teacher-chat-room-copy">
                            <strong>{{ room.title }} · {{ room.subtitle || 'Kelas' }}</strong>
                            <small>{{ room.subtitle || 'Ruang kelas' }}</small>
                        </span>
                        <i class="bi bi-chevron-right teacher-chat-room-arrow" aria-hidden="true"></i>
                    </Link>
                </nav>
                <EmptyState v-else title="Kelas tidak ditemukan" icon="bi-search" />
            </Card>

            <Card class="teacher-chat-preview">
                <div class="teacher-chat-placeholder">
                    <span class="teacher-chat-placeholder-icon"><i class="bi bi-chat-square-text" aria-hidden="true"></i></span>
                    <h2>Buka ruang percakapan</h2>
                    <p>Pilih kelas untuk melihat dan membalas pesan siswa.</p>
                </div>
            </Card>
        </div>

        <section v-else class="teacher-chat-workspace teacher-chat-empty-workspace" aria-label="Ruang chat kelas">
            <div class="teacher-chat-empty-list">
                <div class="teacher-chat-empty-list-heading">
                    <h2>Pilih Kelas</h2>
                    <span>0 ruang</span>
                </div>
                <p><i class="bi bi-info-circle" aria-hidden="true"></i> Ruang sesuai penugasan mengajar Anda</p>
                <div class="teacher-chat-no-rooms">{{ emptyMessage }}</div>
            </div>
            <div class="teacher-chat-placeholder">
                <span class="teacher-chat-placeholder-icon"><i class="bi bi-chat-square-text" aria-hidden="true"></i></span>
                <h2>Belum ada ruang percakapan</h2>
                <p>Ruang chat akan muncul saat Anda mendapat penugasan mengajar.</p>
            </div>
        </section>
    </AppShell>
</template>

<style scoped>
.teacher-chat-workspace {
    display: grid;
    grid-template-columns: 305px minmax(0, 1fr);
    gap: 0;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid var(--border-soft);
    border-radius: 14px;
    background: var(--surface-card);
    box-shadow: 0 16px 34px -28px rgb(24 34 48 / 24%);
}

.teacher-chat-empty-workspace { min-height: 520px; }
.teacher-chat-empty-list { border-right: 1px solid var(--border-soft); }
.teacher-chat-empty-list-heading { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 1rem; border-bottom: 1px solid var(--border-soft); }
.teacher-chat-empty-list-heading h2 { margin: 0; color: var(--text-strong); font-size: 0.9rem; font-weight: 750; }
.teacher-chat-empty-list-heading span, .teacher-chat-empty-list > p { color: var(--text-muted); font-size: 0.72rem; }
.teacher-chat-empty-list > p { margin: 0; padding: 0.75rem 1rem; }
.teacher-chat-no-rooms { padding: 1.5rem 1rem; color: var(--text-muted); font-size: 0.78rem; text-align: center; }
.teacher-chat-empty-workspace > .teacher-chat-placeholder { align-self: center; justify-self: center; }

.teacher-chat-count {
    color: var(--text-muted);
    font-size: 0.78rem;
}

.teacher-chat-search {
    position: relative;
    display: block;
    margin-bottom: 0.75rem;
}

.teacher-chat-search .bi-search {
    position: absolute;
    top: 50%;
    left: 0.85rem;
    color: var(--text-muted);
    transform: translateY(-50%);
}

.teacher-chat-search .form-control {
    padding-left: 2.35rem;
}

.teacher-chat-room-list {
    display: grid;
    max-height: min(64vh, 680px);
    overflow-y: auto;
    margin: 0 -0.5rem -0.5rem;
}

.teacher-chat-room-link {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.75rem;
    padding: 0.8rem 0.5rem;
    border-top: 1px solid var(--border-soft);
    color: var(--text-body);
    text-decoration: none;
    transition: background-color 140ms ease;
}

.teacher-chat-room-link:hover,
.teacher-chat-room-link:focus-visible {
    background: var(--surface-subtle);
    color: var(--text-strong);
}

.teacher-chat-room-mark,
.teacher-chat-placeholder-icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border: 1px solid var(--border-soft);
    border-radius: 12px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: 0.76rem;
    font-weight: 750;
}

.teacher-chat-room-copy {
    display: grid;
    min-width: 0;
    gap: 0.15rem;
}

.teacher-chat-room-copy strong {
    overflow: hidden;
    color: var(--text-strong);
    font-size: 0.88rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-chat-room-copy small {
    color: var(--text-muted);
    font-size: 0.76rem;
}

.teacher-chat-room-arrow {
    margin-left: auto;
    color: var(--text-muted);
}

.teacher-chat-preview :deep(.card-body) {
    display: grid;
    min-height: 320px;
    place-items: center;
}

.teacher-chat-placeholder {
    max-width: 24rem;
    padding: 1rem;
    text-align: center;
}

.teacher-chat-placeholder-icon {
    width: 52px;
    height: 52px;
    margin: 0 auto 0.85rem;
    font-size: 1.2rem;
}

.teacher-chat-placeholder h2 {
    color: var(--text-strong);
    font-size: 1rem;
    font-weight: 700;
}

.teacher-chat-placeholder p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.86rem;
}

@media (max-width: 767.98px) {
    .teacher-chat-workspace {
        grid-template-columns: minmax(0, 1fr);
    }

    .teacher-chat-empty-workspace > .teacher-chat-placeholder { display: none; }
    .teacher-chat-empty-list { min-height: 520px; border-right: 0; }

    .teacher-chat-preview {
        display: none;
    }

    .teacher-chat-room-list {
        max-height: none;
    }
}
</style>
