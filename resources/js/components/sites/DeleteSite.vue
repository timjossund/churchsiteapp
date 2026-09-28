<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps<{
    site: { id: number; name: string };
    disabled: boolean;
}>();
const emit = defineEmits<{
    submit: [submit: () => void];
    processing: [processing: boolean];
}>();
const open = ref(false);
const form = useForm({ name: '' });
const input = ref<HTMLInputElement | null>(null);
const error = ref('');
let focusAfterSubmit = false;
watch(
    () => form.processing,
    (processing) => emit('processing', processing),
    { flush: 'sync' },
);
function setOpen(value: boolean) {
    if (form.processing) return;
    open.value = value;
    form.reset();
    form.clearErrors();
    error.value = '';
}
function failed() {
    error.value =
        'We could not confirm the result. Your request may have been saved. Retry or check My sites for its status.';
    focusAfterSubmit = true;
    return false;
}
function submit() {
    if (props.disabled || form.processing) return;
    error.value = '';
    emit('submit', () =>
        form.delete(`/sites/${props.site.id}`, {
            preserveScroll: true,
            onError: () => {
                focusAfterSubmit = true;
            },
            onFinish: () => {
                if (focusAfterSubmit) nextTick(() => input.value?.focus());
                focusAfterSubmit = false;
            },
            onHttpException: failed,
            onNetworkError: failed,
        }),
    );
}
</script>

<template>
    <section
        aria-labelledby="delete-site-heading"
        class="mt-8 space-y-3 rounded-xl border border-red-200 p-6 dark:border-red-900"
    >
        <h2 id="delete-site-heading" class="text-lg font-semibold">
            Delete site
        </h2>
        <p class="text-sm text-[var(--workspace-muted)]">
            Permanently remove this site and its content. Your account and other
            sites stay available.
        </p>
        <Dialog :open="open" @update:open="setOpen">
            <DialogTrigger as-child
                ><Button variant="destructive" :disabled="disabled"
                    >Delete site</Button
                ></DialogTrigger
            >
            <DialogContent
                :show-close-button="!form.processing"
                @open-auto-focus="
                    (event) => {
                        event.preventDefault();
                        input?.focus();
                    }
                "
                @escape-key-down="
                    (event) => {
                        if (form.processing) event.preventDefault();
                    }
                "
                @interact-outside="
                    (event) => {
                        if (form.processing) event.preventDefault();
                    }
                "
            >
                <form class="space-y-5" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>Delete {{ site.name }}?</DialogTitle>
                        <DialogDescription
                            >This cannot be undone. The site goes offline
                            immediately and its content is removed. We will
                            cancel subscription renewal and finish cleanup after
                            any paid period ends. No refund is issued. Your
                            account and other sites are
                            unaffected.</DialogDescription
                        >
                    </DialogHeader>
                    <div class="space-y-2">
                        <label
                            for="delete-site-name"
                            class="block text-sm font-medium"
                            >Type
                            <strong class="break-words">{{
                                site.name.trim()
                            }}</strong>
                            to confirm</label
                        >
                        <input
                            id="delete-site-name"
                            ref="input"
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="255"
                            autocomplete="off"
                            :disabled="form.processing"
                            :aria-invalid="Boolean(form.errors.name)"
                            :aria-describedby="
                                form.errors.name
                                    ? 'delete-site-name-error'
                                    : undefined
                            "
                            class="min-h-11 w-full rounded-lg border border-input bg-background px-3 focus-visible:outline-2 focus-visible:outline-ring"
                            @input="form.clearErrors('name')"
                        />
                        <p
                            v-if="form.errors.name"
                            id="delete-site-name-error"
                            role="alert"
                            class="text-sm text-red-700 dark:text-red-300"
                        >
                            {{ form.errors.name }}
                        </p>
                        <p
                            v-if="error"
                            role="alert"
                            class="text-sm text-red-700 dark:text-red-300"
                        >
                            {{ error }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="form.processing"
                            @click="setOpen(false)"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="form.processing"
                            >{{
                                form.processing
                                    ? 'Deleting site...'
                                    : 'Permanently delete site'
                            }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
