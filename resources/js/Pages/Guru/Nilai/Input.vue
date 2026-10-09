<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../Components/UI';

import type { GradeCourse, GradeStudent, Score, ScoreField } from '../../../types/assessment';

const props = withDefaults(defineProps<{
    kelasMapel: GradeCourse;
    tahunAjaran?: { id: number; tahun: string } | null;
    semester?: string;
    students?: GradeStudent[];
}>(), { tahunAjaran: null, semester: '1', students: () => [] });

const fieldGroups: { key: ScoreField; label: string; readonly?: boolean }[] = [
    { key: 'sum1', label: 'SUM1' },
    { key: 'sum2', label: 'SUM2' },
    { key: 'sum3', label: 'SUM3' },
    { key: 'sum4', label: 'SUM4' },
    { key: 'nilai_harian', label: 'Dari Tugas', readonly: true },
    { key: 'sts', label: 'Nilai' },
    { key: 'sas', label: 'Nilai' },
    { key: 'sat', label: 'Nilai' },
];
const editableFieldKeys = fieldGroups.filter((field) => !field.readonly).map((field) => field.key);
const pasteStatus = ref('');

const form = useForm({
    semester: props.semester,
    nilai: buildNilai(),
});

const title = computed(() => `Input Nilai - ${props.kelasMapel.mata_pelajaran}`);

const courseTabs = computed(() => [
    { label: 'Ringkasan', href: props.kelasMapel.workspace_url, icon: 'bi-grid-1x2' },
    { label: 'Materi', href: `/guru/materi/${props.kelasMapel.id}/list`, icon: 'bi-file-earmark-text' },
    { label: 'Tugas', href: `/guru/tugas/${props.kelasMapel.id}/list`, icon: 'bi-journal-check' },
    { label: 'Nilai', href: '#', icon: 'bi-bar-chart', active: true },
    { label: 'Absensi', href: `/guru/absensi/${props.kelasMapel.id}/create`, icon: 'bi-clipboard-check' },
    { label: 'Chat', href: `/guru/chat/${props.kelasMapel.id}`, icon: 'bi-chat-dots' },
]);

watch(() => props.students, () => {
    form.semester = props.semester;
    form.nilai = buildNilai();
}, { deep: true });

function roundScores(scores: Record<string, Score | undefined> | undefined) {
    return Object.fromEntries(Object.entries(scores ?? {}).map(([key, value]) => [
        key,
        value === null || value === undefined || value === '' || Number.isNaN(Number(value)) ? value : String(Math.round(Number(value))),
    ])) as typeof scores;
}

function buildNilai() {
    return Object.fromEntries(props.students.map((student) => [
        String(student.id),
        { ...roundScores(student.scores) },
    ]));
}

function scoreClass(value: Score | undefined) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (Number(value) >= 92) return 'excellent';
    if (Number(value) >= 83) return 'good';
    if (Number(value) >= 75) return 'fair';
    return 'low';
}

function formatScore(value: Score | undefined) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return String(Math.round(Number(value)));
}

function normalizeScore(value: Score | undefined) {
    return String(value ?? '').trim().replace(',', '.');
}

function parseClipboardHtml(html: string) {
    if (!html || typeof DOMParser === 'undefined') {
        return [];
    }

    const document = new DOMParser().parseFromString(html, 'text/html');

    return Array.from(document.querySelectorAll('table tr'))
        .map((row) => Array.from(row.querySelectorAll('th, td')).map((cell) => normalizeScore(cell.textContent)))
        .filter((row) => row.length);
}

function parseScoreText(text: string) {
    if (!/[\r\n\t\u2028\u2029]/.test(text)) {
        return [];
    }

    const rows = text
        .replace(/\r\n?|\u2028|\u2029/g, '\n')
        .split('\n');

    while (rows.length > 1 && rows.at(-1) === '') {
        rows.pop();
    }

    return rows.map((row) => row.split('\t').map(normalizeScore));
}

