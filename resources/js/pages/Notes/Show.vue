<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onUnmounted, onMounted, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthModal from '@/components/AuthModal.vue';

const { t, locale } = useTrans();
const { isAuthenticated } = useAuth();

interface ActionItemFull {
    task: string;
    due_date: string | null;
    owner: string | null;
    project: string | null;
    confidence: 'low' | 'medium' | 'high';
}

interface NoteAction {
    id: number;
    type: 'task' | 'meeting' | 'reminder';
    source_items: string[];
    payload: Record<string, unknown>;
    status: 'open' | 'done' | 'cancelled';
    created_at: string;
}

interface Recording {
    id: number;
    status: string;
    audio_path: string;
    duration_seconds: number | null;
    transcript: string | null;
    ai_title: string | null;
    ai_summary: string | null;
    ai_action_items: string[] | null;
    ai_meta: {
        error?: string;
        input_type?: string;
        language?: string;
        source?: string;
        decision_context?: string;
        action_items_full?: ActionItemFull[];
        user_overrides?: {
            title: string;
            summary: string;
            action_items: string[];
        };
        action_state?: Record<string, { done: boolean }>;
        edited_at?: string;
    } | null;
    created_at: string;
    actions?: NoteAction[];
}

const page = usePage<{ recording: Recording; flash?: { auth_success?: boolean } }>();
const recording = ref<Recording>(page.props.recording);
const pollInterval = ref<number | null>(null);

const shareUrl = ref<string | null>(null);
const isSharing = ref(false);
const copied = ref(false);
const showShareSheet = ref(false);
const showAuthModal = ref(false);

// Edit mode state
const isEditMode = ref(false);
const isSaving = ref(false);
const editTitle = ref('');
const editSummary = ref('');
const editActionItems = ref<string[]>([]);

// Checklist state
const checklistState = ref<Record<string, { done: boolean }>>({});
const saveTimeout = ref<number | null>(null);

// Create action modal
const showCreateModal = ref(false);
const selectedItems = ref<Set<number>>(new Set());
const createType = ref<'task' | 'meeting' | 'reminder'>('task');
const createTitle = ref('');
const createDueDate = ref('');
const createPriority = ref<'low' | 'medium' | 'high'>('medium');
const createMeetingDate = ref('');
const createMeetingTime = ref('');
const createDuration = ref(30);
const createAttendees = ref('');
const createRemindAt = ref('');
const createReminderNote = ref('');
const isCreatingAction = ref(false);

const isReady = computed(() => recording.value?.status === 'ready');
const isFailed = computed(() => recording.value?.status === 'failed');
const isProcessing = computed(() => recording.value?.status === 'processing');

// Hide FAB during processing
const showFab = computed(() => !isProcessing.value);

// Get display values (user overrides or original)
const displayTitle = computed(() => {
    const overrides = recording.value?.ai_meta?.user_overrides;
    return overrides?.title ?? recording.value?.ai_title ?? '';
});

const displaySummary = computed(() => {
    const overrides = recording.value?.ai_meta?.user_overrides;
    return overrides?.summary ?? recording.value?.ai_summary ?? '';
});

const displayActionItems = computed<string[]>(() => {
    const overrides = recording.value?.ai_meta?.user_overrides;
    if (overrides?.action_items) {
        return overrides.action_items;
    }
    const simple = recording.value?.ai_action_items;
    if (simple && Array.isArray(simple)) {
        return simple.map((task) => (typeof task === 'string' ? task : String(task)));
    }
    return [];
});

const actionItemsFull = computed<ActionItemFull[]>(() => {
    const full = recording.value?.ai_meta?.action_items_full;
    if (full && Array.isArray(full)) {
        return full;
    }
    return displayActionItems.value.map((task) => ({
        task,
        due_date: null,
        owner: null,
        project: null,
        confidence: 'medium' as const,
    }));
});

const noteActions = computed(() => recording.value?.actions ?? []);

const hasSelectedItems = computed(() => selectedItems.value.size > 0);

// Initialize edit fields and checklist state
const initEditFields = () => {
    editTitle.value = displayTitle.value;
    editSummary.value = displaySummary.value;
    editActionItems.value = [...displayActionItems.value];
};

