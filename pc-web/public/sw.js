/**
 * V0.5.7 块6 — PWA Service Worker
 *
 * 策略:
 *   - HTML:  Network First (永远拿最新, 失败回 cache)
 *   - 静态资源 (JS/CSS/字体): Cache First (1 年)
 *   - 图片:  Cache First (30 天)
 *   - API:  Network Only (不缓存, 实时数据)
 *
 * 版本: v1 (V0.5.7 块6)
 */
const CACHE_VERSION = 'oa-v1'
const STATIC_CACHE = `${CACHE_VERSION}-static`
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`
const IMAGE_CACHE = `${CACHE_VERSION}-images`

const PRECACHE_URLS = [
  '/',
  '/offline.html',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
]

// ============== 安装 ==============
self.addEventListener('install', (event) => {
  console.log('[SW] install', CACHE_VERSION)
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE_URLS).catch(() => null))
      .then(() => self.skipWaiting())
  )
})

// ============== 激活 (清理旧缓存) ==============
self.addEventListener('activate', (event) => {
  console.log('[SW] activate', CACHE_VERSION)
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((k) => !k.startsWith(CACHE_VERSION))
          .map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  )
})

// ============== 拦截请求 ==============
self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  // 只处理 GET
  if (request.method !== 'GET') return

  // 不处理 chrome-extension / devtools
  if (!url.protocol.startsWith('http')) return

  // API 永远不缓存
  if (url.pathname.startsWith('/api/')) {
    return
  }

  // HTML: Network First
  if (request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(networkFirst(request))
    return
  }

  // 图片: Cache First
  if (request.destination === 'image' || /\.(png|jpg|jpeg|gif|webp|svg|ico)$/i.test(url.pathname)) {
    event.respondWith(cacheFirst(request, IMAGE_CACHE, 30 * 24 * 3600))
    return
  }

  // 静态资源 (JS/CSS/字体): Cache First
  if (/\.(js|css|woff2?|ttf|eot)$/i.test(url.pathname) || request.destination === 'script' || request.destination === 'style') {
    event.respondWith(cacheFirst(request, STATIC_CACHE, 365 * 24 * 3600))
    return
  }

  // 其他: Network First 兜底
  event.respondWith(networkFirst(request))
})

// ============== 策略实现 ==============

/** Network First: 试网络, 失败回 cache, 都没有就回 offline.html */
async function networkFirst(request) {
  try {
    const response = await fetch(request)
    // 成功后异步更新缓存 (不阻塞响应)
    if (response.ok) {
      const cache = await caches.open(RUNTIME_CACHE)
      cache.put(request, response.clone())
    }
    return response
  } catch (err) {
    const cached = await caches.match(request)
    if (cached) return cached
    // HTML 请求没缓存 → 返回 offline 页
    if (request.mode === 'navigate') {
      const offline = await caches.match('/offline.html')
      if (offline) return offline
    }
    return new Response('Offline', { status: 503, statusText: 'Offline' })
  }
}

/** Cache First: 命中缓存直接返回, 否则网络 + 缓存 */
async function cacheFirst(request, cacheName, maxAgeSeconds) {
  const cached = await caches.match(request)
  if (cached) {
    // 检查时间
    const dateHeader = cached.headers.get('date')
    if (dateHeader) {
      const age = (Date.now() - new Date(dateHeader).getTime()) / 1000
      if (age < maxAgeSeconds) {
        return cached
      }
    } else {
      return cached // 无 date 头, 信任
    }
  }
  // 网络拉
  try {
    const response = await fetch(request)
    if (response.ok) {
      const cache = await caches.open(cacheName)
      cache.put(request, response.clone())
    }
    return response
  } catch (err) {
    // 离线 + 无缓存 → 返回兜底
    return cached || new Response('Offline', { status: 503 })
  }
}

// ============== 消息 (手动 skipWaiting) ==============
self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
})
