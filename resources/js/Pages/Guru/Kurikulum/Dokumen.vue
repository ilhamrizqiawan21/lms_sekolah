<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { SelectInput, TextareaInput, TextInput } from '../../../Components/Form';
import { Badge, Button, Card, EmptyState } from '../../../Components/UI';

type DocumentType = 'alokasi_waktu' | 'prota' | 'promes' | 'kktp' | 'modul_ajar';
type Content = Record<string, any>;

interface DocumentItem { id: number; jenis: DocumentType; semester: string | null; bab_key: string | null; judul: string; isi: Content; status: 'draft' | 'final'; kelas_mapel_id: number | null; updated_at: string | null; delete_url: string; }
interface Plan { id: number; nama: string; jenis_kurikulum: string; fase: string | null; semester: string; mata_pelajaran: string; tahun_ajaran: string; kelas: { id: number; label: string }[]; dokumen_count: number; capaian_pembelajaran?: { id: number; kode: string | null; elemen: string | null; deskripsi: string }[]; tujuan_pembelajaran?: { id: number; kode: string | null; bab: string | null; tujuan: string; materi_pokok: string | null; indikator_ketercapaian: string | null; alokasi_jp: number; estimasi_pertemuan: number }[]; dokumen?: DocumentItem[]; }

const props = defineProps<{ mode: DocumentType; kurikulum: Plan[]; selectedKurikulumId: number | null; selected: Plan | null; storeUrl: string; moduleUrl: string; documentsUrl: string }>();
const activeType = ref<DocumentType>(props.mode);
const selectedId = ref<number | string>(props.selectedKurikulumId ?? '');
const semester = ref(props.selected?.semester ?? '1');

const types: { value: DocumentType; label: string; description: string; icon: string }[] = [
    { value: 'alokasi_waktu', label: 'Alokasi Waktu', description: 'JP mingguan, minggu efektif, dan bulan mulai.', icon: 'bi-clock-history' },
    { value: 'prota', label: 'Prota', description: 'Pemetaan tujuan pembelajaran tahunan.', icon: 'bi-calendar-range' },
    { value: 'promes', label: 'Promes', description: 'Rencana pertemuan dan distribusi semester.', icon: 'bi-calendar3' },
    { value: 'kktp', label: 'KKTP / KKM', description: 'Kriteria capaian dan ambang ketuntasan.', icon: 'bi-bar-chart-steps' },
    { value: 'modul_ajar', label: 'Modul Ajar', description: 'Rancangan pembelajaran per bab.', icon: 'bi-journal-richtext' },
];

function defaultContent(type: DocumentType): Content {
    const tp = props.selected?.tujuan_pembelajaran ?? [];
    if (type === 'alokasi_waktu') return { jp_per_minggu: 2, minggu_efektif: { '1': 18, '2': 18 }, bulan_mulai: { '1': 7, '2': 1 }, catatan: '' };
    if (type === 'prota') return { rows: tp.map((item) => ({ tp_id: item.id, bab: item.bab ?? '', semester: props.selected?.semester ?? '1', alokasi_jp: item.alokasi_jp })) };
    if (type === 'kktp') return { skala: props.selected?.jenis_kurikulum === 'kurtilas' ? 'angka' : 'interval', kkm: 75, intervals: [{ range: '0–40', label: 'Belum berkembang' }, { range: '41–60', label: 'Mulai berkembang' }, { range: '61–80', label: 'Berkembang sesuai harapan' }, { range: '81–100', label: 'Sangat berkembang' }], rows: tp.map((item) => ({ tp_id: item.id, indikator: item.indikator_ketercapaian ?? '', kkm: 75 })) };
    if (type === 'modul_ajar') return { kesiapan: '', tema_kbc: '', karakteristik_materi: '', profil_lulusan: '', lintas_disiplin: '', iklim: '', kerangka: '', asesmen: '', langkah: [{ topik: '', pendahuluan: '', inti: '', penutup: '' }] };
    return { catatan: '' };
}

