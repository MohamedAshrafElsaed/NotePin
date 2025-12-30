<script setup lang="ts">
import { useAuth } from '@/composables/useAuth';
import { useCsrf } from '@/composables/useCsrf';
import { useTrans } from '@/composables/useTrans';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const { t } = useTrans();
const { getAnonymousId } = useAuth();
const { secureFetch } = useCsrf();

const emit = defineEmits<{
    close: [];
}>();

type PanelState = 'input' | 'uploading';

const state = ref<PanelState>('input');
const text = ref('');
const file = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const errors = ref<Record<string, string[]>>({});
const uploadProgress = ref(0);

const MIN_LENGTH = 10;
const MAX_LENGTH = 20000;
const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_EXTENSIONS = ['txt', 'md'];

const charCount = computed(() => text.value.length);
const hasValidText = computed(() => text.value.length >= MIN_LENGTH && text.value.length <= MAX_LENGTH);
const hasValidFile = computed(() => {
    if (!file.value) {
        return false;
    }
    const ext = file.value.name.split('.').pop()?.toLowerCase() || '';
    return ALLOWED_EXTENSIONS.includes(ext) && file.value.size <= MAX_FILE_SIZE;
});
const canSubmit = computed(() => hasValidText.value || hasValidFile.value);

const textError = computed(() => {
    if (text.value.length > 0 && text.value.length < MIN_LENGTH) {
        return t('textInput.validation.textMin');
    }
    if (text.value.length > MAX_LENGTH) {
        return t('textInput.validation.textMax');
    }
    return errors.value.text?.[0] || null;
});

