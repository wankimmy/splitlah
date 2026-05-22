<script setup>
defineProps({ amount: String, large: Boolean });
</script>

<template>
    <span :class="large ? 'text-3xl font-bold text-stone-900' : 'font-semibold text-stone-800'">{{ amount }}</span>
</template>
