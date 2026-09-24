<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { dashboard } from '@/routes';

type BlockType =
    | 'about'
    | 'plain_text'
    | 'heading_text'
    | 'hero'
    | 'service_times'
    | 'contact'
    | 'image'
    | 'text_image'
    | 'video';
type HeroLinkType = 'none' | 'section' | 'external';
type ServiceTimeEntry = { day: string; time: string; label: string };
type BlockContent = {
    heading?: string;
    body?: string;
    button_label?: string;
    link_type?: HeroLinkType;
    target_block_id?: number | null;
    external_url?: string;
    entries?: ServiceTimeEntry[];
    email?: string;
    phone?: string;
    media_asset_id?: null;
    url?: string;
};
type SiteBlock = {
    id: number;
    type: BlockType;
    position: number;
    content: BlockContent;
};

const props = defineProps<{
    site: { id: number; name: string };
    blocks: SiteBlock[];
}>();

const blockTypes: { type: BlockType; label: string; description: string }[] = [
    {
        type: 'hero',
        label: 'Hero',
        description: 'Welcome visitors with a clear next step',
    },
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
    {
        type: 'service_times',
        label: 'Service times',
        description: 'List your weekly gatherings',
    },
    {
        type: 'contact',
        label: 'Contact',
        description: 'Help visitors call or email you',
    },
    {
        type: 'image',
        label: 'Image',
        description: 'Add an image placeholder',
    },
    {
        type: 'text_image',
        label: 'Text and image',
        description: 'Pair a message with an image placeholder',
    },
    {
        type: 'video',
        label: 'Video',
        description: 'Embed a YouTube or Vimeo video',
    },
];

const weekdays = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
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
const draftButtonLabel = ref('');
const draftLinkType = ref<HeroLinkType>('none');
const draftTargetBlockId = ref<number | null>(null);
const draftExternalUrl = ref('');
const savedButtonLabel = ref('');
const savedLinkType = ref<HeroLinkType>('none');
const savedTargetBlockId = ref<number | null>(null);
const savedExternalUrl = ref('');
const draftEntries = ref<ServiceTimeEntry[]>([]);
const savedEntries = ref<ServiceTimeEntry[]>([]);
const draftEmail = ref('');
const draftPhone = ref('');
const savedEmail = ref('');
const savedPhone = ref('');
const draftVideoUrl = ref('');
const savedVideoUrl = ref('');
const isDirty = computed(
    () =>
        !!selectedBlock.value &&
        (draftHeading.value !== savedHeading.value ||
            draftBody.value !== savedBody.value ||
            (selectedBlock.value?.type === 'hero' &&
                (draftButtonLabel.value !== savedButtonLabel.value ||
                    draftLinkType.value !== savedLinkType.value ||
                    draftTargetBlockId.value !== savedTargetBlockId.value ||
                    draftExternalUrl.value !== savedExternalUrl.value)) ||
            (selectedBlock.value?.type === 'service_times' &&
                JSON.stringify(draftEntries.value) !==
                    JSON.stringify(savedEntries.value)) ||
            (selectedBlock.value?.type === 'contact' &&
                (draftEmail.value !== savedEmail.value ||
                    draftPhone.value !== savedPhone.value)) ||
            (selectedBlock.value?.type === 'video' &&
                draftVideoUrl.value !== savedVideoUrl.value)),
);
const saveForm = useForm<{ content: BlockContent }>({
    content: { body: '' },
});
const saveError = ref('');
const contentSaved = ref(false);
const headingInput = ref<HTMLInputElement | null>(null);
const bodyInput = ref<HTMLTextAreaElement | null>(null);
const buttonLabelInput = ref<HTMLInputElement | null>(null);
const linkTypeInput = ref<HTMLSelectElement | null>(null);
const targetBlockInput = ref<HTMLSelectElement | null>(null);
const externalUrlInput = ref<HTMLInputElement | null>(null);
const addServiceTimeButton = ref<HTMLButtonElement | null>(null);
const serviceTimeStatus = ref('');
const emailInput = ref<HTMLInputElement | null>(null);
const phoneInput = ref<HTMLInputElement | null>(null);
const videoUrlInput = ref<HTMLInputElement | null>(null);
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
        draftButtonLabel.value = block?.content.button_label ?? '';
        draftLinkType.value = block?.content.link_type ?? 'none';
        draftTargetBlockId.value = block?.content.target_block_id ?? null;
        draftExternalUrl.value = block?.content.external_url ?? '';
        draftEntries.value = (block?.content.entries ?? []).map((entry) => ({
            ...entry,
        }));
        draftEmail.value = block?.content.email ?? '';
        draftPhone.value = block?.content.phone ?? '';
        draftVideoUrl.value = block?.content.url ?? '';
        savedHeading.value = draftHeading.value;
        savedBody.value = draftBody.value;
        savedButtonLabel.value = draftButtonLabel.value;
        savedLinkType.value = draftLinkType.value;
        savedTargetBlockId.value = draftTargetBlockId.value;
        savedExternalUrl.value = draftExternalUrl.value;
        savedEntries.value = draftEntries.value.map((entry) => ({ ...entry }));
        savedEmail.value = draftEmail.value;
        savedPhone.value = draftPhone.value;
        savedVideoUrl.value = draftVideoUrl.value;
        serviceTimeStatus.value = '';
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
    draftButtonLabel.value = savedButtonLabel.value;
    draftLinkType.value = savedLinkType.value;
    draftTargetBlockId.value = savedTargetBlockId.value;
    draftExternalUrl.value = savedExternalUrl.value;
    draftEntries.value = savedEntries.value.map((entry) => ({ ...entry }));
    draftEmail.value = savedEmail.value;
    draftPhone.value = savedPhone.value;
    draftVideoUrl.value = savedVideoUrl.value;
    serviceTimeStatus.value = '';
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
    if (block.type === 'hero') {
        return {
            heading: draftHeading.value,
            body: draftBody.value,
            button_label: draftButtonLabel.value,
            link_type: draftLinkType.value,
            target_block_id: draftTargetBlockId.value,
            external_url: draftExternalUrl.value,
        };
    }
    if (block.type === 'service_times') {
        return { heading: draftHeading.value, entries: draftEntries.value };
    }
    if (block.type === 'contact') {
        return {
            heading: draftHeading.value,
            email: draftEmail.value,
            phone: draftPhone.value,
        };
    }
    if (block.type === 'image') return { media_asset_id: null };
    if (block.type === 'text_image') {
        return {
            heading: draftHeading.value,
            body: draftBody.value,
            media_asset_id: null,
        };
    }
    if (block.type === 'video') return { url: draftVideoUrl.value };
    return { heading: draftHeading.value, body: draftBody.value };
}

