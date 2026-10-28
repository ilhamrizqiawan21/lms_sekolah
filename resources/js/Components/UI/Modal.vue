<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

defineSlots<{ default?: () => unknown; header?: () => unknown; footer?: () => unknown }>();

interface Props {
    modelValue: boolean;
    title?: string;
    size?: 'sm' | 'md' | 'lg';
    /** Tanpa kartu dan header bawaan; slot default mengisi seluruh dialog (mis. command palette). */
    bare?: boolean;
    align?: 'center' | 'top';
    /** Esc, klik latar, dan tombol tutup menutup dialog. */
    dismissible?: boolean;
    ariaLabel?: string;
    labelledby?: string;
    describedby?: string;
    dialogClass?: string;
    footerAlign?: 'end' | 'center';
}

const props = withDefaults(defineProps<Props>(), {
    title: '', size: 'md', bare: false, align: 'center', dismissible: true,
    ariaLabel: '', labelledby: '', describedby: '', dialogClass: '', footerAlign: 'end',
});

const emit = defineEmits<{ 'update:modelValue': [open: boolean]; close: [] }>();

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// Beberapa modal bisa terbuka bersamaan (mis. konfirmasi di atas dialog lain): hanya yang teratas menangani Esc/Tab.
const stack: symbol[] = ((globalThis as { __lmsModalStack?: symbol[] }).__lmsModalStack ??= []);

const id = Symbol('modal');
const titleId = useId();
const dialog = ref<HTMLElement | null>(null);
let previousFocus: HTMLElement | null = null;
let pressedOnBackdrop = false;

const labelId = computed(() => props.labelledby || (props.title && !props.bare ? titleId : undefined));
const label = computed(() => (labelId.value ? undefined : props.ariaLabel || props.title || undefined));

function requestClose() {
    if (!props.dismissible) {
        return;
    }

    emit('update:modelValue', false);
    emit('close');
}

function focusables(): HTMLElement[] {
    return [...(dialog.value?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [])].filter((element) => element.offsetParent !== null);
}

function onKeydown(event: KeyboardEvent) {
    if (stack[stack.length - 1] !== id) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        requestClose();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const elements = focusables();

    if (!elements.length) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];

    if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function lockScroll(locked: boolean) {
    document.body.classList.toggle('modal-open', locked || stack.length > 0);
}

async function open() {
    previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    stack.push(id);
    lockScroll(true);
    document.addEventListener('keydown', onKeydown);
    await nextTick();
    const target = dialog.value?.querySelector<HTMLElement>('[data-autofocus]') ?? focusables()[0] ?? dialog.value;
    target?.focus();
}

function release() {
    const index = stack.indexOf(id);

    if (index === -1) {
        return;
    }

    stack.splice(index, 1);
    document.removeEventListener('keydown', onKeydown);
    lockScroll(false);

    if (previousFocus?.isConnected) {
        previousFocus.focus();
    }

    previousFocus = null;
}

watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        open();
    } else {
        release();
    }
}, { immediate: true });

onBeforeUnmount(release);

function onBackdropDown(event: MouseEvent) {
    pressedOnBackdrop = event.target === event.currentTarget;
}

function onBackdropClick(event: MouseEvent) {
    if (pressedOnBackdrop && event.target === event.currentTarget) {
        requestClose();
    }

    pressedOnBackdrop = false;
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="app-modal-backdrop"
            :class="{ 'app-modal-backdrop--top': align === 'top' }"
            @mousedown="onBackdropDown"
            @click="onBackdropClick"
        >
            <div
                ref="dialog"
                role="dialog"
                aria-modal="true"
                tabindex="-1"
                :aria-labelledby="labelId"
                :aria-label="label"
                :aria-describedby="describedby || undefined"
                :class="bare ? dialogClass : ['app-modal', `app-modal--${size}`, dialogClass]"
            >
                <template v-if="bare">
                    <slot />
                </template>
                <template v-else>
                    <header v-if="title || $slots.header" class="app-modal-header">
                        <slot name="header">
                            <h2 :id="titleId" class="app-modal-title">{{ title }}</h2>
                        </slot>
                        <button
                            v-if="dismissible"
                            type="button"
                            class="app-modal-close"
                            aria-label="Tutup"
                            @click="requestClose"
                        >
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </header>
                    <div class="app-modal-body">
                        <slot />
                    </div>
                    <!-- div, bukan footer: CSS lama memberi margin, border, dan warna pada elemen footer. -->
                    <div
                        v-if="$slots.footer"
                        class="app-modal-footer"
                        :class="{ 'app-modal-footer--center': footerAlign === 'center' }"
                    >
                        <slot name="footer" />
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>
