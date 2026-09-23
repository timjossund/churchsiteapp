<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { dashboard } from '@/routes';

type BlockType = 'about' | 'plain_text' | 'heading_text';
type SiteBlock = {
    id: number;
    type: BlockType;
    position: number;
    content: { heading?: string; body: string };
};

const props = defineProps<{
    site: { id: number; name: string };
    blocks: SiteBlock[];
}>();

const blockTypes: { type: BlockType; label: string; description: string }[] = [
    { type: 'about', label: 'About', description: 'Introduce your church' },
    {
        type: 'plain_text',
        label: 'Plain text',
        description: 'Share a simple message',
    },
    {
        type: 'heading_text',
        label: 'Heading and text',
        description: 'Make a point with a heading',
    },
];

const selectedBlockId = ref<number | null>(props.blocks[0]?.id ?? null);
const selectedBlock = computed(
    () =>
        props.blocks.find((block) => block.id === selectedBlockId.value) ??
        null,
);

const draftHeading = ref('');
const draftBody = ref('');
const savedHeading = ref('');
const savedBody = ref('');
const isDirty = computed(
    () =>
        !!selectedBlock.value &&
        (draftHeading.value !== savedHeading.value ||
            draftBody.value !== savedBody.value),
);
const saveForm = useForm<{ content: { heading?: string; body: string } }>({
    content: { body: '' },
});
const saveError = ref('');
const contentSaved = ref(false);
const headingInput = ref<HTMLInputElement | null>(null);
const bodyInput = ref<HTMLTextAreaElement | null>(null);
let ownVisit = false;
let stopBeforeListener: (() => void) | undefined;
let stopNavigateListener: (() => void) | undefined;
let editorHistoryState: unknown;
let editorUrl = '';

watch(
    selectedBlock,
    (block, previous) => {
        if (block?.id === previous?.id) return;
        draftHeading.value = block?.content.heading ?? '';
        draftBody.value = block?.content.body ?? '';
        savedHeading.value = draftHeading.value;
        savedBody.value = draftBody.value;
        saveForm.clearErrors();
        saveError.value = '';
        contentSaved.value = false;
    },
    { immediate: true },
);

function discardDraft(): boolean {
    if (!isDirty.value) return true;
    return window.confirm('Discard your unsaved block edits?');
}

function resetDraft() {
    draftHeading.value = savedHeading.value;
    draftBody.value = savedBody.value;
    saveForm.clearErrors();
    saveError.value = '';
}

function selectBlock(id: number) {
    if (
        id === selectedBlockId.value ||
        saveForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        deleteForm.processing
    )
        return;
    if (!discardDraft()) return;
    deleteError.value = '';
    selectedBlockId.value = id;
}

function runOwnVisit(submit: () => void) {
    ownVisit = true;
    try {
        submit();
    } finally {
        ownVisit = false;
    }
}

function guardBeforeUnload(event: BeforeUnloadEvent) {
    if (!isDirty.value) return;
    event.preventDefault();
    event.returnValue = '';
}

function guardHistory(event: PopStateEvent) {
    if (isDirty.value && !discardDraft()) {
        event.stopImmediatePropagation();
        window.history.pushState(editorHistoryState, '', editorUrl);
    }
}

onMounted(() => {
    editorHistoryState = window.history.state;
    editorUrl = window.location.href;
    stopBeforeListener = router.on('before', (event) => {
        if (!ownVisit && isDirty.value && !discardDraft())
            event.preventDefault();
    });
    stopNavigateListener = router.on('navigate', () => {
        editorHistoryState = window.history.state;
        editorUrl = window.location.href;
    });
    window.addEventListener('beforeunload', guardBeforeUnload);
    window.addEventListener('popstate', guardHistory, true);
});

onUnmounted(() => {
    stopBeforeListener?.();
    stopNavigateListener?.();
    window.removeEventListener('beforeunload', guardBeforeUnload);
    window.removeEventListener('popstate', guardHistory, true);
});

function clearContentError() {
    saveForm.clearErrors();
    saveError.value = '';
    contentSaved.value = false;
}

function contentFor(block: SiteBlock) {
    if (block.id !== selectedBlockId.value) return block.content;
    return { heading: draftHeading.value, body: draftBody.value };
}

