<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import CustomHostnameController from '@/actions/App/Http/Controllers/CustomHostnameController';

export type DomainSummary = {
    enabled: boolean;
    hostname: string | null;
    status: string;
    message: string | null;
    paid: boolean;
    published: boolean;
    ownership_verified: boolean;
    dns_connected: boolean;
    connection_ready: boolean;
    ssl_ready: boolean;
    last_checked_at: string | null;
    records: { purpose: string; type: string; name: string; value: string }[];
    checkout_interval: 'monthly' | 'annual' | null;
    can_connect: boolean;
    can_checkout: boolean;
    can_check: boolean;
    can_disconnect: boolean;
    poll: boolean;
};
const props = defineProps<{
    domain: DomainSummary;
    siteId: number;
    busy: boolean;
    canPoll: boolean;
    confirmLeave: () => boolean;
    runVisit: (submit: () => void) => void;
}>();
const emit = defineEmits<{ busy: [value: boolean]; dirty: [value: boolean] }>();
const page = usePage();
const hostname = ref(props.domain.hostname ?? '');
const interval = ref(props.domain.checkout_interval ?? 'monthly');
const pending = ref(false);
const polling = ref(false);
const errors = ref<Record<string, string>>({});
const feedback = ref('');
const showDomainHelp = ref(false);
const copiedField = ref('');
let copyTimer: ReturnType<typeof setTimeout> | undefined;
const hostnameInput = ref<HTMLInputElement | null>(null);
const intervalInput = ref<HTMLInputElement | null>(null);
const messageElement = ref<HTMLElement | null>(null);
const heading = ref<HTMLElement | null>(null);
const disabled = computed(() => props.busy || pending.value || polling.value);
const canSubmit = computed(() =>
    props.domain.hostname
        ? props.domain.can_checkout
        : props.domain.can_connect &&
          (props.domain.paid || props.domain.can_checkout),
);
watch(hostname, () => {
    emit('dirty', !props.domain.hostname && hostname.value.trim() !== '');
    errors.value = {};
});
watch(
    () => props.domain.hostname,
    (value) => {
        hostname.value = value ?? '';
        emit('dirty', false);
    },
);
watch(
    () => props.domain.checkout_interval,
    (value) => {
        if (value) interval.value = value;
    },
);
const labels: Record<string, string> = {
    empty: 'Connect your domain',
    paused: 'Domain setup is paused',
    payment_pending: 'Payment confirmation pending',
    unpaid: 'Domain saved — paid access required',
    dns_pending: 'Waiting for DNS',
    connection_pending: 'Connection pending',
    ssl_pending: 'HTTPS certificate pending',
    checking: 'Checking connection',
    unpublished: 'Connected — publish your site to go live',
    live: 'Your domain is live',
    removal_pending: 'Disconnect in progress',
    unavailable: 'Connection needs attention',
};
const label = computed(
    () => labels[props.domain.status] ?? 'Status unavailable',
);
const forwardingHostname = computed(
    () => props.domain.hostname ?? 'www.example.org',
);
const rootHostname = computed(() =>
    forwardingHostname.value.replace(/^www\./, ''),
);
const checkoutReturn = computed(() =>
    new URLSearchParams(page.url.split('?')[1] ?? '').get('billing'),
);
const verifiedRecords = computed<Record<string, boolean>>(() => ({
    ownership: props.domain.ownership_verified,
    connection: props.domain.dns_connected,
    hostname: props.domain.connection_ready,
    certificate: props.domain.ssl_ready,
}));
const progress = computed(() => [
    { label: 'Payment confirmed', done: props.domain.paid },
    {
        label: 'Domain ownership verified',
        done: props.domain.ownership_verified,
    },
    { label: 'DNS points to your site', done: props.domain.dns_connected },
    { label: 'Connection active', done: props.domain.connection_ready },
    { label: 'HTTPS certificate ready', done: props.domain.ssl_ready },
    { label: 'Site published', done: props.domain.published },
]);
function showErrors(values: Record<string, string>) {
    errors.value = values;
    nextTick(() => {
        if (values.hostname && hostnameInput.value && !props.domain.hostname)
            hostnameInput.value.focus();
        else if (values.interval) intervalInput.value?.focus();
        else messageElement.value?.focus();
    });
}
function failed() {
    showErrors({
        domain: 'We could not confirm this request. Your saved settings are retained. Wait a moment, then refresh or retry.',
    });
    return false;
}
function submit(action: 'connect' | 'check' | 'disconnect') {
    if (disabled.value) return;
    if (action === 'connect' && (!canSubmit.value || !props.confirmLeave()))
        return;
    if (action === 'check' && !props.domain.can_check) return;
    if (
        action === 'disconnect' &&
        (!props.domain.can_disconnect ||
            !window.confirm(
                'Disconnect this domain? The old address stops serving your site immediately. Billing continues: use Cancel renewal in Billing separately. A replacement can be connected only after removal finishes, so there will be downtime.',
            ))
    )
        return;
    errors.value = {};
    feedback.value = '';
    props.runVisit(() =>
        router.visit(
            action === 'connect'
                ? CustomHostnameController.store.url(props.siteId)
                : action === 'check'
                  ? CustomHostnameController.check.url(props.siteId)
                  : CustomHostnameController.destroy.url(props.siteId),
            {
                method: action === 'disconnect' ? 'delete' : 'post',
                data:
                    action === 'connect'
                        ? {
                              hostname: props.domain.hostname ?? hostname.value,
                              interval: interval.value,
                          }
                        : {},
                only: ['domain', 'billing', 'errors'],
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    pending.value = true;
                    emit('busy', true);
                },
                onFinish: () => {
                    pending.value = false;
                    emit('busy', false);
                },
                onError: showErrors,
                onHttpException: failed,
                onNetworkError: failed,
                onSuccess: () => {
                    feedback.value =
                        action === 'disconnect'
                            ? 'Disconnect requested. Billing continues separately.'
                            : action === 'check'
                              ? 'Status refreshed.'
                              : 'Domain saved.';
                    nextTick(() => heading.value?.focus());
                },
            },
        ),
    );
}
async function copy(value: string, field: string, key: string) {
    clearTimeout(copyTimer);
    copiedField.value = '';
    try {
        await navigator.clipboard.writeText(value);
        feedback.value = `Copied ${field}.`;
        copiedField.value = key;
        copyTimer = setTimeout(() => {
            copiedField.value = '';
        }, 3000);
    } catch {
        feedback.value =
            'Copy is unavailable. Select and copy the record text below.';
    }
}
let timer: ReturnType<typeof setInterval> | undefined;
let polls = 0;
onMounted(() => {
    timer = setInterval(() => {
        if (
            !props.domain.poll ||
            !props.canPoll ||
            disabled.value ||
            document.hidden ||
            polls >= 30
        )
            return;
        polls++;
        polling.value = true;
        emit('busy', true);
        // Read status only. Provisioning continues in the server scheduler.
        props.runVisit(() =>
            router.reload({
                only: ['domain', 'billing'],
                onFinish: () => {
                    polling.value = false;
                    emit('busy', false);
                },
                onNetworkError: () => {
                    polling.value = false;
                    emit('busy', false);
                    return false;
                },
                onHttpException: () => {
                    polling.value = false;
                    emit('busy', false);
                    return false;
                },
            }),
        );
    }, 20000);
});
onUnmounted(() => {
    clearInterval(timer);
    clearTimeout(copyTimer);
});
</script>

