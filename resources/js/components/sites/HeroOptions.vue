<script setup lang="ts">
export type HeroButton = {
    button_label: string;
    link_type: 'none' | 'section' | 'page' | 'external';
    target_block_id: number | null;
    target_page_id: number | null;
    external_url: string;
};
export type HeroExtras = {
    welcome_label: string;
    target_page_id: number | null;
    secondary_button: HeroButton;
};
export type HeroStyle = {
    text_background?: boolean;
    height?: 'current' | 'medium' | 'full';
    overlay?: 'light' | 'medium' | 'dark';
    motion?: 'normal' | 'fixed' | 'half';
};
const extras = defineModel<HeroExtras>({ required: true });
const props = defineProps<{
    section: 'content' | 'buttons';
    primaryType: HeroButton['link_type'];
    pages: { id: number; name: string }[];
    sections: { id: number; name: string }[];
    disabled: boolean;
    errors: Partial<Record<string, string>>;
}>();
const emit = defineEmits<{ change: [] }>();
function error(key: string) {
    return props.errors['content.' + key];
}
</script>

<template>
    <fieldset
        class="hero-options space-y-4"
        :disabled="disabled"
        @input="emit('change')"
        @change="emit('change')"
    >
        <legend class="sr-only">Hero {{ section }} settings</legend>
        <div v-if="section === 'content'">
            <label for="hero-welcome">Welcome label</label>
            <input
                id="hero-welcome"
                v-model="extras.welcome_label"
                :aria-invalid="!!error('welcome_label')"
                aria-describedby="hero-welcome-help hero-welcome-error"
            />
            <p id="hero-welcome-help" class="text-sm">
                Leave blank to hide the label.
            </p>
            <p id="hero-welcome-error" role="alert">
                {{ error('welcome_label') }}
            </p>
        </div>
        <template v-if="section === 'buttons'">
            <div v-if="primaryType === 'page'">
                <label for="hero-page">First button page</label>
                <select
                    id="hero-page"
                    v-model="extras.target_page_id"
                    :aria-invalid="!!error('target_page_id')"
                    aria-describedby="hero-page-error"
                >
                    <option :value="null">Choose a page</option>
                    <option
                        v-for="page in pages"
                        :key="page.id"
                        :value="page.id"
                    >
                        {{ page.name }}
                    </option>
                </select>
                <p id="hero-page-error" role="alert">
                    {{ error('target_page_id') }}
                </p>
            </div>
            <h3 class="font-semibold">Second button</h3>
            <div>
                <label for="hero-second-type">Link destination</label>
                <select
                    id="hero-second-type"
                    v-model="extras.secondary_button.link_type"
                    :aria-invalid="
                        !!error('secondary_button.link_type') ||
                        !!error('secondary_button')
                    "
                    aria-describedby="hero-second-type-error"
                >
                    <option value="none">No button</option>
                    <option value="section">A section on this page</option>
                    <option value="page">A page on this site</option>
                    <option value="external">An external website</option>
                </select>
                <p id="hero-second-type-error" role="alert">
                    {{
                        error('secondary_button.link_type') ||
                        error('secondary_button')
                    }}
                </p>
            </div>
            <template v-if="extras.secondary_button.link_type !== 'none'">
                <div>
                    <label for="hero-second-label">Button text</label>
                    <input
                        id="hero-second-label"
                        v-model="extras.secondary_button.button_label"
                        :aria-invalid="!!error('secondary_button.button_label')"
                        aria-describedby="hero-second-label-error"
                    />
                    <p id="hero-second-label-error" role="alert">
                        {{ error('secondary_button.button_label') }}
                    </p>
                </div>
                <div v-if="extras.secondary_button.link_type === 'page'">
                    <label for="hero-second-page">Page</label>
                    <select
                        id="hero-second-page"
                        v-model="extras.secondary_button.target_page_id"
                        :aria-invalid="
                            !!error('secondary_button.target_page_id')
                        "
                        aria-describedby="hero-second-page-error"
                    >
                        <option :value="null">Choose a page</option>
                        <option
                            v-for="page in pages"
                            :key="page.id"
                            :value="page.id"
                        >
                            {{ page.name }}
                        </option>
                    </select>
                    <p id="hero-second-page-error" role="alert">
                        {{ error('secondary_button.target_page_id') }}
                    </p>
                </div>
                <div v-if="extras.secondary_button.link_type === 'section'">
                    <label for="hero-second-section">Section</label>
                    <select
                        id="hero-second-section"
                        v-model="extras.secondary_button.target_block_id"
                        :aria-invalid="
                            !!error('secondary_button.target_block_id')
                        "
                        aria-describedby="hero-second-section-error"
                    >
                        <option :value="null">Choose a section</option>
                        <option
                            v-for="section in sections"
                            :key="section.id"
                            :value="section.id"
                        >
                            {{ section.name }}
                        </option>
                    </select>
                    <p v-if="!sections.length" class="text-sm">
                        Add another block to link to a section.
                    </p>
                    <p id="hero-second-section-error" role="alert">
                        {{ error('secondary_button.target_block_id') }}
                    </p>
                </div>
                <div v-if="extras.secondary_button.link_type === 'external'">
                    <label for="hero-second-url">Website URL</label>
                    <input
                        id="hero-second-url"
                        v-model="extras.secondary_button.external_url"
                        type="url"
                        placeholder="https://example.org"
                        :aria-invalid="!!error('secondary_button.external_url')"
                        aria-describedby="hero-second-url-error"
                    />
                    <p id="hero-second-url-error" role="alert">
                        {{ error('secondary_button.external_url') }}
                    </p>
                </div>
            </template>
        </template>
    </fieldset>
</template>
