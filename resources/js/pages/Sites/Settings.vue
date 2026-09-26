<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import SiteMediaController from '@/actions/App/Http/Controllers/SiteMediaController';
import PageManager from '@/components/sites/PageManager.vue';
import { dashboard } from '@/routes';
type SiteTheme = 'warm' | 'clean' | 'bold';
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
    pages: { id: number; name: string; position: number; is_home: boolean }[];
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

const pagePending = ref(false);
const pageNamesDirty = ref(false);
let ownVisit = false;
function runOwnVisit(submit: () => void) {
    ownVisit = true;
    try {
        submit();
    } finally {
        ownVisit = false;
    }
}
function confirmEditorDiscard(): boolean {
    return (
        !hasUnsavedEditorChanges.value ||
        window.confirm('Discard your unsaved site settings?')
    );
}
let stopBefore: (() => void) | undefined;
let stopNavigate: (() => void) | undefined;
let settingsHistoryState: unknown;
let settingsUrl = '';
function guardHistory(event: PopStateEvent) {
    if (editorWriteInProgress.value || !confirmEditorDiscard()) {
        event.stopImmediatePropagation();
        window.history.pushState(settingsHistoryState, '', settingsUrl);
    }
}
function guardUnload(event: BeforeUnloadEvent) {
    if (!hasUnsavedEditorChanges.value && !editorWriteInProgress.value) return;
    event.preventDefault();
    event.returnValue = '';
}
onMounted(() => {
    settingsHistoryState = window.history.state;
    settingsUrl = window.location.href;
    stopNavigate = router.on('navigate', () => {
        settingsHistoryState = window.history.state;
        settingsUrl = window.location.href;
    });
    window.addEventListener('popstate', guardHistory, true);
    stopBefore = router.on('before', (event) => {
        if (ownVisit) return;
        if (editorWriteInProgress.value || !confirmEditorDiscard())
            event.preventDefault();
    });
    window.addEventListener('beforeunload', guardUnload);
});
onUnmounted(() => {
    stopBefore?.();
    stopNavigate?.();
    window.removeEventListener('popstate', guardHistory, true);
    window.removeEventListener('beforeunload', guardUnload);
});
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
const socialImageError = ref('');
const socialImageStatus = ref('');
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
    () => logoUploadForm.processing || socialImageForm.processing,
);
const editorWriteInProgress = computed(
    () =>
        pagePending.value ||
        uploadInProgress.value ||
        nameForm.processing ||
        appearanceForm.processing ||
        logoAltTextForm.processing ||
        logoClearForm.processing ||
        socialImageForm.processing ||
        socialImageClearForm.processing,
);
const pendingFocus = ref<HTMLElement | null>(null);
function queueFocus(element: HTMLElement | null) {
    pendingFocus.value = element;
}
watch(
    [editorWriteInProgress, pendingFocus],
    async () => {
        if (editorWriteInProgress.value || !pendingFocus.value) return;
        await nextTick();
        pendingFocus.value?.focus();
        pendingFocus.value = null;
    },
    { flush: 'post' },
);
const hasUnsavedContentChanges = computed(
    () =>
        nameForm.name !== props.site.name ||
        appearanceForm.isDirty ||
        isLogoAltTextDirty.value,
);
const hasUnsavedEditorChanges = computed(
    () => hasUnsavedContentChanges.value || pageNamesDirty.value,
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
    if (nameForm.processing || editorWriteInProgress.value) return;
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
                nextTick(() => queueFocus(nameInput.value));
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

function saveAppearance() {
    if (uploadInProgress.value) return;
    if (
        appearanceForm.processing ||
        nameForm.processing ||
        editorWriteInProgress.value
    )
        return;
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
                    if (fieldErrors.theme_key) queueFocus(themeInput.value);
                    else if (fieldErrors['footer.text'])
                        queueFocus(footerTextInput.value);
                    else if (fieldErrors.slug) queueFocus(siteSlugInput.value);
                    else if (fieldErrors.seo_title)
                        queueFocus(seoTitleInput.value);
                    else if (fieldErrors.seo_description)
                        queueFocus(seoDescriptionInput.value);
                    else queueFocus(themeInput.value);
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
    runOwnVisit(() =>
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
                nextTick(() => queueFocus(socialImageInput.value));
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
        }),
    );
}

function clearSocialImage() {
    if (!props.site.social_image || editorWriteInProgress.value) return;
    socialImageError.value = '';
    socialImageStatus.value = '';
    runOwnVisit(() =>
        socialImageClearForm.delete(
            '/sites/' + props.site.id + '/social-image',
            {
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
            },
        ),
    );
}

