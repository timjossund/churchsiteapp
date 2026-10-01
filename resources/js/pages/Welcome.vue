<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Globe, Layers, Palette } from '@lucide/vue';
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
            <section class="welcome-hero" aria-labelledby="hero-heading">
                <div>
                    <p class="eyebrow">A lot simpler. A lot more welcoming.</p>
                    <h1 id="hero-heading">
                        Your church.<br />A welcoming<br /><em>home online.</em>
                    </h1>
                    <p class="intro">
                        Make your first hello a beautiful one. Build a website
                        that feels like your church, with simple tools that
                        leave more time for the people you serve.
                    </p>
                    <div class="actions">
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                            class="marketing-button hero-primary"
                            >{{
                                $page.props.auth.user
                                    ? 'Open your dashboard'
                                    : 'Start building your site'
                            }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                        <a class="secondary" href="#how-it-works"
                            >See how it works</a
                        >
                    </div>
                    <p class="free">
                        <span aria-hidden="true">✓</span>Start building for
                        free. No credit card required.
                    </p>
                </div>
                <figure
                    aria-label="Illustrative church website with a simple editing panel"
                >
                    <div class="backdrop" aria-hidden="true"></div>
                    <div class="browser" aria-hidden="true">
                        <div class="toolbar">
                            <div class="dots"><i></i><i></i><i></i></div>
                            <span class="address"
                                >Grace Church · Site preview</span
                            ><span class="live">Preview</span>
                        </div>
                        <div class="church">
                            <div class="church-nav">
                                <span class="church-brand">Grace Church</span>
                                <div class="church-links">
                                    <span>Our story</span><span>Sundays</span
                                    ><span>Visit us ↗</span>
                                </div>
                            </div>
                            <div class="church-hero">
                                <div class="arch"></div>
                                <div class="church-copy">
                                    <p class="church-eyebrow">
                                        There's a place for you here
                                    </p>
                                    <p class="church-title">
                                        Life is better<br />together.
                                    </p>
                                    <p class="church-description">
                                        Come as you are. Find your community.
                                        We'd love to meet you this Sunday.
                                    </p>
                                    <span class="visit"
                                        >Plan your visit &nbsp; →</span
                                    >
                                </div>
                            </div>
                            <div class="church-bottom">
                                <p>A little hello. A new beginning.</p>
                                <div class="church-details">
                                    <div>
                                        <strong>Join us on Sundays</strong>10:00
                                        AM · Everyone welcome
                                    </div>
                                    <div>
                                        <strong>Come find your people</strong>A
                                        place to belong.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="edit-card" aria-hidden="true">
                        <div class="edit-label">
                            <span>✣ &nbsp; Make it yours</span><span>•••</span>
                        </div>
                        <div class="field">
                            Life is better together.<span class="caret"></span>
                        </div>
                        <div class="edit-footer">
                            <div class="swatches"><i></i><i></i><i></i></div>
                            <span>✓ Changes saved</span>
                        </div>
                    </div>
                    <figcaption>
                        Your words. Your welcome. Your website.
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
                <p class="text-center text-sm">
                    Many people's church is found first online, help them find
                    yours. <br />
                    Copyright © {{ new Date().getFullYear() }} Church Site App.
                    All rights reserved.
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
.welcome-hero {
    --surface: var(--workspace-surface);
    --soft: var(--workspace-soft);
    --border: var(--workspace-line);
    --muted: var(--workspace-muted);
    --accent: var(--workspace-green);
    --accent-soft: var(--workspace-green-soft);
    --preview-bg: #fffaf0;
    --preview-text: #283d30;
    --preview-soft: #e5eddf;
    --preview-arch: #d0ddc6;
    --preview-border: #d8dfcf;
    --hero-serif: ui-serif, Georgia, Cambria, 'Times New Roman', serif;
    --s1: 0.25rem;
    --s2: 0.5rem;
    --s3: 0.75rem;
    --s4: 1rem;
    --s5: 1.25rem;
    --s6: 1.5rem;
    --s8: 2rem;
    --s10: 2.5rem;
    --s12: 3rem;
    --s16: 4rem;
    --s20: 5rem;
    --s24: 6rem;
    --radius: 0.65rem;
    --radius-lg: 1.5rem;
    --pill: 999rem;
    --shadow: 0 24px 60px rgb(31 50 35 / 10%);
    --panel-shadow: 0 12px 32px rgb(31 50 35 / 12%);
    width: min(calc(100% - 5rem), 1200px);
    margin-inline: auto;
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    align-items: center;
    gap: var(--s16);
    padding-block: var(--s20) var(--s24);
}
.eyebrow {
    display: flex;
    align-items: center;
    gap: var(--s3);
    color: var(--accent);
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
.eyebrow::before {
    content: '';
    width: 24px;
    height: 1px;
    background: var(--accent);
}
.welcome-hero h1 {
    text-wrap: nowrap;
    margin: var(--s6) 0;
    font: 400 clamp(3.5rem, 5.2vw, 4.75rem)/1.04 var(--hero-serif);
    letter-spacing: -0.055em;
}
.welcome-hero h1 em {
    color: var(--accent);
    font-weight: 400;
}
.intro {
    max-width: 390px;
    margin: 0;
    color: var(--muted);
    font-size: 1.03rem;
    line-height: 1.85;
}
.actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--s6);
    margin-top: var(--s8);
}
.secondary {
    font-size: 0.85rem;
    font-weight: 600;
    border-bottom: 1px solid var(--border);
    padding-block: var(--s2);
}
.free {
    color: var(--muted);
    margin-top: var(--s5);
    font-size: 0.73rem;
}
.free span {
    color: var(--accent);
    margin-right: var(--s2);
}
.welcome-hero figure {
    position: relative;
    min-width: 0;
    margin: 0;
    padding-block: var(--s6) var(--s10);
}
.backdrop {
    position: absolute;
    inset: 0 var(--s8) var(--s6) var(--s8);
    border-radius: 48% 48% var(--radius-lg) var(--radius-lg);
    background: var(--accent-soft);
}
.browser {
    position: relative;
    overflow: hidden;
    margin-left: var(--s5);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    background: var(--surface);
    box-shadow: var(--shadow);
}
.toolbar {
    display: flex;
    align-items: center;
    gap: var(--s3);
    padding: var(--s3) var(--s4);
    border-bottom: 1px solid var(--border);
    background: var(--soft);
}
.dots {
    display: flex;
    gap: var(--s1);
}
.dots i {
    width: 5px;
    height: 5px;
    background: var(--border);
    border-radius: var(--pill);
}
.address {
    flex: 1;
    text-align: center;
    color: var(--muted);
    font-size: 0.58rem;
}
.live {
    display: flex;
    align-items: center;
    gap: var(--s1);
    color: var(--accent);
    font-size: 0.58rem;
}
.live::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: var(--pill);
    background: var(--accent);
}
.church {
    color: var(--preview-text);
    background: var(--preview-bg);
}
.church-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--s5) var(--s6);
}
.church-brand {
    font: 600 0.96rem var(--hero-serif);
}
.church-links {
    display: flex;
    gap: var(--s3);
    font-size: 0.55rem;
}
.church-hero {
    position: relative;
    overflow: hidden;
    background: var(--preview-soft);
    padding: var(--s12) var(--s8) var(--s10);
}
.arch {
    position: absolute;
    width: 176px;
    height: 274px;
    right: -8px;
    top: var(--s6);
    border: 27px solid var(--preview-arch);
    border-bottom: 0;
    border-radius: var(--pill) var(--pill) 0 0;
}
.arch::after {
    content: '';
    position: absolute;
    inset: var(--s4) var(--s4) 0;
    border: 1px solid var(--preview-arch);
    border-bottom: 0;
    border-radius: var(--pill) var(--pill) 0 0;
}
.church-copy {
    position: relative;
    max-width: 280px;
}
.church-eyebrow {
    margin: 0;
    font-size: 0.54rem;
    font-weight: 700;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}
