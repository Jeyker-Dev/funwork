self.addEventListener('push', (event) => {
    let payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
        } catch {
            payload = { body: event.data.text() };
        }
    }

    const title = payload.title || 'FunWork Daily Digest';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/apple-touch-icon.png',
        data: payload.data || { url: '/notifications' },
    };

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(title, options),
            self.clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then((windowClients) => {
                    windowClients.forEach((client) => {
                        client.postMessage({ type: 'funwork:notification-received' });
                    });
                }),
        ]),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetPath = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : '/notifications';
    const targetUrl = new URL(targetPath, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            for (const client of windowClients) {
                if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                    return client.focus().then(() => {
                        if ('navigate' in client) {
                            return client.navigate(targetUrl);
                        }

                        return undefined;
                    });
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }

            return undefined;
        }),
    );
});
