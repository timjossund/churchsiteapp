<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { destroy, order, show, store, update } from '@/routes/sites/pages';

type Page = { id: number; name: string; position: number; is_home: boolean };
const props = defineProps<{
    siteId: number;
    pages: Page[];
    busy: boolean;
    confirmLeave: () => boolean;
    runVisit: (submit: () => void) => void;
}>();
const emit = defineEmits<{ busy: [value: boolean]; dirty: [value: boolean] }>();
const createForm = useForm({ name: '' });
const managedPageId = ref<number | null>(null);
const selectedPage = computed(() =>
    props.pages.find((page) => page.id === managedPageId.value),
);
const renameForm = useForm({ name: '' });
const orderForm = useForm({
    expected_order: [] as number[],
    order: [] as number[],
});
const deleteForm = useForm({});
const navigating = ref(false);
const pending = computed(
    () =>
        navigating.value ||
        createForm.processing ||
        renameForm.processing ||
        orderForm.processing ||
        deleteForm.processing,
);
const newNameInput = ref<HTMLInputElement | null>(null);
const renameInput = ref<HTMLInputElement | null>(null);
const error = ref('');
const status = ref('');
const stale = ref(false);
const focusAfterRequest = ref<'create' | 'rename' | null>(null);
watch(
    [pending, () => props.busy, focusAfterRequest],
    async () => {
        if (pending.value || props.busy || !focusAfterRequest.value) return;
        await nextTick();
        (focusAfterRequest.value === 'create'
            ? newNameInput.value
            : renameInput.value
        )?.focus();
        focusAfterRequest.value = null;
    },
    { flush: 'post' },
);
watch(pending, (value) => emit('busy', value));
watch(
    () =>
        createForm.name !== '' ||
        (selectedPage.value !== undefined &&
            renameForm.name !== selectedPage.value.name),
    (value) => emit('dirty', value),
);
function managePage(page: Page) {
    if (
        props.busy ||
        (selectedPage.value &&
            renameForm.name !== selectedPage.value.name &&
            !window.confirm('Discard the unsaved page name?'))
    )
        return;
    managedPageId.value = page.id;
    renameForm.name = page.name;
    clearFeedback();
    nextTick(() => renameInput.value?.focus());
}
function clearFeedback() {
    error.value = '';
    status.value = '';
    stale.value = false;
    createForm.clearErrors();
    renameForm.clearErrors();
}
function failed() {
    error.value =
        'We could not update pages. Your changes have not been confirmed. Try again.';
    stale.value = true;
    return false;
}
function selectPage(page: Page) {
    if (props.busy || !props.confirmLeave()) return;
    clearFeedback();
    props.runVisit(() =>
        router.get(
            show.url({ site: props.siteId, page: page.id }),
            {},
            {
                preserveState: false,
                preserveScroll: true,
                onStart: () => {
                    navigating.value = true;
                },
                onFinish: () => {
                    navigating.value = false;
                },
                onHttpException: failed,
                onNetworkError: failed,
            },
        ),
    );
}
function createPage() {
    if (props.busy) return;
    clearFeedback();
    props.runVisit(() =>
        createForm.post(store.url(props.siteId), {
            preserveScroll: true,
            only: ['pages'],
            onSuccess: () => {
                createForm.reset();
                status.value = 'Page added. Open it to start editing.';
            },
            onError: (errors) => {
                error.value =
                    errors.name ?? 'We could not add this page. Try again.';
                focusAfterRequest.value = 'create';
            },
            onHttpException: failed,
            onNetworkError: failed,
        }),
    );
}
function renamePage() {
    if (props.busy || !selectedPage.value) return;
    const pageId = selectedPage.value.id;
    clearFeedback();
    props.runVisit(() =>
        renameForm.patch(update.url({ site: props.siteId, page: pageId }), {
            preserveScroll: true,
            only: ['pages'],
            onSuccess: () => {
                renameForm.name = selectedPage.value?.name ?? '';
                status.value = 'Page name saved.';
            },
            onError: (errors) => {
                error.value =
                    errors.name ?? 'We could not rename this page. Try again.';
                focusAfterRequest.value = 'rename';
            },
            onHttpException: failed,
            onNetworkError: failed,
        }),
    );
}
function movePage(page: Page, direction: number) {
    if (props.busy) return;
    const ids = props.pages.map((item) => item.id);
    const index = ids.indexOf(page.id);
    const target = index + direction;
    if (index < 0 || target < 0 || target >= ids.length) return;
    clearFeedback();
    orderForm.expected_order = [...ids];
    [ids[index], ids[target]] = [ids[target], ids[index]];
    orderForm.order = ids;
    props.runVisit(() =>
        orderForm.patch(order.url(props.siteId), {
            preserveScroll: true,
            only: ['pages'],
            onSuccess: () => {
                status.value = `${page.name} moved to position ${target + 1}.`;
            },
            onError: () => {
                error.value =
                    'The page order could not be saved. Refresh pages and try again.';
                stale.value = true;
            },
            onHttpException: failed,
            onNetworkError: failed,
        }),
    );
}
function deletePage() {
    if (props.busy || !selectedPage.value || selectedPage.value.is_home) return;
    const pageId = selectedPage.value.id;
    if (
        !window.confirm(
            `Delete "${selectedPage.value?.name ?? ''}" and all its draft blocks? This cannot be undone.`,
        )
    )
        return;
    clearFeedback();
    props.runVisit(() =>
        deleteForm.delete(destroy.url({ site: props.siteId, page: pageId }), {
            preserveScroll: true,
            only: ['pages'],
            onSuccess: () => {
                managedPageId.value = null;
                renameForm.reset();
                status.value = 'Page deleted.';
            },
            onError: (errors) => {
                error.value =
                    errors.page ??
                    'This page could not be deleted. Refresh pages and try again.';
                stale.value = true;
            },
            onHttpException: failed,
            onNetworkError: failed,
        }),
    );
}
function refreshPages() {
    if (props.busy) return;
    props.runVisit(() =>
        router.reload({
            only: ['pages'],
            onStart: () => {
                navigating.value = true;
            },
            onFinish: () => {
                navigating.value = false;
            },
            onSuccess: () => {
                clearFeedback();
                status.value = 'Pages refreshed.';
            },
            onHttpException: failed,
            onNetworkError: failed,
        }),
    );
}
</script>