function saveBlock() {
    const block = selectedBlock.value;
    if (
        !block ||
        saveForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        !isDirty.value
    )
        return;

    saveError.value = '';
    contentSaved.value = false;
    saveForm.content =
        block.type === 'plain_text'
            ? { body: draftBody.value }
            : { heading: draftHeading.value, body: draftBody.value };

    runOwnVisit(() =>
        saveForm.patch('/sites/' + props.site.id + '/blocks/' + block.id, {
            preserveScroll: true,
            onSuccess: () => {
                savedHeading.value = draftHeading.value;
                savedBody.value = draftBody.value;
                contentSaved.value = true;
            },
            onError: (errors) => {
                if (
                    !errors['content.heading'] &&
                    !errors['content.body'] &&
                    !errors.content
                ) {
                    saveError.value =
                        'We could not save this block. Please try again.';
                }
                nextTick(() => {
                    if (errors['content.heading']) headingInput.value?.focus();
                    else bodyInput.value?.focus();
                });
            },
            onHttpException: () => {
                saveError.value =
                    'We could not save this block. Please try again.';
                return false;
            },
            onNetworkError: () => {
                saveError.value =
                    'We could not save this block. Please try again.';
                return false;
            },
        }),
    );
}

watch(
    () => props.blocks,
    (blocks) => {
        if (!blocks.some((block) => block.id === selectedBlockId.value)) {
            selectedBlockId.value = blocks[0]?.id ?? null;
        }
    },
);

const addForm = useForm<{ type: BlockType | '' }>({ type: '' });
const addError = ref('');

function labelFor(type: BlockType): string {
    return blockTypes.find((item) => item.type === type)?.label ?? 'Block';
}

function addBlock(type: BlockType) {
    if (
        addForm.processing ||
        saveForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        !discardDraft()
    )
        return;

    resetDraft();
    addError.value = '';
    orderSaved.value = false;
    addForm.type = type;
    runOwnVisit(() =>
        addForm.post('/sites/' + props.site.id + '/blocks', {
            preserveScroll: true,
            onSuccess: () => {
                selectedBlockId.value = props.blocks.at(-1)?.id ?? null;
                addForm.reset();
            },
            onError: () => {
                addError.value =
                    'We could not add this block. Please try again.';
            },
            onHttpException: () => {
                addError.value =
                    'We could not add this block. Please try again.';
                return false;
            },
            onNetworkError: () => {
                addError.value =
                    'We could not add this block. Please try again.';
                return false;
            },
        }),
    );
}

const orderForm = useForm<{ expected_order: number[]; order: number[] }>({
    expected_order: [],
    order: [],
});
const orderError = ref('');
const orderSaved = ref(false);
const draggedBlockId = ref<number | null>(null);

function persistOrder(nextOrder: number[]) {
    if (
        orderForm.processing ||
        deleteForm.processing ||
        addForm.processing ||
        saveForm.processing ||
        !discardDraft()
    )
        return;

    resetDraft();
    orderError.value = '';
    orderSaved.value = false;
    orderForm.clearErrors();
    orderForm.expected_order = props.blocks.map((block) => block.id);
    orderForm.order = nextOrder;
    runOwnVisit(() =>
        orderForm.patch('/sites/' + props.site.id + '/blocks/order', {
            preserveScroll: true,
            onSuccess: () => {
                orderSaved.value = true;
            },
            onError: (errors) => {
                if (!errors.order) {
                    orderError.value =
                        'We could not change the block order. Please try again.';
                }
            },
            onHttpException: () => {
                orderError.value =
                    'We could not change the block order. Please try again.';
                return false;
            },
            onNetworkError: () => {
                orderError.value =
                    'We could not change the block order. Please try again.';
                return false;
            },
        }),
    );
}

function moveBlock(id: number, offset: number) {
    const next = props.blocks.map((block) => block.id);
    const from = next.indexOf(id);
    const to = from + offset;
    if (from < 0 || to < 0 || to >= next.length) return;
    next.splice(to, 0, next.splice(from, 1)[0]);
    persistOrder(next);
}

function startDrag(event: DragEvent, id: number) {
    if (orderForm.processing || deleteForm.processing || addForm.processing) {
        event.preventDefault();
        return;
    }
    draggedBlockId.value = id;
    event.dataTransfer?.setData('text/plain', String(id));
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}

function dropBlock(targetId: number) {
    const draggedId = draggedBlockId.value;
    draggedBlockId.value = null;
    if (draggedId === null || draggedId === targetId) return;
    const next = props.blocks.map((block) => block.id);
    const from = next.indexOf(draggedId);
    const to = next.indexOf(targetId);
    if (from < 0 || to < 0) return;
    next.splice(to, 0, next.splice(from, 1)[0]);
    persistOrder(next);
}

