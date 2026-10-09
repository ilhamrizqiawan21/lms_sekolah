<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../Components/UI';

import type { GradeGroup, Score, ScoreField } from '../../../types/assessment';

const props = withDefaults(defineProps<{
    kelasMapel?: { id: number; label: string }[];
    tahunAjaran?: { id: number; tahun: string } | null;
    semester?: string;
    groups?: GradeGroup[];
    storeUrl: string;
    indexUrl: string;
}>(), { kelasMapel: () => [], tahunAjaran: null, semester: '1', groups: () => [] });

const fieldGroups: { key: ScoreField; label: string; readonly?: boolean }[] = [
    { key: 'sum1', label: 'SUM1' },
    { key: 'sum2', label: 'SUM2' },
    { key: 'sum3', label: 'SUM3' },
    { key: 'sum4', label: 'SUM4' },
    { key: 'sts', label: 'Nilai' },
    { key: 'sas', label: 'Nilai' },
    { key: 'sat', label: 'Nilai' },
];
const editableFieldKeys = fieldGroups.filter((field) => !field.readonly).map((field) => field.key);

const selectedKelasMapelId = ref(props.kelasMapel[0]?.id ?? null);
const pasteStatus = ref('');

const form = useForm({
    semester: props.semester,
    kelas_mapel_ids: selectedKelasMapelId.value ? [selectedKelasMapelId.value] : [],
    nilai: buildNilai(),
});

const activeGroup = computed(() => props.groups.find((group) => group.kelas_mapel_id === selectedKelasMapelId.value) ?? null);
const taskFields = computed(() => activeGroup.value?.tugas_harian ?? []);

watch(() => props.groups, () => {
    form.semester = props.semester;
    if (!props.kelasMapel.some((item) => item.id === selectedKelasMapelId.value)) {
        selectedKelasMapelId.value = props.kelasMapel[0]?.id ?? null;
    }
    form.kelas_mapel_ids = selectedKelasMapelId.value ? [selectedKelasMapelId.value] : [];
    form.nilai = buildNilai();
}, { deep: true });

watch(selectedKelasMapelId, (value) => {
    form.kelas_mapel_ids = value ? [value] : [];
    pasteStatus.value = '';
});

function roundScores(scores: Record<string, Score | undefined> | undefined) {
    return Object.fromEntries(Object.entries(scores ?? {}).map(([key, value]) => [
        key,
        value === null || value === undefined || value === '' || Number.isNaN(Number(value)) ? value : String(Math.round(Number(value))),
    ])) as typeof scores;
}

function buildNilai() {
    return Object.fromEntries(props.groups.map((group) => [
        String(group.kelas_mapel_id),
        Object.fromEntries(group.students.map((student) => [
            String(student.id),
            { ...roundScores(student.scores) },
        ])),
    ]));
}

function scoreClass(value: Score | undefined) {
    if (value === null || value === undefined || value === '') return '';
    if (Number(value) >= 92) return 'excellent';
    if (Number(value) >= 83) return 'good';
    if (Number(value) >= 75) return 'fair';
    return 'low';
}

function formatScore(value: Score | undefined) {
    if (value === null || value === undefined || value === '') return null;
    return String(Math.round(Number(value)));
}

function normalizeScore(value: Score | undefined) {
    return String(value ?? '').trim().replace(',', '.');
}

function parseScoreText(text: string) {
    if (!/[\r\n\t\u2028\u2029]/.test(text)) return [];

    const rows = text.replace(/\r\n?|\u2028|\u2029/g, '\n').split('\n');
    while (rows.length > 1 && rows.at(-1) === '') rows.pop();

    return rows.map((row) => row.split('\t').map(normalizeScore));
}

function parseClipboardHtml(html: string) {
    if (!html || typeof DOMParser === 'undefined') return [];

    const document = new DOMParser().parseFromString(html, 'text/html');
    return Array.from(document.querySelectorAll('table tr'))
        .map((row) => Array.from(row.querySelectorAll('th, td')).map((cell) => normalizeScore(cell.textContent)))
        .filter((row) => row.length);
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
    const group = activeGroup.value;
    const student = group?.students[studentIndex];
    if (!group || !student || !editableFieldKeys.includes(fieldKey)) return false;

    const groupId = String(group.kelas_mapel_id);
    const studentId = String(student.id);
    const studentScores = form.nilai[groupId]?.[studentId];
    if (!studentScores) return false;

    form.nilai[groupId][studentId] = {
        ...studentScores,
        [fieldKey]: score,
    };

    return true;
}