<template>
    <section
        aria-labelledby="pages-heading"
        class="mb-6 rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
    >
        <h2
            id="pages-heading"
            aria-describedby="page-selection-status"
            class="font-serif text-xl"
        >
            Pages
        </h2>
        <p
            id="page-selection-status"
            role="status"
            class="mt-1 text-sm text-[var(--workspace-muted)]"
        >
            Add and arrange pages, then open one to edit its content. Home is
            the published landing page; additional pages are private drafts.
        </p>
        <ol aria-label="Site pages" class="mt-4 grid gap-2">
            <li
                v-for="(page, index) in pages"
                :key="page.id"
                class="flex max-w-full flex-wrap items-center gap-1 rounded-lg border border-[var(--workspace-line)] p-2"
                :class="
                    page.id === managedPageId
                        ? 'bg-[var(--workspace-soft)]'
                        : ''
                "
            >
                <button
                    type="button"
                    :disabled="busy"
                    class="min-h-11 min-w-0 flex-1 rounded-lg px-3 text-left text-sm font-semibold break-words focus-visible:outline-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-50"
                    @click="selectPage(page)"
                >
                    {{ page.name
                    }}<span
                        class="ml-2 text-xs font-normal text-[var(--workspace-green)]"
                        >Edit page →</span
                    >
                    <span
                        v-if="page.is_home"
                        class="ml-2 rounded bg-[var(--workspace-soft)] px-2 py-1 text-xs text-[var(--workspace-muted)]"
                        >Home</span
                    >
                </button>
                <button
                    type="button"
                    :disabled="busy"
                    :aria-label="`Manage ${page.name}`"
                    :aria-expanded="page.id === managedPageId"
                    class="min-h-11 rounded-lg px-3 text-sm font-semibold hover:bg-[var(--workspace-soft)] disabled:opacity-50"
                    @click="managePage(page)"
                >
                    Manage
                </button>
                <button
                    type="button"
                    :disabled="busy || index === 0"
                    :aria-label="`Move ${page.name} earlier`"
                    class="min-h-11 shrink-0 px-2 disabled:opacity-30"
                    @click="movePage(page, -1)"
                >
                    ↑
                </button>
                <button
                    type="button"
                    :disabled="busy || index === pages.length - 1"
                    :aria-label="`Move ${page.name} later`"
                    class="min-h-11 shrink-0 rounded-r-lg px-2 disabled:opacity-30"
                    @click="movePage(page, 1)"
                >
                    ↓
                </button>
            </li>
        </ol>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <form
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="createPage"
            >
                <div class="min-w-0 flex-1">
                    <label
                        for="new-page-name"
                        class="mb-1 block text-sm font-semibold"
                        >New page name</label
                    >
                    <input
                        id="new-page-name"
                        ref="newNameInput"
                        v-model="createForm.name"
                        required
                        maxlength="255"
                        :disabled="busy"
                        :aria-invalid="!!createForm.errors.name"
                        :aria-describedby="
                            createForm.errors.name
                                ? 'new-page-error'
                                : undefined
                        "
                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3"
                        @input="clearFeedback"
                    />
                    <p
                        v-if="createForm.errors.name"
                        id="new-page-error"
                        role="alert"
                        class="mt-1 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ createForm.errors.name }}
                    </p>
                </div>
                <button
                    type="submit"
                    :disabled="busy"
                    class="min-h-11 rounded-lg bg-[var(--workspace-green)] px-3 text-sm font-semibold text-white disabled:opacity-50 dark:text-[var(--workspace-surface)]"
                >
                    {{ createForm.processing ? 'Adding page...' : 'Add page' }}
                </button>
            </form>
            <form
                v-if="selectedPage"
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="renamePage"
            >
                <div class="min-w-0 flex-1">
                    <label
                        for="current-page-name"
                        class="mb-1 block text-sm font-semibold"
                        >Selected page name</label
                    >
                    <input
                        id="current-page-name"
                        ref="renameInput"
                        v-model="renameForm.name"
                        required
                        maxlength="255"
                        :disabled="busy"
                        :aria-invalid="!!renameForm.errors.name"
                        :aria-describedby="
                            renameForm.errors.name
                                ? 'rename-page-error'
                                : undefined
                        "
                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3"
                        @input="clearFeedback"
                    />
                    <p
                        v-if="renameForm.errors.name"
                        id="rename-page-error"
                        role="alert"
                        class="mt-1 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ renameForm.errors.name }}
                    </p>
                </div>
                <button
                    type="submit"
                    :disabled="busy || renameForm.name === selectedPage.name"
                    class="min-h-11 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold disabled:opacity-50"
                >
                    {{
                        renameForm.processing
                            ? 'Saving name...'
                            : 'Save page name'
                    }}
                </button>
            </form>
        </div>
        <div v-if="selectedPage" class="mt-4 flex flex-wrap items-center gap-3">
            <button
                type="button"
                :disabled="busy || selectedPage.is_home"
                class="min-h-11 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 disabled:opacity-40 dark:text-red-300"
                @click="deletePage"
            >
                {{
                    deleteForm.processing
                        ? 'Deleting page...'
                        : 'Delete selected page'
                }}
            </button>
            <p
                v-if="selectedPage.is_home"
                class="text-sm text-[var(--workspace-muted)]"
            >
                Home cannot be deleted.
            </p>
        </div>
        <p v-if="pending" role="status" class="mt-2 text-sm">
            Updating pages...
        </p>
        <p
            v-if="status"
            role="status"
            class="mt-2 text-sm text-[var(--workspace-green)]"
        >
            {{ status }}
        </p>
        <p
            v-if="error"
            role="alert"
            class="mt-2 text-sm text-red-700 dark:text-red-300"
        >
            {{ error }}
        </p>
        <button
            v-if="stale"
            type="button"
            :disabled="busy"
            class="mt-2 min-h-11 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold"
            @click="refreshPages"
        >
            Refresh pages
        </button>
    </section>
</template>
