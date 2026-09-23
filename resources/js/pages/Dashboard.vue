<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Plus } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import { dashboard } from '@/routes';

type Site = { id: number; name: string };

defineProps<{ sites: Site[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My sites', href: dashboard() }],
    },
});

const form = useForm({ name: '' });
const nameInput = ref<HTMLInputElement | null>(null);
const formError = ref('');

function clearNameError() {
    form.clearErrors('name');
    formError.value = '';
}

function createSite() {
    formError.value = '';
    form.post('/sites', {
        onError: (errors) => {
            if (!errors.name) {
                formError.value =
                    'We could not create your site. Please try again.';
            }
            nextTick(() => nameInput.value?.focus());
        },
        onHttpException: () => {
            formError.value =
                'We could not create your site. Please try again.';
            return false;
        },
        onNetworkError: () => {
            formError.value =
                'We could not create your site. Please try again.';
            return false;
        },
    });
}
</script>

<template>
    <Head title="My sites" />

    <main
        class="mx-auto w-full max-w-[76rem] space-y-10 px-5 py-8 sm:px-8 lg:px-12 lg:py-12"
    >
        <header class="space-y-3">
            <p
                class="text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
            >
                Your workspace
            </p>
            <h1
                class="font-serif text-4xl tracking-tight text-[var(--workspace-ink)] sm:text-[2.5rem]"
            >
                Your church sites
            </h1>
            <p class="text-[var(--workspace-muted)]">
                Keep every site you are building in one place.
            </p>
        </header>

        <section
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start"
        >
            <div class="space-y-5">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-lg font-semibold tracking-tight">
                        My sites
                    </h2>
                    <span class="text-sm text-[var(--workspace-muted)]"
                        >{{ sites.length }}
                        {{ sites.length === 1 ? 'site' : 'sites' }}</span
                    >
                </div>

                <div
                    v-if="sites.length === 0"
                    class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-6 py-14 text-center shadow-[var(--workspace-shadow)]"
                >
                    <div
                        class="mx-auto mb-5 grid size-14 place-items-center rounded-2xl bg-[var(--workspace-green-soft)] text-[var(--workspace-green-ink)]"
                    >
                        <Plus class="size-6" aria-hidden="true" />
                    </div>
                    <h3 class="font-serif text-2xl">
                        A fresh start for your church site
                    </h3>
                    <p
                        class="mx-auto mt-3 max-w-sm text-sm text-[var(--workspace-muted)]"
                    >
                        Name your first site to create a blank workspace. You
                        can build its page next.
                    </p>
                </div>

                <div v-else class="grid gap-5 md:grid-cols-2">
                    <article
                        v-for="site in sites"
                        :key="site.id"
                        class="overflow-hidden rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] shadow-[var(--workspace-shadow)]"
                    >
                        <div
                            class="grid h-40 place-items-center border-b border-[var(--workspace-line)] bg-[var(--workspace-soft)] px-6 text-center text-sm text-[var(--workspace-muted)]"
                        >
                            Blank site, ready for your story
                        </div>
                        <div class="space-y-5 p-5">
                            <div>
                                <span
                                    class="inline-flex rounded-full bg-[var(--workspace-green-soft)] px-3 py-1 text-xs font-semibold text-[var(--workspace-green-ink)]"
                                    >Getting started</span
                                >
                                <h3
                                    class="mt-3 truncate text-lg font-semibold tracking-tight"
                                    :title="site.name"
                                >
                                    {{ site.name }}
                                </h3>
                                <p
                                    class="mt-1 text-sm text-[var(--workspace-muted)]"
                                >
                                    Your workspace is ready
                                </p>
                            </div>
                            <Link
                                :href="`/sites/${site.id}`"
                                class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-[var(--workspace-green)] hover:underline focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)]"
                            >
                                Open site
                                <ArrowRight class="size-4" aria-hidden="true" />
                                <span class="sr-only">{{ site.name }}</span>
                            </Link>
                        </div>
                    </article>
                </div>
            </div>

            <section
                aria-labelledby="create-site-heading"
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-6 shadow-[var(--workspace-shadow)]"
            >
                <div
                    class="mb-5 flex size-10 items-center justify-center rounded-lg bg-[var(--workspace-green-soft)] text-[var(--workspace-green-ink)]"
                >
                    <Plus class="size-5" aria-hidden="true" />
                </div>
                <h2
                    id="create-site-heading"
                    class="font-serif text-2xl tracking-tight"
                >
                    Create a site
                </h2>
                <p class="mt-2 text-sm text-[var(--workspace-muted)]">
                    Start with a name. You can change it later.
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="createSite">
                    <div>
                        <label
                            for="site-name"
                            class="mb-2 block text-sm font-semibold"
                            >Site name</label
                        >
                        <input
                            id="site-name"
                            ref="nameInput"
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="255"
                            autocomplete="off"
                            placeholder="e.g. Grace Church"
                            :aria-invalid="Boolean(form.errors.name)"
                            :aria-describedby="
                                form.errors.name ? 'site-name-error' : undefined
                            "
                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20"
                            @input="clearNameError"
                        />
                        <p
                            v-if="form.errors.name"
                            id="site-name-error"
                            role="alert"
                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <p
                        v-if="formError"
                        role="alert"
                        class="text-sm text-red-700 dark:text-red-300"
                    >
                        {{ formError }}
                    </p>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-[var(--workspace-green)] px-5 text-sm font-semibold text-white hover:bg-[var(--workspace-green-dark)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:cursor-wait disabled:opacity-60 dark:text-[var(--workspace-surface)]"
                    >
                        <Plus class="size-4" aria-hidden="true" />{{
                            form.processing ? 'Creating site…' : 'Create site'
                        }}
                    </button>
                </form>
            </section>
        </section>
    </main>
</template>
