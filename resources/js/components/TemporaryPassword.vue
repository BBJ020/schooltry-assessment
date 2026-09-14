<script setup>
defineProps({ password: { type: String, required: true } });
defineEmits(['close']);

async function copyPassword(password) {
    await navigator.clipboard?.writeText(password);
}
</script>

<template>
    <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true">
        <div class="card w-full max-w-md">
            <h2 class="text-xl font-black">Temporary password created</h2>
            <p class="mt-2 text-sm text-amber-700">Copy this temporary password now. It will not be shown again.</p>
            <code class="mt-4 block break-all rounded-xl bg-slate-100 p-4 text-sm">{{ password }}</code>
            <div class="mt-5 flex justify-end gap-3">
                <button class="btn-secondary" type="button" @click="copyPassword(password)">Copy</button>
                <button class="btn-primary" type="button" @click="$emit('close')">Done</button>
            </div>
        </div>
    </div>
</template>
