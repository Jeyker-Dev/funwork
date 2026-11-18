import sort from '@alpinejs/sort'

Alpine.plugin(sort)

if ('serviceWorker' in navigator && ! window.__funworkPushMessageListenerRegistered) {
    window.__funworkPushMessageListenerRegistered = true;

    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'funwork:notification-received') {
            window.Livewire?.dispatch('notification-received');
        }
    });
}
