<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <span
        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--workspace-green)] text-white dark:text-[var(--workspace-surface)]"
    >
        <AppLogoIcon class="size-6" />
    </span>
    <span
        class="ml-px text-left font-serif text-lg leading-none font-semibold tracking-tight whitespace-nowrap text-[var(--workspace-ink)] group-data-[collapsible=icon]:sr-only"
        >Church Site App</span
    >
</template>
