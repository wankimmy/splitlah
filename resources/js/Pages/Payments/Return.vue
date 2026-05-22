<script setup>
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineProps({ order_id: String, status: String, message: String });
defineOptions({ layout: PublicLayout });
</script>

<template>
    <div class="rounded-2xl border bg-white p-6 text-center">
        <h1 class="text-xl font-bold">Payment submitted</h1>
        <p class="mt-3 text-sm text-stone-600">{{ message }}</p>
        <p class="mt-2 text-xs text-stone-500">Order: {{ order_id }} · Status: {{ status }}</p>
    </div>
</template>
