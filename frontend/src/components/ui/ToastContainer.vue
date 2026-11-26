<script setup>
import { computed } from 'vue'
import { useUiStore } from '@stores/ui'
import { XMarkIcon, CheckCircleIcon, ExclamationCircleIcon, InformationCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

const toastIcons = {
  success: CheckCircleIcon,
  error: ExclamationCircleIcon,
  info: InformationCircleIcon,
  warning: ExclamationTriangleIcon,
}

const toastColors = {
  success: 'toast-success',
  error: 'toast-error',
  info: 'toast-info',
  warning: 'toast-warning',
}

function closeToast(id) {
  uiStore.removeToast(id)
}
</script>

<template>
  <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
    <transition-group name="toast" tag="div">
      <div
        v-for="toast in uiStore.toasts"
        :key="toast.id"
        :class="[
          'pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-lg shadow-lg animate-slide-up min-w-[300px] max-w-md',
          toastColors[toast.type],
        ]"
      >
        <component
          :is="toastIcons[toast.type]"
          class="w-5 h-5 flex-shrink-0 mt-0.5"
        />
        <div class="flex-1 min-w-0">
          <p v-if="toast.title" class="font-medium text-white">{{ toast.title }}</p>
          <p class="text-sm text-white/90">{{ toast.message }}</p>
        </div>
        <button
          @click="closeToast(toast.id)"
          class="flex-shrink-0 text-white/70 hover:text-white transition-colors"
          aria-label="Dismiss"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>