function releaseLogoUploadPreview() {
    if (logoUploadPreviewUrl.value)
        URL.revokeObjectURL(logoUploadPreviewUrl.value);
    logoUploadPreviewUrl.value = null;
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
        nextTick(() => queueFocus(logoFileInput.value));
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
                        if (errors.image) queueFocus(logoFileInput.value);
                        else if (errors.alt_text)
                            queueFocus(logoAltTextInput.value);
                        else queueFocus(logoFileInput.value);
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
                    nextTick(() => queueFocus(logoAltTextInput.value));
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
    layout: { breadcrumbs: [{ title: 'My sites', href: dashboard() }] },
});
</script>
<template>
    <Head :title="`${props.site.name} - Site settings`" />
    <main
        class="mx-auto w-full max-w-[76rem] px-5 py-8 sm:px-8 lg:px-12 lg:py-12"
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
                Site settings
            </p>
            <h1 class="mt-2 font-serif text-4xl tracking-tight">
                {{ props.site.name }}
            </h1>
            <p class="mt-3 text-[var(--workspace-muted)]">
                Manage your pages and the settings they share. Open a page to
                edit its content.
            </p>
        </header>
        <PageManager
            :site-id="props.site.id"
            :pages="props.pages"
            :busy="editorWriteInProgress"
            :confirm-leave="confirmEditorDiscard"
            :run-visit="runOwnVisit"
            @busy="pagePending = $event"
            @dirty="pageNamesDirty = $event"
        />
        <h2 class="mb-4 font-serif text-2xl">Shared site settings</h2>
        <section
            :inert="pagePending"
            aria-label="Shared site settings"
            class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
        >
            <div class="space-y-5">
                <div
                    class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
                >
                    <h3 class="text-base font-semibold">Rename site</h3>
                    <div
                        class="mt-4 border-t border-[var(--workspace-line)] pt-4"
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
                </div>
                <div
                    class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
                >
                    <h3 class="text-base font-semibold">Header logo</h3>
                    <div
                        class="mt-4 border-t border-[var(--workspace-line)] pt-4"
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
                                    JPEG or PNG, up to 5 MB. The preview updates
                                    after upload.
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
                            <img
                                v-if="logoUploadPreviewUrl || props.site.logo"
                                :src="
                                    logoUploadPreviewUrl ?? props.site.logo?.url
                                "
                                :alt="logoDraftAltText || props.site.name"
                                class="max-h-24 max-w-full rounded-lg object-contain"
                            />
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
                                            logoAltTextForm.errors.alt_text ||
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
                </div>
            </div>
            <div
                class="rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
            >
                <h3 class="text-base font-semibold">
                    Styles, footer &amp; sharing
                </h3>
                <div class="mt-4 border-t border-[var(--workspace-line)] pt-4">
                    <form
                        class="grid gap-4 sm:grid-cols-2"
                        @submit.prevent="saveAppearance"
                    >
                        <p
                            class="text-sm text-[var(--workspace-muted)] sm:col-span-2"
                        >
                            Theme and footer apply to every page. Search and
                            sharing details currently apply to Home.
                        </p>
                        <div>
                            <label
                                for="site-theme"
                                class="mb-2 block text-sm font-semibold"
                                >Site theme</label
                            >
                            <select
                                id="site-theme"
                                ref="themeInput"
                                v-model="appearanceForm.theme_key"
                                :disabled="appearanceForm.processing"
                                :aria-invalid="
                                    Boolean(appearanceForm.errors.theme_key)
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
                                        appearanceForm.errors['footer.text'],
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
                                Optional single-line text at the bottom of your
                                page.
                            </p>
                            <p
                                v-if="appearanceForm.errors['footer.text']"
                                id="site-footer-error"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{ appearanceForm.errors['footer.text'] }}
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
                                        Boolean(props.site.published_at) ||
                                        appearanceForm.processing
                                    "
                                    :aria-invalid="
                                        Boolean(appearanceForm.errors.slug)
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
                                Lowercase letters, numbers, and hyphens. Fixed
                                after first publication.
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
                                >Home page title</label
                            >
                            <input
                                id="site-seo-title"
                                ref="seoTitleInput"
                                v-model="appearanceForm.seo_title"
                                type="text"
                                maxlength="255"
                                :disabled="appearanceForm.processing"
                                :aria-invalid="
                                    Boolean(appearanceForm.errors.seo_title)
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
                                >Home description</label
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
                                        appearanceForm.errors.seo_description,
                                    )
                                "
                                :aria-describedby="
                                    appearanceForm.errors.seo_description
                                        ? 'site-seo-description-error'
                                        : undefined
                                "
                                class="w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 py-2 text-sm text-[var(--workspace-ink)]"
                                @input="clearAppearanceError"
                            />
                            <p
                                v-if="appearanceForm.errors.seo_description"
                                id="site-seo-description-error"
                                role="alert"
                                class="mt-2 text-sm text-red-700 dark:text-red-300"
                            >
                                {{ appearanceForm.errors.seo_description }}
                            </p>
                        </div>
                        <div>
                            <label
                                for="site-social-image"
                                class="mb-2 block text-sm font-semibold"
                                >Home social preview image</label
                            >
                            <img
                                v-if="props.site.social_image"
                                :src="props.site.social_image.url"
                                :alt="props.site.social_image.alt_text ?? ''"
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
                            class="text-sm text-red-700 sm:col-span-2 dark:text-red-300"
                        >
                            {{ appearanceError }}
                        </p>
                        <p
                            v-if="appearanceSaved"
                            role="status"
                            class="text-sm font-semibold text-[var(--workspace-green)] sm:col-span-2"
                        >
                            Shared site settings saved.
                        </p>
                        <button
                            type="submit"
                            :disabled="
                                uploadInProgress || appearanceForm.processing
                            "
                            class="min-h-11 w-full rounded-lg bg-[var(--workspace-green)] px-4 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60 sm:col-span-2 dark:text-[var(--workspace-surface)]"
                        >
                            {{
                                appearanceForm.processing
                                    ? 'Saving…'
                                    : 'Save appearance'
                            }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</template>
