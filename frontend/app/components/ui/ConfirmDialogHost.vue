<script setup lang="ts">
const { state, resolveConfirm } = useConfirmDialog()
</script>

<template>
  <BaseModal :open="state.open" :title="state.title" @close="resolveConfirm(false)">
    <p class="text-sm text-txt-secondary">{{ state.message }}</p>

    <div class="mt-6 flex justify-end gap-3">
      <BaseButton type="button" variant="ghost" :block="false" @click="resolveConfirm(false)">
        {{ state.cancelLabel }}
      </BaseButton>
      <BaseButton
        type="button"
        :variant="state.variant === 'danger' ? 'danger' : 'primary'"
        :block="false"
        @click="resolveConfirm(true)"
      >
        {{ state.confirmLabel }}
      </BaseButton>
    </div>
  </BaseModal>
</template>
