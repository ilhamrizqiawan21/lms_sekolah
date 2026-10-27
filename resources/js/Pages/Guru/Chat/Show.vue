<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import ChatRoom from '../../../Components/Chat/ChatRoom.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import type { ChatMessage, ChatRoomPayload, ChatRoomSummary } from '../../../types';

interface Room extends ChatRoomPayload { title: string; subtitle?: string }
interface Props { room: Room; rooms?: ChatRoomSummary[]; messages?: ChatMessage[]; sendUrl: string; backUrl: string; emptyMessage?: string }
const props = withDefaults(defineProps<Props>(), {
    rooms: () => [],
    messages: () => [],
    emptyMessage: 'Belum ada pesan. Mulai percakapan!',
});
const page = usePage();
const search = ref('');
const mobileRoomOpen = ref(true);
const roomList = computed<ChatRoomSummary[]>(() => props.rooms.length ? props.rooms : [{
    id: props.room.id ?? 'current',
    title: props.room.title,
    subtitle: props.room.subtitle,
    url: page.url,
}]);
const filteredRooms = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase('id-ID');
    return keyword
        ? roomList.value.filter((item) => (item.title + ' ' + (item.subtitle ?? '')).toLocaleLowerCase('id-ID').includes(keyword))
        : roomList.value;
});
const selectedRoom = computed(() => roomList.value.find((item) =>
    (props.room.id !== undefined && String(item.id) === String(props.room.id)) || item.url === page.url,
));
function roomMark(item: ChatRoomSummary): string {
    return (item.subtitle || item.title).trim().split(/\s+/)[0]?.slice(0, 3).toUpperCase() || 'K';
}
</script>

<template>
    <Head title="Chat Kelas" />
    <AppShell title="Chat Kelas">
        <PageHeader
            eyebrow="Komunikasi"
            title="Chat Kelas"
            subtitle="Pilih ruang diskusi dari kelas dan mata pelajaran yang Anda ampu."
        />
        <section class="teacher-chat-workspace" :class="{ 'mobile-room-open': mobileRoomOpen }" aria-label="Ruang chat kelas">
            <aside class="teacher-chat-rooms" aria-label="Daftar kelas chat">
                <header class="teacher-chat-rooms-head">
                    <div class="teacher-chat-rooms-title">
                        <h2>Pilih Kelas</h2>
                        <span>{{ roomList.length }} ruang</span>
                    </div>
                    <label class="teacher-chat-search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <span class="visually-hidden">Cari kelas atau mata pelajaran</span>
                        <input v-model="search" type="search" class="form-control" placeholder="Cari kelas atau mapel">
                    </label>
                    <p class="teacher-chat-hint"><i class="bi bi-info-circle" aria-hidden="true"></i> Ruang sesuai penugasan mengajar Anda</p>
                </header>
                <nav v-if="filteredRooms.length" class="teacher-chat-room-list" aria-label="Pilih ruang chat">
                    <Link
                        v-for="item in filteredRooms"
                        :key="item.id"
                        :href="item.url"
                        class="teacher-chat-room-link"
                        :class="{ 'is-active': selectedRoom?.id === item.id }"
                        :aria-current="selectedRoom?.id === item.id ? 'page' : undefined"
                        @click="mobileRoomOpen = true"
                    >
                        <span class="teacher-chat-room-mark" aria-hidden="true">{{ roomMark(item) }}</span>
                        <span class="teacher-chat-room-copy">
                            <strong>{{ item.title }} · {{ item.subtitle || 'Kelas' }}</strong>
                            <small>{{ item.subtitle || 'Ruang kelas' }} · Penugasan Anda</small>
                        </span>
                    </Link>
                </nav>
                <p v-else class="teacher-chat-room-empty">Tidak ada kelas yang cocok.</p>
            </aside>
            <div class="teacher-chat-conversation">
                <ChatRoom
                    :room="room"
                    :messages="messages"
                    :send-url="sendUrl"
                    :empty-message="emptyMessage"
                    show-back-button
                    @back="mobileRoomOpen = false"
                />
            </div>
        </section>
    </AppShell>
