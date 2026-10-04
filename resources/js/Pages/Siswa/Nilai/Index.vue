<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Card, EmptyState, TableWrapper } from '../../../Components/UI';

interface NilaiItem { id: number; mata_pelajaran: string; rata_akhir?: number | string | null; [key: string]: unknown }
interface NilaiGroup { periode: string; nilai: NilaiItem[] }
interface Props { nilaiGroups?: NilaiGroup[] }

const props = withDefaults(defineProps<Props>(), { nilaiGroups: () => [] });

const fields = [
    { key: 'sum1', label: 'SUM1' },
    { key: 'sum2', label: 'SUM2' },
    { key: 'sum3', label: 'SUM3' },
    { key: 'sum4', label: 'SUM4' },
    { key: 'nilai_harian', label: 'Harian' },
    { key: 'sts', label: 'STS' },
    { key: 'sas', label: 'SAS' },
    { key: 'sat', label: 'SAT' },
];

function display(value: unknown): string | number {
    return typeof value === 'string' || typeof value === 'number' ? value : '-';
}
</script>

<template>
    <Head title="Nilai Saya" />

    <AppShell title="Nilai Saya">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <Badge color="primary">Ringkasan nilai per periode</Badge>
            <span class="text-body-secondary small">Nilai yang terlihat di sini mengikuti data terakhir yang tersedia.</span>
        </div>

        <template v-if="props.nilaiGroups.length">
            <Card
                v-for="group in props.nilaiGroups"
                :key="group.periode"
                :title="group.periode"
                icon="bi-calendar3"
                body-class="p-0"
                class="mb-3"
            >
                <TableWrapper>
                    <div class="p-3 border-bottom bg-light-subtle">
                        <span class="text-body-secondary small">Periode {{ group.periode }}</span>
                    </div>
                    <table class="table table-hover mb-0 nilai-table">
                        <thead class="nilai-thead">
                            <tr>
                                <th scope="col">Mata Pelajaran</th>
                                <th scope="col" v-for="field in fields" :key="field.key" class="text-center">{{ field.label }}</th>
                                <th scope="col" class="text-center average-head">Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in group.nilai" :key="item.id">
                                <td><strong>{{ item.mata_pelajaran }}</strong></td>
                                <td v-for="field in fields" :key="`${item.id}-${field.key}`" class="text-center">
                                    {{ display(item[field.key]) }}
                                </td>
                                <td
                                    class="text-center fw-bold"
                                    :class="Number(item.rata_akhir) >= 75 ? 'text-success' : 'text-danger'"
                                >
                                    {{ display(item.rata_akhir) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </TableWrapper>
            </Card>
        </template>

        <Card v-else>
            <EmptyState title="Belum ada data nilai." icon="bi-bar-chart" />
        </Card>
    </AppShell>
</template>

<style scoped>
.nilai-table {
    font-size: 0.82rem;
}

.nilai-thead {
    background: var(--surface-muted);
}

.average-head {
    background: var(--primary-500);
    color: white;
}
</style>
