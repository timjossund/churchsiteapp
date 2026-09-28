<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Globe,
    Layers,
    MousePointer2,
    Palette,
    Plus,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: Layers,
        title: 'A page at a time. A block at a time.',
        text: 'Bring your story together with text, photos, video, service times, and contact details. Add pages as your site grows.',
    },
    {
        icon: Palette,
        title: 'Make it feel like your church.',
        text: 'Choose a theme, pair your fonts, and add your colors. Give every page a consistent look without touching code.',
    },
    {
        icon: Globe,
        title: 'Ready when you are.',
        text: 'Make changes in your draft, then publish when everything looks right. Connect your own domain when you’re ready to go live.',
    },
];
const steps = [
    {
        title: 'Start with a blank canvas',
        text: 'Create your account, name your site, and choose a theme that feels like you.',
    },
    {
        title: 'Bring your church to life',
        text: 'Add your words, photos, and service times. Arrange your blocks and see your page take shape.',
    },
    {
        title: 'Open the doors online',
        text: 'Publish a shareable link. When you’re ready, connect your own www domain.',
    },
];
</script>

<template>
    <Head title="Church websites, made simple">
        <meta
            name="description"
            content="Build a welcoming website for your church with Church Site App. Simple blocks, beautiful themes, and publishing on your schedule. Start building before you pay."
        />
    </Head>
    <div
        class="marketing-page min-h-screen bg-background text-[var(--workspace-ink)]"
    >
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-[var(--workspace-surface)] focus:p-3"
            >Skip to content</a
        >
        <header
            class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-6 py-6 lg:px-10"
        >
            <Link
                href="/"
                aria-label="Church Site App home"
                class="flex items-center gap-2 rounded-lg"
                ><AppLogo
            /></Link>
            <nav
                aria-label="Main navigation"
                class="flex flex-wrap items-center gap-5 text-sm font-semibold sm:gap-7"
            >
                <a href="#features" class="hidden sm:inline">Features</a>
                <a href="#how-it-works" class="hidden md:inline"
                    >How it works</a
                >
                <a href="#pricing">Pricing</a>
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="marketing-button"
                    >My dashboard <ArrowRight class="size-4" aria-hidden="true"
                /></Link>
                <template v-else>
                    <Link :href="login()">Log in</Link>
                    <Link :href="register()" class="marketing-button"
                        >Get started
                        <ArrowRight class="size-4" aria-hidden="true"
                    /></Link>
                </template>
            </nav>
        </header>

        <main id="main">
            <section
                class="mx-auto grid max-w-7xl items-center gap-12 px-6 pt-12 pb-20 lg:grid-cols-[0.9fr_1.1fr] lg:gap-14 lg:px-10 lg:pt-20 lg:pb-28"
            >
                <div>
                    <p class="marketing-eyebrow">
                        <span
                            class="inline-block size-2 rounded-full bg-current"
                        />
                        A little simpler. A lot more welcoming.
                    </p>
                    <h1
                        class="mt-6 max-w-xl font-serif text-5xl leading-[1.06] tracking-tight sm:text-6xl lg:text-7xl"
                    >
                        Your church.<br />An open door.<br /><span
                            class="text-[var(--workspace-green)]"
                            >Online.</span
                        >
                    </h1>
                    <p class="mt-7 max-w-md text-lg leading-relaxed">
                        A beautiful church website shouldn’t take you away from
                        the people you serve. Build, update, and share yours
                        with simple tools that feel right at home.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-5">
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                            class="marketing-button px-6 py-3"
                            >{{
                                $page.props.auth.user
                                    ? 'Open your dashboard'
                                    : 'Start building your site'
                            }}
                            <ArrowRight class="size-4" aria-hidden="true"
                        /></Link>
                        <a
                            href="#how-it-works"
                            class="text-sm font-semibold underline decoration-[var(--workspace-line)] underline-offset-8"
                            >See how it works</a
                        >
                    </div>
                    <p class="mt-5 flex items-center gap-2 text-sm">
                        <Check
                            class="size-4 text-[var(--workspace-green)]"
                            aria-hidden="true"
                        />
                        Start building before you pay.
                    </p>
                </div>

                <figure class="min-w-0">
                    <div
                        class="relative rounded-[2rem] bg-[var(--workspace-green-soft)] p-4 sm:p-7"
                    >
                        <div
                            class="overflow-hidden rounded-xl border border-[var(--workspace-line)] bg-[var(--workspace-surface)] shadow-xl shadow-black/5"
                            aria-hidden="true"
                        >
                            <div
                                class="flex items-center justify-between border-b border-[var(--workspace-line)] px-4 py-3 text-[10px] font-semibold sm:text-xs"
                            >
                                <span class="flex items-center gap-2"
                                    ><AppLogoIcon
                                        class="size-5 text-[var(--workspace-green)]"
                                    />
                                    Grace Community</span
                                >
                                <span
                                    class="rounded-md bg-[var(--workspace-green)] px-3 py-1.5 text-white dark:text-[var(--workspace-surface)]"
                                    >Publish</span
                                >
                            </div>
                            <div
                                class="grid grid-cols-[76px_1fr] sm:grid-cols-[112px_1fr]"
                            >
                                <div
                                    class="border-r border-[var(--workspace-line)] bg-[var(--workspace-soft)] p-2 sm:p-3"
                                >
                                    <p
                                        class="mb-4 text-[9px] font-bold tracking-widest uppercase"
                                    >
                                        Your blocks
                                    </p>
                                    <div
                                        v-for="(block, index) in [
                                            'Welcome',
                                            'About us',
                                            'Service times',
                                            'Contact',
                                        ]"
                                        :key="block"
                                        class="mb-2 flex items-center gap-1.5 rounded-md border p-2 text-[9px] sm:text-[10px]"
                                        :class="
                                            index === 0
                                                ? 'border-[var(--workspace-green)] bg-[var(--workspace-green-soft)]'
                                                : 'border-[var(--workspace-line)] bg-[var(--workspace-surface)]'
                                        "
                                    >
                                        <span class="opacity-50">⠿</span
                                        >{{ block }}
                                    </div>
                                    <span
                                        class="mt-4 flex items-center justify-center gap-1 text-[9px] font-semibold"
                                        ><Plus class="size-3" /> Add block</span
                                    >
                                </div>
                                <div
                                    class="min-w-0 bg-[#fffaf0] text-[#283d30]"
                                >
                                    <div
                                        class="flex items-center justify-between px-4 py-3 text-[9px]"
                                    >
                                        <span class="font-serif font-bold"
                                            >Grace Community</span
                                        ><span>Welcome home</span>
                                    </div>
                                    <div
                                        class="relative overflow-hidden bg-[#e5eddf] px-4 pt-7 pb-8 sm:px-6"
                                    >
                                        <div
                                            class="absolute top-5 -right-5 h-48 w-28 rounded-t-full border-[18px] border-[#d0ddc6] sm:right-3 sm:w-36"
                                        />
                                        <div class="relative max-w-[190px]">
                                            <p
                                                class="text-[8px] font-bold tracking-[0.18em] uppercase"
                                            >
                                                There’s a place for you here
                                            </p>
                                            <p
                                                class="mt-3 font-serif text-3xl leading-tight sm:text-4xl"
                                            >
                                                Life is better<br />together.
                                            </p>
                                            <p
                                                class="mt-3 max-w-36 text-[10px] leading-relaxed"
                                            >
                                                Join us this Sunday. Come as you
                                                are, and find your community.
                                            </p>
                                            <span
                                                class="mt-4 inline-block rounded-md bg-[#2c6749] px-3 py-2 text-[9px] font-semibold text-white"
                                                >Plan your visit</span
                                            >
                                        </div>
                                    </div>
                                    <div class="px-4 py-5 sm:px-6">
                                        <p class="font-serif text-lg">
                                            We’d love to meet you.
                                        </p>
                                        <div
                                            class="mt-3 grid grid-cols-2 gap-3 text-[9px]"
                                        >
                                            <div
                                                class="rounded-lg border border-[#d8dfcf] p-3"
                                            >
                                                <span
                                                    class="block font-semibold"
                                                    >Sunday worship</span
                                                ><span class="mt-1 block"
                                                    >10:00 AM</span
                                                >
                                            </div>
                                            <div
                                                class="rounded-lg border border-[#d8dfcf] p-3"
                                            >
                                                <span
                                                    class="block font-semibold"
                                                    >A place to belong</span
                                                ><span class="mt-1 block"
                                                    >Everyone is welcome.</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            aria-hidden="true"
                            class="absolute -right-2 bottom-12 flex items-center gap-2 rounded-xl border border-[var(--workspace-line)] bg-[var(--workspace-surface)] px-4 py-3 text-xs font-semibold shadow-lg sm:right-0"
                        >
                            <MousePointer2
                                class="size-4 text-[var(--workspace-green)]"
                            />
                            Make it yours.
                        </div>
                    </div>
                    <figcaption class="mt-4 text-center text-xs">
                        An illustrative look inside the builder. Your story goes
                        here.
                    </figcaption>
                </figure>
            </section>

            <section
                id="features"
                class="border-y border-[var(--workspace-line)] bg-[var(--workspace-surface)]"
            >
                <div class="mx-auto max-w-7xl px-6 py-20 lg:px-10">
                    <div class="max-w-2xl">
                        <p class="marketing-eyebrow">
                            Built around your ministry
                        </p>
                        <h2
                            class="mt-4 font-serif text-4xl tracking-tight sm:text-5xl"
                        >
                            Less time figuring it out.<br />More time reaching
                            people.
                        </h2>
                    </div>
                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        <article
                            v-for="feature in features"
                            :key="feature.title"
                            class="rounded-2xl border border-[var(--workspace-line)] bg-[var(--workspace-soft)] p-7"
                        >
                            <span
                                class="mb-6 grid size-11 place-items-center rounded-xl bg-[var(--workspace-green-soft)] text-[var(--workspace-green-ink)]"
                                ><component
                                    :is="feature.icon"
                                    class="size-5"
                                    aria-hidden="true"
                            /></span>
                            <h3 class="font-serif text-2xl leading-tight">
                                {{ feature.title }}
                            </h3>
                            <p class="mt-4 text-sm leading-7">
                                {{ feature.text }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section
                id="how-it-works"
                class="mx-auto grid max-w-7xl gap-12 px-6 py-20 lg:grid-cols-2 lg:px-10 lg:py-28"
            >
                <div>
                    <p class="marketing-eyebrow">
                        From idea to open invitation
                    </p>
                    <h2
                        class="mt-4 max-w-md font-serif text-4xl tracking-tight sm:text-5xl"
                    >
                        A fresh start.<br />Three simple steps.
                    </h2>
                    <p class="mt-6 max-w-md leading-7">
                        Whether you’re starting from scratch or ready for
                        something easier, you can move at your own pace.
                    </p>
                </div>
                <ol class="space-y-8">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="flex gap-5"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-full border border-[var(--workspace-line)] bg-[var(--workspace-surface)] font-serif text-xl text-[var(--workspace-green)]"
                            >{{ index + 1 }}</span
                        >
                        <div>
                            <h3 class="font-serif text-2xl">
                                {{ step.title }}
                            </h3>
                            <p class="mt-2 max-w-md text-sm leading-7">
                                {{ step.text }}
                            </p>
                        </div>
                    </li>
                </ol>
            </section>

            <section id="pricing" class="mx-auto max-w-7xl px-6 pb-20 lg:px-10">
                <div
                    class="grid overflow-hidden rounded-[2rem] border border-[var(--workspace-line)] bg-[var(--workspace-surface)] lg:grid-cols-2"
                >
                    <div class="p-8 sm:p-12">
                        <p class="marketing-eyebrow">
                            Room to start. Room to grow.
                        </p>
                        <h2
                            class="mt-4 font-serif text-4xl tracking-tight sm:text-5xl"
                        >
                            Simple pricing.<br />A site that’s yours.
                        </h2>
                        <p class="mt-6 max-w-md leading-7">
                            Create your site and publish a shareable link before
                            paying. When you’re ready to connect your own
                            domain, choose the plan that works for your church.
                        </p>
                        <p class="mt-4 text-sm leading-6">
                            Subscriptions are per site. Bring a domain you
                            already own; we’ll guide you through connecting its
                            www address.
                        </p>
                    </div>
                    <div class="bg-[var(--workspace-green-soft)] p-8 sm:p-12">
                        <p class="text-sm font-semibold">
                            Your church, on your own domain
                        </p>
                        <p class="mt-5">
                            <span class="font-serif text-6xl tracking-tight"
                                >$15</span
                            ><span class="ml-2 text-sm"
                                >USD / month per site</span
                            >
                        </p>
                        <p class="mt-3 text-sm">Or $150 USD / year per site.</p>
                        <ul class="my-7 space-y-3 text-sm">
                            <li
                                v-for="item in [
                                    'Your own www domain',
                                    'Secure HTTPS when domain setup is complete',
                                    'Your pages, styles, and published updates',
                                ]"
                                :key="item"
                                class="flex items-start gap-2"
                            >
                                <Check
                                    class="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />{{ item }}
                            </li>
                        </ul>
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                            class="marketing-button"
                            >{{
                                $page.props.auth.user
                                    ? 'Go to your sites'
                                    : 'Start with your site'
                            }}
                            <ArrowRight class="size-4" aria-hidden="true"
                        /></Link>
                        <p class="mt-4 text-xs leading-5">
                            No trial. Payment begins when you start connecting
                            your domain.
                        </p>
                    </div>
                </div>
            </section>
        </main>
        <footer class="border-t border-[var(--workspace-line)]">
            <div
                class="mx-auto flex max-w-7xl flex-col justify-between gap-6 px-6 py-9 sm:flex-row sm:items-center lg:px-10"
            >
                <Link
                    href="/"
                    aria-label="Church Site App home"
                    class="flex w-fit items-center gap-2 rounded-lg"
                    ><AppLogo
                /></Link>
                <p class="text-sm">
                    A welcoming place starts with an open door.
                </p>
                <a href="#main" class="w-fit text-sm font-semibold"
                    >Back to top ↑</a
                >
            </div>
        </footer>
    </div>
</template>

<style scoped>
.marketing-page :is(a, button):focus-visible {
    outline: 2px solid var(--workspace-green);
    outline-offset: 5px;
}
.marketing-page section {
    scroll-margin-top: 2rem;
}
.marketing-button {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    border-radius: 0.65rem;
    background: var(--workspace-green);
    padding: 0.7rem 1rem;
    color: var(--workspace-surface);
    font-size: 0.875rem;
    font-weight: 600;
}
.marketing-button:hover {
    background: var(--workspace-green-dark);
}
.marketing-eyebrow {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--workspace-green);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
</style>
