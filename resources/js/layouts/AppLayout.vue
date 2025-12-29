<script setup lang="ts">
import TextInputPanel from '@/components/TextInputPanel.vue';
import { useAuth } from '@/composables/useAuth';
import { useTrans } from '@/composables/useTrans';
import { router } from '@inertiajs/vue3';
import { computed, onUnmounted, provide, ref } from 'vue';

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

// Text input state
const showTextInput = ref(false);
const showInputMenu = ref(false);

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

// Toggle input menu
const toggleInputMenu = () => {
    showInputMenu.value = !showInputMenu.value;
};

const closeInputMenu = () => {
    showInputMenu.value = false;
};

const openTextInput = () => {
    showInputMenu.value = false;
    showTextInput.value = true;
};

const closeTextInput = () => {
    showTextInput.value = false;
};

// Start recording immediately on FAB tap
const startRecording = async () => {
    showInputMenu.value = false;

    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });

        const options = { mimeType: 'audio/webm' };
        if (!MediaRecorder.isTypeSupported('audio/webm')) {
            (options as any).mimeType = 'audio/mp4';
        }

        mediaRecorder = new MediaRecorder(stream, options);
        audioChunks = [];

        // Check if pause is supported
        pauseSupported.value = typeof mediaRecorder.pause === 'function';

        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };

        mediaRecorder.onstop = () => {
            const blob = new Blob(audioChunks, { type: mediaRecorder?.mimeType || 'audio/webm' });
            audioBlob.value = blob;
            audioUrl.value = URL.createObjectURL(blob);
            state.value = 'preview';
        };

        mediaRecorder.onerror = () => {
            error.value = t('recording.error');
            state.value = 'error';
            cleanup();
        };

        mediaRecorder.start(1000);
        state.value = 'recording';
        startCountdown();
    } catch (err) {
        error.value = t('recording.micDenied');
        state.value = 'error';
    }
};

