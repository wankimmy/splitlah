<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Tesseract from 'tesseract.js';
import WizardLayout from '../../Layouts/WizardLayout.vue';
import FlashBanner from '../../Components/FlashBanner.vue';

const props = defineProps({ bill: Object, items: Array, receipt_url: String });

const ocrProgress = ref(0);
const ocrRunning = ref(false);
const uploadBusy = ref(false);
const previewUrl = ref(props.receipt_url);

const itemsForm = useForm({
    merchant_name: props.bill.merchant_name || '',
    receipt_date: props.bill.receipt_date || '',
    subtotal_cents: props.bill.subtotal_cents || 0,
    tax_cents: props.bill.tax_cents || 0,
    service_charge_cents: props.bill.service_charge_cents || 0,
    rounding_cents: props.bill.rounding_cents || 0,
    total_cents: props.bill.total_cents || 0,
    items: props.items.length
        ? props.items.map((i) => ({ name: i.name, quantity: i.quantity, total_price_cents: i.total_price_cents }))
        : [{ name: '', quantity: 1, total_price_cents: 0 }],
});

async function onFile(e) {
    const file = e.target.files?.[0];
    if (!file || uploadBusy.value) return;
    uploadBusy.value = true;
    previewUrl.value = URL.createObjectURL(file);
    const fd = new FormData();
    fd.append('receipt', file);

    await new Promise((resolve, reject) => {
        router.post(`/bills/${props.bill.public_token}/receipt/upload`, fd, {
            forceFormData: true,
            onFinish: resolve,
            onError: reject,
        });
    });

    ocrRunning.value = true;
    try {
        const { data } = await Tesseract.recognize(file, 'eng', {
            logger: (m) => {
                if (m.status === 'recognizing text') ocrProgress.value = Math.round(m.progress * 100);
            },
        });
        router.post(`/bills/${props.bill.public_token}/receipt/parse`, { ocr_text: data.text });
    } finally {
        ocrRunning.value = false;
        uploadBusy.value = false;
    }
}

function addItem() {
    itemsForm.items.push({ name: '', quantity: 1, total_price_cents: 0 });
}

function saveItems() {
    itemsForm.post(`/bills/${props.bill.public_token}/receipt/items`);
}

const confidenceLabel = {
    high: 'Receipt scan confidence: High',
    medium: 'Receipt scan confidence: Medium — please review before continuing.',
    low: 'Receipt scan confidence: Low — manual correction needed.',
};
</script>

<template>
    <WizardLayout :step="3">
        <template #flash>
            <FlashBanner :errors="itemsForm.errors" class="mb-4" />
        </template>
        <h1 class="text-xl font-bold">Review receipt</h1>
        <p v-if="bill.ocr_confidence" class="mt-2 text-sm text-amber-700">
            {{ confidenceLabel[bill.ocr_confidence] || bill.ocr_confidence }}
        </p>

        <div class="mt-4 rounded-xl border border-dashed border-stone-300 bg-white p-4 text-center">
            <label class="block text-sm font-medium text-stone-700">
                Receipt photo
                <input type="file" accept="image/*" capture="environment" class="mt-1 text-sm" :disabled="uploadBusy || ocrRunning" @change="onFile" />
            </label>
            <img v-if="previewUrl" :src="previewUrl" class="mx-auto mt-3 max-h-48 rounded-lg" alt="Receipt" />
            <p v-if="ocrRunning" class="mt-2 text-sm text-teal-700" aria-live="polite">Scanning… {{ ocrProgress }}%</p>
        </div>

        <form class="mt-6 space-y-3" @submit.prevent="saveItems">
            <input v-model="itemsForm.merchant_name" placeholder="Merchant" class="w-full rounded-lg border px-3 py-2 text-sm" />
            <input v-model="itemsForm.receipt_date" type="date" class="w-full rounded-lg border px-3 py-2 text-sm" />
            <div class="grid grid-cols-2 gap-2 text-sm">
                <input v-model.number="itemsForm.subtotal_cents" type="number" placeholder="Subtotal (sen)" class="rounded-lg border px-2 py-2" />
                <input v-model.number="itemsForm.tax_cents" type="number" placeholder="Tax (sen)" class="rounded-lg border px-2 py-2" />
                <input v-model.number="itemsForm.service_charge_cents" type="number" placeholder="Service (sen)" class="rounded-lg border px-2 py-2" />
                <input v-model.number="itemsForm.rounding_cents" type="number" placeholder="Rounding (sen)" class="rounded-lg border px-2 py-2" />
                <input v-model.number="itemsForm.total_cents" type="number" placeholder="Total (sen)" class="col-span-2 rounded-lg border px-2 py-2" required />
            </div>

            <div v-for="(item, i) in itemsForm.items" :key="i" class="flex gap-2">
                <input v-model="item.name" class="flex-1 rounded-lg border px-2 py-2 text-sm" placeholder="Item" />
                <input v-model.number="item.total_price_cents" type="number" class="w-24 rounded-lg border px-2 py-2 text-sm" />
            </div>
            <button type="button" class="text-sm text-teal-700" @click="addItem">+ Add item</button>

            <button type="submit" class="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white">Continue to split</button>
        </form>
    </WizardLayout>
</template>
