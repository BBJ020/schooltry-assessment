<script setup>
defineProps({ message: { type: String, default: '' } });
defineEmits(['dismiss']);
</script>

<template>
    <div v-if="message" class="flex items-start justify-between gap-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
        <span>{{ message }}</span>
        <button type="button" class="font-semibold" aria-label="Dismiss error" @click="$emit('dismiss')">×</button>
    </div>
</template>
