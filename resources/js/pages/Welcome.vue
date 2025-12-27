<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/layouts/AppLayout.vue';

const { t, locale } = useTrans();
const { getAnonymousId } = useAuth();

type Step = 'idle' | 'recording' | 'uploading' | 'processing' | 'ready';

const currentStep = ref<Step>('idle');
const recordingTime = ref(0);
const audioBlob = ref<Blob | null>(null);
const error = ref<string | null>(null);
const uploadProgress = ref(0);
const processingProgress = ref(0);
const result = ref<{
    id: number;
    ai_title: string | null;
    ai_summary: string | null;
    ai_action_items: string[] | null;
    duration_seconds: number | null;
    created_at: string;
} | null>(null);

let mediaRecorder: MediaRecorder | null = null;
let audioChunks: Blob[] = [];
let intervalId: number | null = null;
let pollInterval: number | null = null;
let progressInterval: number | null = null;

const formattedTime = computed(() => {
    const mins = Math.floor(recordingTime.value / 60);
    const secs = recordingTime.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const startRecording = async () => {
    try {
        error.value = null;
        result.value = null;
        audioChunks = [];
        uploadProgress.value = 0;
        processingProgress.value = 0;

        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);

        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) audioChunks.push(event.data);
        };

        mediaRecorder.onstop = () => {
            const blob = new Blob(audioChunks, { type: 'audio/webm' });
            audioBlob.value = blob;
            stream.getTracks().forEach(track => track.stop());
            autoUpload();
        };

        mediaRecorder.start();
        currentStep.value = 'recording';
        recordingTime.value = 0;
        intervalId = window.setInterval(() => recordingTime.value++, 1000);
    } catch {
        error.value = t('recording.micDenied');
    }
};

const stopRecording = () => {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    if (intervalId) { clearInterval(intervalId); intervalId = null; }
};

const simulateProgress = (setter: (v: number) => void, duration: number) => {
    let progress = 0;
    const step = 100 / (duration / 50);
    progressInterval = window.setInterval(() => {
        progress = Math.min(progress + step + Math.random() * 2, 95);
        setter(progress);
    }, 50);
};

const autoUpload = async () => {
    if (!audioBlob.value) return;
    currentStep.value = 'uploading';
    simulateProgress((v) => uploadProgress.value = v, 1500);

    const formData = new FormData();
    formData.append('audio', audioBlob.value, 'recording.webm');
    formData.append('duration', recordingTime.value.toString());
    formData.append('anonymous_id', getAnonymousId());

    try {
        const response = await fetch('/recordings', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) throw new Error('Upload failed');

        if (progressInterval) clearInterval(progressInterval);
        uploadProgress.value = 100;

        const data = await response.json();
        setTimeout(() => autoProcess(data.id), 300);
    } catch {
        if (progressInterval) clearInterval(progressInterval);
        error.value = t('recording.uploadFailed');
        currentStep.value = 'idle';
    }
};

const autoProcess = async (recordingId: number) => {
    currentStep.value = 'processing';
    simulateProgress((v) => processingProgress.value = v, 8000);

    try {
        const response = await fetch(`/recordings/${recordingId}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });
        if (!response.ok) throw new Error('Process failed');
        startPolling(recordingId);
    } catch {
        if (progressInterval) clearInterval(progressInterval);
        error.value = t('recording.processFailed');
        currentStep.value = 'idle';
    }
};

const startPolling = (recordingId: number) => {
    let attempts = 0;
    const maxAttempts = 60;

    pollInterval = window.setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            stopPolling();
            if (progressInterval) clearInterval(progressInterval);
            error.value = t('recording.timeout');
            currentStep.value = 'idle';
            return;
        }

        try {
            const response = await fetch(`/recordings/${recordingId}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (response.ok) {
                const data = await response.json();
                if (data.recording.status === 'ready') {
                    if (progressInterval) clearInterval(progressInterval);
                    processingProgress.value = 100;
                    setTimeout(() => {
                        result.value = data.recording;
                        currentStep.value = 'ready';
                    }, 500);
                    stopPolling();
                } else if (data.recording.status === 'failed') {
                    if (progressInterval) clearInterval(progressInterval);
                    error.value = t('recording.processFailed');
                    currentStep.value = 'idle';
                    stopPolling();
                }
            }
        } catch { /* Continue */ }
    }, 2000);
};

