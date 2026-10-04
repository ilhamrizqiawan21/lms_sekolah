<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { SelectInput, TextareaInput, TextInput } from '../Form';
import { Badge, Button, Card, EmptyState, Modal } from '../UI';
import type { CalendarEvent, CalendarEventForm, CalendarPayload, SelectOption } from '../../types';

interface Props {
    calendar: CalendarPayload;
    monthEvents?: CalendarEvent[];
    storeUrl?: string;
    createTitle?: string;
    fixedScope?: string;
    scopeOptions?: SelectOption[];
    readOnly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    monthEvents: () => [], storeUrl: '', createTitle: 'Tambah Event', fixedScope: '', scopeOptions: () => [], readOnly: false,
});

const selectedEvent = ref<CalendarEvent | null>(null);
const canCreate = computed(() => Boolean(props.storeUrl) && !props.readOnly);
const selectedTitle = computed(() => selectedEvent.value?.can_manage ? 'Edit Event' : 'Detail Event');

const createForm = useForm<CalendarEventForm>({
    title: '',
    event_date: props.calendar.today,
    description: '',
    is_holiday: false,
    is_done: false,
    scope: props.fixedScope || String(props.scopeOptions[0]?.value || 'user'),
});
const editForm = useForm<CalendarEventForm>(blankEventForm());

watch(selectedEvent, (event) => {
    if (!event) {
        return;
    }

    editForm.clearErrors();
    editForm.title = event.title ?? '';
    editForm.event_date = event.event_date ?? props.calendar.today;
    editForm.description = event.description ?? '';
    editForm.is_holiday = Boolean(event.is_holiday);
    editForm.is_done = Boolean(event.is_done);
    editForm.scope = event.scope ?? props.fixedScope ?? 'user';
});

function blankEventForm(): CalendarEventForm {
    return {
        title: '',
        event_date: '',
        description: '',
        is_holiday: false,
        is_done: false,
        scope: '',
    };
}

function submitCreate(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.event_date = props.calendar.today;
            createForm.scope = props.fixedScope || String(props.scopeOptions[0]?.value || 'user');
        },
    };

    createForm.transform((data) => ({
        ...data,
        scope: props.fixedScope || data.scope,
    })).post(props.storeUrl, options);
}

function submitEdit(): void {
    if (!selectedEvent.value?.update_url) {
        return;
    }

    editForm.put(selectedEvent.value.update_url, {
        preserveScroll: true,
        onSuccess: () => {
            selectedEvent.value = null;
        },
    });
}

