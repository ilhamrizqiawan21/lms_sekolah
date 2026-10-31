<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { SelectInput } from '../../../Components/Form';
import { Badge, Card, EmptyState, TableWrapper } from '../../../Components/UI';

interface Exam { id: number; judul: string; kelas: string; mata_pelajaran: string; attempts_selesai: number; waktu_mulai: string | null; }
interface Item { id: number; urutan: number; pertanyaan: string; tipe: string; poin_maksimal: number; terjawab: number; benar: number | null; tingkat_kesukaran: number | null; rata_rata_poin: number | null; rata_rata_persen: number | null; }
interface Report { ujian: { id: number; judul: string; kelas: string; mata_pelajaran: string }; summary: { attempts: number; rata_rata: number | null; soal: number; objektif: number }; items: Item[]; }

const props = defineProps<{ ujian: Exam[]; selectedUjianId: number | null; analisis: Report | null; exportUrl: string | null }>();
const selected = ref<number | string>(props.selectedUjianId ?? '');

function chooseExam() {
    if (!selected.value) return;
    router.get('/guru/analisis-butir-soal', { ujian_id: selected.value }, { preserveState: false, replace: true });
}

function difficultyColor(value: number | null): string {
    if (value === null) return 'secondary';
    if (value < 40) return 'danger';
    if (value < 70) return 'warning text-dark';
    return 'success';
}
</script>

<template>
    <Head title="Analisis Butir Soal" />
    <AppShell title="Analisis Butir Soal">
        <PageHeader eyebrow="Administrasi Guru" title="Analisis Butir Soal" subtitle="Tinjau keterjawaban, persentase benar, tingkat kesukaran, dan rata-rata poin dari hasil CBT.">
            <template #actions><a v-if="exportUrl" :href="exportUrl" class="btn btn-outline-primary"><i class="bi bi-download me-1" aria-hidden="true"></i>Ekspor CSV</a></template>
        </PageHeader>

        <Card class="mb-4" title="Pilih Ujian" icon="bi-bar-chart-line-fill">
            <div v-if="ujian.length" class="row g-3 align-items-end"><div class="col-md-8"><SelectInput v-model="selected" name="ujian_id" label="Ujian CBT" :options="ujian.map((item) => ({ value: item.id, label: `${item.judul} · ${item.kelas} · ${item.mata_pelajaran}` }))" wrapper-class="mb-0" @change="chooseExam" /></div><div class="col-md-4 small text-body-secondary">Hanya attempt yang sudah selesai atau waktu habis yang dihitung.</div></div>
            <EmptyState v-else title="Belum ada ujian" message="Buat dan publikasikan ujian CBT terlebih dahulu agar hasilnya dapat dianalisis." icon="bi-bar-chart" />
        </Card>

        <template v-if="analisis">
            <div class="row g-3 mb-4"><div class="col-sm-6 col-xl-3"><Card><div class="small text-body-secondary">Attempt dinilai</div><div class="fs-3 fw-bold">{{ analisis.summary.attempts }}</div></Card></div><div class="col-sm-6 col-xl-3"><Card><div class="small text-body-secondary">Rata-rata skor</div><div class="fs-3 fw-bold">{{ analisis.summary.rata_rata ?? '-' }}</div></Card></div><div class="col-sm-6 col-xl-3"><Card><div class="small text-body-secondary">Total butir</div><div class="fs-3 fw-bold">{{ analisis.summary.soal }}</div></Card></div><div class="col-sm-6 col-xl-3"><Card><div class="small text-body-secondary">Butir objektif</div><div class="fs-3 fw-bold">{{ analisis.summary.objektif }}</div></Card></div></div>
            <Card :title="analisis.ujian.judul" icon="bi-clipboard-data"><p class="small text-body-secondary">{{ analisis.ujian.kelas }} · {{ analisis.ujian.mata_pelajaran }}</p><TableWrapper stack v-if="analisis.items.length"><table class="table table-hover mb-0 app-table-proportional"><thead><tr><th>No</th><th>Butir Soal</th><th>Tipe</th><th>Terjawab</th><th>Benar</th><th>Tingkat Kesukaran</th><th>Rata-rata Poin</th></tr></thead><tbody><tr v-for="item in analisis.items" :key="item.id"><td data-label="No">{{ item.urutan }}</td><td data-label="Butir Soal" class="question-cell">{{ item.pertanyaan }}</td><td data-label="Tipe"><Badge color="info">{{ item.tipe }}</Badge></td><td data-label="Terjawab">{{ item.terjawab }} / {{ analisis.summary.attempts }}</td><td data-label="Benar">{{ item.benar === null ? '—' : item.benar }}</td><td data-label="Tingkat Kesukaran"><Badge :color="difficultyColor(item.tingkat_kesukaran)">{{ item.tingkat_kesukaran === null ? 'Tidak tersedia' : item.tingkat_kesukaran + '%' }}</Badge></td><td data-label="Rata-rata Poin">{{ item.rata_rata_poin === null ? '—' : `${item.rata_rata_poin} / ${item.poin_maksimal} (${item.rata_rata_persen}%)` }}</td></tr></tbody></table></TableWrapper><EmptyState v-else title="Belum ada butir soal" message="Ujian ini belum memiliki soal yang dapat dianalisis." icon="bi-question-circle" /></Card>
        </template>
        <EmptyState v-else-if="ujian.length" title="Pilih ujian" message="Pilih ujian CBT untuk melihat analisis butir." icon="bi-bar-chart-line" />
    </AppShell>
</template>

<style scoped>
.question-cell{min-width:280px;max-width:520px;white-space:normal;line-height:1.45}.table{vertical-align:middle}
</style>