const deleteForm = useForm({});
const deleteError = ref('');

function removeSelectedBlock() {
    const block = selectedBlock.value;
    if (
        !block ||
        deleteForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        saveForm.processing ||
        !window.confirm(
            isDirty.value
                ? 'Remove this block and discard your unsaved edits? This cannot be undone.'
                : 'Remove this block? This cannot be undone.',
        )
    )
        return;

    resetDraft();
    deleteError.value = '';
    orderSaved.value = false;
    runOwnVisit(() =>
        deleteForm.delete('/sites/' + props.site.id + '/blocks/' + block.id, {
            preserveScroll: true,
            onSuccess: () => {
                selectedBlockId.value = props.blocks[0]?.id ?? null;
            },
            onError: () => {
                deleteError.value =
                    'We could not remove this block. Please try again.';
            },
            onHttpException: () => {
                deleteError.value =
                    'We could not remove this block. Please try again.';
                return false;
            },
            onNetworkError: () => {
                deleteError.value =
                    'We could not remove this block. Please try again.';
                return false;
            },
        }),
    );
}

const nameForm = useForm({ name: props.site.name });
const nameInput = ref<HTMLInputElement | null>(null);
const nameSaved = ref(false);
const nameError = ref('');

watch(
    () => props.site.name,
    (name) => {
        nameForm.name = name;
    },
);

function clearNameError() {
    nameForm.clearErrors('name');
    nameError.value = '';
    nameSaved.value = false;
}

function renameSite() {
    if (
        nameForm.processing ||
        saveForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        !discardDraft()
    )
        return;
    resetDraft();
    nameError.value = '';
    nameSaved.value = false;
    runOwnVisit(() =>
        nameForm.patch('/sites/' + props.site.id, {
            preserveScroll: true,
            onSuccess: () => {
                nameSaved.value = true;
            },
            onError: (errors) => {
                if (!errors.name) {
                    nameError.value =
                        'We could not rename your site. Please try again.';
                }
                nextTick(() => nameInput.value?.focus());
            },
            onHttpException: () => {
                nameError.value =
                    'We could not rename your site. Please try again.';
                return false;
            },
            onNetworkError: () => {
                nameError.value =
                    'We could not rename your site. Please try again.';
                return false;
            },
        }),
    );
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My sites', href: dashboard() }],
    },
});
</script>

