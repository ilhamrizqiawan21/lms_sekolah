<script setup lang="ts">
import { computed } from 'vue';
import type { MetricItem } from '../../types/ui';
import { Link } from '@inertiajs/vue3';

const props = withDefaults(defineProps<{ items?: MetricItem[]; variant?: 'joined' | 'cards' }>(), {
    items: () => [],
    variant: 'joined',
});
const columns = computed(() => Math.min(Math.max(props.items.length, 1), 4));
</script>

<template>
    <div class="metric-strip" :class="[`metric-count-${columns}`, `metric-strip-${variant}`]" :style="{ '--metric-columns': columns }">
        <component
            :is="item.href ? Link : 'div'"
            v-for="item in items"
            :key="item.label"
            :href="item.href"
            class="metric-tile"
            :class="[`metric-${item.tone || 'primary'}`, { 'metric-tile-link': item.href }]"
        >
            <span class="metric-icon">
                <i class="bi" :class="item.icon || 'bi-activity'" aria-hidden="true"></i>
            </span>
            <span class="metric-copy">
                <strong>{{ item.value }}</strong>
                <small>{{ item.label }}</small>
            </span>
        </component>
    </div>
</template>
