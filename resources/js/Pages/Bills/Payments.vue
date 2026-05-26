<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ bill: Object, logs: Array });
defineOptions({ layout: AppLayout });
</script>

<template>
    <div>
        <Link :href="`/bills/${bill.public_token}`" class="text-sm text-teal-700">← Back to tracker</Link>
        <h1 class="mt-2 text-xl font-bold">Payment logs</h1>
        <p class="text-sm text-stone-600">{{ bill.title }}</p>

        <div v-for="(log, i) in logs" :key="i" class="mt-4 rounded-xl border bg-white p-4 text-sm">
            <p class="font-semibold">{{ log.participant }} · {{ log.amount }}</p>
            <p>Order: {{ log.order_id }} · Status: {{ log.status }}</p>
            <p v-if="log.tran_id">Tran: {{ log.tran_id }}</p>
            <p v-if="log.signature_valid !== null">Signature: {{ log.signature_valid ? 'valid' : 'invalid' }}</p>
            <p v-if="log.channel" class="text-xs text-stone-600">Channel: {{ log.channel }}</p>
            <p class="text-xs text-stone-500">{{ log.received_at }}</p>
        </div>
    </div>
</template>