const fileError = computed(() => {
    if (!file.value) {
        return null;
    }
    const ext = file.value.name.split('.').pop()?.toLowerCase() || '';
    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        return t('textInput.validation.fileMimes');
    }
    if (file.value.size > MAX_FILE_SIZE) {
        return t('textInput.validation.fileMax');
    }
    return errors.value.text_file?.[0] || null;
});

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        file.value = input.files[0];
        errors.value = {};
    }
};

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const removeFile = () => {
    file.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const handleClose = () => {
    if (state.value === 'uploading') {
        return;
    }
    emit('close');
};

const handleSubmit = async () => {
    if (!canSubmit.value || state.value === 'uploading') {
        return;
    }

    errors.value = {};
    state.value = 'uploading';
    uploadProgress.value = 0;

    const formData = new FormData();

    // Priority: text over file
    if (text.value.length >= MIN_LENGTH) {
        formData.append('text', text.value);
    } else if (file.value) {
        formData.append('text_file', file.value);
    }

    const anonymousId = getAnonymousId();
    if (anonymousId) {
        formData.append('anonymous_id', anonymousId);
    }

    // Simulate progress
    const progressInterval = setInterval(() => {
        if (uploadProgress.value < 90) {
            uploadProgress.value += Math.random() * 15;
        }
    }, 200);

    try {
        const response = await secureFetch('/notes/text', {
            method: 'POST',
            body: formData,
        });

        clearInterval(progressInterval);

        // Handle CSRF token mismatch (419) - reload page to get fresh token
        if (response.status === 419) {
            state.value = 'input';
            errors.value = { text: [t('textInput.sessionExpired')] };
            // Optionally reload after a delay to get fresh CSRF token
            setTimeout(() => window.location.reload(), 2000);
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            state.value = 'input';
            if (data.errors) {
                errors.value = data.errors;
            } else {
                errors.value = { text: [data.message || t('textInput.error')] };
            }
            return;
        }

        uploadProgress.value = 100;

        // Navigate to the note
        setTimeout(() => {
            router.visit(`/notes/${data.id}`);
        }, 300);
    } catch {
        clearInterval(progressInterval);
        state.value = 'input';
        errors.value = { text: [t('textInput.error')] };
    }
};

// Clear file when text is typed
watch(text, (newVal) => {
    if (newVal.length > 0 && file.value) {
        removeFile();
    }
});
</script>

<template>
    <div class="text-input-overlay" @click.self="handleClose">
        <div class="text-input-card">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-[#E5E7EB] px-4 py-3">
                <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('textInput.title') }}</h2>
                <button
                    @click="handleClose"
                    :disabled="state === 'uploading'"
                    class="flex h-8 w-8 items-center justify-center rounded-full transition-colors hover:bg-[#F1F5F9] disabled:opacity-50"
                >
                    <svg class="h-5 w-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Input State -->
            <div v-if="state === 'input'" class="space-y-4 p-4">
                <!-- Textarea -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[#334155]">
                        {{ t('textInput.pasteLabel') }}
                    </label>
                    <textarea
                        v-model="text"
                        :placeholder="t('textInput.placeholder')"
                        rows="6"
                        class="w-full resize-none rounded-xl border bg-white px-3 py-2.5 text-[#0F172A] transition-colors focus:border-transparent focus:ring-2 focus:ring-[#4F46E5] focus:outline-none"
                        :class="textError ? 'border-red-300' : 'border-[#E5E7EB]'"
                    ></textarea>
                    <div class="mt-1.5 flex items-center justify-between">
                        <p v-if="textError" class="text-xs text-red-500">{{ textError }}</p>
                        <span v-else class="text-xs text-[#94A3B8]">{{
                                t('textInput.charLimit', { min: MIN_LENGTH, max: MAX_LENGTH.toLocaleString() })
                            }}</span>
                        <span class="text-xs tabular-nums" :class="charCount > MAX_LENGTH ? 'text-red-500' : 'text-[#94A3B8]'">
                            {{ charCount.toLocaleString() }} / {{ MAX_LENGTH.toLocaleString() }}
                        </span>
                    </div>
                </div>

                <!-- Divider -->
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-[#E5E7EB]"></div>
                    <span class="text-xs tracking-wider text-[#94A3B8] uppercase">{{ t('textInput.or') }}</span>
                    <div class="h-px flex-1 bg-[#E5E7EB]"></div>
                </div>

                <!-- File Upload -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[#334155]">
                        {{ t('textInput.uploadLabel') }}
                    </label>
                    <input ref="fileInputRef" type="file" accept=".txt,.md" class="hidden" @change="handleFileSelect" />

                    <!-- File selected -->
                    <div v-if="file" class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAFC] px-3 py-2.5">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-[#EEF2FF]">
                            <svg class="h-5 w-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-[#0F172A]">{{ file.name }}</p>
                            <p class="text-xs text-[#94A3B8]">{{ (file.size / 1024).toFixed(1) }} KB</p>
                        </div>
                        <button
                            @click="removeFile"
                            class="flex h-8 w-8 items-center justify-center rounded-full transition-colors hover:bg-[#FEE2E2]"
                        >
                            <svg class="h-4 w-4 text-[#EF4444]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Upload button -->
                    <button
                        v-else
                        @click="triggerFileInput"
                        class="w-full rounded-xl border-2 border-dashed border-[#E5E7EB] bg-[#F8FAFC] px-4 py-6 transition-colors hover:border-[#4F46E5] hover:bg-[#EEF2FF]"
                    >
                        <div class="flex flex-col items-center gap-2">
                            <svg class="h-8 w-8 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                />
                            </svg>
                            <span class="text-sm font-medium">{{ t('textInput.uploadButton') }}</span>
                        </div>
                    </button>

                    <p v-if="fileError" class="mt-1.5 text-xs text-red-500">{{ fileError }}</p>
                    <p v-else class="mt-1.5 text-xs text-[#94A3B8]">{{ t('textInput.fileHint') }}</p>
                </div>

                <!-- Submit Button -->
                <button
                    @click="handleSubmit"
                    :disabled="!canSubmit"
                    class="w-full rounded-xl bg-[#4F46E5] px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-[#4338CA] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ t('textInput.submit') }}
                </button>
            </div>

            <!-- Uploading State -->
            <div v-else-if="state === 'uploading'" class="p-6 text-center">
                <div class="relative mx-auto mb-4 h-16 w-16">
                    <svg class="h-16 w-16 -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="#EEF2FF" stroke-width="8" />
                        <circle
                            cx="50"
                            cy="50"
                            r="42"
                            fill="none"
                            stroke="#4F46E5"
                            stroke-width="8"
                            stroke-linecap="round"
                            :stroke-dasharray="264"
                            :stroke-dashoffset="264 - (264 * uploadProgress) / 100"
                            class="transition-all duration-200"
                        />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-sm font-semibold text-[#4F46E5]">{{ Math.round(uploadProgress) }}%</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-[#0F172A]">{{ t('textInput.processing') }}</p>
                <p class="mt-1 text-xs text-[#64748B]">{{ t('textInput.processingHint') }}</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-input-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    background-color: rgba(0, 0, 0, 0.5);
    padding: 1rem;
}

@media (min-width: 640px) {
    .text-input-overlay {
        align-items: center;
    }
}

.text-input-card {
    width: 100%;
    max-width: 28rem;
    max-height: 90vh;
    overflow-y: auto;
    background-color: white;
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

[dir='rtl'] .text-input-card {
    text-align: right;
}
</style>
