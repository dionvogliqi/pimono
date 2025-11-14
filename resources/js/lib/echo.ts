import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Make Pusher available for Echo (types only)
// @ts-expect-error - Echo expects Pusher on window in some setups
window.Pusher = Pusher

const csrfToken = document
  .querySelector('meta[name="csrf-token"]')
  ?.getAttribute('content')

export const echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
  // Use the default Laravel broadcasting auth endpoint and pass CSRF
  authorizer: (channel: any, _opts?: unknown) => {
    // mark _opts as used to satisfy eslint
    void _opts;
    return {
      authorize(socketId: string, callback: (error: boolean, data: any) => void) {
        fetch('/broadcasting/auth', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        })
          .then(async (response) => {
            if (!response.ok) {
              const errorText = await response.text()
              throw new Error(errorText || 'Auth failed')
            }
            return response.json()
          })
          .then((data) => callback(false, data))
          .catch((error) => callback(true, error))
      },
    }
  },
})
