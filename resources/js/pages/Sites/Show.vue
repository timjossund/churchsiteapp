<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import SiteMediaController from '@/actions/App/Http/Controllers/SiteMediaController';
import { store as uploadPageBlockImage } from '@/routes/sites/pages/blocks/image';
import HeroOptions, {
    type HeroExtras,
    type HeroButton,
    type HeroStyle,
} from '@/components/sites/HeroOptions.vue';
import PageSettings from '@/components/sites/PageSettings.vue';
import { dashboard } from '@/routes';
import { mountSiteMenu } from '@/lib/site-menu';
import { mountHeroMotion } from '@/lib/hero-motion';
import { openStreetMapLinks } from '@/lib/open-street-map';
const previewRoot = ref<HTMLElement | null>(null);
let disposeHeroMotion: (() => void) | undefined;
function refreshHeroMotion() {
    disposeHeroMotion?.();
    disposeHeroMotion = previewRoot.value
        ? mountHeroMotion(previewRoot.value)
        : undefined;
}

const previewHeader = ref<HTMLElement | null>(null);
let disposeSiteMenu: (() => void) | undefined;

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
type HeroLinkType = HeroButton['link_type'];
type SiteTheme = 'warm' | 'clean' | 'bold';
type BlockStyle = HeroStyle & {
    layout?: 'image_left' | 'image_right' | 'list' | 'grid';
    alignment?: 'left' | 'center';
    background?: 'theme' | 'soft' | 'accent' | 'contrast';
    spacing?: 'compact' | 'current' | 'spacious';
    content_width?: 'narrow' | 'current' | 'full';
    heading_size?: 'small' | 'current' | 'large';
    image_ratio?: 'original' | 'landscape' | 'square' | 'portrait';
    crop_position?: 'top' | 'center' | 'bottom';
    corner_style?: 'current' | 'square' | 'rounded';
};
type ServiceTimeEntry = { day: string; time: string; label: string };
type BlockContent = {
    welcome_label?: string;
    target_page_id?: number | null;
    secondary_button?: HeroButton;
    heading?: string;
    body?: string;
    button_label?: string;
    link_type?: HeroLinkType;
    target_block_id?: number | null;
    external_url?: string;
    entries?: ServiceTimeEntry[];
    email?: string;
    phone?: string;
    address?: string;
    map?: { enabled: boolean; url: string };
    media_asset_id?: number | null;
    caption?: string;
    url?: string;
    style?: BlockStyle;
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
        appearance: { font_pairing: string; button_shape: string };
        appearance_colors: Record<string, string>;
        footer: { text: string };
        slug: string | null;
        published_at: string | null;
        published_url: string | null;
        has_unpublished_changes: boolean;
        logo: {
            media_asset_id: number;
            url: string;
            alt_text: string | null;
        } | null;
    };
    selected_page: {
        id: number;
        name: string;
        position: number;
        is_home: boolean;
        path: string | null;
        seo_title: string | null;
        seo_description: string | null;
        default_title: string;
        published_url: string | null;
        social_image: {
            media_asset_id: number;
            url: string;
            alt_text: string | null;
        } | null;
    };
    navigation_pages: { id: number; name: string }[];
    blocks: SiteBlock[];
}>();

function editorActionUrl(url: string): string {
    return `${url}${url.includes('?') ? '&' : '?'}editor_page=${props.selected_page.id}`;
}
function confirmEditorDiscard(): boolean {
    return (
        !hasUnsavedEditorChanges.value ||
        window.confirm('Discard your unsaved page changes?')
    );
}

const blockBaseUrl = computed(
    () => `/sites/${props.site.id}/pages/${props.selected_page.id}/blocks`,
);

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
        description: 'Share an address, phone number, or email',
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

