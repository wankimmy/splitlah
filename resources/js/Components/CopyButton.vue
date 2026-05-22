<script setup>
import { ref } from 'vue';

const props = defineProps({ text: String, label: { type: String, default: 'Copy' } });
const copied = ref(false);

async function copy() {
    await navigator.clipboard.writeText(props.text);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <button
        type="button"
        class="rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50"
        @click="copy"
    >
        {{ copied ? 'Copied!' : label }}
    </button>
</template>