const form = useForm({ kurikulum_id: props.selectedKurikulumId ?? '', kelas_mapel_id: '', jenis: activeType.value, semester: semester.value, bab_key: '', judul: '', status: 'draft', isi: defaultContent(activeType.value) as Content });
const selectedDocument = computed(() => props.selected?.dokumen?.find((item) => item.jenis === activeType.value && (activeType.value !== 'modul_ajar' || item.bab_key === form.bab_key)) ?? props.selected?.dokumen?.find((item) => item.jenis === activeType.value));
const selectedTp = computed(() => props.selected?.tujuan_pembelajaran ?? []);
const moduleBabOptions = computed(() => [...new Set(selectedTp.value.map((item) => item.bab).filter((item): item is string => Boolean(item && item.trim()))) ]);

function loadDocument(type: DocumentType = activeType.value) {
    activeType.value = type;
    const document = props.selected?.dokumen?.find((item) => item.jenis === type && (type !== 'modul_ajar' || item.bab_key === form.bab_key)) ?? props.selected?.dokumen?.find((item) => item.jenis === type);
    form.kurikulum_id = props.selected?.id ?? '';
    form.kelas_mapel_id = document?.kelas_mapel_id ? String(document.kelas_mapel_id) : '';
    form.jenis = type;
    form.semester = document?.semester ?? (type === 'kktp' ? props.selected?.semester ?? '1' : '');
    form.bab_key = document?.bab_key ?? (type === 'modul_ajar' ? moduleBabOptions.value[0] ?? '' : '');
    form.judul = document?.judul ?? `${types.find((item) => item.value === type)?.label ?? type} ${props.selected?.nama ?? ''}`.trim();
    form.status = document?.status ?? 'draft';
    form.isi = document?.isi ?? defaultContent(type);
    form.clearErrors();
}

function choosePlan() {
    if (!selectedId.value) return;
    router.get(props.documentsUrl, { kurikulum_id: selectedId.value, jenis: activeType.value }, { preserveState: false, replace: true });
}

function chooseType(type: DocumentType) {
    if (type === 'modul_ajar') {
        router.get(props.moduleUrl, { kurikulum_id: selectedId.value }, { preserveState: false, replace: true });
        return;
    }
    activeType.value = type;
    loadDocument(type);
}

function save() {
    form.kurikulum_id = props.selected?.id ?? '';
    form.jenis = activeType.value;
    form.post(props.storeUrl, { preserveScroll: true, onSuccess: () => loadDocument(activeType.value) });
}

function removeDocument() {
    if (!selectedDocument.value || !window.confirm('Hapus dokumen ini?')) return;
    router.delete(selectedDocument.value.delete_url, { preserveScroll: true });
}

function addInterval() { form.isi.intervals.push({ range: '', label: '' }); }
function addModuleStep() { form.isi.langkah.push({ topik: '', pendahuluan: '', inti: '', penutup: '' }); }
function removeModuleStep(index: number) { form.isi.langkah.splice(index, 1); }

watch(() => props.selected?.id, () => loadDocument(props.mode));
onMounted(() => loadDocument(activeType.value));
</script>

