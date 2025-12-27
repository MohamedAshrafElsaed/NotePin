<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useTrans();

const isRecording = ref(false);
const recordingTime = ref(0);
const audioBlob = ref<Blob | null>(null);
const audioUrl = ref<string | null>(null);
const isUploading = ref(false);
const error = ref<string | null>(null);

let mediaRecorder: MediaRecorder | null = null;
let audioChunks: Blob[] = [];
let intervalId: number | null = null;

const formattedTime = computed(() => {
    const mins = Math.floor(recordingTime.value / 60);
    const secs = recordingTime.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const hasRecording = computed(() => audioBlob.value !== null);

const startRecording = async () => {
    try {
        error.value = null;
        audioChunks = [];
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);

        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) audioChunks.push(event.data);
        };

        mediaRecorder.onstop = () => {
            const blob = new Blob(audioChunks, { type: 'audio/webm' });
            audioBlob.value = blob;
            audioUrl.value = URL.createObjectURL(blob);
            stream.getTracks().forEach(track => track.stop());
        };

        mediaRecorder.start();
        isRecording.value = true;
        recordingTime.value = 0;
        intervalId = window.setInterval(() => recordingTime.value++, 1000);
    } catch {
        error.value = t('recording.micDenied');
    }
};

const stopRecording = () => {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    isRecording.value = false;
    if (intervalId) { clearInterval(intervalId); intervalId = null; }
};

const discardRecording = () => {
    if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
    audioBlob.value = null;
    audioUrl.value = null;
    recordingTime.value = 0;
    error.value = null;
};

const uploadRecording = async () => {
    if (!audioBlob.value) return;
    isUploading.value = true;
    error.value = null;

    const formData = new FormData();
    formData.append('audio', audioBlob.value, 'recording.webm');
    formData.append('duration', recordingTime.value.toString());

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
        const data = await response.json();
        router.visit(`/notes/${data.id}`);
    } catch {
        error.value = t('recording.uploadFailed');
        isUploading.value = false;
    }
};

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
    if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
});
</script>

<template>
    <Head :title="t('recording.title')" />

    <AppLayout>
        <div class="min-h-[calc(100vh-8rem)] flex flex-col items-center justify-center px-4">
            <!-- Hero Section -->
            <div class="text-center mb-12">
                <h1 class="text-4xl sm:text-5xl font-semibold text-[#0F172A] tracking-tight mb-4">
                    {{ t('app.tagline') }}
                </h1>
                <p class="text-lg sm:text-xl text-[#334155] max-w-lg mx-auto leading-relaxed">
                    {{ t('app.description') }}
                </p>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mb-6 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                {{ error }}
            </div>

            <!-- Recording State: Not Recording, No Recording -->
            <template v-if="!isRecording && !hasRecording">
                <div class="relative mb-8">
                    <button
                        @click="startRecording"
                        class="relative z-10 w-40 h-40 rounded-full flex items-center justify-center bg-[#4F46E5] hover:bg-[#4338CA] hover:scale-105 transition-all duration-300"
                    >
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-white mb-1" fill="currentColor" viewBox="0 0 20 20">
                                <circle cx="10" cy="10" r="5" />
                            </svg>
                            <span class="text-white text-sm font-medium">{{ t('recording.record') }}</span>
                        </div>
                    </button>
                </div>
                <p class="text-[#64748B] text-center max-w-sm">{{ t('recording.clickToStart') }}</p>
            </template>

            <!-- Recording State: Recording -->
            <template v-if="isRecording">
                <div class="relative mb-8">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="absolute w-40 h-40 bg-[#4F46E5]/20 rounded-full animate-ping"></div>
                    </div>
                    <button
                        @click="stopRecording"
                        class="relative z-10 w-40 h-40 rounded-full flex items-center justify-center bg-[#EF4444] hover:bg-[#DC2626] scale-95 transition-all duration-300"
                    >
                        <div class="flex flex-col items-center">
                            <svg class="w-10 h-10 text-white mb-1" fill="currentColor" viewBox="0 0 20 20">
                                <rect x="5" y="5" width="10" height="10" rx="1" />
                            </svg>
                            <span class="text-white text-sm font-medium">{{ t('recording.stop') }}</span>
                        </div>
                    </button>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-semibold text-[#0F172A] tabular-nums">{{ formattedTime }}</div>
                    <p class="text-[#64748B] mt-1">{{ t('recording.inProgress') }}</p>
                </div>
            </template>

            <!-- Recording State: Has Recording (Preview) -->
            <template v-if="!isRecording && hasRecording">
                <div class="w-full max-w-md">
                    <div class="bg-white border border-[#E5E7EB] rounded-xl p-6 mb-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-[#EEF2FF] rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-[#0F172A]">{{ t('recording.ready') }}</p>
                                <p class="text-sm text-[#64748B]">{{ t('recording.duration') }}: {{ formattedTime }}</p>
                            </div>
                        </div>

                        <audio :src="audioUrl!" controls class="w-full mb-4" dir="ltr"></audio>

                        <div class="flex gap-3">
                            <button
                                @click="discardRecording"
                                :disabled="isUploading"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-[#334155] bg-white border border-[#E5E7EB] rounded-lg hover:bg-[#F8FAFC] transition-colors disabled:opacity-50"
                            >
                                {{ t('recording.discard') }}
                            </button>
                            <button
                                @click="uploadRecording"
                                :disabled="isUploading"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <svg v-if="isUploading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ isUploading ? t('recording.uploading') : t('recording.upload') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
