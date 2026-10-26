<script setup lang="ts">
defineSlots<{ default?: () => unknown }>();
import { Link } from '@inertiajs/vue3';
import type { Method } from '@inertiajs/core';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    type?: 'button' | 'submit' | 'reset';
    color?: string;
    size?: string;
    icon?: string;
    href?: string;
    method?: Method;
    as?: string;
    /** Menampilkan spinner dan menonaktifkan tombol (hanya untuk tombol, bukan tautan). */
    loading?: boolean;
}

withDefaults(defineProps<Props>(), {
    type: 'button', color: 'primary', size: 'sm', icon: '', href: '', method: 'get', as: '', loading: false,
});
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :method="method"
        :as="as || (method.toLowerCase() === 'get' ? 'a' : 'button')"
        class="btn app-button"
        :class="[`btn-${color}`, size ? `btn-${size}` : '']"
        v-bind="$attrs"
    >
        <i v-if="icon" class="bi app-button-icon" :class="icon" aria-hidden="true"></i>
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        class="btn app-button"
        :class="[`btn-${color}`, size ? `btn-${size}` : '', { 'is-loading': loading }]"
        :disabled="loading || undefined"
        :aria-busy="loading || undefined"
        v-bind="$attrs"
    >
        <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <i v-else-if="icon" class="bi app-button-icon" :class="icon" aria-hidden="true"></i>
        <slot />
    </button>
</template>