const initChecklistState = () => {
    const saved = recording.value?.ai_meta?.action_state ?? {};
    checklistState.value = { ...saved };
};

onMounted(() => {
    initChecklistState();
    if (page.props.flash?.auth_success && isAuthenticated.value) {
        handleShareClick();
    }
    if (recording.value?.status === 'processing') {
        startPolling();
    }
});

watch(() => recording.value?.ai_meta?.action_state, () => {
    initChecklistState();
}, { deep: true });

// Edit mode functions
const enterEditMode = () => {
    initEditFields();
    isEditMode.value = true;
};

const cancelEdit = () => {
    isEditMode.value = false;
};

const saveOverride = async () => {
    if (!recording.value || isSaving.value) {
        return;
    }

    isSaving.value = true;
    try {
        const response = await fetch(`/notes/${recording.value.id}/override`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                title: editTitle.value,
                summary: editSummary.value,
                action_items: editActionItems.value.filter((item) => item.trim()),
            }),
        });

        if (response.ok) {
            const data = await response.json();
            recording.value.ai_meta = data.ai_meta;
            isEditMode.value = false;
        }
    } catch {
        /* Silent fail */
    } finally {
        isSaving.value = false;
    }
};

const addActionItem = () => {
    editActionItems.value.push('');
};

const removeActionItem = (index: number) => {
    editActionItems.value.splice(index, 1);
};

// Checklist functions
const toggleItemDone = (index: number) => {
    const key = String(index);
    const current = checklistState.value[key]?.done ?? false;
    checklistState.value[key] = { done: !current };
    debounceSaveActionState();
};

const isItemDone = (index: number) => {
    return checklistState.value[String(index)]?.done ?? false;
};

const debounceSaveActionState = () => {
    if (saveTimeout.value) {
        clearTimeout(saveTimeout.value);
    }
    saveTimeout.value = window.setTimeout(() => {
        saveActionState();
    }, 500);
};

const saveActionState = async () => {
    if (!recording.value) {
        return;
    }

    try {
        await fetch(`/notes/${recording.value.id}/action-state`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ state: checklistState.value }),
        });
    } catch {
        /* Silent fail */
    }
};

// Selection for create action
const toggleSelectItem = (index: number) => {
    if (selectedItems.value.has(index)) {
        selectedItems.value.delete(index);
    } else {
        selectedItems.value.add(index);
    }
    selectedItems.value = new Set(selectedItems.value);
};

const isItemSelected = (index: number) => selectedItems.value.has(index);

const openCreateModal = () => {
    if (!hasSelectedItems.value) {
        return;
    }
    const items = Array.from(selectedItems.value).map((i) => displayActionItems.value[i]);
    createTitle.value = items[0] || displayTitle.value;
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createType.value = 'task';
    createTitle.value = '';
    createDueDate.value = '';
    createPriority.value = 'medium';
    createMeetingDate.value = '';
    createMeetingTime.value = '';
    createDuration.value = 30;
    createAttendees.value = '';
    createRemindAt.value = '';
    createReminderNote.value = '';
};

const createAction = async () => {
    if (!recording.value || isCreatingAction.value) {
        return;
    }

    isCreatingAction.value = true;
    const items = Array.from(selectedItems.value).map((i) => displayActionItems.value[i]);

    const payload: Record<string, unknown> = {
        type: createType.value,
        selected_items: items,
        title: createTitle.value,
    };

    if (createType.value === 'task') {
        payload.due_date = createDueDate.value || null;
        payload.priority = createPriority.value;
    } else if (createType.value === 'meeting') {
        payload.date = createMeetingDate.value;
        payload.time = createMeetingTime.value;
        payload.duration_minutes = createDuration.value;
        payload.attendees = createAttendees.value || null;
    } else if (createType.value === 'reminder') {
        payload.remind_at = createRemindAt.value;
        payload.reminder_note = createReminderNote.value || null;
    }

    try {
        const response = await fetch(`/notes/${recording.value.id}/actions`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(payload),
        });

        if (response.ok) {
            const data = await response.json();
            if (!recording.value.actions) {
                recording.value.actions = [];
            }
            recording.value.actions.push(data.action);
            selectedItems.value.clear();
            closeCreateModal();
        }
    } catch {
        /* Silent fail */
    } finally {
        isCreatingAction.value = false;
    }
};

