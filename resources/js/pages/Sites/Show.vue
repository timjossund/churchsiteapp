<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import SiteMediaController from '@/actions/App/Http/Controllers/SiteMediaController';
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
type SiteTheme = 'warm' | 'clean' | 'bold';
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
    media_asset_id?: number | null;
    url?: string;
};
type SiteBlock = {
    id: number;
    type: BlockType;
    position: number;
    content: BlockContent;
    media_url: string | null;
    alt_text: string | null;
};

const props = defineProps<{
    site: {
        id: number;
        name: string;
        theme_key: SiteTheme;
        footer: { text: string };
        slug: string | null;
        seo_title: string | null;
        seo_description: string | null;
        published_at: string | null;
        published_url: string | null;
        has_unpublished_changes: boolean;
        logo: {
            media_asset_id: number;
            url: string;
            alt_text: string | null;
        } | null;
        social_image: {
            media_asset_id: number;
            url: string;
            alt_text: string | null;
        } | null;
    };
    blocks: SiteBlock[];
}>();

const siteThemes: { key: SiteTheme; label: string; description: string }[] = [
    {
        key: 'warm',
        label: 'Warm',
        description: 'Traditional and welcoming',
    },
    {
        key: 'clean',
        label: 'Clean',
        description: 'Minimal and calm',
    },
    {
        key: 'bold',
        label: 'Bold',
        description: 'Contemporary and expressive',
    },
];

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
const draftAltText = ref('');
const savedAltText = ref('');
const clearImagePending = ref(false);
const uploadPreviewUrl = ref<string | null>(null);
const imageUploadStatus = ref('');
const imageUploadError = ref('');
const altTextStatus = ref('');
const altTextError = ref('');
const imageInput = ref<HTMLInputElement | null>(null);
const altTextInput = ref<HTMLInputElement | null>(null);
const imageUploadForm = useForm<{ image: File | null; alt_text: string }>({
    image: null,
    alt_text: '',
});
const altTextForm = useForm<{ alt_text: string }>({ alt_text: '' });
const isContentDirty = computed(
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
                draftVideoUrl.value !== savedVideoUrl.value) ||
            (selectedBlock.value &&
                ['image', 'text_image'].includes(selectedBlock.value.type) &&
                clearImagePending.value)),
);
const isAltTextDirty = computed(
    () =>
        !!selectedBlock.value &&
        ['image', 'text_image'].includes(selectedBlock.value.type) &&
        draftAltText.value !== savedAltText.value,
);
const isDirty = computed(
    () =>
        isContentDirty.value ||
        isAltTextDirty.value ||
        clearImagePending.value ||
        imageUploadForm.processing ||
        altTextForm.processing,
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
        draftAltText.value = block?.alt_text ?? '';
        savedAltText.value = draftAltText.value;
        clearImagePending.value = false;
        releaseUploadPreview();
        imageUploadForm.reset();
        imageUploadForm.clearErrors();
        altTextForm.reset();
        altTextForm.clearErrors();
        imageUploadStatus.value = '';
        imageUploadError.value = '';
        altTextStatus.value = '';
        altTextError.value = '';
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
    draftAltText.value = savedAltText.value;
    clearImagePending.value = false;
    releaseUploadPreview();
    imageUploadForm.reset();
    imageUploadForm.clearErrors();
    altTextForm.clearErrors();
    imageUploadError.value = '';
    altTextError.value = '';
    imageUploadStatus.value = '';
    altTextStatus.value = '';
    serviceTimeStatus.value = '';
    saveForm.clearErrors();
    saveError.value = '';
}

