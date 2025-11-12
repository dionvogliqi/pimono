<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'
import { useTransactionsStore } from '@/stores/transactions'

const store = useTransactionsStore()

const receiverId = ref<number | null>(null)
const amount = ref<string>('')

const currentUserId = computed<number | null>(() => {
  const user = (usePage().props as any).auth?.user
  return user?.id ?? null
})

const canSubmit = computed(() => {
  return !store.sending && receiverId.value && Number(receiverId.value) > 0 && /^\d+(\.\d{1,4})?$/.test(amount.value)
})

async function submit() {
  if (!canSubmit.value) return
  try {
    await store.send(Number(receiverId.value), amount.value)
    amount.value = ''
  } catch {
    // error already set in store
  }
}

onMounted(async () => {
  await store.fetchFirstPage()
  store.ensureSubscribed()
})
</script>

<template>
  <Head title="Transfers" />
  <AppLayout :breadcrumbs="[{ title: 'Transfers', href: '/transfers' }]">
    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Make a transfer</h1>
        <div class="text-sm text-gray-600 dark:text-gray-300">
          Balance: <span class="font-mono">{{ store.balance }}</span>
        </div>
      </div>

      <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <div class="grid gap-4 md:grid-cols-3">
          <div class="flex flex-col gap-2">
            <label class="text-sm text-gray-600 dark:text-gray-300">Recipient ID</label>
            <input
              v-model="receiverId"
              type="number"
              min="1"
              placeholder="e.g. 2"
              class="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm text-gray-600 dark:text-gray-300">Amount</label>
            <input
              v-model="amount"
              type="text"
              inputmode="decimal"
              placeholder="100.00"
              class="w-full rounded-md border px-3 py-2 focus:outline-none focus:ring"
            />
          </div>
          <div class="flex items-end">
            <button
              class="rounded-md bg-black px-4 py-2 text-white disabled:opacity-50 dark:bg-white dark:text-black"
              :disabled="!canSubmit || store.sending"
              @click="submit"
            >
              {{ store.sending ? 'Sending…' : 'Send' }}
            </button>
          </div>
        </div>
        <p v-if="store.error" class="mt-3 text-sm text-red-600">{{ store.error }}</p>
      </div>

      <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-lg font-semibold">Recent Transactions</h2>
          <button class="text-sm underline" :disabled="store.loading" @click="store.fetchFirstPage()">
            Refresh
          </button>
        </div>
        <div v-if="store.loading" class="text-sm text-gray-600">Loading…</div>
        <div v-else>
          <div v-if="store.transactions.length === 0" class="text-sm text-gray-600">No transactions yet.</div>
          <ul class="divide-y">
            <li v-for="tx in store.transactions" :key="tx.id" class="flex items-center justify-between py-2">
              <div class="flex flex-col">
                <span class="text-sm">
                  <span v-if="currentUserId && tx.sender_id === currentUserId">Sent</span>
                  <span v-else>Received</span>
                  <span> {{ tx.amount }}</span>
                  <span class="text-xs text-gray-500">(fee {{ tx.commission_fee }})</span>
                </span>
                <span class="text-xs text-gray-500">to #{{ tx.receiver_id }} from #{{ tx.sender_id }}</span>
              </div>
              <div class="text-xs text-gray-500">{{ tx.created_at?.replace('T', ' ').slice(0, 19) }}</div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