const updateActionStatus = async (actionId: number, status: 'open' | 'done' | 'cancelled') => {
    if (!recording.value) {
        return;
    }

    try {
        const response = await fetch(`/notes/${recording.value.id}/actions/${actionId}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ status }),
        });

        if (response.ok) {
            const data = await response.json();
            const idx = recording.value.actions?.findIndex((a) => a.id === actionId);
            if (idx !== undefined && idx >= 0 && recording.value.actions) {
                recording.value.actions[idx] = data.action;
            }
        }
    } catch {
        /* Silent fail */
    }
};

const deleteAction = async (actionId: number) => {
    if (!recording.value) {
        return;
    }

    try {
        const response = await fetch(`/notes/${recording.value.id}/actions/${actionId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (response.ok) {
            recording.value.actions = recording.value.actions?.filter((a) => a.id !== actionId);
        }
    } catch {
        /* Silent fail */
    }
};

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'task':
            return '✓';
        case 'meeting':
            return '📅';
        case 'reminder':
            return '🔔';
        default:
            return '•';
    }
};

const getTypeLabel = (type: string) => {
    switch (type) {
        case 'task':
            return t('actions.task');
        case 'meeting':
            return t('actions.meeting');
        case 'reminder':
            return t('actions.reminder');
        default:
            return type;
    }
};

const statusConfig = computed(() => ({
    processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700', icon: 'spinner' },
    ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700', icon: 'check' },
    failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700', icon: 'error' },
}));

const currentStatus = computed(() => statusConfig.value[recording.value?.status as keyof typeof statusConfig.value] || statusConfig.value.processing);

const shareMessage = computed(() => {
    if (!recording.value || !shareUrl.value) {
        return '';
    }
    const title = displayTitle.value || t('notes.untitled');
    const summary = displaySummary.value ? displaySummary.value.slice(0, 150) + (displaySummary.value.length > 150 ? '...' : '') : '';
    const items = displayActionItems.value.slice(0, 5);
    let msg = `📝 *${title}*\n\n`;
    if (summary) {
        msg += `${summary}\n\n`;
    }
    if (items.length > 0) {
        msg += `✅ Action Items:\n`;
        items.forEach((item) => {
            msg += `• ${item}\n`;
        });
        msg += '\n';
    }
    msg += `🔗 ${shareUrl.value}`;
    return msg;
});

const whatsAppUrl = computed(() => {
    if (!shareMessage.value) {
        return '';
    }
    return `https://wa.me/?text=${encodeURIComponent(shareMessage.value)}`;
});

const startPolling = () => {
    if (pollInterval.value) {
        return;
    }

    let attempts = 0;
    const maxAttempts = 120;

    pollInterval.value = window.setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            stopPolling();
            return;
        }

        try {
            const response = await fetch(`/recordings/${recording.value.id}`, {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                const data = await response.json();
                recording.value = data.recording;
                if (['ready', 'failed'].includes(data.recording.status)) {
                    stopPolling();
                    initChecklistState();
                }
            }
        } catch {
            /* Continue polling */
        }
    }, 2000);
};

const stopPolling = () => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
};

