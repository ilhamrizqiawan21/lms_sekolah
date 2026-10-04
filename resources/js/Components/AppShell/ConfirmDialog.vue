<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import Modal from '../UI/Modal.vue';

const visible = ref(false);
type ConfirmOptions = NonNullable<Parameters<NonNullable<Window['confirmDialog']>>[1]>;
const options = ref<ConfirmOptions & { message?: string }>({});
let resolver: ((result: boolean) => void) | null = null;

function close(result: boolean) {
    visible.value = false;

    if (resolver) {
        resolver(result);
        resolver = null;
    }
}

function confirm(message: string, config: ConfirmOptions = {}): Promise<boolean> {
    if (resolver) close(false);
    options.value = {
        message,
        title: config.title || 'Konfirmasi',
        confirmText: config.confirmText || 'Ya, lanjutkan',
        cancelText: config.cancelText || 'Batal',
        danger: config.danger === true,
    };
    visible.value = true;

    return new Promise<boolean>((resolve) => {
        resolver = resolve;
    });
}

const confirmAction: NonNullable<Window['confirmAction']> = (message, callback, config = {}) => {
    confirm(message, config).then(callback);
};

onMounted(() => {
    window.confirmAction = confirmAction;
    window.confirmDialog = confirm;
});

onUnmounted(() => {
    close(false);
    if (window.confirmDialog === confirm) delete window.confirmDialog;
    if (window.confirmAction === confirmAction) delete window.confirmAction;
});
</script>

<template>
    <Modal
        :model-value="visible"
        size="sm"
        labelledby="confirmTitle"
        describedby="confirmMessage"
        footer-align="center"
        @close="close(false)"
    >
        <div class="confirm-icon" :class="options.danger ? 'danger' : 'warning'">
            <i class="bi" :class="options.danger ? 'bi-exclamation-triangle-fill' : 'bi-question-circle-fill'" aria-hidden="true"></i>
        </div>
        <h2 id="confirmTitle" class="confirm-title">{{ options.title }}</h2>
        <p id="confirmMessage" class="confirm-message">{{ options.message }}</p>
        <template #footer>
            <button type="button" class="btn btn-outline-secondary" @click="close(false)">{{ options.cancelText }}</button>
            <button type="button" class="btn" :class="options.danger ? 'btn-danger' : 'btn-primary'" @click="close(true)">
                {{ options.confirmText }}
            </button>
        </template>
    </Modal>
</template>
