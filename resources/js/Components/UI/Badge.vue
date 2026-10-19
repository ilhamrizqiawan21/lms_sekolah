<script setup lang="ts">
import { computed } from 'vue';
defineSlots<{ default?: () => unknown }>();
interface Props {
    color?: string;
    icon?: string;
    label?: string;
}

const props = withDefaults(defineProps<Props>(), { color: 'primary', icon: '', label: '' });

const SOFT: Record<string, string> = {
    primary: 'primary',
    success: 'success',
    warning: 'warning',
    danger: 'danger',
    info: 'info',
    secondary: 'muted',
    light: 'muted',
    muted: 'muted',
    admin: 'primary',
    guru: 'info',
    siswa: 'success',
    kepala_sekolah: 'warning',
};
const colorClass = computed(() => {
    const name = (props.color || 'primary').split(' ')[0];
    return SOFT[name] ? `bg-soft-${SOFT[name]}` : `bg-${name}`;
});
</script>

<template>
    <span class="badge app-badge" :class="colorClass" :aria-label="label || undefined">
        <i v-if="icon" class="bi me-1" :class="icon" aria-hidden="true"></i>
        <slot />
    </span>
</template>
