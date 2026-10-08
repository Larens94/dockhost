<!-- Index.vue — Admin SMTP principale settings page.

  exports: defineProps
  used_by: app/Http/Controllers/PanelSmtpSettingsController.php
  rules:   Never bind saved password into form; blank password on save keeps existing. Test email default fabrizio.corpora@gmail.com is UI-only default.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | SMTP principale form + redeploy button.
  agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | Email di prova + Invia mail di test section.
  agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | smtp_test_feedback banner on Prova invio card.
  agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_465 | OVH preset ssl0.ovh.net:465 SSL.
-->

<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

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
    'mt-1 block w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-sm focus:border-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400';

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
        title="SMTP principale"
        description="Configura l’invio email del pannello DokHosts. I valori vengono salvati nel database e sincronizzati sull’environment Dokploy dell’application dokhosts (solo variabili MAIL_*)."
    >
        <div
            v-if="flashSuccess"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-950"
        >
            {{ flashError }}
        </div>

        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold">Server SMTP</h2>
            <p class="mt-1 text-sm text-neutral-500">
                La password già presente su Dokploy resta invariata finché non salvi questo modulo con una nuova password.
            </p>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium" for="smtp-preset">Provider</label>
                    <select id="smtp-preset" v-model="form.provider_preset" :class="inputClass">
                        <option value="ovh">OVH (ssl0.ovh.net:465, SSL)</option>
                        <option value="generic">SMTP generico</option>
                    </select>
                    <p v-if="form.errors.provider_preset" class="mt-1 text-sm text-red-600">
                        {{ form.errors.provider_preset }}
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium" for="smtp-host">MAIL_HOST</label>
                        <input
                            id="smtp-host"
                            v-model="form.mail_host"
                            type="text"
                            required
                            :readonly="genericFieldsLocked"
                            :class="[inputClass, genericFieldsLocked ? 'bg-neutral-50 text-neutral-600' : '']"
                        />
                        <p v-if="form.errors.mail_host" class="mt-1 text-sm text-red-600">{{ form.errors.mail_host }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="smtp-port">MAIL_PORT</label>
                        <input
                            id="smtp-port"
                            v-model="form.mail_port"
                            type="number"
                            min="1"
                            max="65535"
                            required
                            :readonly="genericFieldsLocked"
                            :class="[inputClass, genericFieldsLocked ? 'bg-neutral-50 text-neutral-600' : '']"
                        />
                        <p v-if="form.errors.mail_port" class="mt-1 text-sm text-red-600">{{ form.errors.mail_port }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium" for="smtp-encryption">MAIL_ENCRYPTION</label>
                    <select
                        id="smtp-encryption"
                        v-model="form.mail_encryption"
                        :disabled="genericFieldsLocked"
                        :class="[inputClass, genericFieldsLocked ? 'bg-neutral-50 text-neutral-600' : '']"
                    >
                        <option value="tls">TLS (STARTTLS)</option>
                        <option value="ssl">SSL</option>
                        <option value="null">Nessuna</option>
                    </select>
                    <p v-if="form.errors.mail_encryption" class="mt-1 text-sm text-red-600">
                        {{ form.errors.mail_encryption }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium" for="smtp-username">MAIL_USERNAME</label>
                    <input id="smtp-username" v-model="form.mail_username" type="text" required autocomplete="username" :class="inputClass" />
                    <p v-if="form.errors.mail_username" class="mt-1 text-sm text-red-600">{{ form.errors.mail_username }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium" for="smtp-password">MAIL_PASSWORD</label>
                    <input
                        id="smtp-password"
                        v-model="form.mail_password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="settings.password_configured ? 'Lascia vuoto per mantenere la password attuale' : 'Password SMTP'"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.mail_password" class="mt-1 text-sm text-red-600">{{ form.errors.mail_password }}</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium" for="smtp-from-address">MAIL_FROM_ADDRESS</label>
                        <input
                            id="smtp-from-address"
                            v-model="form.mail_from_address"
                            type="email"
                            required
                            :class="inputClass"
                        />
                        <p v-if="form.errors.mail_from_address" class="mt-1 text-sm text-red-600">
                            {{ form.errors.mail_from_address }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="smtp-from-name">MAIL_FROM_NAME</label>
                        <input id="smtp-from-name" v-model="form.mail_from_name" type="text" required :class="inputClass" />
                        <p v-if="form.errors.mail_from_name" class="mt-1 text-sm text-red-600">{{ form.errors.mail_from_name }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-neutral-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Salvataggio…' : 'Salva SMTP' }}
                    </button>
                    <button
                        v-if="settings.can_redeploy"
                        type="button"
                        class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-50 disabled:opacity-50"
                        :disabled="redeployForm.processing"
                        @click="redeploy"
                    >
                        {{ redeployForm.processing ? 'Avvio…' : 'Ridistribuisci pannello' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold">Prova invio</h2>
            <p class="mt-1 text-sm text-neutral-500">
                Invia una mail di test usando la configurazione SMTP attualmente caricata nel pannello (database + merge runtime).
            </p>

            <div
                v-if="smtpTestFeedback?.type === 'success'"
                class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950"
                role="status"
            >
                {{ smtpTestFeedback.message }}
            </div>
            <div
                v-else-if="smtpTestFeedback?.type === 'error'"
                class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-950"
                role="alert"
            >
                {{ smtpTestFeedback.message }}
            </div>

            <form class="mt-6 flex flex-wrap items-end gap-4" @submit.prevent="sendTestMail">
                <div class="min-w-[16rem] flex-1">
                    <label class="block text-sm font-medium" for="smtp-test-email">Email di prova</label>
                    <input
                        id="smtp-test-email"
                        v-model="testForm.test_email"
                        type="email"
                        required
                        autocomplete="email"
                        :class="inputClass"
                    />
                    <p v-if="testForm.errors.test_email" class="mt-1 text-sm text-red-600">
                        {{ testForm.errors.test_email }}
                    </p>
                </div>
                <button
                    type="submit"
                    class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-50 disabled:opacity-50"
                    :disabled="testForm.processing"
                >
                    {{ testForm.processing ? 'Invio…' : 'Invia mail di test' }}
                </button>
            </form>
        </section>
    </AppLayout>
</template>
