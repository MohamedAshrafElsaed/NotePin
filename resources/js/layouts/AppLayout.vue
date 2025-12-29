<script setup lang="ts">
import { ref, computed, onUnmounted, provide } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';

const { isRTL, t } = useTrans();
const { getAnonymousId } = useAuth();

interface Props {
    showFab?: boolean;
    showHeader?: boolean;
}

withDefaults(defineProps<Props>(), {
    showFab: true,
    showHeader: true,
});

// Recording state
type RecordingState = 'idle' | 'recording' | 'paused' | 'preview' | 'uploading' | 'error';
const MAX_DURATION = 1800; // 30 minutes in seconds

const state = ref<RecordingState>('idle');
const remainingTime = ref(MAX_DURATION);
const audioBlob = ref<Blob | null>(null);
const audioUrl = ref<string | null>(null);
const error = ref<string | null>(null);
const uploadProgress = ref(0);
const pauseSupported = ref(true);

let mediaRecorder: MediaRecorder | null = null;
let audioChunks: Blob[] = [];
let countdownInterval: number | null = null;
let progressInterval: number | null = null;
let stream: MediaStream | null = null;

const isRecording = computed(() => state.value === 'recording' || state.value === 'paused');
const isActive = computed(() => state.value !== 'idle');
const recordedDuration = computed(() => MAX_DURATION - remainingTime.value);

const formattedCountdown = computed(() => {
    const mins = Math.floor(remainingTime.value / 60);
    const secs = remainingTime.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const formattedRecorded = computed(() => {
    const duration = recordedDuration.value;
    const mins = Math.floor(duration / 60);
    const secs = duration % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

// Start recording immediately on FAB tap
const startRecording = async () => {
    if (state.value !== 'idle') return;

    try {
        error.value = null;
        audioChunks = [];
        remainingTime.value = MAX_DURATION;

        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);

        // Check pause/resume support
        pauseSupported.value = typeof mediaRecorder.pause === 'function' && typeof mediaRecorder.resume === 'function';

        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) audioChunks.push(event.data);
        };

        mediaRecorder.onstop = () => {
            const blob = new Blob(audioChunks, { type: 'audio/webm' });
            audioBlob.value = blob;
            audioUrl.value = URL.createObjectURL(blob);
            stream?.getTracks().forEach(track => track.stop());
            stream = null;
            state.value = 'preview';
        };

        mediaRecorder.onerror = () => {
            error.value = t('recording.error');
            state.value = 'error';
            cleanup();
        };

        mediaRecorder.start(1000); // Collect data every second
        state.value = 'recording';
        startCountdown();
    } catch {
        error.value = t('recording.micDenied');
        state.value = 'error';
    }
};

const startCountdown = () => {
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = window.setInterval(() => {
        if (state.value === 'recording') {
            remainingTime.value--;
            if (remainingTime.value <= 0) {
                stopRecording();
            }
        }
    }, 1000);
};

const pauseRecording = () => {
    if (!mediaRecorder || !pauseSupported.value) return;
    if (mediaRecorder.state === 'recording') {
        mediaRecorder.pause();
        state.value = 'paused';
    }
};

const resumeRecording = () => {
    if (!mediaRecorder || !pauseSupported.value) return;
    if (mediaRecorder.state === 'paused') {
        mediaRecorder.resume();
        state.value = 'recording';
    }
};

const stopRecording = () => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
};

const discardRecording = () => {
    cleanup();
    state.value = 'idle';
};

const cleanup = () => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    audioBlob.value = null;
    audioUrl.value = null;
    remainingTime.value = MAX_DURATION;
    error.value = null;
};

const uploadRecording = async () => {
    if (!audioBlob.value) return;

    state.value = 'uploading';
    uploadProgress.value = 0;

    progressInterval = window.setInterval(() => {
        uploadProgress.value = Math.min(uploadProgress.value + Math.random() * 15, 90);
    }, 100);

    const formData = new FormData();
    formData.append('audio', audioBlob.value, 'recording.webm');
    formData.append('duration', recordedDuration.value.toString());
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
        cleanup();
        state.value = 'idle';
        router.visit(`/notes/${data.id}`);
    } catch {
        if (progressInterval) clearInterval(progressInterval);
        error.value = t('recording.uploadFailed');
        state.value = 'error';
    }
};

const dismissError = () => {
    error.value = null;
    state.value = 'idle';
};

// Provide recording state to child components
provide('recorderState', {
    state,
    isActive,
});

onUnmounted(() => {
    cleanup();
});
</script>

