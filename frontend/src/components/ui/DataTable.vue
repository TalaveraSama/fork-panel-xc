<script setup>
import { computed } from 'vue'
import { ChevronUpIcon, ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  data: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  total: {
    type: Number,
    default: 0,
  },
  page: {
    type: Number,
    default: 1,
  },
  perPage: {
    type: Number,
    default: 25,
  },
  sort: {
    type: Object,
    default: () => ({ field: 'id', direction: 'desc' }),
  },
  selectedIds: {
    type: Array,
    default: () => [],
  },
  selectable: {
    type: Boolean,
    default: true,
  },
  rowClickable: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['sort', 'page-change', 'per-page-change', 'selection-change', 'row-click'])

const lastPage = computed(() => Math.ceil(props.total / props.perPage) || 1)
const showingFrom = computed(() => (props.page - 1) * props.perPage + 1)
const showingTo = computed(() => Math.min(props.page * props.perPage, props.total))

function handleSort(column) {
  if (column.sortable) {
    emit('sort', column.key)
  }
}

function handleSelectAll(event) {
  emit('selection-change', event)
}

function handleRowClick(row) {
  if (props.rowClickable) {
    emit('row-click', row)
  }
}

function getSortIcon(column) {
  if (!column.sortable || props.sort.field !== column.key) {
    return null
  }
  return props.sort.direction === 'asc' ? ChevronUpIcon : ChevronDownIcon
}

function changePage(newPage) {
  if (newPage >= 1 && newPage <= lastPage.value && newPage !== props.page) {
    emit('page-change', newPage)
  }
}

function changePerPage(newPerPage) {
  emit('per-page-change', newPerPage)
}
</script>

<template>
  <div class="overflow-hidden">
    <!-- Table -->
    <div class="table-container">
      <table class="table" :class="{ 'cursor-pointer': rowClickable }">
        <thead>
          <tr>
            <th v-for="column in columns" :key="column.key" :style="{ width: column.width }" :class="[column.sortable && 'cursor-pointer select-none']" @click="handleSort(column)">
              <div class="flex items-center gap-1">
                <!-- Select All Checkbox -->
                <input
                  v-if="column.key === 'select' && selectable"
                  type="checkbox"
                  :checked="selectedIds.length > 0 && selectedIds.length === data.length"
                  :indeterminate="selectedIds.length > 0 && selectedIds.length < data.length"
                  @change="handleSelectAll"
                  class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500"
                />
                <span>{{ column.label }}</span>
                <component
                  v-if="getSortIcon(column)"
                  :is="getSortIcon(column)"
                  class="w-4 h-4 text-dark-400"
                />
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in data"
            :key="row.id"
            @click="handleRowClick(row)"
            :class="[
              'transition-colors',
              selectedIds.includes(row.id) && 'bg-primary-50/50 dark:bg-primary-900/10',
            ]"
          >
            <td v-for="column in columns" :key="column.key" :class="[column.align || '']">
              <!-- Select Checkbox -->
              <template v-if="column.key === 'select' && selectable">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(row.id)"
                  @change="$emit('update:selectedIds', selectedIds.includes(row.id) ? selectedIds.filter(id => id !== row.id) : [...selectedIds, row.id])"
                  @click.stop
                  class="w-4 h-4 rounded border-dark-300 text-primary-600 focus:ring-primary-500"
                />
              </template>

              <!-- Slot for custom cell rendering -->
              <slot :name="`cell-${column.key}`" :row="row" :index="data.indexOf(row)">
                <span v-if="column.key !== 'select'" class="text-dark-900 dark:text-white">{{ row[column.key] }}</span>
              </slot>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="!loading && data.length === 0">
            <td :colspan="columns.length" class="text-center py-12 text-dark-400 dark:text-dark-500">
              <slot name="empty">No data available</slot>
            </td>
          </tr>

          <!-- Loading State -->
          <tr v-if="loading">
            <td :colspan="columns.length" class="text-center py-12">
              <div class="flex items-center justify-center gap-3 text-dark-400">
                <svg class="animate-spin h-6 w-6 text-primary-600" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Loading...
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="total > perPage" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-4 py-4 border-t border-dark-200 dark:border-dark-700">
      <div class="text-sm text-dark-500 dark:text-dark-400">
        Showing <span class="font-medium text-dark-900 dark:text-white">{{ showingFrom }}</span> to <span class="font-medium text-dark-900 dark:text-white">{{ showingTo }}</span> of <span class="font-medium text-dark-900 dark:text-white">{{ total }}</span> results
      </div>

      <div class="flex items-center gap-2">
        <!-- Per Page Select -->
        <select :value="perPage" @change="e => changePerPage(Number(e.target.value))" class="input w-auto py-1.5 text-sm">
          <option value="10">10 per page</option>
          <option value="25">25 per page</option>
          <option value="50">50 per page</option>
          <option value="100">100 per page</option>
        </select>

        <!-- Page Navigation -->
        <nav class="flex items-center gap-1" aria-label="Pagination">
          <button
            @click="changePage(page - 1)"
            :disabled="page <= 1 || loading"
            class="btn-secondary btn-sm p-2"
            aria-label="Previous page"
          >
            <ChevronLeftIcon class="w-4 h-4" />
          </button>

          <template v-if="lastPage <= 7">
            <button
              v-for="p in lastPage"
              :key="p"
              @click="changePage(p)"
              :class="[
                'w-8 h-8 rounded-lg text-sm font-medium transition-colors',
                p === page
                  ? 'bg-primary-600 text-white'
                  : 'text-dark-600 dark:text-dark-400 hover:bg-dark-100 dark:hover:bg-dark-800',
              ]"
              :disabled="loading"
            >
              {{ p }}
            </button>
          </template>

          <template v-else>
            <button
              @click="changePage(1)"
              :class="[
                'w-8 h-8 rounded-lg text-sm font-medium transition-colors',
                page === 1 ? 'bg-primary-600 text-white' : 'text-dark-600 dark:text-dark-400 hover:bg-dark-100 dark:hover:bg-dark-800',
              ]"
              :disabled="loading"
            >
              1
            </button>

            <span v-if="page > 3" class="px-2 text-dark-400">...</span>

            <button
              v-for="p in Math.min(lastPage, Math.max(3, page + 1))"
              :key="p"
              @click="changePage(p)"
              :class="[
                'w-8 h-8 rounded-lg text-sm font-medium transition-colors',
                p === page ? 'bg-primary-600 text-white' : 'text-dark-600 dark:text-dark-400 hover:bg-dark-100 dark:hover:bg-dark-800',
              ]"
              :disabled="loading"
              v-if="p >= Math.max(2, page - 1)"
            >
              {{ p }}
            </button>

            <span v-if="page < lastPage - 2" class="px-2 text-dark-400">...</span>

            <button
              @click="changePage(lastPage)"
              :class="[
                'w-8 h-8 rounded-lg text-sm font-medium transition-colors',
                page === lastPage ? 'bg-primary-600 text-white' : 'text-dark-600 dark:text-dark-400 hover:bg-dark-100 dark:hover:bg-dark-800',
              ]"
              :disabled="loading"
            >
              {{ lastPage }}
            </button>
          </template>

          <button
            @click="changePage(page + 1)"
            :disabled="page >= lastPage || loading"
            class="btn-secondary btn-sm p-2"
            aria-label="Next page"
          >
            <ChevronRightIcon class="w-4 h-4" />
          </button>
        </nav>
      </div>
    </div>
  </div>
</template>