function parsePastedScoreGrid(event: ClipboardEvent) {
    const clipboard = event.clipboardData ?? (window as Window & { clipboardData?: DataTransfer }).clipboardData;
    const text = clipboard?.getData('text/plain') || clipboard?.getData('Text') || '';
    const textGrid = parseScoreText(text);

    return textGrid.length
        ? textGrid
        : parseClipboardHtml(clipboard?.getData('text/html') ?? '');
}

function setStudentScore(studentIndex: number, fieldKey: ScoreField, score: string) {
    const student = props.students[studentIndex];
    if (!student || !editableFieldKeys.includes(fieldKey)) {
        return false;
    }

    const studentId = String(student.id);
    const studentScores = form.nilai[studentId];
    if (!studentScores) {
        return false;
    }

    form.nilai[studentId] = {
        ...studentScores,
        [fieldKey]: score,
    };

    return true;
}

async function applyScoreGrid(grid: string[][], studentIndex: number, fieldKey: ScoreField) {
    const startFieldIndex = editableFieldKeys.indexOf(fieldKey);
    if (startFieldIndex < 0) {
        return;
    }

    const isSingleColumn = grid.every((row) => row.length === 1);
    const editableTargetFields = editableFieldKeys.slice(startFieldIndex);
    let pastedCount = 0;
    const lastTarget: { value: { studentIndex: number; fieldKey: ScoreField } | null } = { value: null };

    grid.forEach((row, rowOffset) => {
        if (isSingleColumn) {
            if (setStudentScore(studentIndex + rowOffset, fieldKey, row[0])) {
                pastedCount += 1;
                lastTarget.value = { studentIndex: studentIndex + rowOffset, fieldKey };
            }
            return;
        }

        row.forEach((score, colOffset) => {
            const targetFieldKey = editableTargetFields[colOffset];
            if (!targetFieldKey) {
                return;
            }

            if (setStudentScore(studentIndex + rowOffset, targetFieldKey, score)) {
                pastedCount += 1;
                lastTarget.value = { studentIndex: studentIndex + rowOffset, fieldKey: targetFieldKey };
            }
        });
    });

    pasteStatus.value = pastedCount ? `${pastedCount} nilai ditempel` : '';

    await nextTick();
    if (lastTarget.value) {
        document.querySelector<HTMLTextAreaElement>(
            `.score-input[data-student-index="${lastTarget.value.studentIndex}"][data-field-key="${lastTarget.value.fieldKey}"]`,
        )?.focus();
    }
}

function handleScorePaste(event: ClipboardEvent, studentIndex: number, fieldKey: ScoreField) {
    const grid = parsePastedScoreGrid(event);
    if (!grid.length) {
        return;
    }

    event.preventDefault();
    applyScoreGrid(grid, studentIndex, fieldKey);
}

function handleScoreInput(event: Event, studentIndex: number, fieldKey: ScoreField) {
    if (!(event.target instanceof HTMLTextAreaElement)) return;
    const grid = parseScoreText(event.target.value);
    if (grid.length) {
        applyScoreGrid(grid, studentIndex, fieldKey);
    }
}

function handleScoreEnter(event: KeyboardEvent, studentIndex: number, fieldKey: ScoreField) {
    if (event.isComposing) return;

    event.preventDefault();
    document.querySelector<HTMLTextAreaElement>(
        `.score-input[data-student-index="${studentIndex + 1}"][data-field-key="${fieldKey}"]`,
    )?.focus();
}

