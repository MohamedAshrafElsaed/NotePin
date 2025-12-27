<script setup lang="ts">
import { ref, computed, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';

const { t } = useTrans();
const { getAnonymousId } = useAuth();

interface Props {
    show: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{ close: [] }>();

type RecordingState = 'idle' | 'recording' | 'preview' | 'uploading';

const state = ref<RecordingState>('idle');
const recordingTime = ref(0);
const audioBlob = ref<Blob | null>(null);
const audioUrl = ref<string | null>(null);
const error = ref<string | null>(null);
const uploadProgress = ref(0);

let mediaRecorder: MediaRecorder | null = null;
let audioChunks: Blob[] = [];
let intervalId: number | null = null;
let progressInterval: number | null = null;

const formattedTime = computed(() => {
    const mins = Math.floor(recordingTime.value / 60);
    const secs = recordingTime.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const canClose = computed(() => state.value === 'idle' || state.value === 'preview');

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
            state.value = 'preview';
        };

        mediaRecorder.start();
        state.value = 'recording';
        recordingTime.value = 0;
        intervalId = window.setInterval(() => recordingTime.value++, 1000);
    } catch {
        error.value = t('recording.micDenied');
    }
};

const stopRecording = () => {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
};

const discardRecording = () => {
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
    audioBlob.value = null;
    audioUrl.value = null;
    recordingTime.value = 0;
    state.value = 'idle';
};

const uploadRecording = async () => {
    if (!audioBlob.value) return;

    state.value = 'uploading';
    uploadProgress.value = 0;

    // Simulate progress
    progressInterval = window.setInterval(() => {
        uploadProgress.value = Math.min(uploadProgress.value + Math.random() * 15, 90);
    }, 100);

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

        // Clean up
        if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
        audioBlob.value = null;
        audioUrl.value = null;
        state.value = 'idle';

        emit('close');

        // Navigate to the note and trigger processing
        router.visit(`/notes/${data.id}`);
    } catch {
        if (progressInterval) clearInterval(progressInterval);
        error.value = t('recording.uploadFailed');
        state.value = 'preview';
    }
};

const handleClose = () => {
    if (canClose.value) {
        discardRecording();
        emit('close');
    }
};

watch(() => props.show, (newVal) => {
    if (!newVal) {
        // Reset when sheet closes
        if (state.value === 'recording') {
            stopRecording();
        }
        discardRecording();
    }
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
    if (progressInterval) clearInterval(progressInterval);
    if (audioUrl.value) URL.revokeObjectURL(audioUrl.value);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="sheet-backdrop" @click.self="handleClose">
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="translate-y-full"
                    enter-to-class="translate-y-0"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="translate-y-0"
                    leave-to-class="translate-y-full"
                >
                    <div v-if="show" class="sheet-content">
                        <!-- Handle bar -->
                        <div class="flex justify-center pt-3 pb-2">
                            <div class="w-10 h-1 bg-[#E5E7EB] rounded-full"></div>
                        </div>

                        <!-- Close button (only when safe) -->
                        <button
                            v-if="canClose"
                            @click="handleClose"
                            class="absolute top-4 end-4 p-2 text-[#64748B] hover:text-[#0F172A] rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <div class="px-6 pb-8 pt-2">
                            <!-- Error message -->
                            <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm text-center">
                                {{ error }}
                                <button @click="error = null" class="ms-2 font-medium">×</button>
                            </div>

                            <!-- IDLE STATE -->
                            <div v-if="state === 'idle'" class="text-center py-6">
                                <button
                                    @click="startRecording"
                                    class="w-20 h-20 rounded-full bg-[#4F46E5] hover:bg-[#4338CA] flex items-center justify-center mx-auto mb-4 transition-colors shadow-lg shadow-[#4F46E5]/30"
                                >
                                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                    </svg>
                                </button>
                                <p class="text-[#334155] font-medium">{{ t('recording.tapToRecord') }}</p>
                                <p class="text-sm text-[#64748B] mt-1">{{ t('recording.clickToStart') }}</p>
                            </div>

                            <!-- RECORDING STATE -->
                            <div v-if="state === 'recording'" class="text-center py-6">
                                <div class="relative mb-4">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-20 h-20 bg-[#EF4444]/20 rounded-full animate-ping"></div>
                                    </div>
                                    <button
                                        @click="stopRecording"
                                        class="relative w-20 h-20 rounded-full bg-[#EF4444] hover:bg-[#DC2626] flex items-center justify-center mx-auto transition-colors shadow-lg"
                                    >
                                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <rect x="5" y="5" width="10" height="10" rx="1" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-3xl font-semibold text-[#0F172A] tabular-nums mb-2">{{ formattedTime }}</div>
                                <p class="text-[#64748B]">{{ t('recording.inProgress') }}</p>
                            </div>

                            <!-- PREVIEW STATE -->
                            <div v-if="state === 'preview'" class="py-4">
                                <div class="text-center mb-4">
                                    <div class="text-2xl font-semibold text-[#0F172A] tabular-nums">{{ formattedTime }}</div>
                                    <p class="text-sm text-[#64748B]">{{ t('recording.preview') }}</p>
                                </div>

                                <!-- Audio player -->
                                <div class="mb-6">
                                    <audio v-if="audioUrl" :src="audioUrl" controls class="w-full h-12 rounded-lg"></audio>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-3">
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

                            <!-- UPLOADING STATE -->
                            <div v-if="state === 'uploading'" class="text-center py-6">
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
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.sheet-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.sheet-content {
    position: relative;
    width: 100%;
    max-width: 28rem;
    background-color: white;
    border-top-left-radius: 1.5rem;
    border-top-right-radius: 1.5rem;
    padding-bottom: env(safe-area-inset-bottom, 0px);
}

audio {
    outline: none;
}

audio::-webkit-media-controls-panel {
    background-color: #F8FAFC;
}
</style>