<template>
    <section
        aria-labelledby="site-domain-title"
        class="min-w-0 rounded-[1.25rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] p-5 shadow-[var(--workspace-shadow)]"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2
                id="site-domain-title"
                ref="heading"
                tabindex="-1"
                class="font-serif text-2xl"
            >
                Custom domain
            </h2>
            <button
                type="button"
                class="min-h-11 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-4"
                :aria-expanded="showDomainHelp"
                aria-controls="site-domain-help"
                @click="showDomainHelp = !showDomainHelp"
            >
                {{ showDomainHelp ? 'Hide help' : 'Domain help' }}
            </button>
        </div>
        <div
            id="site-domain-help"
            v-show="showDomainHelp"
            class="mt-4 space-y-3 rounded-xl bg-gray-100 p-4 dark:bg-gray-800"
        >
            <h3 class="font-semibold">Domain FAQ</h3>
            <details
                class="rounded-lg border border-[var(--workspace-line)] p-3 text-sm"
            >
                <summary
                    class="cursor-pointer rounded font-semibold focus-visible:outline-2 focus-visible:outline-offset-4"
                >
                    How do I send visitors from my domain without www?
                </summary>
                <div class="mt-3 space-y-3">
                    <p>
                        Your site uses
                        <strong class="break-all">{{
                            forwardingHostname
                        }}</strong
                        >. To make
                        <strong class="break-all">{{ rootHostname }}</strong>
                        work too, set up forwarding with the company where you
                        bought your domain. Church Site App does not set up this
                        redirect automatically.
                    </p>
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>
                            Sign in to your domain provider and open Domain
                            forwarding or URL redirects.
                        </li>
                        <li>
                            Forward
                            <strong class="break-all">{{
                                rootHostname
                            }}</strong>
                            to
                            <strong class="break-all"
                                >https://{{ forwardingHostname }}</strong
                            >.
                        </li>
                        <li>
                            Choose a permanent (301) redirect, without masking
                            or framing. Enable HTTPS forwarding and keep page
                            paths and query strings if available.
                        </li>
                        <li>
                            Save, then test both http://{{ rootHostname }} and
                            https://{{ rootHostname }}. Both should open your
                            www site. Test a page address too if your site has
                            multiple pages.
                        </li>
                    </ol>
                    <p>
                        Keep your www CNAME and verification records as shown
                        above. If your provider does not support HTTPS
                        forwarding, ask its support team for help or use the
                        Cloudflare option below.
                    </p>
                </div>
            </details>
            <details
                class="rounded-lg border border-[var(--workspace-line)] p-3 text-sm"
            >
                <summary
                    class="cursor-pointer rounded font-semibold focus-visible:outline-2 focus-visible:outline-offset-4"
                >
                    Can I redirect my root domain with Cloudflare?
                </summary>
                <div class="mt-3 space-y-3">
                    <p>
                        If Cloudflare manages your domain's DNS, set up the
                        redirect in your own domain's Cloudflare dashboard.
                    </p>
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>
                            Select
                            <strong class="break-all">{{
                                rootHostname
                            }}</strong>
                            and make sure its root (@) DNS record is Proxied and
                            its HTTPS certificate is active. Leave your www
                            connection CNAME set to DNS only.
                        </li>
                        <li>
                            Open Rules, then Redirect Rules, and create a Single
                            Redirect.
                        </li>
                        <li>
                            Match requests whose hostname equals
                            <strong class="break-all">{{ rootHostname }}</strong
                            >, for both HTTP and HTTPS.
                        </li>
                        <li>
                            Set a permanent (301) redirect to
                            <strong class="break-all"
                                >https://{{ forwardingHostname }}</strong
                            >, preserving the incoming path and query string.
                        </li>
                        <li>
                            Deploy the rule, then test both HTTP and HTTPS root
                            addresses and a page path.
                        </li>
                    </ol>
                    <p>
                        Follow
                        <a
                            href="https://developers.cloudflare.com/rules/url-forwarding/examples/redirect-root-to-www/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold underline underline-offset-4"
                            >Cloudflare's root-to-www guide (opens in a new
                            tab)</a
                        >. Its example covers HTTPS; include HTTP in your rule
                        as well.
                    </p>
                </div>
            </details>
        </div>
        <p class="mt-2 font-semibold" role="status">{{ label }}</p>
        <p v-if="!domain.enabled" class="mt-2 text-sm">
            Domain setup is not available yet. Your shareable site remains
            available.
        </p>
        <p v-if="domain.message" class="mt-2 text-sm" role="status">
            {{ domain.message }}
        </p>
        <p
            v-if="!domain.paid && checkoutReturn === 'canceled'"
            class="mt-2 text-sm"
            role="status"
        >
            You returned from checkout without completing it. Your domain is
            saved; you can resume checkout below. Access waits for payment
            confirmation.
        </p>
        <p
            v-else-if="
                !domain.paid &&
                (checkoutReturn === 'processing' ||
                    domain.status === 'payment_pending')
            "
            class="mt-2 text-sm"
            role="status"
        >
            Waiting for confirmed payment. Returning from checkout does not
            activate your domain. Status updates automatically while you wait.
        </p>
        <form
            v-if="canSubmit"
            class="mt-4 space-y-4"
            @submit.prevent="submit('connect')"
        >
            <div>
                <label for="domain-hostname" class="block text-sm font-semibold"
                    >Full www hostname</label
                >
                <input
                    id="domain-hostname"
                    ref="hostnameInput"
                    v-model="hostname"
                    type="text"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                    :readonly="!!domain.hostname"
                    :disabled="disabled"
                    :aria-invalid="!!errors.hostname"
                    aria-describedby="domain-hostname-help domain-hostname-error"
                    placeholder="www.yourchurch.org"
                    class="mt-1 min-h-11 w-full rounded-lg border border-[var(--workspace-line)] bg-transparent px-3"
                />
                <p id="domain-hostname-help" class="mt-1 text-sm">
                    For example, www.yourchurch.org. Use a domain you own,
                    without https:// or a page path. Bare domains are not
                    supported.
                </p>
                <p
                    id="domain-hostname-error"
                    class="mt-1 text-sm text-red-700 dark:text-red-300"
                >
                    {{ errors.hostname }}
                </p>
            </div>
            <fieldset
                v-if="!domain.paid"
                :disabled="disabled"
                aria-describedby="domain-payment-help domain-interval-error"
                :aria-invalid="!!errors.interval"
            >
                <legend class="text-sm font-semibold">Choose your plan</legend>
                <label class="mt-2 flex min-h-11 items-center gap-2"
                    ><input
                        ref="intervalInput"
                        v-model="interval"
                        type="radio"
                        name="domain-plan"
                        value="monthly"
                    />
                    $15 / month USD</label
                >
                <label class="flex min-h-11 items-center gap-2"
                    ><input
                        v-model="interval"
                        type="radio"
                        name="domain-plan"
                        value="annual"
                    />
                    $150 / year USD</label
                >
                <p v-if="domain.checkout_interval" class="text-sm">
                    Your previous plan is selected. If checkout is still open,
                    finish it or wait for it to expire before changing plans.
                </p>
                <p id="domain-payment-help" class="mt-2 text-sm">
                    Payment is due in Stripe Checkout when you begin connecting,
                    before DNS and HTTPS setup finishes. There is no free trial.
                    Your subscription renews automatically; cancel renewal in
                    Billing.
                </p>
                <p
                    id="domain-interval-error"
                    class="mt-1 text-sm text-red-700 dark:text-red-300"
                >
                    {{ errors.interval }}
                </p>
            </fieldset>
            <p v-else class="text-sm">
                Your existing paid subscription covers this connection. No
                additional subscription is started.
            </p>
            <button
                type="submit"
                :disabled="disabled"
                class="min-h-11 rounded-lg border border-[var(--workspace-line)] px-4 text-sm font-semibold disabled:opacity-50"
            >
                {{
                    domain.paid
                        ? 'Connect domain'
                        : domain.hostname
                          ? 'Resume checkout'
                          : 'Continue to payment'
                }}
            </button>
        </form>
        <p v-else-if="!domain.hostname && domain.enabled" class="mt-2 text-sm">
            New domain setup is temporarily unavailable. Check Billing below or
            try again later.
        </p>
        <template v-if="domain.hostname">
            <p class="mt-3 font-semibold break-all">{{ domain.hostname }}</p>
            <p v-if="domain.status === 'unpaid'" class="mt-2 text-sm">
                Your domain configuration is retained, but custom-domain content
                is unavailable without confirmed paid access. Resume checkout
                when available, or manage payment in Billing below.
            </p>
            <p v-if="domain.status === 'unpublished'" class="mt-2 text-sm">
                Open a page in the editor and publish your site to make it
                available at this address.
            </p>
            <p v-if="domain.status === 'removal_pending'" class="mt-2 text-sm">
                The old address has stopped serving your site. Wait for removal
                to finish before connecting a replacement. Billing continues
                unless you cancel renewal separately.
            </p>
            <ul
                class="mt-4 grid gap-2 text-sm sm:grid-cols-2"
                aria-label="Connection progress"
            >
                <li v-for="item in progress" :key="item.label">
                    {{ item.label }}:
                    <strong>{{ item.done ? 'Complete' : 'Pending' }}</strong>
                </li>
            </ul>
            <div v-if="domain.status !== 'removal_pending'" class="mt-5">
                <h3 class="font-semibold">DNS records</h3>
                <p class="mt-1 text-sm">
                    Add these records at your DNS provider. Some providers
                    append your domain automatically; use the full names below
                    as a reference. Keep the connection CNAME set to DNS only if
                    your provider offers proxying. DNS and certificate checks
                    can take time.
                </p>
                <ul class="mt-3 space-y-3">
                    <li
                        v-for="(record, index) in domain.records"
                        :key="`${record.type}-${record.name}-${index}`"
                        class="rounded-lg border border-[var(--workspace-line)] p-3 text-sm"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="font-semibold">
                                {{ record.type }} ·
                                {{
                                    record.purpose === 'ownership'
                                        ? 'Ownership verification'
                                        : record.purpose === 'connection'
                                          ? 'Site connection'
                                          : record.purpose === 'certificate'
                                            ? 'HTTPS validation'
                                            : 'Provider validation'
                                }}
                            </p>
                            <span
                                v-if="verifiedRecords[record.purpose]"
                                class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2 py-1 text-xs font-semibold text-green-800 dark:bg-green-950 dark:text-green-200"
                            >
                                <CircleCheck
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Verified
                            </span>
                            <span v-else class="text-xs font-medium">
                                Pending
                            </span>
                        </div>
                        <p class="mt-2">
                            Name:
                            <code class="break-all select-text">{{
                                record.name
                            }}</code>
                        </p>
                        <p class="mt-2">
                            Value:
                            <code class="break-all select-text">{{
                                record.value
                            }}</code>
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-11 min-w-32 items-center justify-center gap-1.5 rounded-lg border border-[var(--workspace-line)] px-3"
                                :class="{
                                    'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200':
                                        copiedField === `${index}-name`,
                                }"
                                :aria-label="`Copy ${record.type} record ${index + 1} name`"
                                @click="
                                    copy(
                                        record.name,
                                        'record name',
                                        `${index}-name`,
                                    )
                                "
                            >
                                <CircleCheck
                                    v-if="copiedField === `${index}-name`"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{
                                    copiedField === `${index}-name`
                                        ? 'Copied!'
                                        : 'Copy name'
                                }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 min-w-32 items-center justify-center gap-1.5 rounded-lg border border-[var(--workspace-line)] px-3"
                                :class="{
                                    'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200':
                                        copiedField === `${index}-value`,
                                }"
                                :aria-label="`Copy ${record.type} record ${index + 1} value`"
                                @click="
                                    copy(
                                        record.value,
                                        'record value',
                                        `${index}-value`,
                                    )
                                "
                            >
                                <CircleCheck
                                    v-if="copiedField === `${index}-value`"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{
                                    copiedField === `${index}-value`
                                        ? 'Copied!'
                                        : 'Copy value'
                                }}
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    v-if="domain.can_check"
                    type="button"
                    :disabled="disabled"
                    class="min-h-11 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold disabled:opacity-50"
                    @click="submit('check')"
                >
                    Refresh / retry
                </button>
                <button
                    v-if="domain.can_disconnect"
                    type="button"
                    :disabled="disabled"
                    class="min-h-11 rounded-lg border border-[var(--workspace-line)] px-3 text-sm font-semibold disabled:opacity-50"
                    @click="submit('disconnect')"
                >
                    Disconnect domain
                </button>
            </div>
            <p v-if="domain.last_checked_at" class="mt-2 text-sm">
                Last checked
                {{ new Date(domain.last_checked_at).toLocaleString() }}.
            </p>
            <p v-if="domain.enabled" class="mt-2 text-sm">
                Checks continue automatically even when you close this page.
                While setup is pending, this page refreshes status for up to ten
                minutes. You can also refresh manually.
            </p>
            <p class="mt-3 text-sm">
                To replace this domain, disconnect it first and wait for removal
                to finish. The old address stops working first, so replacement
                includes downtime. Disconnecting does not stop billing. Use
                <a
                    href="#site-billing-title"
                    class="font-semibold underline underline-offset-4"
                    >Cancel renewal in Billing</a
                >
                separately.
            </p>
        </template>
        <p v-if="pending" role="status" class="mt-3 text-sm">
            {{ domain.paid ? 'Updating domain…' : 'Processing request…' }}
        </p>
        <p role="status" aria-live="polite" class="mt-2 text-sm">
            {{ feedback }}
        </p>
        <div
            v-if="Object.keys(errors).length"
            ref="messageElement"
            tabindex="-1"
            role="alert"
            class="mt-2 text-sm text-red-700 dark:text-red-300"
        >
            <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
        </div>
    </section>
</template>
