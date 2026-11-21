
const CACHE_NAME = "clickfeira-v3";

const ARQUIVOS_CACHE = [
    "./offline.html",
    "./manifest.json",
    "./assets/img/logo-clickfeira.png",
    "./assets/img/banner-principal.png",
    "./assets/img/app-icon.png",
    "./assets/img/app-icon-192.png"
];

const URL_OFFLINE = new URL("./offline.html", self.registration.scope);

// Instalação
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ARQUIVOS_CACHE);
        })
    );

    self.skipWaiting();
});

// Ativação
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((nomesCaches) => {
            return Promise.all(
                nomesCaches
                    .filter((nome) => nome.startsWith("clickfeira-") && nome !== CACHE_NAME)
                    .map((nome) => caches.delete(nome))
            );
        })
    );

    self.clients.claim();
});

// Requisições
self.addEventListener("fetch", (event) => {
    const requisicao = event.request;

    if (requisicao.method !== "GET") return;

    const url = new URL(requisicao.url);

    if (url.origin !== self.location.origin) return;

    // Navegação entre páginas
    if (requisicao.mode === "navigate") {
        event.respondWith(
            fetch(requisicao).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                const paginaOffline = await cache.match(URL_OFFLINE);

                if (paginaOffline) {
                    return paginaOffline;
                }

                return new Response(
                    "Você está sem conexão com a internet.",
                    {
                        status: 503,
                        headers: { "Content-Type": "text/plain; charset=utf-8" }
                    }
                );
            })
        );

        return;
    }

    // Imagens
    if (requisicao.destination === "image") {
        event.respondWith(
            caches.match(requisicao).then((respostaCache) => {
                if (respostaCache) return respostaCache;

                return fetch(requisicao).then((resposta) => {
                    if (resposta.ok) {
                        const copia = resposta.clone();

                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(requisicao, copia);
                        });
                    }

                    return resposta;
                });
            })
        );
    }
});