function submit() {
    if (form.processing) {
        return;
    }

    props.students.forEach((student) => {
        const scores = form.nilai[String(student.id)];
        if (!scores) {
            return;
        }

        editableFieldKeys.forEach((fieldKey) => {
            scores[fieldKey] = normalizeScore(scores[fieldKey]);
        });
    });

    form.post(props.kelasMapel.store_url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="title" />

    <AppShell title="Input Nilai">
        <PageHeader
            :title="`${kelasMapel.mata_pelajaran} · ${kelasMapel.kelas}`"
            :subtitle="`Input nilai · Tahun Ajaran ${tahunAjaran?.tahun ?? '-'} · Semester ${semester === '2' ? 'Genap' : 'Ganjil'}`"
        >
            <template #actions>
                <a :href="kelasMapel.export_excel_url" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Excel</a>
                <a :href="kelasMapel.export_pdf_url" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF</a>
            </template>
        </PageHeader>

        <nav class="workspace-tabs" aria-label="Navigasi kelas dan mata pelajaran">
            <a v-for="tab in courseTabs" :key="tab.label" :href="tab.href" class="workspace-tab" :class="{ 'is-active': tab.active }" :aria-current="tab.active ? 'page' : undefined">
                <i class="bi" :class="tab.icon" aria-hidden="true"></i>{{ tab.label }}
            </a>
        </nav>

        <form @submit.prevent="submit">
            <Card body-class="p-0">
                <div class="grade-toolbar">
                    <span class="grade-hint"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Enter: pindah ke siswa berikutnya. Tempel langsung dari spreadsheet untuk mengisi banyak nilai.</span>
                    <span class="d-flex align-items-center gap-2">
                        <Badge v-if="pasteStatus" color="success">{{ pasteStatus }}</Badge>
                        <Badge color="secondary">{{ students.length }} siswa</Badge>
                    </span>
                </div>

                <TableWrapper :scroll-hint="false">
                    <table class="table table-bordered app-table grade-table mb-0">
                        <colgroup>
                            <col class="grade-col-student">
                            <col v-for="field in fieldGroups" :key="`col-${field.key}`" class="grade-col-score">
                            <col class="grade-col-total">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" rowspan="2" class="grade-sticky">Siswa</th>
                                <th scope="colgroup" colspan="4" class="text-center bg-soft-success">Sumatif Harian</th>
                                <th scope="col" class="text-center bg-soft-success">Nilai Harian</th>
                                <th scope="col" class="text-center bg-soft-warning">STS</th>
                                <th scope="col" class="text-center bg-soft-warning">SAS</th>
                                <th scope="col" class="text-center bg-soft-danger">SAT</th>
                                <th scope="col" rowspan="2" class="text-center bg-soft-muted">Rata-rata<br>Akhir</th>
                            </tr>
                            <tr>
                                <th scope="col"
                                    v-for="field in fieldGroups"
                                    :key="field.key"
                                    class="text-center"
                                >
                                    {{ field.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(student, studentIndex) in students" :key="student.id">
                                <th scope="row" class="grade-sticky grade-student">
                                    <span class="grade-student-name">{{ student.nama }}</span>
                                    <span class="grade-student-nis">{{ student.no }}. NIS {{ student.nis }}</span>
                                </th>
                                <td v-for="field in fieldGroups" :key="`${student.id}-${field.key}`" class="text-center">
                                    <span
                                        v-if="field.readonly"
                                        class="score-result readonly-score"
                                        :class="scoreClass(form.nilai[String(student.id)][field.key])"
                                        :title="'Nilai harian dihitung otomatis dari nilai tugas'"
                                    >
                                        {{ formatScore(form.nilai[String(student.id)][field.key]) ?? '–' }}
                                    </span>
                                    <textarea
                                        v-else
                                        v-model="form.nilai[String(student.id)][field.key]"
                                        rows="1"
                                        inputmode="decimal"
                                        class="form-control form-control-sm score-input"
                                        autocomplete="off"
                                        pattern="^\\d{1,3}([,.]\\d{1,2})?$"
                                        placeholder="–"
                                        :aria-label="`${field.key.toUpperCase()} ${student.nama}`"
                                        :data-student-index="studentIndex"
                                        :data-field-key="field.key"
                                        @keydown.enter="handleScoreEnter($event, studentIndex, field.key)"
                                        @paste.stop="handleScorePaste($event, studentIndex, field.key)"
                                        @input="handleScoreInput($event, studentIndex, field.key)"
                                        @focus="($event.target as HTMLTextAreaElement).select()"
                                    ></textarea>
                                </td>
                                <td class="text-center">
                                    <strong
                                        v-if="formatScore(student.rata_akhir)"
                                        class="score-result grade-total"
                                        :class="scoreClass(student.rata_akhir)"
                                    >
                                        {{ formatScore(student.rata_akhir) }}
                                    </strong>
                                    <span v-else class="text-body-secondary">–</span>
                                </td>
                            </tr>
                            <tr v-if="!students.length">
                                <td colspan="10">
                                    <EmptyState title="Tidak ada siswa di kelas ini." icon="bi-people" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </TableWrapper>

                <div class="grade-savebar">
                    <a href="/guru/nilai" class="btn btn-outline-secondary" aria-label="Kembali ke daftar nilai">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Kembali</span>
                    </a>
                    <span class="grade-savebar-status" role="status" aria-live="polite">
                        <template v-if="form.isDirty"><i class="bi bi-circle-fill grade-dirty-dot" aria-hidden="true"></i>Belum disimpan<span class="d-none d-sm-inline">: ada perubahan nilai</span></template>
                        <template v-else-if="form.recentlySuccessful"><i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>Tersimpan</template>
                    </span>
                    <Button type="submit" color="primary" icon="bi-save" :loading="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Semua' }}
                    </Button>
                </div>
            </Card>
        </form>
    </AppShell>
</template>

<style scoped>
.grade-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 1rem;
    padding: 0.7rem 1rem;
    border-bottom: 1px solid var(--border-soft);
}
.grade-hint { color: var(--text-muted); font-size: 0.8rem; }

.grade-table {
    min-width: 960px;
    table-layout: fixed;
}
.grade-col-student { width: 240px; }
.grade-col-score { width: 78px; }
.grade-col-total { width: 96px; }

.grade-table th,
.grade-table td {
    padding: 0.45rem 0.4rem;
    vertical-align: middle;
}
.grade-table thead th {
    line-height: 1.2;
    white-space: normal;
    text-align: center;
}

/* Kolom siswa tetap terlihat saat tabel digeser ke samping */
.grade-sticky {
    position: sticky;
    left: 0;
    z-index: 2;
    background: var(--surface-card);
    box-shadow: 1px 0 0 var(--border-soft);
}
.grade-table thead .grade-sticky { z-index: 3; background: var(--surface-subtle); text-align: left; }
.grade-student {
    padding-left: 0.85rem !important;
    font-weight: 400;
    text-align: left;
    white-space: normal;
}
.grade-student-name {
    display: block;
    color: var(--text-strong);
    font-weight: 600;
    line-height: 1.25;
}
.grade-student-nis {
    display: block;
    margin-top: 0.1rem;
    color: var(--text-muted);
    font-size: 0.75rem;
}

.grade-table .score-input {
    width: 100%;
    min-width: 0;
    height: 32px;
    min-height: 32px;
    overflow: hidden;
    resize: none;
    text-align: center;
    font-variant-numeric: tabular-nums;
}

.readonly-score {
    display: inline-flex;
    width: 100%;
    min-width: 0;
    min-height: 32px;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-control);
    background: var(--surface-muted);
    font-weight: 650;
}
.grade-total { font-size: 0.95rem; }

/* Satu bilah simpan yang menempel di bawah layar */
.grade-savebar {
    position: sticky;
    bottom: 0;
    z-index: 4;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 1rem;
    border-top: 1px solid var(--border-soft);
    border-radius: 0 0 var(--card-radius) var(--card-radius);
    background: var(--surface-card);
}
.grade-savebar-status {
    flex: 1 1 auto;
    color: var(--text-muted);
    font-size: 0.85rem;
    text-align: right;
}
.grade-dirty-dot {
    margin-right: 0.4rem;
    color: var(--status-warning-text);
    font-size: 0.5rem;
    vertical-align: middle;
}

@media (max-width: 991.98px) {
    /* di atas navigasi bawah mobile */
    .grade-savebar { bottom: calc(3.85rem + env(safe-area-inset-bottom)); }
}
@media (max-width: 575.98px) {
    .grade-col-student { width: 150px; }
    .grade-savebar-status { font-size: 0.75rem; }
}
</style>