<template>
    <div class="app-layout" :dir="isRTL ? 'rtl' : 'ltr'">
        <!-- Header slot -->
        <slot name="header" />

        <!-- Main Content -->
        <main class="app-main" :class="{ 'has-fab': showFab }">
            <slot />
        </main>

        <!-- Recording Overlay (appears when recording/paused/preview/uploading) -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-full"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-full"
        >
            <div v-if="isActive" class="recorder-overlay">
                <div class="recorder-card">
                    <!-- Error State -->
                    <div v-if="state === 'error'" class="text-center py-6">
                        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <p class="text-[#334155] font-medium mb-2">{{ error }}</p>
                        <button @click="dismissError" class="px-6 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-xl hover:bg-[#4338CA] transition-colors">
                            {{ t('recording.tryAgain') }}
                        </button>
                    </div>

                    <!-- Recording / Paused State -->
                    <div v-else-if="state === 'recording' || state === 'paused'" class="text-center py-4">
                        <!-- Countdown Timer -->
                        <div class="mb-6">
                            <div class="text-5xl font-bold tabular-nums" :class="state === 'paused' ? 'text-[#F59E0B]' : 'text-[#EF4444]'">
                                {{ formattedCountdown }}
                            </div>
                            <p class="text-sm text-[#64748B] mt-2">
                                {{ state === 'paused' ? t('recording.paused') : t('recording.remaining') }}
                            </p>
                        </div>

                        <!-- Recording indicator -->
                        <div class="flex items-center justify-center gap-2 mb-6">
                            <div v-if="state === 'recording'" class="w-3 h-3 bg-[#EF4444] rounded-full animate-pulse"></div>
                            <div v-else class="w-3 h-3 bg-[#F59E0B] rounded-full"></div>
                            <span class="text-sm font-medium text-[#64748B]">{{ formattedRecorded }} {{ t('recording.recorded') }}</span>
                        </div>

                        <!-- Controls -->
                        <div class="flex items-center justify-center gap-4">
                            <!-- Pause/Resume Button -->
                            <div class="flex flex-col items-center">
                                <button
                                    v-if="pauseSupported"
                                    @click="state === 'paused' ? resumeRecording() : pauseRecording()"
                                    class="w-14 h-14 rounded-full flex items-center justify-center transition-colors"
                                    :class="state === 'paused' ? 'bg-[#10B981] hover:bg-[#059669]' : 'bg-[#F59E0B] hover:bg-[#D97706]'"
                                >
                                    <svg v-if="state === 'paused'" class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                    <svg v-else class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                                    </svg>
                                </button>
                                <span v-if="!pauseSupported" class="text-xs text-[#94A3B8] mt-1">{{ t('recording.pauseNotSupported') }}</span>
                            </div>

                            <!-- Stop Button -->
                            <button
                                @click="stopRecording"
                                class="w-16 h-16 rounded-full bg-[#EF4444] hover:bg-[#DC2626] flex items-center justify-center transition-colors shadow-lg"
                            >
                                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <rect x="6" y="6" width="12" height="12" rx="2"/>
                                </svg>
                            </button>

                            <!-- Discard Button -->
                            <button
                                @click="discardRecording"
                                class="w-14 h-14 rounded-full bg-[#64748B] hover:bg-[#475569] flex items-center justify-center transition-colors"
                            >
                                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Preview State -->
                    <div v-else-if="state === 'preview'" class="py-4">
                        <div class="text-center mb-4">
                            <div class="text-2xl font-semibold text-[#0F172A] tabular-nums">{{ formattedRecorded }}</div>
                            <p class="text-sm text-[#64748B]">{{ t('recording.preview') }}</p>
                        </div>

                        <!-- Audio player -->
                        <div class="mb-6 px-2">
                            <audio v-if="audioUrl" :src="audioUrl" controls class="w-full h-12 rounded-lg"></audio>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 px-2">
                            <button
                                @click="discardRecording"
                                class="flex-1 px-4 py-3 text-sm font-medium text-[#334155] bg-white border border-[#E5E7EB] rounded-xl hover:bg-[#F8FAFC] transition-colors"
                            >
                                {{ t('recording.discard') }}
                            </button>
                            <button
                                @click="uploadRecording"
                                class="flex-1 px-4 py-3 text-sm font-medium text-white bg-[#4F46E5] rounded-xl hover:bg-[#4338CA] transition-colors"
                            >
                                {{ t('recording.upload') }}
                            </button>
                        </div>
                    </div>

                    <!-- Uploading State -->
                    <div v-else-if="state === 'uploading'" class="text-center py-6">
                        <div class="w-16 h-16 mx-auto mb-4 relative">
                            <svg class="w-16 h-16 -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#EEF2FF" stroke-width="8"/>
                                <circle
                                    cx="50" cy="50" r="42" fill="none" stroke="#4F46E5" stroke-width="8"
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${uploadProgress * 2.64} 264`"
                                    class="transition-all duration-200"
                                />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-sm font-semibold text-[#4F46E5]">{{ Math.round(uploadProgress) }}%</span>
                            </div>
                        </div>
                        <p class="text-[#334155] font-medium">{{ t('steps.uploading') }}</p>
                        <p class="text-sm text-[#64748B] mt-1">{{ t('steps.uploadingDesc') }}</p>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Floating Mic Button (only when idle) -->
        <button
            v-if="showFab && state === 'idle'"
            @click="startRecording"
            class="fab-mic"
            :aria-label="t('recording.record')"
        >
            <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
.app-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-height: 100dvh;
    background-color: #F8FAFC;
}

.app-main {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}

.app-main.has-fab {
    padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px));
}

.fab-mic {
    position: fixed;
    bottom: calc(24px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    z-index: 50;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background-color: #4F46E5;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
    transition: transform 0.15s ease, background-color 0.15s ease;
}

.fab-mic:hover {
    background-color: #4338CA;
}

.fab-mic:active {
    transform: translateX(-50%) scale(0.95);
}

.recorder-overlay {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 100;
    padding: 16px;
    padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px));
}

.recorder-card {
    max-width: 400px;
    margin: 0 auto;
    background-color: white;
    border-radius: 24px;
    box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.15);
    padding: 16px;
}

audio {
    outline: none;
}

audio::-webkit-media-controls-panel {
    background-color: #F8FAFC;
}
</style>
