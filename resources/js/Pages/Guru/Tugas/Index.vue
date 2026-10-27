<script setup lang="ts">
import type { PropType } from 'vue';
import type { TeacherTask } from '../../../types/tasks';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { TextInput, TextareaInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Button, Card, EmptyState, MetricStrip } from '../../../Components/UI';
import type { AppPageProps } from '../../../types';

const props = defineProps({
    kelasMapel: { type: Array as PropType<{ id: number; kelas: string; mata_pelajaran: string; semester: string; label: string; href: string }[]>, default: () => [] },
    tugas: { type: Array as PropType<TeacherTask[]>, default: () => [] },
    storeUrl: { type: String, required: true },
});
const page = usePage<AppPageProps>();
const academicLabel = computed(() => {
    const academic = page.props.academic;
    if (!academic) return '';

    return `Tahun Ajaran ${academic.tahun} · Semester ${academic.semester === '2' ? '2 · Genap' : '1 · Ganjil'}`;
});

const form = useForm({
    kelas_mapel_ids: [] as number[],
    judul: '',
    deskripsi: '',
    batas_waktu: '',
});

const courseSearch = ref('');

const courseSummaries = computed(() => props.kelasMapel.map((course) => {
    const courseTasks = props.tugas.filter((item) => Number(item.kelas_mapel_id) === Number(course.id));
    const submitted = courseTasks.reduce((total, item) => total + Number(item.sudah_mengumpulkan || 0), 0);
    const pendingGrades = courseTasks.reduce((total, item) => total + Number(item.perlu_dinilai || 0), 0);
    const overdue = courseTasks.filter((item) => item.is_overdue).length;
    const latestTask = courseTasks[0] ?? null;

    return {
        ...course,
        total_tugas: courseTasks.length,
        total_pengumpulan: submitted,
        perlu_dinilai: pendingGrades,
        lewat_deadline: overdue,
        tugas_terbaru: latestTask?.judul ?? 'Belum ada tugas',
    };
}));

const filteredCourseSummaries = computed(() => {
    const keyword = courseSearch.value.trim().toLowerCase();

    if (!keyword) {
        return courseSummaries.value;
    }

    return courseSummaries.value.filter((item) => [
        item.kelas,
        item.mata_pelajaran,
        item.semester,
        item.label,
        item.tugas_terbaru,
    ].filter(Boolean).join(' ').toLowerCase().includes(keyword));
});

type TaskFilter = 'all' | 'active' | 'grading' | 'overdue';

const taskFilter = ref<TaskFilter>('all');
const taskFilters: Array<{ value: TaskFilter; label: string }> = [
    { value: 'all', label: 'Semua' },
    { value: 'active', label: 'Belum lewat' },
    { value: 'grading', label: 'Perlu dinilai' },
    { value: 'overdue', label: 'Lewat deadline' },
];

const filteredTasks = computed(() => {
    const matchingTasks = props.tugas.filter((task) => {
        if (taskFilter.value === 'grading') {
            return Number(task.perlu_dinilai) > 0;
        }
        if (taskFilter.value === 'overdue') {
            return task.is_overdue;
        }
        if (taskFilter.value === 'active') {
            return !task.is_overdue;
        }

        return true;
    });

    return matchingTasks.slice(0, 6);
});

const metrics = computed(() => {
    const submitted = props.tugas.reduce((total, item) => total + Number(item.sudah_mengumpulkan || 0), 0);
    const pendingGrades = props.tugas.reduce((total, item) => total + Number(item.perlu_dinilai || 0), 0);
    const overdue = props.tugas.filter((item) => item.is_overdue).length;

    return [
        { label: 'Total tugas', value: props.tugas.length, icon: 'bi-journal-check', tone: 'primary' },
        { label: 'Pengumpulan', value: submitted, icon: 'bi-inbox-fill', tone: 'success' },
        { label: 'Perlu dinilai', value: pendingGrades, icon: 'bi-pencil-square', tone: pendingGrades ? 'danger' : 'muted' },
        { label: 'Lewat deadline', value: overdue, icon: 'bi-exclamation-triangle', tone: overdue ? 'warning' : 'muted' },
    ];
});

function toggleAllCourses() {
    if (form.kelas_mapel_ids.length === props.kelasMapel.length) {
        form.kelas_mapel_ids = [];
        return;
    }

    form.kelas_mapel_ids = props.kelasMapel.map((item) => item.id);
}