const stopPolling = () => {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
};

const resetFlow = () => {
    currentStep.value = 'idle';
    recordingTime.value = 0;
    audioBlob.value = null;
    result.value = null;
    error.value = null;
    uploadProgress.value = 0;
    processingProgress.value = 0;
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
};

const formatDuration = (seconds: number | null) => {
    if (!seconds) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
    if (progressInterval) clearInterval(progressInterval);
    stopPolling();
});
</script>

<template>
    <Head :title="t('recording.title')" />

    <AppLayout>
        <div class="min-h-[calc(100vh-8rem)] flex flex-col items-center justify-center px-4 py-8">
            <!-- Hero (only in idle) -->
            <div v-if="currentStep === 'idle'" class="text-center mb-12">
                <h1 class="text-4xl sm:text-5xl font-semibold text-[#0F172A] tracking-tight mb-4">{{ t('app.tagline') }}</h1>
                <p class="text-lg sm:text-xl text-[#334155] max-w-lg mx-auto leading-relaxed">{{ t('app.description') }}</p>
            </div>

            <!-- Error -->
            <div v-if="error" class="mb-6 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm max-w-md w-full text-center">
                {{ error }}
                <button @click="error = null" class="ms-2 underline">×</button>
            </div>

            <!-- IDLE STATE -->
            <template v-if="currentStep === 'idle'">
                <div class="relative mb-8">
                    <button @click="startRecording" class="relative z-10 w-40 h-40 rounded-full flex items-center justify-center bg-[#4F46E5] hover:bg-[#4338CA] hover:scale-105 transition-all duration-300 shadow-lg shadow-[#4F46E5]/30">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-white mb-1" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="5" /></svg>
                            <span class="text-white text-sm font-medium">{{ t('recording.record') }}</span>
                        </div>
                    </button>
                </div>
                <p class="text-[#64748B] text-center max-w-sm">{{ t('recording.clickToStart') }}</p>
            </template>

            <!-- RECORDING STATE -->
            <template v-if="currentStep === 'recording'">
                <div class="relative mb-8">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="absolute w-40 h-40 bg-[#EF4444]/20 rounded-full animate-ping"></div>
                        <div class="absolute w-48 h-48 bg-[#EF4444]/10 rounded-full animate-pulse"></div>
                    </div>
                    <button @click="stopRecording" class="relative z-10 w-40 h-40 rounded-full flex items-center justify-center bg-[#EF4444] hover:bg-[#DC2626] transition-all duration-300 shadow-lg shadow-[#EF4444]/30">
                        <div class="flex flex-col items-center">
                            <svg class="w-10 h-10 text-white mb-1" fill="currentColor" viewBox="0 0 20 20"><rect x="5" y="5" width="10" height="10" rx="1" /></svg>
                            <span class="text-white text-sm font-medium">{{ t('recording.stop') }}</span>
                        </div>
                    </button>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-semibold text-[#0F172A] tabular-nums mb-2">{{ formattedTime }}</div>
                    <p class="text-[#64748B]">{{ t('recording.inProgress') }}</p>
                </div>
            </template>

            <!-- UPLOADING STATE -->
            <template v-if="currentStep === 'uploading'">
                <div class="text-center max-w-md w-full">
                    <div class="relative mb-8">
                        <div class="w-32 h-32 mx-auto relative">
                            <svg class="w-32 h-32 -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" fill="none" stroke="#EEF2FF" stroke-width="8"/>
                                <circle cx="50" cy="50" r="45" fill="none" stroke="#4F46E5" stroke-width="8" stroke-linecap="round"
                                        :stroke-dasharray="`${uploadProgress * 2.83} 283`" class="transition-all duration-300"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-2xl font-bold text-[#4F46E5]">{{ Math.round(uploadProgress) }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-[#EEF2FF] rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#4F46E5] animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-[#0F172A]">{{ t('steps.uploading') }}</h2>
                    </div>
                    <p class="text-[#64748B]">{{ t('steps.uploadingDesc') }}</p>
                </div>
            </template>

            <!-- PROCESSING STATE -->
            <template v-if="currentStep === 'processing'">
                <div class="text-center max-w-md w-full">
                    <div class="relative mb-8">
                        <div class="w-32 h-32 mx-auto relative">
                            <svg class="w-32 h-32 -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" fill="none" stroke="#F0FDF4" stroke-width="8"/>
                                <circle cx="50" cy="50" r="45" fill="none" stroke="#22C55E" stroke-width="8" stroke-linecap="round"
                                        :stroke-dasharray="`${processingProgress * 2.83} 283`" class="transition-all duration-300"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-2xl font-bold text-[#22C55E]">{{ Math.round(processingProgress) }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-[#F0FDF4] rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#22C55E] animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-[#0F172A]">{{ t('steps.processing') }}</h2>
                    </div>
                    <p class="text-[#64748B] mb-6">{{ t('steps.processingDesc') }}</p>

                    <!-- Magic happening animation -->
                    <div class="flex justify-center gap-1">
                        <div class="w-2 h-2 bg-[#22C55E] rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="w-2 h-2 bg-[#22C55E] rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="w-2 h-2 bg-[#22C55E] rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                </div>
            </template>

            <!-- READY STATE - Results -->
            <template v-if="currentStep === 'ready' && result">
                <div class="w-full max-w-2xl animate-fade-in">
                    <!-- Success header -->
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-[#F0FDF4] rounded-full flex items-center justify-center mx-auto mb-4 animate-scale-in">
                            <svg class="w-8 h-8 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <h2 class="text-2xl font-semibold text-[#0F172A]">{{ t('steps.complete') }}</h2>
                    </div>

                    <!-- Title & Meta -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-semibold text-[#0F172A]">{{ result.ai_title || t('notes.untitled') }}</h1>
                        <div class="flex items-center gap-4 mt-2 text-sm text-[#64748B]">
                            <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>{{ formatDuration(result.duration_seconds) }}</span>
                            <span>{{ formatDate(result.created_at) }}</span>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-white border border-[#E5E7EB] rounded-xl p-6 mb-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 bg-[#EEF2FF] rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg></div>
                            <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('summary.title') }}</h2>
                        </div>
                        <p class="text-[#334155] leading-relaxed whitespace-pre-line">{{ result.ai_summary }}</p>
                    </div>

                    <!-- Action Items -->
                    <div v-if="result.ai_action_items?.length" class="bg-white border border-[#E5E7EB] rounded-xl p-6 mb-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 bg-[#F0FDF4] rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg></div>
                            <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('actionItems.title') }}</h2>
                        </div>
                        <ul class="space-y-3">
                            <li v-for="(item, index) in result.ai_action_items" :key="index" class="flex items-start gap-3">
                                <div class="w-5 h-5 mt-0.5 rounded border-2 border-[#E5E7EB] flex-shrink-0"></div>
                                <span class="text-[#334155]">{{ item }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button @click="resetFlow" class="flex-1 px-5 py-3 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors">{{ t('recording.recordAnother') }}</button>
                        <a :href="`/notes/${result.id}`" class="flex-1 px-5 py-3 text-sm font-medium text-center text-[#334155] bg-white border border-[#E5E7EB] rounded-lg hover:bg-[#F8FAFC] transition-colors">{{ t('recording.viewDetails') }}</a>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes scale-in {
    from { transform: scale(0); }
    to { transform: scale(1); }
}
.animate-fade-in { animation: fade-in 0.5s ease-out; }
.animate-scale-in { animation: scale-in 0.3s ease-out; }
</style>
