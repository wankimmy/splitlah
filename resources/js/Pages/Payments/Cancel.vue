<script setup>
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineProps({ order_id: String });
defineOptions({ layout: PublicLayout });
</script>

<template>
    <div class="rounded-2xl border bg-white p-6 text-center">
        <h1 class="text-xl font-bold">Payment cancelled</h1>
        <p class="mt-2 text-sm text-stone-600">You can try again from your payment link.</p>
        <p v-if="order_id" class="mt-2 text-xs text-stone-500">Order: {{ order_id }}</p>
    </div>
</template>
