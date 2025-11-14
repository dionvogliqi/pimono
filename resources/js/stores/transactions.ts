import { defineStore } from 'pinia'
import { echo } from '@/lib/echo'
import { usePage } from '@inertiajs/vue3'

export interface TransactionItem {
  id: number
  sender_id: number
  receiver_id: number
  amount: string
  commission_fee: string
  total_debited: string
  status?: string
  created_at?: string
}

export interface Paginated<T> {
  items: T[]
  meta: {
    current_page: number
    per_page: number
    total: number
  }
}

type SharedPageProps = {
  csrfToken?: string
  auth?: {
    user?: {
      id: number
    }
  }
}

function resolveCsrfToken(): string {
  const page = usePage<SharedPageProps>()
  const token = page.props.csrfToken

  if (typeof token === 'string' && token.length > 0) {
    return token
  }

  if (typeof document === 'undefined') {
    return ''
  }

  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

export const useTransactionsStore = defineStore('transactions', {
  state: () => ({
    balance: '0.0000' as string,
    transactions: [] as TransactionItem[],
    loading: false,
    sending: false,
    subscribed: false,
    page: 1,
    perPage: 20,
    total: 0,
    error: '' as string | null,
  }),
  actions: {
    async fetchFirstPage() {
      this.page = 1
      await this.fetch()
    },
    async fetch(page = this.page) {
      this.loading = true
      this.error = ''
      try {
        const res = await fetch(`/api/transactions?page=${page}&per_page=${this.perPage}`, {
          credentials: 'include',
          headers: {
            'Accept': 'application/json',
          },
        })
        if (!res.ok) throw new Error(await res.text())
        const data = await res.json()
        this.balance = data.balance
        this.transactions = data.transactions
        this.page = data.meta.current_page
        this.perPage = data.meta.per_page
        this.total = data.meta.total
      } catch (e: any) {
        this.error = e?.message || 'Failed to load transactions'
      } finally {
        this.loading = false
      }
    },
    async send(receiver_id: number, amount: string) {
      if (this.sending) return
      this.sending = true
      this.error = ''
      try {
        const csrf = resolveCsrfToken()
        const res = await fetch('/api/transactions', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
          },
          body: JSON.stringify({ receiver_id, amount }),
        })

        if (!res.ok) {
          let message = 'Transfer failed'
          try {
            const payload = await res.json()
            message = payload?.message || message
          } catch {
            const text = await res.text()
            if (text) {
              message = text
            }
          }
          throw new Error(message)
        }

        const data = await res.json()
        this.balance = data.balance
        this.transactions = [data.transaction, ...this.transactions]
      } catch (e: any) {
        this.error = e?.message || 'Transfer failed'
        throw e
      } finally {
        this.sending = false
      }
    },
    ensureSubscribed() {
      if (this.subscribed) return
      const page = usePage<SharedPageProps>()
      const authUser = page.props.auth?.user
      if (!authUser?.id) return

      echo.private(`private-user.${authUser.id}`).listen('.TransferCompleted', (payload: any) => {
        // If the auth user is sender, payload.balances.sender is correct; if receiver, payload.balances.receiver is correct
        const newBalance = authUser.id === payload.transaction.sender_id ? payload.balances.sender : payload.balances.receiver
        if (newBalance) this.balance = newBalance
        // Prepend the new transaction if it involves this user
        this.transactions = [payload.transaction, ...this.transactions]
        this.total += 1
      })

      this.subscribed = true
    },
  },
})
