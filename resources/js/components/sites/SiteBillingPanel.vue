<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { cancel, portal } from '@/routes/sites/billing';

export type BillingSummary = {
    can_manage: boolean;
    can_cancel: boolean;
    status: string;
    interval: string | null;
    amount: number | null;
    currency: string;
    paid_until: string | null;
    ends_at: string | null;
};

const props = defineProps<{
    billing: BillingSummary;
    siteId: number;
    busy: boolean;
    confirmLeave: () => boolean;
    runVisit: (submit: () => void) => void;
}>();
const emit = defineEmits<{ busy: [value: boolean] }>();
const pending = ref(false);
const error = ref('');
const errorElement = ref<HTMLElement | null>(null);
function showError(message: string) {
    error.value = message;
    nextTick(() => errorElement.value?.focus());
}
function failed() {
    showError('Billing could not be confirmed. Please refresh or retry.');
    return false;
}
function submit(action: 'portal' | 'cancel') {
    if (props.busy || pending.value) return;
    if (action === 'portal' && !props.confirmLeave()) return;
    if (
        action === 'cancel' &&
        !window.confirm(
            'Cancel renewal? Your paid access will continue through the end of the paid period. Your site and account will remain available.',
        )
    )
        return;
    error.value = '';
    props.runVisit(() =>
        router.post(
            (action === 'portal' ? portal : cancel).url(props.siteId),
            {},
            {
                preserveScroll: true,
                only: ['billing', 'domain', 'errors'],
                onStart: () => {
                    pending.value = true;
                    emit('busy', true);
                },
                onFinish: () => {
                    pending.value = false;
                    emit('busy', false);
                },
                onError: (errors) =>
                    showError(
                        errors.billing ??
                            'Billing could not be confirmed. Please retry.',
                    ),
                onHttpException: failed,
                onNetworkError: failed,
            },
        ),
    );
}
const labels: Record<string, string> = {
    free: 'Free publishing',
    pending: 'Payment confirmation pending',
    action_required: 'Payment needs attention',
    active: 'Subscription active',
    cancellation_scheduled: 'Renewal canceled',
    ended: 'Subscription ended',
    unavailable: 'Billing status unavailable',
};
const label = computed(
    () => labels[props.billing.status] ?? labels.unavailable,
);
const price = computed(() =>
    props.billing.amount === null
        ? null
        : new Intl.NumberFormat('en-US', {
              style: 'currency',
              currency: props.billing.currency,
          }).format(props.billing.amount / 100),
);
</script>

<template>
    <section
        aria-labelledby="site-billing-title"
        class="min-w-0 rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
    >
        <h2 id="site-billing-title" tabindex="-1" class="font-serif text-2xl">
            Billing
        </h2>
        <p class="mt-2 font-semibold" role="status">{{ label }}</p>
        <p v-if="price" class="mt-1 text-sm">
            {{ price }} USD /
            {{ billing.interval === 'annual' ? 'year' : 'month' }}
        </p>
        <p v-if="billing.ends_at" class="mt-2 text-sm">
            Subscription ends {{ new Date(billing.ends_at).toLocaleString() }}.
        </p>
        <p v-else-if="billing.paid_until" class="mt-2 text-sm">
            Paid through {{ new Date(billing.paid_until).toLocaleString() }}.
        </p>
        <p v-if="billing.status === 'free'" class="mt-2 text-sm">
            Editing and shareable site publishing are free. A subscription
            starts when you begin connecting your own domain: $15/month or
            $150/year USD, with no free trial.
        </p>
        <p v-else-if="billing.status === 'pending'" class="mt-2 text-sm">
            Payment has not been confirmed. Returning from checkout does not
            activate your subscription. Refresh this page to check again.
        </p>
        <p
            v-else-if="billing.status === 'action_required'"
            class="mt-2 text-sm"
        >
            Your payment needs attention before custom-domain access can be
            active.
        </p>
        <p v-else-if="billing.status === 'unavailable'" class="mt-2 text-sm">
            We could not confirm your billing status. Please try again later.
        </p>
        <p v-if="billing.status !== 'free'" class="mt-2 text-sm">
            Your shareable site and editor remain available without a
            subscription.
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button
                v-if="billing.can_manage"
                type="button"
                :disabled="busy || pending"
                class="min-h-11 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold disabled:opacity-50"
                @click="submit('portal')"
            >
                Payment methods and invoices
            </button>
            <button
                v-if="billing.can_cancel"
                type="button"
                :disabled="busy || pending"
                class="min-h-11 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold disabled:opacity-50"
                @click="submit('cancel')"
            >
                Cancel renewal
            </button>
        </div>
        <p v-if="pending" role="status" class="mt-2 text-sm">
            Contacting billing...
        </p>
        <p
            v-if="error"
            ref="errorElement"
            tabindex="-1"
            role="alert"
            class="mt-2 text-sm text-red-700 dark:text-red-300"
        >
            {{ error }}
        </p>
    </section>
</template>
