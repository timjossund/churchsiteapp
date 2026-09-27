<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { update } from '@/routes/sites/pages/settings';
import { store, destroy } from '@/routes/sites/pages/social-image';

const props = defineProps<{
    siteId: number;
    siteSlug: string | null;
    page: {
        id: number;
        is_home: boolean;
        path: string | null;
        seo_title: string | null;
        seo_description: string | null;
        default_title: string;
        social_image: { url: string; alt_text: string | null } | null;
    };
    busy: boolean;
    runVisit: (submit: () => void) => void;
}>();
const emit = defineEmits<{ busy: [boolean]; dirty: [boolean] }>();
const form = useForm({
    path: props.page.path ?? '',
    seo_title: props.page.seo_title ?? '',
    seo_description: props.page.seo_description ?? '',
});
const imageForm = useForm<{ image: File | null; alt_text: string }>({
    image: null,
    alt_text: '',
});
const clearForm = useForm({});
const expanded = ref(false);
const error = ref('');
const status = ref('');
const imageError = ref('');
const imageStatus = ref('');
const pathInput = ref<HTMLInputElement | null>(null);
const titleInput = ref<HTMLInputElement | null>(null);
const descriptionInput = ref<HTMLTextAreaElement | null>(null);
const imageInput = ref<HTMLInputElement | null>(null);
const pendingFocus = ref<HTMLElement | null>(null);
const pending = computed(
    () => form.processing || imageForm.processing || clearForm.processing,
);
const dirty = computed(() => form.isDirty || imageForm.image !== null);
const address = computed(() => `/s/${props.siteSlug ?? '{site-address}'}`);
const effectiveTitle = computed(
    () => form.seo_title.trim() || props.page.default_title,
);
watch(pending, (value) => emit('busy', value), { immediate: true });
watch(dirty, (value) => emit('dirty', value), { immediate: true });
watch(
    [pending, pendingFocus],
    async () => {
        if (pending.value || !pendingFocus.value) return;
        expanded.value = true;
        await nextTick();
        pendingFocus.value?.focus();
        pendingFocus.value = null;
    },
    { flush: 'post' },
);
onUnmounted(() => {
    emit('busy', false);
    emit('dirty', false);
});
function clearFeedback() {
    form.clearErrors();
    error.value = '';
    status.value = '';
}
function failedSave() {
    expanded.value = true;
    error.value =
        'We could not save page settings. Your edits are still here. Try again.';
    return false;
}
function save() {
    if (props.busy) return;
    clearFeedback();
    props.runVisit(() =>
        form
            .transform((data) =>
                props.page.is_home
                    ? {
                          seo_title: data.seo_title,
                          seo_description: data.seo_description,
                      }
                    : data,
            )
            .patch(update([props.siteId, props.page.id]).url, {
                preserveScroll: true,
                onSuccess: () => {
                    form.path = props.page.path ?? '';
                    form.seo_title = props.page.seo_title ?? '';
                    form.seo_description = props.page.seo_description ?? '';
                    form.defaults();
                    status.value = 'Page settings saved.';
                },
                onError: (errors) => {
                    pendingFocus.value = errors.path
                        ? pathInput.value
                        : errors.seo_title
                          ? titleInput.value
                          : descriptionInput.value;
                    if (
                        !errors.path &&
                        !errors.seo_title &&
                        !errors.seo_description
                    )
                        failedSave();
                },
                onHttpException: failedSave,
                onNetworkError: failedSave,
            }),
    );
}
function failedUpload() {
    imageStatus.value = '';
    imageError.value =
        'We could not upload this image. Retry your selected image.';
    pendingFocus.value = imageInput.value;
    return false;
}
function upload() {
    if (props.busy || !imageForm.image) return;
    imageError.value = '';
    imageForm.clearErrors();
    imageStatus.value = 'Uploading social preview image…';
    props.runVisit(() =>
        imageForm.post(store([props.siteId, props.page.id]).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                imageForm.reset();
                if (imageInput.value) imageInput.value.value = '';
                imageStatus.value = 'Social preview image uploaded.';
            },
            onError: (errors) => {
                imageStatus.value = '';
                imageError.value =
                    errors.image ??
                    errors.alt_text ??
                    'We could not upload this image. Try again.';
                pendingFocus.value = imageInput.value;
            },
            onCancel: () => {
                imageStatus.value =
                    'Upload canceled. Your selected image is ready to retry.';
            },
            onHttpException: failedUpload,
            onNetworkError: failedUpload,
        }),
    );
}
function selectImage(event: Event) {
    if (props.busy) return;
    const input = event.currentTarget;
    const file =
        input instanceof HTMLInputElement ? input.files?.[0] : undefined;
    if (!file) return;
    imageForm.image = file;
    upload();
}
function discardImage() {
    imageForm.reset();
    imageError.value = '';
    imageStatus.value = '';
    if (imageInput.value) imageInput.value.value = '';
}
function failedClear() {
    expanded.value = true;
    imageError.value = 'We could not clear this image. Try again.';
    return false;
}
function clearImage() {
    if (props.busy || !props.page.social_image) return;
    imageError.value = '';
    imageStatus.value = '';
    props.runVisit(() =>
        clearForm.delete(destroy([props.siteId, props.page.id]).url, {
            preserveScroll: true,
            onSuccess: () => {
                imageStatus.value = 'Social preview image cleared.';
            },
            onError: failedClear,
            onHttpException: failedClear,
            onNetworkError: failedClear,
        }),
    );
}
</script>

