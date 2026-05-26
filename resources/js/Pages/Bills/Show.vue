<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import CopyButton from '../../Components/CopyButton.vue';
import FlashBanner from '../../Components/FlashBanner.vue';
import MoneyText from '../../Components/MoneyText.vue';

const props = defineProps({ bill: Object, participants: Array, filter: String, timeline: Array, summary_text: String });
defineOptions({ layout: AppLayout });

const filters = ['all', 'paid', 'unpaid', 'pending', 'failed'];
const progress = computed(() =>
    props.bill.participant_count ? (props.bill.paid_count / props.bill.participant_count) * 100 : 0,
);
const unpaid = computed(() => props.participants.filter((p) => !['paid', 'manual_paid'].includes(p.status)));

const manualForm = useForm({ method: 'cash', reference_no: '', note: '' });

function setFilter(f) {
    router.get(`/bills/${props.bill.public_token}`, { filter: f }, { preserveState: true });
}

function markPaid(token) {
    if (!confirm('Mark this participant as paid manually?')) {
        return;
    }
    manualForm.post(`/bills/${props.bill.public_token}/participants/${token}/manual-paid`, { preserveScroll: true });
}

function remindUnpaid() {
    unpaid.value.forEach((p) => {
        const url = p.whatsapp_url || `https://wa.me/?text=${encodeURIComponent(p.share_message)}`;
        window.open(url, '_blank');
    });
}
</script>

<template>
    <div class="space-y-4">
        <FlashBanner class="mb-2" />
        <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <h1 class="text-xl font-bold">{{ bill.title }}</h1>
            <p class="text-sm text-stone-600">{{ bill.organizer_name }} · {{ bill.merchant_name || 'No merchant' }}</p>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                <div><p class="text-stone-500">Total</p><p class="font-bold">{{ bill.total }}</p></div>
                <div><p class="text-stone-500">Collected</p><p class="font-bold text-emerald-700">{{ bill.collected }}</p></div>
                <div><p class="text-stone-500">Remaining</p><p class="font-bold text-amber-700">{{ bill.remaining }}</p></div>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-stone-100">
                <div class="h-full bg-teal-600 transition-all" :style="{ width: progress + '%' }" />
            </div>
            <p class="mt-2 text-xs text-stone-500">{{ bill.paid_count }} / {{ bill.participant_count }} paid</p>
        </div>

        <div class="flex gap-2 overflow-x-auto text-xs">
            <button
                v-for="f in filters"
                :key="f"
                class="rounded-full px-3 py-1 capitalize"
                :class="filter === f ? 'bg-teal-600 text-white' : 'bg-stone-100'"
                @click="setFilter(f)"
            >
                {{ f }}
            </button>
        </div>

        <p v-if="unpaid.length" class="text-sm text-stone-600">
            {{ unpaid.length }} people still unpaid
            <button class="ml-2 font-medium text-teal-700" @click="remindUnpaid">Remind unpaid</button>
        </p>

        <div v-for="p in participants" :key="p.token" class="rounded-xl border border-stone-200 bg-white p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-semibold">{{ p.name }}</p>
                    <MoneyText :amount="p.amount" large />
                </div>
                <StatusBadge :status="p.status" />
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <CopyButton :text="p.payment_url" label="Copy link" />
                <a :href="p.whatsapp_url" target="_blank" class="rounded-lg border px-3 py-2 text-sm font-medium text-teal-700">WhatsApp</a>
                <button
                    v-if="!['paid', 'manual_paid'].includes(p.status)"
                    type="button"
                    class="rounded-lg border px-3 py-2 text-xs text-stone-600"
                    @click="markPaid(p.token)"
                >
                    Mark paid
                </button>
            </div>
        </div>

        <div class="rounded-xl border bg-white p-4">
            <p class="text-sm font-semibold">Activity</p>
            <ul class="mt-2 space-y-1 text-xs text-stone-600">
                <li v-for="(t, i) in timeline" :key="i">{{ t.created_at }} — {{ t.action }}</li>
            </ul>
        </div>

        <div class="flex gap-2">
            <CopyButton :text="summary_text" label="Copy summary" />
            <Link :href="`/bills/${bill.public_token}/payments`" class="rounded-lg border px-3 py-2 text-sm">Payment logs</Link>
            <Link :href="`/bills/${bill.public_token}/receipt`" class="rounded-lg border px-3 py-2 text-sm">Receipt</Link>
        </div>
    </div>
</template>
