<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import CalendarWorkspace from '../../../Components/Calendar/CalendarWorkspace.vue';
import AcademicTimeline from '../../../Components/Calendar/AcademicTimeline.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import type { CalendarEvent, CalendarPayload, SelectOption, TimelineEvent } from '../../../types';

interface Props { calendar: CalendarPayload; monthEvents?: CalendarEvent[]; timelineEvents?: TimelineEvent[]; storeUrl: string; createTitle?: string; scopeOptions?: SelectOption[]; pageTitle?: string }
withDefaults(defineProps<Props>(), { monthEvents: () => [], timelineEvents: () => [], createTitle: 'Tambah Event', scopeOptions: () => [], pageTitle: 'Kalender dan Reminder' });
</script>

<template>
    <Head :title="pageTitle" />
    <AppShell title="Kalender">
        <PageHeader eyebrow="Aktivitas" :title="pageTitle" subtitle="Atur jadwal, hari libur, dan tenggat akademik dalam satu tampilan." icon="bi-calendar3" />
        <CalendarWorkspace
            :calendar="calendar"
            :month-events="monthEvents"
            :store-url="storeUrl"
            :create-title="createTitle"
            :scope-options="scopeOptions"
        />
        <AcademicTimeline :events="timelineEvents" class="mt-4" />
    </AppShell>
</template>
