<script setup>
import { onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    participant: Object,
    bill: Object,
    payment_url: String,
    qr_value: String,
    fiuu_enabled: Boolean,
    duitnow_configured: Boolean,
    fiuu_create_url: String,
});
const csrf = usePage().props.csrf_token;
defineOptions({ layout: PublicLayout });

const qrDataUrl = ref('');

onMounted(async () => {
    qrDataUrl.value = await QRCode.toDataURL(props.qr_value || props.payment_url, { width: 200, margin: 1 });
});
</script>

<template>
    <div class="space-y-5">
        <div class="rounded-2xl border bg-white p-6 text-center shadow-sm">
            <p class="text-sm text-stone-500">You owe</p>
            <p class="text-4xl font-bold text-stone-900">{{ participant.amount }}</p>
            <StatusBadge :status="participant.status" class="mt-2 inline-block" />
            <p class="mt-4 text-sm text-stone-600">For: {{ bill.title }}</p>
            <p class="text-sm text-stone-500">Organizer: {{ bill.organizer_name }}</p>
            <p v-if="bill.due_date" class="text-xs text-amber-700">Due: {{ bill.due_date }}</p>
        </div>

        <div v-if="participant.is_paid" class="rounded-xl bg-emerald-50 p-4 text-center text-sm text-emerald-800">
            Payment completed. You have already paid {{ participant.amount }} for this bill.
        </div>

        <template v-else>
            <form v-if="fiuu_enabled" :action="fiuu_create_url" method="POST" class="space-y-3">
                <input type="hidden" name="_token" :value="csrf" />
                <button type="submit" class="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white">
                    Pay with Fiuu Sandbox
                </button>
            </form>

            <div class="rounded-xl border bg-white p-4 text-center">
                <p class="text-sm font-medium">
                    {{ duitnow_configured ? 'DuitNow QR via Fiuu Sandbox' : 'QR opens secure payment page' }}
                </p>
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR" class="mx-auto mt-3" />
            </div>
        </template>
    </div>
</template>
