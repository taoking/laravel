<script setup>
const props = defineProps({
    value: { type: [String, Number, Boolean], default: '' },
});

function normalize(value) {
    if (value === true) {
        return 'success';
    }

    if (value === false) {
        return 'danger';
    }

    const text = String(value ?? '').toLowerCase();

    if (['active', 'success', 'finished', 'completed', 'ok', 'healthy', 'passed'].includes(text)) {
        return 'success';
    }

    if (['running', 'pending', 'processing', 'queued', 'created'].includes(text)) {
        return 'warning';
    }

    if (['failed', 'error', 'disabled', 'unhealthy', 'timeout'].includes(text)) {
        return 'danger';
    }

    return 'neutral';
}
</script>

<template>
    <span :class="['status-badge', `status-${normalize(props.value)}`]">
        {{ props.value === true ? '正常' : props.value === false ? '异常' : props.value || '-' }}
    </span>
</template>
