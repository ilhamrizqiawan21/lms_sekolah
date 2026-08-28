<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '../Form/InputError.vue';
import { Button, Card } from '../UI';

const props = defineProps({
    room: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
    sendUrl: { type: String, required: true },
    emptyMessage: { type: String, default: 'Belum ada pesan.' },
});

const chatArea = ref(null);
const form = useForm({
    message: '',
});

function scrollToLatest() {
    if (!chatArea.value) {
        return;
    }

    chatArea.value.scrollTop = chatArea.value.scrollHeight;
}

function sendMessage() {
    form.post(props.sendUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            nextTick(scrollToLatest);
        },
    });
}

onMounted(() => nextTick(scrollToLatest));

watch(
    () => props.messages.length,
    () => nextTick(scrollToLatest),
);
</script>

<template>
    <Card title="Chat Room" icon="bi-chat-dots-fill">
        <div ref="chatArea" class="chat-area">
            <template v-if="messages.length">
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="chat-message"
                    :class="{ 'is-mine': message.is_mine }"
                >
                    <small class="text-muted d-block">{{ message.author }}</small>
                    <div class="chat-bubble">
                        {{ message.message }}
                    </div>
                    <small class="text-muted d-block chat-time">{{ message.time }}</small>
                </div>
            </template>

            <p v-else class="text-muted text-center pt-5 mb-0">{{ emptyMessage }}</p>
        </div>

        <form class="d-flex gap-2" @submit.prevent="sendMessage">
            <div class="flex-grow-1">
                <input
                    v-model="form.message"
                    type="text"
                    name="message"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.message }"
                    placeholder="Ketik pesan..."
                    required
                    maxlength="1000"
                    autocomplete="off"
                    :disabled="form.processing"
                >
                <InputError :message="form.errors.message" />
            </div>

            <Button
                type="submit"
                color="success"
                icon="bi-send-fill"
                :disabled="form.processing"
                :title="form.processing ? 'Mengirim pesan' : 'Kirim pesan'"
                :aria-label="form.processing ? 'Mengirim pesan' : 'Kirim pesan'"
            />
        </form>
    </Card>
</template>

<style scoped>
.chat-area {
    height: 400px;
    overflow-y: auto;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: var(--surface-muted);
}

.chat-message {
    margin-bottom: 0.75rem;
}

.chat-message.is-mine {
    text-align: right;
}

.chat-bubble {
    display: inline-block;
    max-width: min(75%, 42rem);
    border: 1px solid var(--gray-200);
    border-radius: 1rem;
    background: var(--surface-card);
    padding: 0.5rem 0.75rem;
    text-align: left;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.chat-message.is-mine .chat-bubble {
    border-color: var(--success-600, #198754);
    background: var(--success-600, #198754);
    color: #fff;
}

.chat-time {
    font-size: 0.65rem;
}

:global([data-bs-theme="dark"]) .chat-area {
    border-color: var(--border-soft);
    background: var(--surface-input);
}

:global([data-bs-theme="dark"]) .chat-bubble {
    border-color: var(--border-soft);
    background: var(--surface-muted);
    color: var(--text-body);
}

:global([data-bs-theme="dark"]) .chat-message.is-mine .chat-bubble {
    border-color: color-mix(in srgb, var(--primary-500) 70%, var(--surface-card));
    background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
    color: #fff;
}
</style>
