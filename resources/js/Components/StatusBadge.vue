<script setup>
const props = defineProps({ status: String });
const map = {
    unpaid: 'bg-stone-100 text-stone-700',
    pending: 'bg-amber-100 text-amber-800',
    paid: 'bg-emerald-100 text-emerald-800',
    manual_paid: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-100 text-red-800',
    cancelled: 'bg-stone-100 text-stone-500',
};
</script>

<template>
    <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="map[status] || map.unpaid">
        {{ status?.replace('_', ' ') }}
    </span>
</template>
