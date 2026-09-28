<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        aria-hidden="true"
        focusable="false"
        v-bind="$attrs"
    >
        <path fill="currentColor" d="M18.5 1h3v3h3v3h-3v5h-3V7h-3V4h3Z" />
        <path
            fill="currentColor"
            fill-rule="evenodd"
            d="M5 38V25a15 15 0 0 1 30 0v13h-6V25a9 9 0 0 0-18 0v13H5Z"
        />
        <path
            fill="currentColor"
            d="M15 38V27a5 5 0 0 1 10 0v11H15Z"
            opacity=".55"
        />
    </svg>
</template>