</template>

<style scoped>
.teacher-chat-workspace {
    display: grid;
    grid-template-columns: 305px minmax(0, 1fr);
    height: clamp(620px, calc(100dvh - 225px), 820px);
    min-height: 620px;
    overflow: hidden;
    border: 1px solid var(--border-soft);
    border-radius: 14px;
    background: var(--surface-card);
    box-shadow: 0 16px 34px -28px rgb(24 34 48 / 24%);
}
.teacher-chat-rooms { display: flex; min-width: 0; flex-direction: column; border-right: 1px solid var(--border-soft); }
.teacher-chat-rooms-head { padding: 1rem; border-bottom: 1px solid var(--border-soft); }
.teacher-chat-rooms-title { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.teacher-chat-rooms-title h2 { margin: 0; color: var(--text-strong); font-size: 0.9rem; font-weight: 750; }
.teacher-chat-rooms-title span, .teacher-chat-hint { color: var(--text-muted); font-size: 0.72rem; }
.teacher-chat-search { position: relative; display: block; margin-top: 0.85rem; }
.teacher-chat-search .bi { position: absolute; top: 50%; left: 0.72rem; color: var(--text-muted); transform: translateY(-50%); }
.teacher-chat-search .form-control { min-height: 36px; padding-left: 2rem; font-size: 0.78rem; }
.teacher-chat-hint { margin: 0.55rem 0 0; }
.teacher-chat-hint .bi { margin-right: 0.2rem; }
.teacher-chat-room-list { min-height: 0; flex: 1; overflow-y: auto; }
.teacher-chat-room-link {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.7rem;
    padding: 0.82rem 0.9rem;
    border-bottom: 1px solid var(--border-soft);
    color: var(--text-body);
    text-decoration: none;
}
.teacher-chat-room-link:hover, .teacher-chat-room-link:focus-visible { background: var(--surface-subtle); }
.teacher-chat-room-link.is-active { background: var(--brand-soft-bg); }
.teacher-chat-room-mark {
    display: grid;
    width: 39px;
    height: 39px;
    flex: 0 0 39px;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--app-primary) 16%, var(--border-soft));
    border-radius: 12px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: 0.75rem;
    font-weight: 800;
}
.teacher-chat-room-copy { display: grid; min-width: 0; gap: 0.2rem; }
.teacher-chat-room-copy strong, .teacher-chat-room-copy small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.teacher-chat-room-copy strong { color: var(--text-strong); font-size: 0.77rem; }
.teacher-chat-room-copy small { color: var(--text-muted); font-size: 0.69rem; }
.teacher-chat-room-empty { padding: 1.5rem 1rem; color: var(--text-muted); font-size: 0.78rem; text-align: center; }
.teacher-chat-conversation { min-width: 0; min-height: 0; }
.teacher-chat-conversation :deep(.chat-conversation) {
    display: flex;
    height: 100%;
    flex-direction: column;
    border: 0;
    border-radius: 0;
    box-shadow: none;
}
.teacher-chat-conversation :deep(.chat-conversation-body) { min-height: 0; flex: 1; }
.teacher-chat-conversation :deep(.chat-area) { min-height: 0; max-height: none; }
@media (max-width: 1180px) {
    .teacher-chat-workspace { grid-template-columns: 275px minmax(0, 1fr); }
}
@media (max-width: 680px) {
    .teacher-chat-workspace {
        display: block;
        height: auto;
        min-height: 0;
        overflow: visible;
        border: 0;
        background: transparent;
        box-shadow: none;
    }
    .teacher-chat-rooms, .teacher-chat-conversation {
        overflow: hidden;
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        background: var(--surface-card);
        box-shadow: 0 16px 34px -28px rgb(24 34 48 / 24%);
    }
    .teacher-chat-rooms { min-height: 520px; }
    .teacher-chat-conversation { display: none; height: calc(100dvh - 145px); min-height: 540px; }
    .teacher-chat-workspace.mobile-room-open .teacher-chat-rooms { display: none; }
    .teacher-chat-workspace.mobile-room-open .teacher-chat-conversation { display: block; }
}
</style>
