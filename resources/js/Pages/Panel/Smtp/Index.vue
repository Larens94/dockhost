<!-- Index.vue — Admin SMTP principale settings page.

  exports: defineProps
  used_by: app/Http/Controllers/PanelSmtpSettingsController.php
  rules:   Never bind saved password into form; blank password on save keeps existing. Test email default fabrizio.corpora@gmail.com is UI-only default.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | SMTP principale form + redeploy button.
  agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | Email di prova + Invia mail di test section.
  agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | smtp_test_feedback banner on Prova invio card.
  agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_465 | OVH preset ssl0.ovh.net:465 SSL.
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../../composables/usePanelTranslations';
import AppLayout from '../../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');
const smtpTestFeedback = computed(() => page.props.flash?.smtp_test_feedback ?? null);

const inputClass =
    'w-full rounded-xl border border-neutral-200 bg-white py-2.5 px-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs';

const form = useForm({
    provider_preset: props.settings.provider_preset ?? 'generic',
    mail_host: props.settings.mail_host ?? '',
    mail_port: props.settings.mail_port ?? '587',
    mail_encryption: props.settings.mail_encryption ?? 'tls',
    mail_username: props.settings.mail_username ?? '',
    mail_password: '',
    mail_from_address: props.settings.mail_from_address ?? '',
    mail_from_name: props.settings.mail_from_name ?? '',
});

const genericFieldsLocked = computed(() => form.provider_preset === 'ovh');

const applyOvhPreset = () => {
    form.mail_host = 'ssl0.ovh.net';
    form.mail_port = '465';
    form.mail_encryption = 'ssl';
};

watch(
    () => form.provider_preset,
    (preset) => {
        if (preset === 'ovh') {
            applyOvhPreset();
        }
    },
);

const submit = () => {
    form.post('/panel/smtp', {
        preserveScroll: true,
        onSuccess: () => {
            form.mail_password = '';
        },
    });
};

const redeployForm = useForm({});

const redeploy = () => {
    redeployForm.post('/panel/smtp/redeploy', { preserveScroll: true });
};

const testForm = useForm({
    test_email: 'fabrizio.corpora@gmail.com',
});

const sendTestMail = () => {
    testForm.post('/panel/smtp/test', { preserveScroll: true });
};
</script>

