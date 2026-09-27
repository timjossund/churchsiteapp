<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import SiteBillingPanel, {
    type BillingSummary,
} from '@/components/sites/SiteBillingPanel.vue';
import SiteDomainPanel, {
    type DomainSummary,
} from '@/components/sites/SiteDomainPanel.vue';
import { dashboard } from '@/routes';
import { show } from '@/routes/sites';

const props = defineProps<{
    site: { id: number; name: string };
    billing: BillingSummary;
    domain: DomainSummary;
}>();
const billingPending = ref(false);
const domainPending = ref(false);
const domainDirty = ref(false);
const busy = computed(() => billingPending.value || domainPending.value);
let ownVisit = false;
function runOwnVisit(submit: () => void) {
    ownVisit = true;
    try {
        submit();
    } finally {
        ownVisit = false;
    }
}
function confirmDiscard(): boolean {
    return (
        !domainDirty.value ||
        window.confirm('Discard your unsaved domain entry?')
    );
}
// Domain submission saves the entry itself; only other navigation needs a discard prompt.
function confirmDomainSubmit(): boolean {
    return true;
}
let stopBefore: (() => void) | undefined;
let stopNavigate: (() => void) | undefined;
let pageHistoryState: unknown;
let pageUrl = '';
function guardHistory(event: PopStateEvent) {
    if (busy.value || !confirmDiscard()) {
        event.stopImmediatePropagation();
        window.history.pushState(pageHistoryState, '', pageUrl);
    }
}
function guardUnload(event: BeforeUnloadEvent) {
    if (!domainDirty.value && !busy.value) return;
    event.preventDefault();
    event.returnValue = '';
}
onMounted(() => {
    pageHistoryState = window.history.state;
    pageUrl = window.location.href;
    stopNavigate = router.on('navigate', () => {
        pageHistoryState = window.history.state;
        pageUrl = window.location.href;
    });
    stopBefore = router.on('before', (event) => {
        if (ownVisit) return;
        if (busy.value || !confirmDiscard()) event.preventDefault();
    });
    window.addEventListener('popstate', guardHistory, true);
    window.addEventListener('beforeunload', guardUnload);
});
onUnmounted(() => {
    stopBefore?.();
    stopNavigate?.();
    window.removeEventListener('popstate', guardHistory, true);
    window.removeEventListener('beforeunload', guardUnload);
});
defineOptions({
    layout: { breadcrumbs: [{ title: 'My sites', href: dashboard() }] },
});
</script>

<template>
    <Head :title="`${props.site.name} - Go Live`" />
    <main
        class="mx-auto w-full max-w-[76rem] px-5 py-8 sm:px-8 lg:px-12 lg:py-12"
    >
        <header class="mb-8">
            <Link
                :href="show(props.site.id)"
                class="text-sm font-semibold text-[var(--workspace-green)] hover:underline"
                >← Site settings</Link
            >
            <p
                class="mt-7 text-xs font-bold tracking-[0.14em] text-[var(--workspace-green)] uppercase"
            >
                Go Live
            </p>
            <h1 class="mt-2 font-serif text-4xl tracking-tight break-words">
                {{ props.site.name }}
            </h1>
            <p class="mt-3 text-[var(--workspace-muted)]">
                Connect your custom domain and manage your site's subscription.
            </p>
        </header>
        <div class="grid gap-6 lg:grid-cols-2">
            <SiteDomainPanel
                :domain="props.domain"
                :site-id="props.site.id"
                :busy="busy"
                :can-poll="!domainDirty && !busy"
                :confirm-leave="confirmDomainSubmit"
                :run-visit="runOwnVisit"
                @busy="domainPending = $event"
                @dirty="domainDirty = $event"
            />
            <SiteBillingPanel
                :billing="props.billing"
                :site-id="props.site.id"
                :busy="busy"
                :confirm-leave="confirmDiscard"
                :run-visit="runOwnVisit"
                @busy="billingPending = $event"
            />
        </div>
    </main>
</template>
