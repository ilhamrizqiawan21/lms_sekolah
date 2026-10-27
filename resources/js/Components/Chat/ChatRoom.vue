<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '../Form/InputError.vue';
import { Button, Card } from '../UI';
import type { ChatMessage, ChatRoomPayload } from '../../types';

interface Props { room: ChatRoomPayload; messages?: ChatMessage[]; sendUrl: string; emptyMessage?: string; showBackButton?: boolean }
const props = withDefaults(defineProps<Props>(), { messages: () => [], emptyMessage: 'Belum ada pesan.', showBackButton: false });
const emit = defineEmits<{ back: [] }>();

const chatArea = ref<HTMLElement | null>(null);
let pollTimer: ReturnType<typeof setInterval> | null = null;
const form = useForm<{ message: string }>({
    message: '',
});

function isNearBottom(): boolean {
    if (!chatArea.value) {
        return true;
    }

    const { scrollHeight, scrollTop, clientHeight } = chatArea.value;
    return scrollHeight - scrollTop - clientHeight < 120;
}

function scrollToLatest(): void {
    if (!chatArea.value) {
        return;
    }

    chatArea.value.scrollTop = chatArea.value.scrollHeight;
}

function sendMessage(): void {
    form.post(props.sendUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            nextTick(scrollToLatest);
        },
    });
}

function startPolling(): void {
    if (pollTimer) return;
    pollTimer = setInterval(() => {
        // Jangan reload jika dokumen sedang tersembunyi (tab tidak aktif) atau form sedang submit
        if (document.hidden || form.processing) {
            return;
        }

        router.reload({
            only: ['messages'],
        });
    }, 4000);
}

function stopPolling(): void {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function handleVisibilityChange(): void {
    if (document.hidden) {
        stopPolling();
    } else {
        // Saat tab aktif kembali, langsung refresh pesan dan jalankan polling jika tidak sedang submit
        if (!form.processing) {
            router.reload({
                only: ['messages'],
            });
        }
        startPolling();
    }
}

onMounted(() => {
    nextTick(scrollToLatest);
    startPolling();
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

watch(
    () => props.messages.length,
    () => {
        const shouldScroll = isNearBottom();
        nextTick(() => {
            if (shouldScroll) {
                scrollToLatest();
            }
        });
    },
);
</script>

<template>
    <Card class="chat-conversation" body-class="chat-conversation-body">
        <header class="chat-room-heading">
            <button
                v-if="showBackButton"
                type="button"
                class="chat-back-button"
                aria-label="Kembali ke daftar kelas"
                @click="emit('back')"
            >
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
            </button>
            <span class="chat-room-mark" aria-hidden="true">
                {{ (room.subtitle || room.title || 'K').slice(0, 2).toUpperCase() }}
            </span>
            <div class="chat-room-copy">
                <strong>{{ room.title || 'Ruang kelas' }} · {{ room.subtitle || 'Kelas' }}</strong>
                <small><span class="online-indicator" aria-hidden="true"></span>Ruang diskusi kelas</small>
            </div>
        </header>

        <div ref="chatArea" class="chat-area" role="log" aria-live="polite" aria-label="Pesan dalam ruang kelas">
            <template v-if="messages.length">
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="chat-message"
                    :class="{ 'is-mine': message.is_mine }"
                >
                    <img
                        v-if="message.avatar_url"
                        :src="message.avatar_url"
                        :alt="`Foto ${message.author}`"
                        class="chat-avatar"
                        width="34"
                        height="34"
                        decoding="async"
                    >
                    <div v-else class="chat-avatar chat-avatar-empty" aria-hidden="true">
                        {{ message.author.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase() }}
                    </div>
                    <div class="chat-message-body">
                        <small class="chat-author">{{ message.is_mine ? 'Anda' : message.author }}</small>
                        <div class="chat-bubble">
                            {{ message.message }}
                        </div>
                        <small class="chat-time">{{ message.time }}</small>
                    </div>
                </div>
            </template>

            <div v-else class="chat-empty-state">
                <span class="chat-empty-icon"><i class="bi bi-chat-square-text" aria-hidden="true"></i></span>
                <p>{{ emptyMessage }}</p>
            </div>
        </div>

        <form class="chat-composer" @submit.prevent="sendMessage">
            <div class="chat-composer-field">
                <label class="visually-hidden" for="class-chat-message">Tulis pesan</label>
                <input
                    id="class-chat-message"
                    v-model="form.message"
                    type="text"
                    name="message"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.message }"
                    placeholder="Ketik pesan untuk kelas ini..."
                    required
                    maxlength="1000"
                    autocomplete="off"
                    :disabled="form.processing"
                >
                <InputError :message="form.errors.message" />
            </div>

            <Button
                type="submit"
                color="primary"
                icon="bi-send-fill"
                :disabled="form.processing"
                :title="form.processing ? 'Mengirim pesan' : 'Kirim pesan'"
                :aria-label="form.processing ? 'Mengirim pesan' : 'Kirim pesan'"
            />
        </form>
        <p class="chat-composer-hint">Enter untuk mengirim · Maksimal 1.000 karakter</p>
    </Card>
</template>

<style scoped>
.chat-conversation {
    overflow: hidden;
    border-radius: var(--card-radius);
}

:deep(.chat-conversation-body) {
    display: flex;
    min-height: 0;
    flex-direction: column;
    padding: 0;
}

.chat-room-heading {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 76px;
    padding: 0.8rem 1.1rem;
    border-bottom: 1px solid var(--border-soft);
    background: var(--surface-card);
}

.chat-back-button {
    display: none;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    place-items: center;
    border: 1px solid var(--border-soft);
    border-radius: 9px;
    background: var(--surface-card);
    color: var(--text-body);
}

.chat-room-mark,
.chat-empty-icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--app-primary) 16%, var(--border-soft));
    border-radius: 13px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: 0.78rem;
    font-weight: 750;
}

