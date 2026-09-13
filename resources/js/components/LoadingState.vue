<script setup>
defineProps({ label: { type: String, default: 'Loading…' } });
</script>

<template>
    <div class="flex items-center gap-3 py-6 text-sm text-slate-500" role="status">
        <span class="h-5 w-5 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600" />
        <span>{{ label }}</span>
    </div>
</template>
