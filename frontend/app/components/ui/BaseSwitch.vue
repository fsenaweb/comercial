<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  label?: string
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

function toggle() {
  if (props.disabled) return
  emit('update:modelValue', !props.modelValue)
}
</script>

<template>
  <label
    class="inline-flex items-center gap-2.5"
    :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
    @click.prevent="toggle"
  >
    <span v-if="label" class="text-sm font-medium text-txt-secondary">{{ label }}</span>
    <span
      role="switch"
      :aria-checked="modelValue"
      :aria-disabled="disabled"
      class="flex h-[22px] w-[38px] shrink-0 items-center rounded-full p-0.5 transition-colors"
      :class="modelValue ? 'bg-emerald-600' : 'bg-border-strong'"
    >
      <span class="h-[18px] w-[18px] rounded-full bg-white shadow transition-transform" :class="{ 'translate-x-4': modelValue }" />
    </span>
  </label>
</template>
