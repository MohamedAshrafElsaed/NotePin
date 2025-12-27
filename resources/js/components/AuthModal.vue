<script setup lang="ts">
import { ref, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';

const { t } = useTrans();

interface Props {
    show: boolean;
    action?: string;
    redirectTo?: string;
}

const props = withDefaults(defineProps<Props>(), {
    action: 'default',
    redirectTo: '/',
});

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const emailInput = ref('');
const emailSent = ref(false);
const emailSending = ref(false);
const emailError = ref<string | null>(null);
const showEmailForm = ref(false);

const getAnonymousId = (): string => {
    let id = localStorage.getItem('notepin_anon_id');
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem('notepin_anon_id', id);
    }
    return id;
};

const handleGoogleAuth = () => {
    const anonymousId = getAnonymousId();
    const params = new URLSearchParams({
        redirect: props.redirectTo,
        action: props.action,
        anonymous_id: anonymousId,
    });
    window.location.href = `/auth/google?${params.toString()}`;
};

const handleEmailSubmit = async () => {
    if (!emailInput.value || emailSending.value) return;

    emailSending.value = true;
    emailError.value = null;

    try {
        const response = await fetch('/auth/email', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                email: emailInput.value,
                redirect: props.redirectTo,
                action: props.action,
                anonymous_id: getAnonymousId(),
            }),
        });

        if (!response.ok) throw new Error('Failed to send');

        emailSent.value = true;
    } catch {
        emailError.value = t('auth.emailError');
    } finally {
        emailSending.value = false;
    }
};

const handleClose = () => {
    emit('close');
};

const resetState = () => {
    emailInput.value = '';
    emailSent.value = false;
    emailSending.value = false;
    emailError.value = null;
    showEmailForm.value = false;
};

watch(() => props.show, (newVal) => {
    if (!newVal) resetState();
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
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="handleClose"
                />

                <!-- Modal -->
                <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl p-6 sm:p-8">
                    <!-- Close Button -->
                    <button
                        @click="handleClose"
                        class="absolute top-4 end-4 p-2 text-[#64748B] hover:text-[#0F172A] hover:bg-[#F8FAFC] rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Header -->
                    <div class="text-center mb-6">
                        <div class="w-14 h-14 bg-[#EEF2FF] rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-[#0F172A]">{{ t('auth.title') }}</h2>
                        <p class="text-[#64748B] mt-1">{{ t('auth.subtitle') }}</p>
                    </div>

                    <!-- Email Sent Success -->
                    <div v-if="emailSent" class="text-center py-4">
                        <div class="w-12 h-12 bg-[#F0FDF4] rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="font-semibold text-[#0F172A] mb-1">{{ t('auth.emailSentTitle') }}</h3>
                        <p class="text-sm text-[#64748B]">{{ t('auth.emailSentDesc') }}</p>
                        <button
                            @click="resetState"
                            class="mt-4 text-sm text-[#4F46E5] hover:text-[#4338CA] font-medium"
                        >
                            {{ t('auth.tryAnotherMethod') }}
                        </button>
                    </div>

                    <!-- Auth Options -->
                    <div v-else class="space-y-3">
                        <!-- Google Button -->
                        <button
                            @click="handleGoogleAuth"
                            class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-white border border-[#E5E7EB] rounded-xl font-medium text-[#0F172A] hover:bg-[#F8FAFC] hover:border-[#D1D5DB] transition-colors"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            {{ t('auth.continueGoogle') }}
                        </button>

                        <!-- Divider -->
                        <div class="relative flex items-center py-2">
                            <div class="flex-1 border-t border-[#E5E7EB]"></div>
                            <span class="px-3 text-sm text-[#64748B]">{{ t('auth.or') }}</span>
                            <div class="flex-1 border-t border-[#E5E7EB]"></div>
                        </div>

                        <!-- Email Form -->
                        <div v-if="showEmailForm">
                            <div class="space-y-3">
                                <input
                                    v-model="emailInput"
                                    type="email"
                                    :placeholder="t('auth.emailPlaceholder')"
                                    class="w-full px-4 py-3 bg-white border border-[#E5E7EB] rounded-xl text-[#0F172A] placeholder-[#64748B] focus:outline-none focus:border-[#4F46E5] focus:ring-2 focus:ring-[#EEF2FF] transition-colors"
                                    @keyup.enter="handleEmailSubmit"
                                />
                                <p v-if="emailError" class="text-sm text-[#EF4444]">{{ emailError }}</p>
                                <button
                                    @click="handleEmailSubmit"
                                    :disabled="!emailInput || emailSending"
                                    class="w-full px-4 py-3 bg-[#4F46E5] text-white rounded-xl font-medium hover:bg-[#4338CA] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                >
                                    <svg v-if="emailSending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    {{ emailSending ? t('auth.sending') : t('auth.sendLink') }}
                                </button>
                            </div>
                        </div>

                        <!-- Email Button -->
                        <button
                            v-else
                            @click="showEmailForm = true"
                            class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-white border border-[#E5E7EB] rounded-xl font-medium text-[#0F172A] hover:bg-[#F8FAFC] hover:border-[#D1D5DB] transition-colors"
                        >
                            <svg class="w-5 h-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{ t('auth.continueEmail') }}
                        </button>
                    </div>

                    <!-- Footer -->
                    <p class="text-xs text-center text-[#64748B] mt-6">
                        {{ t('auth.terms') }}
                    </p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