function emailHref(block: SiteBlock): string | null {
    const email = contentFor(block).email ?? '';
    // Draft addresses stay plain text until the server's RFC validation accepts them.
    if (block.id === selectedBlockId.value && email !== savedEmail.value)
        return null;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return null;
    return `mailto:${encodeURIComponent(email).replace('%40', '@')}`;
}

function phoneHref(block: SiteBlock): string | null {
    const phone = contentFor(block).phone ?? '';
    if (!/^\+?[0-9().\- ]+$/.test(phone) || !/[0-9]/.test(phone)) return null;
    return `tel:${phone.replace(/[().\- ]/g, '')}`;
}

function entryError(index: number, field: keyof ServiceTimeEntry): string {
    return (
        (saveForm.errors as Record<string, string | undefined>)[
            `content.entries.${index}.${field}`
        ] ?? ''
    );
}

function entryRowError(index: number): string {
    return (
        (saveForm.errors as Record<string, string | undefined>)[
            `content.entries.${index}`
        ] ?? ''
    );
}

function formatServiceTime(time: string): string {
    const match = /^(\d{2}):(\d{2})$/.exec(time);
    if (!match) return time || 'Time to be added';
    const hour = Number(match[1]);
    if (hour > 23 || Number(match[2]) > 59) return time;
    return `${hour % 12 || 12}:${match[2]} ${hour < 12 ? 'AM' : 'PM'}`;
}

function addServiceTime() {
    draftEntries.value.push({ day: '', time: '', label: '' });
    serviceTimeStatus.value = 'Gathering added.';
    clearContentError();
    nextTick(() =>
        document
            .getElementById(`service-time-${draftEntries.value.length - 1}`)
            ?.focus(),
    );
}

function moveServiceTime(index: number, offset: number) {
    const next = index + offset;
    if (next < 0 || next >= draftEntries.value.length) return;
    draftEntries.value.splice(next, 0, draftEntries.value.splice(index, 1)[0]);
    serviceTimeStatus.value = `Gathering moved to position ${next + 1}.`;
    clearContentError();
}

function removeServiceTime(index: number) {
    draftEntries.value.splice(index, 1);
    serviceTimeStatus.value = 'Gathering removed.';
    clearContentError();
    nextTick(() => addServiceTimeButton.value?.focus());
}

