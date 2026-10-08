<!-- Account/TwoFactor.vue — Optional TOTP 2FA setup for panel users.

  exports: none
  used_by: TwoFactorAuthenticationController
  rules:   Recovery codes shown once after confirm — user must save them offline.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Italian account 2FA UI.
-->

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    enabled: {
        type: Boolean,
        default: false,
    },
    qrUrl: {
        type: String,
        default: null,
    },
    setupSecret: {
        type: String,
        default: null,
    },
    recoveryCodes: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const prepareForm = useForm({});
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '', code: '' });

const flashSuccess = computed(() => page.props.flash?.success);

const startSetup = () => {
    prepareForm.post('/account/two-factor/prepare', { preserveScroll: true });
};

const confirmSetup = () => {
    confirmForm.post('/account/two-factor/confirm', { preserveScroll: true });
};

const disableTwoFactor = () => {
    disableForm.delete('/account/two-factor', { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Autenticazione a due fattori" description="Proteggi l’accesso al pannello con un codice TOTP.">
        <Head title="2FA" />

        <div class="mx-auto max-w-xl space-y-6">
            <p v-if="flashSuccess" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flashSuccess }}
            </p>

            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h1 class="text-lg font-semibold">Autenticazione a due fattori (2FA)</h1>
                <p class="mt-2 text-sm text-zinc-600">
                    Opzionale per amministratori e membri hosting. Dopo la password ti verrà chiesto un codice a 6 cifre
                    dall’app autenticatore (Google Authenticator, Authy, ecc.).
                </p>

                <p v-if="enabled" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true" />
                    2FA attiva sul tuo account
                </p>

                <div v-if="!enabled && !setupSecret" class="mt-6">
                    <button
                        type="button"
                        class="rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="prepareForm.processing"
                        @click="startSetup"
                    >
                        Attiva 2FA
                    </button>
                </div>

                <div v-if="setupSecret && !enabled" class="mt-6 space-y-4">
                    <p class="text-sm text-zinc-600">Scansiona il QR o inserisci la chiave segreta nell’app autenticatore.</p>
                    <img v-if="qrUrl" :src="qrUrl" alt="QR code TOTP" class="h-[200px] w-[200px] rounded-lg border border-neutral-200" />
                    <p class="font-mono text-sm break-all text-zinc-800">{{ setupSecret }}</p>
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="confirmSetup">
                        <div>
                            <label class="block text-sm font-medium" for="totp-code">Codice a 6 cifre</label>
                            <input
                                id="totp-code"
                                v-model="confirmForm.code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                required
                                class="mt-1 w-40 rounded-lg border border-neutral-200 px-3 py-2 text-sm"
                            />
                            <p v-if="confirmForm.errors.code" class="mt-1 text-sm text-red-600">{{ confirmForm.errors.code }}</p>
                        </div>
                        <button
                            type="submit"
                            class="rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                            :disabled="confirmForm.processing"
                        >
                            Conferma e attiva
                        </button>
                    </form>
                </div>

                <div v-if="recoveryCodes?.length" class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <h2 class="text-sm font-semibold text-amber-900">Codici di recupero (salvali ora)</h2>
                    <p class="mt-1 text-xs text-amber-800">Ogni codice si usa una sola volta se perdi l’app autenticatore.</p>
                    <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-amber-950">
                        <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
                    </ul>
                </div>

                <form v-if="enabled" class="mt-8 space-y-4 border-t border-neutral-100 pt-6" @submit.prevent="disableTwoFactor">
                    <h2 class="text-sm font-semibold text-zinc-900">Disattiva 2FA</h2>
                    <div>
                        <label class="block text-sm font-medium" for="disable-password">Password</label>
                        <input
                            id="disable-password"
                            v-model="disableForm.password"
                            type="password"
                            required
                            class="mt-1 w-full max-w-sm rounded-lg border border-neutral-200 px-3 py-2 text-sm"
                        />
                        <p v-if="disableForm.errors.password" class="mt-1 text-sm text-red-600">{{ disableForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="disable-code">Codice TOTP attuale</label>
                        <input
                            id="disable-code"
                            v-model="disableForm.code"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            required
                            class="mt-1 w-40 rounded-lg border border-neutral-200 px-3 py-2 text-sm"
                        />
                        <p v-if="disableForm.errors.code" class="mt-1 text-sm text-red-600">{{ disableForm.errors.code }}</p>
                    </div>
                    <button
                        type="submit"
                        class="rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                        :disabled="disableForm.processing"
                    >
                        Disattiva 2FA
                    </button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
