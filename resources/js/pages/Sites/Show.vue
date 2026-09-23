<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { dashboard } from '@/routes';

const props = defineProps<{ site: { id: number; name: string } }>();

const form = useForm({ name: props.site.name });
const nameInput = ref<HTMLInputElement | null>(null);
const saved = ref(false);
const formError = ref('');

watch(
    () => props.site.name,
    (name) => {
        form.name = name;
    },
);

function clearNameError() {
    form.clearErrors('name');
    formError.value = '';
    saved.value = false;
}

function renameSite() {
    formError.value = '';
    saved.value = false;
    form.patch(`/sites/${props.site.id}`, {
        onSuccess: () => {
            saved.value = true;
        },
        onError: (errors) => {
            if (!errors.name) {
                formError.value =
                    'We could not rename your site. Please try again.';
            }
            nextTick(() => nameInput.value?.focus());
        },
        onHttpException: () => {
            formError.value =
                'We could not rename your site. Please try again.';
            return false;
        },
        onNetworkError: () => {
            formError.value =
                'We could not rename your site. Please try again.';
            return false;
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My sites', href: dashboard() }],
    },
});
</script>

<template>
    <Head :title="props.site.name" />
    <main class="mx-auto w-full max-w-4xl px-5 py-10 sm:px-8 lg:py-16">
        <Link
            :href="dashboard()"
            class="text-sm font-semibold text-[var(--workspace-green)] hover:underline"
            >← All sites</Link
        >
        <p
            class="mt-10 text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
        >
            Your site workspace
        </p>
        <h1 class="mt-3 font-serif text-4xl tracking-tight">
            {{ props.site.name }}
        </h1>
        <div
            class="mt-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] lg:items-start"
        >
            <section
                aria-labelledby="blank-site-heading"
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-6 py-16 text-center shadow-[var(--workspace-shadow)]"
            >
                <h2 id="blank-site-heading" class="font-serif text-2xl">
                    A blank page, ready for your story
                </h2>
                <p
                    class="mx-auto mt-3 max-w-md text-sm text-[var(--workspace-muted)]"
                >
                    Your site is saved. Page building is coming next.
                </p>
            </section>

            <section
                aria-labelledby="site-details-heading"
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-6 shadow-[var(--workspace-shadow)]"
            >
                <h2 id="site-details-heading" class="font-serif text-2xl">
                    Site details
                </h2>
                <p class="mt-2 text-sm text-[var(--workspace-muted)]">
                    Change the name you see in your workspace.
                </p>
                <form class="mt-6 space-y-4" @submit.prevent="renameSite">
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
                    <p
                        v-if="saved"
                        role="status"
                        class="text-sm font-semibold text-[var(--workspace-green)]"
                    >
                        Site name saved.
                    </p>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-[var(--workspace-green)] px-5 text-sm font-semibold text-white hover:bg-[var(--workspace-green-dark)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:cursor-wait disabled:opacity-60 dark:text-[var(--workspace-surface)]"
                    >
                        {{ form.processing ? 'Saving…' : 'Save name' }}
                    </button>
                </form>
            </section>
        </div>
    </main>
</template>