<template>
    <Head :title="props.site.name" />
    <main
        class="mx-auto w-full max-w-[88rem] px-5 py-8 sm:px-8 lg:px-10 lg:py-10"
    >
        <header class="mb-8">
            <Link
                :href="dashboard()"
                class="text-sm font-semibold text-[var(--workspace-green)] hover:underline"
                >← All sites</Link
            >
            <p
                class="mt-7 text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
            >
                Page editor
            </p>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-serif text-4xl tracking-tight">
                        {{ props.site.name }}
                    </h1>
                    <p class="mt-2 text-sm text-[var(--workspace-muted)]">
                        Build your page one block at a time.
                    </p>
                </div>
                <details class="group relative">
                    <summary
                        class="inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-4 text-sm font-semibold marker:hidden hover:bg-[var(--workspace-soft)]"
                    >
                        Rename site
                    </summary>
                    <div
                        class="mt-3 w-full rounded-xl border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)] sm:w-80"
                    >
                        <form class="space-y-4" @submit.prevent="renameSite">
                            <div>
                                <label
                                    for="site-name"
                                    class="mb-2 block text-sm font-semibold"
                                    >Site name</label
                                >
                                <input
                                    id="site-name"
                                    ref="nameInput"
                                    v-model="nameForm.name"
                                    type="text"
                                    required
                                    maxlength="255"
                                    autocomplete="off"
                                    :aria-invalid="
                                        Boolean(nameForm.errors.name)
                                    "
                                    :aria-describedby="
                                        nameForm.errors.name
                                            ? 'site-name-error'
                                            : undefined
                                    "
                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20"
                                    @input="clearNameError"
                                />
                                <p
                                    v-if="nameForm.errors.name"
                                    id="site-name-error"
                                    role="alert"
                                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                                >
                                    {{ nameForm.errors.name }}
                                </p>
                            </div>
                            <p
                                v-if="nameError"
                                role="alert"
                                class="text-sm text-red-700 dark:text-red-300"
                            >
                                {{ nameError }}
                            </p>
                            <p
                                v-if="nameSaved"
                                role="status"
                                class="text-sm font-semibold text-[var(--workspace-green)]"
                            >
                                Site name saved.
                            </p>
                            <button
                                type="submit"
                                :disabled="nameForm.processing"
                                class="min-h-11 w-full rounded-lg bg-[var(--workspace-green)] px-4 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60 dark:text-[var(--workspace-surface)]"
                            >
                                {{
                                    nameForm.processing
                                        ? 'Saving…'
                                        : 'Save name'
                                }}
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <div
            class="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)_17rem] lg:items-start"
        >
            <section
                aria-labelledby="block-list-heading"
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
            >
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="block-list-heading" class="font-serif text-xl">
                        Blocks
                    </h2>
                    <span class="text-xs text-[var(--workspace-muted)]">{{
                        props.blocks.length
                    }}</span>
                </div>
                <p class="mt-2 text-sm text-[var(--workspace-muted)]">
                    Select a block to edit. Drag it or use the arrows to change
                    its order.
                </p>

                <ol v-if="props.blocks.length" class="mt-5 space-y-2">
                    <li
                        v-for="block in props.blocks"
                        :key="block.id"
                        class="flex items-center gap-1"
                        :draggable="
                            props.blocks.length > 1 &&
                            !orderForm.processing &&
                            !addForm.processing &&
                            !deleteForm.processing
                        "
                        @dragstart="startDrag($event, block.id)"
                        @dragover.prevent
                        @drop.prevent="dropBlock(block.id)"
                        @dragend="draggedBlockId = null"
                    >
                        <button
                            type="button"
                            :aria-pressed="selectedBlockId === block.id"
                            :disabled="
                                orderForm.processing ||
                                deleteForm.processing ||
                                addForm.processing
                            "
                            class="min-h-11 min-w-0 flex-1 rounded-lg border px-3 py-2 text-left text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                            :class="
                                selectedBlockId === block.id
                                    ? 'border-[var(--workspace-green)] bg-[var(--workspace-green-soft)] text-[var(--workspace-green-ink)]'
                                    : 'border-[var(--workspace-line)] hover:bg-[var(--workspace-soft)]'
                            "
                            @click="selectBlock(block.id)"
                        >
                            <span class="mr-2 text-xs opacity-70"
                                >{{ block.position + 1 }}.</span
                            >
                            {{ labelFor(block.type) }}
                        </button>
                        <div class="flex shrink-0 gap-1">
                            <button
                                type="button"
                                :aria-label="
                                    'Move ' +
                                    labelFor(block.type) +
                                    ' block ' +
                                    (block.position + 1) +
                                    ' up'
                                "
                                :disabled="
                                    block.position === 0 ||
                                    orderForm.processing ||
                                    deleteForm.processing ||
                                    addForm.processing
                                "
                                class="grid size-10 place-items-center rounded-lg border border-[var(--workspace-line)] text-lg hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:cursor-not-allowed disabled:opacity-40"
                                @click="moveBlock(block.id, -1)"
                            >
                                ↑
                            </button>
                            <button
                                type="button"
                                :aria-label="
                                    'Move ' +
                                    labelFor(block.type) +
                                    ' block ' +
                                    (block.position + 1) +
                                    ' down'
                                "
                                :disabled="
                                    block.position ===
                                        props.blocks.length - 1 ||
                                    orderForm.processing ||
                                    deleteForm.processing ||
                                    addForm.processing
                                "
                                class="grid size-10 place-items-center rounded-lg border border-[var(--workspace-line)] text-lg hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:cursor-not-allowed disabled:opacity-40"
                                @click="moveBlock(block.id, 1)"
                            >
                                ↓
                            </button>
                        </div>
                    </li>
                </ol>
                <p v-else class="mt-5 text-sm text-[var(--workspace-muted)]">
                    No blocks yet. Add one below to begin.
                </p>
                <p
                    v-if="props.blocks.length === 1"
                    class="mt-3 text-xs text-[var(--workspace-muted)]"
                >
                    Add another block to switch between them.
                </p>
                <p
                    v-if="orderForm.processing"
                    role="status"
                    class="mt-3 text-sm text-[var(--workspace-muted)]"
                >
                    Saving block order…
                </p>
                <p
                    v-if="orderSaved && !orderForm.processing"
                    role="status"
                    class="mt-3 text-sm text-[var(--workspace-green)]"
                >
                    Block order saved.
                </p>
                <div
                    v-if="orderError || orderForm.errors.order"
                    class="mt-3 space-y-2"
                >
                    <p
                        role="alert"
                        class="text-sm text-red-700 dark:text-red-300"
                    >
                        {{ orderError || orderForm.errors.order }}
                    </p>
                    <button
                        v-if="orderForm.errors.order"
                        type="button"
                        class="text-sm font-semibold text-[var(--workspace-green)] underline"
                        @click="router.reload()"
                    >
                        Refresh blocks
                    </button>
                </div>

                <div class="mt-7 border-t border-[var(--workspace-line)] pt-5">
                    <h3 class="text-sm font-semibold">Add a block</h3>
                    <div class="mt-3 space-y-2">
                        <button
                            v-for="item in blockTypes"
                            :key="item.type"
                            type="button"
                            :disabled="
                                addForm.processing ||
                                orderForm.processing ||
                                deleteForm.processing ||
                                saveForm.processing
                            "
                            class="flex min-h-11 w-full flex-col justify-center rounded-lg border border-[var(--workspace-line)] px-3 py-2 text-left hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:cursor-wait disabled:opacity-60"
                            @click="addBlock(item.type)"
                        >
                            <span class="text-sm font-semibold">{{
                                item.label
                            }}</span>
                            <span
                                class="text-xs text-[var(--workspace-muted)]"
                                >{{ item.description }}</span
                            >
                        </button>
                    </div>
                    <p
                        v-if="addForm.processing"
                        role="status"
                        class="mt-3 text-sm text-[var(--workspace-muted)]"
                    >
                        Adding block…
                    </p>
                    <p
                        v-if="addError"
                        role="alert"
                        class="mt-3 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ addError }}
                    </p>
                </div>
            </section>

            <section
                aria-labelledby="preview-heading"
                class="min-h-[32rem] overflow-hidden rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] shadow-[var(--workspace-shadow)]"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-[var(--workspace-line)] px-6 py-4"
                >
                    <h2 id="preview-heading" class="text-sm font-semibold">
                        Page preview
                    </h2>
                    <span class="text-xs text-[var(--workspace-muted)]"
                        >Private workspace</span
                    >
                </div>
                <div
                    v-if="props.blocks.length === 0"
                    class="flex min-h-[27rem] flex-col items-center justify-center px-6 text-center"
                >
                    <div
                        class="mb-5 grid size-14 place-items-center rounded-2xl bg-[var(--workspace-green-soft)] font-serif text-3xl text-[var(--workspace-green-ink)]"
                    >
                        +
                    </div>
                    <h3 class="font-serif text-2xl">
                        A blank page, ready for your story
                    </h3>
                    <p
                        class="mt-3 max-w-sm text-sm text-[var(--workspace-muted)]"
                    >
                        Add your first block to see the page take shape.
                    </p>
                </div>
                <div v-else>
                    <section
                        v-for="block in props.blocks"
                        :key="block.id"
                        class="border-b border-[var(--workspace-line)] px-6 py-12 last:border-b-0 sm:px-10"
                        :class="
                            block.type === 'about'
                                ? 'bg-[var(--workspace-soft)]'
                                : ''
                        "
                    >
                        <template v-if="block.type === 'about'">
                            <p
                                class="text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
                            >
                                About us
                            </p>
                            <h3 class="mt-3 font-serif text-3xl">
                                {{
                                    contentFor(block).heading ||
                                    'Your introduction'
                                }}
                            </h3>
                            <p
                                class="mt-4 whitespace-pre-line text-[var(--workspace-muted)]"
                            >
                                {{
                                    contentFor(block).body ||
                                    'Tell visitors who you are and what matters to your community.'
                                }}
                            </p>
                        </template>
                        <template v-else-if="block.type === 'heading_text'">
                            <h3 class="font-serif text-2xl">
                                {{
                                    contentFor(block).heading || 'Your heading'
                                }}
                            </h3>
                            <p
                                class="mt-4 whitespace-pre-line text-[var(--workspace-muted)]"
                            >
                                {{
                                    contentFor(block).body ||
                                    'Add the details you want visitors to know.'
                                }}
                            </p>
                        </template>
                        <p
                            v-else
                            class="max-w-prose text-lg leading-relaxed whitespace-pre-line"
                        >
                            {{
                                contentFor(block).body ||
                                'Your message will appear here.'
                            }}
                        </p>
                    </section>
                </div>
            </section>

            <aside
                aria-labelledby="block-details-heading"
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
            >
                <p
                    class="text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
                >
                    Selected block
                </p>
                <h2 id="block-details-heading" class="mt-2 font-serif text-xl">
                    {{
                        selectedBlock
                            ? labelFor(selectedBlock.type)
                            : 'Nothing selected'
                    }}
                </h2>
                <template v-if="selectedBlock">
                    <p class="mt-3 text-sm text-[var(--workspace-muted)]">
                        Edit the fields below, then save your changes.
                    </p>
                    <form
                        class="mt-6 space-y-5 border-t border-[var(--workspace-line)] pt-5"
                        @submit.prevent="saveBlock"
                    >
                        <div v-if="selectedBlock.type !== 'plain_text'">
                            <label
                                for="block-heading"
                                class="mb-2 block text-sm font-semibold"
                                >Heading</label
                            >
                            <input
                                id="block-heading"
                                ref="headingInput"
                                v-model="draftHeading"
                                type="text"
                                :disabled="
                                    saveForm.processing ||
                                    addForm.processing ||
                                    deleteForm.processing ||
                                    orderForm.processing
                                "
                                :aria-invalid="
                                    Boolean(saveForm.errors['content.heading'])
                                "
                                :aria-describedby="
                                    saveForm.errors['content.heading']
                                        ? 'block-heading-error'
                                        : undefined
                                "
                                class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                @input="clearContentError"
                            />
                            <p
                                v-if="saveForm.errors['content.heading']"
                                id="block-heading-error"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{ saveForm.errors['content.heading'] }}
                            </p>
                        </div>
                        <div>
                            <label
                                for="block-body"
                                class="mb-2 block text-sm font-semibold"
                                >Text</label
                            >
                            <textarea
                                id="block-body"
                                ref="bodyInput"
                                v-model="draftBody"
                                rows="8"
                                :disabled="
                                    saveForm.processing ||
                                    addForm.processing ||
                                    deleteForm.processing ||
                                    orderForm.processing
                                "
                                :aria-invalid="
                                    Boolean(saveForm.errors['content.body'])
                                "
                                :aria-describedby="
                                    saveForm.errors['content.body']
                                        ? 'block-body-error'
                                        : undefined
                                "
                                class="min-h-40 w-full resize-y rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                @input="clearContentError"
                            />
                            <p
                                v-if="saveForm.errors['content.body']"
                                id="block-body-error"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{ saveForm.errors['content.body'] }}
                            </p>
                        </div>
                        <p
                            v-if="isDirty"
                            role="status"
                            class="text-sm text-[var(--workspace-muted)]"
                        >
                            Unsaved changes
                        </p>
                        <p
                            v-if="saveError || saveForm.errors.content"
                            role="alert"
                            class="text-sm text-red-700 dark:text-red-300"
                        >
                            {{ saveError || saveForm.errors.content }}
                        </p>
                        <p
                            v-if="contentSaved && !isDirty"
                            role="status"
                            class="text-sm font-semibold text-[var(--workspace-green)]"
                        >
                            Block saved.
                        </p>
                        <button
                            type="submit"
                            :disabled="
                                saveForm.processing ||
                                addForm.processing ||
                                deleteForm.processing ||
                                orderForm.processing ||
                                !isDirty
                            "
                            class="min-h-11 w-full rounded-lg bg-[var(--workspace-green)] px-4 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:cursor-not-allowed disabled:opacity-60 dark:text-[var(--workspace-surface)]"
                        >
                            {{ saveForm.processing ? 'Saving…' : 'Save block' }}
                        </button>
                    </form>
                    <div
                        class="mt-6 border-t border-[var(--workspace-line)] pt-5"
                    >
                        <button
                            type="button"
                            :disabled="
                                deleteForm.processing ||
                                saveForm.processing ||
                                orderForm.processing ||
                                addForm.processing
                            "
                            class="min-h-11 w-full rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-wait disabled:opacity-60 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950"
                            @click="removeSelectedBlock"
                        >
                            {{
                                deleteForm.processing
                                    ? 'Removing…'
                                    : 'Remove block'
                            }}
                        </button>
                        <p
                            v-if="deleteError"
                            role="alert"
                            class="mt-3 text-sm text-red-700 dark:text-red-300"
                        >
                            {{ deleteError }}
                        </p>
                    </div>
                </template>
                <p v-else class="mt-3 text-sm text-[var(--workspace-muted)]">
                    Add a block to start shaping your page.
                </p>
            </aside>
        </div>
    </main>
</template>