<template>
    <Head title="Administrasi Pembelajaran" />
    <AppShell title="Administrasi Pembelajaran">
        <PageHeader eyebrow="Administrasi Guru" title="Dokumen Perencanaan" subtitle="Kelola Alokasi Waktu, Prota, Promes, KKTP/KKM, dan Modul Ajar dalam satu tempat.">
            <template #actions><Link href="/guru/kurikulum" class="btn btn-outline-secondary"><i class="bi bi-journal-bookmark me-1" aria-hidden="true"></i>Kelola ATP / CP / TP</Link></template>
        </PageHeader>

        <Card class="mb-4" title="Pilih Kurikulum" icon="bi-journal-bookmark-fill">
            <div v-if="kurikulum.length" class="row g-3 align-items-end">
                <div class="col-md-8"><SelectInput v-model="selectedId" name="kurikulum_id" label="Kurikulum / ATP" :options="kurikulum.map((item) => ({ value: item.id, label: `${item.nama} · ${item.mata_pelajaran} · Sem. ${item.semester}` }))" wrapper-class="mb-0" @change="choosePlan" /></div>
                <div class="col-md-4"><div class="small text-body-secondary">{{ selected?.kelas.map((item) => item.label).join(', ') || 'Belum ada kelas' }}</div><div class="mt-1"><Badge color="info">{{ selected?.dokumen_count ?? 0 }} dokumen tersimpan</Badge></div></div>
            </div>
            <EmptyState v-else title="Belum ada ATP / kurikulum" message="Buat kurikulum terlebih dahulu sebelum mengisi dokumen administrasi." icon="bi-journal-x"><template #action><Link href="/guru/kurikulum" class="btn btn-primary">Buka Kurikulum</Link></template></EmptyState>
        </Card>

        <template v-if="selected">
            <div class="row g-3 mb-4">
                <div v-for="item in types" :key="item.value" class="col-md-6 col-xl">
                    <button type="button" class="document-card w-100 text-start" :class="{ active: activeType === item.value }" @click="chooseType(item.value)">
                        <i class="bi" :class="item.icon" aria-hidden="true"></i><strong>{{ item.label }}</strong><span>{{ item.description }}</span>
                    </button>
                </div>
            </div>

            <Card v-if="activeType === 'promes'" title="Promes dan Rencana Pertemuan" icon="bi-calendar3">
                <p class="text-body-secondary">Promes aktif menggunakan generator rencana pertemuan agar tanggal mengikuti jadwal mengajar dan kalender sekolah.</p>
                <Link :href="`/guru/kurikulum/${selected.id}/promes`" class="btn btn-primary"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Buka Promes</Link>
            </Card>

            <Card v-else title="Editor Dokumen" icon="bi-pencil-square">
                <form @submit.prevent="save">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><TextInput v-model="form.judul" name="judul" label="Judul Dokumen" required :error="form.errors.judul" /></div>
                        <div class="col-md-3"><SelectInput v-model="form.status" name="status" label="Status" :options="[{ value: 'draft', label: 'Draft' }, { value: 'final', label: 'Final' }]" :error="form.errors.status" wrapper-class="" /></div>
                        <div v-if="activeType === 'kktp'" class="col-md-3"><SelectInput v-model="form.semester" name="semester" label="Semester" :options="[{ value: '1', label: 'Ganjil' }, { value: '2', label: 'Genap' }]" :error="form.errors.semester" wrapper-class="" /></div>
                        <div v-if="activeType === 'modul_ajar'" class="col-md-6"><TextInput v-model="form.bab_key" name="bab_key" label="Bab / Unit" placeholder="Contoh: Bab 1 — Bilangan" required :error="form.errors.bab_key" /><small v-if="moduleBabOptions.length" class="text-body-secondary">Bab dari TP: {{ moduleBabOptions.join(', ') }}</small></div>
                    </div>

                    <section v-if="activeType === 'alokasi_waktu'" class="document-section">
                        <h3>Pengaturan Waktu Belajar</h3><p class="text-body-secondary">Kapasitas ini dipakai sebagai acuan pembagian program tahunan dan semester.</p>
                        <div class="row g-3"><div class="col-md-4"><TextInput v-model="form.isi.jp_per_minggu" name="jp_per_minggu" type="number" min="1" max="40" label="JP per Minggu" /></div><div class="col-md-4"><TextInput v-model="form.isi.minggu_efektif['1']" name="minggu_efektif_ganjil" type="number" min="0" max="30" label="Minggu Efektif Ganjil" /></div><div class="col-md-4"><TextInput v-model="form.isi.minggu_efektif['2']" name="minggu_efektif_genap" type="number" min="0" max="30" label="Minggu Efektif Genap" /></div><div class="col-md-4"><TextInput v-model="form.isi.bulan_mulai['1']" name="bulan_mulai_ganjil" type="number" min="1" max="12" label="Bulan Mulai Ganjil" /></div><div class="col-md-4"><TextInput v-model="form.isi.bulan_mulai['2']" name="bulan_mulai_genap" type="number" min="1" max="12" label="Bulan Mulai Genap" /></div><div class="col-12"><TextareaInput v-model="form.isi.catatan" name="catatan_alokasi" label="Catatan Perhitungan" :rows="3" /></div></div>
                        <div class="capacity-summary mt-3"><span>Kapasitas Ganjil</span><strong>{{ Number(form.isi.jp_per_minggu || 0) * Number(form.isi.minggu_efektif?.['1'] || 0) }} JP</strong><span>Kapasitas Genap</span><strong>{{ Number(form.isi.jp_per_minggu || 0) * Number(form.isi.minggu_efektif?.['2'] || 0) }} JP</strong></div>
                    </section>

                    <section v-else-if="activeType === 'prota'" class="document-section"><h3>Pemetaan Tahunan</h3><p class="text-body-secondary">Review bab, semester, dan alokasi setiap tujuan pembelajaran.</p><div v-for="row in form.isi.rows" :key="row.tp_id" class="mapping-row"><div><strong>{{ selectedTp.find((item) => item.id === row.tp_id)?.kode || 'TP' }}</strong><span>{{ selectedTp.find((item) => item.id === row.tp_id)?.tujuan }}</span></div><TextInput v-model="row.bab" :name="`prota_bab_${row.tp_id}`" label="Bab" /><SelectInput v-model="row.semester" :name="`prota_semester_${row.tp_id}`" label="Semester" :options="[{ value: '1', label: 'Ganjil' }, { value: '2', label: 'Genap' }]" wrapper-class="" /></div><p v-if="!form.isi.rows?.length" class="text-body-secondary">Tambahkan TP terlebih dahulu pada halaman Kurikulum & ATP.</p></section>

                    <section v-else-if="activeType === 'kktp'" class="document-section"><h3>Kriteria Ketercapaian</h3><p class="text-body-secondary">Untuk Kurikulum Merdeka/KBC gunakan interval deskriptif; Kurtilas dapat menggunakan nilai KKM.</p><div class="row g-3 mb-3"><div class="col-md-4"><SelectInput v-model="form.isi.skala" name="skala_kktp" label="Skala" :options="[{ value: 'interval', label: 'Interval deskriptif' }, { value: 'angka', label: 'Angka / KKM' }]" wrapper-class="" /></div><div class="col-md-4"><TextInput v-model="form.isi.kkm" name="kkm_umum" type="number" min="0" max="100" label="KKM / Ambang Nilai" /></div></div><div v-for="(interval, index) in form.isi.intervals" :key="index" class="interval-row"><Badge color="primary">{{ Number(index) + 1 }}</Badge><TextInput v-model="interval.range" :name="`interval_range_${index}`" label="Rentang" /><TextInput v-model="interval.label" :name="`interval_label_${index}`" label="Deskripsi" /></div><Button type="button" color="outline-secondary" size="sm" icon="bi-plus-lg" @click="addInterval">Tambah Interval</Button><hr><h4 class="h6">Indikator per TP</h4><div v-for="row in form.isi.rows" :key="row.tp_id" class="mapping-row"><div><strong>{{ selectedTp.find((item) => item.id === row.tp_id)?.kode || 'TP' }}</strong><span>{{ selectedTp.find((item) => item.id === row.tp_id)?.tujuan }}</span></div><TextareaInput v-model="row.indikator" :name="`indikator_${row.tp_id}`" label="Indikator" :rows="2" /><TextInput v-model="row.kkm" :name="`kkm_${row.tp_id}`" type="number" min="0" max="100" label="KKM" /></div></section>

                    <section v-else class="document-section"><h3>Modul Ajar per Bab</h3><p class="text-body-secondary">Isi bagian rancangan pembelajaran; data CP, TP, dan alokasi diturunkan dari ATP.</p><div class="row g-3"><div class="col-md-6"><TextareaInput v-model="form.isi.kesiapan" name="kesiapan" label="Kesiapan Peserta Didik" :rows="3" /></div><div v-if="selected.jenis_kurikulum === 'kbc'" class="col-md-6"><TextareaInput v-model="form.isi.tema_kbc" name="tema_kbc" label="Tema Panca Cinta / KBC" :rows="3" /></div><div class="col-md-6"><TextareaInput v-model="form.isi.karakteristik_materi" name="karakteristik_materi" label="Karakteristik Materi" :rows="3" /></div><div class="col-md-6"><TextareaInput v-model="form.isi.profil_lulusan" name="profil_lulusan" label="Profil Lulusan" :rows="3" /></div><div class="col-md-6"><TextareaInput v-model="form.isi.lintas_disiplin" name="lintas_disiplin" label="Lintas Disiplin" :rows="3" /></div><div class="col-md-6"><TextareaInput v-model="form.isi.iklim" name="iklim" label="Iklim Pembelajaran" :rows="3" /></div><div class="col-12"><TextareaInput v-model="form.isi.kerangka" name="kerangka" label="Kerangka / Model Pembelajaran" :rows="3" /></div><div class="col-12"><TextareaInput v-model="form.isi.asesmen" name="asesmen" label="Asesmen dan Diferensiasi" :rows="3" /></div></div><div class="d-flex justify-content-between align-items-center mt-4"><h4 class="h6 mb-0">Langkah per Pertemuan</h4><Button type="button" color="outline-secondary" size="sm" icon="bi-plus-lg" @click="addModuleStep">Tambah Pertemuan</Button></div><div v-for="(step, index) in form.isi.langkah" :key="index" class="step-card"><div class="d-flex justify-content-between"><strong>Pertemuan {{ Number(index) + 1 }}</strong><button type="button" class="btn btn-sm btn-outline-danger" @click="removeModuleStep(Number(index))">Hapus</button></div><div class="row g-3 mt-1"><div class="col-12"><TextareaInput v-model="step.topik" :name="`langkah_topik_${index}`" label="Topik / Pembahasan" :rows="2" /></div><div class="col-md-4"><TextareaInput v-model="step.pendahuluan" :name="`langkah_pendahuluan_${index}`" label="Pendahuluan" :rows="3" /></div><div class="col-md-4"><TextareaInput v-model="step.inti" :name="`langkah_inti_${index}`" label="Kegiatan Inti" :rows="3" /></div><div class="col-md-4"><TextareaInput v-model="step.penutup" :name="`langkah_penutup_${index}`" label="Penutup" :rows="3" /></div></div></div></section>

                    <div v-if="form.errors.isi" class="alert alert-danger mt-3">{{ form.errors.isi }}</div><div class="d-flex justify-content-end gap-2 mt-4"><Button v-if="selectedDocument" type="button" color="outline-danger" @click="removeDocument">Hapus Dokumen</Button><Button type="submit" color="primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan Dokumen' }}</Button></div>
                </form>
            </Card>
        </template>
    </AppShell>