function selectBlock(id: number) {
    if (uploadInProgress.value) return;
    if (
        id === selectedBlockId.value ||
        saveForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        imageUploadForm.processing ||
        altTextForm.processing
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

function releaseUploadPreview() {
    if (uploadPreviewUrl.value) URL.revokeObjectURL(uploadPreviewUrl.value);
    uploadPreviewUrl.value = null;
}

function clearImageErrors() {
    imageUploadForm.clearErrors('image', 'alt_text');
    imageUploadError.value = '';
    imageUploadStatus.value = '';
}

function clearAltTextFeedback() {
    imageUploadForm.clearErrors('alt_text');
    altTextForm.clearErrors('alt_text');
    altTextError.value = '';
    altTextStatus.value = '';
}

function requestClearBlockImage() {
    if (uploadInProgress.value) return;
    const block = selectedBlock.value;
    if (!block) return;
    if (clearImagePending.value) {
        clearImagePending.value = false;
        imageUploadStatus.value = 'Image will be kept.';
        return;
    }
    if (!block.content.media_asset_id || isAltTextDirty.value) return;
    clearImagePending.value = true;
    imageUploadStatus.value = 'Image will be cleared when you save the block.';
    clearContentError();
}

function selectImageFile(event: Event) {
    if (editorWriteInProgress.value) return;
    const input = event.currentTarget;
    const file =
        input instanceof HTMLInputElement ? input.files?.[0] : undefined;
    if (!file || !selectedBlock.value) return;

    releaseUploadPreview();
    clearImageErrors();
    if (file.size > 5 * 1024 * 1024) {
        imageUploadError.value = 'Choose an image that is 5 MB or smaller.';
        if (input instanceof HTMLInputElement) input.value = '';
        nextTick(() => imageInput.value?.focus());
        return;
    }

    clearImagePending.value = false;
    imageUploadForm.image = file;
    imageUploadForm.alt_text = draftAltText.value;
    uploadPreviewUrl.value = URL.createObjectURL(file);
    imageUploadStatus.value = 'Uploading image…';
    const blockId = selectedBlock.value.id;

    runOwnVisit(() =>
        imageUploadForm.post(
            SiteMediaController.uploadBlockImage({
                site: props.site.id,
                block: blockId,
            }).url,
            {
                forceFormData: true,
                preserveScroll: true,
                onCancel: () => {
                    imageUploadStatus.value = '';
                    releaseUploadPreview();
                    imageUploadForm.image = null;
                    imageUploadForm.progress = null;
                    if (imageInput.value) imageInput.value.value = '';
                },
                onSuccess: () => {
                    savedAltText.value = imageUploadForm.alt_text;
                    draftAltText.value = imageUploadForm.alt_text;
                    imageUploadStatus.value = 'Image uploaded.';
                    releaseUploadPreview();
                    imageUploadForm.reset();
                    if (imageInput.value) imageInput.value.value = '';
                },
                onError: (errors) => {
                    imageUploadStatus.value = '';
                    imageUploadError.value = errors.image ?? '';
                    nextTick(() => {
                        if (errors.image) imageInput.value?.focus();
                        else if (errors.alt_text) altTextInput.value?.focus();
                        else imageInput.value?.focus();
                    });
                    releaseUploadPreview();
                    imageUploadForm.image = null;
                    if (imageInput.value) imageInput.value.value = '';
                },
                onHttpException: () => {
                    imageUploadStatus.value = '';
                    imageUploadError.value =
                        'We could not upload this image. Please try again.';
                    releaseUploadPreview();
                    imageUploadForm.image = null;
                    if (imageInput.value) imageInput.value.value = '';
                    return false;
                },
                onNetworkError: () => {
                    imageUploadStatus.value = '';
                    imageUploadError.value =
                        'We could not upload this image. Please try again.';
                    releaseUploadPreview();
                    imageUploadForm.image = null;
                    if (imageInput.value) imageInput.value.value = '';
                    return false;
                },
            },
        ),
    );
}

function saveAltText() {
    if (uploadInProgress.value) return;
    const block = selectedBlock.value;
    const mediaAssetId = block?.content.media_asset_id;
    if (
        !block ||
        !mediaAssetId ||
        altTextForm.processing ||
        imageUploadForm.processing ||
        !isAltTextDirty.value
    ) {
        return;
    }

    altTextError.value = '';
    altTextStatus.value = '';
    altTextForm.alt_text = draftAltText.value;
    runOwnVisit(() =>
        altTextForm.patch(
            SiteMediaController.updateAltText({
                site: props.site.id,
                mediaAsset: mediaAssetId,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    savedAltText.value = altTextForm.alt_text;
                    draftAltText.value = altTextForm.alt_text;
                    altTextStatus.value = 'Image description saved.';
                },
                onError: (errors) => {
                    altTextError.value = errors.alt_text ?? '';
                    nextTick(() => altTextInput.value?.focus());
                },
                onHttpException: () => {
                    altTextError.value =
                        'We could not save this image description. Please try again.';
                    return false;
                },
                onNetworkError: () => {
                    altTextError.value =
                        'We could not save this image description. Please try again.';
                    return false;
                },
            },
        ),
    );
}

function blockMediaUrl(block: SiteBlock): string | null {
    if (block.id === selectedBlockId.value) {
        if (clearImagePending.value) return null;
        if (uploadPreviewUrl.value) return uploadPreviewUrl.value;
    }

    return block.media_url;
}

function blockPreviewAltText(block: SiteBlock): string {
    return block.id === selectedBlockId.value && uploadPreviewUrl.value
        ? draftAltText.value
        : (block.alt_text ?? '');
}

function guardBeforeUnload(event: BeforeUnloadEvent) {
    if (!isDirty.value && !uploadInProgress.value) return;
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
        if (uploadInProgress.value) {
            event.preventDefault();
            return;
        }
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
    releaseUploadPreview();
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
    if (block.type === 'image') {
        return {
            media_asset_id: clearImagePending.value
                ? null
                : (block.content.media_asset_id ?? null),
        };
    }
    if (block.type === 'text_image') {
        return {
            heading: draftHeading.value,
            body: draftBody.value,
            media_asset_id: clearImagePending.value
                ? null
                : (block.content.media_asset_id ?? null),
        };
    }
    if (block.type === 'video') return { url: draftVideoUrl.value };
    return { heading: draftHeading.value, body: draftBody.value };
}

const previewHeadingFallbacks: Partial<Record<BlockType, string>> = {
    hero: 'Welcome to our church',
    about: 'Your introduction',
    heading_text: 'Your heading',
    service_times: 'Service times',
    contact: 'Contact us',
    text_image: 'Your heading',
};

function previewHeading(block: SiteBlock, fallback: string): string {
    return contentFor(block).heading?.trim() || fallback;
}

function sectionNavigationLabel(block: SiteBlock): string {
    const fallback = previewHeadingFallbacks[block.type];
    return fallback ? previewHeading(block, fallback) : labelFor(block.type);
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
    if (uploadInProgress.value) return;
    const block = selectedBlock.value;
    if (
        !block ||
        saveForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        imageUploadForm.processing ||
        altTextForm.processing ||
        !isContentDirty.value
    )
        return;

    saveError.value = '';
    contentSaved.value = false;
    const mediaAssetId = clearImagePending.value
        ? null
        : (block.content.media_asset_id ?? null);
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
                    ? { media_asset_id: mediaAssetId }
                    : block.type === 'text_image'
                      ? {
                            heading: draftHeading.value,
                            body: draftBody.value,
                            media_asset_id: mediaAssetId,
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
                if (clearImagePending.value) {
                    clearImagePending.value = false;
                    draftAltText.value = '';
                    savedAltText.value = '';
                    altTextForm.reset();
                    altTextForm.clearErrors();
                    altTextError.value = '';
                    imageUploadStatus.value = 'Image cleared.';
                }
                contentSaved.value = true;
            },
            onError: (errors) => {
                clearImagePending.value = false;
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
                clearImagePending.value = false;
                saveError.value =
                    'We could not save this block. Please try again.';
                return false;
            },
            onNetworkError: () => {
                clearImagePending.value = false;
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
    if (uploadInProgress.value) return;
    if (
        addForm.processing ||
        saveForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        imageUploadForm.processing ||
        altTextForm.processing ||
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
    if (uploadInProgress.value) return;
    if (
        orderForm.processing ||
        deleteForm.processing ||
        addForm.processing ||
        saveForm.processing ||
        imageUploadForm.processing ||
        altTextForm.processing ||
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
    if (uploadInProgress.value) return;
    if (
        orderForm.processing ||
        deleteForm.processing ||
        addForm.processing ||
        imageUploadForm.processing ||
        altTextForm.processing
    ) {
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
    if (uploadInProgress.value) return;
    const block = selectedBlock.value;
    if (
        !block ||
        deleteForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        saveForm.processing ||
        imageUploadForm.processing ||
        altTextForm.processing ||
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
const appearanceForm = useForm<{
    theme_key: SiteTheme;
    footer: { text: string };
    slug: string;
    seo_title: string;
    seo_description: string;
}>({
    theme_key: props.site.theme_key,
    footer: { text: props.site.footer.text },
    slug: props.site.slug ?? '',
    seo_title: props.site.seo_title ?? '',
    seo_description: props.site.seo_description ?? '',
});
const nameInput = ref<HTMLInputElement | null>(null);
const themeInput = ref<HTMLSelectElement | null>(null);
const footerTextInput = ref<HTMLInputElement | null>(null);
const siteSlugInput = ref<HTMLInputElement | null>(null);
const seoTitleInput = ref<HTMLInputElement | null>(null);
const seoDescriptionInput = ref<HTMLTextAreaElement | null>(null);
const socialImageInput = ref<HTMLInputElement | null>(null);
const socialImageForm = useForm<{ image: File | null; alt_text: string }>({
    image: null,
    alt_text: '',
});
const socialImageClearForm = useForm({});
const publishForm = useForm({});
const socialImageError = ref('');
const socialImageStatus = ref('');
const publishError = ref('');
const publishStatus = ref('');
const appearanceDetails = ref<HTMLDetailsElement | null>(null);
const nameSaved = ref(false);
const nameError = ref('');
const appearanceSaved = ref(false);
const appearanceError = ref('');
const logoDraftAltText = ref(props.site.logo?.alt_text ?? '');
const logoUploadPreviewUrl = ref<string | null>(null);
const logoUploadStatus = ref('');
const logoUploadError = ref('');
const logoAltTextStatus = ref('');
const logoAltTextError = ref('');
const logoFileInput = ref<HTMLInputElement | null>(null);
const logoAltTextInput = ref<HTMLInputElement | null>(null);
const logoUploadForm = useForm<{ image: File | null; alt_text: string }>({
    image: null,
    alt_text: '',
});
const logoAltTextForm = useForm<{ alt_text: string }>({ alt_text: '' });
const logoClearForm = useForm({});
const uploadInProgress = computed(
    () =>
        imageUploadForm.processing ||
        logoUploadForm.processing ||
        socialImageForm.processing,
);
const editorWriteInProgress = computed(
    () =>
        uploadInProgress.value ||
        nameForm.processing ||
        appearanceForm.processing ||
        saveForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        altTextForm.processing ||
        logoAltTextForm.processing ||
        logoClearForm.processing ||
        socialImageForm.processing ||
        socialImageClearForm.processing ||
        publishForm.processing,
);
const hasUnsavedEditorChanges = computed(
    () =>
        nameForm.name !== props.site.name ||
        appearanceForm.isDirty ||
        isDirty.value ||
        isLogoAltTextDirty.value,
);
const isLogoAltTextDirty = computed(
    () => logoDraftAltText.value !== (props.site.logo?.alt_text ?? ''),
);

watch(
    () => props.site.name,
    (name) => {
        nameForm.name = name;
    },
);

watch(
    () => props.site.id,
    () => {
        appearanceForm.theme_key = props.site.theme_key;
        appearanceForm.footer.text = props.site.footer.text;
        appearanceForm.slug = props.site.slug ?? '';
        appearanceForm.seo_title = props.site.seo_title ?? '';
        appearanceForm.seo_description = props.site.seo_description ?? '';
        appearanceForm.defaults();
        appearanceForm.clearErrors();
        appearanceError.value = '';
        appearanceSaved.value = false;
    },
);

function clearNameError() {
    nameForm.clearErrors('name');
    nameError.value = '';
    nameSaved.value = false;
}

function renameSite() {
    if (uploadInProgress.value) return;
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

function clearAppearanceError() {
    appearanceForm.clearErrors();
    appearanceError.value = '';
    appearanceSaved.value = false;
}

function publishSite() {
    if (editorWriteInProgress.value || hasUnsavedEditorChanges.value) return;
    publishError.value = '';
    publishStatus.value = '';
    if (!props.site.slug) {
        publishError.value =
            'Choose and save a shareable address before publishing.';
        if (appearanceDetails.value) appearanceDetails.value.open = true;
        nextTick(() => siteSlugInput.value?.focus());
        return;
    }

    publishForm.post('/sites/' + props.site.id + '/publish', {
        preserveScroll: true,
        onSuccess: () => {
            publishStatus.value = 'Your saved draft is now published.';
        },
        onError: (errors) => {
            publishError.value =
                errors.slug ??
                errors.publish ??
                'We could not publish this page. Please try again.';
            if (errors.slug) {
                if (appearanceDetails.value)
                    appearanceDetails.value.open = true;
                nextTick(() => siteSlugInput.value?.focus());
            }
        },
        onHttpException: () => {
            publishError.value =
                'We could not publish this page. Please try again.';
            return false;
        },
        onNetworkError: () => {
            publishError.value =
                'We could not publish this page. Please try again.';
            return false;
        },
    });
}

function saveAppearance() {
    if (uploadInProgress.value) return;
    if (
        appearanceForm.processing ||
        nameForm.processing ||
        saveForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        !discardDraft()
    )
        return;
    resetDraft();
    appearanceError.value = '';
    appearanceSaved.value = false;
    runOwnVisit(() =>
        appearanceForm.patch('/sites/' + props.site.id, {
            preserveScroll: true,
            onSuccess: () => {
                appearanceForm.footer.text = appearanceForm.footer.text.trim();
                appearanceForm.slug = appearanceForm.slug.trim().toLowerCase();
                appearanceForm.seo_title = appearanceForm.seo_title.trim();
                appearanceForm.seo_description =
                    appearanceForm.seo_description.trim();
                appearanceForm.defaults();
                appearanceSaved.value = true;
            },
            onError: (errors) => {
                const fieldErrors = errors as Record<string, string>;
                if (
                    !fieldErrors.theme_key &&
                    !fieldErrors['footer.text'] &&
                    !fieldErrors.slug &&
                    !fieldErrors.seo_title &&
                    !fieldErrors.seo_description
                ) {
                    appearanceError.value =
                        'We could not save appearance settings. Please try again.';
                }
                nextTick(() => {
                    if (fieldErrors.theme_key) themeInput.value?.focus();
                    else if (fieldErrors['footer.text'])
                        footerTextInput.value?.focus();
                    else if (fieldErrors.slug) siteSlugInput.value?.focus();
                    else if (fieldErrors.seo_title)
                        seoTitleInput.value?.focus();
                    else if (fieldErrors.seo_description)
                        seoDescriptionInput.value?.focus();
                    else themeInput.value?.focus();
                });
            },
            onHttpException: () => {
                appearanceError.value =
                    'We could not save appearance settings. Please try again.';
                return false;
            },
            onNetworkError: () => {
                appearanceError.value =
                    'We could not save appearance settings. Please try again.';
                return false;
            },
        }),
    );
}

function selectSocialImage(event: Event) {
    if (editorWriteInProgress.value) return;
    const input = event.currentTarget;
    const file =
        input instanceof HTMLInputElement ? input.files?.[0] : undefined;
    if (!file) return;
    socialImageError.value = '';
    socialImageStatus.value = 'Uploading social preview image…';
    socialImageForm.image = file;
    socialImageForm.alt_text = '';
    socialImageForm.post('/sites/' + props.site.id + '/social-image', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            socialImageStatus.value = 'Social preview image uploaded.';
            socialImageForm.reset();
            if (socialImageInput.value) socialImageInput.value.value = '';
        },
        onError: (errors) => {
            socialImageStatus.value = '';
            socialImageError.value =
                errors.image ??
                errors.alt_text ??
                'We could not upload this image. Please try again.';
            nextTick(() => socialImageInput.value?.focus());
            socialImageForm.image = null;
            if (socialImageInput.value) socialImageInput.value.value = '';
        },
        onHttpException: () => {
            socialImageStatus.value = '';
            socialImageError.value =
                'We could not upload this image. Please try again.';
            return false;
        },
        onNetworkError: () => {
            socialImageStatus.value = '';
            socialImageError.value =
                'We could not upload this image. Please try again.';
            return false;
        },
    });
}

function clearSocialImage() {
    if (!props.site.social_image || socialImageClearForm.processing) return;
    socialImageError.value = '';
    socialImageStatus.value = '';
    socialImageClearForm.delete('/sites/' + props.site.id + '/social-image', {
        preserveScroll: true,
        onSuccess: () => {
            socialImageStatus.value = 'Social preview image cleared.';
        },
        onError: () => {
            socialImageError.value =
                'We could not clear this image. Please try again.';
        },
        onHttpException: () => {
            socialImageError.value =
                'We could not clear this image. Please try again.';
            return false;
        },
        onNetworkError: () => {
            socialImageError.value =
                'We could not clear this image. Please try again.';
            return false;
        },
    });
}

function releaseLogoUploadPreview() {
    if (logoUploadPreviewUrl.value)
        URL.revokeObjectURL(logoUploadPreviewUrl.value);
    logoUploadPreviewUrl.value = null;
}

function logoPreviewUrl(): string | null {
    return logoUploadPreviewUrl.value ?? props.site.logo?.url ?? null;
}

function logoPreviewAltText(): string {
    if (logoUploadPreviewUrl.value) return logoDraftAltText.value;
    return props.site.logo?.alt_text || props.site.name;
}

function clearLogoUploadFeedback() {
    logoUploadForm.clearErrors('image', 'alt_text');
    logoUploadError.value = '';
    logoUploadStatus.value = '';
}

function clearLogoAltTextFeedback() {
    logoUploadForm.clearErrors('alt_text');
    logoAltTextForm.clearErrors('alt_text');
    logoAltTextError.value = '';
    logoAltTextStatus.value = '';
}

function selectLogoFile(event: Event) {
    if (editorWriteInProgress.value) return;
    const input = event.currentTarget;
    const file =
        input instanceof HTMLInputElement ? input.files?.[0] : undefined;
    if (!file) return;

    releaseLogoUploadPreview();
    clearLogoUploadFeedback();
    if (file.size > 5 * 1024 * 1024) {
        logoUploadError.value = 'Choose an image that is 5 MB or smaller.';
        if (input instanceof HTMLInputElement) input.value = '';
        nextTick(() => logoFileInput.value?.focus());
        return;
    }

    logoUploadForm.image = file;
    logoUploadForm.alt_text = logoDraftAltText.value;
    logoUploadPreviewUrl.value = URL.createObjectURL(file);
    logoUploadStatus.value = 'Uploading logo…';

    runOwnVisit(() =>
        logoUploadForm.post(
            SiteMediaController.uploadLogo({ site: props.site.id }).url,
            {
                forceFormData: true,
                preserveScroll: true,
                onCancel: () => {
                    logoUploadStatus.value = '';
                    releaseLogoUploadPreview();
                    logoUploadForm.image = null;
                    logoUploadForm.progress = null;
                    if (logoFileInput.value) logoFileInput.value.value = '';
                },
                onSuccess: () => {
                    logoDraftAltText.value = logoUploadForm.alt_text;
                    logoUploadStatus.value = 'Logo uploaded.';
                    releaseLogoUploadPreview();
                    logoUploadForm.reset();
                    if (logoFileInput.value) logoFileInput.value.value = '';
                },
                onError: (errors) => {
                    logoUploadStatus.value = '';
                    logoUploadError.value = errors.image ?? '';
                    nextTick(() => {
                        if (errors.image) logoFileInput.value?.focus();
                        else if (errors.alt_text)
                            logoAltTextInput.value?.focus();
                        else logoFileInput.value?.focus();
                    });
                    releaseLogoUploadPreview();
                    logoUploadForm.image = null;
                    if (logoFileInput.value) logoFileInput.value.value = '';
                },
                onHttpException: () => {
                    logoUploadStatus.value = '';
                    logoUploadError.value =
                        'We could not upload this logo. Please try again.';
                    releaseLogoUploadPreview();
                    logoUploadForm.image = null;
                    if (logoFileInput.value) logoFileInput.value.value = '';
                    return false;
                },
                onNetworkError: () => {
                    logoUploadStatus.value = '';
                    logoUploadError.value =
                        'We could not upload this logo. Please try again.';
                    releaseLogoUploadPreview();
                    logoUploadForm.image = null;
                    if (logoFileInput.value) logoFileInput.value.value = '';
                    return false;
                },
            },
        ),
    );
}

function saveLogoAltText() {
    if (uploadInProgress.value) return;
    const logo = props.site.logo;
    if (
        !logo ||
        !isLogoAltTextDirty.value ||
        logoAltTextForm.processing ||
        logoUploadForm.processing ||
        logoClearForm.processing
    ) {
        return;
    }

    logoAltTextError.value = '';
    logoAltTextStatus.value = '';
    logoAltTextForm.alt_text = logoDraftAltText.value;
    runOwnVisit(() =>
        logoAltTextForm.patch(
            SiteMediaController.updateAltText({
                site: props.site.id,
                mediaAsset: logo.media_asset_id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    logoDraftAltText.value = logoAltTextForm.alt_text;
                    logoAltTextForm.defaults();
                    logoAltTextStatus.value = 'Logo description saved.';
                },
                onError: (errors) => {
                    logoAltTextError.value = errors.alt_text ?? '';
                    nextTick(() => logoAltTextInput.value?.focus());
                },
                onHttpException: () => {
                    logoAltTextError.value =
                        'We could not save this logo description. Please try again.';
                    return false;
                },
                onNetworkError: () => {
                    logoAltTextError.value =
                        'We could not save this logo description. Please try again.';
                    return false;
                },
            },
        ),
    );
}

function clearLogo() {
    if (uploadInProgress.value) return;
    if (
        !props.site.logo ||
        logoClearForm.processing ||
        logoUploadForm.processing ||
        logoAltTextForm.processing
    ) {
        return;
    }

    logoUploadError.value = '';
    logoUploadStatus.value = '';
    logoAltTextError.value = '';
    logoAltTextStatus.value = '';
    runOwnVisit(() =>
        logoClearForm.delete(
            SiteMediaController.clearLogo({ site: props.site.id }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    logoDraftAltText.value = '';
                    logoAltTextForm.reset();
                    logoAltTextForm.clearErrors();
                    logoUploadStatus.value = 'Logo cleared.';
                },
                onError: () => {
                    logoUploadError.value =
                        'We could not clear this logo. Please try again.';
                },
                onHttpException: () => {
                    logoUploadError.value =
                        'We could not clear this logo. Please try again.';
                    return false;
                },
                onNetworkError: () => {
                    logoUploadError.value =
                        'We could not clear this logo. Please try again.';
                    return false;
                },
            },
        ),
    );
}

onUnmounted(() => releaseLogoUploadPreview());

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
            <div
                class="relative mt-2 flex flex-wrap items-end justify-between gap-4"
            >
                <div>
                    <h1 class="font-serif text-4xl tracking-tight">
                        {{ props.site.name }}
                    </h1>
                    <p class="mt-2 text-sm text-[var(--workspace-muted)]">
                        Build your page one block at a time.
                    </p>
                </div>
                <div class="ml-auto flex flex-col items-end gap-4 lg:flex-row">
                    <details class="group relative">
                        <summary
                            aria-controls="site-name-dropdown"
                            class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-4 text-sm font-semibold marker:hidden hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)]"
                        >
                            <span>Rename site</span>
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 20 20"
                                fill="none"
                                class="size-4 transition-transform group-open:rotate-180"
                            >
                                <path
                                    d="m5 7.5 5 5 5-5"
                                    stroke="currentColor"
                                    stroke-width="1.75"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </summary>
                        <div
                            id="site-name-dropdown"
                            class="absolute top-full right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2.5rem)] rounded-xl border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
                        >
                            <form
                                class="space-y-4"
                                @submit.prevent="renameSite"
                            >
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
                                        :disabled="nameForm.processing"
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
                                    :disabled="
                                        uploadInProgress || nameForm.processing
                                    "
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
                    <details class="group relative">
                        <summary
                            aria-controls="site-logo-dropdown"
                            class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-4 text-sm font-semibold marker:hidden hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)]"
                        >
                            <span>Site logo</span>
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 20 20"
                                fill="none"
                                class="size-4 transition-transform group-open:rotate-180"
                            >
                                <path
                                    d="m5 7.5 5 5 5-5"
                                    stroke="currentColor"
                                    stroke-width="1.75"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </summary>
                        <div
                            id="site-logo-dropdown"
                            class="absolute top-full right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2.5rem)] rounded-xl border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
                        >
                            <div class="space-y-4">
                                <div>
                                    <label
                                        for="site-logo-file"
                                        class="mb-2 block text-sm font-semibold"
                                    >
                                        {{
                                            props.site.logo
                                                ? 'Replace logo'
                                                : 'Choose logo'
                                        }}
                                    </label>
                                    <input
                                        id="site-logo-file"
                                        ref="logoFileInput"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                        :disabled="
                                            editorWriteInProgress ||
                                            logoUploadForm.processing ||
                                            logoAltTextForm.processing ||
                                            logoClearForm.processing
                                        "
                                        :aria-invalid="
                                            Boolean(
                                                logoUploadError ||
                                                logoUploadForm.errors.image,
                                            )
                                        "
                                        :aria-describedby="
                                            logoUploadError ||
                                            logoUploadForm.errors.image
                                                ? 'site-logo-error'
                                                : 'site-logo-help'
                                        "
                                        class="block min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-2 text-sm text-[var(--workspace-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                        @change="selectLogoFile"
                                    />
                                    <p
                                        id="site-logo-help"
                                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                                    >
                                        JPEG or PNG, up to 5 MB. The preview
                                        updates after upload.
                                    </p>
                                    <p
                                        v-if="
                                            logoUploadError ||
                                            logoUploadForm.errors.image
                                        "
                                        id="site-logo-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{
                                            logoUploadError ||
                                            logoUploadForm.errors.image
                                        }}
                                    </p>
                                </div>
                                <p
                                    v-if="
                                        logoUploadForm.processing &&
                                        logoUploadForm.progress
                                    "
                                    role="status"
                                    aria-live="polite"
                                    class="text-sm text-[var(--workspace-muted)]"
                                >
                                    Uploading logo:
                                    {{ logoUploadForm.progress.percentage }}%
                                </p>
                                <p
                                    v-else-if="logoUploadStatus"
                                    role="status"
                                    aria-live="polite"
                                    class="text-sm text-[var(--workspace-muted)]"
                                >
                                    {{ logoUploadStatus }}
                                </p>
                                <div>
                                    <label
                                        for="site-logo-alt-text"
                                        class="mb-2 block text-sm font-semibold"
                                    >
                                        Logo description (alt text)
                                    </label>
                                    <input
                                        id="site-logo-alt-text"
                                        ref="logoAltTextInput"
                                        v-model="logoDraftAltText"
                                        type="text"
                                        maxlength="255"
                                        placeholder="Leave blank to use the site name"
                                        :disabled="
                                            uploadInProgress ||
                                            logoUploadForm.processing ||
                                            logoAltTextForm.processing ||
                                            logoClearForm.processing
                                        "
                                        :aria-invalid="
                                            Boolean(
                                                logoAltTextError ||
                                                logoAltTextForm.errors
                                                    .alt_text ||
                                                logoUploadForm.errors.alt_text,
                                            )
                                        "
                                        :aria-describedby="
                                            logoAltTextError ||
                                            logoAltTextForm.errors.alt_text ||
                                            logoUploadForm.errors.alt_text
                                                ? 'site-logo-alt-error'
                                                : undefined
                                        "
                                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                        @input="clearLogoAltTextFeedback"
                                    />
                                    <p
                                        v-if="
                                            logoAltTextError ||
                                            logoAltTextForm.errors.alt_text ||
                                            logoUploadForm.errors.alt_text
                                        "
                                        id="site-logo-alt-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{
                                            logoAltTextError ||
                                            logoAltTextForm.errors.alt_text ||
                                            logoUploadForm.errors.alt_text
                                        }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="props.site.logo"
                                        type="button"
                                        :disabled="
                                            uploadInProgress ||
                                            !isLogoAltTextDirty ||
                                            logoUploadForm.processing ||
                                            logoAltTextForm.processing ||
                                            logoClearForm.processing
                                        "
                                        class="min-h-10 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-50"
                                        @click="saveLogoAltText"
                                    >
                                        {{
                                            logoAltTextForm.processing
                                                ? 'Saving description…'
                                                : 'Save description'
                                        }}
                                    </button>
                                    <button
                                        v-if="props.site.logo"
                                        type="button"
                                        :disabled="
                                            uploadInProgress ||
                                            logoUploadForm.processing ||
                                            logoAltTextForm.processing ||
                                            logoClearForm.processing
                                        "
                                        class="min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-50 dark:text-red-300"
                                        @click="clearLogo"
                                    >
                                        {{
                                            logoClearForm.processing
                                                ? 'Clearing…'
                                                : 'Clear logo'
                                        }}
                                    </button>
                                </div>
                                <p
                                    v-if="logoAltTextStatus"
                                    role="status"
                                    aria-live="polite"
                                    class="text-sm text-[var(--workspace-muted)]"
                                >
                                    {{ logoAltTextStatus }}
                                </p>
                            </div>
                        </div>
                    </details>
                    <details ref="appearanceDetails" class="group relative">
                        <summary
                            aria-controls="site-appearance-dropdown"
                            class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-4 text-sm font-semibold marker:hidden hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)]"
                        >
                            <span>Theme &amp; footer</span>
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 20 20"
                                fill="none"
                                class="size-4 transition-transform group-open:rotate-180"
                            >
                                <path
                                    d="m5 7.5 5 5 5-5"
                                    stroke="currentColor"
                                    stroke-width="1.75"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </summary>
                        <div
                            id="site-appearance-dropdown"
                            class="absolute top-full right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2.5rem)] rounded-xl border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
                        >
                            <form
                                class="space-y-4"
                                @submit.prevent="saveAppearance"
                            >
                                <div>
                                    <label
                                        for="site-theme"
                                        class="mb-2 block text-sm font-semibold"
                                        >Page theme</label
                                    >
                                    <select
                                        id="site-theme"
                                        ref="themeInput"
                                        v-model="appearanceForm.theme_key"
                                        :disabled="appearanceForm.processing"
                                        :aria-invalid="
                                            Boolean(
                                                appearanceForm.errors.theme_key,
                                            )
                                        "
                                        :aria-describedby="
                                            appearanceForm.errors.theme_key
                                                ? 'site-theme-help site-theme-error'
                                                : 'site-theme-help'
                                        "
                                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20"
                                        @change="clearAppearanceError"
                                    >
                                        <option
                                            v-for="theme in siteThemes"
                                            :key="theme.key"
                                            :value="theme.key"
                                        >
                                            {{ theme.label }}
                                        </option>
                                    </select>
                                    <p
                                        id="site-theme-help"
                                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                                    >
                                        {{
                                            siteThemes.find(
                                                (theme) =>
                                                    theme.key ===
                                                    appearanceForm.theme_key,
                                            )?.description
                                        }}
                                    </p>
                                    <p
                                        v-if="appearanceForm.errors.theme_key"
                                        id="site-theme-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ appearanceForm.errors.theme_key }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="site-footer-text"
                                        class="mb-2 block text-sm font-semibold"
                                        >Footer text</label
                                    >
                                    <input
                                        id="site-footer-text"
                                        ref="footerTextInput"
                                        v-model="appearanceForm.footer.text"
                                        type="text"
                                        autocomplete="off"
                                        :disabled="appearanceForm.processing"
                                        :aria-invalid="
                                            Boolean(
                                                appearanceForm.errors[
                                                    'footer.text'
                                                ],
                                            )
                                        "
                                        :aria-describedby="
                                            appearanceForm.errors['footer.text']
                                                ? 'site-footer-help site-footer-error'
                                                : 'site-footer-help'
                                        "
                                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20"
                                        @input="clearAppearanceError"
                                    />
                                    <p
                                        id="site-footer-help"
                                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                                    >
                                        Optional single-line text at the bottom
                                        of your page.
                                    </p>
                                    <p
                                        v-if="
                                            appearanceForm.errors['footer.text']
                                        "
                                        id="site-footer-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{
                                            appearanceForm.errors['footer.text']
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="site-slug"
                                        class="mb-2 block text-sm font-semibold"
                                        >Shareable address</label
                                    >
                                    <div
                                        class="flex min-h-11 items-center rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-soft)] px-3 text-sm text-[var(--workspace-muted)]"
                                    >
                                        <span>/s/</span>
                                        <input
                                            id="site-slug"
                                            ref="siteSlugInput"
                                            v-model="appearanceForm.slug"
                                            type="text"
                                            maxlength="100"
                                            pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                            :disabled="
                                                Boolean(
                                                    props.site.published_at,
                                                ) || appearanceForm.processing
                                            "
                                            :aria-invalid="
                                                Boolean(
                                                    appearanceForm.errors.slug,
                                                )
                                            "
                                            :aria-describedby="
                                                appearanceForm.errors.slug
                                                    ? 'site-slug-help site-slug-error'
                                                    : 'site-slug-help'
                                            "
                                            class="min-w-0 flex-1 bg-transparent px-1 text-[var(--workspace-ink)] outline-none disabled:opacity-70"
                                            @input="clearAppearanceError"
                                        />
                                    </div>
                                    <p
                                        id="site-slug-help"
                                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                                    >
                                        Lowercase letters, numbers, and hyphens.
                                        Fixed after first publication.
                                    </p>
                                    <p
                                        v-if="appearanceForm.errors.slug"
                                        id="site-slug-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ appearanceForm.errors.slug }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="site-seo-title"
                                        class="mb-2 block text-sm font-semibold"
                                        >Page title</label
                                    >
                                    <input
                                        id="site-seo-title"
                                        ref="seoTitleInput"
                                        v-model="appearanceForm.seo_title"
                                        type="text"
                                        maxlength="255"
                                        :disabled="appearanceForm.processing"
                                        :aria-invalid="
                                            Boolean(
                                                appearanceForm.errors.seo_title,
                                            )
                                        "
                                        :aria-describedby="
                                            appearanceForm.errors.seo_title
                                                ? 'site-seo-title-error'
                                                : undefined
                                        "
                                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-sm text-[var(--workspace-ink)]"
                                        @input="clearAppearanceError"
                                    />
                                    <p
                                        v-if="appearanceForm.errors.seo_title"
                                        id="site-seo-title-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ appearanceForm.errors.seo_title }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="site-seo-description"
                                        class="mb-2 block text-sm font-semibold"
                                        >Page description</label
                                    >
                                    <textarea
                                        id="site-seo-description"
                                        ref="seoDescriptionInput"
                                        v-model="appearanceForm.seo_description"
                                        maxlength="2000"
                                        rows="3"
                                        :disabled="appearanceForm.processing"
                                        :aria-invalid="
                                            Boolean(
                                                appearanceForm.errors
                                                    .seo_description,
                                            )
                                        "
                                        :aria-describedby="
                                            appearanceForm.errors
                                                .seo_description
                                                ? 'site-seo-description-error'
                                                : undefined
                                        "
                                        class="w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 py-2 text-sm text-[var(--workspace-ink)]"
                                        @input="clearAppearanceError"
                                    />
                                    <p
                                        v-if="
                                            appearanceForm.errors
                                                .seo_description
                                        "
                                        id="site-seo-description-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{
                                            appearanceForm.errors
                                                .seo_description
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="site-social-image"
                                        class="mb-2 block text-sm font-semibold"
                                        >Social preview image</label
                                    >
                                    <img
                                        v-if="props.site.social_image"
                                        :src="props.site.social_image.url"
                                        :alt="
                                            props.site.social_image.alt_text ??
                                            ''
                                        "
                                        class="mb-3 max-h-32 rounded-lg object-contain"
                                    />
                                    <input
                                        id="site-social-image"
                                        ref="socialImageInput"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                        :disabled="editorWriteInProgress"
                                        :aria-invalid="
                                            Boolean(
                                                socialImageError ||
                                                socialImageForm.errors.image,
                                            )
                                        "
                                        :aria-describedby="
                                            socialImageError ||
                                            socialImageForm.errors.image
                                                ? 'site-social-image-help site-social-image-error'
                                                : 'site-social-image-help'
                                        "
                                        class="block min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-2 text-sm"
                                        @change="selectSocialImage"
                                    />
                                    <p
                                        id="site-social-image-help"
                                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                                    >
                                        JPEG or PNG, up to 5 MB.
                                    </p>
                                    <p
                                        v-if="
                                            socialImageError ||
                                            socialImageForm.errors.image
                                        "
                                        id="site-social-image-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{
                                            socialImageError ||
                                            socialImageForm.errors.image
                                        }}
                                    </p>
                                    <p
                                        v-if="socialImageStatus"
                                        role="status"
                                        aria-live="polite"
                                        class="mt-2 text-sm text-[var(--workspace-muted)]"
                                    >
                                        {{ socialImageStatus }}
                                    </p>
                                    <button
                                        v-if="props.site.social_image"
                                        type="button"
                                        :disabled="
                                            editorWriteInProgress ||
                                            socialImageClearForm.processing
                                        "
                                        class="mt-2 min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 disabled:opacity-50 dark:text-red-300"
                                        @click="clearSocialImage"
                                    >
                                        {{
                                            socialImageClearForm.processing
                                                ? 'Clearing…'
                                                : 'Clear social image'
                                        }}
                                    </button>
                                </div>
                                <p
                                    v-if="appearanceError"
                                    role="alert"
                                    class="text-sm text-red-700 dark:text-red-300"
                                >
                                    {{ appearanceError }}
                                </p>
                                <p
                                    v-if="appearanceSaved"
                                    role="status"
                                    class="text-sm font-semibold text-[var(--workspace-green)]"
                                >
                                    Page appearance saved.
                                </p>
                                <button
                                    type="submit"
                                    :disabled="
                                        uploadInProgress ||
                                        appearanceForm.processing
                                    "
                                    class="min-h-11 w-full rounded-lg bg-[var(--workspace-green)] px-4 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60 dark:text-[var(--workspace-surface)]"
                                >
                                    {{
                                        appearanceForm.processing
                                            ? 'Saving…'
                                            : 'Save appearance'
                                    }}
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </header>

        <section
            aria-labelledby="publishing-heading"
            class="mb-6 rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)] sm:flex sm:items-center sm:justify-between sm:gap-6"
        >
            <div>
                <h2 id="publishing-heading" class="font-serif text-xl">
                    Publishing
                </h2>
                <p
                    v-if="!props.site.published_at"
                    class="mt-1 text-sm text-[var(--workspace-muted)]"
                >
                    This page has not been published yet.
                </p>
                <p
                    v-else-if="props.site.has_unpublished_changes"
                    class="mt-1 text-sm text-[var(--workspace-muted)]"
                >
                    Saved draft changes are not on the published page yet.
                </p>
                <p v-else class="mt-1 text-sm text-[var(--workspace-muted)]">
                    Published
                    {{ new Date(props.site.published_at).toLocaleString() }}.
                </p>
                <a
                    v-if="props.site.published_url"
                    :href="props.site.published_url"
                    target="_blank"
                    rel="noreferrer"
                    class="mt-2 inline-flex min-h-10 items-center rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold text-[var(--workspace-green)] underline underline-offset-2 focus:ring-2 focus:ring-[var(--workspace-green)] focus:outline-none"
                >
                    View published page
                    <span class="sr-only"> (opens in a new tab)</span>
                </a>
                <p
                    v-if="hasUnsavedEditorChanges"
                    role="status"
                    class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                >
                    Save your pending editor changes before publishing.
                </p>
                <p
                    v-if="publishError"
                    role="alert"
                    class="mt-1 text-sm text-red-700 dark:text-red-300"
                >
                    {{ publishError }}
                </p>
                <p
                    v-if="publishStatus"
                    role="status"
                    aria-live="polite"
                    class="mt-1 text-sm text-[var(--workspace-green)]"
                >
                    {{ publishStatus }}
                </p>
            </div>
            <button
                type="button"
                :disabled="editorWriteInProgress || hasUnsavedEditorChanges"
                class="mt-4 min-h-11 rounded-lg bg-[var(--workspace-green)] px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:mt-0"
                @click="publishSite"
            >
                {{
                    publishForm.processing
                        ? 'Publishing…'
                        : props.site.published_at
                          ? 'Publish saved draft'
                          : 'Publish page'
                }}
            </button>
        </section>

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
                            !uploadInProgress &&
                            props.blocks.length > 1 &&
                            !orderForm.processing &&
                            !addForm.processing &&
                            !deleteForm.processing &&
                            !imageUploadForm.processing &&
                            !altTextForm.processing
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
                                uploadInProgress ||
                                orderForm.processing ||
                                deleteForm.processing ||
                                addForm.processing ||
                                imageUploadForm.processing ||
                                altTextForm.processing
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
                                    uploadInProgress ||
                                    block.position === 0 ||
                                    orderForm.processing ||
                                    deleteForm.processing ||
                                    addForm.processing ||
                                    imageUploadForm.processing ||
                                    altTextForm.processing
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
                                    uploadInProgress ||
                                    block.position ===
                                        props.blocks.length - 1 ||
                                    orderForm.processing ||
                                    deleteForm.processing ||
                                    addForm.processing ||
                                    imageUploadForm.processing ||
                                    altTextForm.processing
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
                                uploadInProgress ||
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
                <div class="site-preview" :data-theme="props.site.theme_key">
                    <header
                        class="border-b border-[var(--site-preview-border)] px-6 py-7 sm:px-10"
                    >
                        <div class="flex items-center gap-2">
                            <img
                                v-if="logoPreviewUrl()"
                                :src="logoPreviewUrl() ?? undefined"
                                :alt="logoPreviewAltText()"
                                class="max-h-24 max-w-40 shrink-0 object-contain object-left"
                            />
                            <h3
                                class="min-w-0 font-serif text-2xl font-semibold tracking-tight break-words"
                            >
                                {{ props.site.name }}
                            </h3>
                        </div>
                        <nav
                            v-if="props.blocks.length"
                            aria-label="Page sections"
                            class="mt-4"
                        >
                            <ul class="flex flex-wrap gap-2">
                                <li
                                    v-for="block in props.blocks"
                                    :key="block.id"
                                >
                                    <a
                                        :href="`#block-${block.id}`"
                                        class="inline-flex min-h-10 items-center rounded-lg border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)] px-3 py-2 text-sm font-semibold text-[var(--site-preview-accent)] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                    >
                                        {{ sectionNavigationLabel(block) }}
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </header>
                    <div
                        v-if="props.blocks.length === 0"
                        class="flex min-h-[27rem] flex-col items-center justify-center px-6 text-center"
                    >
                        <div
                            class="mb-5 grid size-14 place-items-center rounded-2xl bg-[var(--site-preview-soft)] font-serif text-3xl text-[var(--site-preview-accent)]"
                        >
                            +
                        </div>
                        <h3 class="font-serif text-2xl">
                            A blank page, ready for your story
                        </h3>
                        <p
                            class="mt-3 max-w-sm text-sm text-[var(--site-preview-muted)]"
                        >
                            Add your first block to see the page take shape.
                        </p>
                    </div>
                    <div v-else>
                        <section
                            v-for="block in props.blocks"
                            :key="block.id"
                            :id="`block-${block.id}`"
                            class="border-b border-[var(--site-preview-border)] px-6 py-12 last:border-b-0 sm:px-10"
                            :class="
                                block.type === 'about'
                                    ? 'bg-[var(--site-preview-soft)]'
                                    : ''
                            "
                        >
                            <template v-if="block.type === 'hero'">
                                <p
                                    class="text-xs font-bold tracking-[0.14em] text-[var(--site-preview-accent)] uppercase"
                                >
                                    Welcome
                                </p>
                                <h3
                                    class="mt-4 max-w-xl font-serif text-4xl leading-tight sm:text-5xl"
                                >
                                    {{
                                        previewHeading(
                                            block,
                                            'Welcome to our church',
                                        )
                                    }}
                                </h3>
                                <p
                                    class="mt-5 max-w-prose whitespace-pre-line text-[var(--site-preview-muted)]"
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
                                        contentFor(block).link_type ===
                                        'external'
                                            ? '_blank'
                                            : undefined
                                    "
                                    :rel="
                                        contentFor(block).link_type ===
                                        'external'
                                            ? 'noopener noreferrer'
                                            : undefined
                                    "
                                    class="mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                >
                                    {{ contentFor(block).button_label }}
                                </a>
                            </template>
                            <template v-else-if="block.type === 'about'">
                                <p
                                    class="text-xs font-bold tracking-[0.14em] text-[var(--site-preview-accent)] uppercase"
                                >
                                    About us
                                </p>
                                <h3 class="mt-3 font-serif text-3xl">
                                    {{
                                        previewHeading(
                                            block,
                                            'Your introduction',
                                        )
                                    }}
                                </h3>
                                <p
                                    class="mt-4 whitespace-pre-line text-[var(--site-preview-muted)]"
                                >
                                    {{
                                        contentFor(block).body ||
                                        'Tell visitors who you are and what matters to your community.'
                                    }}
                                </p>
                            </template>
                            <template v-else-if="block.type === 'heading_text'">
                                <h3 class="font-serif text-2xl">
                                    {{ previewHeading(block, 'Your heading') }}
                                </h3>
                                <p
                                    class="mt-4 whitespace-pre-line text-[var(--site-preview-muted)]"
                                >
                                    {{
                                        contentFor(block).body ||
                                        'Add the details you want visitors to know.'
                                    }}
                                </p>
                            </template>
                            <template
                                v-else-if="block.type === 'service_times'"
                            >
                                <h3 class="font-serif text-2xl">
                                    {{ previewHeading(block, 'Service times') }}
                                </h3>
                                <ul
                                    v-if="contentFor(block).entries?.length"
                                    class="mt-6 divide-y divide-[var(--site-preview-border)]"
                                >
                                    <li
                                        v-for="(entry, index) in contentFor(
                                            block,
                                        ).entries"
                                        :key="index"
                                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3"
                                    >
                                        <span class="font-semibold">{{
                                            entry.day.charAt(0).toUpperCase() +
                                            entry.day.slice(1)
                                        }}</span>
                                        <span
                                            class="text-[var(--site-preview-muted)]"
                                            >{{
                                                formatServiceTime(entry.time)
                                            }}</span
                                        >
                                        <span
                                            v-if="entry.label"
                                            class="w-full text-sm text-[var(--site-preview-muted)]"
                                            >{{ entry.label }}</span
                                        >
                                    </li>
                                </ul>
                                <p
                                    v-else
                                    class="mt-4 text-[var(--site-preview-muted)]"
                                >
                                    Add your weekly gatherings in the editor.
                                </p>
                            </template>
                            <template v-else-if="block.type === 'contact'">
                                <h3 class="font-serif text-2xl">
                                    {{ previewHeading(block, 'Contact us') }}
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
                                        class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                        >{{ contentFor(block).email }}</a
                                    >
                                    <span
                                        v-else-if="contentFor(block).email"
                                        class="text-[var(--site-preview-muted)]"
                                        >{{ contentFor(block).email }}</span
                                    >
                                    <a
                                        v-if="phoneHref(block)"
                                        :href="phoneHref(block) ?? undefined"
                                        class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                        >{{ contentFor(block).phone }}</a
                                    >
                                    <span
                                        v-else-if="contentFor(block).phone"
                                        class="text-[var(--site-preview-muted)]"
                                        >{{ contentFor(block).phone }}</span
                                    >
                                </div>
                                <p
                                    v-else
                                    class="mt-4 text-[var(--site-preview-muted)]"
                                >
                                    Add an email or phone number in the editor.
                                </p>
                            </template>
                            <template v-else-if="block.type === 'image'">
                                <div
                                    class="overflow-hidden rounded-xl border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)]"
                                >
                                    <img
                                        v-if="blockMediaUrl(block)"
                                        :src="blockMediaUrl(block) ?? undefined"
                                        :alt="blockPreviewAltText(block)"
                                        class="max-h-[32rem] w-full object-contain"
                                    />
                                    <div
                                        v-else
                                        role="group"
                                        aria-label="Image placeholder"
                                        class="flex min-h-64 flex-col items-center justify-center p-8 text-center"
                                    >
                                        <span class="font-semibold">Image</span>
                                        <span
                                            class="mt-2 text-sm text-[var(--site-preview-muted)]"
                                            >Choose an image in the
                                            editor.</span
                                        >
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="block.type === 'text_image'">
                                <div
                                    class="grid gap-8 md:grid-cols-2 md:items-center"
                                >
                                    <div>
                                        <h3 class="font-serif text-2xl">
                                            {{
                                                previewHeading(
                                                    block,
                                                    'Your heading',
                                                )
                                            }}
                                        </h3>
                                        <p
                                            class="mt-4 whitespace-pre-line text-[var(--site-preview-muted)]"
                                        >
                                            {{
                                                contentFor(block).body ||
                                                'Add the details you want visitors to know.'
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="overflow-hidden rounded-xl border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)]"
                                    >
                                        <img
                                            v-if="blockMediaUrl(block)"
                                            :src="
                                                blockMediaUrl(block) ??
                                                undefined
                                            "
                                            :alt="blockPreviewAltText(block)"
                                            class="max-h-[32rem] min-h-56 w-full object-contain"
                                        />
                                        <div
                                            v-else
                                            role="group"
                                            aria-label="Image placeholder"
                                            class="flex min-h-56 flex-col items-center justify-center p-8 text-center"
                                        >
                                            <span class="font-semibold"
                                                >Image</span
                                            >
                                            <span
                                                class="mt-2 text-sm text-[var(--site-preview-muted)]"
                                                >Choose an image in the
                                                editor.</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else-if="block.type === 'video'">
                                <div
                                    class="mx-auto max-w-3xl overflow-hidden rounded-xl bg-[var(--site-preview-soft)]"
                                >
                                    <div class="aspect-video">
                                        <iframe
                                            v-if="videoEmbedUrl(block)"
                                            :src="
                                                videoEmbedUrl(block) ??
                                                undefined
                                            "
                                            title="YouTube or Vimeo video preview"
                                            loading="lazy"
                                            allowfullscreen
                                            class="h-full w-full border-0"
                                        />
                                        <div
                                            v-else
                                            role="status"
                                            class="flex h-full flex-col items-center justify-center p-6 text-center text-sm text-[var(--site-preview-muted)]"
                                        >
                                            <span class="font-semibold"
                                                >Video preview</span
                                            >
                                            <span class="mt-2"
                                                >Enter a supported YouTube or
                                                Vimeo link in the editor.</span
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
                            <p v-else class="text-[var(--site-preview-muted)]">
                                {{ labelFor(block.type) }} block
                            </p>
                        </section>
                    </div>
                    <footer
                        v-if="props.site.footer.text"
                        class="border-t border-[var(--site-preview-border)] px-6 py-5 text-sm text-[var(--site-preview-muted)] sm:px-10"
                    >
                        {{ props.site.footer.text }}
                    </footer>
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
                    <section
                        v-if="
                            ['image', 'text_image'].includes(selectedBlock.type)
                        "
                        aria-labelledby="block-image-heading"
                        class="mt-6 space-y-4 border-t border-[var(--workspace-line)] pt-5"
                    >
                        <h3
                            id="block-image-heading"
                            class="text-sm font-semibold"
                        >
                            Image
                        </h3>
                        <div>
                            <label
                                for="block-image-file"
                                class="mb-2 block text-sm font-semibold"
                            >
                                {{
                                    selectedBlock.content.media_asset_id
                                        ? 'Replace image'
                                        : 'Choose image'
                                }}
                            </label>
                            <input
                                id="block-image-file"
                                ref="imageInput"
                                type="file"
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                :disabled="
                                    editorWriteInProgress ||
                                    imageUploadForm.processing ||
                                    altTextForm.processing
                                "
                                :aria-invalid="
                                    Boolean(
                                        imageUploadError ||
                                        imageUploadForm.errors.image,
                                    )
                                "
                                :aria-describedby="
                                    imageUploadError ||
                                    imageUploadForm.errors.image
                                        ? 'block-image-error'
                                        : 'block-image-help'
                                "
                                class="block min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-2 text-sm text-[var(--workspace-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                @change="selectImageFile"
                            />
                            <p
                                id="block-image-help"
                                class="mt-2 text-xs text-[var(--workspace-muted)]"
                            >
                                JPEG or PNG, up to 5 MB. Uploading replaces the
                                saved image.
                            </p>
                            <p
                                v-if="
                                    imageUploadError ||
                                    imageUploadForm.errors.image
                                "
                                id="block-image-error"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{
                                    imageUploadError ||
                                    imageUploadForm.errors.image
                                }}
                            </p>
                        </div>
                        <p
                            v-if="
                                imageUploadForm.processing &&
                                imageUploadForm.progress
                            "
                            role="status"
                            aria-live="polite"
                            class="text-sm text-[var(--workspace-muted)]"
                        >
                            Uploading image:
                            {{ imageUploadForm.progress.percentage }}%
                        </p>
                        <p
                            v-else-if="imageUploadStatus"
                            role="status"
                            aria-live="polite"
                            class="text-sm text-[var(--workspace-muted)]"
                        >
                            {{ imageUploadStatus }}
                        </p>
                        <div>
                            <label
                                for="block-image-alt-text"
                                class="mb-2 block text-sm font-semibold"
                            >
                                Image description (alt text)
                            </label>
                            <input
                                id="block-image-alt-text"
                                ref="altTextInput"
                                v-model="draftAltText"
                                type="text"
                                maxlength="255"
                                placeholder="Describe the image, or leave blank if decorative"
                                :disabled="
                                    uploadInProgress ||
                                    imageUploadForm.processing ||
                                    altTextForm.processing
                                "
                                :aria-invalid="
                                    Boolean(
                                        altTextError ||
                                        altTextForm.errors.alt_text ||
                                        imageUploadForm.errors.alt_text,
                                    )
                                "
                                :aria-describedby="
                                    altTextError ||
                                    altTextForm.errors.alt_text ||
                                    imageUploadForm.errors.alt_text
                                        ? 'block-image-alt-error'
                                        : undefined
                                "
                                class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                @input="clearAltTextFeedback"
                            />
                            <p
                                v-if="
                                    altTextError ||
                                    altTextForm.errors.alt_text ||
                                    imageUploadForm.errors.alt_text
                                "
                                id="block-image-alt-error"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{
                                    altTextError ||
                                    altTextForm.errors.alt_text ||
                                    imageUploadForm.errors.alt_text
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="selectedBlock.content.media_asset_id"
                                type="button"
                                :disabled="
                                    uploadInProgress ||
                                    !isAltTextDirty ||
                                    altTextForm.processing ||
                                    imageUploadForm.processing ||
                                    clearImagePending
                                "
                                class="min-h-10 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-50"
                                @click="saveAltText"
                            >
                                {{
                                    altTextForm.processing
                                        ? 'Saving description…'
                                        : 'Save description'
                                }}
                            </button>
                            <button
                                v-if="selectedBlock.content.media_asset_id"
                                type="button"
                                :disabled="
                                    uploadInProgress ||
                                    (isAltTextDirty && !clearImagePending) ||
                                    imageUploadForm.processing ||
                                    altTextForm.processing
                                "
                                aria-describedby="block-image-clear-help"
                                class="min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-50 dark:text-red-300"
                                @click="requestClearBlockImage"
                            >
                                {{
                                    clearImagePending
                                        ? 'Undo clear'
                                        : 'Clear image'
                                }}
                            </button>
                        </div>
                        <p
                            v-if="selectedBlock.content.media_asset_id"
                            id="block-image-clear-help"
                            class="text-xs text-[var(--workspace-muted)]"
                        >
                            <template v-if="clearImagePending">
                                Save the block to clear this image, or undo the
                                clear to keep it. You can also choose a
                                replacement image now.
                            </template>
                            <template v-else>
                                Save a changed description before clearing the
                                image. Clear image takes effect when you save
                                the block.
                            </template>
                        </p>
                        <p
                            v-if="altTextStatus"
                            role="status"
                            aria-live="polite"
                            class="text-sm text-[var(--workspace-muted)]"
                        >
                            {{ altTextStatus }}
                        </p>
                    </section>
                    <form
                        class="mt-6 space-y-5 border-t border-[var(--workspace-line)] pt-5"
                        @submit.prevent="saveBlock"
                    >
                        <div
                            v-if="
                                selectedBlock.type !== 'plain_text' &&
                                selectedBlock.type !== 'image' &&
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
                                    uploadInProgress ||
                                    saveForm.processing ||
                                    addForm.processing ||
                                    deleteForm.processing ||
                                    orderForm.processing ||
                                    imageUploadForm.processing ||
                                    altTextForm.processing
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
                                selectedBlock.type !== 'image' &&
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
                                    uploadInProgress ||
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
                                    uploadInProgress ||
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
                                        uploadInProgress ||
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
                                        uploadInProgress ||
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
                                        uploadInProgress ||
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
                                        uploadInProgress ||
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
                                                    uploadInProgress ||
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
                                                    uploadInProgress ||
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
                                                    uploadInProgress ||
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
                                                uploadInProgress ||
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
                                                uploadInProgress ||
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
                                                uploadInProgress ||
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
                                    uploadInProgress ||
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
                                        uploadInProgress ||
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
                                        uploadInProgress ||
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
                                uploadInProgress ||
                                saveForm.processing ||
                                addForm.processing ||
                                deleteForm.processing ||
                                orderForm.processing ||
                                imageUploadForm.processing ||
                                altTextForm.processing ||
                                !isContentDirty
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
                                uploadInProgress ||
                                deleteForm.processing ||
                                saveForm.processing ||
                                orderForm.processing ||
                                addForm.processing ||
                                imageUploadForm.processing ||
                                altTextForm.processing
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