const startCountdown = () => {
    countdownInterval = setInterval(() => {
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
        stream.getTracks().forEach((track) => track.stop());
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

    const formData = new FormData();
    formData.append('audio', audioBlob.value, 'recording.webm');
    formData.append('duration', String(recordedDuration.value));

    const anonymousId = getAnonymousId();
    if (anonymousId) {
        formData.append('anonymous_id', anonymousId);
    }

    progressInterval = setInterval(() => {
        if (uploadProgress.value < 90) {
            uploadProgress.value += Math.random() * 10;
        }
    }, 200);

    try {
        const response = await fetch('/recordings', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();
        uploadProgress.value = 100;

        cleanup();
        state.value = 'idle';

        router.visit(`/notes/${data.id}`);
    } catch (err) {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        error.value = t('recording.uploadFailed');
        state.value = 'error';
    }
};

const dismissError = () => {
    cleanup();
    state.value = 'idle';
};

// Provide state for child components
provide('recordingState', state);

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
                    <div v-if="state === 'error'" class="py-6 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>
                        <p class="mb-2 font-medium text-[#334155]">{{ error }}</p>
                        <button
                            @click="dismissError"
                            class="rounded-xl bg-[#4F46E5] px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA]"
                        >
                            {{ t('recording.tryAgain') }}
                        </button>
                    </div>

                    <!-- Recording / Paused State -->
                    <div v-else-if="state === 'recording' || state === 'paused'" class="py-4 text-center">
                        <!-- Countdown Timer -->
                        <div class="mb-6">
                            <div class="text-5xl font-bold tabular-nums" :class="state === 'paused' ? 'text-[#F59E0B]' : 'text-[#EF4444]'">
                                {{ formattedCountdown }}
                            </div>
                            <p class="mt-2 text-sm text-[#64748B]">
                                {{ state === 'paused' ? t('recording.paused') : t('recording.remaining') }}
                            </p>
                        </div>

                        <!-- Recording indicator -->
                        <div class="mb-6 flex items-center justify-center gap-2">
                            <div v-if="state === 'recording'" class="h-3 w-3 animate-pulse rounded-full bg-[#EF4444]"></div>
                            <div v-else class="h-3 w-3 rounded-full bg-[#F59E0B]"></div>
                            <span class="text-sm font-medium text-[#64748B]">{{ formattedRecorded }} {{ t('recording.recorded') }}</span>
                        </div>

                        <!-- Controls -->
                        <div class="flex items-center justify-center gap-4">
                            <!-- Pause/Resume Button -->
                            <div class="flex flex-col items-center">
                                <button
                                    v-if="pauseSupported"
                                    @click="state === 'paused' ? resumeRecording() : pauseRecording()"
                                    class="flex h-14 w-14 items-center justify-center rounded-full transition-colors"
                                    :class="state === 'paused' ? 'bg-[#10B981] hover:bg-[#059669]' : 'bg-[#F59E0B] hover:bg-[#D97706]'"
                                >
                                    <svg v-if="state === 'paused'" class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                    <svg v-else class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" />
                                    </svg>
                                </button>
                                <span v-if="!pauseSupported" class="mt-1 text-xs text-[#94A3B8]">{{ t('recording.pauseNotSupported') }}</span>
                            </div>

                            <!-- Stop Button -->
                            <button
                                @click="stopRecording"
                                class="flex h-16 w-16 items-center justify-center rounded-full bg-[#EF4444] shadow-lg transition-colors hover:bg-[#DC2626]"
                            >
                                <svg class="h-7 w-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <rect x="6" y="6" width="12" height="12" rx="2" />
                                </svg>
                            </button>

                            <!-- Discard Button -->
                            <button
                                @click="discardRecording"
                                class="flex h-14 w-14 items-center justify-center rounded-full bg-[#64748B] transition-colors hover:bg-[#475569]"
                            >
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Preview State -->
                    <div v-else-if="state === 'preview'" class="py-4">
                        <div class="mb-4 text-center">
                            <div class="text-2xl font-semibold text-[#0F172A] tabular-nums">{{ formattedRecorded }}</div>
                            <p class="text-sm text-[#64748B]">{{ t('recording.preview') }}</p>
                        </div>

                        <!-- Audio player -->
                        <div class="mb-6 px-2">
                            <audio v-if="audioUrl" :src="audioUrl" controls class="h-12 w-full rounded-lg"></audio>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 px-2">
                            <button
                                @click="discardRecording"
                                class="flex-1 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm font-medium text-[#334155] transition-colors hover:bg-[#F8FAFC]"
                            >
                                {{ t('recording.discard') }}
                            </button>
                            <button
                                @click="uploadRecording"
                                class="flex-1 rounded-xl bg-[#4F46E5] px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-[#4338CA]"
                            >
                                {{ t('recording.upload') }}
                            </button>
                        </div>
                    </div>

                    <!-- Uploading State -->
                    <div v-else-if="state === 'uploading'" class="py-6 text-center">
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
                                    :stroke-dasharray="`${uploadProgress * 2.64} 264`"
                                    class="transition-all duration-200"
                                />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-sm font-semibold text-[#4F46E5]">{{ Math.round(uploadProgress) }}%</span>
                            </div>
                        </div>
                        <p class="font-medium text-[#334155]">{{ t('steps.uploading') }}</p>
                        <p class="mt-1 text-sm text-[#64748B]">{{ t('steps.uploadingDesc') }}</p>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Text Input Panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <TextInputPanel v-if="showTextInput" @close="closeTextInput" />
        </Transition>

        <!-- Input Menu Backdrop -->
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showInputMenu" class="fixed inset-0 z-40 bg-black/20" @click="closeInputMenu"></div>
        </Transition>

        <!-- Input Menu -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <div v-if="showInputMenu" class="input-menu">
                <button @click="startRecording" class="flex w-full items-center gap-3 rounded-t-xl px-4 py-3 transition-colors hover:bg-[#F8FAFC]">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#EEF2FF]">
                        <svg class="h-5 w-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                            />
                        </svg>
                    </div>
                    <div class="text-start">
                        <p class="text-sm font-medium text-[#0F172A]">{{ t('textInput.menuVoice') }}</p>
                        <p class="text-xs text-[#64748B]">{{ t('textInput.menuVoiceDesc') }}</p>
                    </div>
                </button>
                <div class="mx-4 h-px bg-[#E5E7EB]"></div>
                <button @click="openTextInput" class="flex w-full items-center gap-3 rounded-b-xl px-4 py-3 transition-colors hover:bg-[#F8FAFC]">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#FEF3C7]">
                        <svg class="h-5 w-5 text-[#F59E0B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                            />
                        </svg>
                    </div>
                    <div class="text-start">
                        <p class="text-sm font-medium text-[#0F172A]">{{ t('textInput.menuText') }}</p>
                        <p class="text-xs text-[#64748B]">{{ t('textInput.menuTextDesc') }}</p>
                    </div>
                </button>
            </div>
        </Transition>

        <!-- Floating Add Button (only when idle) -->
        <button
            v-if="showFab && state === 'idle' && !showTextInput"
            @click="toggleInputMenu"
            class="fab-mic"
            :class="{ 'fab-mic-active': showInputMenu }"
            :aria-label="t('recording.record')"
        >
            <svg v-if="showInputMenu" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <svg v-else class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
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
    background-color: #f8fafc;
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
    background-color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
    transition:
        transform 0.15s ease,
        background-color 0.15s ease;
}

.fab-mic:hover {
    background-color: #4338ca;
}

.fab-mic:active {
    transform: translateX(-50%) scale(0.95);
}

.fab-mic-active {
    background-color: #64748b;
}

.fab-mic-active:hover {
    background-color: #475569;
}

.input-menu {
    position: fixed;
    bottom: calc(100px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    z-index: 50;
    width: calc(100% - 32px);
    max-width: 320px;
    background-color: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
    overflow: hidden;
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
    background-color: #f8fafc;
}
</style>