async function destroySelected(): Promise<void> {
    if (!selectedEvent.value?.delete_url) {
        return;
    }

    const confirmed = await window.confirmDialog?.('Hapus event ini?', {
        title: 'Hapus Event',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(selectedEvent.value.delete_url, {
        preserveScroll: true,
        onSuccess: () => {
            selectedEvent.value = null;
        },
    });
}

function toggleDone(event: CalendarEvent): void {
    if (!event.toggle_done_url) {
        return;
    }

    router.patch(event.toggle_done_url, {}, {
        preserveScroll: true,
    });
}

function eventClass(event: CalendarEvent): Record<string, boolean> {
    return {
        'calendar-event-holiday': event.is_holiday,
        'calendar-event-normal': !event.is_holiday,
        'calendar-event-done': event.is_done,
    };
}

function openEvent(event: CalendarEvent): void {
    selectedEvent.value = event;
}
</script>

<template>
    <div class="row">
        <div class="col-lg-8 mb-4">
            <Card :title="calendar.title" icon="bi-calendar3" body-class="p-0">
                <template #actions>
                    <div class="calendar-actions">
                        <a :href="calendar.prev_url" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i> {{ calendar.prev_label }}
                        </a>
                        <a :href="calendar.today_url" class="btn btn-sm btn-outline-primary">Hari Ini</a>
                        <a :href="calendar.next_url" class="btn btn-sm btn-outline-secondary">
                            {{ calendar.next_label }} <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </template>

                <table class="table table-bordered mb-0 calendar-table">
                    <thead>
                        <tr class="text-center calendar-head">
                            <th scope="col" v-for="day in calendar.weekdays" :key="day">{{ day }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(week, rowIndex) in calendar.weeks" :key="rowIndex">
                            <td
                                v-for="cell in week"
                                :key="cell.date || `${rowIndex}-${cell.day}`"
                                class="calendar-cell"
                                :class="{ 'calendar-cell-empty': !cell.date, 'calendar-cell-today': cell.is_today }"
                            >
                                <template v-if="cell.date">
                                    <div class="calendar-day" :class="{ 'calendar-day-today': cell.is_today }">{{ cell.day }}</div>
                                    <button
                                        v-for="event in cell.events"
                                        :key="event.id"
                                        type="button"
                                        class="calendar-event"
                                        :class="eventClass(event)"
                                        :title="event.title"
                                        @click="openEvent(event)"
                                    >
                                        <i v-if="event.is_holiday" class="bi bi-circle-fill me-1" aria-hidden="true"></i>{{ event.title_short }}
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>
        </div>

        <div class="col-lg-4">
            <Card v-if="canCreate" :title="createTitle" icon="bi-plus-circle" class="mb-3">
                <form @submit.prevent="submitCreate">
                    <TextInput v-model="createForm.title" name="title" label="Judul" required :error="createForm.errors.title" wrapper-class="mb-2" />
                    <TextInput v-model="createForm.event_date" type="date" name="event_date" label="Tanggal" required :error="createForm.errors.event_date" wrapper-class="mb-2" />
                    <TextareaInput v-model="createForm.description" name="description" label="Deskripsi" :rows="2" :error="createForm.errors.description" wrapper-class="mb-2" />
                    <SelectInput
                        v-if="scopeOptions.length && !fixedScope"
                        v-model="createForm.scope"
                        name="scope"
                        label="Cakupan"
                        :options="scopeOptions"
                        required
                        :error="createForm.errors.scope"
                        wrapper-class="mb-2"
                    />
                    <div class="mb-2 d-flex gap-3">
                        <div class="form-check">
                            <input id="createHoliday" v-model="createForm.is_holiday" type="checkbox" class="form-check-input">
                            <label class="form-check-label text-sm" for="createHoliday">Libur</label>
                        </div>
                        <div class="form-check">
                            <input id="createDone" v-model="createForm.is_done" type="checkbox" class="form-check-input">
                            <label class="form-check-label text-sm" for="createDone">Selesai</label>
                        </div>
                    </div>
                    <Button type="submit" color="success" icon="bi-save" class="w-100" :disabled="createForm.processing">
                        {{ createForm.processing ? 'Menyimpan...' : 'Simpan' }}
                    </Button>
                </form>
            </Card>

            <Card :title="`Event ${calendar.month_label}`" icon="bi-list-ul" body-class="p-0">
                <div v-if="monthEvents.length">
                    <div
                        v-for="event in monthEvents"
                        :key="event.id"
                        class="calendar-list-item"
                    >
                        <button type="button" class="calendar-list-main" @click="openEvent(event)">
                            <strong class="text-sm" :class="{ 'calendar-list-done': event.is_done }">
                                <i v-if="event.is_holiday" class="bi bi-circle-fill me-1 text-danger" aria-hidden="true"></i>
                                <i v-else-if="event.is_done" class="bi bi-check-circle-fill me-1 text-success" aria-hidden="true"></i>
                                {{ event.title }}
                            </strong>
                            <span class="text-body-secondary text-xs">
                                {{ event.event_date_label }}
                                <template v-if="event.description"> - {{ event.description }}</template>
                            </span>
                            <span v-if="event.created_by" class="text-body-secondary text-xs">Dibuat oleh {{ event.created_by }}</span>
                        </button>
                        <div class="calendar-list-meta">
                            <Badge v-if="event.scope" :color="event.scope === 'school' ? 'info text-dark' : 'secondary'">{{ event.scope }}</Badge>
                            <Button
                                v-if="event.toggle_done_url && !event.is_done"
                                type="button"
                                color="outline-success"
                                size="sm"
                                icon="bi-check-lg"
                                title="Tandai selesai"
                                aria-label="Tandai selesai"
                                @click="toggleDone(event)"
                            />
                        </div>
                    </div>
                </div>
                <EmptyState v-else title="Tidak ada event." icon="bi-calendar3" />
            </Card>
        </div>
    </div>

    <Modal :model-value="!!selectedEvent" :title="selectedTitle" @update:model-value="selectedEvent = null">
        <template v-if="selectedEvent">
            <div v-if="selectedEvent.is_done" class="mb-2"><Badge color="success">Selesai</Badge></div>

            <form id="calendarEditForm" @submit.prevent="submitEdit">
                <TextInput v-model="editForm.title" data-autofocus name="edit_title" label="Judul" required :disabled="!selectedEvent.can_manage" :error="editForm.errors.title" wrapper-class="mb-2" />
                <TextInput v-model="editForm.event_date" type="date" name="edit_event_date" label="Tanggal" required :disabled="!selectedEvent.can_manage" :error="editForm.errors.event_date" wrapper-class="mb-2" />
                <TextareaInput v-model="editForm.description" name="edit_description" label="Deskripsi" :rows="2" :disabled="!selectedEvent.can_manage" :error="editForm.errors.description" wrapper-class="mb-2" />
                <div v-if="selectedEvent.created_by" class="mb-2">
                    <div class="form-label mb-1">Dibuat oleh</div>
                    <div class="text-body-secondary text-sm">{{ selectedEvent.created_by }}</div>
                </div>
                <div class="d-flex gap-3 mb-2">
                    <div class="form-check">
                        <input :id="`eventHoliday${selectedEvent.id}`" v-model="editForm.is_holiday" type="checkbox" class="form-check-input" :disabled="!selectedEvent.can_manage">
                        <label class="form-check-label text-sm" :for="`eventHoliday${selectedEvent.id}`">Libur</label>
                    </div>
                    <div class="form-check">
                        <input :id="`eventDone${selectedEvent.id}`" v-model="editForm.is_done" type="checkbox" class="form-check-input" :disabled="!selectedEvent.can_manage">
                        <label class="form-check-label text-sm" :for="`eventDone${selectedEvent.id}`">Selesai</label>
                    </div>
                </div>

                <div v-if="!selectedEvent.can_manage" class="alert alert-info py-2 mb-0">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    Event ini hanya dapat dilihat.
                </div>
            </form>
        </template>

        <template #footer>
            <Button v-if="selectedEvent?.can_manage" type="button" color="outline-danger" size="" icon="bi-trash" class="me-auto" @click="destroySelected">Hapus</Button>
            <Button type="button" color="outline-secondary" size="" @click="selectedEvent = null">Tutup</Button>
            <Button v-if="selectedEvent?.can_manage" type="submit" form="calendarEditForm" color="primary" size="" :disabled="editForm.processing">
                {{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}
            </Button>
        </template>
    </Modal>
</template>

<style scoped>
.calendar-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.calendar-table {
    table-layout: fixed;
    overflow: hidden;
    border-color: var(--bs-border-color);
}

.calendar-head {
    background: var(--surface-muted);
}

.calendar-head th {
    font-size: 0.75rem;
    padding: 8px 0;
    color: var(--text-muted);
    font-weight: 700;
}

.calendar-cell {
    height: 80px;
    vertical-align: top;
    padding: 4px;
    background: var(--surface-card);
}

.calendar-cell-empty {
    background: var(--surface-subtle);
}

.calendar-cell-today {
    background: var(--status-success-bg);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--status-success-text) 30%, transparent);
}

.calendar-day {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.calendar-day-today {
    font-weight: 800;
    color: var(--status-success-text);
}

.calendar-event {
    display: block;
    width: 100%;
    border: 1px solid transparent;
    border-radius: 4px;
    margin-bottom: 4px;
    padding: 4px;
    text-align: left;
    font-size: 0.68rem;
    line-height: 1.2;
}

.calendar-event-normal {
    background: var(--status-info-bg);
    color: var(--status-info-text);
    border: 1px solid color-mix(in srgb, currentColor 22%, transparent);
}

.calendar-event-normal:hover,
.calendar-event-holiday:hover {
    filter: brightness(1.06);
}

.calendar-event-holiday {
    background: var(--status-danger-bg);
    color: var(--status-danger-text);
    border: 1px solid color-mix(in srgb, currentColor 22%, transparent);
}

.calendar-event-done {
    opacity: 0.55;
    text-decoration: line-through;
}

.calendar-list-item {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--bs-border-color);
    padding: 0.5rem;
}

.calendar-list-main {
    flex: 1;
    min-width: 0;
    border: 0;
    background: transparent;
    color: inherit;
    text-align: left;
    padding: 0;
}

.calendar-list-main span {
    display: block;
}

.calendar-list-done {
    opacity: 0.6;
    text-decoration: line-through;
}

.calendar-list-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

@media (max-width: 575.98px) {
    .calendar-cell {
        height: 64px;
        padding: 2px;
    }

    .calendar-event {
        font-size: 0.58rem;
        padding: 3px;
    }

    .calendar-actions,
    .calendar-actions .btn {
        width: 100%;
    }
}
</style>