</template>

<style scoped>
.document-card{display:grid;gap:.25rem;min-height:126px;padding:1rem;border:1px solid var(--bs-border-color);border-radius:.85rem;background:var(--bs-body-bg);color:inherit;transition:.15s ease}.document-card:hover,.document-card.active{border-color:var(--bs-primary);box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.1);transform:translateY(-1px)}.document-card i{font-size:1.35rem;color:var(--bs-primary)}.document-card strong{font-size:.95rem}.document-card span{color:var(--bs-secondary-color);font-size:.78rem;line-height:1.4}.document-section h3{font-size:1.05rem;margin-bottom:.25rem}.capacity-summary{display:grid;grid-template-columns:1fr auto;gap:.45rem 1rem;padding:.8rem 1rem;border-radius:.65rem;background:rgba(var(--bs-primary-rgb),.08);color:var(--bs-secondary-color)}.capacity-summary strong{color:var(--bs-primary)}.mapping-row{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(140px,.7fr) minmax(130px,.55fr);gap:.75rem;align-items:end;padding:.75rem 0;border-bottom:1px solid var(--bs-border-color)}.mapping-row>div{display:grid;gap:.2rem}.mapping-row span{font-size:.8rem;color:var(--bs-secondary-color);line-height:1.4}.interval-row{display:grid;grid-template-columns:36px minmax(130px,.6fr) minmax(0,1.4fr);gap:.75rem;align-items:end;margin-bottom:.65rem}.step-card{margin-top:.75rem;padding:.85rem;border:1px solid var(--bs-border-color);border-radius:.7rem;background:rgba(var(--bs-secondary-rgb),.035)}@media(max-width:700px){.mapping-row,.interval-row{grid-template-columns:1fr}.interval-row .badge{width:max-content}.document-card{min-height:unset}.step-card .row{margin-top:.25rem!important}}
</style>