function submit() {
    if (form.processing) {
        return;
    }

    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

</script>

<template>
    <Head title="Tugas" />

    <AppShell title="Tugas">
        <PageHeader
            eyebrow="Ruang Mengajar"
            title="Tugas"
            subtitle="Buat, bagikan, dan pantau tugas lintas kelas dari satu tempat."
        >
        </PageHeader>
        <div v-if="academicLabel" class="app-page-period" aria-label="Periode akademik aktif">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <span>{{ academicLabel }}</span>
        </div>

        <MetricStrip v-if="kelasMapel.length" :items="metrics" variant="cards" />

        <div v-if="kelasMapel.length" class="tugas-layout">
            <div class="tugas-col-form">
                <Card title="Buat Tugas Baru" subtitle="Atur instruksi, tenggat, dan kelas penerima." icon="bi-plus-circle">
                    <template #actions>
                        <span class="badge rounded-pill text-bg-primary-subtle border border-primary-subtle text-primary fw-bold" style="font-size: 8px;">Guru</span>
                    </template>
                    <form @submit.prevent="submit">
                        <div class="form-help-panel">
                            <i class="bi bi-lightbulb-fill" aria-hidden="true"></i>
                            <span>
                                <span class="form-help-panel-title">Tentukan kelas tujuan terlebih dahulu.</span>
                                Tugas akan dibuat untuk setiap kelas dan mata pelajaran yang dipilih.
                            </span>
                        </div>

                        <TextInput v-model="form.judul" name="judul" label="Judul" required :error="form.errors.judul" />
                        <TextareaInput v-model="form.deskripsi" name="deskripsi" label="Deskripsi" :rows="3" :error="form.errors.deskripsi" />
                        <TextInput v-model="form.batas_waktu" type="date" name="batas_waktu" label="Deadline" required :error="form.errors.batas_waktu" />

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <label class="form-label mb-0">Kelas Tujuan <span class="text-danger">*</span></label>
                                <Button color="outline-secondary" type="button" @click="toggleAllCourses">
                                    {{ form.kelas_mapel_ids.length === kelasMapel.length ? 'Kosongkan' : 'Pilih semua' }}
                                </Button>
                            </div>
                            <div class="assignment-list">
                                <label
                                    v-for="item in kelasMapel"
                                    :key="item.id"
                                    class="assignment-option"
                                    :class="{ selected: form.kelas_mapel_ids.includes(item.id) }"
                                >
                                    <input
                                        v-model="form.kelas_mapel_ids"
                                        class="form-check-input"
                                        type="checkbox"
                                        :value="item.id"
                                    >
                                    <span>{{ item.label }}</span>
                                    <a :href="item.href" class="assignment-option-link" @click.stop>
                                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                        <span class="visually-hidden">Buka {{ item.label }}</span>
                                    </a>
                                </label>
                            </div>
                            <div v-if="form.errors.kelas_mapel_ids" class="text-danger small mt-1">
                                {{ form.errors.kelas_mapel_ids }}
                            </div>
                        </div>

                        <Button type="submit" color="primary" size="" icon="bi-save" class="w-100 assignment-submit" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Tugas' }}
                        </Button>
                    </form>
                </Card>
            </div>

            <div class="tugas-col-courses">
                <Card title="Kelas yang Diajar" subtitle="Pantau aktivitas tugas per kelas dan mata pelajaran." icon="bi-people-fill">
                    <template #actions>
                        <span class="badge rounded-pill text-bg-success-subtle border border-success-subtle text-success fw-bold" style="font-size: 8px;">{{ kelasMapel.length }} penugasan</span>
                    </template>
                    <div class="courses-toolbar d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="assignment-search flex-grow-1">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <TextInput v-model="courseSearch" name="search_kelas_mapel" type="search" placeholder="Cari kelas atau mata pelajaran" aria-label="Cari kelas atau mata pelajaran" wrapper-class="mb-0" class="form-control-sm" />
                        </div>
                        <span class="text-muted small fw-semibold text-nowrap">{{ filteredCourseSummaries.length }} kelas</span>
                    </div>

                    <div v-if="filteredCourseSummaries.length" class="assignment-course-grid">
                        <a
                            v-for="item in filteredCourseSummaries"
                            :key="item.id"
                            :href="item.href"
                            class="assignment-course-card"
                        >
                            <span class="assignment-course-main">
                                <span class="assignment-course-kicker">Semester {{ item.semester ?? '-' }}</span>
                                <strong>{{ item.kelas }}</strong>
                                <span>{{ item.mata_pelajaran }}</span>
                            </span>
                            <span class="assignment-course-stats">
                                <span><strong>{{ item.total_tugas }}</strong> tugas</span>
                                <span><strong>{{ item.total_pengumpulan }}</strong> kumpul</span>
                                <span :class="{ danger: item.perlu_dinilai > 0 }"><strong>{{ item.perlu_dinilai }}</strong> perlu dinilai</span>
                                <span :class="{ warning: item.lewat_deadline > 0 }"><strong>{{ item.lewat_deadline }}</strong> lewat</span>
                            </span>
                        </a>
                    </div>

                    <EmptyState v-else :title="courseSearch ? 'Kelas tidak ditemukan' : 'Belum ada kelas mengajar'" icon="bi-grid-1x2" />
                </Card>
            </div>
        </div>

        <Card v-else>
            <EmptyState title="Anda belum memiliki penugasan mengajar semester ini" icon="bi-journal" />
        </Card>
        <Card v-if="kelasMapel.length && tugas.length" title="Tugas terbaru" icon="bi-journal-check" body-class="p-0" class="task-overview">
            <template #actions>
                <div class="task-filters" role="group" aria-label="Filter tugas">
                    <button
                        v-for="filter in taskFilters"
                        :key="filter.value"
                        type="button"
                        class="task-filter"
                        :class="{ active: taskFilter === filter.value }"
                        :aria-pressed="taskFilter === filter.value"
                        @click="taskFilter = filter.value"
                    >{{ filter.label }}</button>
                </div>
            </template>

            <div v-if="filteredTasks.length" class="task-list">
                <article v-for="task in filteredTasks" :key="task.id" class="task-row">
                    <div class="task-main">
                        <strong>{{ task.judul }}</strong>
                        <div class="task-meta">
                            <span>{{ task.mata_pelajaran || 'Mata pelajaran' }}</span>
                            <span class="task-class">{{ task.kelas || 'Kelas' }}</span>
                        </div>
                    </div>

                    <div class="task-due" :class="{ overdue: task.is_overdue }">
                        <span>{{ task.is_overdue ? 'Lewat deadline' : 'Deadline' }}</span>
                        <strong>{{ task.batas_waktu || 'Belum ditentukan' }}</strong>
                    </div>

                    <div class="task-submission">
                        <div class="task-submission-heading">
                            <span>Pengumpulan</span>
                            <strong>{{ task.sudah_mengumpulkan }} / {{ task.total_siswa ?? 0 }} siswa</strong>
                        </div>
                        <div
                            class="task-progress"
                            role="progressbar"
                            :aria-label="'Pengumpulan ' + task.judul"
                            :aria-valuenow="task.progress_percent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <span :class="{ complete: task.progress_percent >= 80 }" :style="{ width: task.progress_percent + '%' }" />
                        </div>
                        <span class="task-submission-note">{{ task.perlu_dinilai }} pengumpulan perlu dinilai</span>
                    </div>

                    <Link :href="task.pengumpulan_url" class="btn btn-sm btn-outline-primary task-action">
                        Lihat pengumpulan
                    </Link>
                </article>
            </div>
            <EmptyState v-else title="Tidak ada tugas untuk filter ini" icon="bi-journal-x" />
        </Card>
    </AppShell>
</template>

<style scoped>
.tugas-layout {
    display: grid;
    grid-template-columns: minmax(300px, 0.78fr) minmax(0, 1.22fr);
    align-items: start;
    gap: 17px;
    margin-bottom: 20px;
}
@media (max-width: 950px) {
    .tugas-layout {
        grid-template-columns: 1fr;
    }
}
.assignment-list {
    display: grid;
    gap: 8px;
}

.assignment-option {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    padding: 9px 10px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    background: var(--surface-card);
    cursor: pointer;
    transition: var(--transition-fast);
}

.assignment-option input {
    margin-top: 2px;
}

.assignment-option:hover,
.assignment-option.selected {
    border-color: var(--primary-300);
    background: var(--primary-50);
}

.assignment-option span {
    color: var(--text-body);
    font-size: 0.84rem;
    line-height: 1.35;
}

.assignment-option-link {
    margin-left: auto;
    color: var(--text-muted);
    line-height: 1;
}

.assignment-option-link:hover {
    color: var(--text-brand);
}

.assignment-submit {
    margin-top: 0.25rem;
}

.assignment-search {
    position: relative;
    width: min(220px, 100%);
}

.assignment-search .bi {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.8rem;
}

.assignment-search :deep(.form-control) {
    padding-left: 30px;
}

.assignment-course-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: 10px;
    max-height: min(58vh, 520px);
    overflow-y: auto;
    padding-right: 2px;
}