async function applyScoreGrid(grid: string[][], studentIndex: number, fieldKey: ScoreField) {
    const startFieldIndex = editableFieldKeys.indexOf(fieldKey);
    if (startFieldIndex < 0) return;

    const isSingleColumn = grid.every((row) => row.length === 1);
    const targetFields = editableFieldKeys.slice(startFieldIndex);
    let pastedCount = 0;
    const lastTarget: { value: { studentIndex: number; fieldKey: ScoreField } | null } = { value: null };

    grid.forEach((row, rowOffset) => {
        const scores = isSingleColumn ? [row[0]] : row;
        const fields = isSingleColumn ? [fieldKey] : targetFields;

        scores.forEach((score, columnOffset) => {
            const targetField = fields[columnOffset];
            if (targetField && setStudentScore(studentIndex + rowOffset, targetField, score)) {
                pastedCount += 1;
                lastTarget.value = { studentIndex: studentIndex + rowOffset, fieldKey: targetField };
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
    if (!grid.length) return;

    event.preventDefault();
    applyScoreGrid(grid, studentIndex, fieldKey);
}

function handleScoreInput(event: Event, studentIndex: number, fieldKey: ScoreField) {
    if (!(event.target instanceof HTMLTextAreaElement)) return;
    const grid = parseScoreText(event.target.value);
    if (grid.length) applyScoreGrid(grid, studentIndex, fieldKey);
}

function onKelasMapelChange() {
    if (!selectedKelasMapelId.value) return;

    // Fetch only the newly-selected class's roster/scores instead of
    // shipping every class's data up front on the initial page load.
    router.get(props.indexUrl, { kelas_mapel_id: selectedKelasMapelId.value }, {
        only: ['groups'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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

    form.kelas_mapel_ids = selectedKelasMapelId.value ? [selectedKelasMapelId.value] : [];

    const groupId = String(selectedKelasMapelId.value ?? '');
    Object.values(form.nilai[groupId] ?? {}).forEach((scores) => {
        editableFieldKeys.forEach((fieldKey) => {
            scores[fieldKey] = normalizeScore(scores[fieldKey]);
        });
    });

    form.post(props.storeUrl, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Nilai" />

    <AppShell title="Nilai">
        <PageHeader
            eyebrow="Penilaian"
            title="Input Nilai"
            subtitle="Pilih kelas, lalu isi nilai siswa. Nilai harian dihitung otomatis dari tugas."
        />

        <form v-if="kelasMapel.length" @submit.prevent="submit">
            <Card title="Kelas dan Mata Pelajaran" icon="bi-funnel" class="mb-4">
                <label for="kelas-mapel" class="form-label">Kelas Aktif</label>
                <select id="kelas-mapel" v-model="selectedKelasMapelId" class="form-select" @change="onKelasMapelChange">
                    <option v-for="item in kelasMapel" :key="item.id" :value="item.id">
                        {{ item.label }}
                    </option>
                </select>
                <div v-if="form.errors.kelas_mapel_ids" class="text-danger small mt-2">
                    {{ form.errors.kelas_mapel_ids }}
                </div>
            </Card>

            <Card
                v-if="activeGroup"
                :title="`${activeGroup.mata_pelajaran} - ${activeGroup.kelas}`"
                icon="bi-table"
                body-class="p-0"
                class="mb-4"
            >
                <template #actions>
                    <div class="d-flex align-items-center gap-2">
                        <a :href="activeGroup.export_excel_url" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Excel
                        </a>
                        <a :href="activeGroup.export_pdf_url" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> PDF
                        </a>
                    </div>
                </template>

                <div class="grade-toolbar">
                    <span class="grade-hint"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Enter: pindah ke siswa berikutnya. Tempel langsung dari spreadsheet untuk mengisi banyak nilai.</span>
                    <span class="d-flex align-items-center gap-2">
                        <Badge v-if="pasteStatus" color="success">{{ pasteStatus }}</Badge>
                        <Badge color="secondary">{{ activeGroup.students.length }} siswa</Badge>
                    </span>
                </div>
                <TableWrapper :scroll-hint="false">
                    <table class="table table-bordered table-hover app-table grade-table mb-0">
                        <colgroup>
                            <col class="grade-col-student">
                            <col v-for="field in fieldGroups" :key="`col-${field.key}`" class="grade-col-score">
                            <col v-for="task in taskFields" :key="`col-${task.id}`" class="grade-col-score">
                            <col class="grade-col-total">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" rowspan="2" class="grid-sticky-col">Siswa</th>
                                <th scope="colgroup" colspan="4" class="text-center bg-soft-success">Sumatif Harian</th>
                                <th scope="colgroup" :colspan="Math.max(taskFields.length, 1)" class="text-center bg-soft-success">Nilai Harian</th>
                                <th scope="col" class="text-center bg-soft-warning">STS</th>
                                <th scope="col" class="text-center bg-soft-warning">SAS</th>
                                <th scope="col" class="text-center bg-soft-danger">SAT</th>
                                <th scope="col" rowspan="2" class="text-center bg-soft-muted">Rata-rata<br>Akhir</th>
                            </tr>
                            <tr>
                                <th scope="col" v-for="field in fieldGroups.slice(0, 4)" :key="field.key" class="text-center w-score">
                                    {{ field.label }}
                                </th>
                                <th scope="col" v-for="task in taskFields" :key="task.id" class="text-center w-score" :title="task.judul">
                                    {{ task.label }}
                                </th>
                                <th scope="col" v-for="field in fieldGroups.slice(4)" :key="field.key" class="text-center w-score">
                                    {{ field.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(student, studentIndex) in activeGroup.students" :key="`${activeGroup.kelas_mapel_id}-${student.id}`">
                                <th scope="row" class="grid-sticky-col identity-cell">
                                    <span class="identity-name">{{ student.nama }}</span>
                                    <span class="identity-meta">{{ student.no }}. NIS {{ student.nis }}</span>
                                </th>
                                <template v-for="field in fieldGroups.slice(0, 4)" :key="`${activeGroup.kelas_mapel_id}-${student.id}-${field.key}`">
                                <td class="text-center">
                                    <span
                                        v-if="field.readonly"
                                        class="score-result readonly-score"
                                        :class="scoreClass(form.nilai[String(activeGroup.kelas_mapel_id)][String(student.id)][field.key])"
                                    >
                                        {{ formatScore(form.nilai[String(activeGroup.kelas_mapel_id)][String(student.id)][field.key]) ?? '-' }}
                                    </span>
                                    <textarea
                                        v-else
                                        v-model="form.nilai[String(activeGroup.kelas_mapel_id)][String(student.id)][field.key]"
                                        rows="1"
                                        inputmode="decimal"
                                        class="form-control form-control-sm score-input"
                                        autocomplete="off"
                                        placeholder="-"
                                        :data-student-index="studentIndex"
                                        :data-field-key="field.key"
                                        @keydown.enter="handleScoreEnter($event, studentIndex, field.key)"
                                        @paste.stop="handleScorePaste($event, studentIndex, field.key)"
                                        @input="handleScoreInput($event, studentIndex, field.key)"
                                        @focus="($event.target as HTMLTextAreaElement).select()"
                                    ></textarea>
                                </td>
                                </template>
                                <td v-for="task in taskFields" :key="`${activeGroup.kelas_mapel_id}-${student.id}-${task.id}`" class="text-center">
                                    <span class="score-result readonly-score" :class="scoreClass(student.task_scores?.[task.label])" :title="task.judul">
                                        {{ formatScore(student.task_scores?.[task.label]) ?? '-' }}
                                    </span>
                                </td>
                                <template v-for="field in fieldGroups.slice(4)" :key="`${activeGroup.kelas_mapel_id}-${student.id}-${field.key}`">
                                <td class="text-center">
                                    <textarea
                                        v-model="form.nilai[String(activeGroup.kelas_mapel_id)][String(student.id)][field.key]"
                                        rows="1"
                                        inputmode="decimal"
                                        class="form-control form-control-sm score-input"
                                        autocomplete="off"
                                        placeholder="-"
                                        :data-student-index="studentIndex"
                                        :data-field-key="field.key"
                                        @keydown.enter="handleScoreEnter($event, studentIndex, field.key)"
                                        @paste.stop="handleScorePaste($event, studentIndex, field.key)"
                                        @input="handleScoreInput($event, studentIndex, field.key)"
                                        @focus="($event.target as HTMLTextAreaElement).select()"
                                    ></textarea>
                                </td>
                                </template>
                                <td class="text-center">
                                    <strong v-if="formatScore(student.rata_akhir)" class="score-result" :class="scoreClass(student.rata_akhir)">
                                        {{ formatScore(student.rata_akhir) }}
                                    </strong>
                                    <span v-else class="text-body-secondary">-</span>
                                </td>
                            </tr>
                            <tr v-if="!activeGroup.students.length">
                                <td :colspan="9 + taskFields.length">
                                    <EmptyState title="Tidak ada siswa di kelas ini." icon="bi-people" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </TableWrapper>

                <div class="sticky-savebar">
                    <span class="sticky-savebar-status" role="status" aria-live="polite">
                        <template v-if="form.isDirty"><i class="bi bi-circle-fill dirty-dot" aria-hidden="true"></i>Belum disimpan<span class="d-none d-sm-inline">: ada perubahan nilai</span></template>
                        <template v-else-if="form.recentlySuccessful"><i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>Tersimpan</template>
                    </span>
                    <Button type="submit" color="primary" icon="bi-save" :loading="form.processing" :disabled="!activeGroup">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Nilai' }}
                    </Button>
                </div>
            </Card>

            <Card v-else>
                <EmptyState title="Pilih kelas penugasan." icon="bi-funnel" />
            </Card>
        </form>

        <Card v-else>
            <EmptyState title="Anda belum memiliki penugasan" icon="bi-bar-chart" />
        </Card>
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

@media (max-width: 575.98px) {
    .grade-col-student { width: 150px; }
}
</style>
