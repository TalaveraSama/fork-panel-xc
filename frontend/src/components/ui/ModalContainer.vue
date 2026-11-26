<script setup>
import { computed } from 'vue'
import { useUiStore } from '@stores/ui'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
</script>

<template>
  <teleport to="body">
    <transition-group name="modal" tag="div">
      <div
        v-for="modal in uiStore.modals"
        :key="modal.id"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <!-- Overlay -->
        <div
          class="modal-overlay"
          @click="modal.closable !== false && uiStore.closeModal(modal.id)"
        />

        <!-- Modal -->
        <div
          :class="[
            'modal relative w-full max-w-lg',
            modal.size === 'sm' && 'max-w-md',
            modal.size === 'lg' && 'max-w-2xl',
            modal.size === 'xl' && 'max-w-4xl',
            modal.size === 'full' && 'max-w-full',
          ]"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="modal.title ? 'modal-title' : undefined"
        >
          <div class="bg-white dark:bg-dark-900 rounded-xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div v-if="modal.title || modal.closable !== false" class="flex items-center justify-between px-6 py-4 border-b border-dark-200 dark:border-dark-700">
              <h3 v-if="modal.title" id="modal-title" class="text-lg font-semibold text-dark-900 dark:text-white">
                {{ modal.title }}
              </h3>
              <button
                v-if="modal.closable !== false"
                @click="uiStore.closeModal(modal.id)"
                class="p-1 rounded-lg text-dark-400 hover:text-dark-900 dark:text-dark-500 dark:hover:text-dark-100 hover:bg-dark-100 dark:hover:bg-dark-800 transition-colors"
                aria-label="Close"
              >
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <!-- Body -->
            <div class="p-6" :class="modal.noPadding && 'p-0'">
              <component
                v-if="modal.component"
                :is="modal.component"
                v-bind="modal.props"
              />
              <div v-else-if="modal.content" v-html="modal.content" />
              <slot v-else :name="modal.id" />
            </div>

            <!-- Footer -->
            <div v-if="modal.actions" class="px-6 py-4 border-t border-dark-200 dark:border-dark-700 bg-dark-50 dark:bg-dark-950 flex items-center justify-end gap-3">
              <template v-for="action in modal.actions" :key="action.label">
                <button
                  :type="action.type"
                  @click="action.handler?.(modal.id)"
                  :class="[
                    'btn',
                    action.variant === 'primary' && 'btn-primary',
                    action.variant === 'danger' && 'btn-danger',
                    action.variant === 'ghost' && 'btn-ghost',
                    action.variant === 'secondary' && 'btn-secondary',
                  ]"
                  :disabled="action.loading"
                >
                  {{ action.label }}
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </transition-group>
  </teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal,
.modal-leave-to .modal {
  transform: translate(-50%, -50%) scale(0.95);
}
</style>