.assignment-course-card {
    display: grid;
    gap: 8px;
    width: 100%;
    padding: 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    background: var(--surface-card);
    color: var(--text-body);
    text-align: left;
    text-decoration: none;
    transition: var(--transition-fast);
}

.assignment-course-card:hover,
.assignment-course-card:focus-visible {
    border-color: var(--primary-300);
    background: var(--primary-50);
}

.assignment-course-main {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.assignment-course-main strong,
.assignment-course-main span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assignment-course-main strong {
    color: var(--text-strong);
    font-size: 0.95rem;
}

.assignment-course-main span:not(.assignment-course-kicker) {
    color: var(--text-muted);
    font-size: 0.78rem;
}

.assignment-course-kicker {
    color: var(--text-brand);
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.assignment-course-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 5px 12px;
}

.assignment-course-stats span {
    min-width: 0;
    color: var(--text-muted);
    font-size: 0.72rem;
}

.assignment-course-stats strong {
    color: var(--text-strong);
}

.assignment-course-stats .danger strong {
    color: var(--status-danger-text);
}

.assignment-course-stats .warning strong {
    color: var(--status-warning-text);
}

.task-overview {
    margin-top: 0.25rem;
}

.task-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.task-filter {
    min-height: 30px;
    padding: 0.25rem 0.65rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-sm);
    background: var(--surface-card);
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 600;
}