.church-title {
    font: 400 2.9rem/1.08 var(--hero-serif);
    letter-spacing: -0.035em;
    margin: var(--s4) 0;
}
.church-description {
    max-width: 175px;
    font-size: 0.64rem;
    line-height: 1.8;
    margin-bottom: var(--s5);
}
.visit {
    --accent: #2c6749;
    --surface: #ffffff;
    display: inline-block;
    padding: var(--s3) var(--s4);
    border-radius: var(--radius);
    background: var(--accent);
    color: var(--surface);
    font-size: 0.62rem;
    font-weight: 600;
}
.church-bottom {
    padding: var(--s6) var(--s8) var(--s8);
}
.church-bottom p {
    font: 400 1.25rem var(--hero-serif);
    margin: 0 0 var(--s4);
}
.church-details {
    display: flex;
    gap: var(--s8);
    font-size: 0.6rem;
}
.church-details strong {
    display: block;
    margin-bottom: var(--s1);
}
.edit-card {
    position: absolute;
    left: calc(-1 * var(--s8));
    bottom: var(--s10);
    width: 205px;
    padding: var(--s4);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    box-shadow: var(--panel-shadow);
    transform: rotate(-3deg);
}
.edit-label {
    display: flex;
    justify-content: space-between;
    color: var(--muted);
    font-size: 0.56rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 700;
}
.field {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: var(--s3);
    margin-top: var(--s3);
    font-size: 0.7rem;
}
.caret {
    display: inline-block;
    height: 0.85rem;
    border-left: 1px solid var(--accent);
    vertical-align: middle;
}
.edit-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: var(--s3);
    font-size: 0.57rem;
    color: var(--muted);
}
.swatches {
    display: flex;
    gap: var(--s1);
}
.swatches i {
    width: 13px;
    height: 13px;
    border-radius: var(--pill);
    border: 1px solid var(--preview-border);
    background: var(--preview-bg);
}
.swatches i:first-child {
    background: var(--accent);
}
.swatches i:last-child {
    background: var(--preview-arch);
}
.welcome-hero figcaption {
    position: absolute;
    right: var(--s1);
    bottom: 0;
    color: var(--muted);
    font-size: 0.67rem;
}

