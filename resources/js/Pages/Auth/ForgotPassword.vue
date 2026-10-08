<!-- ForgotPassword.vue — Richiesta link reimpostazione password.

  exports: none
  used_by: PasswordResetLinkController
  rules:   Italian copy matching Login.vue styling.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Forgot password page.
-->

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const page = usePage();
const appName = computed(() => page.props.appName || 'DokHosts');

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <div class="flex min-h-screen bg-neutral-50">
        <Head title="Password dimenticata" />
        <div class="flex flex-1 items-center justify-center px-6 py-12">
            <form
                class="w-full max-w-sm space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <div>
                    <h1 class="text-xl font-semibold tracking-normal">Password dimenticata</h1>
                    <p class="mt-1 text-sm text-zinc-500">
                        Inserisci l’email del tuo account {{ appName }}: ti invieremo un link per impostare una nuova password.
                    </p>
                </div>
                <p v-if="status" class="text-sm text-emerald-700">{{ status }}</p>
                <div>
                    <label class="block text-sm font-medium" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-zinc-900 px-3.5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Invia link
                </button>
                <p class="text-center text-sm text-zinc-500">
                    <Link href="/login" class="text-zinc-900 hover:underline">Torna al login</Link>
                </p>
            </form>
        </div>
    </div>
</template>