.task-filter.active {
    border-color: var(--primary-300);
    background: var(--primary-50);
    color: var(--text-brand);
}

.task-list {
    display: grid;
}

.task-row {
    display: grid;
    grid-template-columns: minmax(180px, 1.3fr) minmax(110px, 0.75fr) minmax(160px, 1fr) auto;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
}

.task-row:last-child {
    border-bottom: 0;
}

.task-main {
    min-width: 0;
}

.task-main > strong {
    display: block;
    overflow: hidden;
    color: var(--text-strong);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 0.75rem;
}

.task-class {
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    background: var(--primary-50);
    color: var(--text-brand);
}

.task-due {
    display: grid;
    gap: 2px;
    color: var(--text-muted);
    font-size: 0.72rem;
}

.task-due strong {
    color: var(--text-body);
    font-size: 0.78rem;
    font-weight: 600;
}

.task-due.overdue,
.task-due.overdue strong {
    color: var(--status-danger-text);
}

.task-submission {
    min-width: 0;
}

.task-submission-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.7rem;
}

.task-submission-heading strong {
    font-size: 0.75rem;
}

.task-progress {
    height: 6px;
    margin-top: 6px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--gray-100);
}

.task-progress span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--text-brand);
}

.task-progress span.complete {
    background: var(--status-success-text);
}

.task-submission-note {
    display: block;
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 0.68rem;
}

.task-action {
    white-space: nowrap;
}

@media (max-width: 767.98px) {
    .assignment-search {
        width: 100%;
    }

    .assignment-course-grid {
        max-height: 420px;
    }

    .task-filters {
        width: 100%;
    }

    .task-filter {
        flex: 1 1 auto;
    }

    .task-row {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px 12px;
        padding: 12px;
    }

    .task-main,
    .task-submission {
        grid-column: 1 / -1;
    }

    .task-due {
        grid-column: 1;
    }

    .task-action {
        grid-column: 2;
        grid-row: 2;
        justify-self: end;
    }
}

@media (max-width: 420px) {
    .task-row {
        grid-template-columns: minmax(0, 1fr);
    }

    .task-main,
    .task-due,
    .task-submission,
    .task-action {
        grid-column: 1;
    }

    .task-action {
        justify-self: start;
    }
}
</style>
