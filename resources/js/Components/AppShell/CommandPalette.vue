<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import Modal from '../UI/Modal.vue';
import { router } from '@inertiajs/vue3';

import type { SidebarItem, SidebarMenuEntry } from '../../types/navigation';

const props = withDefaults(defineProps<{ open?: boolean; items?: SidebarMenuEntry[] }>(), {
    open: false, items: () => [],
});
const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const query = ref('');
const input = ref<HTMLInputElement | null>(null);
const activeIndex = ref(0);

const results = computed(() => {
    const term = query.value.trim().toLowerCase();
    const commands = props.items.filter((item) => item.type === 'item');
    return term ? commands.filter((item) => item.label.toLowerCase().includes(term)) : commands;
});

watch(() => props.open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    query.value = '';
    activeIndex.value = 0;
    await nextTick();
    input.value?.focus();
});

watch(results, () => {
    activeIndex.value = 0;
});

function close() {
    emit('update:open', false);
}

function visit(item: SidebarItem) {
    close();
    if (item.inertia) router.visit(item.href);
    else window.location.assign(item.href);
}

function moveActive(direction: number) {
    if (!results.value.length) return;
    activeIndex.value = (activeIndex.value + direction + results.value.length) % results.value.length;
}

function visitActive() {
    const item = results.value[activeIndex.value];
    if (item) visit(item);
}

</script>

<template>
    <Modal
        :model-value="open"
        bare
        align="top"
        dialog-class="command-palette"
        aria-label="Akses cepat"
        @update:model-value="emit('update:open', $event)"
    >
        <div class="command-palette-input">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                ref="input"
                v-model="query"
                type="search"
                placeholder="Cari menu..."
                aria-label="Cari menu"
                @keydown.down.prevent="moveActive(1)"
                @keydown.up.prevent="moveActive(-1)"
                @keydown.enter.prevent="visitActive"
            >
            <kbd>Esc</kbd>
        </div>
        <div class="command-palette-results">
            <button v-for="(item, index) in results" :key="item.href" type="button" class="command-palette-item" :class="{ 'is-active': index === activeIndex }" @mouseenter="activeIndex = index" @click="visit(item)">
                <i class="bi" :class="item.icon" aria-hidden="true"></i>
                <span>{{ item.label }}</span>
                <i class="bi bi-arrow-return-left command-palette-enter" aria-hidden="true"></i>
            </button>
            <p v-if="!results.length" class="command-palette-empty">Menu tidak ditemukan.</p>
        </div>
    </Modal>
</template>
