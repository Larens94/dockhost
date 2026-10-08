<!-- Create.vue — Create component.

  exports: none
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({
    name: 'Unlimited',
    slug: 'unlimited',
    max_domains: '',
    disk_mb: '',
});

const submit = () => {
    form.post('/service-plans');
};
</script>

<template>
    <AppLayout title="Nuovo piano" description="Limiti del piano. Lascia vuoto per illimitato.">
        <template #actions>
            <Link href="/service-plans" class="text-sm text-zinc-600 hover:text-zinc-900">Indietro</Link>
        </template>

        <form
            class="max-w-xl space-y-5 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <label class="block text-sm font-medium" for="name">Nome</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium" for="slug">Slug</label>
                <input
                    id="slug"
                    v-model="form.slug"
                    type="text"
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
                <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium" for="max_domains">Max domini</label>
                <input
                    id="max_domains"
                    v-model="form.max_domains"
                    type="number"
                    min="1"
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
            </div>
            <div>
                <label class="block text-sm font-medium" for="disk_mb">Disco (MB)</label>
                <input
                    id="disk_mb"
                    v-model="form.disk_mb"
                    type="number"
                    min="1"
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
            </div>
            <button
                type="submit"
                class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                Crea
            </button>
        </form>
    </AppLayout>
</template>