.hero-primary {
    padding: 1rem 1.5rem;
}
@media (max-width: 1000px) {
    .welcome-hero {
        width: calc(100% - 3rem);
        gap: 2rem;
    }
    .edit-card {
        left: 0;
    }
    .church-title {
        font-size: 2.5rem;
    }
    .arch {
        right: -52px;
    }
}
@media (max-width: 760px) {
    .welcome-hero {
        grid-template-columns: 1fr;
        padding-block: 2.5rem 3rem;
        gap: 2.5rem;
    }
    .welcome-hero h1 {
        text-wrap: nowrap;
        font-size: clamp(3.2rem, 9vw, 4.5rem);
    }
    .intro {
        max-width: 480px;
    }
    .welcome-hero figure {
        max-width: 540px;
        width: 100%;
        margin-inline: auto;
    }
    .arch {
        right: 1rem;
    }
}
@media (max-width: 420px) {
    .welcome-hero {
        width: calc(100% - 2rem);
    }
    .actions {
        gap: 1.25rem;
    }
    .church-links {
        display: none;
    }
    .church-hero {
        padding: 2rem 1.25rem;
    }
    .church-title {
        font-size: 2.45rem;
    }
    .arch {
        right: -70px;
    }
    .church-bottom {
        padding-inline: 1.25rem;
        padding-bottom: 5rem;
    }
    .edit-card {
        width: 185px;
        bottom: 2.5rem;
    }
    .welcome-hero figcaption {
        font-size: 0.6rem;
    }
    .church-details {
        gap: 1.25rem;
    }
}
</style>
