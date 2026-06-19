<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="page-header">
        <div>
            <p class="eyebrow">Management</p>
            <h2>{{ title }}</h2>
            <p v-if="subtitle" class="muted">{{ subtitle }}</p>
        </div>
        <div class="header-actions">
            <slot />
        </div>
    </div>
</template>