<template>
    <AppLayout
        :title="t('smtp.title')"
        :description="t('smtp.description')"
        :full-width="true"
    >
        <!-- Flash Feedback Alerts -->
        <div
            v-if="flashSuccess"
            class="mb-6 flex items-center gap-2.5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-900 shadow-2xs"
        >
            <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            <span>{{ flashSuccess }}</span>
        </div>
        <div
            v-if="flashError"
            class="mb-6 flex items-center gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-900 shadow-2xs"
        >
            <svg class="h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <span>{{ flashError }}</span>
        </div>

        <div class="space-y-8">
            <!-- Server Settings Card -->
            <section class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs">
                <div class="border-b border-neutral-100 bg-neutral-50/50 px-6 py-4 sm:px-8">
                    <h2 class="text-sm font-bold text-neutral-900">{{ t('smtp.server') }}</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">
                        {{ t('smtp.password_help') }}
                    </p>
                </div>

                <form class="p-6 sm:p-8 space-y-6" @submit.prevent="submit">
                    <!-- Preset Selector -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-preset">
                            {{ t('smtp.provider') }}
                        </label>
                        <select id="smtp-preset" v-model="form.provider_preset" :class="inputClass">
                            <option value="ovh">{{ t('smtp.ovh') }}</option>
                            <option value="generic">{{ t('smtp.generic') }}</option>
                        </select>
                        <p v-if="form.errors.provider_preset" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.provider_preset }}
                        </p>
                    </div>

                    <!-- Host & Port -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-host">
                                MAIL_HOST <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="smtp-host"
                                v-model="form.mail_host"
                                type="text"
                                required
                                :readonly="genericFieldsLocked"
                                :class="[inputClass, genericFieldsLocked ? 'bg-neutral-50 text-neutral-500 cursor-not-allowed' : '']"
                            />
                            <p v-if="form.errors.mail_host" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.mail_host }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-port">
                                MAIL_PORT <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="smtp-port"
                                v-model="form.mail_port"
                                type="number"
                                min="1"
                                max="65535"
                                required
                                :readonly="genericFieldsLocked"
                                :class="[inputClass, genericFieldsLocked ? 'bg-neutral-50 text-neutral-500 cursor-not-allowed' : '']"
                            />
                            <p v-if="form.errors.mail_port" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.mail_port }}</p>
                        </div>
                    </div>

                    <!-- Encryption -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-encryption">
                            MAIL_ENCRYPTION
                        </label>
                        <select
                            id="smtp-encryption"
                            v-model="form.mail_encryption"
                            :disabled="genericFieldsLocked"
                            :class="[inputClass, genericFieldsLocked ? 'bg-neutral-50 text-neutral-500 cursor-not-allowed' : '']"
                        >
                            <option value="tls">{{ t('smtp.tls') }}</option>
                            <option value="ssl">{{ t('smtp.ssl') }}</option>
                            <option value="null">{{ t('common.none') }}</option>
                        </select>
                        <p v-if="form.errors.mail_encryption" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.mail_encryption }}
                        </p>
                    </div>

                    <!-- Username & Password -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-username">
                                MAIL_USERNAME <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="smtp-username"
                                v-model="form.mail_username"
                                type="text"
                                required
                                autocomplete="username"
                                :class="inputClass"
                            />
                            <p v-if="form.errors.mail_username" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.mail_username }}</p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="smtp-password">
                                    MAIL_PASSWORD
                                </label>
                                <span class="text-[11px] text-neutral-400">
                                    {{ settings.password_configured ? 'Saved' : 'Not set' }}
                                </span>
                            </div>
                            <input
                                id="smtp-password"
                                v-model="form.mail_password"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="settings.password_configured ? t('smtp.password_keep') : t('smtp.password_new')"
                                :class="inputClass"
                            />
                            <p v-if="form.errors.mail_password" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.mail_password }}</p>
                        </div>
                    </div>

                    <!-- From Address & Name -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-from-address">
                                MAIL_FROM_ADDRESS <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="smtp-from-address"
                                v-model="form.mail_from_address"
                                type="email"
                                required
                                :class="inputClass"
                            />
                            <p v-if="form.errors.mail_from_address" class="mt-1.5 text-xs font-medium text-rose-600">
                                {{ form.errors.mail_from_address }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-from-name">
                                MAIL_FROM_NAME <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="smtp-from-name"
                                v-model="form.mail_from_name"
                                type="text"
                                required
                                :class="inputClass"
                            />
                            <p v-if="form.errors.mail_from_name" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.mail_from_name }}</p>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-neutral-100">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <span>{{ form.processing ? t('common.saving') : t('smtp.save') }}</span>
                        </button>

                        <button
                            v-if="settings.can_redeploy"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2.5 text-xs font-semibold text-neutral-700 shadow-2xs hover:bg-neutral-50 transition disabled:opacity-50"
                            :disabled="redeployForm.processing"
                            @click="redeploy"
                        >
                            <span>{{ redeployForm.processing ? t('common.starting') : t('smtp.redeploy') }}</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Test Delivery Card -->
            <section class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs">
                <div class="border-b border-neutral-100 bg-neutral-50/50 px-6 py-4 sm:px-8">
                    <h2 class="text-sm font-bold text-neutral-900">{{ t('smtp.test_heading') }}</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">
                        {{ t('smtp.test_help') }}
                    </p>
                </div>

                <div class="p-6 sm:p-8">
                    <!-- Delivery Feedback Banner -->
                    <div
                        v-if="smtpTestFeedback?.type === 'success'"
                        class="mb-6 flex items-center gap-2.5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-950"
                        role="status"
                    >
                        <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>{{ smtpTestFeedback.message }}</span>
                    </div>
                    <div
                        v-else-if="smtpTestFeedback?.type === 'error'"
                        class="mb-6 flex items-center gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-950"
                        role="alert"
                    >
                        <svg class="h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ smtpTestFeedback.message }}</span>
                    </div>

                    <form class="flex flex-col sm:flex-row items-stretch sm:items-end gap-4" @submit.prevent="sendTestMail">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="smtp-test-email">
                                {{ t('smtp.test_email') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="smtp-test-email"
                                v-model="testForm.test_email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="recipient@example.com"
                                :class="inputClass"
                            />
                            <p v-if="testForm.errors.test_email" class="mt-1.5 text-xs font-medium text-rose-600">
                                {{ testForm.errors.test_email }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800 disabled:opacity-50"
                            :disabled="testForm.processing"
                        >
                            <svg
                                v-if="testForm.processing"
                                class="h-4 w-4 animate-spin text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            <span>{{ testForm.processing ? t('common.sending') : t('smtp.send_test') }}</span>
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
