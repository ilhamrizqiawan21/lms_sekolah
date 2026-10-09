<script setup lang="ts">
import { Badge, Card } from '../../../Components/UI';
import type { PropType } from 'vue';
import type { Announcement } from '../../../types/announcements';
import { Head, Link } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';

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
        <PageHeader title="Detail Pengumuman" icon="bi-megaphone" />
        <div class="mb-3">
            <Link :href="backUrl" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Kembali ke Pengumuman</Link>
        </div>
        <Card body-class="p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div><h1 class="h3 mb-1">{{ pengumuman.judul }}</h1><div class="text-body-secondary small">{{ pengumuman.creator?.nama_lengkap || '-' }} · {{ new Date(pengumuman.created_at).toLocaleString('id-ID') }}</div></div>
                    <Badge color="success">{{ pengumuman.target }}</Badge>
                </div>
                <hr>
                <div class="text-secondary u-ws-pre-line">{{ pengumuman.isi }}</div>
                <div v-if="pengumuman.is_public_login" class="mt-4">
                    <Badge color="success">Tampil di halaman login</Badge>
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