.chat-room-copy { display: grid; min-width: 0; gap: 0.2rem; }
.chat-room-copy strong { overflow: hidden; color: var(--text-strong); font-size: 0.9rem; text-overflow: ellipsis; white-space: nowrap; }
.chat-room-copy small { display: flex; align-items: center; color: var(--text-muted); font-size: 0.72rem; }
.online-indicator {
    display: inline-block;
    width: 7px;
    height: 7px;
    margin-right: 6px;
    border-radius: 50%;
    background: var(--status-success-text, #16804a);
}
.chat-area {
    display: flex;
    min-height: 350px;
    max-height: min(58vh, 620px);
    flex: 1 1 auto;
    flex-direction: column;
    overflow-y: auto;
    padding: 1.25rem clamp(1rem, 3vw, 2.35rem);
    background: radial-gradient(ellipse at top, var(--surface-card), var(--surface-subtle) 72%);
    scroll-behavior: smooth;
}

.chat-message {
    margin-bottom: 1rem;
    display: flex;
    max-width: min(78%, 600px);
    align-items: flex-end;
    gap: 0.55rem;
}

.chat-message.is-mine {
    align-self: flex-end;
    flex-direction: row-reverse;
    text-align: right;
}

.chat-avatar {
    width: 27px;
    height: 27px;
    flex: 0 0 27px;
    border-radius: 999px;
    object-fit: cover;
}

.chat-avatar-empty {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-soft);
    background: var(--surface-muted);
    color: var(--text-muted);
    font-size: 0.62rem;
    font-weight: 800;
}

.chat-message-body {
    min-width: 0;
}

.chat-author { display: block; margin: 0 0 0.3rem 0.15rem; color: var(--text-muted); font-size: 0.68rem; }

.chat-bubble {
    display: inline-block;
    max-width: 100%;
    border: 1px solid var(--border-soft);
    border-radius: 0.9rem 0.9rem 0.9rem 0.3rem;
    background: var(--surface-card);
    padding: 0.55rem 0.75rem;
    color: var(--text-body);
    font-size: 0.78rem;
    line-height: 1.6;
    text-align: left;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.chat-message.is-mine .chat-bubble {
    border-radius: 0.9rem 0.9rem 0.3rem 0.9rem;
    border-color: color-mix(in srgb, var(--app-primary) 18%, var(--border-soft));
    background: var(--brand-soft-bg);
    color: var(--text-strong);
}

.chat-time {
    display: block;
    margin-top: 0.28rem;
    color: var(--text-muted);
    font-size: 0.65rem;
}

.chat-message.is-mine .chat-time { text-align: right; }

.chat-empty-state {
    display: grid;
    min-height: 280px;
    flex: 1;
    align-content: center;
    justify-items: center;
    gap: 0.75rem;
    color: var(--text-muted);
    text-align: center;
}

.chat-empty-icon { width: 52px; height: 52px; font-size: 1.1rem; }
.chat-empty-state p { max-width: 34ch; margin: 0; }

.chat-composer {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.9rem 1rem 0.4rem;
    border-top: 1px solid var(--border-soft);
    background: var(--surface-card);
}

.chat-composer-field { min-width: 0; flex: 1 1 auto; }
.chat-composer .form-control { min-height: 44px; }
.chat-composer :deep(.btn) { min-width: 44px; min-height: 44px; }
.chat-composer-hint { margin: 0; padding: 0 1rem 0.85rem; color: var(--text-muted); font-size: 0.68rem; }

@media (max-width: 575.98px) {
    .chat-area { min-height: 300px; max-height: 58vh; padding: 1rem 0.8rem; }
    .chat-message { max-width: 90%; }
    .chat-back-button { display: grid; }
    .chat-composer { padding-inline: 0.75rem; }
    .chat-composer-hint { padding-inline: 0.75rem; }
}

@media (min-width: 576px) and (max-width: 680px) {
    .chat-back-button { display: grid; }
}
</style>
