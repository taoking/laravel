<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [Object, Array, String, Number, Boolean, null], default: () => ({}) },
    rows: { type: Number, default: 8 },
});

const emit = defineEmits(['update:modelValue', 'invalid']);

function stringify(value) {
    if (typeof value === 'string') {
        return value;
    }

    return JSON.stringify(value ?? {}, null, 2);
}

const draft = ref(stringify(props.modelValue));

watch(() => props.modelValue, (value) => {
    draft.value = stringify(value);
});

function update(value) {
    draft.value = value;

    try {
        emit('update:modelValue', value.trim() ? JSON.parse(value) : {});
        emit('invalid', '');
    } catch (error) {
        emit('invalid', error.message);
    }
}
</script>

<template>
    <textarea :value="draft" class="json-editor" :rows="rows" spellcheck="false" @input="update($event.target.value)" />
</template>
