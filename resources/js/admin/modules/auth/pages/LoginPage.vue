<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue';
import { Translation, useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useCountdown } from '@/shared/composables/useCountdown';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import OtpInput from '@/shared/ui/OtpInput.vue';
import PhoneField from '@/shared/ui/PhoneField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { safeRedirect } from '@/shared/utils/redirect';
import { adminAuthApi } from '../api';
import { useAdminAuthStore } from '../store';
import type { StaffUser } from '../types';

type Method = 'password' | 'otp';

const { t } = useI18n();
const { localizeDigits } = useLocale();
const router = useRouter();
const route = useRoute();
const auth = useAdminAuthStore();

const method = ref<Method>('password');
const step = ref<'credentials' | 'code'>('credentials');
const phone = ref('');
const password = ref('');
const code = ref('');
const codeLength = ref(5);
const otpInput = useTemplateRef('otpInput');
const form = useApiForm(['phone_number', 'password', 'code'] as const);
const countdown = useCountdown();

const methods = computed(() => [
    { value: 'password' as const, label: t('auth.login.methodPassword') },
    { value: 'otp' as const, label: t('auth.login.methodOtp') },
]);

watch(method, () => form.clearErrors());

async function finishLogin(user: StaffUser): Promise<void> {
    auth.setUser(user);
    await router.replace(safeRedirect(route.query.redirect, '/'));
}

async function loginWithPassword(): Promise<void> {
    const user = await form.submit(() => adminAuthApi.loginWithPassword(phone.value, password.value));

    if (user) {
        await finishLogin(user);
    }
}

async function sendCode(): Promise<void> {
    const challenge = await form.submit(() => adminAuthApi.sendLoginCode(phone.value));

    if (challenge) {
        codeLength.value = challenge.code_length;
        countdown.start(challenge.resend_available_in);
        code.value = '';
        step.value = 'code';
        otpInput.value?.reset();
    } else if (form.lastError.value?.retryAfter) {
        countdown.start(form.lastError.value.retryAfter);
    }
}

async function loginWithCode(): Promise<void> {
    if (code.value.length < codeLength.value) {
        return;
    }

    const user = await form.submit(() => adminAuthApi.loginWithCode(phone.value, code.value));

    if (user) {
        await finishLogin(user);
    } else {
        otpInput.value?.reset();
    }
}

function changePhone(): void {
    form.clearErrors();
    countdown.stop();
    step.value = 'credentials';
}

function submit(): void {
    if (step.value === 'code') {
        void loginWithCode();
    } else {
        void (method.value === 'password' ? loginWithPassword() : sendCode());
    }
}
</script>

<template>
    <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
        <div class="flex flex-col gap-3">
            <span class="flex size-12 items-center justify-center rounded-[14px] bg-surface-muted text-primary">
                <AppIcon name="shield" :size="24" />
            </span>
            <h1 class="text-2xl font-bold">{{ t('admin.loginTitle') }}</h1>
            <p v-if="step === 'credentials'" class="text-sm leading-relaxed text-fg-muted">
                {{ t('admin.loginSubtitle') }}
            </p>
            <p v-else class="text-sm leading-relaxed text-fg-muted">
                <Translation keypath="auth.verify.sentTo" scope="global">
                    <template #length>{{ localizeDigits(codeLength) }}</template>
                    <template #phone>
                        <bdi dir="ltr" class="font-semibold text-fg">{{ localizeDigits(phone) }}</bdi>
                    </template>
                </Translation>
                {{ ' ' }}
                <button
                    type="button"
                    class="cursor-pointer rounded font-semibold text-primary hover:text-primary-hover focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="changePhone"
                >
                    {{ t('admin.changePhone') }}
                </button>
            </p>
        </div>

        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />

        <template v-if="step === 'credentials'">
            <SegmentedControl v-model="method" :label="t('auth.login.method')" :options="methods" />

            <PhoneField v-model="phone" :error="form.errors.phone_number" autocomplete="username" autofocus />

            <TextField
                v-if="method === 'password'"
                v-model="password"
                :label="t('auth.fields.password')"
                type="password"
                icon="lock"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <AppButton type="submit" :loading="form.processing.value">
                {{ method === 'password' ? t('admin.submit') : t('auth.login.sendCode') }}
            </AppButton>
        </template>

        <template v-else>
            <OtpInput
                ref="otpInput"
                v-model="code"
                :length="codeLength"
                :error="form.errors.code"
                :disabled="form.processing.value"
                @complete="loginWithCode"
            />

            <div class="flex min-h-11 items-center justify-between text-sm text-fg-muted">
                <template v-if="countdown.isRunning.value">
                    <span>{{ t('auth.verify.resendIn') }}</span>
                    <span dir="ltr" class="font-semibold text-fg tabular-nums">{{
                        localizeDigits(countdown.formatted.value)
                    }}</span>
                </template>
                <button
                    v-else
                    type="button"
                    class="-mx-2 h-11 cursor-pointer rounded-lg px-2 font-semibold text-primary hover:text-primary-hover focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="sendCode"
                >
                    {{ t('auth.verify.resend') }}
                </button>
            </div>

            <AppButton type="submit" :loading="form.processing.value" :disabled="code.length < codeLength">
                {{ t('admin.submit') }}
            </AppButton>
        </template>
    </form>
</template>