<template>
    <section
        aria-labelledby="page-settings-heading"
        class="mb-6 min-w-0 rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
    >
        <h2 id="page-settings-heading" class="font-serif text-xl">
            <button
                type="button"
                :aria-expanded="expanded"
                aria-controls="page-settings-fields"
                :disabled="busy"
                class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--workspace-green)] disabled:cursor-wait"
                @click="expanded = !expanded"
            >
                <span>Page settings</span>
                <span class="flex items-center gap-3 font-sans text-sm">
                    <span
                        v-if="dirty"
                        class="text-xs text-[var(--workspace-muted)]"
                        >Unsaved changes</span
                    >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="size-5 shrink-0 transition-transform"
                        :class="{ 'rotate-180': expanded }"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </span>
            </button>
        </h2>
        <p v-show="expanded" class="mt-2 text-sm text-[var(--workspace-muted)]">
            Set this page's address and how it appears in search and shared
            links. Saved changes stay in the draft until published.
        </p>
        <fieldset
            id="page-settings-fields"
            v-show="expanded"
            :disabled="busy"
            class="mt-4 grid min-w-0 gap-5 disabled:opacity-70 md:grid-cols-2"
        >
            <legend class="sr-only">Page address and sharing details</legend>
            <form class="min-w-0 space-y-4" @submit.prevent="save">
                <div>
                    <label
                        v-if="!page.is_home"
                        for="page-path"
                        class="mb-2 block text-sm font-semibold"
                        >Page path</label
                    >
                    <p v-else class="mb-2 text-sm font-semibold">
                        Page address
                    </p>
                    <p
                        class="mb-2 text-sm break-all text-[var(--workspace-muted)]"
                    >
                        {{ address }}{{ page.is_home ? '' : '/' + form.path }}
                    </p>
                    <input
                        v-if="!page.is_home"
                        id="page-path"
                        ref="pathInput"
                        v-model="form.path"
                        type="text"
                        maxlength="100"
                        autocomplete="off"
                        :aria-invalid="Boolean(form.errors.path)"
                        aria-describedby="page-path-help page-path-error"
                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-sm focus-visible:outline-2 focus-visible:outline-[var(--workspace-green)]"
                        @input="clearFeedback"
                    />
                    <p
                        id="page-path-help"
                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                    >
                        {{
                            page.is_home
                                ? 'Home always uses the site’s root address.'
                                : 'Letters, numbers, and hyphens. Renaming a page keeps this path. Published path changes replace the old URL without a redirect.'
                        }}
                    </p>
                    <p
                        v-if="form.errors.path"
                        id="page-path-error"
                        role="alert"
                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ form.errors.path }}
                    </p>
                </div>
                <div>
                    <label
                        for="page-seo-title"
                        class="mb-2 block text-sm font-semibold"
                        >Search and share title</label
                    >
                    <input
                        id="page-seo-title"
                        ref="titleInput"
                        v-model="form.seo_title"
                        :placeholder="page.default_title"
                        type="text"
                        maxlength="255"
                        :aria-invalid="Boolean(form.errors.seo_title)"
                        aria-describedby="page-title-help page-title-error"
                        class="min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 text-sm focus-visible:outline-2 focus-visible:outline-[var(--workspace-green)]"
                        @input="clearFeedback"
                    />
                    <p
                        id="page-title-help"
                        class="mt-2 text-xs break-words text-[var(--workspace-muted)]"
                    >
                        Title preview: {{ effectiveTitle }}. Leave blank to use
                        the default.
                    </p>
                    <p
                        v-if="form.errors.seo_title"
                        id="page-title-error"
                        role="alert"
                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ form.errors.seo_title }}
                    </p>
                </div>
                <div>
                    <label
                        for="page-seo-description"
                        class="mb-2 block text-sm font-semibold"
                        >Search and share description</label
                    >
                    <textarea
                        id="page-seo-description"
                        ref="descriptionInput"
                        v-model="form.seo_description"
                        maxlength="2000"
                        rows="3"
                        :aria-invalid="Boolean(form.errors.seo_description)"
                        aria-describedby="page-description-help page-description-error"
                        class="w-full rounded-lg border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-[var(--workspace-green)]"
                        @input="clearFeedback"
                    />
                    <p
                        id="page-description-help"
                        class="mt-2 text-xs text-[var(--workspace-muted)]"
                    >
                        Optional. A blank description stays unset.
                    </p>
                    <p
                        v-if="form.errors.seo_description"
                        id="page-description-error"
                        role="alert"
                        class="mt-2 text-sm text-red-700 dark:text-red-300"
                    >
                        {{ form.errors.seo_description }}
                    </p>
                </div>
                <p
                    v-if="error"
                    role="alert"
                    class="text-sm text-red-700 dark:text-red-300"
                >
                    {{ error }}
                </p>
                <p
                    v-if="status"
                    role="status"
                    class="text-sm font-semibold text-[var(--workspace-green)]"
                >
                    {{ status }}
                </p>
                <button
                    type="submit"
                    class="min-h-11 rounded-lg bg-[var(--workspace-green)] px-4 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--workspace-green)] dark:text-[var(--workspace-surface)]"
                >
                    {{ form.processing ? 'Saving…' : 'Save page settings' }}
                </button>
            </form>
            <div class="min-w-0">
                <label
                    for="page-social-image"
                    class="mb-2 block text-sm font-semibold"
                    >Social preview image</label
                >
                <img
                    v-if="page.social_image"
                    :src="page.social_image.url"
                    :alt="page.social_image.alt_text ?? ''"
                    class="mb-3 max-h-40 max-w-full rounded-lg object-contain"
                />
                <p v-else class="mb-3 text-sm text-[var(--workspace-muted)]">
                    No social image selected for this page.
                </p>
                <input
                    id="page-social-image"
                    ref="imageInput"
                    type="file"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    :aria-invalid="Boolean(imageError)"
                    aria-describedby="page-image-help page-image-error"
                    class="block min-h-11 w-full min-w-0 rounded-lg border border-[var(--workspace-line)] p-2 text-sm"
                    @change="selectImage"
                />
                <p
                    id="page-image-help"
                    class="mt-2 text-xs text-[var(--workspace-muted)]"
                >
                    JPEG or PNG, up to 5 MB. Uploads save immediately to this
                    page's draft.
                </p>
                <p v-if="imageForm.progress" role="status" class="mt-2 text-sm">
                    Uploading: {{ imageForm.progress.percentage }}%
                </p>
                <p
                    v-if="imageError"
                    id="page-image-error"
                    role="alert"
                    class="mt-2 text-sm text-red-700 dark:text-red-300"
                >
                    {{ imageError }}
                </p>
                <p
                    v-if="imageStatus"
                    role="status"
                    class="mt-2 text-sm text-[var(--workspace-muted)]"
                >
                    {{ imageStatus }}
                </p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <button
                        v-if="imageForm.image"
                        type="button"
                        class="min-h-10 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold"
                        @click="upload"
                    >
                        Retry selected image
                    </button>
                    <button
                        v-if="imageForm.image"
                        type="button"
                        class="min-h-10 rounded-lg border border-[var(--workspace-line)] px-3 text-sm"
                        @click="discardImage"
                    >
                        Discard selected image
                    </button>
                    <button
                        v-if="page.social_image"
                        type="button"
                        class="min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 dark:text-red-300"
                        @click="clearImage"
                    >
                        {{
                            clearForm.processing
                                ? 'Clearing…'
                                : 'Clear social image'
                        }}
                    </button>
                </div>
            </div>
        </fieldset>
    </section>
</template>