function heroHref(block: SiteBlock): string | null {
    const content = contentFor(block);
    if (!content.button_label?.trim()) return null;
    if (content.link_type === 'section') {
        const target = content.target_block_id;
        return typeof target === 'number' &&
            target !== block.id &&
            props.blocks.some((candidate) => candidate.id === target)
            ? `#block-${target}`
            : null;
    }
    if (content.link_type === 'external' && content.external_url) {
        try {
            const url = new URL(content.external_url);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch {
            return null;
        }
    }
    return null;
}

function videoEmbedUrl(block: SiteBlock): string | null {
    const source = contentFor(block).url?.trim() ?? '';
    if (!source) return null;
    const hasControlCharacter = source.split('').some((character) => {
        const code = character.charCodeAt(0);
        return code < 0x20 || code === 0x7f;
    });
    if (hasControlCharacter) return null;

    const rawAuthority = /^https:\/\/([^/?#]+)/i.exec(source)?.[1];
    if (!rawAuthority || rawAuthority.includes('@')) return null;

    try {
        const url = new URL(source);
        const rawHostname = rawAuthority.replace(/:\d+$/, '').toLowerCase();
        const rawPath = /^https:\/\/[^/?#]+([^?#]*)/i.exec(source)?.[1];

        if (
            url.protocol !== 'https:' ||
            !rawHostname ||
            rawHostname !== url.hostname ||
            !rawPath ||
            url.pathname !== rawPath ||
            url.username ||
            url.password ||
            url.port
        ) {
            return null;
        }

        const rawQuery = url.search.slice(1);
        if (
            rawQuery !== '' &&
            rawQuery.split('&').some((segment) => segment === '')
        )
            return null;

        const query = new Map<string, string>();
        for (const [key, value] of url.searchParams.entries()) {
            if (!/^[A-Za-z0-9_-]+$/.test(key) || query.has(key)) return null;
            query.set(key, value);
        }

        if (
            ['youtube.com', 'www.youtube.com'].includes(url.hostname) &&
            url.pathname === '/watch' &&
            !query.has('list')
        ) {
            const videoId = query.get('v');
            return videoId && /^[A-Za-z0-9_-]{11}$/.test(videoId)
                ? `https://www.youtube-nocookie.com/embed/${videoId}`
                : null;
        }

        if (['youtu.be', 'www.youtu.be'].includes(url.hostname)) {
            const videoId = /^\/([A-Za-z0-9_-]{11})$/.exec(url.pathname)?.[1];
            return videoId && !query.has('list')
                ? `https://www.youtube-nocookie.com/embed/${videoId}`
                : null;
        }

        if (['vimeo.com', 'www.vimeo.com'].includes(url.hostname)) {
            const videoId = /^\/([0-9]+)$/.exec(url.pathname)?.[1];
            return videoId ? `https://player.vimeo.com/video/${videoId}` : null;
        }
    } catch {
        return null;
    }

    return null;
}

function changeHeroLinkType() {
    if (draftLinkType.value !== 'section') draftTargetBlockId.value = null;
    if (draftLinkType.value !== 'external') draftExternalUrl.value = '';
    if (draftLinkType.value === 'none') draftButtonLabel.value = '';
    clearContentError();
}

function saveBlock() {
    const block = selectedBlock.value;
    if (
        !block ||
        saveForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        !isDirty.value
    )
        return;

    saveError.value = '';
    contentSaved.value = false;
    saveForm.content =
        block.type === 'hero'
            ? {
                  heading: draftHeading.value,
                  body: draftBody.value,
                  button_label: draftButtonLabel.value,
                  link_type: draftLinkType.value,
                  target_block_id: draftTargetBlockId.value,
                  external_url: draftExternalUrl.value,
              }
            : block.type === 'contact'
              ? {
                    heading: draftHeading.value,
                    email: draftEmail.value,
                    phone: draftPhone.value,
                }
              : block.type === 'service_times'
                ? {
                      heading: draftHeading.value,
                      entries: draftEntries.value.map((entry) => ({
                          ...entry,
                      })),
                  }
                : block.type === 'video'
                  ? { url: draftVideoUrl.value }
                  : block.type === 'image'
                    ? { media_asset_id: null }
                    : block.type === 'text_image'
                      ? {
                            heading: draftHeading.value,
                            body: draftBody.value,
                            media_asset_id: null,
                        }
                      : block.type === 'plain_text'
                        ? { body: draftBody.value }
                        : {
                              heading: draftHeading.value,
                              body: draftBody.value,
                          };

    runOwnVisit(() =>
        saveForm.patch('/sites/' + props.site.id + '/blocks/' + block.id, {
            preserveScroll: true,
            onSuccess: () => {
                savedHeading.value = draftHeading.value;
                savedBody.value = draftBody.value;
                savedButtonLabel.value = draftButtonLabel.value;
                savedLinkType.value = draftLinkType.value;
                savedTargetBlockId.value = draftTargetBlockId.value;
                savedExternalUrl.value = draftExternalUrl.value;
                savedEntries.value = draftEntries.value.map((entry) => ({
                    ...entry,
                }));
                savedEmail.value = draftEmail.value;
                savedPhone.value = draftPhone.value;
                savedVideoUrl.value = draftVideoUrl.value;
                contentSaved.value = true;
            },
            onError: (errors) => {
                if (
                    !errors['content.heading'] &&
                    !errors['content.body'] &&
                    !errors['content.button_label'] &&
                    !errors['content.link_type'] &&
                    !errors['content.target_block_id'] &&
                    !errors['content.external_url'] &&
                    !errors['content.email'] &&
                    !errors['content.phone'] &&
                    !errors['content.url'] &&
                    !Object.keys(errors).some((key) =>
                        key.startsWith('content.entries'),
                    ) &&
                    !errors.content
                ) {
                    saveError.value =
                        'We could not save this block. Please try again.';
                }
                nextTick(() => {
                    if (errors['content.heading']) headingInput.value?.focus();
                    else if (errors['content.body']) bodyInput.value?.focus();
                    else if (errors['content.button_label'])
                        buttonLabelInput.value?.focus();
                    else if (errors['content.link_type'])
                        linkTypeInput.value?.focus();
                    else if (errors['content.target_block_id'])
                        targetBlockInput.value?.focus();
                    else if (errors['content.external_url'])
                        externalUrlInput.value?.focus();
                    else if (errors['content.email']) emailInput.value?.focus();
                    else if (errors['content.phone']) phoneInput.value?.focus();
                    else if (errors['content.url'])
                        videoUrlInput.value?.focus();
                    else if (
                        Object.keys(errors).some((key) =>
                            key.startsWith('content.entries.'),
                        )
                    ) {
                        const key = Object.keys(errors).find((item) =>
                            item.startsWith('content.entries.'),
                        );
                        const match =
                            /^content\.entries\.(\d+)(?:\.(day|time|label))?$/.exec(
                                key ?? '',
                            );
                        if (match)
                            document
                                .getElementById(
                                    `service-${match[2] ?? 'day'}-${match[1]}`,
                                )
                                ?.focus();
                        else addServiceTimeButton.value?.focus();
                    } else if (errors['content.entries'])
                        addServiceTimeButton.value?.focus();
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
                        :id="`block-${block.id}`"
                        class="border-b border-[var(--workspace-line)] px-6 py-12 last:border-b-0 sm:px-10"
                        :class="
                            block.type === 'about'
                                ? 'bg-[var(--workspace-soft)]'
                                : ''
                        "
                    >
                        <template v-if="block.type === 'hero'">
                            <p
                                class="text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
                            >
                                Welcome
                            </p>
                            <h3
                                class="mt-4 max-w-xl font-serif text-4xl leading-tight sm:text-5xl"
                            >
                                {{
                                    contentFor(block).heading ||
                                    'Welcome to our church'
                                }}
                            </h3>
                            <p
                                class="mt-5 max-w-prose whitespace-pre-line text-[var(--workspace-muted)]"
                            >
                                {{
                                    contentFor(block).body ||
                                    'Share a warm invitation with your visitors.'
                                }}
                            </p>
                            <a
                                v-if="heroHref(block)"
                                :href="heroHref(block) ?? undefined"
                                :target="
                                    contentFor(block).link_type === 'external'
                                        ? '_blank'
                                        : undefined
                                "
                                :rel="
                                    contentFor(block).link_type === 'external'
                                        ? 'noopener noreferrer'
                                        : undefined
                                "
                                class="mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--workspace-green)] px-5 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] dark:text-[var(--workspace-surface)]"
                            >
                                {{ contentFor(block).button_label }}
                            </a>
                        </template>
                        <template v-else-if="block.type === 'about'">
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
                        <template v-else-if="block.type === 'service_times'">
                            <h3 class="font-serif text-2xl">
                                {{
                                    contentFor(block).heading || 'Service times'
                                }}
                            </h3>
                            <ul
                                v-if="contentFor(block).entries?.length"
                                class="mt-6 divide-y divide-[var(--workspace-line)]"
                            >
                                <li
                                    v-for="(entry, index) in contentFor(block)
                                        .entries"
                                    :key="index"
                                    class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3"
                                >
                                    <span class="font-semibold">{{
                                        entry.day.charAt(0).toUpperCase() +
                                        entry.day.slice(1)
                                    }}</span>
                                    <span
                                        class="text-[var(--workspace-muted)]"
                                        >{{
                                            formatServiceTime(entry.time)
                                        }}</span
                                    >
                                    <span
                                        v-if="entry.label"
                                        class="w-full text-sm text-[var(--workspace-muted)]"
                                        >{{ entry.label }}</span
                                    >
                                </li>
                            </ul>
                            <p
                                v-else
                                class="mt-4 text-[var(--workspace-muted)]"
                            >
                                Add your weekly gatherings in the editor.
                            </p>
                        </template>
                        <template v-else-if="block.type === 'contact'">
                            <h3 class="font-serif text-2xl">
                                {{ contentFor(block).heading || 'Contact us' }}
                            </h3>
                            <div
                                v-if="
                                    contentFor(block).email ||
                                    contentFor(block).phone
                                "
                                class="mt-5 flex flex-col items-start gap-3"
                            >
                                <a
                                    v-if="emailHref(block)"
                                    :href="emailHref(block) ?? undefined"
                                    class="text-[var(--workspace-green)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)]"
                                    >{{ contentFor(block).email }}</a
                                >
                                <span
                                    v-else-if="contentFor(block).email"
                                    class="text-[var(--workspace-muted)]"
                                    >{{ contentFor(block).email }}</span
                                >
                                <a
                                    v-if="phoneHref(block)"
                                    :href="phoneHref(block) ?? undefined"
                                    class="text-[var(--workspace-green)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)]"
                                    >{{ contentFor(block).phone }}</a
                                >
                                <span
                                    v-else-if="contentFor(block).phone"
                                    class="text-[var(--workspace-muted)]"
                                    >{{ contentFor(block).phone }}</span
                                >
                            </div>
                            <p
                                v-else
                                class="mt-4 text-[var(--workspace-muted)]"
                            >
                                Add an email or phone number in the editor.
                            </p>
                        </template>
                        <template v-else-if="block.type === 'image'">
                            <div
                                role="group"
                                aria-label="Image placeholder"
                                class="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-[var(--workspace-line)] bg-[var(--workspace-soft)] p-8 text-center"
                            >
                                <span class="font-semibold">Image</span>
                                <span
                                    class="mt-2 text-sm text-[var(--workspace-muted)]"
                                    >Image uploads are not available yet.</span
                                >
                            </div>
                        </template>
                        <template v-else-if="block.type === 'text_image'">
                            <div
                                class="grid gap-8 md:grid-cols-2 md:items-center"
                            >
                                <div>
                                    <h3 class="font-serif text-2xl">
                                        {{
                                            contentFor(block).heading ||
                                            'Your heading'
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
                                </div>
                                <div
                                    role="group"
                                    aria-label="Image placeholder"
                                    class="flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-[var(--workspace-line)] bg-[var(--workspace-soft)] p-8 text-center"
                                >
                                    <span class="font-semibold">Image</span>
                                    <span
                                        class="mt-2 text-sm text-[var(--workspace-muted)]"
                                        >Image uploads are not available
                                        yet.</span
                                    >
                                </div>
                            </div>
                        </template>
                        <template v-else-if="block.type === 'video'">
                            <div
                                class="mx-auto max-w-3xl overflow-hidden rounded-xl bg-[var(--workspace-soft)]"
                            >
                                <div class="aspect-video">
                                    <iframe
                                        v-if="videoEmbedUrl(block)"
                                        :src="videoEmbedUrl(block) ?? undefined"
                                        title="YouTube or Vimeo video preview"
                                        loading="lazy"
                                        allowfullscreen
                                        class="h-full w-full border-0"
                                    />
                                    <div
                                        v-else
                                        role="status"
                                        class="flex h-full flex-col items-center justify-center p-6 text-center text-sm text-[var(--workspace-muted)]"
                                    >
                                        <span class="font-semibold"
                                            >Video preview</span
                                        >
                                        <span class="mt-2"
                                            >Enter a supported YouTube or Vimeo
                                            link in the editor.</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </template>
                        <p
                            v-else-if="block.type === 'plain_text'"
                            class="max-w-prose text-lg leading-relaxed whitespace-pre-line"
                        >
                            {{
                                contentFor(block).body ||
                                'Your message will appear here.'
                            }}
                        </p>
                        <p v-else class="text-[var(--workspace-muted)]">
                            {{ labelFor(block.type) }} block
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
                    <p
                        v-if="selectedBlock.type === 'image'"
                        class="mt-3 text-sm text-[var(--workspace-muted)]"
                    >
                        Image uploads are not available yet.
                    </p>
                    <p
                        v-else
                        class="mt-3 text-sm text-[var(--workspace-muted)]"
                    >
                        Edit the fields below, then save your changes.
                    </p>
                    <form
                        v-if="selectedBlock.type !== 'image'"
                        class="mt-6 space-y-5 border-t border-[var(--workspace-line)] pt-5"
                        @submit.prevent="saveBlock"
                    >
                        <div
                            v-if="
                                selectedBlock.type !== 'plain_text' &&
                                selectedBlock.type !== 'video'
                            "
                        >
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
                        <div
                            v-if="
                                selectedBlock.type !== 'service_times' &&
                                selectedBlock.type !== 'contact' &&
                                selectedBlock.type !== 'video'
                            "
                        >
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
                        <div v-if="selectedBlock.type === 'video'">
                            <label
                                for="video-url"
                                class="mb-2 block text-sm font-semibold"
                                >Video URL</label
                            >
                            <input
                                id="video-url"
                                ref="videoUrlInput"
                                v-model="draftVideoUrl"
                                type="text"
                                inputmode="url"
                                autocomplete="url"
                                placeholder="https://www.youtube.com/watch?v=…"
                                :disabled="
                                    saveForm.processing ||
                                    addForm.processing ||
                                    deleteForm.processing ||
                                    orderForm.processing
                                "
                                :aria-invalid="
                                    Boolean(saveForm.errors['content.url'])
                                "
                                :aria-describedby="
                                    saveForm.errors['content.url']
                                        ? 'video-url-error'
                                        : 'video-url-help'
                                "
                                class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                @input="clearContentError"
                            />
                            <p
                                id="video-url-help"
                                class="mt-2 text-sm text-[var(--workspace-muted)]"
                            >
                                Paste an HTTPS link to one YouTube or Vimeo
                                video.
                            </p>
                            <p
                                v-if="saveForm.errors['content.url']"
                                id="video-url-error"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{ saveForm.errors['content.url'] }}
                            </p>
                        </div>
                        <template v-if="selectedBlock.type === 'hero'">
                            <div
                                class="border-t border-[var(--workspace-line)] pt-5"
                            >
                                <label
                                    for="hero-link-type"
                                    class="mb-2 block text-sm font-semibold"
                                    >Button link</label
                                >
                                <select
                                    id="hero-link-type"
                                    ref="linkTypeInput"
                                    v-model="draftLinkType"
                                    :disabled="
                                        saveForm.processing ||
                                        addForm.processing ||
                                        deleteForm.processing ||
                                        orderForm.processing
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            saveForm.errors[
                                                'content.link_type'
                                            ],
                                        )
                                    "
                                    :aria-describedby="
                                        saveForm.errors['content.link_type']
                                            ? 'hero-link-type-error'
                                            : undefined
                                    "
                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 focus:outline-none disabled:opacity-60"
                                    @change="changeHeroLinkType"
                                >
                                    <option value="none">No button</option>
                                    <option value="section">
                                        A section on this page
                                    </option>
                                    <option value="external">
                                        An external website
                                    </option>
                                </select>
                                <p
                                    v-if="saveForm.errors['content.link_type']"
                                    id="hero-link-type-error"
                                    role="alert"
                                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                                >
                                    {{ saveForm.errors['content.link_type'] }}
                                </p>
                            </div>
                            <div v-if="draftLinkType !== 'none'">
                                <label
                                    for="hero-button-label"
                                    class="mb-2 block text-sm font-semibold"
                                    >Button text</label
                                >
                                <input
                                    id="hero-button-label"
                                    ref="buttonLabelInput"
                                    v-model="draftButtonLabel"
                                    type="text"
                                    :disabled="
                                        saveForm.processing ||
                                        addForm.processing ||
                                        deleteForm.processing ||
                                        orderForm.processing
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            saveForm.errors[
                                                'content.button_label'
                                            ],
                                        )
                                    "
                                    :aria-describedby="
                                        saveForm.errors['content.button_label']
                                            ? 'hero-button-label-error'
                                            : undefined
                                    "
                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                    @input="clearContentError"
                                />
                                <p
                                    v-if="
                                        saveForm.errors['content.button_label']
                                    "
                                    id="hero-button-label-error"
                                    role="alert"
                                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                                >
                                    {{
                                        saveForm.errors['content.button_label']
                                    }}
                                </p>
                            </div>
                            <div v-if="draftLinkType === 'section'">
                                <label
                                    for="hero-section-target"
                                    class="mb-2 block text-sm font-semibold"
                                    >Link to block</label
                                >
                                <select
                                    id="hero-section-target"
                                    ref="targetBlockInput"
                                    v-model.number="draftTargetBlockId"
                                    :disabled="
                                        saveForm.processing ||
                                        addForm.processing ||
                                        deleteForm.processing ||
                                        orderForm.processing
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            saveForm.errors[
                                                'content.target_block_id'
                                            ],
                                        )
                                    "
                                    :aria-describedby="
                                        saveForm.errors[
                                            'content.target_block_id'
                                        ]
                                            ? 'hero-section-target-error'
                                            : undefined
                                    "
                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 focus:outline-none disabled:opacity-60"
                                    @change="clearContentError"
                                >
                                    <option :value="null">
                                        Choose a block
                                    </option>
                                    <option
                                        v-for="target in props.blocks.filter(
                                            (item) =>
                                                item.id !== selectedBlock?.id,
                                        )"
                                        :key="target.id"
                                        :value="target.id"
                                    >
                                        {{ target.position + 1 }}.
                                        {{ labelFor(target.type) }}
                                    </option>
                                </select>
                                <p
                                    v-if="props.blocks.length === 1"
                                    class="mt-2 text-xs text-[var(--workspace-muted)]"
                                >
                                    Add another block to link to a section.
                                </p>
                                <p
                                    v-if="
                                        saveForm.errors[
                                            'content.target_block_id'
                                        ]
                                    "
                                    id="hero-section-target-error"
                                    role="alert"
                                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                                >
                                    {{
                                        saveForm.errors[
                                            'content.target_block_id'
                                        ]
                                    }}
                                </p>
                            </div>
                            <div v-if="draftLinkType === 'external'">
                                <label
                                    for="hero-external-url"
                                    class="mb-2 block text-sm font-semibold"
                                    >Website URL</label
                                >
                                <input
                                    id="hero-external-url"
                                    ref="externalUrlInput"
                                    v-model="draftExternalUrl"
                                    type="url"
                                    inputmode="url"
                                    placeholder="https://example.org"
                                    :disabled="
                                        saveForm.processing ||
                                        addForm.processing ||
                                        deleteForm.processing ||
                                        orderForm.processing
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            saveForm.errors[
                                                'content.external_url'
                                            ],
                                        )
                                    "
                                    :aria-describedby="
                                        saveForm.errors['content.external_url']
                                            ? 'hero-external-url-error'
                                            : undefined
                                    "
                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                    @input="clearContentError"
                                />
                                <p
                                    v-if="
                                        saveForm.errors['content.external_url']
                                    "
                                    id="hero-external-url-error"
                                    role="alert"
                                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                                >
                                    {{
                                        saveForm.errors['content.external_url']
                                    }}
                                </p>
                            </div>
                        </template>
                        <div
                            v-if="selectedBlock.type === 'service_times'"
                            class="border-t border-[var(--workspace-line)] pt-5"
                        >
                            <h3 class="text-sm font-semibold">
                                Weekly gatherings
                            </h3>
                            <p
                                class="mt-1 text-xs text-[var(--workspace-muted)]"
                            >
                                Times are local to your church. Add them in the
                                order you want visitors to see.
                            </p>
                            <p
                                v-if="!draftEntries.length"
                                class="mt-4 text-sm text-[var(--workspace-muted)]"
                            >
                                No times yet. Add a weekly gathering below.
                            </p>
                            <ol v-else class="mt-4 space-y-4">
                                <li
                                    v-for="(entry, index) in draftEntries"
                                    :key="index"
                                    class="space-y-3 rounded-lg border border-[var(--workspace-line)] p-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span class="text-sm font-semibold"
                                            >Gathering {{ index + 1 }}</span
                                        >
                                        <div class="flex gap-1">
                                            <button
                                                type="button"
                                                :aria-label="`Move gathering ${index + 1} up`"
                                                :disabled="
                                                    index === 0 ||
                                                    saveForm.processing ||
                                                    addForm.processing ||
                                                    deleteForm.processing ||
                                                    orderForm.processing
                                                "
                                                class="grid size-9 place-items-center rounded-md border border-[var(--workspace-line)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-40"
                                                @click="
                                                    moveServiceTime(index, -1)
                                                "
                                            >
                                                ↑
                                            </button>
                                            <button
                                                type="button"
                                                :aria-label="`Move gathering ${index + 1} down`"
                                                :disabled="
                                                    index ===
                                                        draftEntries.length -
                                                            1 ||
                                                    saveForm.processing ||
                                                    addForm.processing ||
                                                    deleteForm.processing ||
                                                    orderForm.processing
                                                "
                                                class="grid size-9 place-items-center rounded-md border border-[var(--workspace-line)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-40"
                                                @click="
                                                    moveServiceTime(index, 1)
                                                "
                                            >
                                                ↓
                                            </button>
                                            <button
                                                type="button"
                                                :aria-label="`Remove gathering ${index + 1}`"
                                                :disabled="
                                                    saveForm.processing ||
                                                    addForm.processing ||
                                                    deleteForm.processing ||
                                                    orderForm.processing
                                                "
                                                class="min-h-9 rounded-md border border-red-300 px-2 text-xs font-semibold text-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-40 dark:text-red-300"
                                                @click="
                                                    removeServiceTime(index)
                                                "
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                    <p
                                        v-if="entryRowError(index)"
                                        role="alert"
                                        class="text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ entryRowError(index) }}
                                    </p>
                                    <div>
                                        <label
                                            :for="`service-day-${index}`"
                                            class="mb-1 block text-sm font-semibold"
                                            >Day</label
                                        >
                                        <select
                                            :id="`service-day-${index}`"
                                            v-model="entry.day"
                                            :disabled="
                                                saveForm.processing ||
                                                addForm.processing ||
                                                deleteForm.processing ||
                                                orderForm.processing
                                            "
                                            :aria-invalid="
                                                Boolean(
                                                    entryError(index, 'day'),
                                                )
                                            "
                                            :aria-describedby="
                                                entryError(index, 'day')
                                                    ? `service-day-error-${index}`
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="" disabled>
                                                Choose a day
                                            </option>
                                            <option
                                                v-for="day in weekdays"
                                                :key="day"
                                                :value="day"
                                            >
                                                {{
                                                    day
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    day.slice(1)
                                                }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="entryError(index, 'day')"
                                            :id="`service-day-error-${index}`"
                                            role="alert"
                                            class="mt-1 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{ entryError(index, 'day') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            :for="`service-time-${index}`"
                                            class="mb-1 block text-sm font-semibold"
                                            >Local time</label
                                        >
                                        <input
                                            :id="`service-time-${index}`"
                                            v-model="entry.time"
                                            type="time"
                                            :disabled="
                                                saveForm.processing ||
                                                addForm.processing ||
                                                deleteForm.processing ||
                                                orderForm.processing
                                            "
                                            :aria-invalid="
                                                Boolean(
                                                    entryError(index, 'time'),
                                                )
                                            "
                                            :aria-describedby="
                                                entryError(index, 'time')
                                                    ? `service-time-error-${index}`
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                            @input="clearContentError"
                                        />
                                        <p
                                            v-if="entryError(index, 'time')"
                                            :id="`service-time-error-${index}`"
                                            role="alert"
                                            class="mt-1 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{ entryError(index, 'time') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            :for="`service-label-${index}`"
                                            class="mb-1 block text-sm font-semibold"
                                            >Label (optional)</label
                                        >
                                        <input
                                            :id="`service-label-${index}`"
                                            v-model="entry.label"
                                            type="text"
                                            placeholder="Traditional service"
                                            :disabled="
                                                saveForm.processing ||
                                                addForm.processing ||
                                                deleteForm.processing ||
                                                orderForm.processing
                                            "
                                            :aria-invalid="
                                                Boolean(
                                                    entryError(index, 'label'),
                                                )
                                            "
                                            :aria-describedby="
                                                entryError(index, 'label')
                                                    ? `service-label-error-${index}`
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                            @input="clearContentError"
                                        />
                                        <p
                                            v-if="entryError(index, 'label')"
                                            :id="`service-label-error-${index}`"
                                            role="alert"
                                            class="mt-1 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{ entryError(index, 'label') }}
                                        </p>
                                    </div>
                                </li>
                            </ol>
                            <button
                                ref="addServiceTimeButton"
                                type="button"
                                :disabled="
                                    saveForm.processing ||
                                    addForm.processing ||
                                    deleteForm.processing ||
                                    orderForm.processing
                                "
                                class="mt-4 min-h-11 w-full rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                @click="addServiceTime"
                            >
                                Add a gathering
                            </button>
                            <p
                                v-if="serviceTimeStatus"
                                role="status"
                                class="mt-2 text-sm text-[var(--workspace-muted)]"
                            >
                                {{ serviceTimeStatus }}
                            </p>
                            <p
                                v-if="saveForm.errors['content.entries']"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{ saveForm.errors['content.entries'] }}
                            </p>
                        </div>
                        <template v-if="selectedBlock.type === 'contact'">
                            <div>
                                <label
                                    for="contact-email"
                                    class="mb-2 block text-sm font-semibold"
                                    >Email address</label
                                >
                                <input
                                    id="contact-email"
                                    ref="emailInput"
                                    v-model="draftEmail"
                                    type="email"
                                    inputmode="email"
                                    autocomplete="email"
                                    :disabled="
                                        saveForm.processing ||
                                        addForm.processing ||
                                        deleteForm.processing ||
                                        orderForm.processing
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            saveForm.errors['content.email'],
                                        )
                                    "
                                    :aria-describedby="
                                        saveForm.errors['content.email']
                                            ? 'contact-email-error'
                                            : undefined
                                    "
                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                    @input="clearContentError"
                                />
                                <p
                                    v-if="saveForm.errors['content.email']"
                                    id="contact-email-error"
                                    role="alert"
                                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                                >
                                    {{ saveForm.errors['content.email'] }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="contact-phone"
                                    class="mb-2 block text-sm font-semibold"
                                    >Phone number</label
                                >
                                <input
                                    id="contact-phone"
                                    ref="phoneInput"
                                    v-model="draftPhone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="+1 (555) 123-4567"
                                    :disabled="
                                        saveForm.processing ||
                                        addForm.processing ||
                                        deleteForm.processing ||
                                        orderForm.processing
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            saveForm.errors['content.phone'],
                                        )
                                    "
                                    :aria-describedby="
                                        saveForm.errors['content.phone']
                                            ? 'contact-phone-error'
                                            : undefined
                                    "
                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                    @input="clearContentError"
                                />
                                <p
                                    v-if="saveForm.errors['content.phone']"
                                    id="contact-phone-error"
                                    role="alert"
                                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                                >
                                    {{ saveForm.errors['content.phone'] }}
                                </p>
                            </div>
                        </template>
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