const retryProcessing = async () => {
    if (!recording.value || recording.value.status !== 'failed') {
        return;
    }

    try {
        const response = await fetch(`/recordings/${recording.value.id}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (response.ok) {
            recording.value.status = 'processing';
            startPolling();
        }
    } catch {
        /* Silent fail */
    }
};

const createShare = async (): Promise<string | null> => {
    if (!recording.value) {
        return null;
    }
    if (shareUrl.value) {
        return shareUrl.value;
    }

    isSharing.value = true;
    try {
        const response = await fetch(`/recordings/${recording.value.id}/share`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to create share');
        }

        const data = await response.json();
        shareUrl.value = data.url;
        return data.url;
    } catch {
        return null;
    } finally {
        isSharing.value = false;
    }
};

const handleShareClick = async () => {
    if (!isAuthenticated.value) {
        showAuthModal.value = true;
        return;
    }

    const url = await createShare();
    if (!url) {
        return;
    }

    if (navigator.share) {
        const title = displayTitle.value || t('notes.untitled');
        const summary = displaySummary.value?.slice(0, 100) || '';
        try {
            await navigator.share({
                title: title,
                text: summary ? `${title}\n\n${summary}...` : title,
                url: url,
            });
            isSharing.value = false;
            return;
        } catch (err) {
            if ((err as Error).name === 'AbortError') {
                isSharing.value = false;
                return;
            }
        }
    }

    isSharing.value = false;
    showShareSheet.value = true;
};

const copyShareUrl = async () => {
    if (!shareUrl.value) {
        return;
    }
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        const input = document.createElement('input');
        input.value = shareUrl.value;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
};

const openWhatsApp = () => {
    if (whatsAppUrl.value) {
        window.open(whatsAppUrl.value, '_blank');
    }
};

const getConfidenceColor = (confidence: string) => {
    switch (confidence) {
        case 'high':
            return 'bg-green-100 text-green-700';
        case 'medium':
            return 'bg-yellow-100 text-yellow-700';
        case 'low':
            return 'bg-gray-100 text-gray-600';
        default:
            return 'bg-gray-100 text-gray-600';
    }
};

onUnmounted(() => {
    stopPolling();
    if (saveTimeout.value) {
        clearTimeout(saveTimeout.value);
    }
});

const formatDuration = (seconds: number | null | undefined) => {
    if (!seconds) {
        return '0:00';
    }
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const formatDate = (dateStr: string | undefined) => {
    if (!dateStr) {
        return '';
    }
    const date = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - date.getTime()) / 86400000);
    if (diffDays === 0) {
        return t('time.today');
    }
    if (diffDays === 1) {
        return t('time.yesterday');
    }
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', { month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head :title="displayTitle || t('notes.untitled')" />

    <AppLayout :show-fab="showFab">
        <template #header>
            <header class="sticky top-0 z-40 border-b border-[#E5E7EB] bg-white/95 backdrop-blur-sm">
                <div class="px-4 sm:px-6">
                    <div class="flex h-14 items-center justify-between">
                        <Link
                            :href="isAuthenticated ? '/dashboard' : '/'"
                            class="-ms-2 flex items-center gap-1 px-2 py-1 text-[#64748B] transition-colors hover:text-[#0F172A]"
                        >
                            <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span class="text-sm font-medium">{{ t('nav.back') }}</span>
                        </Link>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="isReady && !isEditMode"
                                @click="enterEditMode"
                                class="flex items-center gap-1.5 rounded-lg border border-[#E5E7EB] px-3 py-1.5 text-sm font-medium text-[#64748B] transition-colors hover:bg-[#F8FAFC]"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ t('edit.button') }}
                            </button>

                            <button
                                v-if="isReady && !isEditMode"
                                @click="handleShareClick"
                                :disabled="isSharing"
                                class="flex items-center gap-1.5 rounded-lg bg-[#4F46E5] px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA] disabled:opacity-70"
                            >
                                <svg v-if="isSharing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                </svg>
                                {{ t('share.button') }}
                            </button>
                        </div>
                    </div>
                </div>
            </header>
        </template>

        <div class="mx-auto w-full max-w-2xl px-4 py-6 sm:px-6">
            <!-- Processing State -->
            <div v-if="isProcessing" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#EEF2FF]">
                    <svg class="h-8 w-8 animate-spin text-[#4F46E5]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                </div>
                <h2 class="mb-1 text-lg font-semibold text-[#0F172A]">{{ t('show.processing') }}</h2>
                <p class="text-[#64748B]">{{ t('show.processingDesc') }}</p>
            </div>

            <!-- Ready State - View Mode -->
            <template v-else-if="isReady && !isEditMode">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="mb-2 text-2xl font-bold text-[#0F172A]">{{ displayTitle || t('notes.untitled') }}</h1>
                    <div class="flex items-center gap-3 text-sm text-[#64748B]">
                        <span>{{ formatDate(recording.created_at) }}</span>
                        <span v-if="recording.duration_seconds">•</span>
                        <span v-if="recording.duration_seconds">{{ formatDuration(recording.duration_seconds) }}</span>
                        <span v-if="recording.ai_meta?.language">•</span>
                        <span v-if="recording.ai_meta?.language" class="uppercase">{{ recording.ai_meta.language }}</span>
                        <span v-if="recording.ai_meta?.edited_at" class="text-[#4F46E5]">• {{ t('edit.edited') }}</span>
                    </div>
                </div>

                <!-- Decision Context -->
                <div v-if="recording.ai_meta?.decision_context" class="mb-6 rounded-xl border border-[#C7D2FE] bg-[#EEF2FF] p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-[#4338CA]">{{ recording.ai_meta.decision_context }}</p>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mb-6">
                    <h2 class="mb-2 text-sm font-semibold tracking-wider text-[#64748B] uppercase">{{ t('summary.title') }}</h2>
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                        <p class="leading-relaxed whitespace-pre-wrap text-[#334155]">{{ displaySummary }}</p>
                    </div>
                </div>

                <!-- Action Items Checklist -->
                <div v-if="displayActionItems.length" class="mb-6">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-sm font-semibold tracking-wider text-[#64748B] uppercase">
                            {{ t('actionItems.title') }}
                            <span class="font-normal text-[#64748B]">({{ displayActionItems.length }})</span>
                        </h2>
                        <button
                            v-if="hasSelectedItems"
                            @click="openCreateModal"
                            class="flex items-center gap-1 rounded-lg bg-[#4F46E5] px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-[#4338CA]"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ t('actions.createFrom') }}
                        </button>
                    </div>
                    <div class="divide-y divide-[#E5E7EB] rounded-xl border border-[#E5E7EB] bg-white">
                        <div v-for="(item, index) in displayActionItems" :key="index" class="p-4">
                            <div class="flex items-start gap-3">
                                <!-- Selection checkbox -->
                                <button
                                    @click="toggleSelectItem(index)"
                                    class="mt-0.5 h-5 w-5 flex-shrink-0 rounded border-2 transition-colors"
                                    :class="isItemSelected(index) ? 'border-[#4F46E5] bg-[#4F46E5]' : 'border-[#E5E7EB] hover:border-[#4F46E5]'"
                                >
                                    <svg v-if="isItemSelected(index)" class="h-full w-full text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>

                                <!-- Done checkbox -->
                                <button
                                    @click="toggleItemDone(index)"
                                    class="mt-0.5 h-5 w-5 flex-shrink-0 rounded-full border-2 transition-colors"
                                    :class="isItemDone(index) ? 'border-green-500 bg-green-500' : 'border-[#E5E7EB] hover:border-green-500'"
                                >
                                    <svg v-if="isItemDone(index)" class="h-full w-full text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>

                                <div class="min-w-0 flex-1">
                                    <p :class="isItemDone(index) ? 'text-[#94A3B8] line-through' : 'text-[#334155]'">{{ item }}</p>
                                    <div v-if="actionItemsFull[index]" class="mt-2 flex flex-wrap items-center gap-2">
                                        <span v-if="actionItemsFull[index].owner" class="inline-flex items-center gap-1 rounded-full bg-[#F1F5F9] px-2 py-0.5 text-xs text-[#475569]">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            {{ actionItemsFull[index].owner }}
                                        </span>
                                        <span v-if="actionItemsFull[index].due_date" class="inline-flex items-center gap-1 rounded-full bg-[#FEF3C7] px-2 py-0.5 text-xs text-[#92400E]">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            {{ actionItemsFull[index].due_date }}
                                        </span>
                                        <span v-if="actionItemsFull[index].project" class="inline-flex items-center gap-1 rounded-full bg-[#DBEAFE] px-2 py-0.5 text-xs text-[#1E40AF]">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                            </svg>
                                            {{ actionItemsFull[index].project }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Created Actions -->
                <div v-if="noteActions.length" class="mb-6">
                    <h2 class="mb-2 text-sm font-semibold tracking-wider text-[#64748B] uppercase">{{ t('actions.created') }}</h2>
                    <div class="space-y-3">
                        <div v-for="action in noteActions" :key="action.id" class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 text-lg">{{ getTypeIcon(action.type) }}</span>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium uppercase text-[#64748B]">{{ getTypeLabel(action.type) }}</span>
                                            <span :class="action.status === 'done' ? 'bg-green-100 text-green-700' : action.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-[#EEF2FF] text-[#4F46E5]'" class="rounded-full px-2 py-0.5 text-xs">
                                                {{ action.status }}
                                            </span>
                                        </div>
                                        <p class="mt-1 font-medium text-[#0F172A]">{{ action.payload.title }}</p>
                                        <div class="mt-1 text-xs text-[#64748B]">
                                            <template v-if="action.type === 'task' && action.payload.due_date">
                                                {{ t('actions.dueDate') }}: {{ action.payload.due_date }}
                                            </template>
                                            <template v-if="action.type === 'meeting'">
                                                {{ action.payload.date }} {{ action.payload.time }} ({{ action.payload.duration_minutes }}min)
                                            </template>
                                            <template v-if="action.type === 'reminder'">
                                                {{ action.payload.remind_at }}
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button v-if="action.status === 'open'" @click="updateActionStatus(action.id, 'done')" class="rounded p-1 text-green-600 hover:bg-green-50" :title="t('actions.markDone')">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                    <button @click="deleteAction(action.id)" class="rounded p-1 text-red-600 hover:bg-red-50" :title="t('actions.delete')">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Edit Mode -->
            <template v-else-if="isReady && isEditMode">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('edit.title') }}</h2>
                    <div class="flex items-center gap-2">
                        <button @click="cancelEdit" class="rounded-lg border border-[#E5E7EB] px-3 py-1.5 text-sm font-medium text-[#64748B] transition-colors hover:bg-[#F8FAFC]">
                            {{ t('edit.cancel') }}
                        </button>
                        <button @click="saveOverride" :disabled="isSaving" class="flex items-center gap-1.5 rounded-lg bg-[#4F46E5] px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA] disabled:opacity-70">
                            <svg v-if="isSaving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {{ t('edit.save') }}
                        </button>
                    </div>
                </div>

                <!-- Edit Title -->
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('edit.noteTitle') }}</label>
                    <input v-model="editTitle" type="text" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                </div>

                <!-- Edit Summary -->
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('summary.title') }}</label>
                    <textarea v-model="editSummary" rows="6" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]"></textarea>
                </div>

                <!-- Edit Action Items -->
                <div class="mb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium text-[#64748B]">{{ t('actionItems.title') }}</label>
                        <button @click="addActionItem" class="flex items-center gap-1 text-sm text-[#4F46E5] hover:text-[#4338CA]">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ t('edit.addItem') }}
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="(item, index) in editActionItems" :key="index" class="flex items-center gap-2">
                            <input v-model="editActionItems[index]" type="text" class="flex-1 rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                            <button @click="removeActionItem(index)" class="rounded p-1 text-red-600 hover:bg-red-50">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Failed State -->
            <div v-else-if="isFailed" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="mb-1 text-lg font-semibold text-[#0F172A]">{{ t('show.failed') }}</h2>
                <p class="mb-6 text-[#64748B]">{{ t('show.failedDesc') }}</p>
                <button @click="retryProcessing" class="rounded-lg bg-[#4F46E5] px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA]">
                    {{ t('show.retryProcessing') }}
                </button>
            </div>

            <!-- Not Found -->
            <div v-else class="py-16 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="mb-2 text-xl font-semibold text-[#0F172A]">{{ t('show.notFound') }}</h2>
                <p class="mb-6 text-[#64748B]">{{ t('show.notFoundDesc') }}</p>
                <Link href="/" class="rounded-lg bg-[#4F46E5] px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA]">
                    {{ t('show.goHome') }}
                </Link>
            </div>
        </div>

        <!-- Share Bottom Sheet -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showShareSheet" class="fixed inset-0 z-50 bg-black/50" @click.self="showShareSheet = false">
                    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
                        <div v-if="showShareSheet" class="pb-safe absolute right-0 bottom-0 left-0 rounded-t-3xl bg-white p-6">
                            <div class="mb-4 flex justify-center">
                                <div class="h-1 w-10 rounded-full bg-[#E5E7EB]"></div>
                            </div>

                            <h3 class="mb-4 text-center text-lg font-semibold text-[#0F172A]">{{ t('share.title') }}</h3>

                            <div class="mb-4 flex items-center gap-2 rounded-xl bg-[#F8FAFC] p-3">
                                <input type="text" :value="shareUrl" readonly class="flex-1 bg-transparent text-sm text-[#334155] outline-none" />
                                <button @click="copyShareUrl" class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors" :class="copied ? 'bg-green-100 text-green-700' : 'bg-[#4F46E5] text-white hover:bg-[#4338CA]'">
                                    {{ copied ? t('share.copied') : t('share.copy') }}
                                </button>
                            </div>

                            <button @click="openWhatsApp" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 font-medium text-white transition-colors hover:bg-[#20BD5A]">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg>
                                WhatsApp
                            </button>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Create Action Modal -->
        <Teleport to="body">
            <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center" @click.self="closeCreateModal">
                    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="translate-y-full sm:translate-y-0 sm:scale-95" enter-to-class="translate-y-0 sm:scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="translate-y-0 sm:scale-100" leave-to-class="translate-y-full sm:translate-y-0 sm:scale-95">
                        <div v-if="showCreateModal" class="pb-safe w-full max-w-md rounded-t-3xl bg-white p-6 sm:rounded-2xl sm:pb-6">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-[#0F172A]">{{ t('actions.createTitle') }}</h3>
                                <button @click="closeCreateModal" class="rounded p-1 text-[#64748B] hover:bg-[#F8FAFC]">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Type Selection -->
                            <div class="mb-4">
                                <label class="mb-2 block text-sm font-medium text-[#64748B]">{{ t('actions.type') }}</label>
                                <div class="flex gap-2">
                                    <button v-for="type in ['task', 'meeting', 'reminder']" :key="type" @click="createType = type as 'task' | 'meeting' | 'reminder'" class="flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors" :class="createType === type ? 'border-[#4F46E5] bg-[#EEF2FF] text-[#4F46E5]' : 'border-[#E5E7EB] text-[#64748B] hover:bg-[#F8FAFC]'">
                                        {{ getTypeLabel(type) }}
                                    </button>
                                </div>
                            </div>

                            <!-- Title -->
                            <div class="mb-4">
                                <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.titleLabel') }}</label>
                                <input v-model="createTitle" type="text" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                            </div>

                            <!-- Task Fields -->
                            <template v-if="createType === 'task'">
                                <div class="mb-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.dueDate') }}</label>
                                        <input v-model="createDueDate" type="date" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.priority') }}</label>
                                        <select v-model="createPriority" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]">
                                            <option value="low">{{ t('actions.priorityLow') }}</option>
                                            <option value="medium">{{ t('actions.priorityMedium') }}</option>
                                            <option value="high">{{ t('actions.priorityHigh') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            <!-- Meeting Fields -->
                            <template v-if="createType === 'meeting'">
                                <div class="mb-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.meetingDate') }}</label>
                                        <input v-model="createMeetingDate" type="date" required class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.meetingTime') }}</label>
                                        <input v-model="createMeetingTime" type="time" required class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                                    </div>
                                </div>
                                <div class="mb-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.duration') }}</label>
                                        <select v-model="createDuration" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]">
                                            <option :value="15">15 min</option>
                                            <option :value="30">30 min</option>
                                            <option :value="45">45 min</option>
                                            <option :value="60">1 hour</option>
                                            <option :value="90">1.5 hours</option>
                                            <option :value="120">2 hours</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.attendees') }}</label>
                                    <input v-model="createAttendees" type="text" :placeholder="t('actions.attendeesPlaceholder')" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                                </div>
                            </template>

                            <!-- Reminder Fields -->
                            <template v-if="createType === 'reminder'">
                                <div class="mb-4">
                                    <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.remindAt') }}</label>
                                    <input v-model="createRemindAt" type="datetime-local" required class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]" />
                                </div>
                                <div class="mb-4">
                                    <label class="mb-1 block text-sm font-medium text-[#64748B]">{{ t('actions.reminderNote') }}</label>
                                    <textarea v-model="createReminderNote" rows="2" class="w-full rounded-lg border border-[#E5E7EB] px-3 py-2 text-[#0F172A] outline-none focus:border-[#4F46E5] focus:ring-1 focus:ring-[#4F46E5]"></textarea>
                                </div>
                            </template>

                            <!-- Submit -->
                            <button @click="createAction" :disabled="isCreatingAction" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#4F46E5] px-4 py-3 font-medium text-white transition-colors hover:bg-[#4338CA] disabled:opacity-70">
                                <svg v-if="isCreatingAction" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ t('actions.create') }}
                            </button>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Auth Modal -->
        <AuthModal :show="showAuthModal" @close="showAuthModal = false" />
    </AppLayout>
</template>
