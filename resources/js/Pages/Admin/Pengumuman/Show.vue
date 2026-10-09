<script setup lang="ts">
import { Badge, Card } from '../../../Components/UI';
import type { PropType } from 'vue';
import type { Announcement } from '../../../types/announcements';
import { Head, Link } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { formatDateTime, targetLabel } from '../../../utils/announcements';

const props = defineProps({
    pengumuman: { type: Object as PropType<Announcement>, required: true },
    targetKelasLabels: { type: Array as PropType<string[]>, default: () => [] },
    backUrl: { type: String, default: '/admin/pengumuman' },
});

function formatFileSize(bytes: number | null) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.ceil(kb)} KB`;
}
</script>

<template>
    <Head title="Detail Pengumuman" />
    <AppShell title="Detail Pengumuman">
        <PageHeader
            eyebrow="Pengumuman"
            :title="pengumuman.judul"
            :subtitle="`${pengumuman.creator?.nama_lengkap || '-'} · ${formatDateTime(pengumuman.created_at)}`"
        >
            <template #actions>
                <Link :href="backUrl" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali</Link>
            </template>
        </PageHeader>
        <Card body-class="p-4">
                <div class="mb-3"><Badge color="secondary">{{ targetLabel(pengumuman.target) }}</Badge></div>
                <div class="text-secondary u-ws-pre-line">{{ pengumuman.isi }}</div>
                <div v-if="pengumuman.is_public_login" class="mt-4">
                    <Badge color="info">Tampil di halaman login</Badge>
                </div>
                <div v-if="pengumuman.attachment" class="mt-3">
                    <a v-if="pengumuman.attachment.url" :href="pengumuman.attachment.url" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ pengumuman.attachment.name }}
                        <span v-if="formatFileSize(pengumuman.attachment.size)" class="text-body-secondary">({{ formatFileSize(pengumuman.attachment.size) }})</span>
                    </a>
                    <Badge v-else color="secondary">
                        <i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ pengumuman.attachment.name }}
                    </Badge>
                </div>
                <div v-if="targetKelasLabels.length" class="mt-4"><strong>Kelas tujuan:</strong> {{ targetKelasLabels.join(', ') }}</div>
        </Card>
    </AppShell>
</template>