function heroExtras(content: BlockContent = {}): HeroExtras {
    return {
        welcome_label: content.welcome_label ?? 'Welcome',
        target_page_id: content.target_page_id ?? null,
        secondary_button: {
            button_label: '',
            link_type: 'none',
            target_block_id: null,
            target_page_id: null,
            external_url: '',
            ...content.secondary_button,
        },
    };
}
const draftHero = ref<HeroExtras>(heroExtras());
const savedHero = ref<HeroExtras>(heroExtras());
const blockEditor = ref<HTMLElement>();
function openFieldSection(element: HTMLElement) {
    let section = element.closest('details');
    while (section) {
        section.open = true;
        section = section.parentElement?.closest('details') ?? null;
    }
}
function revealEditorErrors(): boolean {
    const fields = blockEditor.value?.querySelectorAll<HTMLElement>(
        '[aria-invalid="true"]',
    );
    fields?.forEach(openFieldSection);
    fields?.[0]?.focus();
    return !!fields?.length;
}
function revealInvalidField(event: Event) {
    if (event.target instanceof HTMLElement) openFieldSection(event.target);
}
const failedHeroImages = ref<Record<number, string>>({});
function heroHasImage(block: SiteBlock): boolean {
    const url = blockMediaUrl(block);
    return !!url && failedHeroImages.value[block.id] !== url;
}
const draftHeading = ref('');
const draftBody = ref('');
const savedHeading = ref('');
const savedBody = ref('');
const draftCaption = ref('');
const savedCaption = ref('');
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
const draftAddress = ref('');
const savedEmail = ref('');
const savedPhone = ref('');
const savedAddress = ref('');
const draftMapEnabled = ref(false);
const draftMapUrl = ref('');
const savedMapEnabled = ref(false);
const savedMapUrl = ref('');
const mapInputError = computed(() => {
    if (draftMapUrl.value.trim() === '' && !draftMapEnabled.value) return '';
    return openStreetMapLinks(draftMapUrl.value)
        ? ''
        : draftMapUrl.value.trim() === ''
          ? 'Add an OpenStreetMap marker link to show the map.'
          : 'This link is not a valid OpenStreetMap marker link. Check Include marker in Share, then copy the full Link URL.';
});
const draftVideoUrl = ref('');
const savedVideoUrl = ref('');
const draftBlockStyle = ref<BlockStyle>({});
const savedBlockStyle = ref<BlockStyle>({});
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
            (['image', 'text_image'].includes(selectedBlock.value.type) &&
                draftCaption.value !== savedCaption.value) ||
            ((selectedBlock.value.type === 'hero' ||
                blockHasTextButton(selectedBlock.value)) &&
                (draftHero.value.target_page_id !==
                    savedHero.value.target_page_id ||
                    (selectedBlock.value.type === 'hero' &&
                        JSON.stringify(draftHero.value) !==
                            JSON.stringify(savedHero.value)) ||
                    draftButtonLabel.value !== savedButtonLabel.value ||
                    draftLinkType.value !== savedLinkType.value ||
                    draftTargetBlockId.value !== savedTargetBlockId.value ||
                    draftExternalUrl.value !== savedExternalUrl.value)) ||
            (selectedBlock.value?.type === 'service_times' &&
                JSON.stringify(draftEntries.value) !==
                    JSON.stringify(savedEntries.value)) ||
            (selectedBlock.value?.type === 'contact' &&
                (draftEmail.value !== savedEmail.value ||
                    draftPhone.value !== savedPhone.value ||
                    draftAddress.value !== savedAddress.value ||
                    draftMapEnabled.value !== savedMapEnabled.value ||
                    draftMapUrl.value !== savedMapUrl.value)) ||
            (selectedBlock.value?.type === 'video' &&
                draftVideoUrl.value !== savedVideoUrl.value) ||
            JSON.stringify(draftBlockStyle.value) !==
                JSON.stringify(savedBlockStyle.value) ||
            (selectedBlock.value &&
                ['image', 'text_image', 'hero'].includes(
                    selectedBlock.value.type,
                ) &&
                clearImagePending.value)),
);
const isAltTextDirty = computed(
    () =>
        !!selectedBlock.value &&
        ['image', 'text_image', 'hero'].includes(selectedBlock.value.type) &&
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
const captionInput = ref<HTMLTextAreaElement | null>(null);
const buttonLabelInput = ref<HTMLInputElement | null>(null);
const linkTypeInput = ref<HTMLSelectElement | null>(null);
const targetBlockInput = ref<HTMLSelectElement | null>(null);
const externalUrlInput = ref<HTMLInputElement | null>(null);
const addServiceTimeButton = ref<HTMLButtonElement | null>(null);
const serviceTimeStatus = ref('');
const blockLayoutInput = ref<HTMLSelectElement | null>(null);
const blockAlignmentInput = ref<HTMLSelectElement | null>(null);
const blockBackgroundInput = ref<HTMLSelectElement | null>(null);
const emailInput = ref<HTMLInputElement | null>(null);
const phoneInput = ref<HTMLInputElement | null>(null);
const addressInput = ref<HTMLTextAreaElement | null>(null);
const mapEnabledInput = ref<HTMLInputElement | null>(null);
const mapUrlInput = ref<HTMLInputElement | null>(null);
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
        draftHero.value = heroExtras(block?.content);
        savedHero.value = heroExtras(block?.content);
        draftHeading.value = block?.content.heading ?? '';
        draftBody.value = block?.content.body ?? '';
        draftCaption.value = block?.content.caption ?? '';
        draftButtonLabel.value = block?.content.button_label ?? '';
        draftLinkType.value = block?.content.link_type ?? 'none';
        draftTargetBlockId.value = block?.content.target_block_id ?? null;
        draftExternalUrl.value = block?.content.external_url ?? '';
        draftEntries.value = (block?.content.entries ?? []).map((entry) => ({
            ...entry,
        }));
        draftEmail.value = block?.content.email ?? '';
        draftPhone.value = block?.content.phone ?? '';
        draftAddress.value =
            typeof block?.content.address === 'string'
                ? block.content.address
                : '';
        draftVideoUrl.value = block?.content.url ?? '';
        draftMapEnabled.value = block?.content.map?.enabled === true;
        draftMapUrl.value =
            typeof block?.content.map?.url === 'string'
                ? block.content.map.url
                : '';
        draftBlockStyle.value = block ? styleForBlock(block) : {};
        savedBlockStyle.value = { ...draftBlockStyle.value };
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
        savedCaption.value = draftCaption.value;
        savedButtonLabel.value = draftButtonLabel.value;
        savedLinkType.value = draftLinkType.value;
        savedTargetBlockId.value = draftTargetBlockId.value;
        savedExternalUrl.value = draftExternalUrl.value;
        savedEntries.value = draftEntries.value.map((entry) => ({ ...entry }));
        savedEmail.value = draftEmail.value;
        savedPhone.value = draftPhone.value;
        savedAddress.value = draftAddress.value;
        savedMapEnabled.value = draftMapEnabled.value;
        savedMapUrl.value = draftMapUrl.value;
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
    draftHero.value = heroExtras(savedHero.value);
    draftHeading.value = savedHeading.value;
    draftBody.value = savedBody.value;
    draftCaption.value = savedCaption.value;
    draftButtonLabel.value = savedButtonLabel.value;
    draftLinkType.value = savedLinkType.value;
    draftTargetBlockId.value = savedTargetBlockId.value;
    draftExternalUrl.value = savedExternalUrl.value;
    draftEntries.value = savedEntries.value.map((entry) => ({ ...entry }));
    draftEmail.value = savedEmail.value;
    draftPhone.value = savedPhone.value;
    draftAddress.value = savedAddress.value;
    draftMapEnabled.value = savedMapEnabled.value;
    draftMapUrl.value = savedMapUrl.value;
    draftVideoUrl.value = savedVideoUrl.value;
    draftBlockStyle.value = { ...savedBlockStyle.value };
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
            uploadPageBlockImage({
                site: props.site.id,
                page: props.selected_page.id,
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
            editorActionUrl(
                SiteMediaController.updateAltText({
                    site: props.site.id,
                    mediaAsset: mediaAssetId,
                }).url,
            ),
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
    if (!hasUnsavedEditorChanges.value && !editorWriteInProgress.value) return;
    event.preventDefault();
    event.returnValue = '';
}

function guardHistory(event: PopStateEvent) {
    if (editorWriteInProgress.value || !confirmEditorDiscard()) {
        event.stopImmediatePropagation();
        window.history.pushState(editorHistoryState, '', editorUrl);
    }
}

onMounted(() => {
    refreshHeroMotion();
    if (previewHeader.value)
        disposeSiteMenu = mountSiteMenu(previewHeader.value);
    editorHistoryState = window.history.state;
    editorUrl = window.location.href;
    stopBeforeListener = router.on('before', (event) => {
        if (ownVisit) return;
        if (editorWriteInProgress.value) {
            event.preventDefault();
            return;
        }
        if (!confirmEditorDiscard()) event.preventDefault();
    });
    stopNavigateListener = router.on('navigate', () => {
        previewHeader.value
            ?.querySelector<HTMLDialogElement>('[data-menu-dialog]')
            ?.close();
        editorHistoryState = window.history.state;
        editorUrl = window.location.href;
    });
    window.addEventListener('beforeunload', guardBeforeUnload);
    window.addEventListener('popstate', guardHistory, true);
});

onUnmounted(() => {
    disposeHeroMotion?.();
    disposeSiteMenu?.();
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

watch(
    () => [
        saveForm.errors,
        imageUploadForm.errors,
        altTextForm.errors,
        imageUploadError.value,
        altTextError.value,
    ],
    () => {
        nextTick(revealEditorErrors);
    },
    { deep: true, flush: 'post' },
);

watch(
    [
        () => props.blocks,
        selectedBlockId,
        () => draftBlockStyle.value.motion,
        clearImagePending,
        uploadPreviewUrl,
        failedHeroImages,
    ],
    () => refreshHeroMotion(),
    { deep: true, flush: 'post' },
);

function blockHasHeading(block: SiteBlock): boolean {
    return !['plain_text', 'image', 'video'].includes(block.type);
}

function blockHasTextButton(block: SiteBlock): boolean {
    return ['about', 'heading_text', 'plain_text', 'text_image'].includes(
        block.type,
    );
}

function styleForBlock(block: SiteBlock): BlockStyle {
    const saved = block.content.style ?? {};
    return {
        ...(block.type === 'hero'
            ? {
                  height: saved.height ?? 'current',
                  overlay: saved.overlay ?? 'medium',
                  motion: saved.motion ?? 'normal',
              }
            : {}),
        ...(block.type === 'text_image'
            ? {
                  layout:
                      saved.layout === 'image_left'
                          ? 'image_left'
                          : 'image_right',
              }
            : {}),
        ...(block.type === 'service_times'
            ? { layout: saved.layout === 'grid' ? 'grid' : 'list' }
            : {}),
        ...(['image', 'text_image'].includes(block.type)
            ? {
                  image_ratio:
                      saved.image_ratio === 'landscape' ||
                      saved.image_ratio === 'square' ||
                      saved.image_ratio === 'portrait'
                          ? saved.image_ratio
                          : 'original',
                  crop_position:
                      saved.crop_position === 'top' ||
                      saved.crop_position === 'bottom'
                          ? saved.crop_position
                          : 'center',
                  corner_style:
                      saved.corner_style === 'square' ||
                      saved.corner_style === 'rounded'
                          ? saved.corner_style
                          : 'current',
              }
            : {}),
        spacing:
            saved.spacing === 'compact' || saved.spacing === 'spacious'
                ? saved.spacing
                : 'current',
        content_width:
            saved.content_width === 'narrow' || saved.content_width === 'full'
                ? saved.content_width
                : 'current',
        ...(blockHasHeading(block)
            ? {
                  heading_size:
                      saved.heading_size === 'small' ||
                      saved.heading_size === 'large'
                          ? saved.heading_size
                          : 'current',
              }
            : {}),
        alignment:
            saved.alignment === 'center'
                ? 'center'
                : saved.alignment === 'left'
                  ? 'left'
                  : block.type === 'video'
                    ? 'center'
                    : 'left',
        background:
            saved.background === 'theme' ||
            saved.background === 'soft' ||
            saved.background === 'accent' ||
            saved.background === 'contrast'
                ? saved.background
                : block.type === 'about'
                  ? 'soft'
                  : 'theme',
    };
}

function contentFor(block: SiteBlock): BlockContent {
    if (block.id !== selectedBlockId.value) return block.content;
    let content: BlockContent;
    if (block.type === 'hero') {
        content = {
            ...draftHero.value,
            media_asset_id: clearImagePending.value
                ? null
                : (block.content.media_asset_id ?? null),
            heading: draftHeading.value,
            body: draftBody.value,
            button_label: draftButtonLabel.value,
            link_type: draftLinkType.value,
            target_block_id: draftTargetBlockId.value,
            external_url: draftExternalUrl.value,
        };
    } else if (block.type === 'service_times') {
        content = { heading: draftHeading.value, entries: draftEntries.value };
    } else if (block.type === 'contact') {
        content = {
            heading: draftHeading.value,
            email: draftEmail.value,
            phone: draftPhone.value,
            address: draftAddress.value,
            map: { enabled: draftMapEnabled.value, url: draftMapUrl.value },
        };
    } else if (block.type === 'image') {
        content = {
            media_asset_id: clearImagePending.value
                ? null
                : (block.content.media_asset_id ?? null),
            caption: draftCaption.value,
        };
    } else if (block.type === 'text_image') {
        content = {
            heading: draftHeading.value,
            body: draftBody.value,
            media_asset_id: clearImagePending.value
                ? null
                : (block.content.media_asset_id ?? null),
            caption: draftCaption.value,
        };
    } else if (block.type === 'video') {
        content = { url: draftVideoUrl.value };
    } else if (block.type === 'plain_text') {
        content = { body: draftBody.value };
    } else {
        content = { heading: draftHeading.value, body: draftBody.value };
    }
    if (blockHasTextButton(block)) {
        content = {
            ...content,
            button_label: draftButtonLabel.value,
            link_type: draftLinkType.value,
            target_block_id: draftTargetBlockId.value,
            target_page_id: draftHero.value.target_page_id,
            external_url: draftExternalUrl.value,
        };
    }
    return { ...content, style: { ...draftBlockStyle.value } };
}

function blockIsCentered(block: SiteBlock): boolean {
    const alignment = contentFor(block).style?.alignment;
    return (
        alignment === 'center' ||
        (alignment !== 'left' && block.type === 'video')
    );
}

function blockBackground(block: SiteBlock): string {
    const background = contentFor(block).style?.background;
    return background &&
        ['theme', 'soft', 'accent', 'contrast'].includes(background)
        ? background
        : block.type === 'about'
          ? 'soft'
          : 'theme';
}

function imageRatio(block: SiteBlock): string {
    const ratio = contentFor(block).style?.image_ratio;
    return ratio === 'landscape' || ratio === 'square' || ratio === 'portrait'
        ? ratio
        : 'original';
}

function imageCropPosition(block: SiteBlock): string {
    const position = contentFor(block).style?.crop_position;
    return position === 'top' || position === 'bottom' ? position : 'center';
}

function imageCornerStyle(block: SiteBlock): string {
    const corners = contentFor(block).style?.corner_style;
    return corners === 'square' || corners === 'rounded' ? corners : 'current';
}

function previewHeading(block: SiteBlock, fallback: string): string {
    return contentFor(block).heading?.trim() || fallback;
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

function contactAddress(block: SiteBlock): string {
    const address = contentFor(block).address;
    return typeof address === 'string' ? address.trim() : '';
}

function directionsHref(block: SiteBlock): string | null {
    const address = contactAddress(block);
    return address
        ? `https://www.google.com/maps/dir/?${new URLSearchParams({ api: '1', destination: address })}`
        : null;
}

function contactMapLinks(block: SiteBlock) {
    const map = contentFor(block).map;
    return map?.enabled === true && typeof map.url === 'string'
        ? openStreetMapLinks(map.url)
        : null;
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

function heroLinkType(block: SiteBlock, secondary = false) {
    return secondary
        ? contentFor(block).secondary_button?.link_type
        : contentFor(block).link_type;
}

function heroHref(block: SiteBlock, secondary = false): string | null {
    const content = secondary
        ? contentFor(block).secondary_button
        : contentFor(block);
    if (!content) return null;
    if (content.link_type === 'page' && content.button_label?.trim()) {
        return props.navigation_pages.some(
            (page) => page.id === content.target_page_id,
        )
            ? `/sites/${props.site.id}/pages/${content.target_page_id}`
            : null;
    }
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
    if (draftLinkType.value !== 'page') draftHero.value.target_page_id = null;
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
            ? contentFor(block)
            : block.type === 'contact'
              ? {
                    heading: draftHeading.value,
                    email: draftEmail.value,
                    phone: draftPhone.value,
                    address: draftAddress.value,
                    map: {
                        enabled: draftMapEnabled.value,
                        url: draftMapUrl.value,
                    },
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
                    ? {
                          media_asset_id: mediaAssetId,
                          caption: draftCaption.value,
                      }
                    : block.type === 'text_image'
                      ? {
                            heading: draftHeading.value,
                            body: draftBody.value,
                            media_asset_id: mediaAssetId,
                            caption: draftCaption.value,
                        }
                      : block.type === 'plain_text'
                        ? { body: draftBody.value }
                        : {
                              heading: draftHeading.value,
                              body: draftBody.value,
                          };
    if (blockHasTextButton(block)) {
        saveForm.content = {
            ...saveForm.content,
            button_label: draftButtonLabel.value,
            link_type: draftLinkType.value,
            target_block_id: draftTargetBlockId.value,
            target_page_id: draftHero.value.target_page_id,
            external_url: draftExternalUrl.value,
        };
    }
    saveForm.content.style = { ...draftBlockStyle.value };

    runOwnVisit(() =>
        saveForm.patch(blockBaseUrl.value + '/' + block.id, {
            preserveScroll: true,
            onSuccess: () => {
                savedHero.value = heroExtras(draftHero.value);
                savedHeading.value = draftHeading.value;
                savedBody.value = draftBody.value;
                savedCaption.value = draftCaption.value;
                savedButtonLabel.value = draftButtonLabel.value;
                savedLinkType.value = draftLinkType.value;
                savedTargetBlockId.value = draftTargetBlockId.value;
                savedExternalUrl.value = draftExternalUrl.value;
                savedEntries.value = draftEntries.value.map((entry) => ({
                    ...entry,
                }));
                savedEmail.value = draftEmail.value;
                savedPhone.value = draftPhone.value;
                savedAddress.value = draftAddress.value;
                draftMapUrl.value = draftMapUrl.value.trim();
                savedMapEnabled.value = draftMapEnabled.value;
                savedMapUrl.value = draftMapUrl.value;
                savedVideoUrl.value = draftVideoUrl.value;
                savedBlockStyle.value = { ...draftBlockStyle.value };
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
                    !errors['content.caption'] &&
                    !errors['content.button_label'] &&
                    !errors['content.link_type'] &&
                    !errors['content.target_block_id'] &&
                    !errors['content.target_page_id'] &&
                    !errors['content.external_url'] &&
                    !errors['content.email'] &&
                    !errors['content.phone'] &&
                    !errors['content.address'] &&
                    !errors['content.map'] &&
                    !errors['content.map.enabled'] &&
                    !errors['content.map.url'] &&
                    !errors['content.url'] &&
                    !errors['content.style'] &&
                    !errors['content.style.layout'] &&
                    !errors['content.style.image_ratio'] &&
                    !errors['content.style.crop_position'] &&
                    !errors['content.style.corner_style'] &&
                    !errors['content.style.alignment'] &&
                    !errors['content.style.background'] &&
                    !errors['content.style.spacing'] &&
                    !errors['content.style.content_width'] &&
                    !errors['content.style.heading_size'] &&
                    !Object.keys(errors).some((key) =>
                        key.startsWith('content.entries'),
                    ) &&
                    !errors.content
                ) {
                    saveError.value =
                        'We could not save this block. Please try again.';
                }
                nextTick(() => {
                    if (revealEditorErrors()) return;
                    if (
                        Object.keys(errors).some((key) =>
                            /content\.(welcome_label|target_page_id|secondary_button|style\.(height|overlay|motion))/.test(
                                key,
                            ),
                        )
                    ) {
                        revealEditorErrors();
                        return;
                    }
                    if (errors['content.media_asset_id']) {
                        imageInput.value?.focus();
                        return;
                    }
                    if (errors['content.heading']) headingInput.value?.focus();
                    else if (errors['content.body']) bodyInput.value?.focus();
                    else if (errors['content.caption'])
                        captionInput.value?.focus();
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
                    else if (errors['content.address'])
                        addressInput.value?.focus();
                    else if (errors['content.map.enabled'])
                        mapEnabledInput.value?.focus();
                    else if (errors['content.map'] || errors['content.map.url'])
                        mapUrlInput.value?.focus();
                    else if (errors['content.url'])
                        videoUrlInput.value?.focus();
                    else if (errors['content.style.layout'])
                        blockLayoutInput.value?.focus();
                    else if (errors['content.style.alignment'])
                        blockAlignmentInput.value?.focus();
                    else if (errors['content.style.background'])
                        blockBackgroundInput.value?.focus();
                    else if (errors['content.style'])
                        (selectedBlock.value?.type === 'text_image' ||
                        selectedBlock.value?.type === 'service_times'
                            ? blockLayoutInput.value
                            : blockAlignmentInput.value
                        )?.focus();
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
        addForm.post(blockBaseUrl.value, {
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
        orderForm.patch(blockBaseUrl.value + '/order', {
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
        deleteForm.delete(blockBaseUrl.value + '/' + block.id, {
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

const pageSettingsPending = ref(false);
const pageSettingsDirty = ref(false);
const publishForm = useForm({});
const publishError = ref('');
const publishStatus = ref('');
const uploadInProgress = computed(() => imageUploadForm.processing);
const editorWriteInProgress = computed(
    () =>
        uploadInProgress.value ||
        saveForm.processing ||
        addForm.processing ||
        orderForm.processing ||
        deleteForm.processing ||
        altTextForm.processing ||
        publishForm.processing ||
        pageSettingsPending.value,
);
const hasUnsavedEditorChanges = computed(
    () => isDirty.value || pageSettingsDirty.value,
);
function logoPreviewUrl(): string | null {
    return props.site.logo?.url ?? null;
}
function logoPreviewAltText(): string {
    return props.site.logo?.alt_text || props.site.name;
}
function publishSite() {
    if (editorWriteInProgress.value || hasUnsavedEditorChanges.value) return;
    publishError.value = '';
    publishStatus.value = '';
    if (!props.site.slug) {
        publishError.value =
            'Choose and save a shareable address before publishing.';
        return;
    }

    runOwnVisit(() =>
        publishForm.post(
            editorActionUrl('/sites/' + props.site.id + '/publish'),
            {
                preserveScroll: true,
                onSuccess: () => {
                    publishStatus.value = 'Your saved site is now published.';
                },
                onError: (errors) => {
                    publishError.value =
                        errors.slug ??
                        errors.publish ??
                        'We could not publish the site. Please try again.';
                },
                onHttpException: () => {
                    publishError.value =
                        'We could not publish the site. Please try again.';
                    return false;
                },
                onNetworkError: () => {
                    publishError.value =
                        'We could not publish the site. Please try again.';
                    return false;
                },
            },
        ),
    );
}

defineOptions({
    layout: {
        fullWidth: true,
        breadcrumbs: [{ title: 'My sites', href: dashboard() }],
    },
});
</script>

<template>
    <Head :title="props.site.name" />
    <main
        class="mx-auto w-full max-w-[1600px] px-5 py-8 sm:px-8 lg:px-10 lg:py-10"
    >
        <header class="mb-8">
            <Link
                :href="`/sites/${props.site.id}`"
                class="text-sm font-semibold text-[var(--workspace-green)] hover:underline"
                >← Site settings</Link
            >
            <div
                class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start"
            >
                <div class="min-w-0">
                    <p
                        class="text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
                    >
                        {{ props.site.name }} / Page editor
                    </p>
                    <h1
                        class="mt-2 font-serif text-4xl tracking-tight break-words"
                    >
                        {{ props.selected_page.name }}
                    </h1>
                    <p class="mt-2 text-sm text-[var(--workspace-muted)]">
                        Edit this page's blocks and preview. Shared styles,
                        header, and footer are managed in site settings.
                    </p>
                </div>
                <section
                    aria-label="Publishing controls"
                    class="min-w-0 lg:max-w-md lg:pt-6 lg:text-right"
                >
                    <div>
                        <div class="flex flex-wrap gap-3 lg:justify-end">
                            <button
                                type="button"
                                :disabled="
                                    editorWriteInProgress ||
                                    hasUnsavedEditorChanges
                                "
                                class="min-h-11 rounded-lg bg-[var(--workspace-green)] px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 dark:text-[var(--workspace-surface)]"
                                @click="publishSite"
                            >
                                {{
                                    publishForm.processing
                                        ? 'Publishing…'
                                        : 'Publish site'
                                }}
                            </button>
                            <a
                                v-if="
                                    props.site.published_at &&
                                    props.selected_page.published_url
                                "
                                :href="props.selected_page.published_url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex min-h-11 items-center rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold text-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)] focus:outline-none"
                            >
                                View page
                                <span aria-hidden="true" class="ms-2">→</span>
                                <span class="sr-only">
                                    (opens in a new tab)</span
                                >
                            </a>
                        </div>
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
                </section>
            </div>
        </header>

        <PageSettings
            :key="props.selected_page.id"
            :site-id="props.site.id"
            :site-slug="props.site.slug"
            :page="props.selected_page"
            :busy="editorWriteInProgress"
            :run-visit="runOwnVisit"
            @busy="pageSettingsPending = $event"
            @dirty="pageSettingsDirty = $event"
        />
        <section
            :inert="pageSettingsPending"
            aria-labelledby="add-block-heading"
            class="mb-6 rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
        >
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-3"
            >
                <h2 id="add-block-heading" class="font-serif text-xl">
                    Add a block
                </h2>
                <p class="text-xs font-medium text-[var(--workspace-muted)]">
                    Scroll for more
                    <span aria-hidden="true">→</span>
                </p>
            </div>
            <div
                role="group"
                aria-label="Choose a block type. Scroll horizontally to see more options."
                class="mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain scroll-smooth pb-2"
            >
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
                    class="flex min-h-16 shrink-0 basis-[82%] snap-start flex-col justify-center rounded-lg border border-[var(--workspace-line)] px-3 py-2 text-left hover:bg-[var(--workspace-soft)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:cursor-wait disabled:opacity-60 sm:basis-[calc(28.57%_-_0.54rem)] lg:basis-56"
                    @click="addBlock(item.type)"
                >
                    <span class="text-sm font-semibold">{{ item.label }}</span>
                    <span class="text-xs text-[var(--workspace-muted)]">{{
                        item.description
                    }}</span>
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
        </section>

        <div
            :inert="pageSettingsPending"
            class="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)_17rem] lg:items-start"
        >
            <section
                aria-labelledby="block-list-heading"
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)] lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto lg:overscroll-contain"
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
                    No blocks yet. Add one above to begin.
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
                    ref="previewRoot"
                    class="site-preview"
                    :data-theme="props.site.theme_key"
                    :style="props.site.appearance_colors"
                    :data-font-pairing="props.site.appearance.font_pairing"
                    :data-button-shape="props.site.appearance.button_shape"
                >
                    <header
                        ref="previewHeader"
                        class="border-b border-[var(--site-preview-border)] px-6 py-7 sm:px-10"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-8 gap-y-4"
                        >
                            <div
                                class="flex max-w-[calc(100%-5rem)] min-w-0 flex-wrap items-center gap-3 sm:max-w-full"
                            >
                                <img
                                    v-if="logoPreviewUrl()"
                                    :src="logoPreviewUrl() ?? undefined"
                                    :alt="logoPreviewAltText()"
                                    class="h-[75px] w-auto max-w-full shrink-0 object-contain object-left"
                                />
                                <h3
                                    class="min-w-0 font-serif text-2xl font-semibold tracking-tight break-words"
                                >
                                    {{ props.site.name }}
                                </h3>
                            </div>
                            <nav
                                aria-label="Site pages"
                                class="ml-auto hidden max-w-full min-w-0 sm:block"
                            >
                                <ul
                                    class="flex flex-wrap justify-end gap-x-6 gap-y-1"
                                >
                                    <li
                                        v-for="page in props.navigation_pages"
                                        :key="page.id"
                                        class="max-w-full min-w-0"
                                    >
                                        <Link
                                            :href="`/sites/${props.site.id}/pages/${page.id}`"
                                            :aria-current="
                                                page.id ===
                                                props.selected_page.id
                                                    ? 'page'
                                                    : undefined
                                            "
                                            class="inline-flex min-h-10 max-w-full items-center py-2 text-sm font-semibold text-[var(--site-preview-accent)] underline-offset-4 hover:opacity-75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                            :class="{
                                                underline:
                                                    page.id ===
                                                    props.selected_page.id,
                                            }"
                                            ><span
                                                class="min-w-0 break-words"
                                                >{{ page.name }}</span
                                            ></Link
                                        >
                                    </li>
                                </ul>
                            </nav>
                            <button
                                type="button"
                                data-menu-open
                                aria-label="Open menu"
                                aria-expanded="false"
                                aria-controls="site-mobile-menu"
                                class="site-menu-toggle ml-auto shrink-0 sm:hidden"
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="size-6"
                                >
                                    <path d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <dialog
                                id="site-mobile-menu"
                                data-menu-dialog
                                aria-label="Site menu"
                                class="site-menu-drawer"
                            >
                                <div class="mb-6 flex justify-end">
                                    <button
                                        type="button"
                                        data-menu-close
                                        aria-label="Close menu"
                                        autofocus
                                        class="site-menu-toggle"
                                    >
                                        <svg
                                            aria-hidden="true"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            class="size-6"
                                        >
                                            <path d="m6 6 12 12M6 18 18 6" />
                                        </svg>
                                    </button>
                                </div>
                                <nav aria-label="Mobile site pages">
                                    <ul class="flex flex-col gap-2">
                                        <li
                                            v-for="page in props.navigation_pages"
                                            :key="page.id"
                                            class="max-w-full min-w-0"
                                        >
                                            <Link
                                                :href="`/sites/${props.site.id}/pages/${page.id}`"
                                                :aria-current="
                                                    page.id ===
                                                    props.selected_page.id
                                                        ? 'page'
                                                        : undefined
                                                "
                                                class="inline-flex min-h-10 max-w-full items-center py-2 text-sm font-semibold text-[var(--site-preview-accent)] underline-offset-4 hover:opacity-75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                :class="{
                                                    underline:
                                                        page.id ===
                                                        props.selected_page.id,
                                                }"
                                                ><span
                                                    class="min-w-0 break-words"
                                                    >{{ page.name }}</span
                                                ></Link
                                            >
                                        </li>
                                    </ul>
                                </nav>
                            </dialog>
                        </div>
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
                            class="site-block border-b border-[var(--site-preview-border)] px-6 py-12 last:border-b-0 sm:px-10"
                            :data-block-type="block.type"
                            :data-spacing="contentFor(block).style?.spacing"
                            :data-content-width="
                                contentFor(block).style?.content_width
                            "
                            :data-heading-size="
                                blockHasHeading(block)
                                    ? contentFor(block).style?.heading_size
                                    : undefined
                            "
                            :data-background="blockBackground(block)"
                            :data-height="
                                block.type === 'hero'
                                    ? (contentFor(block).style?.height ??
                                      'current')
                                    : undefined
                            "
                            :data-overlay="
                                block.type === 'hero'
                                    ? (contentFor(block).style?.overlay ??
                                      'medium')
                                    : undefined
                            "
                            :data-motion="
                                block.type === 'hero'
                                    ? (contentFor(block).style?.motion ??
                                      'normal')
                                    : undefined
                            "
                            :data-has-image="
                                block.type === 'hero' && heroHasImage(block)
                                    ? 'true'
                                    : undefined
                            "
                            :class="[
                                blockIsCentered(block) ? 'text-center' : '',
                                block.type === 'hero' ? 'site-hero' : '',
                            ]"
                        >
                            <div class="site-block-content">
                                <template v-if="block.type === 'hero'">
                                    <img
                                        v-if="heroHasImage(block)"
                                        :src="blockMediaUrl(block) ?? undefined"
                                        alt=""
                                        class="site-hero-image"
                                        @error="
                                            failedHeroImages[block.id] =
                                                blockMediaUrl(block) ?? ''
                                        "
                                    />
                                    <div class="site-hero-content">
                                        <p
                                            v-if="
                                                (contentFor(block)
                                                    .welcome_label ??
                                                    'Welcome') !== ''
                                            "
                                            class="text-xs font-bold tracking-[0.14em] text-[var(--site-preview-accent)] uppercase"
                                        >
                                            {{
                                                contentFor(block)
                                                    .welcome_label ?? 'Welcome'
                                            }}
                                        </p>
                                        <h3
                                            class="mt-4 max-w-xl font-serif text-4xl leading-tight sm:text-5xl"
                                            :class="
                                                blockIsCentered(block)
                                                    ? 'mx-auto'
                                                    : ''
                                            "
                                        >
                                            {{
                                                previewHeading(
                                                    block,
                                                    'Welcome to our church',
                                                )
                                            }}
                                        </h3>
                                        <p
                                            v-if="contentFor(block).body"
                                            class="mt-5 max-w-prose whitespace-pre-line text-[var(--site-preview-muted)]"
                                            :class="
                                                blockIsCentered(block)
                                                    ? 'mx-auto'
                                                    : ''
                                            "
                                        >
                                            {{ contentFor(block).body }}
                                        </p>
                                        <template
                                            v-for="secondary in [false, true]"
                                            :key="String(secondary)"
                                        >
                                            <component
                                                :is="
                                                    heroLinkType(
                                                        block,
                                                        secondary,
                                                    ) === 'page'
                                                        ? Link
                                                        : 'a'
                                                "
                                                v-if="
                                                    heroHref(block, secondary)
                                                "
                                                :href="
                                                    heroHref(
                                                        block,
                                                        secondary,
                                                    ) ?? undefined
                                                "
                                                class="site-hero-button mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                :class="secondary ? 'ms-3' : ''"
                                                :target="
                                                    heroLinkType(
                                                        block,
                                                        secondary,
                                                    ) === 'external'
                                                        ? '_blank'
                                                        : undefined
                                                "
                                                :rel="
                                                    heroLinkType(
                                                        block,
                                                        secondary,
                                                    ) === 'external'
                                                        ? 'noopener noreferrer'
                                                        : undefined
                                                "
                                                >{{
                                                    secondary
                                                        ? contentFor(block)
                                                              .secondary_button
                                                              ?.button_label
                                                        : contentFor(block)
                                                              .button_label
                                                }}</component
                                            >
                                        </template>
                                    </div>
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
                                <template
                                    v-else-if="block.type === 'heading_text'"
                                >
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
                                        :class="
                                            blockIsCentered(block)
                                                ? 'mx-auto max-w-prose'
                                                : ''
                                        "
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
                                        {{
                                            previewHeading(
                                                block,
                                                'Service times',
                                            )
                                        }}
                                    </h3>
                                    <ul
                                        v-if="contentFor(block).entries?.length"
                                        :data-service-layout="
                                            contentFor(block).style?.layout ===
                                            'grid'
                                                ? 'grid'
                                                : 'list'
                                        "
                                        class="mt-6"
                                        :class="[
                                            contentFor(block).style?.layout ===
                                            'grid'
                                                ? 'grid grid-cols-1 gap-3 sm:grid-cols-2'
                                                : 'divide-y divide-[var(--site-preview-border)]',
                                            blockIsCentered(block)
                                                ? contentFor(block).style
                                                      ?.layout === 'grid'
                                                    ? 'mx-auto max-w-4xl'
                                                    : 'mx-auto max-w-2xl'
                                                : '',
                                        ]"
                                    >
                                        <li
                                            v-for="(entry, index) in contentFor(
                                                block,
                                            ).entries"
                                            :key="index"
                                            class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3"
                                            :class="
                                                contentFor(block).style
                                                    ?.layout === 'grid'
                                                    ? 'rounded-lg border border-[var(--site-preview-border)] px-3'
                                                    : ''
                                            "
                                        >
                                            <span class="font-semibold">{{
                                                entry.day
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                entry.day.slice(1)
                                            }}</span>
                                            <span
                                                class="text-[var(--site-preview-muted)]"
                                                >{{
                                                    formatServiceTime(
                                                        entry.time,
                                                    )
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
                                        Add your weekly gatherings in the
                                        editor.
                                    </p>
                                </template>
                                <template v-else-if="block.type === 'contact'">
                                    <div
                                        :class="
                                            contactMapLinks(block)
                                                ? 'grid items-start gap-6 md:grid-cols-2'
                                                : ''
                                        "
                                    >
                                        <div class="min-w-0">
                                            <h3 class="font-serif text-2xl">
                                                {{
                                                    previewHeading(
                                                        block,
                                                        'Contact us',
                                                    )
                                                }}
                                            </h3>
                                            <div
                                                v-if="
                                                    contentFor(block).email ||
                                                    contentFor(block).phone ||
                                                    contactAddress(block)
                                                "
                                                class="mt-5 flex flex-col gap-3"
                                                :class="
                                                    blockIsCentered(block)
                                                        ? 'items-center'
                                                        : 'items-start'
                                                "
                                            >
                                                <a
                                                    v-if="emailHref(block)"
                                                    :href="
                                                        emailHref(block) ??
                                                        undefined
                                                    "
                                                    class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                    >{{
                                                        contentFor(block).email
                                                    }}</a
                                                >
                                                <span
                                                    v-else-if="
                                                        contentFor(block).email
                                                    "
                                                    class="text-[var(--site-preview-muted)]"
                                                    >{{
                                                        contentFor(block).email
                                                    }}</span
                                                >
                                                <a
                                                    v-if="phoneHref(block)"
                                                    :href="
                                                        phoneHref(block) ??
                                                        undefined
                                                    "
                                                    class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                    >{{
                                                        contentFor(block).phone
                                                    }}</a
                                                >
                                                <span
                                                    v-else-if="
                                                        contentFor(block).phone
                                                    "
                                                    class="text-[var(--site-preview-muted)]"
                                                    >{{
                                                        contentFor(block).phone
                                                    }}</span
                                                >
                                                <address
                                                    v-if="contactAddress(block)"
                                                    class="break-words whitespace-pre-line text-[var(--site-preview-muted)] not-italic"
                                                >
                                                    {{ contactAddress(block) }}
                                                </address>
                                                <a
                                                    v-if="directionsHref(block)"
                                                    :href="
                                                        directionsHref(block) ??
                                                        undefined
                                                    "
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                    >Get directions</a
                                                >
                                            </div>
                                            <p
                                                v-else-if="
                                                    !contactMapLinks(block)
                                                "
                                                class="mt-4 text-[var(--site-preview-muted)]"
                                            >
                                                Add an address, email, or phone
                                                number in the editor.
                                            </p>
                                        </div>
                                        <div
                                            v-if="contactMapLinks(block)"
                                            class="w-full min-w-0 space-y-2"
                                        >
                                            <iframe
                                                :src="
                                                    contactMapLinks(block)
                                                        ?.embed_url
                                                "
                                                :title="`Location map: ${previewHeading(block, 'Contact us')}`"
                                                loading="lazy"
                                                class="aspect-video min-h-64 w-full rounded-xl border border-[var(--site-preview-border)]"
                                            ></iframe>
                                            <p
                                                class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-[var(--site-preview-muted)]"
                                            >
                                                <a
                                                    :href="
                                                        contactMapLinks(block)
                                                            ?.location_url
                                                    "
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                    >View on OpenStreetMap</a
                                                >
                                                <span>
                                                    Map data ©
                                                    <a
                                                        href="https://www.openstreetmap.org/copyright"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                        >OpenStreetMap
                                                        contributors</a
                                                    >
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="block.type === 'image'">
                                    <figure
                                        :class="
                                            blockIsCentered(block)
                                                ? 'mx-auto max-w-3xl'
                                                : ''
                                        "
                                    >
                                        <div
                                            class="site-image-frame overflow-hidden rounded-xl border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)]"
                                            :data-image-ratio="
                                                imageRatio(block)
                                            "
                                            :data-crop-position="
                                                imageCropPosition(block)
                                            "
                                            :data-corner-style="
                                                imageCornerStyle(block)
                                            "
                                        >
                                            <img
                                                v-if="blockMediaUrl(block)"
                                                :src="
                                                    blockMediaUrl(block) ??
                                                    undefined
                                                "
                                                :alt="
                                                    blockPreviewAltText(block)
                                                "
                                                class="max-h-[32rem] w-full object-contain"
                                            />
                                            <div
                                                v-else
                                                data-image-placeholder
                                                role="group"
                                                aria-label="Image placeholder"
                                                class="flex min-h-64 flex-col items-center justify-center p-8 text-center"
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
                                        <figcaption
                                            v-if="
                                                blockMediaUrl(block) &&
                                                contentFor(block).caption
                                            "
                                            class="mt-3 text-sm whitespace-pre-line text-[var(--site-preview-muted)]"
                                        >
                                            {{ contentFor(block).caption }}
                                        </figcaption>
                                    </figure>
                                </template>
                                <template
                                    v-else-if="block.type === 'text_image'"
                                >
                                    <div
                                        class="grid gap-8 md:grid-cols-2 md:items-center"
                                    >
                                        <div
                                            :class="
                                                contentFor(block).style
                                                    ?.layout === 'image_left'
                                                    ? 'md:order-2'
                                                    : ''
                                            "
                                        >
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
                                            <a
                                                v-if="
                                                    blockHasTextButton(block) &&
                                                    heroHref(block)
                                                "
                                                :href="
                                                    heroHref(block) ?? undefined
                                                "
                                                :target="
                                                    heroLinkType(block) ===
                                                    'external'
                                                        ? '_blank'
                                                        : undefined
                                                "
                                                :rel="
                                                    heroLinkType(block) ===
                                                    'external'
                                                        ? 'noopener noreferrer'
                                                        : undefined
                                                "
                                                class="site-hero-button mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                                >{{
                                                    contentFor(block)
                                                        .button_label
                                                }}</a
                                            >
                                        </div>
                                        <figure
                                            :class="
                                                contentFor(block).style
                                                    ?.layout === 'image_left'
                                                    ? 'md:order-1'
                                                    : 'md:order-2'
                                            "
                                        >
                                            <div
                                                class="site-image-frame overflow-hidden rounded-xl border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)]"
                                                :data-image-ratio="
                                                    imageRatio(block)
                                                "
                                                :data-crop-position="
                                                    imageCropPosition(block)
                                                "
                                                :data-corner-style="
                                                    imageCornerStyle(block)
                                                "
                                            >
                                                <img
                                                    v-if="blockMediaUrl(block)"
                                                    :src="
                                                        blockMediaUrl(block) ??
                                                        undefined
                                                    "
                                                    :alt="
                                                        blockPreviewAltText(
                                                            block,
                                                        )
                                                    "
                                                    class="max-h-[32rem] min-h-56 w-full object-contain"
                                                />
                                                <div
                                                    v-else
                                                    data-image-placeholder
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
                                            <figcaption
                                                v-if="
                                                    blockMediaUrl(block) &&
                                                    contentFor(block).caption
                                                "
                                                class="mt-3 text-sm whitespace-pre-line text-[var(--site-preview-muted)]"
                                            >
                                                {{ contentFor(block).caption }}
                                            </figcaption>
                                        </figure>
                                    </div>
                                </template>
                                <template v-else-if="block.type === 'video'">
                                    <div
                                        class="max-w-3xl overflow-hidden rounded-xl bg-[var(--site-preview-soft)]"
                                        :class="
                                            blockIsCentered(block)
                                                ? 'mx-auto'
                                                : 'mr-auto'
                                        "
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
                                                    >Enter a supported YouTube
                                                    or Vimeo link in the
                                                    editor.</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <p
                                    v-else-if="block.type === 'plain_text'"
                                    class="max-w-prose text-lg leading-relaxed whitespace-pre-line"
                                    :class="
                                        blockIsCentered(block) ? 'mx-auto' : ''
                                    "
                                >
                                    {{
                                        contentFor(block).body ||
                                        'Your message will appear here.'
                                    }}
                                </p>
                                <p
                                    v-else
                                    class="text-[var(--site-preview-muted)]"
                                >
                                    {{ labelFor(block.type) }} block
                                </p>
                                <a
                                    v-if="
                                        block.type !== 'text_image' &&
                                        blockHasTextButton(block) &&
                                        heroHref(block)
                                    "
                                    :href="heroHref(block) ?? undefined"
                                    :target="
                                        heroLinkType(block) === 'external'
                                            ? '_blank'
                                            : undefined
                                    "
                                    :rel="
                                        heroLinkType(block) === 'external'
                                            ? 'noopener noreferrer'
                                            : undefined
                                    "
                                    class="site-hero-button mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]"
                                    >{{ contentFor(block).button_label }}</a
                                >
                            </div>
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
                ref="blockEditor"
                aria-labelledby="block-details-heading"
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)] lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto lg:overscroll-contain"
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
                        @invalid.capture="revealInvalidField"
                    >
                        <details
                            :key="'content-' + selectedBlock.id"
                            class="block-editor-section"
                            open
                        >
                            <summary>Content</summary>
                            <div class="space-y-5 pt-4">
                                <HeroOptions
                                    v-if="selectedBlock.type === 'hero'"
                                    section="content"
                                    v-model="draftHero"
                                    :primary-type="draftLinkType"
                                    :pages="props.navigation_pages"
                                    :sections="
                                        props.blocks
                                            .filter(
                                                (item) =>
                                                    item.id !==
                                                    selectedBlock?.id,
                                            )
                                            .map((item) => ({
                                                id: item.id,
                                                name: `${item.position + 1}. ${labelFor(item.type)}`,
                                            }))
                                    "
                                    :disabled="editorWriteInProgress"
                                    :errors="saveForm.errors"
                                    @change="clearContentError"
                                />
                                <section
                                    v-if="
                                        ['image', 'text_image'].includes(
                                            selectedBlock.type,
                                        )
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
                                                selectedBlock.content
                                                    .media_asset_id
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
                                                    imageUploadForm.errors
                                                        .image ||
                                                    saveForm.errors[
                                                        'content.media_asset_id'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                imageUploadError ||
                                                imageUploadForm.errors.image ||
                                                saveForm.errors[
                                                    'content.media_asset_id'
                                                ]
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
                                            JPEG or PNG, up to 5 MB. Uploading
                                            replaces the saved image.
                                        </p>
                                        <p
                                            v-if="
                                                imageUploadError ||
                                                imageUploadForm.errors.image ||
                                                saveForm.errors[
                                                    'content.media_asset_id'
                                                ]
                                            "
                                            id="block-image-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                imageUploadError ||
                                                imageUploadForm.errors.image ||
                                                saveForm.errors[
                                                    'content.media_asset_id'
                                                ]
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
                                        {{
                                            imageUploadForm.progress.percentage
                                        }}%
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
                                                    altTextForm.errors
                                                        .alt_text ||
                                                    imageUploadForm.errors
                                                        .alt_text,
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
                                            v-if="
                                                selectedBlock.content
                                                    .media_asset_id
                                            "
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
                                            v-if="
                                                selectedBlock.content
                                                    .media_asset_id
                                            "
                                            type="button"
                                            :disabled="
                                                uploadInProgress ||
                                                (isAltTextDirty &&
                                                    !clearImagePending) ||
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
                                        v-if="
                                            selectedBlock.content.media_asset_id
                                        "
                                        id="block-image-clear-help"
                                        class="text-xs text-[var(--workspace-muted)]"
                                    >
                                        <template v-if="clearImagePending">
                                            Save the block to clear this image,
                                            or undo the clear to keep it. You
                                            can also choose a replacement image
                                            now.
                                        </template>
                                        <template v-else>
                                            Save a changed description before
                                            clearing the image. Clear image
                                            takes effect when you save the
                                            block.
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
                                <div
                                    v-if="
                                        selectedBlock.type === 'image' ||
                                        selectedBlock.type === 'text_image'
                                    "
                                >
                                    <label
                                        for="block-caption"
                                        class="mb-2 block text-sm font-semibold"
                                        >Caption (optional)</label
                                    >
                                    <textarea
                                        id="block-caption"
                                        ref="captionInput"
                                        v-model="draftCaption"
                                        rows="3"
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
                                            Boolean(
                                                saveForm.errors[
                                                    'content.caption'
                                                ],
                                            )
                                        "
                                        :aria-describedby="
                                            saveForm.errors['content.caption']
                                                ? 'block-caption-help block-caption-error'
                                                : 'block-caption-help'
                                        "
                                        class="min-h-24 w-full resize-y rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                        @input="clearContentError"
                                    />
                                    <p
                                        id="block-caption-help"
                                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                                    >
                                        Displayed below the image. The image
                                        description above remains for screen
                                        readers. The caption stays saved if you
                                        replace or clear the image.
                                    </p>
                                    <p
                                        v-if="
                                            saveForm.errors['content.caption']
                                        "
                                        id="block-caption-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ saveForm.errors['content.caption'] }}
                                    </p>
                                </div>
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
                                            Boolean(
                                                saveForm.errors[
                                                    'content.heading'
                                                ],
                                            )
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
                                        v-if="
                                            saveForm.errors['content.heading']
                                        "
                                        id="block-heading-error"
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ saveForm.errors['content.heading'] }}
                                    </p>
                                </div>
                                <div
                                    v-if="
                                        selectedBlock.type !==
                                            'service_times' &&
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
                                            Boolean(
                                                saveForm.errors['content.body'],
                                            )
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
                                            Boolean(
                                                saveForm.errors['content.url'],
                                            )
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
                                        Paste an HTTPS link to one YouTube or
                                        Vimeo video.
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
                                <div
                                    v-if="
                                        selectedBlock.type === 'service_times'
                                    "
                                    class="border-t border-[var(--workspace-line)] pt-5"
                                >
                                    <h3 class="text-sm font-semibold">
                                        Weekly gatherings
                                    </h3>
                                    <p
                                        class="mt-1 text-xs text-[var(--workspace-muted)]"
                                    >
                                        Times are local to your church. Add them
                                        in the order you want visitors to see.
                                    </p>
                                    <p
                                        v-if="!draftEntries.length"
                                        class="mt-4 text-sm text-[var(--workspace-muted)]"
                                    >
                                        No times yet. Add a weekly gathering
                                        below.
                                    </p>
                                    <ol v-else class="mt-4 space-y-4">
                                        <li
                                            v-for="(
                                                entry, index
                                            ) in draftEntries"
                                            :key="index"
                                            class="space-y-3 rounded-lg border border-[var(--workspace-line)] p-3"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <span
                                                    class="text-sm font-semibold"
                                                    >Gathering
                                                    {{ index + 1 }}</span
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
                                                            moveServiceTime(
                                                                index,
                                                                -1,
                                                            )
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
                                                            moveServiceTime(
                                                                index,
                                                                1,
                                                            )
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
                                                            removeServiceTime(
                                                                index,
                                                            )
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
                                                            entryError(
                                                                index,
                                                                'day',
                                                            ),
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
                                                    v-if="
                                                        entryError(index, 'day')
                                                    "
                                                    :id="`service-day-error-${index}`"
                                                    role="alert"
                                                    class="mt-1 text-sm text-red-700 dark:text-red-300"
                                                >
                                                    {{
                                                        entryError(index, 'day')
                                                    }}
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
                                                            entryError(
                                                                index,
                                                                'time',
                                                            ),
                                                        )
                                                    "
                                                    :aria-describedby="
                                                        entryError(
                                                            index,
                                                            'time',
                                                        )
                                                            ? `service-time-error-${index}`
                                                            : undefined
                                                    "
                                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                                    @input="clearContentError"
                                                />
                                                <p
                                                    v-if="
                                                        entryError(
                                                            index,
                                                            'time',
                                                        )
                                                    "
                                                    :id="`service-time-error-${index}`"
                                                    role="alert"
                                                    class="mt-1 text-sm text-red-700 dark:text-red-300"
                                                >
                                                    {{
                                                        entryError(
                                                            index,
                                                            'time',
                                                        )
                                                    }}
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
                                                            entryError(
                                                                index,
                                                                'label',
                                                            ),
                                                        )
                                                    "
                                                    :aria-describedby="
                                                        entryError(
                                                            index,
                                                            'label',
                                                        )
                                                            ? `service-label-error-${index}`
                                                            : undefined
                                                    "
                                                    class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] disabled:opacity-60"
                                                    @input="clearContentError"
                                                />
                                                <p
                                                    v-if="
                                                        entryError(
                                                            index,
                                                            'label',
                                                        )
                                                    "
                                                    :id="`service-label-error-${index}`"
                                                    role="alert"
                                                    class="mt-1 text-sm text-red-700 dark:text-red-300"
                                                >
                                                    {{
                                                        entryError(
                                                            index,
                                                            'label',
                                                        )
                                                    }}
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
                                        v-if="
                                            saveForm.errors['content.entries']
                                        "
                                        role="alert"
                                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ saveForm.errors['content.entries'] }}
                                    </p>
                                </div>
                                <template
                                    v-if="selectedBlock.type === 'contact'"
                                >
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
                                                    saveForm.errors[
                                                        'content.email'
                                                    ],
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
                                            v-if="
                                                saveForm.errors['content.email']
                                            "
                                            id="contact-email-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors['content.email']
                                            }}
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
                                                    saveForm.errors[
                                                        'content.phone'
                                                    ],
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
                                            v-if="
                                                saveForm.errors['content.phone']
                                            "
                                            id="contact-phone-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors['content.phone']
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            for="contact-address"
                                            class="mb-2 block text-sm font-semibold"
                                            >Street address</label
                                        >
                                        <textarea
                                            id="contact-address"
                                            ref="addressInput"
                                            v-model="draftAddress"
                                            rows="3"
                                            autocomplete="street-address"
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
                                                        'content.address'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.address'
                                                ]
                                                    ? 'contact-address-help contact-address-error'
                                                    : 'contact-address-help'
                                            "
                                            class="min-h-24 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 py-2 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @input="clearContentError"
                                        />
                                        <p
                                            id="contact-address-help"
                                            class="mt-2 text-xs text-[var(--workspace-muted)]"
                                        >
                                            Optional. Visitors can open
                                            directions to this address in Google
                                            Maps.
                                        </p>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.address'
                                                ]
                                            "
                                            id="contact-address-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.address'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <fieldset
                                        class="space-y-3 rounded-xl bg-[var(--workspace-soft)] p-4"
                                    >
                                        <legend
                                            class="px-1 text-sm font-semibold"
                                        >
                                            Location map
                                        </legend>
                                        <label
                                            class="flex min-h-11 items-center gap-3 text-sm font-semibold"
                                        >
                                            <input
                                                ref="mapEnabledInput"
                                                v-model="draftMapEnabled"
                                                type="checkbox"
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
                                                            'content.map.enabled'
                                                        ],
                                                    )
                                                "
                                                :aria-describedby="
                                                    saveForm.errors[
                                                        'content.map.enabled'
                                                    ]
                                                        ? 'contact-map-help contact-map-error'
                                                        : 'contact-map-help'
                                                "
                                                class="size-4 accent-[var(--workspace-green)] focus-visible:outline-2 focus-visible:outline-offset-4"
                                                @change="clearContentError"
                                            />
                                            Show map
                                        </label>
                                        <div>
                                            <label
                                                for="contact-map-url"
                                                class="mb-2 block text-sm font-semibold"
                                                >OpenStreetMap sharing
                                                link</label
                                            >
                                            <input
                                                id="contact-map-url"
                                                ref="mapUrlInput"
                                                v-model="draftMapUrl"
                                                type="text"
                                                inputmode="url"
                                                autocomplete="off"
                                                placeholder="https://www.openstreetmap.org/?mlat=...&amp;mlon=..."
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
                                                            'content.map'
                                                        ] ||
                                                        saveForm.errors[
                                                            'content.map.url'
                                                        ] ||
                                                        mapInputError,
                                                    )
                                                "
                                                :aria-describedby="
                                                    saveForm.errors[
                                                        'content.map'
                                                    ] ||
                                                    saveForm.errors[
                                                        'content.map.url'
                                                    ] ||
                                                    saveForm.errors[
                                                        'content.map.enabled'
                                                    ] ||
                                                    mapInputError
                                                        ? 'contact-map-help contact-map-error'
                                                        : 'contact-map-help'
                                                "
                                                class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                                @input="clearContentError"
                                            />
                                        </div>
                                        <div
                                            id="contact-map-help"
                                            class="space-y-2 text-xs text-[var(--workspace-muted)]"
                                        >
                                            <ol
                                                class="list-decimal space-y-1 pl-4"
                                            >
                                                <li>
                                                    Find your church on
                                                    <a
                                                        href="https://www.openstreetmap.org/"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="font-semibold underline underline-offset-4"
                                                        >OpenStreetMap (opens in
                                                        a new tab)</a
                                                    >.
                                                </li>
                                                <li>
                                                    Choose Share, check Include
                                                    marker, and place the pin on
                                                    your church.
                                                </li>
                                                <li>
                                                    Copy the full Link URL,
                                                    paste it here, and turn on
                                                    Show map.
                                                </li>
                                            </ol>
                                            <p>
                                                Confirm the pin in the preview.
                                                Use the full link, not the short
                                                link or HTML embed code.
                                            </p>
                                            <p>
                                                Save your block, then Publish to
                                                update the live site. If your
                                                street address changes, update
                                                this map link too.
                                            </p>
                                            <p>
                                                Turning off Show map keeps your
                                                link for later. The map loads
                                                from OpenStreetMap; your contact
                                                details and directions remain
                                                available separately.
                                            </p>
                                        </div>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.map'
                                                ] ||
                                                saveForm.errors[
                                                    'content.map.url'
                                                ] ||
                                                saveForm.errors[
                                                    'content.map.enabled'
                                                ] ||
                                                mapInputError
                                            "
                                            id="contact-map-error"
                                            role="alert"
                                            class="text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.map'
                                                ] ||
                                                saveForm.errors[
                                                    'content.map.url'
                                                ] ||
                                                saveForm.errors[
                                                    'content.map.enabled'
                                                ] ||
                                                mapInputError
                                            }}
                                        </p>
                                    </fieldset>
                                </template>
                            </div>
                        </details>
                        <details
                            :key="'background-' + selectedBlock.id"
                            class="block-editor-section"
                        >
                            <summary>Background &amp; layout</summary>
                            <div class="space-y-5 pt-4">
                                <fieldset
                                    class="space-y-4 rounded-xl bg-[var(--workspace-soft)] p-4"
                                >
                                    <legend class="px-1 text-sm font-semibold">
                                        Block appearance
                                    </legend>
                                    <p
                                        v-if="saveForm.errors['content.style']"
                                        role="alert"
                                        class="text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ saveForm.errors['content.style'] }}
                                    </p>
                                    <div
                                        v-if="
                                            selectedBlock.type === 'text_image'
                                        "
                                    >
                                        <label
                                            for="block-layout"
                                            class="mb-2 block text-sm font-semibold"
                                            >Image placement</label
                                        >
                                        <select
                                            id="block-layout"
                                            ref="blockLayoutInput"
                                            v-model="draftBlockStyle.layout"
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
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.layout'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.style.layout'
                                                ]
                                                    ? 'block-layout-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="image_right">
                                                Image on the right
                                            </option>
                                            <option value="image_left">
                                                Image on the left
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.layout'
                                                ]
                                            "
                                            id="block-layout-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.layout'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="
                                            selectedBlock.type ===
                                            'service_times'
                                        "
                                    >
                                        <label
                                            for="block-service-layout"
                                            class="mb-2 block text-sm font-semibold"
                                            >Service-time layout</label
                                        >
                                        <select
                                            id="block-service-layout"
                                            ref="blockLayoutInput"
                                            v-model="draftBlockStyle.layout"
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
                                                        'content.style.layout'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.style.layout'
                                                ]
                                                    ? 'block-service-layout-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="list">List</option>
                                            <option value="grid">
                                                Two-column grid
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.layout'
                                                ]
                                            "
                                            id="block-service-layout-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.layout'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <fieldset
                                        v-if="
                                            selectedBlock.type === 'image' ||
                                            selectedBlock.type === 'text_image'
                                        "
                                        class="space-y-4"
                                        :disabled="
                                            uploadInProgress ||
                                            saveForm.processing ||
                                            addForm.processing ||
                                            deleteForm.processing ||
                                            orderForm.processing ||
                                            imageUploadForm.processing ||
                                            altTextForm.processing
                                        "
                                    >
                                        <legend
                                            class="mb-2 text-sm font-semibold"
                                        >
                                            Image presentation
                                        </legend>
                                        <div>
                                            <label
                                                for="block-image-ratio"
                                                class="mb-2 block text-sm font-semibold"
                                                >Image shape</label
                                            >
                                            <select
                                                id="block-image-ratio"
                                                v-model="
                                                    draftBlockStyle.image_ratio
                                                "
                                                :aria-invalid="
                                                    Boolean(
                                                        saveForm.errors[
                                                            'content.style.image_ratio'
                                                        ],
                                                    )
                                                "
                                                :aria-describedby="
                                                    saveForm.errors[
                                                        'content.style.image_ratio'
                                                    ]
                                                        ? 'image-ratio-help image-ratio-error'
                                                        : 'image-ratio-help'
                                                "
                                                class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                                @change="clearContentError"
                                            >
                                                <option value="original">
                                                    Original
                                                </option>
                                                <option value="landscape">
                                                    16:9 landscape
                                                </option>
                                                <option value="square">
                                                    Square
                                                </option>
                                                <option value="portrait">
                                                    4:5 portrait
                                                </option>
                                            </select>
                                            <p
                                                id="image-ratio-help"
                                                class="mt-2 text-xs text-[var(--workspace-muted)]"
                                            >
                                                Original keeps the full image
                                                and its current sizing. Other
                                                shapes crop to fill the frame.
                                            </p>
                                            <p
                                                v-if="
                                                    saveForm.errors[
                                                        'content.style.image_ratio'
                                                    ]
                                                "
                                                id="image-ratio-error"
                                                role="alert"
                                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                                            >
                                                {{
                                                    saveForm.errors[
                                                        'content.style.image_ratio'
                                                    ]
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <label
                                                for="block-crop-position"
                                                class="mb-2 block text-sm font-semibold"
                                                >Vertical crop position</label
                                            >
                                            <select
                                                id="block-crop-position"
                                                v-model="
                                                    draftBlockStyle.crop_position
                                                "
                                                :disabled="
                                                    draftBlockStyle.image_ratio ===
                                                    'original'
                                                "
                                                :aria-invalid="
                                                    Boolean(
                                                        saveForm.errors[
                                                            'content.style.crop_position'
                                                        ],
                                                    )
                                                "
                                                :aria-describedby="
                                                    saveForm.errors[
                                                        'content.style.crop_position'
                                                    ]
                                                        ? 'crop-position-help crop-position-error'
                                                        : 'crop-position-help'
                                                "
                                                class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                                @change="clearContentError"
                                            >
                                                <option value="top">Top</option>
                                                <option value="center">
                                                    Center
                                                </option>
                                                <option value="bottom">
                                                    Bottom
                                                </option>
                                            </select>
                                            <p
                                                id="crop-position-help"
                                                class="mt-2 text-xs text-[var(--workspace-muted)]"
                                            >
                                                Applies when an image shape
                                                crops the photo.
                                            </p>
                                            <p
                                                v-if="
                                                    saveForm.errors[
                                                        'content.style.crop_position'
                                                    ]
                                                "
                                                id="crop-position-error"
                                                role="alert"
                                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                                            >
                                                {{
                                                    saveForm.errors[
                                                        'content.style.crop_position'
                                                    ]
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <label
                                                for="block-corner-style"
                                                class="mb-2 block text-sm font-semibold"
                                                >Corners</label
                                            >
                                            <select
                                                id="block-corner-style"
                                                v-model="
                                                    draftBlockStyle.corner_style
                                                "
                                                :aria-invalid="
                                                    Boolean(
                                                        saveForm.errors[
                                                            'content.style.corner_style'
                                                        ],
                                                    )
                                                "
                                                :aria-describedby="
                                                    saveForm.errors[
                                                        'content.style.corner_style'
                                                    ]
                                                        ? 'corner-style-error'
                                                        : undefined
                                                "
                                                class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                                @change="clearContentError"
                                            >
                                                <option value="current">
                                                    Current
                                                </option>
                                                <option value="square">
                                                    Square
                                                </option>
                                                <option value="rounded">
                                                    More rounded
                                                </option>
                                            </select>
                                            <p
                                                v-if="
                                                    saveForm.errors[
                                                        'content.style.corner_style'
                                                    ]
                                                "
                                                id="corner-style-error"
                                                role="alert"
                                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                                            >
                                                {{
                                                    saveForm.errors[
                                                        'content.style.corner_style'
                                                    ]
                                                }}
                                            </p>
                                        </div>
                                    </fieldset>
                                    <div>
                                        <label
                                            for="block-alignment"
                                            class="mb-2 block text-sm font-semibold"
                                            >Content alignment</label
                                        >
                                        <select
                                            id="block-alignment"
                                            ref="blockAlignmentInput"
                                            v-model="draftBlockStyle.alignment"
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
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.alignment'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.style.alignment'
                                                ]
                                                    ? 'block-alignment-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="left">Left</option>
                                            <option value="center">
                                                Center
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.alignment'
                                                ]
                                            "
                                            id="block-alignment-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.alignment'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            for="block-background"
                                            class="mb-2 block text-sm font-semibold"
                                            >Background</label
                                        >
                                        <select
                                            id="block-background"
                                            ref="blockBackgroundInput"
                                            v-model="draftBlockStyle.background"
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
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.background'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.style.background'
                                                ]
                                                    ? 'block-background-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="theme">
                                                Theme background
                                            </option>
                                            <option value="soft">
                                                Soft contrast
                                            </option>
                                            <option value="accent">
                                                Accent
                                            </option>
                                            <option value="contrast">
                                                Contrast
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.background'
                                                ]
                                            "
                                            id="block-background-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.background'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            for="block-spacing"
                                            class="mb-2 block text-sm font-semibold"
                                            >Section spacing</label
                                        >
                                        <select
                                            id="block-spacing"
                                            v-model="draftBlockStyle.spacing"
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
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.spacing'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.style.spacing'
                                                ]
                                                    ? 'block-spacing-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="compact">
                                                Compact
                                            </option>
                                            <option value="current">
                                                Current
                                            </option>
                                            <option value="spacious">
                                                Spacious
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.spacing'
                                                ]
                                            "
                                            id="block-spacing-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.spacing'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            for="block-content_width"
                                            class="mb-2 block text-sm font-semibold"
                                            >Content width</label
                                        >
                                        <select
                                            id="block-content_width"
                                            v-model="
                                                draftBlockStyle.content_width
                                            "
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
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.content_width'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.style.content_width'
                                                ]
                                                    ? 'block-content_width-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="narrow">
                                                Narrow
                                            </option>
                                            <option value="current">
                                                Current
                                            </option>
                                            <option value="full">
                                                Full available width
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.content_width'
                                                ]
                                            "
                                            id="block-content_width-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.content_width'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div v-if="blockHasHeading(selectedBlock)">
                                        <label
                                            for="block-heading_size"
                                            class="mb-2 block text-sm font-semibold"
                                            >Heading size</label
                                        >
                                        <select
                                            id="block-heading_size"
                                            v-model="
                                                draftBlockStyle.heading_size
                                            "
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
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.heading_size'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.style.heading_size'
                                                ]
                                                    ? 'block-heading_size-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option value="small">Small</option>
                                            <option value="current">
                                                Current
                                            </option>
                                            <option value="large">Large</option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.heading_size'
                                                ]
                                            "
                                            id="block-heading_size-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.heading_size'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                </fieldset>
                                <section
                                    v-if="selectedBlock.type === 'hero'"
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
                                                selectedBlock.content
                                                    .media_asset_id
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
                                                    imageUploadForm.errors
                                                        .image ||
                                                    saveForm.errors[
                                                        'content.media_asset_id'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                imageUploadError ||
                                                imageUploadForm.errors.image ||
                                                saveForm.errors[
                                                    'content.media_asset_id'
                                                ]
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
                                            JPEG or PNG, up to 5 MB. Uploading
                                            replaces the saved image.
                                        </p>
                                        <p
                                            v-if="
                                                imageUploadError ||
                                                imageUploadForm.errors.image ||
                                                saveForm.errors[
                                                    'content.media_asset_id'
                                                ]
                                            "
                                            id="block-image-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                imageUploadError ||
                                                imageUploadForm.errors.image ||
                                                saveForm.errors[
                                                    'content.media_asset_id'
                                                ]
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
                                        {{
                                            imageUploadForm.progress.percentage
                                        }}%
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
                                                    altTextForm.errors
                                                        .alt_text ||
                                                    imageUploadForm.errors
                                                        .alt_text,
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
                                            v-if="
                                                selectedBlock.content
                                                    .media_asset_id
                                            "
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
                                            v-if="
                                                selectedBlock.content
                                                    .media_asset_id
                                            "
                                            type="button"
                                            :disabled="
                                                uploadInProgress ||
                                                (isAltTextDirty &&
                                                    !clearImagePending) ||
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
                                        v-if="
                                            selectedBlock.content.media_asset_id
                                        "
                                        id="block-image-clear-help"
                                        class="text-xs text-[var(--workspace-muted)]"
                                    >
                                        <template v-if="clearImagePending">
                                            Save the block to clear this image,
                                            or undo the clear to keep it. You
                                            can also choose a replacement image
                                            now.
                                        </template>
                                        <template v-else>
                                            Save a changed description before
                                            clearing the image. Clear image
                                            takes effect when you save the
                                            block.
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

                                <fieldset
                                    v-if="selectedBlock.type === 'hero'"
                                    class="hero-options space-y-4"
                                    :disabled="editorWriteInProgress"
                                >
                                    <legend class="mb-3 font-semibold">
                                        Hero background settings
                                    </legend>
                                    <div>
                                        <label for="hero-height"
                                            >Hero height</label
                                        >
                                        <select
                                            id="hero-height"
                                            v-model="draftBlockStyle.height"
                                            :aria-invalid="
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.height'
                                                    ],
                                                )
                                            "
                                            aria-describedby="hero-height-error"
                                            @change="clearContentError"
                                        >
                                            <option value="current">
                                                Current height
                                            </option>
                                            <option value="medium">
                                                Medium (60vh)
                                            </option>
                                            <option value="full">
                                                Full screen (100vh)
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.height'
                                                ]
                                            "
                                            id="hero-height-error"
                                            role="alert"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.height'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label for="hero-overlay"
                                            >Image overlay</label
                                        >
                                        <select
                                            id="hero-overlay"
                                            v-model="draftBlockStyle.overlay"
                                            :aria-invalid="
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.overlay'
                                                    ],
                                                )
                                            "
                                            aria-describedby="hero-overlay-error"
                                            @change="clearContentError"
                                        >
                                            <option value="light">Light</option>
                                            <option value="medium">
                                                Medium
                                            </option>
                                            <option value="dark">Dark</option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.overlay'
                                                ]
                                            "
                                            id="hero-overlay-error"
                                            role="alert"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.overlay'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <label for="hero-motion"
                                            >Background motion</label
                                        >
                                        <select
                                            id="hero-motion"
                                            v-model="draftBlockStyle.motion"
                                            :aria-invalid="
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.style.motion'
                                                    ],
                                                )
                                            "
                                            aria-describedby="hero-motion-error"
                                            @change="clearContentError"
                                        >
                                            <option value="normal">
                                                Normal scrolling
                                            </option>
                                            <option value="fixed">
                                                Fixed background
                                            </option>
                                            <option value="half">
                                                Half-speed parallax
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.style.motion'
                                                ]
                                            "
                                            id="hero-motion-error"
                                            role="alert"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.style.motion'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                </fieldset>
                            </div>
                        </details>
                        <details
                            v-if="
                                selectedBlock.type === 'hero' ||
                                blockHasTextButton(selectedBlock)
                            "
                            :key="'buttons-' + selectedBlock.id"
                            class="block-editor-section"
                        >
                            <summary>Buttons</summary>
                            <div class="space-y-5 pt-4">
                                <div class="space-y-5">
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
                                                saveForm.errors[
                                                    'content.link_type'
                                                ]
                                                    ? 'hero-link-type-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 focus:outline-none disabled:opacity-60"
                                            @change="changeHeroLinkType"
                                        >
                                            <option value="none">
                                                No button
                                            </option>
                                            <option value="section">
                                                A section on this page
                                            </option>
                                            <option value="page">
                                                A page on this site
                                            </option>
                                            <option value="external">
                                                An external website
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.link_type'
                                                ]
                                            "
                                            id="hero-link-type-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.link_type'
                                                ]
                                            }}
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
                                                saveForm.errors[
                                                    'content.button_label'
                                                ]
                                                    ? 'hero-button-label-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @input="clearContentError"
                                        />
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.button_label'
                                                ]
                                            "
                                            id="hero-button-label-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.button_label'
                                                ]
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
                                                        item.id !==
                                                        selectedBlock?.id,
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
                                            Add another block to link to a
                                            section.
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
                                                saveForm.errors[
                                                    'content.external_url'
                                                ]
                                                    ? 'hero-external-url-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] outline-none focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 disabled:opacity-60"
                                            @input="clearContentError"
                                        />
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.external_url'
                                                ]
                                            "
                                            id="hero-external-url-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.external_url'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="
                                            selectedBlock.type !== 'hero' &&
                                            draftLinkType === 'page'
                                        "
                                    >
                                        <label
                                            for="text-button-page"
                                            class="mb-2 block text-sm font-semibold"
                                            >Link to page</label
                                        >
                                        <select
                                            id="text-button-page"
                                            v-model.number="
                                                draftHero.target_page_id
                                            "
                                            :disabled="editorWriteInProgress"
                                            :aria-invalid="
                                                Boolean(
                                                    saveForm.errors[
                                                        'content.target_page_id'
                                                    ],
                                                )
                                            "
                                            :aria-describedby="
                                                saveForm.errors[
                                                    'content.target_page_id'
                                                ]
                                                    ? 'text-button-page-error'
                                                    : undefined
                                            "
                                            class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-[var(--workspace-ink)] focus:border-[var(--workspace-green)] focus:ring-2 focus:ring-[var(--workspace-green)]/20 focus:outline-none disabled:opacity-60"
                                            @change="clearContentError"
                                        >
                                            <option :value="null">
                                                Choose a page
                                            </option>
                                            <option
                                                v-for="page in props.navigation_pages"
                                                :key="page.id"
                                                :value="page.id"
                                            >
                                                {{ page.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                saveForm.errors[
                                                    'content.target_page_id'
                                                ]
                                            "
                                            id="text-button-page-error"
                                            role="alert"
                                            class="mt-2 text-sm text-red-700 dark:text-red-300"
                                        >
                                            {{
                                                saveForm.errors[
                                                    'content.target_page_id'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <HeroOptions
                                        v-if="selectedBlock.type === 'hero'"
                                        section="buttons"
                                        v-model="draftHero"
                                        :primary-type="draftLinkType"
                                        :pages="props.navigation_pages"
                                        :sections="
                                            props.blocks
                                                .filter(
                                                    (item) =>
                                                        item.id !==
                                                        selectedBlock?.id,
                                                )
                                                .map((item) => ({
                                                    id: item.id,
                                                    name: `${item.position + 1}. ${labelFor(item.type)}`,
                                                }))
                                        "
                                        :disabled="editorWriteInProgress"
                                        :errors="saveForm.errors"
                                        @change="clearContentError"
                                    />
                                </div>
                            </div>
                        </details>
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
